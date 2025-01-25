#!/bin/bash

echo "Installing dependencies..."
composer update
composer install
npm install

echo "Starting php-fpm..."
exec php-fpm

