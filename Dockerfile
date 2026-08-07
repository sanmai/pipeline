FROM php:latest

RUN apt-get update && apt-get install -y unzip git moreutils && rm -rf /var/lib/apt/lists/*
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN useradd --home-dir /opt/pipeline --shell /bin/bash pipeline
RUN mkdir -p /opt/pipeline/.composer && chown -R pipeline:pipeline /opt/pipeline

USER pipeline
WORKDIR /app
