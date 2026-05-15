FROM yiisoftware/yii2-php:8.5-apache-latest

RUN docker-php-ext-install sockets
