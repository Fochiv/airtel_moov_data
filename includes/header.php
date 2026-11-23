<?php
declare(strict_types=1);
$currentLanguage = lang();
$isAdminPage = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/');
?>
<!doctype html>
<html lang="<?= h($currentLanguage) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7fb">
    <title><?= h($pageTitle ?? 'Gooddeal Data') ?></title>
    <link rel="icon" type="image/png" href="/Logo_Airtel_Libertis_data.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/site.css" rel="stylesheet">
</head>
<body class="<?= $isAdminPage ? 'admin-body' : 'site-body' ?>">

