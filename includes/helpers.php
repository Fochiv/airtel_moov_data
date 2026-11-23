<?php
declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function lang(): string
{
    $requested = $_GET['lang'] ?? null;
    if (is_string($requested) && in_array($requested, ['fr', 'en'], true)) {
        $_SESSION['lang'] = $requested;
    }
    return ($_SESSION['lang'] ?? 'fr') === 'en' ? 'en' : 'fr';
}

function t(string $key): string
{
    static $translations = [
        'fr' => [
            'tagline' => 'INTERNET MOBILE, SIMPLEMENT',
            'hero_title' => 'Choisissez votre opérateur',
            'hero_text' => 'Trouvez le forfait internet qui vous convient.',
            'choose_operator' => 'Choisissez votre opérateur',
            'see_plans' => 'Voir les forfaits',
            'secure_payment' => 'Paiement sécurisé',
            'fast_delivery' => 'Livraison rapide',
            'no_account' => 'Sans compte client',
            'plans_title' => 'Les forfaits disponibles',
            'all_plans' => 'Tous les forfaits',
            'weekly' => 'Hebdomadaires',
            'monthly' => 'Mensuels',
            'valid_for' => 'Valable',
            'buy' => 'Acheter',
            'popular' => 'Populaire',
            'how_title' => 'Comment ça marche',
            'step1' => 'Choisissez votre opérateur et votre forfait.',
            'step2' => 'Indiquez le numéro à recharger.',
            'step3' => 'Effectuez le paiement en toute sécurité.',
            'offers_title' => 'Nos forfaits',
            'footer_rights' => 'Tous droits réservés.',
            'purchase_title' => 'Finaliser votre achat',
            'recipient_phone' => 'Numéro à recharger',
            'payment_method' => 'Moyen de paiement',
            'payment_phone' => 'Numéro Mobile Money',
            'select_method' => 'Choisissez un moyen de paiement',
            'payment_unavailable' => 'Aucun moyen de paiement disponible pour le moment.',
            'confirm_buy' => 'Continuer vers le paiement',
            'cancel' => 'Annuler',
            'support' => 'Contacter l’assistance',
            'order_waiting' => 'Paiement en attente de confirmation',
            'order_paid' => 'Paiement confirmé',
            'order_failed' => 'Le paiement n’a pas abouti',
            'order_delivered' => 'Forfait livré',
            'order_number' => 'Référence de commande',
            'order_note' => 'La livraison commence après confirmation du paiement.',
            'network_error' => 'Le paiement est momentanément indisponible. Réessayez plus tard.',
            'phone_error' => 'Vérifiez le numéro gabonais saisi.',
            'language' => 'Langue',
        ],
        'en' => [
            'tagline' => 'MOBILE INTERNET, MADE SIMPLE',
            'hero_title' => 'Choose your network',
            'hero_text' => 'Find the data plan that works for you.',
            'choose_operator' => 'Choose your network',
            'see_plans' => 'View plans',
            'secure_payment' => 'Secure payment',
            'fast_delivery' => 'Fast delivery',
            'no_account' => 'No customer account',
            'plans_title' => 'Available data plans',
            'all_plans' => 'All plans',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'valid_for' => 'Valid for',
            'buy' => 'Buy',
            'popular' => 'Popular',
            'how_title' => 'How it works',
            'step1' => 'Choose your network and data plan.',
            'step2' => 'Enter the number to top up.',
            'step3' => 'Pay securely with Mobile Money.',
            'offers_title' => 'Our data plans',
            'footer_rights' => 'All rights reserved.',
            'purchase_title' => 'Complete your purchase',
            'recipient_phone' => 'Number to top up',
            'payment_method' => 'Payment method',
            'payment_phone' => 'Mobile Money number',
            'select_method' => 'Choose a payment method',
            'payment_unavailable' => 'No payment method is available right now.',
            'confirm_buy' => 'Continue to payment',
            'cancel' => 'Cancel',
            'support' => 'Contact support',
            'order_waiting' => 'Waiting for payment confirmation',
            'order_paid' => 'Payment confirmed',
            'order_failed' => 'Payment was not completed',
            'order_delivered' => 'Data plan delivered',
            'order_number' => 'Order reference',
            'order_note' => 'Delivery starts after payment confirmation.',
            'network_error' => 'Payment is temporarily unavailable. Please try again later.',
            'phone_error' => 'Check the Gabonese phone number you entered.',
            'language' => 'Language',
        ],
    ];
    $language = lang();
    return $translations[$language][$key] ?? $translations['fr'][$key] ?? $key;
}

function format_price(int|string $price): string
{
    return number_format((int) $price, 0, ',', ' ') . ' F';
}

function localized_package_name(string $name): string
{
    if (lang() !== 'en') {
        return $name;
    }
    return str_replace(['Illimitées', 'Illimité', 'Go', 'jours', 'jour'], ['Unlimited', 'Unlimited', 'GB', 'days', 'day'], $name);
}

function operator_label(string $operator): string
{
    return $operator === 'airtel' ? 'Airtel' : 'Moov';
}

function operator_color(string $operator): string
{
    return $operator === 'airtel' ? 'airtel' : 'moov';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    $known = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sent) || !is_string($known) || $known === '' || !hash_equals($known, $sent)) {
        http_response_code(419);
        exit('Session expirée. Rechargez la page et réessayez.');
    }
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Méthode non autorisée.');
    }
}

function is_admin(): bool
{
    return isset($_SESSION['admin_id']) && is_numeric($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!is_admin()) {
        header('Location: login.php');
        exit;
    }
}

function admin_nav(string $active): array
{
    return [
        'dashboard' => ['label' => lang() === 'fr' ? 'Vue d’ensemble' : 'Overview', 'href' => 'index.php', 'icon' => 'grid'],
        'packages' => ['label' => lang() === 'fr' ? 'Forfaits' : 'Data plans', 'href' => 'forfaits.php', 'icon' => 'sim'],
        'orders' => ['label' => lang() === 'fr' ? 'Commandes' : 'Orders', 'href' => 'commandes.php', 'icon' => 'receipt'],
        'settings' => ['label' => lang() === 'fr' ? 'Paramètres' : 'Settings', 'href' => 'parametres.php', 'icon' => 'settings'],
    ];
}

function icon(string $name, string $class = ''): string
{
    $icons = [
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'headset' => '<path d="M3 13v-1a9 9 0 0 1 18 0v1"/><path d="M5 13h2v6H5a2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2Zm14 0h-2v6h2a2 2 0 0 0 2-2v-2a2 2 0 0 0-2-2Zm0 6a4 4 0 0 1-4 3h-2"/>',
        'shield' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/>',
        'truck' => '<path d="M10 17h4V5H2v12h3"/><path d="M14 9h4l4 4v4h-3"/><circle cx="7.5" cy="17.5" r="2.5"/><circle cx="16.5" cy="17.5" r="2.5"/>',
        'user' => '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="8" r="4"/>',
        'grid' => '<rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="5" rx="2"/><rect x="13" y="10" width="8" height="11" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/>',
        'sim' => '<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M9 8h6M8 12h8M9 16h6"/>',
        'receipt' => '<path d="M4 2v20l4-2 4 2 4-2 4 2V2l-4 2-4-2-4 2-4-2Z"/><path d="M8 9h8M8 13h6"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.5 2.6-1.7-.7-.2.1a8 8 0 0 1-1.7 1l-.2 1.8h-3l-.3-1.8a8 8 0 0 1-1.8-1l-1.7.7-1.5-2.6 1.4-1.1A8 8 0 0 1 7 13H5v-3h2a8 8 0 0 1 .7-1.8L8.2 7l1.5-2.6 1.7.7a8 8 0 0 1 1.8-1l.3-1.8h3l.2 1.8a8 8 0 0 1 1.7 1l1.7-.7L21.6 7l-1.4 1.1A8 8 0 0 1 21 10h2v3h-2a8 8 0 0 1-1.6 2Z"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'edit' => '<path d="m15 5 4 4M4 20l4-.8L19 8a2.1 2.1 0 0 0-3-3L5 16l-1 4Z"/>',
        'trash' => '<path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6m4 4v6m6-6v6"/>',
        'filter' => '<path d="M4 6h16M7 12h10m-7 6h4"/>',
        'close' => '<path d="m18 6-12 12M6 6l12 12"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/>',
    ];
    $path = $icons[$name] ?? $icons['check'];
    return '<svg class="icon ' . h($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($message) ? $message : null;
}

