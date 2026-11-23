<?php
declare(strict_types=1);

function bizconnectpay_configured(): bool
{
    return app_config('BIZCONNECTPAY_CLIENT_ID') !== null
        && app_config('BIZCONNECTPAY_CLIENT_SECRET') !== null;
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
    if (!bizconnectpay_configured()) {
        throw new RuntimeException('Payment service is not configured.');
    }
    $pdo = db();
    $statement = $pdo->prepare('SELECT cache_value, expires_at FROM app_cache WHERE cache_key = ?');
    $statement->execute(['bcp_access_token']);
    $cached = $statement->fetch();
    if (is_array($cached) && (int) $cached['expires_at'] > time() + 60) {
        return (string) $cached['cache_value'];
    }

    $response = bcp_request('POST', '/auth/application/token', [
        'client_id' => (string) app_config('BIZCONNECTPAY_CLIENT_ID'),
        'client_secret' => (string) app_config('BIZCONNECTPAY_CLIENT_SECRET'),
    ]);
    $accessToken = $response['data']['access_token'] ?? null;
    $expiresIn = (int) ($response['data']['expires_in'] ?? 3600);
    if (!is_string($accessToken) || $accessToken === '' || ($response['data']['environment'] ?? '') !== 'SANDBOX') {
        throw new RuntimeException('Payment service unavailable.');
    }
    $expiresAt = time() + max(120, $expiresIn - 120);
    $save = $pdo->prepare('DELETE FROM app_cache WHERE cache_key = ?');
    $save->execute(['bcp_access_token']);
    $save = $pdo->prepare('INSERT INTO app_cache (cache_key, cache_value, expires_at) VALUES (?, ?, ?)');
    $save->execute(['bcp_access_token', $accessToken, $expiresAt]);
    return $accessToken;
}

function bcp_services(): array
{
    if (!bizconnectpay_configured()) {
        return [];
    }
    $pdo = db();
    $statement = $pdo->prepare('SELECT cache_value, expires_at FROM app_cache WHERE cache_key = ?');
    $statement->execute(['bcp_services_ga']);
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
            if (in_array($method, ['mtn_momo', 'orange_money'], true)) {
                $services[$method] = true;
            }
        }
    }
    $statement = $pdo->prepare('DELETE FROM app_cache WHERE cache_key = ?');
    $statement->execute(['bcp_services_ga']);
    $statement = $pdo->prepare('INSERT INTO app_cache (cache_key, cache_value, expires_at) VALUES (?, ?, ?)');
    $statement->execute(['bcp_services_ga', json_encode($services, JSON_THROW_ON_ERROR), time() + 300]);
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
    if (strlen($national) === 8 && str_starts_with($national, '0')) {
        $national = substr($national, 1);
    }
    if (strlen($national) < 7 || strlen($national) > 8) {
        return null;
    }
    return '+241' . $national;
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
    if (!in_array($method, ['orange_money', 'mtn_momo'], true)) {
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
            || !in_array($apiStatus, ['CREATED', 'PENDING', 'PROCESSING', 'REQUIRES_ACTION', 'COMPLETED', 'FAILED'], true)) {
            throw new RuntimeException('Payment service unavailable.');
        }
        $redirect = $data['redirect_url'] ?? null;
        if (is_string($redirect) && !str_starts_with($redirect, 'https://')) {
            $redirect = null;
        }
        $savedStatus = $apiStatus === 'COMPLETED' ? 'paid' : ($apiStatus === 'FAILED' ? 'failed' : 'pending_payment');
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

