<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();

$pdo = db();
$gross = (int) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status IN ('paid', 'delivered')")->fetchColumn();
$pending = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending_payment', 'payment_unknown')")->fetchColumn();
$failed = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'failed'")->fetchColumn();
$totalOrders = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$today = gmdate('Y-m-d');
$weekAgo = gmdate('Y-m-d', time() - 6 * 86400);
$monthStart = gmdate('Y-m-01');
$statement = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN substr(paid_at, 1, 10) = ? THEN amount ELSE 0 END), 0) AS daily,
    COALESCE(SUM(CASE WHEN substr(paid_at, 1, 10) >= ? THEN amount ELSE 0 END), 0) AS weekly,
    COALESCE(SUM(CASE WHEN substr(paid_at, 1, 10) >= ? THEN amount ELSE 0 END), 0) AS monthly
    FROM orders WHERE status IN ('paid', 'delivered')");
$statement->execute([$today, $weekAgo, $monthStart]);
$periods = $statement->fetch() ?: ['daily' => 0, 'weekly' => 0, 'monthly' => 0];
$byOperator = $pdo->query("SELECT `operator`, COUNT(*) AS total, COALESCE(SUM(amount),0) AS revenue FROM orders WHERE status IN ('paid','delivered') GROUP BY `operator`")->fetchAll();
$popular = $pdo->query("SELECT `operator`, package_name, COUNT(*) AS total FROM orders WHERE status IN ('paid','delivered') GROUP BY `operator`, package_name ORDER BY total DESC LIMIT 4")->fetchAll();
$statusRows = $pdo->query("SELECT status, COUNT(*) AS total FROM orders GROUP BY status")->fetchAll();
$recent = $pdo->query("SELECT client_reference, `operator`, package_name, amount, customer_phone, payment_method, status, created_at FROM orders ORDER BY created_at DESC, id DESC LIMIT 8")->fetchAll();
$paidCount = max(1, $gross);
$pageTitle = lang() === 'fr' ? 'Tableau de bord' : 'Dashboard';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="admin-shell">
    <?php require __DIR__ . '/partials/sidebar.php'; ?>
    <main class="admin-main">
        <header class="admin-topbar">
            <button class="admin-menu-toggle" type="button" data-menu-toggle aria-label="Menu"><?= icon('menu') ?></button>
            <div><span class="admin-top-kicker"><?= h(lang() === 'fr' ? 'Bonjour' : 'Welcome') ?>, <?= h($_SESSION['admin_username'] ?? 'admin') ?></span><h1><?= h(lang() === 'fr' ? 'Vue d’ensemble' : 'Overview') ?></h1></div>
            <div class="admin-top-actions">
                <a class="admin-language" href="?lang=<?= $currentLanguage === 'fr' ? 'en' : 'fr' ?>" aria-label="Language"><?= icon('globe') ?><span><?= $currentLanguage === 'fr' ? 'EN' : 'FR' ?></span></a>
                <form method="post" action="logout.php"><?= csrf_field() ?><button class="logout-button" type="submit"><?= icon('logout') ?><span><?= h(lang() === 'fr' ? 'Déconnexion' : 'Sign out') ?></span></button></form>
            </div>
        </header>
        <div class="admin-content">
            <section class="metric-grid">
                <article class="metric-card metric-balance"><span class="metric-label"><?= h(lang() === 'fr' ? 'Solde disponible' : 'Available balance') ?></span><strong class="metric-value">—</strong><span class="metric-hint"><?= h(lang() === 'fr' ? 'Solde non disponible dans l’API' : 'Balance not available from the API') ?></span></article>
                <article class="metric-card"><span class="metric-label"><?= h(lang() === 'fr' ? 'Revenus bruts' : 'Gross revenue') ?></span><strong class="metric-value"><?= h(format_price($gross)) ?></strong><span class="metric-hint"><?= h(lang() === 'fr' ? 'Commandes payées et livrées' : 'Paid and delivered orders') ?></span></article>
                <article class="metric-card"><span class="metric-label"><?= h(lang() === 'fr' ? 'Paiements en attente' : 'Payments pending') ?></span><strong class="metric-value"><?= $pending ?></strong><span class="metric-hint"><?= h(lang() === 'fr' ? 'En attente de confirmation' : 'Awaiting confirmation') ?></span></article>
                <article class="metric-card"><span class="metric-label"><?= h(lang() === 'fr' ? 'Commandes échouées' : 'Failed orders') ?></span><strong class="metric-value"><?= $failed ?></strong><span class="metric-hint"><?= h(lang() === 'fr' ? 'Paiement non confirmé' : 'Payment not confirmed') ?></span></article>
            </section>
            <section class="period-grid">
                <article class="period-card"><span><?= h(lang() === 'fr' ? 'Aujourd’hui' : 'Today') ?></span><strong><?= h(format_price($periods['daily'])) ?></strong><small><?= h(lang() === 'fr' ? 'Ventes confirmées' : 'Confirmed sales') ?></small></article>
                <article class="period-card"><span><?= h(lang() === 'fr' ? 'Cette semaine' : 'This week') ?></span><strong><?= h(format_price($periods['weekly'])) ?></strong><small><?= h(lang() === 'fr' ? 'Ventes confirmées' : 'Confirmed sales') ?></small></article>
                <article class="period-card"><span><?= h(lang() === 'fr' ? 'Ce mois' : 'This month') ?></span><strong><?= h(format_price($periods['monthly'])) ?></strong><small><?= h(lang() === 'fr' ? 'Ventes confirmées' : 'Confirmed sales') ?></small></article>
                <article class="period-card"><span><?= h(lang() === 'fr' ? 'Commandes totales' : 'Total orders') ?></span><strong><?= $totalOrders ?></strong><small><?= h(lang() === 'fr' ? 'Tous statuts' : 'All statuses') ?></small></article>
            </section>
            <section class="admin-insights-grid">
                <article class="admin-panel">
                    <div class="panel-heading"><div><span class="panel-kicker"><?= h(lang() === 'fr' ? 'Ventes confirmées' : 'Confirmed sales') ?></span><h2><?= h(lang() === 'fr' ? 'Revenu par opérateur' : 'Revenue by network') ?></h2></div></div>
                    <?php if (!$byOperator): ?><p class="admin-empty"><?= h(lang() === 'fr' ? 'Les ventes confirmées apparaîtront ici.' : 'Confirmed sales will appear here.') ?></p><?php endif; ?>
                    <?php foreach ($byOperator as $row): ?>
                        <div class="operator-revenue-row"><span class="operator-dot <?= h(operator_color($row['operator'])) ?>"></span><span><?= h(operator_label($row['operator'])) ?></span><strong><?= h(format_price($row['revenue'])) ?></strong><small><?= (int) $row['total'] ?> <?= h(lang() === 'fr' ? 'commandes' : 'orders') ?></small></div>
                    <?php endforeach; ?>
                    <div class="panel-divider"></div>
                    <div class="panel-heading compact"><h3><?= h(lang() === 'fr' ? 'Forfaits populaires' : 'Popular plans') ?></h3></div>
                    <?php if (!$popular): ?><p class="admin-empty"><?= h(lang() === 'fr' ? 'Pas encore de ventes.' : 'No sales yet.') ?></p><?php endif; ?>
                    <?php foreach ($popular as $row): ?>
                        <div class="popular-row"><span><i class="operator-dot <?= h(operator_color($row['operator'])) ?>"></i><?= h(operator_label($row['operator']) . ' · ' . $row['package_name']) ?></span><strong><?= (int) $row['total'] ?></strong></div>
                    <?php endforeach; ?>
                </article>
                <article class="admin-panel">
                    <div class="panel-heading"><div><span class="panel-kicker"><?= h(lang() === 'fr' ? 'Vue rapide' : 'Quick view') ?></span><h2><?= h(lang() === 'fr' ? 'Statut des commandes' : 'Order status') ?></h2></div></div>
                    <?php if (!$statusRows): ?><p class="admin-empty"><?= h(lang() === 'fr' ? 'Aucune commande pour le moment.' : 'No orders yet.') ?></p><?php endif; ?>
                    <?php foreach ($statusRows as $row): ?>
                        <?php $share = $totalOrders > 0 ? round(((int) $row['total'] / $totalOrders) * 100) : 0; ?>
                        <div class="status-stat"><div><span><?= h(match ($row['status']) {
                            'paid' => lang() === 'fr' ? 'Payée' : 'Paid',
                            'delivered' => lang() === 'fr' ? 'Livrée' : 'Delivered',
                            'failed' => lang() === 'fr' ? 'Échouée' : 'Failed',
                            'payment_unknown' => lang() === 'fr' ? 'En vérification' : 'Being checked',
                            default => lang() === 'fr' ? 'En attente' : 'Pending',
                        }) ?></span><strong><?= (int) $row['total'] ?></strong></div><div class="status-track"><i style="width: <?= $share ?>%"></i></div></div>
                    <?php endforeach; ?>
                    <a class="panel-link" href="commandes.php"><?= h(lang() === 'fr' ? 'Voir les commandes' : 'View orders') ?> <?= icon('arrow') ?></a>
                </article>
            </section>
            <section class="admin-panel orders-panel">
                <div class="panel-heading"><div><span class="panel-kicker"><?= h(lang() === 'fr' ? 'Dernière activité' : 'Latest activity') ?></span><h2><?= h(lang() === 'fr' ? 'Historique des commandes' : 'Order history') ?></h2></div><a class="panel-link" href="commandes.php"><?= h(lang() === 'fr' ? 'Tout voir' : 'View all') ?> <?= icon('arrow') ?></a></div>
                <?php if (!$recent): ?><p class="admin-empty"><?= h(lang() === 'fr' ? 'Les commandes payées apparaîtront ici.' : 'Paid orders will appear here.') ?></p><?php else: ?>
                    <div class="table-wrap"><table class="admin-table"><thead><tr><th><?= h(lang() === 'fr' ? 'Référence' : 'Reference') ?></th><th><?= h(lang() === 'fr' ? 'Client' : 'Customer') ?></th><th><?= h(lang() === 'fr' ? 'Forfait' : 'Plan') ?></th><th><?= h(lang() === 'fr' ? 'Montant' : 'Amount') ?></th><th><?= h(lang() === 'fr' ? 'Statut' : 'Status') ?></th></tr></thead><tbody>
                    <?php foreach ($recent as $row): ?>
                        <?php
                        $recentStatus = (string) $row['status'];
                        $recentStatusLabel = match ($recentStatus) {
                            'pending_payment' => lang() === 'fr' ? 'En attente' : 'Pending',
                            'payment_unknown' => lang() === 'fr' ? 'En vérification' : 'Being checked',
                            'paid' => lang() === 'fr' ? 'Paiement confirmé' : 'Payment confirmed',
                            'delivered' => lang() === 'fr' ? 'Livrée' : 'Delivered',
                            'failed' => lang() === 'fr' ? 'Échouée' : 'Failed',
                            default => lang() === 'fr' ? 'Statut inconnu' : 'Unknown status',
                        };
                        $recentStatusClass = in_array($recentStatus, ['pending_payment', 'payment_unknown', 'paid', 'delivered', 'failed'], true)
                            ? $recentStatus
                            : 'payment_unknown';
                        ?>
                        <tr><td class="reference-cell"><?= h($row['client_reference']) ?></td><td><?= h($row['customer_phone']) ?></td><td><span class="operator-pill <?= h(operator_color($row['operator'])) ?>"><?= h(operator_label($row['operator'])) ?></span> <?= h($row['package_name']) ?></td><td><?= h(format_price($row['amount'])) ?></td><td><span class="status-pill <?= h($recentStatusClass) ?>"><?= h($recentStatusLabel) ?></span></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

