<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

try {
    verify_csrf();
    $packageId = filter_var($_POST['package_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$packageId || $packageId < 1) {
        throw new InvalidArgumentException('Ce forfait n’est plus disponible.');
    }
    $order = create_mobile_money_order(
        $packageId,
        trim((string) ($_POST['customer_phone'] ?? '')),
        trim((string) ($_POST['payment_phone'] ?? '')),
        trim((string) ($_POST['payment_method'] ?? '')),
        trim((string) ($_POST['checkout_token'] ?? ''))
    );
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'status_url' => '/commande.php?ref=' . rawurlencode($order['client_reference']),
        'redirect_url' => $order['redirect_url'],
    ], JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    $message = $error->getMessage();
    if (lang() === 'en') {
        $message = match ($message) {
            'Ce forfait n’est plus disponible.' => 'This data plan is no longer available.',
            'Choisissez un moyen de paiement.' => 'Choose a payment method.',
            'Vérifiez le numéro gabonais saisi.' => 'Check the Gabonese phone number you entered.',
            'Rechargez la page puis réessayez.' => 'Reload the page and try again.',
            'Rechargez la page pour démarrer une autre commande.' => 'Reload the page to start another order.',
            default => 'Check your details and try again.',
        };
    }
    echo json_encode(['success' => false, 'message' => $message], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => lang() === 'fr'
            ? 'Le paiement est momentanément indisponible. Réessayez plus tard.'
            : 'Payment is temporarily unavailable. Please try again later.',
    ], JSON_THROW_ON_ERROR);
}

