<?php
declare(strict_types=1);
$activeCustomerNav = $activeCustomerNav ?? 'home';
$customerLinks = [
    'home' => ['href' => '/#accueil', 'icon' => 'home', 'label' => t('orders_home')],
    'plans' => ['href' => '/#forfaits', 'icon' => 'sim', 'label' => t('orders_plans')],
    'orders' => ['href' => '/commandes.php', 'icon' => 'receipt', 'label' => t('orders')],
];
?>
<nav class="customer-bottom-nav" aria-label="<?= h(t('orders_nav_label')) ?>">
    <div class="customer-bottom-nav-inner">
        <?php foreach ($customerLinks as $key => $link): ?>
            <a href="<?= h($link['href']) ?>" class="customer-nav-link <?= $activeCustomerNav === $key ? 'active' : '' ?>" <?= $activeCustomerNav === $key ? 'aria-current="page"' : '' ?>>
                <?= icon($link['icon']) ?>
                <span><?= h($link['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</nav>
