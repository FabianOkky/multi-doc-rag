<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\ConnectionEstablished;
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
     * The test suite shares the dev Supabase project but runs in an isolated schema
     * (DB_SEARCH_PATH=testing), so RefreshDatabase only ever drops that schema — never public/dev
     * data. Two things are needed on each connect, before any query runs: the schema must exist
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
