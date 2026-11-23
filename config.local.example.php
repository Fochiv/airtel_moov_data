<?php
/*
 * Rename this file to config.local.php on Hostinger.
 * Replace placeholders with values from the hosting control panel and sandbox.
 * This file is blocked from public access by .htaccess.
 */
return [
    'DB_DRIVER' => 'mysql',
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'YOUR_HOSTINGER_DATABASE',
    'DB_USER' => 'YOUR_HOSTINGER_DATABASE_USER',
    'DB_PASSWORD' => 'YOUR_HOSTINGER_DATABASE_PASSWORD',
    'BIZCONNECTPAY_CLIENT_ID' => 'YOUR_SANDBOX_CLIENT_ID',
    'BIZCONNECTPAY_CLIENT_SECRET' => 'YOUR_SANDBOX_CLIENT_SECRET',
    'BIZCONNECTPAY_WEBHOOK_SECRET' => 'YOUR_WEBHOOK_SIGNING_SECRET',
    'ADMIN_INITIAL_PASSWORD' => 'CHOOSE_A_NEW_ADMIN_PASSWORD',
    'PUBLIC_WEBHOOK_URL' => 'https://gooddeal-ga.top/webhook.php',
];

