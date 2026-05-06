<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

/**
 * Vérifie si l'utilisateur est connecté
 */
if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool {
        return !empty($_SESSION['user_id']);
    }
}

/**
 * Récupère les informations de l'utilisateur connecté
 * (Cette fonction manquait dans le message précédent)
 */
if (!function_exists('current_user')) {
    function current_user(): ?array {
        if (!is_logged_in()) return null;
        return [
            'id'          => (int)$_SESSION['user_id'],
            'nom_complet' => $_SESSION['nom_complet'] ?? '',
            'email'       => $_SESSION['email']       ?? '',
            'role'        => $_SESSION['role']        ?? 'user',
        ];
    }
}

/**
 * Redirige si l'utilisateur n'est pas admin
 */
if (!function_exists('require_admin')) {
    function require_admin(string $redirect = '../index.php'): void {
        if (!is_logged_in()) {
            header('Location: ../login.php');
            exit;
        }
        
        $allowed_roles = ['admin', 'superadmin', 'admin_activites', 'admin_espaces'];
        $current_role = $_SESSION['role'] ?? 'user';

        if (!in_array($current_role, $allowed_roles)) {
            header('Location: ' . $redirect);
            exit;
        }
    }
}

// --- PROTECTION CONTRE LES FAILLES (XSS & CSRF) ---

if (!function_exists('e')) {
    function e(?string $s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_check')) {
    function csrf_check(?string $token): bool {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}