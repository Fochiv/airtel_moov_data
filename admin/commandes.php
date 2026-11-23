<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $action = (string) ($_POST['action'] ?? '');
    if (!$orderId) {
        flash('error', lang() === 'fr' ? 'Commande introuvable.' : 'Order not found.');
    } elseif ($action === 'deliver') {
        $deliver = $pdo->prepare("UPDATE orders SET status = 'delivered', delivered_at = ? WHERE id = ? AND status = 'paid'");
        $deliver->execute([gmdate('Y-m-d H:i:s'), $orderId]);
        flash($deliver->rowCount() === 1 ? 'success' : 'error', $deliver->rowCount() === 1
            ? (lang() === 'fr' ? 'Commande marquée comme livrée.' : 'Order marked as delivered.')
            : (lang() === 'fr' ? 'Cette commande n’est pas disponible pour livraison.' : 'This order is not available for delivery.'));
    } elseif ($action === 'refresh_payment') {
        $find = $pdo->prepare('SELECT id, client_reference, amount, payment_method, payment_intent_id, status FROM orders WHERE id = ?');
        $find->execute([$orderId]);
        $paymentOrder = $find->fetch();
        if (!is_array($paymentOrder)
            || !in_array($paymentOrder['status'], ['pending_payment', 'payment_unknown'], true)
            || !is_string($paymentOrder['payment_intent_id'])
            || $paymentOrder['payment_intent_id'] === '') {
            flash('error', lang() === 'fr'
                ? 'Cette commande ne peut pas être vérifiée auprès du service de paiement.'
                : 'This order cannot be checked with the payment service.');
        } else {
            try {
                refresh_mobile_money_order_status($paymentOrder);
                $current = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
                $current->execute([$orderId]);
                [$messageType, $message] = match ((string) $current->fetchColumn()) {
                    'paid' => ['success', lang() === 'fr' ? 'Paiement confirmé. La commande peut être livrée.' : 'Payment confirmed. The order can be delivered.'],
                    'failed' => ['error', lang() === 'fr' ? 'Le paiement a échoué.' : 'The payment failed.'],
                    'payment_unknown' => ['warning', lang() === 'fr' ? 'Le paiement est encore en vérification. Ne livrez pas encore cette commande.' : 'The payment is still being checked. Do not deliver this order yet.'],
                    default => ['warning', lang() === 'fr' ? 'Le paiement est toujours en attente de confirmation.' : 'The payment is still awaiting confirmation.'],
                };
                flash($messageType, $message);
            } catch (Throwable) {
                flash('error', lang() === 'fr'
                    ? 'Impossible de vérifier le paiement pour le moment. Aucun changement n’a été effectué.'
                    : 'Unable to check the payment right now. No changes were made.');
            }
        }
    } else {
        flash('error', lang() === 'fr' ? 'Action non reconnue.' : 'Unknown action.');
    }
    header('Location: commandes.php');
    exit;
}

$operator = (string) ($_GET['operator'] ?? '');
$allowedOperator = in_array($operator, ['airtel', 'moov'], true) ? $operator : '';
$statusOptions = [
    'pending_payment' => lang() === 'fr' ? 'En attente' : 'Pending',
    'payment_unknown' => lang() === 'fr' ? 'En vérification' : 'Being checked',
    'paid' => lang() === 'fr' ? 'Paiement confirmé' : 'Payment confirmed',
    'delivered' => lang() === 'fr' ? 'Livrée' : 'Delivered',
    'failed' => lang() === 'fr' ? 'Échouée' : 'Failed',
];
$requestedStatus = (string) ($_GET['status'] ?? '');
$allowedStatus = array_key_exists($requestedStatus, $statusOptions) ? $requestedStatus : '';
$search = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT id, client_reference, `operator`, package_name, amount, customer_phone, payment_phone, payment_method, status, created_at, paid_at, delivered_at, payment_intent_id FROM orders WHERE 1 = 1';
$params = [];
if ($allowedStatus !== '') {
    $sql .= ' AND status = ?';
    $params[] = $allowedStatus;
}
if ($allowedOperator !== '') {
    $sql .= ' AND `operator` = ?';
    $params[] = $allowedOperator;
}
if ($search !== '') {
    $sql .= ' AND (client_reference LIKE ? OR customer_phone LIKE ? OR package_name LIKE ?)';
    $needle = '%' . $search . '%';
    array_push($params, $needle, $needle, $needle);
}
$sql .= ' ORDER BY created_at DESC, id DESC LIMIT 250';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$orders = $statement->fetchAll();
$flashMessage = take_flash();
$activeAdminPage = 'orders';
$pageTitle = lang() === 'fr' ? 'Toutes les commandes' : 'All orders';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="admin-shell">
    <?php require __DIR__ . '/partials/sidebar.php'; ?>
    <main class="admin-main">
        <header class="admin-topbar">
            <button class="admin-menu-toggle" type="button" data-menu-toggle aria-label="Menu"><?= icon('menu') ?></button>
            <div><span class="admin-top-kicker"><?= h(lang() === 'fr' ? 'Suivi des ventes' : 'Sales tracking') ?></span><h1><?= h(lang() === 'fr' ? 'Toutes les commandes' : 'All orders') ?></h1></div>
            <div class="admin-top-actions"><a class="admin-language" href="?lang=<?= $currentLanguage === 'fr' ? 'en' : 'fr' ?>"><?= icon('globe') ?><span><?= $currentLanguage === 'fr' ? 'EN' : 'FR' ?></span></a></div>
        </header>
        <div class="admin-content">
            <?php if ($flashMessage): ?><div class="admin-alert <?= h($flashMessage['type']) ?>" role="status"><?= h($flashMessage['message']) ?></div><?php endif; ?>
            <section class="admin-panel orders-panel">
                <div class="panel-heading"><div><span class="panel-kicker"><?= count($orders) ?> <?= h(lang() === 'fr' ? 'commandes affichées' : 'orders shown') ?></span><h2><?= h(lang() === 'fr' ? 'Historique des commandes' : 'Order history') ?></h2></div></div>
                <form class="order-search" method="get" action="commandes.php">
                    <label class="search-field"><?= icon('search') ?><input name="q" type="search" value="<?= h($search) ?>" placeholder="<?= h(lang() === 'fr' ? 'Référence, numéro ou forfait' : 'Reference, number or plan') ?>"></label>
                    <select class="form-select" name="status" aria-label="<?= h(lang() === 'fr' ? 'Statut' : 'Status') ?>">
                        <option value=""><?= h(lang() === 'fr' ? 'Tous les statuts' : 'All statuses') ?></option>
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= h($value) ?>" <?= $allowedStatus === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select" name="operator" aria-label="<?= h(lang() === 'fr' ? 'Opérateur' : 'Network') ?>"><option value=""><?= h(lang() === 'fr' ? 'Tous les opérateurs' : 'All networks') ?></option><option value="airtel" <?= $allowedOperator === 'airtel' ? 'selected' : '' ?>>Airtel</option><option value="moov" <?= $allowedOperator === 'moov' ? 'selected' : '' ?>>Moov</option></select>
                    <button class="button-primary" type="submit"><?= icon('filter') ?> <?= h(lang() === 'fr' ? 'Filtrer' : 'Filter') ?></button>
                </form>
                <?php if (!$orders): ?><p class="admin-empty"><?= h(lang() === 'fr' ? 'Aucune commande ne correspond à votre recherche.' : 'No orders match your search.') ?></p><?php else: ?>
                    <div class="table-wrap"><table class="admin-table"><thead><tr><th><?= h(lang() === 'fr' ? 'Référence' : 'Reference') ?></th><th><?= h(lang() === 'fr' ? 'Client' : 'Customer') ?></th><th><?= h(lang() === 'fr' ? 'Forfait' : 'Plan') ?></th><th><?= h(lang() === 'fr' ? 'Paiement' : 'Payment') ?></th><th><?= h(lang() === 'fr' ? 'Montant' : 'Amount') ?></th><th><?= h(lang() === 'fr' ? 'Statut' : 'Status') ?></th><th><?= h(lang() === 'fr' ? 'Action' : 'Action') ?></th></tr></thead><tbody>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $orderStatus = (string) $order['status'];
                        $orderStatusLabel = match ($orderStatus) {
                            'pending_payment' => lang() === 'fr' ? 'En attente' : 'Pending',
                            'payment_unknown' => lang() === 'fr' ? 'En vérification' : 'Being checked',
                            'paid' => lang() === 'fr' ? 'Paiement confirmé' : 'Payment confirmed',
                            'delivered' => lang() === 'fr' ? 'Livrée' : 'Delivered',
                            'failed' => lang() === 'fr' ? 'Échouée' : 'Failed',
                            default => lang() === 'fr' ? 'Statut inconnu' : 'Unknown status',
                        };
                        $orderStatusClass = in_array($orderStatus, ['pending_payment', 'payment_unknown', 'paid', 'delivered', 'failed'], true)
                            ? $orderStatus
                            : 'payment_unknown';
                        ?>
                        <tr>
                            <td class="reference-cell"><?= h($order['client_reference']) ?><small class="cell-subtitle"><?= h($order['paid_at'] ?? $order['created_at']) ?></small></td>
                            <td><?= h($order['customer_phone']) ?><small class="cell-subtitle"><?= h(lang() === 'fr' ? 'Payeur :' : 'Payer:') ?> <?= h($order['payment_phone']) ?></small></td>
                            <td><span class="operator-pill <?= h(operator_color($order['operator'])) ?>"><?= h(operator_label($order['operator'])) ?></span><small class="cell-subtitle"><?= h($order['package_name']) ?></small></td>
                            <td><?= h(gabon_payment_method_label((string) $order['payment_method']) ?? (string) $order['payment_method']) ?></td>
                            <td><strong><?= h(format_price($order['amount'])) ?></strong></td>
                            <td><span class="status-pill <?= h($orderStatusClass) ?>"><?= h($orderStatusLabel) ?></span></td>
                            <td>
                                <?php if ($order['status'] === 'paid'): ?>
                                    <form method="post" action="commandes.php"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="action" value="deliver"><button class="button-deliver" type="submit" data-confirm="<?= h(lang() === 'fr' ? 'Confirmer la livraison de ce forfait ?' : 'Confirm delivery of this plan?') ?>"><?= icon('check') ?> <?= h(lang() === 'fr' ? 'Livrer' : 'Deliver') ?></button></form>
                                <?php elseif (in_array($order['status'], ['pending_payment', 'payment_unknown'], true) && is_string($order['payment_intent_id']) && $order['payment_intent_id'] !== ''): ?>
                                    <form method="post" action="commandes.php"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><input type="hidden" name="action" value="refresh_payment"><button class="button-secondary button-check-payment" type="submit"><?= h(lang() === 'fr' ? 'Vérifier' : 'Check') ?></button></form>
                                <?php elseif ($order['status'] === 'delivered'): ?>
                                    <span class="delivered-at"><?= h($order['delivered_at'] ?? '') ?></span>
                                <?php else: ?>
                                    <span class="delivered-at">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
            <p class="admin-note"><?= h(lang() === 'fr'
                ? 'Seul un paiement confirmé par le service de paiement peut être livré. Actualisez les commandes en attente pour vérifier leur dernier état.'
                : 'Only a payment confirmed by the payment service can be delivered. Refresh pending orders to check their latest status.') ?></p>
        </div>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

