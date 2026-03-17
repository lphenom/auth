FROM php:8.1-alpine

# Install system dependencies + ext-curl (required by MirSmsSender and UniSenderEmailSender)
RUN apk add --no-cache \
    git \
    unzip \
    curl \
    libcurl \
    curl-dev \
    bash \
    openssh-client \
    && docker-php-ext-install curl

# Install Composer 2.9.5
COPY --from=composer:2.9.5 /usr/bin/composer /usr/bin/composer

WORKDIR /app

CMD ["php", "-v"]
