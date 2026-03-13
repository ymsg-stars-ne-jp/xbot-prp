<?php
require_once 'vendor/autoload.php';

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Now you can access your environment variables like this:
// $variable = getenv('VARIABLE_NAME');
