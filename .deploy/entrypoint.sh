#!/bin/sh

echo "🎬 entrypoint.sh: [$(whoami)] [PHP $(php -r 'echo phpversion();')]"
composer dump-autoload --no-interaction --no-dev --optimize

# 💡 Group into a custom command e.g. php artisan app:on-deploy
echo "🎬 artisan commands"
php artisan storage:link
php artisan migrate --no-interaction --force --seed
php artisan config:cache
php artisan route:cache


echo "🎬 start supervisord"
supervisord -c $LARAVEL_PATH/.deploy/config/supervisor.conf