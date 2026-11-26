<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$pdo = db();
$apiMessage = '';
$apiMessageClass = '';
$flashMessage = take_flash();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $find = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
        $find->execute([(int) $_SESSION['admin_id']]);
        $admin = $find->fetch();
        if (!is_array($admin) || !password_verify($currentPassword, (string) $admin['password_hash'])) {
            flash('error', lang() === 'fr' ? 'Le mot de passe actuel est incorrect.' : 'The current password is incorrect.');
        } elseif (strlen($newPassword) < 12) {
            flash('error', lang() === 'fr' ? 'Choisissez un mot de passe d’au moins 12 caractères.' : 'Choose a password with at least 12 characters.');
        } elseif (!hash_equals($newPassword, $confirmPassword)) {
            flash('error', lang() === 'fr' ? 'Les mots de passe ne correspondent pas.' : 'The passwords do not match.');
        } else {
            $update = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
            $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $_SESSION['admin_id']]);
            flash('success', lang() === 'fr' ? 'Mot de passe mis à jour.' : 'Password updated.');
        }
        header('Location: parametres.php');
        exit;
    } else {
        try {
            if (!bizconnectpay_configured()) {
                throw new RuntimeException('Payment configuration is incomplete.');
            }
            $services = bcp_services();
            $apiMessage = lang() === 'fr'
                ? (count($services) ? 'Connexion prête. ' . count($services) . ' moyen(s) de paiement disponible(s) au Gabon.' : 'Connexion établie, mais aucun moyen de paiement Mobile Money n’est activé pour le Gabon.')
                : (count($services) ? 'Connection ready. ' . count($services) . ' payment method(s) available in Gabon.' : 'Connected, but no Mobile Money payment method is enabled for Gabon.');
            $apiMessageClass = count($services) ? 'success' : 'warning';
        } catch (Throwable) {
            $apiMessage = lang() === 'fr'
                ? 'Connexion non configurée ou indisponible. Vérifiez les secrets du projet.'
                : 'Connection is not configured or is unavailable. Check the project secrets.';
            $apiMessageClass = 'warning';
        }
    }
}
$paymentEnvironment = 'SANDBOX';
try {
    $paymentEnvironment = bizconnectpay_environment();
} catch (Throwable) {
    $paymentEnvironment = 'CONFIGURATION';
}
$isConfigured = bizconnectpay_configured();
$environmentClass = $paymentEnvironment === 'SANDBOX'
    ? 'pending'
    : ($paymentEnvironment === 'LIVE' && $isConfigured ? 'delivered' : 'failed');
try {
    $paymentMethods = $isConfigured ? bcp_services() : [];
} catch (Throwable) {
    $paymentMethods = [];
}
$activeAdminPage = 'settings';
$pageTitle = lang() === 'fr' ? 'Paramètres' : 'Settings';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="admin-shell">
    <?php require __DIR__ . '/partials/sidebar.php'; ?>
    <main class="admin-main">
        <header class="admin-topbar">
            <button class="admin-menu-toggle" type="button" data-menu-toggle aria-label="Menu"><?= icon('menu') ?></button>
            <div><span class="admin-top-kicker"><?= h(lang() === 'fr' ? 'Configuration du site' : 'Site configuration') ?></span><h1><?= h(lang() === 'fr' ? 'Paramètres' : 'Settings') ?></h1></div>
            <div class="admin-top-actions"><a class="admin-language" href="?lang=<?= $currentLanguage === 'fr' ? 'en' : 'fr' ?>"><?= icon('globe') ?><span><?= $currentLanguage === 'fr' ? 'EN' : 'FR' ?></span></a></div>
        </header>
        <div class="admin-content settings-content">
            <?php if ($apiMessage !== ''): ?><div class="admin-alert <?= h($apiMessageClass) ?>" role="status"><?= h($apiMessage) ?></div><?php endif; ?>
            <?php if ($flashMessage): ?><div class="admin-alert <?= h($flashMessage['type']) ?>" role="status"><?= h($flashMessage['message']) ?></div><?php endif; ?>
            <section class="admin-panel settings-panel">
                <div class="panel-heading"><div><span class="panel-kicker"><?= h(lang() === 'fr' ? 'Configuration du paiement' : 'Payment configuration') ?></span><h2><?= h(lang() === 'fr' ? 'Paiement en ligne' : 'Online payment') ?></h2></div><span class="status-pill <?= $isConfigured ? 'delivered' : 'failed' ?>"><?= h($isConfigured ? (lang() === 'fr' ? 'Configuré' : 'Configured') : (lang() === 'fr' ? 'À configurer' : 'Setup required')) ?></span></div>
                <p><?= h(lang() === 'fr'
                    ? 'Les clés de paiement restent sur le serveur. Elles ne sont jamais envoyées au navigateur ni affichées dans l’interface client.'
                    : 'Payment credentials stay on the server. They are never sent to the browser or shown in the customer interface.') ?></p>
                <dl class="settings-list">
                    <div><dt><?= h(lang() === 'fr' ? 'Mode' : 'Mode') ?></dt><dd><span class="status-pill <?= h($environmentClass) ?>"><?= h($paymentEnvironment) ?></span></dd></div>
                    <div><dt><?= h(lang() === 'fr' ? 'Pays / devise' : 'Country / currency') ?></dt><dd>Gabon · XAF</dd></div>
                    <div><dt><?= h(lang() === 'fr' ? 'Moyens disponibles' : 'Available methods') ?></dt><dd>
                        <?php if (!$paymentMethods): ?><?= h(lang() === 'fr' ? 'Aucun moyen confirmé' : 'No confirmed methods') ?>
                        <?php else: ?><?= h(implode(', ', array_map(static fn(string $method): string => gabon_payment_method_label($method) ?? $method, array_keys($paymentMethods)))) ?><?php endif; ?>
                    </dd></div>
                    <div><dt>Webhook</dt><dd><code><?= h((string) app_config('PUBLIC_WEBHOOK_URL', 'https://gooddeal-ga.top/webhook.php')) ?></code></dd></div>
                    <div><dt><?= h(lang() === 'fr' ? 'Assistance client' : 'Customer support') ?></dt><dd><a href="mailto:support@gooddeal-ga.top">support@gooddeal-ga.top</a></dd></div>
                </dl>
                <form method="post" action="parametres.php" class="settings-actions"><?= csrf_field() ?><button class="button-primary" type="submit"><?= icon('shield') ?> <?= h(lang() === 'fr' ? 'Vérifier la connexion' : 'Check connection') ?></button></form>
                <?php if (!$isConfigured): ?><p class="admin-note"><?= h(lang() === 'fr' ? 'Ajoutez les identifiants et le secret webhook correspondant au mode sélectionné dans la configuration serveur. Ne les collez pas dans une conversation ni dans le code.' : 'Add the credentials and webhook secret for the selected mode in the server configuration. Do not paste them into chat or source code.') ?></p><?php endif; ?>
            </section>
            <section class="admin-panel settings-panel">
                <div class="panel-heading"><div><span class="panel-kicker"><?= h(lang() === 'fr' ? 'Sécurité' : 'Security') ?></span><h2><?= h(lang() === 'fr' ? 'Compte administrateur' : 'Administrator account') ?></h2></div></div>
                <p><?= h(lang() === 'fr' ? 'Le mot de passe est stocké sous forme de hash. La connexion est limitée après plusieurs tentatives incorrectes.' : 'The password is stored as a hash. Sign-in is rate limited after repeated failed attempts.') ?></p>
                <form method="post" action="parametres.php" class="password-change-form">
                    <?= csrf_field() ?><input type="hidden" name="action" value="change_password">
                    <label class="form-label" for="currentPassword"><?= h(lang() === 'fr' ? 'Mot de passe actuel' : 'Current password') ?></label>
                    <input class="form-control admin-input" id="currentPassword" name="current_password" type="password" autocomplete="current-password" required>
                    <div class="form-grid-two">
                        <div><label class="form-label" for="newPassword"><?= h(lang() === 'fr' ? 'Nouveau mot de passe' : 'New password') ?></label><input class="form-control admin-input" id="newPassword" name="new_password" type="password" minlength="12" autocomplete="new-password" required></div>
                        <div><label class="form-label" for="confirmPassword"><?= h(lang() === 'fr' ? 'Confirmer le mot de passe' : 'Confirm password') ?></label><input class="form-control admin-input" id="confirmPassword" name="confirm_password" type="password" minlength="12" autocomplete="new-password" required></div>
                    </div>
                    <button class="button-primary mt-4" type="submit"><?= h(lang() === 'fr' ? 'Mettre à jour le mot de passe' : 'Update password') ?> <?= icon('check') ?></button>
                </form>
            </section>
        </div>
    </main>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

