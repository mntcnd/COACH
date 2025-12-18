<?php
// Load environment variables from .env file
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Return the configuration array
return [
    'sendgrid_api_key' => $_ENV['SENDGRID_API_KEY'],
    'from_email' => 'coach.hub2025@gmail.com',
    'from_name' => 'COACH'
];
?>