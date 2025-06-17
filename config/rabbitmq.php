<?php

return [
    "RABBITMQ_HOST" => env("RABBITMQ_HOST", "localhost"),
    "RABBITMQ_PORT" => env("RABBITMQ_PORT", "5672"),
    "RABBITMQ_USER" => env("RABBITMQ_USER", "guest"),
    "RABBITMQ_PASS" => env("RABBITMQ_PASS", "guest"),
    "RABBITMQ_VHOST" => env("RABBITMQ_VHOST", "/"),
];
