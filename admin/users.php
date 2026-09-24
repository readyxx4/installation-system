<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

function table_exists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare("
        SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $table);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function column_exists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function prepare_assignment_assign_by_column(mysqli $conn): void
{
    if (table_exists($conn, 'assignment') && !column_exists($conn, 'assignment', 'assign_by')) {
        $conn->query("ALTER TABLE assignment ADD COLUMN assign_by CHAR(13) NULL AFTER customer_id");
    }
}

prepare_assignment_assign_by_column($conn);

function user_role_name($role): string
{
    return match ((string) $role) {
        '1' => 'หัวหน้าช่าง',
        '2' => 'พนักงานขาย',
        '3' => 'ผู้ดูแลระบบ',
        default => 'ไม่ทราบสิทธิ์',
    };
}

function user_role_badge($role): string
{
    return match ((string) $role) {
        '0' => 'blue',
        '1' => 'green',
        '2' => 'orange',
        '3' => 'red',
        default => 'blue',
    };
}

function user_created_column(mysqli $conn): ?string
{
    foreach (['created_at', 'created_date', 'registered_at'] as $column) {
        if (column_exists($conn, 'user', $column)) {
            return $column;
        }
    }

    return null;
}

function format_user_created_date($value): string
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime((string) $value);

    if ($timestamp === false) {
        return '-';
    }

    $months = [
        1 => 'ม.ค.',
        2 => 'ก.พ.',
        3 => 'มี.ค.',
        4 => 'เม.ย.',
        5 => 'พ.ค.',
        6 => 'มิ.ย.',
        7 => 'ก.ค.',
        8 => 'ส.ค.',
        9 => 'ก.ย.',
        10 => 'ต.ค.',
        11 => 'พ.ย.',
        12 => 'ธ.ค.',
    ];

    $day = (int) date('j', $timestamp);
    $month = $months[(int) date('n', $timestamp)] ?? date('m', $timestamp);
    $year = (int) date('Y', $timestamp) + 543;

    return "{$day} {$month} {$year}";
}

function user_has_assignment_assign_by(mysqli $conn, string $user_id): bool
{
    if (!table_exists($conn, 'assignment') || !column_exists($conn, 'assignment', 'assign_by')) {
        return false;
    }

    $stmt = $conn->prepare("
        SELECT assign_id
        FROM assignment
        WHERE assign_by = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $user_id);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function user_has_setup_sale_id(mysqli $conn, string $user_id): bool
{
    if (!table_exists($conn, 'setup') || !column_exists($conn, 'setup', 'sale_id')) {
        return false;
    }

    $stmt = $conn->prepare("
        SELECT setup_id
        FROM setup
        WHERE sale_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $user_id);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function user_has_historical_relation(mysqli $conn, string $user_id): bool
{
    return user_has_setup_sale_id($conn, $user_id)
        || user_has_assignment_assign_by($conn, $user_id);
}

function active_admin_count_excluding(mysqli $conn, string $user_id): int
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM `user`
        WHERE user_role = 3
          AND user_status = 1
          AND user_id <> ?
    ");
    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return (int) ($row['total'] ?? 0);
}

$action = $_GET['action'] ?? '';
$search = trim($_GET['q'] ?? '');

if (in_array($action, ['suspend', 'restore', 'delete'], true)) {
    $target_id = trim($_GET['id'] ?? '');

    if ($target_id === '') {
        redirect_to(app_system_url('admin/users.php?status=error'));
    }

    try {
        $check_user = $conn->prepare("
            SELECT user_id, user_name, user_role, user_status
            FROM `user`
            WHERE user_id = ?
            LIMIT 1
        ");
        $check_user->bind_param('s', $target_id);
        $check_user->execute();
        $user_result = $check_user->get_result();

        if ($user_result->num_rows !== 1) {
            redirect_to(app_system_url('admin/users.php?status=error'));
        }

        $target_user = $user_result->fetch_assoc();

        if (in_array($action, ['suspend', 'delete'], true) && $target_id === ($_SESSION['user_id'] ?? '')) {
            redirect_to(app_system_url('admin/users.php?status=user_self_protected'));
        }

        if (
            in_array($action, ['suspend', 'delete'], true)
            && (int) $target_user['user_role'] === 3
            && (int) $target_user['user_status'] === 1
            && active_admin_count_excluding($conn, $target_id) < 1
        ) {
            redirect_to(app_system_url('admin/users.php?status=user_last_admin'));
        }

        if ($action === 'suspend') {
            $status = 0;
            $stmt = $conn->prepare("
                UPDATE `user`
                SET user_status = ?
                WHERE user_id = ?
            ");
            $stmt->bind_param('is', $status, $target_id);
            $stmt->execute();
            redirect_to(app_system_url('admin/users.php?status=user_suspended'));
        }

        if ($action === 'restore') {
            $status = 1;
            $stmt = $conn->prepare("
                UPDATE `user`
                SET user_status = ?
                WHERE user_id = ?
            ");
            $stmt->bind_param('is', $status, $target_id);
            $stmt->execute();
            redirect_to(app_system_url('admin/users.php?status=user_restored'));
        }

        if (user_has_historical_relation($conn, $target_id)) {
            redirect_to(app_system_url('admin/users.php?status=user_historical'));
        }

        $stmt = $conn->prepare("
            DELETE FROM `user`
            WHERE user_id = ?
        ");
        $stmt->bind_param('s', $target_id);
        $stmt->execute();

        redirect_to(app_system_url('admin/users.php?status=deleted'));
    } catch (Throwable $e) {
        redirect_to(app_system_url('admin/users.php?status=error'));
    }
}

$created_column = user_created_column($conn);
$created_select = $created_column !== null ? ", `{$created_column}` AS user_created_at" : ", NULL AS user_created_at";

if ($search !== '') {
    $like = '%' . $search . '%';

    $stmt = $conn->prepare("
        SELECT user_id, user_name, user_phone, user_email, user_role, user_status, user_address{$created_select}
        FROM `user`
        WHERE user_role IN (1, 2, 3)
          AND (
              user_id LIKE ?
           OR user_name LIKE ?
           OR user_phone LIKE ?
           OR user_email LIKE ?
           OR user_address LIKE ?
          )
        ORDER BY user_id DESC
    ");

    $stmt->bind_param('sssss', $like, $like, $like, $like, $like);
    $stmt->execute();
    $users = $stmt->get_result();
} else {
    $users = $conn->query("
        SELECT user_id, user_name, user_phone
            , user_email, user_role, user_status, user_address{$created_select}
        FROM `user`
        WHERE user_role IN (1, 2, 3)
        ORDER BY user_id DESC
    ");
}

layout_header('จัดการข้อมูลพนักงาน', 'users', 'จัดการข้อมูลและสิทธิ์ของพนักงานในระบบ');
?>

<link
    rel="stylesheet"
    href="<?= h(app_asset_url('admin/assets/css/users.css')) ?>?v=<?= h(asset_version('admin/assets/css/users.css')) ?>"
>

<?= flash_message() ?>

<div class="admin-user-split-layout admin-users-page">
        <div class="panel admin-user-list-pane">
    <div class="admin-users-page-head">
        <div>
            <h1>รายการพนักงาน</h1>
        </div>
    </div>

    <form class="toolbar user-toolbar" method="GET" action="<?= h(app_system_url('admin/users.php')) ?>">
        <input type="text" name="q" placeholder="ค้นหารหัส ชื่อ-นามสกุล เบอร์โทร อีเมล หรือที่อยู่" value="<?= h($search) ?>">

        <button class="btn btn-search" type="submit">
            <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="11" cy="11" r="7"></circle>
                <path d="M20 20l-3.5-3.5"></path>
            </svg>
            <span>ค้นหา</span>
        </button>

        <a class="btn btn-reset" href="<?= h(app_system_url('admin/users.php')) ?>">
            <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 12a9 9 0 1 0 3-6.7"></path>
                <path d="M3 4v6h6"></path>
            </svg>
            <span>ล้างค้นหา</span>
        </a>

        <a class="btn btn-add user-add-in-toolbar" href="<?= h(app_system_url('admin/user_add.php')) ?>">
            <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 5v14"></path>
                <path d="M5 12h14"></path>
            </svg>
            <span>เพิ่มพนักงานใหม่</span>
        </a>
    </form>

    <div class="table-wrap">
            <table class="data-table admin-users-table">
            <thead>
                <tr>
                    <th>รหัสพนักงาน</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>เบอร์โทร</th>
                    <th>อีเมล</th>
                    <th>สิทธิ์</th>
                    <th style="width:200px;">จัดการ</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($users->num_rows === 0): ?>
                    <tr>
                        <td colspan="6" class="empty-state">ไม่พบข้อมูลพนักงาน</td>
                    </tr>
                <?php endif; ?>

                <?php while ($row = $users->fetch_assoc()): ?>
                    <?php
                    $edit_url = app_system_url('admin/user_edit.php?id=' . urlencode($row['user_id']));
                    $address = (string) $row['user_address'];
                    $role_name = user_role_name($row['user_role']);
                    $role_badge = user_role_badge($row['user_role']);
                    $is_active = (int) ($row['user_status'] ?? 0) === 1;
                    $is_current_user = $row['user_id'] === ($_SESSION['user_id'] ?? '');
                    $has_historical_relation = user_has_historical_relation($conn, $row['user_id']);
                    $is_last_active_admin = $is_active
                        && (int) $row['user_role'] === 3
                        && active_admin_count_excluding($conn, $row['user_id']) < 1;
                    $can_delete_user = !$is_current_user && !$has_historical_relation && !$is_last_active_admin;
                    $delete_disabled_reason = $is_current_user
                        ? 'ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่'
                        : ($has_historical_relation
                            ? 'ไม่สามารถลบได้ เนื่องจากมีประวัติการทำงาน'
                            : 'ไม่สามารถลบผู้ดูแลระบบที่ใช้งานอยู่คนสุดท้าย');
                    ?>
                    <tr class="user-detail-row<?= $is_active ? '' : ' is-suspended' ?>"
                        tabindex="0"
                        data-user-id="<?= h($row['user_id']) ?>"
                        data-user-name="<?= h($row['user_name'] ?: '-') ?>"
                        data-user-phone="<?= h($row['user_phone'] ?: '-') ?>"
                        data-user-email="<?= h($row['user_email'] ?: '-') ?>"
                        data-user-role="<?= h($role_name) ?>"
                        data-user-role-badge="<?= h($role_badge) ?>"
                        data-user-address="<?= h($address !== '' ? $address : '-') ?>"
                        data-user-edit-url="<?= h($edit_url) ?>">
                        <td><?= h($row['user_id']) ?></td>
                        <td>
                            <button type="button" class="user-row-name-trigger">
                                <?= h($row['user_name'] ?: '-') ?>
                            </button>
                        </td>
                        <td><?= h($row['user_phone']) ?></td>
                        <td><?= h($row['user_email']) ?></td>
                        <td class="role-cell">
                            <span class="badge <?= h($role_badge) ?>">
                                <?= h($role_name) ?>
                            </span>
                        </td>
                        <td>
                            <div class="user-actions">
                                <a class="btn btn-edit"
                                   href="<?= h($edit_url) ?>">
                                <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 20h9"></path>
                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                                </svg>
                                <span>แก้ไข</span>
                                </a>

                            <?php if (!$is_current_user): ?>
                                <?php if ($is_active): ?>
                                    <?php if ($is_last_active_admin): ?>
                                        <span class="btn btn-account btn-suspend disabled-link"
                                              aria-disabled="true"
                                              title="ไม่สามารถระงับบัญชีที่กำลังใช้งานอยู่ได้ เพราะต้องมีผู้ดูแลระบบที่ใช้งานได้อย่างน้อย 1 บัญชี">
                                            <i class="fa-solid fa-ban action-icon" aria-hidden="true"></i>
                                            <span>ระงับ</span>
                                        </span>
                                    <?php else: ?>
                                        <a class="btn btn-account btn-suspend"
                                           href="<?= h(app_system_url('admin/users.php?action=suspend&id=' . urlencode($row['user_id']))) ?>"
                                           title="ระงับบัญชี"
                                           data-confirm-delete="ต้องการระงับบัญชีของ <?= h($row['user_name'] ?: $row['user_id']) ?> หรือไม่? ผู้ใช้นี้จะไม่สามารถเข้าสู่ระบบได้ แต่ข้อมูลและประวัติงานจะยังคงอยู่">
                                            <i class="fa-solid fa-ban action-icon" aria-hidden="true"></i>
                                            <span>ระงับ</span>
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a class="btn btn-account btn-restore"
                                       href="<?= h(app_system_url('admin/users.php?action=restore&id=' . urlencode($row['user_id']))) ?>"
                                       title="คืนสถานะ"
                                       data-confirm-delete="ต้องการคืนสถานะบัญชีของ <?= h($row['user_name'] ?: $row['user_id']) ?> หรือไม่? ผู้ใช้นี้จะสามารถเข้าสู่ระบบได้อีกครั้ง">
                                        <i class="fa-solid fa-rotate-left action-icon" aria-hidden="true"></i>
                                        <span>คืนสถานะ</span>
                                    </a>
                                <?php endif; ?>

                            <?php endif; ?>

                            <?php if ($can_delete_user): ?>
                                <a class="btn btn-delete"
                                   href="<?= h(app_system_url('admin/users.php?action=delete&id=' . urlencode($row['user_id']))) ?>"
                                   title="ลบบัญชี"
                                   aria-label="ลบบัญชี"
                                   data-confirm-delete="ยืนยันการลบข้อมูลพนักงานนี้หรือไม่?">
                                    <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M3 6h18"></path>
                                        <path d="M8 6V4h8v2"></path>
                                        <path d="M19 6l-1 14H6L5 6"></path>
                                        <path d="M10 11v6"></path>
                                        <path d="M14 11v6"></path>
                                    </svg>
                                    <span>ลบ</span>
                                </a>
                            <?php else: ?>
                                <button type="button"
                                        class="btn btn-delete btn-delete-disabled"
                                        disabled
                                        title="<?= h($delete_disabled_reason) ?>"
                                        aria-label="<?= h($delete_disabled_reason) ?>">
                                    <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M3 6h18"></path>
                                        <path d="M8 6V4h8v2"></path>
                                        <path d="M19 6l-1 14H6L5 6"></path>
                                        <path d="M10 11v6"></path>
                                        <path d="M14 11v6"></path>
                                    </svg>
                                    <span>ลบ</span>
                                </button>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
        </div>

        <aside class="admin-user-detail-panel" aria-live="polite" hidden>
            <button type="button" class="admin-user-detail-close" data-detail-close aria-label="ปิดข้อมูลพนักงาน">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>

            <div class="admin-user-detail-content" data-user-detail-content>
                <h2 class="admin-user-detail-title">ข้อมูลพนักงาน</h2>
                <div class="admin-user-detail-head">
                    <div class="admin-user-detail-avatar">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <div>
                        <span data-detail-id>-</span>
                        <h2 data-detail-name>-</h2>
                        <p data-detail-role>-</p>
                    </div>
                </div>

                <div class="admin-user-detail-list">
                    <div class="admin-user-detail-item">
                        <span class="admin-user-detail-icon"><i class="fa-solid fa-id-card" aria-hidden="true"></i></span>
                        <span>รหัสพนักงาน</span>
                        <strong data-detail-code>-</strong>
                    </div>
                    <div class="admin-user-detail-item">
                        <span class="admin-user-detail-icon"><i class="fa-solid fa-phone" aria-hidden="true"></i></span>
                        <span>เบอร์โทรศัพท์</span>
                        <strong data-detail-phone>-</strong>
                    </div>
                    <div class="admin-user-detail-item">
                        <span class="admin-user-detail-icon"><i class="fa-solid fa-envelope" aria-hidden="true"></i></span>
                        <span>อีเมล</span>
                        <strong data-detail-email>-</strong>
                    </div>
                    <div class="admin-user-detail-item admin-user-detail-address">
                        <span class="admin-user-detail-icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></span>
                        <span>ที่อยู่</span>
                        <strong data-detail-address>-</strong>
                    </div>
                </div>

                <div class="admin-user-detail-actions">
                    <a class="btn btn-edit" href="#" data-detail-edit>
                        <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 20h9"></path>
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                        </svg>
                        <span>แก้ไขข้อมูล</span>
                    </a>
                </div>
            </div>
        </aside>
    </div>

<script src="<?= h(app_asset_url('admin/assets/js/confirm_delete.js')) ?>?v=<?= h(asset_version('admin/assets/js/confirm_delete.js')) ?>"></script>
<script src="<?= h(app_asset_url('admin/assets/js/user_detail_panel.js')) ?>?v=<?= h(asset_version('admin/assets/js/user_detail_panel.js')) ?>"></script>

<?php layout_footer(); ?>
