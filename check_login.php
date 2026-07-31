<?php
require_once __DIR__ . '/helpers.php';

function require_login($allowed_roles = null): void
{
    if (empty($_SESSION['logged_in']) || !isset($_SESSION['user_role'])) {
        redirect_to(app_public_url('login.html?error=login'));
    }

    if ($allowed_roles !== null) {
        $allowed_roles = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];

        $currentRole = role_key($_SESSION['user_role']);
        $allowedRoles = array_map('role_key', $allowed_roles);

        if (!in_array($currentRole, $allowedRoles, true)) {
            redirect_to(app_public_url('login.html?error=denied'));
        }
    }
}