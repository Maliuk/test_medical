FROM yiisoftware/yii2-php:8.5-apache-latest

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN pecl install mongodb && \
    docker-php-ext-enable mongodb && \
    docker-php-ext-install sockets
