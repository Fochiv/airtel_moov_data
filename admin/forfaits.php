<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_admin();
$pdo = db();
$message = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
            $operator = (string) ($_POST['operator'] ?? '');
            $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_INT);
            $volume = trim((string) ($_POST['data_volume'] ?? ''));
            $validity = trim((string) ($_POST['validity'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $active = isset($_POST['active']) ? 1 : 0;
            if (!in_array($operator, ['airtel', 'moov'], true)
                || !$price || $price < 1 || $price > 100000000
                || $volume === '' || mb_strlen($volume) > 100
                || $validity === '' || mb_strlen($validity) > 100
                || mb_strlen($description) > 255) {
                throw new InvalidArgumentException(lang() === 'fr' ? 'Vérifiez les champs du forfait.' : 'Check the plan fields.');
            }
            if ($id > 0) {
                $save = $pdo->prepare('UPDATE packages SET `operator` = ?, price = ?, data_volume = ?, validity = ?, description = ?, active = ? WHERE id = ?');
                $save->execute([$operator, $price, $volume, $validity, $description, $active, $id]);
            } else {
                $save = $pdo->prepare('INSERT INTO packages (`operator`, price, data_volume, validity, description, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $save->execute([$operator, $price, $volume, $validity, $description, $active, gmdate('Y-m-d H:i:s')]);
            }
            flash('success', lang() === 'fr' ? 'Forfait enregistré.' : 'Plan saved.');
        } elseif ($action === 'toggle') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Forfait introuvable.');
            }
            $toggle = $pdo->prepare('UPDATE packages SET active = CASE WHEN active = 1 THEN 0 ELSE 1 END WHERE id = ?');
            $toggle->execute([$id]);
            flash('success', lang() === 'fr' ? 'Statut du forfait mis à jour.' : 'Plan status updated.');
        } elseif ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new InvalidArgumentException('Forfait introuvable.');
            }
            $used = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE package_id = ?');
            $used->execute([$id]);
            if ((int) $used->fetchColumn() > 0) {
                throw new InvalidArgumentException(lang() === 'fr'
                    ? 'Ce forfait est lié à des commandes. Désactivez-le plutôt que de le supprimer.'
                    : 'This plan has linked orders. Deactivate it instead of deleting it.');
            }
            $delete = $pdo->prepare('DELETE FROM packages WHERE id = ?');
            $delete->execute([$id]);
            flash('success', lang() === 'fr' ? 'Forfait supprimé.' : 'Plan deleted.');
        }
    } catch (InvalidArgumentException $error) {
        flash('error', $error->getMessage());
    }
    header('Location: forfaits.php');
    exit;
}

$packages = $pdo->query("SELECT * FROM packages ORDER BY CASE `operator` WHEN 'airtel' THEN 0 ELSE 1 END, price ASC")->fetchAll();
$flashMessage = take_flash();
$activeAdminPage = 'packages';
$pageTitle = lang() === 'fr' ? 'Gestion des forfaits' : 'Data plans';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="admin-shell">
    <?php require __DIR__ . '/partials/sidebar.php'; ?>
    <main class="admin-main">
        <header class="admin-topbar">
            <button class="admin-menu-toggle" type="button" data-menu-toggle aria-label="Menu"><?= icon('menu') ?></button>
            <div><span class="admin-top-kicker"><?= h(lang() === 'fr' ? 'Catalogue' : 'Catalog') ?></span><h1><?= h(lang() === 'fr' ? 'Gestion des forfaits' : 'Manage data plans') ?></h1></div>
            <div class="admin-top-actions"><a class="admin-language" href="?lang=<?= $currentLanguage === 'fr' ? 'en' : 'fr' ?>"><?= icon('globe') ?><span><?= $currentLanguage === 'fr' ? 'EN' : 'FR' ?></span></a></div>
        </header>
        <div class="admin-content">
            <?php if ($flashMessage): ?><div class="admin-alert <?= h($flashMessage['type']) ?>" role="status"><?= h($flashMessage['message']) ?></div><?php endif; ?>
            <section class="admin-panel">
                <div class="panel-heading"><div><span class="panel-kicker"><?= count($packages) ?> <?= h(lang() === 'fr' ? 'forfaits au catalogue' : 'plans in catalog') ?></span><h2><?= h(lang() === 'fr' ? 'Tous les forfaits' : 'All plans') ?></h2></div><button class="button-primary" type="button" data-bs-toggle="modal" data-bs-target="#packageModal"><?= icon('plus') ?> <?= h(lang() === 'fr' ? 'Ajouter un forfait' : 'Add a plan') ?></button></div>
                <div class="table-wrap"><table class="admin-table"><thead><tr><th><?= h(lang() === 'fr' ? 'Opérateur' : 'Network') ?></th><th><?= h(lang() === 'fr' ? 'Data' : 'Data') ?></th><th><?= h(lang() === 'fr' ? 'Validité' : 'Validity') ?></th><th><?= h(lang() === 'fr' ? 'Prix' : 'Price') ?></th><th><?= h(lang() === 'fr' ? 'Statut' : 'Status') ?></th><th><?= h(lang() === 'fr' ? 'Actions' : 'Actions') ?></th></tr></thead><tbody>
                <?php foreach ($packages as $package): ?>
                    <tr>
                        <td><span class="operator-pill <?= h(operator_color($package['operator'])) ?>"><?= h(operator_label($package['operator'])) ?></span></td>
                        <td><strong><?= h($package['data_volume']) ?></strong><small class="cell-subtitle"><?= h($package['description']) ?></small></td>
                        <td><?= h($package['validity']) ?></td>
                        <td><strong><?= h(format_price($package['price'])) ?></strong></td>
                        <td><span class="status-pill <?= (int) $package['active'] === 1 ? 'delivered' : 'failed' ?>"><?= h((int) $package['active'] === 1 ? (lang() === 'fr' ? 'Actif' : 'Active') : (lang() === 'fr' ? 'Inactif' : 'Inactive')) ?></span></td>
                        <td><div class="table-actions">
                            <button class="icon-button" type="button" data-edit-package
                                data-id="<?= (int) $package['id'] ?>"
                                data-operator="<?= h($package['operator']) ?>"
                                data-price="<?= (int) $package['price'] ?>"
                                data-volume="<?= h($package['data_volume']) ?>"
                                data-validity="<?= h($package['validity']) ?>"
                                data-description="<?= h($package['description']) ?>"
                                data-active="<?= (int) $package['active'] ?>"
                                aria-label="<?= h(lang() === 'fr' ? 'Modifier' : 'Edit') ?>"><?= icon('edit') ?></button>
                            <form method="post" action="forfaits.php">
                                <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $package['id'] ?>">
                                <button class="icon-button" type="submit" aria-label="<?= h(lang() === 'fr' ? 'Activer ou désactiver' : 'Toggle status') ?>"><?= icon((int) $package['active'] === 1 ? 'close' : 'check') ?></button>
                            </form>
                            <form method="post" action="forfaits.php">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $package['id'] ?>">
                                <button class="icon-button danger" type="submit" data-confirm="<?= h(lang() === 'fr' ? 'Supprimer ce forfait ? Cette action est définitive.' : 'Delete this plan? This cannot be undone.') ?>" aria-label="<?= h(lang() === 'fr' ? 'Supprimer' : 'Delete') ?>"><?= icon('trash') ?></button>
                            </form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
            </section>
        </div>
    </main>
</div>
<div class="modal fade" id="packageModal" tabindex="-1" aria-labelledby="packageModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content purchase-modal">
        <div class="modal-header"><div><span class="eyebrow"><?= h(lang() === 'fr' ? 'Catalogue' : 'Catalog') ?></span><h2 class="modal-title" id="packageModalTitle"><?= h(lang() === 'fr' ? 'Nouveau forfait' : 'New plan') ?></h2></div><button type="button" class="modal-close" data-bs-dismiss="modal" aria-label="Close"><?= icon('close') ?></button></div>
        <form method="post" action="forfaits.php" id="packageForm" class="admin-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="packageEditId" value="">
            <label class="form-label" for="packageOperator"><?= h(lang() === 'fr' ? 'Opérateur' : 'Network') ?></label>
            <select class="form-select" id="packageOperator" name="operator" required><option value="airtel">Airtel</option><option value="moov">Moov</option></select>
            <div class="form-grid-two">
                <div><label class="form-label" for="packagePrice"><?= h(lang() === 'fr' ? 'Prix (FCFA)' : 'Price (XAF)') ?></label><input class="form-control admin-input" id="packagePrice" name="price" type="number" min="1" max="100000000" required></div>
                <div><label class="form-label" for="packageVolume"><?= h(lang() === 'fr' ? 'Volume data' : 'Data volume') ?></label><input class="form-control admin-input" id="packageVolume" name="data_volume" maxlength="100" placeholder="5 Go" required></div>
            </div>
            <label class="form-label mt-3" for="packageValidity"><?= h(lang() === 'fr' ? 'Validité' : 'Validity') ?></label><input class="form-control admin-input" id="packageValidity" name="validity" maxlength="100" placeholder="7 jours" required>
            <label class="form-label mt-3" for="packageDescription"><?= h(lang() === 'fr' ? 'Description courte' : 'Short description') ?></label><input class="form-control admin-input" id="packageDescription" name="description" maxlength="255">
            <label class="admin-checkbox mt-3"><input type="checkbox" id="packageActive" name="active" checked><span><?= h(lang() === 'fr' ? 'Forfait actif et visible sur le site' : 'Active and visible on the site') ?></span></label>
            <div class="modal-actions mt-4"><button type="button" class="button-secondary" data-bs-dismiss="modal"><?= h(lang() === 'fr' ? 'Annuler' : 'Cancel') ?></button><button class="button-primary" type="submit"><?= h(lang() === 'fr' ? 'Enregistrer' : 'Save') ?> <?= icon('check') ?></button></div>
        </form>
    </div></div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>

