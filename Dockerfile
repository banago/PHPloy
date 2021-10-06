FROM alpine:3.11

LABEL maintainer="banago@gmail.com"

RUN apk add --update \
    bash \
    git \
    ca-certificates \
    php-bz2 \
    php-cli \
    php-ftp \
    php-mbstring \
    php-openssl \
    php-phar \
    php-zip \
    php-ctype \
    php-xmlrpc \
    php-zlib \
    && rm -rf /var/cache/apk/*

COPY dist/phploy.phar .

RUN chmod +x phploy.phar && \
    mv phploy.phar /usr/bin/phploy && \
    export PATH=$PATH":/usr/bin"
