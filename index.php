<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Gooddeal Data — ' . t('hero_title');
$operatorFilter = $_GET['operateur'] ?? 'tous';
if (!in_array($operatorFilter, ['tous', 'airtel', 'moov'], true)) {
    $operatorFilter = 'tous';
}
$statement = db()->query("SELECT * FROM packages WHERE active = 1 ORDER BY CASE `operator` WHEN 'airtel' THEN 0 ELSE 1 END, price ASC");
$packages = $statement->fetchAll();
$checkoutToken = (string) ($_SESSION['checkout_token'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $checkoutToken)) {
    $checkoutToken = bin2hex(random_bytes(16));
}
$tokenReference = 'GD' . strtoupper($checkoutToken);
$existingCheckout = db()->prepare('SELECT status FROM orders WHERE client_reference = ?');
$existingCheckout->execute([$tokenReference]);
$existingStatus = $existingCheckout->fetchColumn();
if (in_array($existingStatus, ['paid', 'delivered', 'failed'], true)) {
    $checkoutToken = bin2hex(random_bytes(16));
}
$_SESSION['checkout_token'] = $checkoutToken;
try {
    $availableMethods = bcp_services();
    $serviceCatalogLoaded = bizconnectpay_configured();
} catch (Throwable) {
    $availableMethods = [];
    $serviceCatalogLoaded = false;
}
$paymentOptions = $serviceCatalogLoaded
    ? $availableMethods
    : ['airtel_money' => 'Airtel Money', 'moov_money' => 'Moov Money'];
require __DIR__ . '/includes/header.php';
?>
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand-mark" href="/" aria-label="Gooddeal Data">
            <img src="/Logo_Airtel_Libertis_data.png" alt="">
        </a>
        <nav class="topbar-actions" aria-label="<?= h(t('language')) ?>">
            <a class="language-link <?= $currentLanguage === 'fr' ? 'active' : '' ?>" href="?lang=fr"><?= $currentLanguage === 'fr' ? 'FR' : 'FR' ?></a>
            <span class="language-divider"></span>
            <a class="language-link <?= $currentLanguage === 'en' ? 'active' : '' ?>" href="?lang=en"><?= $currentLanguage === 'en' ? 'EN' : 'EN' ?></a>
        </nav>
    </div>
</header>

<main>
    <section class="hero-section">
        <div class="container hero-content">
            <div class="hero-copy">
                <span class="eyebrow"><?= h(t('tagline')) ?></span>
                <h1><?= h(t('hero_title')) ?><br><span class="brand-word airtel-word"><?= h(operator_label('airtel')) ?></span> <span class="brand-join">&amp;</span> <span class="brand-word moov-word"><?= h(operator_label('moov')) ?></span></h1>
                <p><?= h(t('hero_text')) ?></p>
            </div>
            <div class="operator-picker" aria-labelledby="operator-label">
                <h2 id="operator-label"><?= h(t('choose_operator')) ?></h2>
                <a class="operator-card operator-airtel" href="#forfaits" data-operator-link="airtel">
                    <span class="operator-logo"><img src="/logo-airtel.jpg" alt="Airtel"></span>
                    <span class="operator-copy"><strong>Airtel</strong><small><?= h(t('see_plans')) ?></small></span>
                    <?= icon('arrow', 'operator-arrow') ?>
                </a>
                <a class="operator-card operator-moov" href="#forfaits" data-operator-link="moov">
                    <span class="operator-logo"><img src="/moov-gabon.jpg" alt="Moov Africa"></span>
                    <span class="operator-copy"><strong>Moov</strong><small><?= h(t('see_plans')) ?></small></span>
                    <?= icon('arrow', 'operator-arrow') ?>
                </a>
            </div>
            <div class="trust-points">
                <div class="trust-point"><?= icon('shield') ?><span><?= h(t('secure_payment')) ?></span></div>
                <div class="trust-point"><?= icon('truck') ?><span><?= h(t('fast_delivery')) ?></span></div>
                <div class="trust-point"><?= icon('user') ?><span><?= h(t('no_account')) ?></span></div>
            </div>
        </div>
    </section>

    <section class="plans-section" id="forfaits">
        <div class="container">
            <div class="section-heading">
                <div>
                    <span class="eyebrow"><?= h(t('offers_title')) ?></span>
                    <h2><?= h(t('plans_title')) ?></h2>
                </div>
                <div class="plan-filters" role="group" aria-label="<?= h(t('plans_title')) ?>">
                    <button class="filter-chip <?= $operatorFilter === 'tous' ? 'active' : '' ?>" type="button" data-filter="tous"><?= h(t('all_plans')) ?></button>
                    <button class="filter-chip <?= $operatorFilter === 'airtel' ? 'active' : '' ?>" type="button" data-filter="airtel">Airtel</button>
                    <button class="filter-chip <?= $operatorFilter === 'moov' ? 'active' : '' ?>" type="button" data-filter="moov">Moov</button>
                </div>
            </div>
            <div class="plan-grid">
                <?php foreach ($packages as $index => $package): ?>
                    <?php $operator = (string) $package['operator']; ?>
                    <article class="plan-card <?= h(operator_color($operator)) ?>" data-operator="<?= h($operator) ?>">
                        <div class="plan-card-top">
                            <img class="plan-operator-logo" src="<?= $operator === 'airtel' ? '/logo-airtel.jpg' : '/moov-gabon.jpg' ?>" alt="<?= h(operator_label($operator)) ?>">
                            <span class="plan-badge"><?= h(operator_label($operator)) ?></span>
                        </div>
                        <h3><?= h(localized_package_name((string) $package['data_volume'])) ?></h3>
                        <p class="plan-subtitle"><?= h(operator_label($operator)) ?> Internet</p>
                        <p class="plan-validity"><?= icon('clock') ?> <?= h(t('valid_for')) ?> <?= h(localized_package_name((string) $package['validity'])) ?></p>
                        <div class="plan-bottom">
                            <strong class="plan-price"><?= h(format_price($package['price'])) ?></strong>
                            <button
                                class="button-buy"
                                type="button"
                                data-buy
                                data-package-id="<?= (int) $package['id'] ?>"
                                data-package-name="<?= h(localized_package_name(operator_label($operator) . ' · ' . $package['data_volume'] . ' · ' . $package['validity'])) ?>"
                                data-package-price="<?= h(format_price($package['price'])) ?>"
                                data-operator="<?= h($operator) ?>"
                                aria-label="<?= h(t('buy') . ' ' . localized_package_name((string) $package['data_volume'])) ?>"
                            ><?= h(t('buy')) ?> <?= icon('arrow') ?></button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if (!$packages): ?>
                <p class="empty-plans"><?= h(lang() === 'fr' ? 'Aucun forfait disponible pour le moment.' : 'No data plans are available right now.') ?></p>
            <?php endif; ?>
        </div>
    </section>

    <section class="how-section">
        <div class="container">
            <div class="section-heading how-heading">
                <div>
                    <span class="eyebrow"><?= h(lang() === 'fr' ? 'Simple et rapide' : 'Simple and fast') ?></span>
                    <h2><?= h(t('how_title')) ?></h2>
                </div>
            </div>
            <div class="steps-grid">
                <div class="step-card"><span class="step-number">1</span><p><?= h(t('step1')) ?></p></div>
                <div class="step-card"><span class="step-number">2</span><p><?= h(t('step2')) ?></p></div>
                <div class="step-card step-admin-trigger" data-admin-trigger tabindex="0" role="button" aria-label="<?= h(t('step3')) ?>"><span class="step-number">3</span><p><?= h(t('step3')) ?></p></div>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <a class="footer-logo" href="/" aria-label="Gooddeal Data"><img src="/Logo_Airtel_Libertis_data.png" alt=""></a>
        <span>© <?= date('Y') ?> Gooddeal Data. <?= h(t('footer_rights')) ?></span>
        <a href="mailto:support@gooddeal.com">support@gooddeal.com</a>
    </div>
</footer>

<a class="support-fab" href="mailto:support@gooddeal.com" aria-label="<?= h(t('support')) ?>" title="<?= h(t('support')) ?>">
    <?= icon('headset') ?>
</a>

<div class="modal fade" id="purchaseModal" tabindex="-1" aria-labelledby="purchaseTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content purchase-modal">
            <div class="modal-header">
                <div>
                    <span class="eyebrow"><?= h(lang() === 'fr' ? 'Paiement simple et sécurisé' : 'Simple and secure payment') ?></span>
                    <h2 class="modal-title" id="purchaseTitle"><?= h(t('purchase_title')) ?></h2>
                </div>
                <button type="button" class="modal-close" data-bs-dismiss="modal" aria-label="<?= h(t('cancel')) ?>"><?= icon('close') ?></button>
            </div>
            <div class="selected-plan-summary" id="selectedPlanSummary"></div>
            <form id="purchaseForm" action="/api/create-order.php" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="checkout_token" value="<?= h($checkoutToken) ?>">
                <input type="hidden" name="package_id" id="packageId">
                <label class="form-label" for="customerPhone"><?= h(t('recipient_phone')) ?></label>
                <input class="form-control" type="tel" id="customerPhone" name="customer_phone" inputmode="tel" autocomplete="tel" placeholder="06 12 34 56" required>
                <label class="form-label mt-3" for="paymentMethod"><?= h(t('payment_method')) ?></label>
                <select class="form-select" id="paymentMethod" name="payment_method" required>
                    <option value=""><?= h(t('select_method')) ?></option>
                    <?php foreach ($paymentOptions as $method => $label): ?>
                        <option value="<?= h($method) ?>"><?= h((string) $label) ?></option>
                    <?php endforeach; ?>
                    <?php if (!$paymentOptions): ?><option value="" disabled><?= h(t('payment_unavailable')) ?></option><?php endif; ?>
                </select>
                <label class="form-label mt-3" for="payerPhone"><?= h(t('payment_phone')) ?></label>
                <input class="form-control" type="tel" id="payerPhone" name="payment_phone" inputmode="tel" autocomplete="tel" placeholder="06 12 34 56" required>
                <p class="form-feedback" id="purchaseFeedback" role="status"></p>
                <div class="modal-actions">
                    <button type="button" class="button-secondary" data-bs-dismiss="modal"><?= h(t('cancel')) ?></button>
                    <button class="button-primary" type="submit" id="purchaseSubmit"><?= h(t('confirm_buy')) ?> <?= icon('arrow') ?></button>
                </div>
            </form>
            <p class="payment-security-note"><?= icon('shield') ?> <?= h(lang() === 'fr' ? 'Vos informations sont utilisées uniquement pour traiter la commande.' : 'Your information is used only to process this order.') ?></p>
        </div>
    </div>
</div>
<script>
window.GooddealData = {
    lang: <?= json_encode($currentLanguage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
    copy: {
        phoneError: <?= json_encode(t('phone_error'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        serviceError: <?= json_encode(t('network_error'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    }
};
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
