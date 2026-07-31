<?php
require_once __DIR__ . '/helpers.php';

if (!empty($_SESSION['logged_in']) && isset($_SESSION['user_role'])) {
    redirect_to(role_dashboard($_SESSION['user_role']));
}

redirect_to(app_public_url('login.html'));