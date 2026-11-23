<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    if ($orderId) {
        $deliver = $pdo->prepare("UPDATE orders SET status = 'delivered', delivered_at = ? WHERE id = ? AND status = 'paid'");
        $deliver->execute([gmdate('Y-m-d H:i:s'), $orderId]);
        flash($deliver->rowCount() === 1 ? 'success' : 'error', $deliver->rowCount() === 1
            ? (lang() === 'fr' ? 'Commande marquée comme livrée.' : 'Order marked as delivered.')
            : (lang() === 'fr' ? 'Cette commande n’est pas disponible pour livraison.' : 'This order is not available for delivery.'));
    }
    header('Location: commandes.php');
    exit;
}

$operator = (string) ($_GET['operator'] ?? '');
$allowedOperator = in_array($operator, ['airtel', 'moov'], true) ? $operator : '';
$search = trim((string) ($_GET['q'] ?? ''));
$sql = "SELECT id, client_reference, `operator`, package_name, amount, customer_phone, payment_phone, payment_method, status, created_at, paid_at, delivered_at FROM orders WHERE status IN ('paid', 'delivered')";
$params = [];
if ($allowedOperator !== '') {
    $sql .= ' AND `operator` = ?';
    $params[] = $allowedOperator;
}
if ($search !== '') {
    $sql .= ' AND (client_reference LIKE ? OR customer_phone LIKE ? OR package_name LIKE ?)';
    $needle = '%' . $search . '%';
    array_push($params, $needle, $needle, $needle);
}
$sql .= ' ORDER BY paid_at DESC, id DESC LIMIT 250';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$orders = $statement->fetchAll();
$flashMessage = take_flash();
$activeAdminPage = 'orders';
$pageTitle = lang() === 'fr' ? 'Commandes payées' : 'Paid orders';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="admin-shell">
    <?php require __DIR__ . '/partials/sidebar.php'; ?>
    <main class="admin-main">
        <header class="admin-topbar">
            <button class="admin-menu-toggle" type="button" data-menu-toggle aria-label="Menu"><?= icon('menu') ?></button>
            <div><span class="admin-top-kicker"><?= h(lang() === 'fr' ? 'Paiements confirmés' : 'Confirmed payments') ?></span><h1><?= h(lang() === 'fr' ? 'Commandes payées' : 'Paid orders') ?></h1></div>
            <div class="admin-top-actions"><a class="admin-language" href="?lang=<?= $currentLanguage === 'fr' ? 'en' : 'fr' ?>"><?= icon('globe') ?><span><?= $currentLanguage === 'fr' ? 'EN' : 'FR' ?></span></a></div>
        </header>
        <div class="admin-content">
            <?php if ($flashMessage): ?><div class="admin-alert <?= h($flashMessage['type']) ?>" role="status"><?= h($flashMessage['message']) ?></div><?php endif; ?>
            <section class="admin-panel orders-panel">
                <div class="panel-heading"><div><span class="panel-kicker"><?= count($orders) ?> <?= h(lang() === 'fr' ? 'commandes affichées' : 'orders shown') ?></span><h2><?= h(lang() === 'fr' ? 'Historique des commandes' : 'Order history') ?></h2></div></div>
                <form class="order-search" method="get" action="commandes.php">
                    <label class="search-field"><?= icon('search') ?><input name="q" type="search" value="<?= h($search) ?>" placeholder="<?= h(lang() === 'fr' ? 'Référence, numéro ou forfait' : 'Reference, number or plan') ?>"></label>
                    <select class="form-select" name="operator" aria-label="<?= h(lang() === 'fr' ? 'Opérateur' : 'Network') ?>"><option value=""><?= h(lang() === 'fr' ? 'Tous les opérateurs' : 'All networks') ?></option><option value="airtel" <?= $allowedOperator === 'airtel' ? 'selected' : '' ?>>Airtel</option><option value="moov" <?= $allowedOperator === 'moov' ? 'selected' : '' ?>>Moov</option></select>
                    <button class="button-primary" type="submit"><?= icon('filter') ?> <?= h(lang() === 'fr' ? 'Filtrer' : 'Filter') ?></button>
                </form>
                <?php if (!$orders): ?><p class="admin-empty"><?= h(lang() === 'fr' ? 'Aucune commande payée ne correspond à votre recherche.' : 'No paid orders match your search.') ?></p><?php else: ?>
                    <div class="table-wrap"><table class="admin-table"><thead><tr><th><?= h(lang() === 'fr' ? 'Référence' : 'Reference') ?></th><th><?= h(lang() === 'fr' ? 'Client' : 'Customer') ?></th><th><?= h(lang() === 'fr' ? 'Forfait' : 'Plan') ?></th><th><?= h(lang() === 'fr' ? 'Paiement' : 'Payment') ?></th><th><?= h(lang() === 'fr' ? 'Montant' : 'Amount') ?></th><th><?= h(lang() === 'fr' ? 'Statut' : 'Status') ?></th><th><?= h(lang() === 'fr' ? 'Action' : 'Action') ?></th></tr></thead><tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td class="reference-cell"><?= h($order['client_reference']) ?><small class="cell-subtitle"><?= h($order['paid_at'] ?? $order['created_at']) ?></small></td>
                            <td><?= h($order['customer_phone']) ?><small class="cell-subtitle"><?= h(lang() === 'fr' ? 'Payeur :' : 'Payer:') ?> <?= h($order['payment_phone']) ?></small></td>
                            <td><span class="operator-pill <?= h(operator_color($order['operator'])) ?>"><?= h(operator_label($order['operator'])) ?></span><small class="cell-subtitle"><?= h($order['package_name']) ?></small></td>
                            <td><?= h($order['payment_method'] === 'orange_money' ? 'Orange Money' : 'MTN Mobile Money') ?></td>
                            <td><strong><?= h(format_price($order['amount'])) ?></strong></td>
                            <td><span class="status-pill <?= h($order['status']) ?>"><?= h($order['status'] === 'delivered' ? (lang() === 'fr' ? 'Livrée' : 'Delivered') : (lang() === 'fr' ? 'Payée' : 'Paid')) ?></span></td>
                            <td>
                                <?php if ($order['status'] === 'paid'): ?>
                                    <form method="post" action="commandes.php"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><button class="button-deliver" type="submit" data-confirm="<?= h(lang() === 'fr' ? 'Confirmer la livraison de ce forfait ?' : 'Confirm delivery of this plan?') ?>"><?= icon('check') ?> <?= h(lang() === 'fr' ? 'Livrer' : 'Deliver') ?></button></form>
                                <?php else: ?><span class="delivered-at"><?= h($order['delivered_at'] ?? '') ?></span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
            <p class="admin-note"><?= h(lang() === 'fr' ? 'Seules les commandes dont le paiement a été confirmé sont affichées ici.' : 'Only orders with confirmed payments are shown here.') ?></p>
        </div>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

