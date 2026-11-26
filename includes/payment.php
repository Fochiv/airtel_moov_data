<?php
declare(strict_types=1);

function bizconnectpay_environment(): string
{
    $environment = strtoupper(trim((string) app_config('BIZCONNECTPAY_ENVIRONMENT', 'SANDBOX')));
    if (!in_array($environment, ['SANDBOX', 'LIVE'], true)) {
        throw new RuntimeException('Payment environment must be SANDBOX or LIVE.');
    }
    return $environment;
}

function bizconnectpay_credentials(): ?array
{
    $prefix = bizconnectpay_environment() === 'LIVE' ? 'BIZCONNECTPAY_LIVE_' : 'BIZCONNECTPAY_';
    $clientId = app_config($prefix . 'CLIENT_ID');
    $clientSecret = app_config($prefix . 'CLIENT_SECRET');
    if (!is_string($clientId) || trim($clientId) === ''
        || !is_string($clientSecret) || trim($clientSecret) === '') {
        return null;
    }
    return ['client_id' => $clientId, 'client_secret' => $clientSecret];
}

function bizconnectpay_webhook_secret(): string
{
    $key = bizconnectpay_environment() === 'LIVE'
        ? 'BIZCONNECTPAY_LIVE_WEBHOOK_SECRET'
        : 'BIZCONNECTPAY_WEBHOOK_SECRET';
    return (string) app_config($key, '');
}

function bizconnectpay_configured(): bool
{
    try {
        return bizconnectpay_credentials() !== null && bizconnectpay_webhook_secret() !== '';
    } catch (Throwable) {
        return false;
    }
}

function gabon_payment_method_label(string $method): ?string
{
    $normalized = strtolower(trim($method));
    if (str_contains($normalized, 'airtel')) {
        return 'Airtel Money';
    }
    if (str_contains($normalized, 'moov')) {
        return 'Moov Money';
    }
    return null;
}

function bcp_request(string $method, string $path, ?array $payload = null, ?string $idempotencyKey = null, ?string $token = null): array
{
    $base = 'https://api.bizconnectpay.com/api/v1';
    if ($path === '/auth/application/token') {
        $base = 'https://account.bizconnectpay.com/api/v1';
    }
    $handle = curl_init($base . $path);
    if ($handle === false) {
        throw new RuntimeException('Payment service unavailable.');
    }

    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token !== null) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    if ($idempotencyKey !== null) {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_POSTFIELDS => $payload !== null ? json_encode($payload, JSON_THROW_ON_ERROR) : null,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);
    if (!is_string($body) || $status < 200 || $status >= 300) {
        throw new RuntimeException('Payment service unavailable.');
    }
    $data = json_decode($body, true);
    if (!is_array($data) || ($data['success'] ?? false) !== true) {
        throw new RuntimeException('Payment service unavailable.');
    }
    return $data;
}

function bcp_access_token(): string
{
    $environment = bizconnectpay_environment();
    $credentials = bizconnectpay_credentials();
    if ($credentials === null || bizconnectpay_webhook_secret() === '') {
        throw new RuntimeException('Payment service is not configured.');
    }
    $cacheKey = 'bcp_access_token_' . strtolower($environment);
    $pdo = db();
    $statement = $pdo->prepare('SELECT cache_value, expires_at FROM app_cache WHERE cache_key = ?');
    $statement->execute([$cacheKey]);
    $cached = $statement->fetch();
    if (is_array($cached) && (int) $cached['expires_at'] > time() + 60) {
        return (string) $cached['cache_value'];
    }

    $response = bcp_request('POST', '/auth/application/token', $credentials);
    $accessToken = $response['data']['access_token'] ?? null;
    $expiresIn = (int) ($response['data']['expires_in'] ?? 3600);
    if (!is_string($accessToken) || $accessToken === '' || ($response['data']['environment'] ?? '') !== $environment) {
        throw new RuntimeException('Payment service unavailable.');
    }
    $expiresAt = time() + max(120, $expiresIn - 120);
    $save = $pdo->prepare('DELETE FROM app_cache WHERE cache_key = ?');
    $save->execute([$cacheKey]);
    $save = $pdo->prepare('INSERT INTO app_cache (cache_key, cache_value, expires_at) VALUES (?, ?, ?)');
    $save->execute([$cacheKey, $accessToken, $expiresAt]);
    return $accessToken;
}

function bcp_services(): array
{
    if (!bizconnectpay_configured()) {
        return [];
    }
    $cacheKey = 'bcp_services_ga_v3_' . strtolower(bizconnectpay_environment());
    $pdo = db();
    $statement = $pdo->prepare('SELECT cache_value, expires_at FROM app_cache WHERE cache_key = ?');
    $statement->execute([$cacheKey]);
    $cached = $statement->fetch();
    if (is_array($cached) && (int) $cached['expires_at'] > time()) {
        $services = json_decode((string) $cached['cache_value'], true);
        if (is_array($services)) {
            return $services;
        }
    }
    $response = bcp_request('GET', '/services', null, null, bcp_access_token());
    $all = $response['data']['services'] ?? [];
    $services = [];
    foreach (is_array($all) ? $all : [] as $service) {
        if (($service['country']['code'] ?? '') === 'GA'
            && ($service['currency']['code'] ?? '') === 'XAF'
            && ($service['operation'] ?? '') === 'COLLECTION'
            && ($service['method_type'] ?? '') === 'MOBILE_MONEY'
            && ($service['available'] ?? false) === true) {
            $method = (string) ($service['payment_method'] ?? '');
            $label = gabon_payment_method_label($method);
            if ($label !== null) {
                $services[$method] = $label;
            }
        }
    }
    $statement = $pdo->prepare('DELETE FROM app_cache WHERE cache_key = ?');
    $statement->execute([$cacheKey]);
    $statement = $pdo->prepare('INSERT INTO app_cache (cache_key, cache_value, expires_at) VALUES (?, ?, ?)');
    $statement->execute([$cacheKey, json_encode($services, JSON_THROW_ON_ERROR), time() + 300]);
    return $services;
}

function normalize_gabon_phone(string $phone): ?string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '241')) {
        $national = substr($digits, 3);
    } else {
        $national = $digits;
    }
    if (strlen($national) === 9 && str_starts_with($national, '0')) {
        $national = substr($national, 1);
    } elseif (strlen($national) === 8 && str_starts_with($national, '0')) {
        $national = substr($national, 1);
    }
    if (strlen($national) < 7 || strlen($national) > 8) {
        return null;
    }
    return '+241' . $national;
}

function bcp_local_order_status(string $providerStatus): string
{
    return match (strtoupper(trim($providerStatus))) {
        'CREATED', 'PENDING', 'PROCESSING', 'REQUIRES_ACTION' => 'pending_payment',
        'COMPLETED' => 'paid',
        'FAILED', 'CANCELLED', 'EXPIRED' => 'failed',
        'UNKNOWN' => 'payment_unknown',
        default => 'payment_unknown',
    };
}

function create_mobile_money_order(int $packageId, string $customerPhone, string $paymentPhone, string $method, string $requestToken): array
{
    $pdo = db();
    if (!preg_match('/^[a-f0-9]{32}$/', $requestToken)) {
        throw new InvalidArgumentException('Rechargez la page puis réessayez.');
    }
    $statement = $pdo->prepare('SELECT id, `operator`, price, data_volume, validity, description FROM packages WHERE id = ? AND active = 1');
    $statement->execute([$packageId]);
    $package = $statement->fetch();
    if (!is_array($package)) {
        throw new InvalidArgumentException('Ce forfait n’est plus disponible.');
    }
    if (gabon_payment_method_label($method) === null) {
        throw new InvalidArgumentException('Choisissez un moyen de paiement.');
    }
    $normalizedCustomer = normalize_gabon_phone($customerPhone);
    $normalizedPayer = normalize_gabon_phone($paymentPhone);
    if ($normalizedCustomer === null || $normalizedPayer === null) {
        throw new InvalidArgumentException('Vérifiez le numéro gabonais saisi.');
    }

    $clientReference = 'GD' . strtoupper($requestToken);
    $existingQuery = $pdo->prepare('SELECT id, package_id, amount, customer_phone, payment_phone, payment_method, status, payment_intent_id, redirect_url FROM orders WHERE client_reference = ?');
    $existingQuery->execute([$clientReference]);
    $existing = $existingQuery->fetch();
    if (is_array($existing)) {
        if ((int) $existing['package_id'] !== (int) $package['id']
            || (int) $existing['amount'] !== (int) $package['price']
            || (string) $existing['customer_phone'] !== $normalizedCustomer
            || (string) $existing['payment_phone'] !== $normalizedPayer
            || (string) $existing['payment_method'] !== $method) {
            throw new InvalidArgumentException('Rechargez la page pour démarrer une autre commande.');
        }
        if (in_array($existing['status'], ['paid', 'delivered', 'failed'], true)
            || is_string($existing['payment_intent_id']) && $existing['payment_intent_id'] !== '') {
            return [
                'client_reference' => $clientReference,
                'redirect_url' => is_string($existing['redirect_url']) ? $existing['redirect_url'] : null,
            ];
        }
    }

    $services = bcp_services();
    if (!isset($services[$method])) {
        throw new RuntimeException('Ce moyen de paiement n’est pas disponible pour le moment.');
    }

    $createdAt = gmdate('Y-m-d H:i:s');
    $packageName = $package['data_volume'] . ' · ' . $package['validity'];
    if (!is_array($existing)) {
        $statement = $pdo->prepare("INSERT INTO orders
            (client_reference, package_id, `operator`, package_name, amount, customer_phone, payment_phone, payment_method, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_payment', ?)");
        $statement->execute([
            $clientReference,
            (int) $package['id'],
            (string) $package['operator'],
            $packageName,
            (int) $package['price'],
            $normalizedCustomer,
            $normalizedPayer,
            $method,
            $createdAt,
        ]);
        $orderId = (int) $pdo->lastInsertId();
    } else {
        $orderId = (int) $existing['id'];
    }

    try {
        $result = bcp_request('POST', '/payments', [
            'amount' => (string) (int) $package['price'],
            'currency' => 'XAF',
            'country' => 'GA',
            'operation' => 'COLLECTION',
            'payment_method' => $method,
            'payer' => ['phone_number' => $normalizedPayer, 'lang' => lang()],
            'description' => 'Forfait ' . operator_label((string) $package['operator']) . ' ' . $packageName,
            'metadata' => ['client_reference' => $clientReference],
        ], $clientReference, bcp_access_token());
        $data = $result['data'] ?? [];
        $intentId = $data['id'] ?? null;
        $reference = $data['reference'] ?? null;
        $apiAmount = (float) ($data['amount'] ?? -1);
        $apiStatus = (string) ($data['status'] ?? '');
        if (!is_string($intentId) || $intentId === ''
            || !is_string($reference) || $reference === ''
            || $apiAmount !== (float) (int) $package['price']
            || ($data['currency'] ?? '') !== 'XAF'
            || ($data['environment'] ?? '') !== bizconnectpay_environment()
            || $apiStatus === '') {
            throw new RuntimeException('Payment service unavailable.');
        }
        $redirect = $data['redirect_url'] ?? null;
        if (is_string($redirect) && !str_starts_with($redirect, 'https://')) {
            $redirect = null;
        }
        $savedStatus = bcp_local_order_status($apiStatus);
        $save = $pdo->prepare('UPDATE orders SET status = ?, paid_at = ?, payment_intent_id = ?, payment_reference = ?, provider_reference = ?, redirect_url = ? WHERE id = ?');
        $save->execute([
            $savedStatus,
            $savedStatus === 'paid' ? gmdate('Y-m-d H:i:s') : null,
            $intentId,
            $reference,
            is_string($data['provider_reference'] ?? null) ? $data['provider_reference'] : null,
            $redirect,
            $orderId,
        ]);
        return ['client_reference' => $clientReference, 'redirect_url' => $redirect];
    } catch (Throwable $error) {
        // The provider may have accepted the request before the connection failed.
        // Keep the same reference so a retry uses the same idempotency key.
        $pending = $pdo->prepare("UPDATE orders SET status = 'payment_unknown' WHERE id = ? AND payment_intent_id IS NULL");
        $pending->execute([$orderId]);
        throw $error;
    }
}

function refresh_mobile_money_order_status(array $order): void
{
    $currentStatus = (string) ($order['status'] ?? '');
    if (!in_array($currentStatus, ['pending_payment', 'payment_unknown'], true)) {
        return;
    }

    $intentId = (string) ($order['payment_intent_id'] ?? '');
    $clientReference = (string) ($order['client_reference'] ?? '');
    if ($intentId === '' || $clientReference === '') {
        return;
    }

    $response = bcp_request('GET', '/payments/' . rawurlencode($intentId), null, null, bcp_access_token());
    $data = $response['data'] ?? null;
    if (!is_array($data)
        || ($data['id'] ?? '') !== $intentId
        || ($data['environment'] ?? '') !== bizconnectpay_environment()
        || ($data['country'] ?? '') !== 'GA'
        || ($data['currency'] ?? '') !== 'XAF'
        || ($data['operation'] ?? '') !== 'COLLECTION'
        || ($data['payment_method'] ?? '') !== (string) ($order['payment_method'] ?? '')
        || (float) ($data['amount'] ?? -1) !== (float) (int) ($order['amount'] ?? 0)) {
        throw new RuntimeException('Payment status could not be verified.');
    }

    $metadata = $data['metadata'] ?? null;
    $apiReference = is_array($metadata) ? ($metadata['client_reference'] ?? null) : null;
    if (is_string($apiReference) && $apiReference !== '' && !hash_equals($clientReference, $apiReference)) {
        throw new RuntimeException('Payment status could not be verified.');
    }

    $providerStatus = (string) ($data['status'] ?? '');
    if ($providerStatus === '') {
        throw new RuntimeException('Payment status could not be verified.');
    }
    $newStatus = bcp_local_order_status($providerStatus);
    $update = db()->prepare("UPDATE orders
        SET status = ?, paid_at = ?
        WHERE id = ? AND status IN ('pending_payment', 'payment_unknown')");
    $update->execute([
        $newStatus,
        $newStatus === 'paid' ? gmdate('Y-m-d H:i:s') : null,
        (int) ($order['id'] ?? 0),
    ]);
}

