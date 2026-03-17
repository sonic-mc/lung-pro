FROM php:8.3

# Update and install required dependencies, including sockets
RUN apt-get update -y && apt-get install -y \
    openssl zip unzip git libonig-dev libzip-dev \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    libpq-dev \
    && docker-php-ext-install -j$(nproc) gd zip pdo_mysql pdo_pgsql sockets

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set the working directory
WORKDIR /app

# Copy project files
COPY . /app

# Rename .env.example to .env
RUN mv .env.example .env

# Install PHP dependencies with Composer
RUN composer install --ignore-platform-req=*


# Expose port 8080
EXPOSE 8080

# Run Laravel's built-in server
#CMD php artisan serve --host=0.0.0.0 --port=8080
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]