<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['received' => false, 'error' => 'method_not_allowed']);
    exit;
}

$secret = (string) app_config('BIZCONNECTPAY_WEBHOOK_SECRET', '');
$raw = file_get_contents('php://input');
$timestamp = (string) ($_SERVER['HTTP_X_BIZCONNECTPAY_TIMESTAMP'] ?? '');
$receivedSignature = (string) ($_SERVER['HTTP_X_BIZCONNECTPAY_SIGNATURE'] ?? '');
$headerEventId = (string) ($_SERVER['HTTP_X_BIZCONNECTPAY_EVENT_ID'] ?? '');
if ($secret === '' || !is_string($raw) || $raw === '' || !ctype_digit($timestamp)
    || abs(time() - (int) $timestamp) > 300 || !preg_match('/^v1=[a-f0-9]{64}$/i', $receivedSignature)) {
    http_response_code(401);
    echo json_encode(['received' => false, 'error' => 'unauthorized']);
    exit;
}

$expectedSignature = 'v1=' . hash_hmac('sha256', $timestamp . '.' . $raw, $secret);
if (!hash_equals($expectedSignature, $receivedSignature)) {
    http_response_code(401);
    echo json_encode(['received' => false, 'error' => 'unauthorized']);
    exit;
}

$event = json_decode($raw, true);
$eventId = is_array($event) ? (string) ($event['id'] ?? '') : '';
$type = is_array($event) ? (string) ($event['type'] ?? '') : '';
$data = is_array($event) ? ($event['data'] ?? null) : null;
$intentId = is_array($data) ? (string) ($data['payment_intent_id'] ?? '') : '';
$status = is_array($data) ? (string) ($data['status'] ?? '') : '';
if ($eventId === '' || ($headerEventId !== '' && !hash_equals($eventId, $headerEventId))
    || !is_array($data) || $intentId === '') {
    http_response_code(400);
    echo json_encode(['received' => false, 'error' => 'invalid_event']);
    exit;
}

if (!in_array($type, [
    'bizconnectpay.transaction.completed.v1',
    'bizconnectpay.transaction.failed.v1',
], true)) {
    http_response_code(200);
    echo json_encode(['received' => true, 'ignored' => true]);
    exit;
}

try {
    $pdo = db();
    $pdo->beginTransaction();
    $dedupe = $pdo->prepare('SELECT 1 FROM webhook_events WHERE event_id = ?');
    $dedupe->execute([$eventId]);
    if ($dedupe->fetchColumn()) {
        $pdo->commit();
        http_response_code(200);
        echo json_encode(['received' => true, 'duplicate' => true]);
        exit;
    }

    $find = $pdo->prepare('SELECT id, amount, status FROM orders WHERE payment_intent_id = ?');
    $find->execute([$intentId]);
    $order = $find->fetch();
    if (!is_array($order)) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['received' => false, 'error' => 'order_not_found']);
        exit;
    }
    $eventAmount = (float) ($data['amount'] ?? -1);
    if (($data['currency'] ?? '') !== 'XAF'
        || ($data['operation'] ?? '') !== 'COLLECTION'
        || $eventAmount !== (float) (int) $order['amount']) {
        $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['received' => false, 'error' => 'payment_mismatch']);
        exit;
    }

    $newStatus = null;
    if ($type === 'bizconnectpay.transaction.completed.v1' && $status === 'COMPLETED') {
        if (!in_array($order['status'], ['paid', 'delivered'], true)) {
            $newStatus = 'paid';
        }
    } elseif ($type === 'bizconnectpay.transaction.failed.v1' && $status === 'FAILED') {
        if (!in_array($order['status'], ['paid', 'delivered'], true)) {
            $newStatus = 'failed';
        }
    } else {
        $pdo->rollBack();
        http_response_code(422);
        echo json_encode(['received' => false, 'error' => 'invalid_status']);
        exit;
    }
    if ($newStatus !== null) {
        $update = $pdo->prepare('UPDATE orders SET status = ?, paid_at = ? WHERE id = ?');
        $update->execute([$newStatus, $newStatus === 'paid' ? gmdate('Y-m-d H:i:s') : null, (int) $order['id']]);
    }
    $saveEvent = $pdo->prepare('INSERT INTO webhook_events (event_id, received_at) VALUES (?, ?)');
    $saveEvent->execute([$eventId, gmdate('Y-m-d H:i:s')]);
    $pdo->commit();
    http_response_code(200);
    echo json_encode(['received' => true]);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['received' => false, 'error' => 'processing_failed']);
}

