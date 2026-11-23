<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (is_admin()) {
    header('Location: index.php');
    exit;
}
$pdo = db();
$adminExists = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0;
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $ipHash = hash('sha256', $remote . (string) app_config('APP_RATE_LIMIT_SALT', 'gooddeal-local-rate-limit'));
    $deleteOld = $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?');
    $deleteOld->execute([time() - 900]);
    $countAttempts = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND attempted_at >= ?');
    $countAttempts->execute([$ipHash, time() - 900]);
    $locked = (int) $countAttempts->fetchColumn() >= 5;

    if ($adminExists && !$locked) {
        $find = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ? LIMIT 1');
        $find->execute(['admin']);
        $admin = $find->fetch();
        if (is_array($admin) && password_verify($password, (string) $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_username'] = (string) $admin['username'];
            $clear = $pdo->prepare('DELETE FROM login_attempts WHERE ip_hash = ?');
            $clear->execute([$ipHash]);
            header('Location: index.php');
            exit;
        }
    }
    $record = $pdo->prepare('INSERT INTO login_attempts (ip_hash, attempted_at) VALUES (?, ?)');
    $record->execute([$ipHash, time()]);
    $error = $locked
        ? (lang() === 'fr' ? 'Trop de tentatives. Réessayez dans 15 minutes.' : 'Too many attempts. Try again in 15 minutes.')
        : (lang() === 'fr' ? 'Mot de passe incorrect.' : 'Incorrect password.');
}

$pageTitle = lang() === 'fr' ? 'Connexion administrateur' : 'Administrator sign in';
require dirname(__DIR__) . '/includes/header.php';
?>
<main class="admin-login-page">
    <div class="admin-login-card">
        <nav class="login-language" aria-label="<?= h(t('language')) ?>">
            <a href="?lang=fr" class="<?= $currentLanguage === 'fr' ? 'active' : '' ?>">FR</a>
            <a href="?lang=en" class="<?= $currentLanguage === 'en' ? 'active' : '' ?>">EN</a>
        </nav>
        <a class="login-logo" href="/" aria-label="Gooddeal Data"><img src="/Logo_Airtel_Libertis_data.png" alt=""></a>
        <span class="eyebrow"><?= h(lang() === 'fr' ? 'Espace sécurisé' : 'Secure area') ?></span>
        <h1><?= h(lang() === 'fr' ? 'Connexion admin' : 'Admin sign in') ?></h1>
        <p><?= h(lang() === 'fr' ? 'Connectez-vous pour gérer les forfaits et les commandes.' : 'Sign in to manage plans and orders.') ?></p>
        <?php if (!$adminExists): ?>
            <div class="admin-alert warning">
                <?= h(lang() === 'fr'
                    ? 'Le compte administrateur n’est pas configuré. Ajoutez un secret ADMIN_INITIAL_PASSWORD d’au moins 12 caractères au projet, puis rechargez cette page.'
                    : 'The administrator account is not configured. Add an ADMIN_INITIAL_PASSWORD secret of at least 12 characters, then reload this page.') ?>
            </div>
        <?php endif; ?>
        <?php if ($error !== ''): ?><div class="admin-alert error" role="alert"><?= h($error) ?></div><?php endif; ?>
        <?php if ($adminExists): ?>
            <form method="post" action="login.php">
                <?= csrf_field() ?>
                <input type="text" name="username" value="admin" autocomplete="username" class="visually-hidden" tabindex="-1" aria-hidden="true">
                <label class="form-label" for="password"><?= h(lang() === 'fr' ? 'Mot de passe' : 'Password') ?></label>
                <input class="form-control admin-input" id="password" name="password" type="password" autocomplete="current-password" required>
                <button class="button-primary w-100 mt-4" type="submit"><?= h(lang() === 'fr' ? 'Se connecter' : 'Sign in') ?> <?= icon('arrow') ?></button>
            </form>
        <?php endif; ?>
        <a class="login-back" href="/"><?= h(lang() === 'fr' ? 'Retour au site' : 'Back to site') ?></a>
    </div>
</main>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

