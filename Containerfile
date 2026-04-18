FROM 127.0.0.1:8443/dreams/dreams-base:php82

COPY --chown=app:app . .
RUN composer install --no-progress --no-interaction --no-dev --no-scripts --optimize-autoloader --prefer-dist
# RUN composer update
# Optimize Laravel 
# RUN php artisan optimize
