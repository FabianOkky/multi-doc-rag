<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->keepIsolatedSchemaUsableForVectors();
        $this->keepFileUploadsWorkingUnderArtisanServe();
    }

    /**
     * Let the `artisan serve` development server locate a temporary directory.
     *
     * `artisan serve` runs `php -S` as a child process and blanks out every
     * environment variable outside its own allow-list. On Windows, PHP resolves
     * its temporary directory from TMP / TEMP / USERPROFILE, so with those gone
     * it cannot create the temporary file an upload needs, and every upload
     * fails at request startup with "unable to create a temporary file" — before
     * any application code runs. Adding them back costs nothing elsewhere: on
     * Linux and macOS the variables are usually unset anyway and PHP falls back
     * to /tmp.
     *
     * PHPRC is included so a developer can point the child server at a custom
     * php.ini (to raise upload_max_filesize, for instance) without patching the
     * framework.
     */
    protected function keepFileUploadsWorkingUnderArtisanServe(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        ServeCommand::$passthroughVariables = array_values(array_unique([
            ...ServeCommand::$passthroughVariables,
            'PHPRC',
            'TEMP',
            'TMP',
            'USERPROFILE',
        ]));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Prepare a non-public Postgres connection's session for pgvector.
     *
     * The test suite may share the development database but always runs in an isolated schema
     * (DB_SEARCH_PATH=testing, pinned in phpunit.xml), so RefreshDatabase only ever drops that
     * schema — never public/dev data. Two things are needed on each connect, before any query runs: the schema must exist
     * (migrate:fresh creates its "migrations" table before any migration file runs), and "public"
     * must be appended to the SESSION search_path so the pgvector "vector" type and "<=>" operator
     * (which live in public) resolve. The CONFIG search_path stays the isolated schema only, which
     * is what bounds RefreshDatabase's drop scope. Entirely a no-op for the default public connection.
     */
    protected function keepIsolatedSchemaUsableForVectors(): void
    {
        Event::listen(function (ConnectionEstablished $event): void {
            $connection = $event->connection;

            if ($connection->getDriverName() !== 'pgsql') {
                return;
            }

            $searchPath = $connection->getConfig('search_path');
            $primary = trim(explode(',', is_array($searchPath) ? implode(',', $searchPath) : (string) $searchPath)[0], " \t\"'");

            if ($primary === '' || $primary === 'public') {
                return;
            }

            $connection->unprepared('create schema if not exists "'.$primary.'"');
            $connection->unprepared('set search_path to "'.$primary.'", public');
        });
    }
}
