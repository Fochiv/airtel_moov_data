<?php
declare(strict_types=1);
$navItems = admin_nav($activeAdminPage ?? 'dashboard');
?>
<aside class="admin-sidebar">
    <a class="sidebar-brand" href="/" aria-label="Gooddeal Data"><img src="/Logo_Airtel_Libertis_data.png" alt=""></a>
    <div class="sidebar-caption"><?= h(lang() === 'fr' ? 'GESTION' : 'MANAGEMENT') ?></div>
    <nav class="sidebar-nav" aria-label="<?= h(lang() === 'fr' ? 'Administration' : 'Administration') ?>">
        <?php foreach ($navItems as $key => $item): ?>
            <a href="<?= h($item['href']) ?>" class="sidebar-link <?= ($activeAdminPage ?? '') === $key ? 'active' : '' ?>">
                <?= icon($item['icon']) ?><span><?= h($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-spacer"></div>
    <div class="sidebar-support">
        <span class="sidebar-support-mark"><?= icon('headset') ?></span>
        <div><strong><?= h(lang() === 'fr' ? 'Besoin d’aide ?' : 'Need help?') ?></strong><a href="mailto:support@gooddeal-ga.top">support@gooddeal-ga.top</a></div>
    </div>
    <form class="sidebar-logout" method="post" action="logout.php">
        <?= csrf_field() ?>
        <button type="submit"><?= icon('logout') ?><span><?= h(lang() === 'fr' ? 'Déconnexion' : 'Sign out') ?></span></button>
    </form>
</aside>
<button class="admin-overlay" type="button" data-menu-toggle aria-label="<?= h(lang() === 'fr' ? 'Fermer le menu' : 'Close menu') ?>"></button>

