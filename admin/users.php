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
        $conn->query("ALTER TABLE assignment ADD COLUMN assign_by CHAR(13) NULL AFTER user_id");
    }
}

prepare_assignment_assign_by_column($conn);

function user_role_name($role): string
{
    return match ((string) $role) {
        '0' => 'ลูกค้า',
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

function wrap_text_every_chars(string $text, int $limit = 15): string
{
    $text = trim($text);
    $length = mb_strlen($text, 'UTF-8');
    $lines = [];

    for ($i = 0; $i < $length; $i += $limit) {
        $lines[] = mb_substr($text, $i, $limit, 'UTF-8');
    }

    return implode("\n", $lines);
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

        if ((int) $delete_user['user_role'] === 1 && table_exists($conn, 'assignment') && column_exists($conn, 'assignment', 'assign_by')) {
            $check_assignment_by = $conn->prepare("
                SELECT assign_id
                FROM assignment
                WHERE assign_by = ?
                LIMIT 1
            ");
            $check_assignment_by->bind_param('s', $delete_id);
            $check_assignment_by->execute();

            if ($check_assignment_by->get_result()->num_rows > 0) {
                redirect_to(app_system_url('admin/users.php?status=manager_assigned'));
            }
        }

        if (table_exists($conn, 'setup')) {
            $check_setup = $conn->prepare("
                SELECT setup_id
                FROM setup
                WHERE user_id = ?
                LIMIT 1
            ");
            $check_setup->bind_param('s', $delete_id);
            $check_setup->execute();

            if ($check_setup->get_result()->num_rows > 0) {
                redirect_to(app_system_url('admin/users.php?status=user_linked'));
            }
        }

        if (table_exists($conn, 'assignment')) {
            $check_assignment_user = $conn->prepare("
                SELECT assign_id
                FROM assignment
                WHERE user_id = ?
                LIMIT 1
            ");
            $check_assignment_user->bind_param('s', $delete_id);
            $check_assignment_user->execute();

            if ($check_assignment_user->get_result()->num_rows > 0) {
                redirect_to(app_system_url('admin/users.php?status=user_linked'));
            }
        }

        if (table_exists($conn, 'product_payment')) {
            $check_payment = $conn->prepare("
                SELECT paymentpro_id
                FROM product_payment
                WHERE user_id = ?
                LIMIT 1
            ");
            $check_payment->bind_param('s', $delete_id);
            $check_payment->execute();

            if ($check_payment->get_result()->num_rows > 0) {
                redirect_to(app_system_url('admin/users.php?status=user_linked'));
            }
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

if ($search !== '') {
    $like = '%' . $search . '%';

    $stmt = $conn->prepare("
        SELECT user_id, user_name, user_phone, user_email, user_role, user_address
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
        SELECT user_id, user_name, user_phone, user_email, user_role, user_address
        FROM `user`
        WHERE user_role IN (1, 2, 3)
        ORDER BY user_id DESC
    ");
}

layout_header('จัดการข้อมูลพนักงาน', 'users');
?>

<?= flash_message() ?>

<div class="panel">
    <div class="panel-title">รายการข้อมูลผู้ใช้ทั้งหมด</div>

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
            <span>เพิ่มผู้ใช้ใหม่</span>
        </a>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>รหัสผู้ใช้</th>
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
                        <td colspan="7" class="empty-state">ไม่พบข้อมูลผู้ใช้</td>
                    </tr>
                <?php endif; ?>

                <?php while ($row = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?= h($row['user_id']) ?></td>
                        <td><?= h($row['user_name'] ?: '-') ?></td>
                        <td><?= h($row['user_phone']) ?></td>
                        <td><?= h($row['user_email']) ?></td>
                        <td>
                            <span class="badge <?= h(user_role_badge($row['user_role'])) ?>">
                                <?= h(user_role_name($row['user_role'])) ?>
                            </span>
                        </td>
                        <td class="address-cell">
                            <?php
                            $address = (string) $row['user_address'];
                            $is_long = mb_strlen($address, 'UTF-8') > 25;

                            $short_address = mb_strlen($address, 'UTF-8') > 25
                                ? mb_substr($address, 0, 25, 'UTF-8') . '...'
                                : $address;

                            $wrapped_address = wrap_text_every_chars($address, 15);
                            ?>

                            <?php if ($is_long): ?>
                                <span class="address-short"><?= h($short_address) ?></span>
                                <span class="address-full" style="display:none;"><?= nl2br(h($wrapped_address)) ?></span>
                                <button type="button" class="text-more-btn" data-toggle-address>ดูเพิ่มเติม</button>
                            <?php else: ?>
                                <?= h($address) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn btn-edit"
                               href="<?= h(app_system_url('admin/user_edit.php?id=' . urlencode($row['user_id']))) ?>">
                                <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 20h9"></path>
                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>
                                </svg>
                                <span>แก้ไข</span>
                            </a>

                            <?php if ($row['user_id'] !== ($_SESSION['user_id'] ?? '')): ?>
                                <a class="btn btn-delete"
                                   href="<?= h(app_system_url('admin/users.php?action=delete&id=' . urlencode($row['user_id']))) ?>"
                                   data-confirm-delete="ยืนยันการลบข้อมูลผู้ใช้นี้หรือไม่?">
                                    <svg class="action-icon" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M3 6h18"></path>
                                        <path d="M8 6V4h8v2"></path>
                                        <path d="M19 6l-1 14H6L5 6"></path>
                                        <path d="M10 11v6"></path>
                                        <path d="M14 11v6"></path>
                                    </svg>
                                    <span>ลบ</span>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="<?= h(app_asset_url('admin/assets/js/toggle_address.js')) ?>?v=<?= h(asset_version('admin/assets/js/toggle_address.js')) ?>"></script>
<script src="<?= h(app_asset_url('admin/assets/js/confirm_delete.js')) ?>?v=<?= h(asset_version('admin/assets/js/confirm_delete.js')) ?>"></script>

<?php layout_footer(); ?>
