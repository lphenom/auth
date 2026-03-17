FROM php:8.1-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    curl \
    bash \
    openssh-client

# Install Composer 2.9.5
COPY --from=composer:2.9.5 /usr/bin/composer /usr/bin/composer

WORKDIR /app

CMD ["php", "-v"]
