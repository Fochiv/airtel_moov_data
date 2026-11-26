<?php
/*
 * Rename this file to config.local.php on Hostinger.
 * Replace placeholders with values from the hosting control panel.
 * Keep SANDBOX selected until a controlled LIVE launch is approved.
 * This file is blocked from public access by .htaccess.
 */
return [
    'DB_DRIVER' => 'mysql',
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'YOUR_HOSTINGER_DATABASE',
    'DB_USER' => 'YOUR_HOSTINGER_DATABASE_USER',
    'DB_PASSWORD' => 'YOUR_HOSTINGER_DATABASE_PASSWORD',
    'BIZCONNECTPAY_ENVIRONMENT' => 'SANDBOX',
    'BIZCONNECTPAY_CLIENT_ID' => 'YOUR_SANDBOX_CLIENT_ID',
    'BIZCONNECTPAY_CLIENT_SECRET' => 'YOUR_SANDBOX_CLIENT_SECRET',
    'BIZCONNECTPAY_WEBHOOK_SECRET' => 'YOUR_SANDBOX_WEBHOOK_SECRET',
    'BIZCONNECTPAY_LIVE_CLIENT_ID' => 'YOUR_LIVE_CLIENT_ID',
    'BIZCONNECTPAY_LIVE_CLIENT_SECRET' => 'YOUR_LIVE_CLIENT_SECRET',
    'BIZCONNECTPAY_LIVE_WEBHOOK_SECRET' => 'YOUR_LIVE_WEBHOOK_SECRET',
    'ADMIN_INITIAL_PASSWORD' => 'CHOOSE_A_NEW_ADMIN_PASSWORD',
    'PUBLIC_WEBHOOK_URL' => 'https://gooddeal-ga.top/webhook.php',
];

