<?php
$LOGIN_REGEX = "/\d{9}/";
$PASSWORD_GENERATOR_ALGORITHM = "sha256";
$PASSWORD_GENERATOR_SECRET = "secret";
$PASSWORD_GENERATOR_LENGTH = 6;

$JWT_KEY = "jwt";
$JWT_ALGORITHM = "HS256";
$JWT_DURATION = 3 * 60 * 60; // 3 hours

$ADMIN_USERNAME = "admin";
$ADMIN_PASSWORD = "admin";

$DB_HOST = "127.0.0.1";
$DB_PORT = 3306;
$DB_USER = "root";
$DB_PASSWORD = "root";
$DB_DATABASE = "db";

$DEBUG = true;

$MAIL_HOST = "smtps.example.com";
$MAIL_PORT = 25;
$MAIL_SMTP_AUTH = false;
$MAIL_USERNAME = "admin@example.com";
$MAIL_PASSWORD = "123456";
$MAIL_SMTP_SECURE = false;

$JWT_LEEWAY = 15 * 60;

?>