web: php artisan migrate --force && php artisan config:cache && PHPRC=$PWD/deploy/php.ini php artisan serve --host=0.0.0.0 --port=$PORT
worker: php artisan queue:work --tries=3 --max-time=3600 --sleep=3 --backoff=10
