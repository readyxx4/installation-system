<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

function clear_authenticated_user_session(): void
{
    foreach (['logged_in', 'login_type', 'user_id', 'user_name', 'user_email', 'user_role'] as $key) {
        unset($_SESSION[$key]);
    }
}

function require_login($allowed_roles = null): void
{
    if (empty($_SESSION['logged_in']) || !isset($_SESSION['user_role'])) {
        redirect_to(app_public_url('login.html?error=login'));
    }

    $currentRole = role_key($_SESSION['user_role']);

    if ($currentRole !== 'technician') {
        global $conn;

        $userId = trim((string) ($_SESSION['user_id'] ?? ''));
        if ($userId === '') {
            clear_authenticated_user_session();
            redirect_to(app_public_url('login.html?error=login'));
        }

        $stmt = $conn->prepare("
            SELECT user_status
            FROM `user`
            WHERE user_id = ?
              AND user_role IN (1, 2, 3)
            LIMIT 1
        ");

        if (!$stmt) {
            clear_authenticated_user_session();
            redirect_to(app_public_url('login.html?error=server'));
        }

        $stmt->bind_param('s', $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            clear_authenticated_user_session();
            redirect_to(app_public_url('login.html?error=login'));
        }

        $account = $result->fetch_assoc();
        if ((int) ($account['user_status'] ?? 0) !== 1) {
            clear_authenticated_user_session();
            redirect_to(app_public_url('login.html?error=suspended'));
        }
    }

    if ($allowed_roles !== null) {
        $allowed_roles = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];

        $allowedRoles = array_map('role_key', $allowed_roles);

        if (!in_array($currentRole, $allowedRoles, true)) {
            redirect_to(app_public_url('login.html?error=denied'));
        }
    }
}
