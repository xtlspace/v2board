#!/bin/bash

set -e

if [ ! -d ".git" ]; then
  echo "Please deploy using Git."
  exit 1
fi

if ! command -v git &> /dev/null; then
    echo "Git is not installed! Please install it and try again."
    exit 1
fi

branch=$(git rev-parse --abbrev-ref HEAD)

git config --global --add safe.directory $(pwd)
git fetch --all && git reset --hard "origin/${branch}" && git pull origin "${branch}"
rm -rf composer.lock composer.phar
wget https://github.com/composer/composer/releases/latest/download/composer.phar -O composer.phar
php composer.phar update -vvv

if ! php -m | grep -q "pcntl"; then
    echo "Adding pcntl extension to cli-php.ini"
    sed -i '/extension=redis.so/a extension=pcntl.so' cli-php.ini || true
fi
php -c cli-php.ini webman.php stop || true
echo "Webman stopped. Please restart it by yourself."

php artisan v2board:update

if [ -f "/etc/init.d/bt" ]; then
  chown -R www $(pwd);
fi
