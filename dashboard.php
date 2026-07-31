<?php
require_once __DIR__ . '/check_login.php';

require_login();

redirect_to(role_dashboard($_SESSION['user_role']));