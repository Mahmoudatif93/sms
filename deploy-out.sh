#!/bin/bash
cd /home/app/code
git pull
rm -rf vendor
composer install
php artisan migrate