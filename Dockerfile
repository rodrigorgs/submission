# Dockerfile
FROM php:8.1-apache
RUN a2enmod rewrite
RUN a2enmod headers