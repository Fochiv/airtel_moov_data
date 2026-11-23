<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();
$orders = [];
$error = '';
$searched = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $now = time();
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $ipHash = hash('sha256', $remote . (string) app_config('APP_RATE_LIMIT_SALT', 'gooddeal-local-rate-limit'));
    $pdo->prepare('DELETE FROM order_lookup_attempts WHERE attempted_at < ?')->execute([$now - 900]);
    $attempts = $pdo->prepare('SELECT COUNT(*) FROM order_lookup_attempts WHERE ip_hash = ? AND attempted_at >= ?');
    $attempts->execute([$ipHash, $now - 900]);
    if ((int) $attempts->fetchColumn() >= 10) {
        http_response_code(429);
        $error = t('orders_rate_limit');
    } else {
        $pdo->prepare('INSERT INTO order_lookup_attempts (ip_hash, attempted_at) VALUES (?, ?)')->execute([$ipHash, $now]);
        $phone = normalize_gabon_phone((string) ($_POST['customer_phone'] ?? ''));
        if ($phone === null) {
            $error = t('orders_lookup_error');
        } else {
            $searched = true;
            $statement = $pdo->prepare('SELECT client_reference, `operator`, package_name, amount, status, created_at FROM orders WHERE customer_phone = ? ORDER BY id DESC LIMIT 10');
            $statement->execute([$phone]);
            $orders = $statement->fetchAll();
        }
    }
}

$pageTitle = lang() === 'fr' ? 'Mes commandes — Gooddeal Data' : 'My orders — Gooddeal Data';
require __DIR__ . '/includes/header.php';
?>
<main class="orders-page">
    <div class="container orders-container">
        <span class="eyebrow"><?= h(t('orders')) ?></span>
        <h1><?= h(t('orders_title')) ?></h1>
        <p class="orders-intro"><?= h(t('orders_intro')) ?></p>

        <form class="orders-search-form" method="post" action="/commandes.php">
            <?= csrf_field() ?>
            <label class="form-label" for="ordersPhone"><?= h(t('orders_phone')) ?></label>
            <div class="orders-search-controls">
                <input class="form-control" type="tel" id="ordersPhone" name="customer_phone" inputmode="tel" autocomplete="tel" placeholder="06 12 34 56" maxlength="20" required>
                <button class="button-primary" type="submit"><?= icon('search') ?> <?= h(t('orders_search')) ?></button>
            </div>
        </form>

        <?php if ($error !== ''): ?>
            <p class="orders-message error" role="alert"><?= h($error) ?></p>
        <?php elseif ($searched && !$orders): ?>
            <p class="orders-message" role="status"><?= h(t('orders_empty')) ?></p>
        <?php elseif ($orders): ?>
            <section class="customer-order-list" aria-label="<?= h(t('orders_title')) ?>">
                <?php foreach ($orders as $order): ?>
                    <?php
                    $status = (string) $order['status'];
                    $statusLabel = match ($status) {
                        'paid' => t('orders_paid'),
                        'delivered' => t('orders_delivered'),
                        'failed' => t('orders_failed'),
                        'payment_unknown' => t('orders_payment_check'),
                        default => t('orders_pending_payment'),
                    };
                    $statusClass = match ($status) {
                        'paid' => 'paid',
                        'delivered' => 'delivered',
                        'failed' => 'failed',
                        default => 'pending',
                    };
                    ?>
                    <article class="customer-order-card">
                        <div class="customer-order-card-top">
                            <span class="operator-pill <?= h(operator_color((string) $order['operator'])) ?>"><?= h(operator_label((string) $order['operator'])) ?></span>
                            <span class="status-pill <?= h($statusClass) ?>"><?= h($statusLabel) ?></span>
                        </div>
                        <h2><?= h(localized_package_name((string) $order['package_name'])) ?></h2>
                        <div class="customer-order-meta">
                            <span><?= h(t('orders_date')) ?> · <?= h(substr((string) $order['created_at'], 0, 10)) ?></span>
                            <strong><?= h(format_price($order['amount'])) ?></strong>
                        </div>
                        <a class="customer-order-reference" href="/commande.php?ref=<?= rawurlencode((string) $order['client_reference']) ?>">
                            <?= h(t('orders_reference')) ?> · <?= h($order['client_reference']) ?> <?= icon('arrow') ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
</main>

<a class="support-fab" href="mailto:support@gooddeal.com" aria-label="<?= h(t('support')) ?>" title="<?= h(t('support')) ?>"><?= icon('headset') ?></a>
<?php $activeCustomerNav = 'orders'; require __DIR__ . '/includes/customer-bottom-nav.php'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
