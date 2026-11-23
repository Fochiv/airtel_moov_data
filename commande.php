<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$reference = trim((string) ($_GET['ref'] ?? ''));
$statement = db()->prepare('SELECT client_reference, `operator`, package_name, amount, customer_phone, status, redirect_url, created_at FROM orders WHERE client_reference = ?');
$statement->execute([$reference]);
$order = $statement->fetch();
if (!is_array($order)) {
    http_response_code(404);
    $pageTitle = lang() === 'fr' ? 'Commande introuvable' : 'Order not found';
} else {
    $pageTitle = lang() === 'fr' ? 'Suivi de commande' : 'Order status';
}
require __DIR__ . '/includes/header.php';
?>
<main class="order-page">
    <div class="container order-container">
        <nav class="order-language" aria-label="<?= h(t('language')) ?>">
            <a href="?ref=<?= rawurlencode($reference) ?>&lang=fr" class="<?= $currentLanguage === 'fr' ? 'active' : '' ?>">FR</a>
            <a href="?ref=<?= rawurlencode($reference) ?>&lang=en" class="<?= $currentLanguage === 'en' ? 'active' : '' ?>">EN</a>
        </nav>
        <a class="back-link" href="/"><?= icon('arrow') ?> <?= h(lang() === 'fr' ? 'Retour aux forfaits' : 'Back to plans') ?></a>
        <?php if (!is_array($order)): ?>
            <section class="order-panel">
                <span class="eyebrow">Gooddeal Data</span>
                <h1><?= h(lang() === 'fr' ? 'Commande introuvable' : 'Order not found') ?></h1>
                <p><?= h(lang() === 'fr' ? 'Vérifiez la référence ou contactez notre assistance.' : 'Check your reference or contact support.') ?></p>
            </section>
        <?php else: ?>
            <?php
            $statusText = match ($order['status']) {
                'paid' => t('order_paid'),
                'delivered' => t('order_delivered'),
                'failed' => t('order_failed'),
                default => t('order_waiting'),
            };
            $statusClass = match ($order['status']) {
                'paid', 'delivered' => 'success',
                'failed' => 'failed',
                default => 'pending',
            };
            ?>
            <section class="order-panel">
                <span class="order-status-icon <?= h($statusClass) ?>"><?= icon($statusClass === 'success' ? 'check' : 'clock') ?></span>
                <span class="eyebrow"><?= h(operator_label((string) $order['operator'])) ?> · <?= h(localized_package_name((string) $order['package_name'])) ?></span>
                <h1><?= h($statusText) ?></h1>
                <p><?= h(t('order_note')) ?></p>
                <div class="order-details">
                    <div><span><?= h(t('order_number')) ?></span><strong><?= h($order['client_reference']) ?></strong></div>
                    <div><span><?= h(lang() === 'fr' ? 'Montant' : 'Amount') ?></span><strong><?= h(format_price($order['amount'])) ?></strong></div>
                    <div><span><?= h(t('recipient_phone')) ?></span><strong><?= h($order['customer_phone']) ?></strong></div>
                </div>
                <?php if ($order['status'] === 'pending_payment' && is_string($order['redirect_url']) && str_starts_with($order['redirect_url'], 'https://')): ?>
                    <a class="button-primary continue-payment" href="<?= h($order['redirect_url']) ?>"><?= h(lang() === 'fr' ? 'Poursuivre le paiement' : 'Continue payment') ?> <?= icon('arrow') ?></a>
                <?php endif; ?>
                <?php if ($order['status'] === 'pending_payment'): ?>
                    <button class="button-secondary order-refresh" type="button" onclick="window.location.reload()"><?= h(lang() === 'fr' ? 'Actualiser le statut' : 'Refresh status') ?></button>
                <?php endif; ?>
                <a class="support-inline" href="mailto:support@gooddeal.com"><?= h(t('support')) ?> · support@gooddeal.com</a>
            </section>
        <?php endif; ?>
    </div>
</main>
<a class="support-fab" href="mailto:support@gooddeal.com" aria-label="<?= h(t('support')) ?>" title="<?= h(t('support')) ?>"><?= icon('headset') ?></a>
<?php require __DIR__ . '/includes/footer.php'; ?>

