FROM alpine:3.11

LABEL maintainer="banago@gmail.com"

RUN apk add --update \
    bash \
    git \
    ca-certificates \
    php-bz2 \
    php-cli \
    php-ftp \
    php-mcrypt \
    php-openssl \
    php-phar \
    php-zip \
    php-ctype \
    php-xmlrpc \
    php-zlib \
    && rm -rf /var/cache/apk/*

RUN wget https://github.com/banago/PHPloy/raw/master/dist/phploy.phar --no-check-certificate && \
    chmod +x phploy.phar && \
    mv phploy.phar /usr/bin/phploy && \
    export PATH=$PATH":/usr/bin"
