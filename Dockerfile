# PHP + Apache — chạy Cổng Tri Thức trên hosting (từ GitHub)
FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_sqlite \
    && a2enmod rewrite headers \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html
WORKDIR /var/www/html

COPY . /var/www/html/

# Thư mục data ghi được (SQLite)
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/data

EXPOSE 80

CMD ["apache2-foreground"]
