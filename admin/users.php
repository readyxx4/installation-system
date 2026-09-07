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

$action = $_GET['action'] ?? '';
$search = trim($_GET['q'] ?? '');

if ($action === 'delete') {
    $delete_id = trim($_GET['id'] ?? '');

    if ($delete_id === '') {
        redirect_to(app_system_url('admin/users.php?status=error'));
    }

    if ($delete_id === ($_SESSION['user_id'] ?? '')) {
        redirect_to(app_system_url('admin/users.php?status=error'));
    }

    try {
        $check_user = $conn->prepare("
            SELECT user_id, user_role
            FROM `user`
            WHERE user_id = ?
            LIMIT 1
        ");
        $check_user->bind_param('s', $delete_id);
        $check_user->execute();
        $user_result = $check_user->get_result();

        if ($user_result->num_rows !== 1) {
            redirect_to(app_system_url('admin/users.php?status=error'));
        }

        $delete_user = $user_result->fetch_assoc();

        if ((int) $delete_user['user_role'] === 1 && user_has_assignment_assign_by($conn, $delete_id)) {
            redirect_to(app_system_url('admin/users.php?status=manager_assigned'));
        }

        if ((int) $delete_user['user_role'] === 2 && user_has_setup_sale_id($conn, $delete_id)) {
            redirect_to(app_system_url('admin/users.php?status=user_linked'));
        }

        $stmt = $conn->prepare("
            DELETE FROM `user`
            WHERE user_id = ?
        ");
        $stmt->bind_param('s', $delete_id);
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
        SELECT user_id, user_name, user_phone, user_email, user_role, user_address{$created_select}
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
        SELECT user_id, user_name, user_phone, user_email, user_role, user_address{$created_select}
        FROM `user`
        WHERE user_role IN (1, 2, 3)
        ORDER BY user_id DESC
    ");
}

layout_header('จัดการข้อมูลพนักงาน', 'users');
?>

<?= flash_message() ?>

<div class="admin-user-split-layout">
        <div class="panel admin-user-list-pane">
    <div class="panel-title">รายการข้อมูลพนักงานทั้งหมด</div>

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
                    <th>ที่อยู่</th>
                    <th style="width:160px;">จัดการ</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($users->num_rows === 0): ?>
                    <tr>
                        <td colspan="7" class="empty-state">ไม่พบข้อมูลพนักงาน</td>
                    </tr>
                <?php endif; ?>

                <?php while ($row = $users->fetch_assoc()): ?>
                    <?php
                    $edit_url = app_system_url('admin/user_edit.php?id=' . urlencode($row['user_id']));
                    $address = (string) $row['user_address'];
                    $role_name = user_role_name($row['user_role']);
                    $role_badge = user_role_badge($row['user_role']);
                    $created_display = format_user_created_date($row['user_created_at'] ?? null);
                    $delete_block_reason = '';

                    if ((int) $row['user_role'] === 1 && user_has_assignment_assign_by($conn, $row['user_id'])) {
                        $delete_block_reason = 'ไม่สามารถลบได้ เนื่องจากมีประวัติการมอบหมายงาน';
                    } elseif ((int) $row['user_role'] === 2 && user_has_setup_sale_id($conn, $row['user_id'])) {
                        $delete_block_reason = 'ไม่สามารถลบได้ เนื่องจากมีประวัติการสร้างใบงานติดตั้ง';
                    }

                    $is_current_user = $row['user_id'] === ($_SESSION['user_id'] ?? '');
                    $can_delete_user = !$is_current_user && $delete_block_reason === '';
                    ?>
                    <tr class="user-detail-row"
                        tabindex="0"
                        data-user-id="<?= h($row['user_id']) ?>"
                        data-user-name="<?= h($row['user_name'] ?: '-') ?>"
                        data-user-phone="<?= h($row['user_phone'] ?: '-') ?>"
                        data-user-email="<?= h($row['user_email'] ?: '-') ?>"
                        data-user-role="<?= h($role_name) ?>"
                        data-user-role-badge="<?= h($role_badge) ?>"
                        data-user-address="<?= h($address !== '' ? $address : '-') ?>"
                        data-user-created="<?= h($created_display) ?>"
                        data-user-edit-url="<?= h($edit_url) ?>">
                        <td><?= h($row['user_id']) ?></td>
                        <td>
                            <button type="button" class="user-row-name-trigger">
                                <?= h($row['user_name'] ?: '-') ?>
                            </button>
                        </td>
                        <td><?= h($row['user_phone']) ?></td>
                        <td><?= h($row['user_email']) ?></td>
                        <td>
                            <span class="badge <?= h($role_badge) ?>">
                                <?= h($role_name) ?>
                            </span>
                        </td>
                        <td class="address-cell admin-address-cell">
                            <?php
                            $is_long = mb_strlen($address, 'UTF-8') > 25;

                            $short_address = mb_strlen($address, 'UTF-8') > 25
                                ? mb_substr($address, 0, 25, 'UTF-8') . '...'
                                : $address;

                            ?>

                            <?php if ($is_long): ?>
                                <span class="address-short"><?= h($short_address) ?></span>
                                <span class="address-full admin-address-full" style="display:none;"><?= nl2br(h($address)) ?></span>
                                <button type="button" class="text-more-btn" data-toggle-address>ดูเพิ่มเติม</button>
                            <?php else: ?>
                                <?= h($address) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn btn-edit"
                               href="<?= h($edit_url) ?>">
                                <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 20h9"></path>
                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                                </svg>
                                <span>แก้ไข</span>
                            </a>

                            <?php if (!$is_current_user): ?>
                                <?php if ($can_delete_user): ?>
                                <a class="btn btn-delete"
                                   href="<?= h(app_system_url('admin/users.php?action=delete&id=' . urlencode($row['user_id']))) ?>"
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
                                <span class="btn btn-delete disabled-link"
                                      aria-disabled="true"
                                      title="<?= h($delete_block_reason) ?>">
                                    <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M3 6h18"></path>
                                        <path d="M8 6V4h8v2"></path>
                                        <path d="M19 6l-1 14H6L5 6"></path>
                                        <path d="M10 11v6"></path>
                                        <path d="M14 11v6"></path>
                                    </svg>
                                    <span>ลบ</span>
                                </span>
                                <?php endif; ?>
                            <?php endif; ?>
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
                    <div class="admin-user-detail-item">
                        <span class="admin-user-detail-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>
                        <span>สร้างบัญชีเมื่อ</span>
                        <strong data-detail-created>-</strong>
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

<script src="<?= h(app_asset_url('admin/assets/js/toggle_address.js')) ?>?v=<?= h(asset_version('admin/assets/js/toggle_address.js')) ?>"></script>
<script src="<?= h(app_asset_url('admin/assets/js/confirm_delete.js')) ?>?v=<?= h(asset_version('admin/assets/js/confirm_delete.js')) ?>"></script>
<script src="<?= h(app_asset_url('admin/assets/js/user_detail_panel.js')) ?>?v=<?= h(asset_version('admin/assets/js/user_detail_panel.js')) ?>"></script>

<?php layout_footer(); ?>
