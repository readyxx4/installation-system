<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('1');

date_default_timezone_set('Asia/Bangkok');

$keyword = trim($_GET['q'] ?? '');
$search = $keyword;

function table_exists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare("\n        SELECT TABLE_NAME\n        FROM INFORMATION_SCHEMA.TABLES\n        WHERE TABLE_SCHEMA = DATABASE()\n          AND TABLE_NAME = ?\n        LIMIT 1\n    ");
    $stmt->bind_param('s', $table);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function column_exists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("\n        SELECT COLUMN_NAME\n        FROM INFORMATION_SCHEMA.COLUMNS\n        WHERE TABLE_SCHEMA = DATABASE()\n          AND TABLE_NAME = ?\n          AND COLUMN_NAME = ?\n        LIMIT 1\n    ");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function prepare_assignment_assign_by(mysqli $conn): void
{
    if (!table_exists($conn, 'assignment') || !column_exists($conn, 'assignment', 'assign_by')) {
        redirect_to(app_system_url('manager/index.php?status=schema_missing'));
    }
}

prepare_assignment_assign_by($conn);

function make_cancel_assign_id(mysqli $conn): string
{
    $prefix = 'ASG-';
    for ($i = 1; $i <= 9999999; $i++) {
        $assign_id = $prefix . str_pad((string) $i, 7, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare("SELECT assign_id FROM assignment WHERE assign_id = ? LIMIT 1");
        $stmt->bind_param('s', $assign_id);
        $stmt->execute();

        if ($stmt->get_result()->num_rows === 0) {
            return $assign_id;
        }
    }

    throw new Exception('ไม่สามารถสร้างรหัสการยกเลิกใบงานได้');
}

function assignment_cancel_allowed(array $row): bool
{
    if (!empty($row['assign_id']) && (string) ($row['assign_status'] ?? '') === '4') {
        return false;
    }

    if (in_array((string) ($row['assign_status'] ?? ''), ['2', '5'], true)) {
        return false;
    }

    return in_array((string) ($row['setup_status'] ?? ''), ['0', '1'], true);
}

function assignment_cancel_disabled_title(array $row): string
{
    if (!empty($row['assign_id']) && (string) ($row['assign_status'] ?? '') === '4') {
        return 'งานนี้ถูกยกเลิกแล้ว';
    }

    if ((string) ($row['setup_status'] ?? '') === '4' || (string) ($row['assign_status'] ?? '') === '5') {
        return 'งานนี้เสร็จสิ้นแล้ว ไม่สามารถยกเลิกได้';
    }

    if (in_array((string) ($row['setup_status'] ?? ''), ['2', '3'], true)
        || (string) ($row['assign_status'] ?? '') === '2') {
        return 'ไม่สามารถยกเลิกงานที่เริ่มดำเนินการแล้ว';
    }

    return 'ไม่สามารถยกเลิกงานนี้ได้';
}


$action = $_GET['action'] ?? '';

if ($action === 'cancel') {
    $setup_id_cancel = trim($_GET['id'] ?? '');

    if ($setup_id_cancel === '') {
        redirect_to(app_system_url('manager/assignment_list.php?status=error'));
    }

    try {
        $setup_cancel_stmt = $conn->prepare("
            SELECT setup_id, customer_id, setup_status
            FROM setup
            WHERE setup_id = ?
            LIMIT 1
        ");
        $setup_cancel_stmt->bind_param('s', $setup_id_cancel);
        $setup_cancel_stmt->execute();
        $setup_cancel = $setup_cancel_stmt->get_result()->fetch_assoc();

        if (!$setup_cancel) {
            redirect_to(app_system_url('manager/assignment_list.php?status=all&cancel=notfound'));
        }

        $assign_stmt = $conn->prepare("\n            SELECT assign_id, tech_id, assign_status\n            FROM assignment\n            WHERE setup_id = ?\n            ORDER BY assign_date DESC, assign_id DESC\n            LIMIT 1\n        ");
        $assign_stmt->bind_param('s', $setup_id_cancel);
        $assign_stmt->execute();
        $assignment = $assign_stmt->get_result()->fetch_assoc();

        $cancel_check = [
            'setup_status' => $setup_cancel['setup_status'] ?? null,
            'assign_id' => $assignment['assign_id'] ?? null,
            'assign_status' => $assignment['assign_status'] ?? null,
        ];

        if (!assignment_cancel_allowed($cancel_check)) {
            redirect_to(app_system_url('manager/assignment_list.php?status=all&cancel=not_allowed'));
        }

        $conn->begin_transaction();

        if ($assignment) {
            $cancel_stmt = $conn->prepare("\n                UPDATE assignment\n                SET assign_status = 4\n                WHERE assign_id = ?\n            ");
            $cancel_stmt->bind_param('s', $assignment['assign_id']);
            $cancel_stmt->execute();
        } else {
            $cancel_assign_id = make_cancel_assign_id($conn);
            $assign_by = $_SESSION['user_id'] ?? null;
            $assign_date = date('Y-m-d H:i:s');
            $cancel_status = 4;
            $customer_id = $setup_cancel['customer_id'] ?? null;

            if ($customer_id === null || $customer_id === '') {
                redirect_to(app_system_url('manager/assignment_list.php?status=all&cancel=error'));
            }

            $cancel_insert_stmt = $conn->prepare("
                INSERT INTO assignment
                    (assign_id, setup_id, tech_id, customer_id, assign_by, assign_date, assign_status)
                VALUES (?, ?, NULL, ?, ?, ?, ?)
            ");
            $cancel_insert_stmt->bind_param(
                'sssssi',
                $cancel_assign_id,
                $setup_id_cancel,
                $customer_id,
                $assign_by,
                $assign_date,
                $cancel_status
            );
            $cancel_insert_stmt->execute();
        }

        $conn->commit();

        redirect_to(app_system_url('manager/assignment_history.php?status=canceled')); 
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // skip rollback error
        }

        redirect_to(app_system_url('manager/assignment_list.php?status=all&cancel=error'));
    }
}

$has_install_date = table_exists($conn, 'assignment') && column_exists($conn, 'assignment', 'assign_install_date');
$has_install_time = table_exists($conn, 'assignment') && column_exists($conn, 'assignment', 'assign_install_time');
$has_install_end_time = table_exists($conn, 'assignment') && column_exists($conn, 'assignment', 'assign_install_end_time');
$assign_install_date_select = $has_install_date ? 'a.assign_install_date' : 'NULL AS assign_install_date';
$assign_install_time_select = $has_install_time ? 'a.assign_install_time' : 'NULL AS assign_install_time';
$assign_install_end_time_select = $has_install_end_time ? 'a.assign_install_end_time' : 'NULL AS assign_install_end_time';

function manager_icon_svg(string $name): string
{
    $icons = [
        'search' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20L16.65 16.65"></path></svg>',
        'reset' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"></path><path d="M3 4v6h6"></path></svg>',
        'eye' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
        'edit' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path></svg>',
        'cancel' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M15 9l-6 6"></path><path d="M9 9l6 6"></path></svg>',
        'close' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 6L6 18"></path><path d="M6 6l12 12"></path></svg>',
        'calendar' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4"></path><path d="M8 2v4"></path><path d="M3 10h18"></path></svg>',
        'user' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>',
        'tool' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5 5L3 18l3 3 6.7-6.7a4 4 0 0 0 5-5l-2.4 2.4-3-3 2.4-2.4z"></path></svg>',
        'box' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 8l-9-5-9 5 9 5 9-5z"></path><path d="M3 8v8l9 5 9-5V8"></path><path d="M12 13v8"></path></svg>',
        'plus' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>',
        'assign' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="4" width="14" height="17" rx="2"></rect><path d="M9 4.5V3h6v1.5"></path><path d="M12 9v7"></path><path d="M8.5 12.5h7"></path></svg>',
    ];

    return $icons[$name] ?? '';
}

function setup_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'ยังไม่ได้มอบหมาย',
        '1' => 'มอบหมายงานแล้ว',
        '2' => 'ช่างรับงานแล้ว',
        '3' => 'กำลังติดตั้ง',
        '4' => 'ติดตั้งเสร็จสิ้น',
        default => 'ไม่ทราบสถานะ',
    };
}

function setup_status_badge($status): string
{
    return match ((string) $status) {
        '0' => 'orange',
        '1' => 'blue',
        '2' => 'cyan',
        '3' => 'purple',
        '4' => 'green',
        default => 'red',
    };
}


function assignment_is_overdue(array $row): bool
{
    if (empty($row['assign_id'])) {
        return false;
    }

    $assign_status = (int) ($row['assign_status'] ?? 0);
    if (!in_array($assign_status, [1, 2], true)) {
        return false;
    }

    $install_date = trim((string) ($row['assign_install_date'] ?? ''));
    $install_start = trim((string) ($row['assign_install_time'] ?? ''));
    $install_end = trim((string) ($row['assign_install_end_time'] ?? ''));

    if ($install_date === '') {
        return false;
    }

    if ($install_end === '' && $install_start !== '') {
        $start_timestamp = strtotime($install_date . ' ' . $install_start);
        if ($start_timestamp !== false) {
            $install_end = date('H:i:s', $start_timestamp + 7200);
        }
    }

    if ($install_end === '') {
        return false;
    }

    $timezone = new DateTimeZone('Asia/Bangkok');
    $deadline = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        $install_date . ' ' . $install_end,
        $timezone
    );

    if (!$deadline) {
        $deadline = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i',
            $install_date . ' ' . substr($install_end, 0, 5),
            $timezone
        );
    }

    return $deadline instanceof DateTimeImmutable
        && $deadline < new DateTimeImmutable('now', $timezone);
}

function display_assignment_status(array $row): string
{
    if (assignment_is_overdue($row)) {
        return 'เกินกำหนด';
    }

    if (!empty($row['assign_id']) && (string) ($row['assign_status'] ?? '') === '4') {
        return 'ยกเลิกแล้ว';
    }

    $status_name = setup_status_name($row['setup_status']);
    if ((string) ($row['setup_status'] ?? '') === '1' && assignment_has_unavailable_active_tech($row)) {
        return $status_name . '*';
    }

    return $status_name;
}

function assignment_has_unavailable_active_tech(array $row): bool
{
    if (empty($row['assign_id']) || empty($row['tech_id'])) {
        return false;
    }

    if ((string) ($row['tech_status'] ?? '0') === '0') {
        return false;
    }

    if ((string) ($row['setup_status'] ?? '') === '4') {
        return false;
    }

    return !in_array((string) ($row['assign_status'] ?? ''), ['4', '5'], true);
}

function display_assignment_badge(array $row): string
{
    if (assignment_is_overdue($row)) {
        return 'red';
    }

    if (!empty($row['assign_id']) && (string) ($row['assign_status'] ?? '') === '4') {
        return 'slate';
    }

    return setup_status_badge($row['setup_status']);
}

function assign_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'ยังไม่มอบหมาย',
        '1' => 'มอบหมายงานแล้ว',
        '2' => 'ช่างรับงาน',
        '3' => 'ช่างปฏิเสธงาน',
        '4' => 'ยกเลิกการมอบหมาย',
        '5' => 'งานเสร็จสิ้น',
        default => '',
    };
}

function thai_date($date): string
{
    if (empty($date)) {
        return '-';
    }

    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '-';
}

function thai_datetime($date): string
{
    if (empty($date)) {
        return '-';
    }

    $ts = strtotime($date);
    return $ts ? date('d/m/Y H:i', $ts) : '-';
}

function thai_time($time): string
{
    if (empty($time)) {
        return '-';
    }

    $ts = strtotime((string) $time);
    return $ts ? date('H:i', $ts) . ' น.' : '-';
}

function money_text($value): string
{
    return number_format((float) $value, 2) . ' บาท';
}

$latest_assignment_join = "\n    LEFT JOIN assignment a\n        ON a.setup_id = s.setup_id\n       AND a.assign_id = (\n            SELECT a2.assign_id\n            FROM assignment a2\n            WHERE a2.setup_id = s.setup_id\n            ORDER BY a2.assign_date DESC, a2.assign_id DESC\n            LIMIT 1\n       )\n";

$base_sql = "\n    SELECT\n        s.setup_id,\n        s.customer_id AS user_id,\n        s.pro_id,\n        s.setup_date,\n        s.setup_location,\n        s.setup_address,\n        s.setup_note,\n        s.setup_status,\n        s.created_at,\n\n        c.customer_name AS user_name,\n        c.customer_phone AS user_phone,\n        c.customer_email AS user_email,\n        c.customer_address AS user_address,\n\n        p.pro_name,\n        p.pro_price_install,\n\n        a.assign_id,\n        a.assign_date,\n        a.assign_status,\n        {$assign_install_date_select},\n        {$assign_install_time_select},\n\n        t.tech_id,\n        t.tech_name,\n        t.tech_fullname,\n        t.tech_phone,\n        t.tech_email,\n        t.tech_status,\n\n        m.user_name AS manager_name,\n\n        COUNT(idt.detail_id) AS item_count,\n        COALESCE(SUM(COALESCE(idt.install_qty, 1) * COALESCE(p2.pro_price_install, 0)), 0) AS install_total\n\n    FROM setup s\n    LEFT JOIN customers c ON s.customer_id = c.customer_id\n    LEFT JOIN product p ON s.pro_id = p.pro_id\n    {$latest_assignment_join}\n    LEFT JOIN technicians t ON a.tech_id = t.tech_id\n    LEFT JOIN `user` m ON a.assign_by = m.user_id\n    LEFT JOIN install_detail idt ON s.setup_id = idt.setup_id\n    LEFT JOIN product p2 ON idt.pro_id = p2.pro_id\n";

$install_date_group_sql = $has_install_date ? "        a.assign_install_date,\n" : "";
$install_time_group_sql = $has_install_time ? "        a.assign_install_time,\n" : "";
$install_end_time_group_sql = $has_install_end_time ? "        a.assign_install_end_time,\n" : "";

$group_sql = "\n    GROUP BY\n        s.setup_id,\n        s.customer_id,\n        s.pro_id,\n        s.setup_date,\n        s.setup_location,\n        s.setup_address,\n        s.setup_note,\n        s.setup_status,\n        s.created_at,\n        c.customer_name,\n        c.customer_phone,\n        c.customer_email,\n        c.customer_address,\n        p.pro_name,\n        p.pro_price_install,\n        a.assign_id,\n        a.assign_date,\n        a.assign_status,\n{$install_date_group_sql}{$install_time_group_sql}{$install_end_time_group_sql}        t.tech_id,\n        t.tech_name,\n        t.tech_fullname,\n        t.tech_phone,\n        t.tech_email,\n        t.tech_status,\n        m.user_name\n";

if ($search !== '') {
    $like = '%' . $search . '%';

    $stmt = $conn->prepare($base_sql . "\n        WHERE s.setup_id LIKE ?\n           OR c.customer_name LIKE ?\n           OR c.customer_phone LIKE ?\n           OR c.customer_email LIKE ?\n           OR p.pro_name LIKE ?\n           OR t.tech_name LIKE ?\n           OR t.tech_fullname LIKE ?\n           OR t.tech_phone LIKE ?\n           OR m.user_name LIKE ?\n    " . $group_sql . "\n        ORDER BY
            CASE
                WHEN a.assign_id IS NULL OR (s.setup_status = 0 AND (a.assign_status IS NULL OR a.assign_status <> 4)) THEN 0
                WHEN a.assign_status = 4 THEN 1
                WHEN t.tech_status = 1 AND a.assign_status IN (1, 2, 3) THEN 2
                ELSE 3
            END ASC,
            s.created_at DESC,
            s.setup_id DESC\n    ");

    $stmt->bind_param(
        'sssssssss',
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like,
        $like
    );
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($base_sql . $group_sql . "\n        ORDER BY
            CASE
                WHEN a.assign_id IS NULL OR (s.setup_status = 0 AND (a.assign_status IS NULL OR a.assign_status <> 4)) THEN 0
                WHEN a.assign_status = 4 THEN 1
                WHEN t.tech_status = 1 AND a.assign_status IN (1, 2, 3) THEN 2
                ELSE 3
            END ASC,
            s.created_at DESC,
            s.setup_id DESC\n    ");
}

$setups = [];
while ($row = $result->fetch_assoc()) {
    $row['customer_display'] = $row['user_name'] ?: '-';
    $row['tech_display'] = $row['tech_fullname'] ?: ($row['tech_name'] ?: '-');
    $row['manager_display'] = $row['manager_name'] ?: '-';
    $row['setup_date_display'] = thai_date($row['setup_date'] ?? null);
    $row['assign_date_display'] = thai_datetime($row['assign_date'] ?? null);
    $row['created_at_display'] = thai_datetime($row['created_at'] ?? null);
    $row['install_date_display'] = thai_date($row['assign_install_date'] ?? null);
    $row['install_time_display'] = thai_time($row['assign_install_time'] ?? null);
    $row['item_count_display'] = (int) ($row['item_count'] ?? 0);
    $row['install_total_display'] = money_text($row['install_total'] ?? 0);
    $row['is_overdue'] = assignment_is_overdue($row);
    $row['status_name'] = display_assignment_status($row);
    $row['status_badge'] = display_assignment_badge($row);
    $row['assign_status_name'] = !empty($row['assign_id']) ? assign_status_name($row['assign_status']) : 'ยังไม่มอบหมาย';

    $setups[] = $row;
}

function assignment_display_priority(array $row): int
{
    if ((string) ($row['setup_status'] ?? '') === '0'
        && (empty($row['assign_id']) || (string) ($row['assign_status'] ?? '') !== '4')) {
        return 0;
    }

    if (!empty($row['is_overdue'])) {
        return 1;
    }

    if (!empty($row['assign_id']) && (string) ($row['assign_status'] ?? '') === '4') {
        return 3;
    }

    return 2;
}

usort($setups, function (array $left, array $right): int {
    $priority = assignment_display_priority($left) <=> assignment_display_priority($right);
    if ($priority !== 0) {
        return $priority;
    }

    $left_time = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
    $right_time = strtotime((string) ($right['created_at'] ?? '')) ?: 0;

    if ($left_time !== $right_time) {
        return $right_time <=> $left_time;
    }

    return strcmp((string) ($right['setup_id'] ?? ''), (string) ($left['setup_id'] ?? ''));
});


$visible_setups = array_values(array_filter($setups, function ($row) {
    return (string) ($row['setup_status'] ?? '') === '0'
        && (empty($row['assign_id']) || (string) ($row['assign_status'] ?? '') !== '4');
}));

$setups_json = json_encode($visible_setups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

layout_header('รายการมอบหมายงาน', 'assignment_list');
?>

<div class="manager-list-page manager-list-detail-page assignment-from-setup-page assignment-list-no-tophead">
    <?= flash_message() ?>

    <div class="assignment-sale-header">
        <div class="admin-dashboard-top">
            <div>
                <h1>รายการมอบหมายงาน</h1>
                <p>ตรวจสอบใบงานที่รอการมอบหมาย และดำเนินการมอบหมายช่างติดตั้ง</p>
            </div>

            <div class="admin-dashboard-actions">
                <div class="admin-date-pill">
                    <i class="fa-regular fa-calendar"></i>
                    <?= h(date('d/m/Y')) ?>
                </div>
            </div>
        </div>
    </div>

    <section class="manager-panel manager-list-panel assignment-from-setup-card">
        <form class="assignment-search-form assignment-toolbar-card" method="GET" action="<?= h(app_system_url('manager/assignment_list.php')) ?>">
            <div class="assignment-search-field">
                <?= manager_icon_svg('search') ?>
                <input
                    type="text"
                    name="q"
                    value="<?= h($keyword) ?>"
                    placeholder="ค้นหารหัสใบงาน ลูกค้า สินค้า ช่าง หรือผู้มอบหมาย"
                >
            </div>

            <div class="assignment-toolbar-buttons">
                <button type="submit" class="btn-search assignment-search-btn">
                    <?= manager_icon_svg('search') ?>
                    ค้นหา
                </button>

                <a class="btn-reset assignment-reset-btn" href="<?= h(app_system_url('manager/assignment_list.php')) ?>">
                    <?= manager_icon_svg('reset') ?>
                    ล้างค้นหา
                </a>
            </div>
        </form>

        <div class="table-wrap manager-table-wrap assignment-table-wrap">
            <table class="data-table manager-table assignment-detail-table setup-source-table">
                <thead>
                    <tr>
                        <th>รหัสใบงาน</th>
                        <th>ลูกค้า</th>
                        <th>จำนวนสินค้า</th>
                        <th>ค่าติดตั้ง</th>
                        <th class="text-center">จัดการ</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($visible_setups) === 0): ?>
                        <tr>
                            <td colspan="5" class="empty-state">ไม่พบข้อมูลใบงานติดตั้ง</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($visible_setups as $row): ?>
                        <tr>
                            <td class="setup-col-id">
                                <strong><?= h($row['setup_id']) ?></strong>
                            </td>

                            <td class="setup-col-customer">
                                <strong><?= h($row['customer_display']) ?></strong>
                            </td>

                            <td class="setup-col-items">
                                <span class="setup-item-count">
                                    <?= h((string) ((int) ($row['item_count'] ?? 0))) ?> รายการ
                                </span>
                            </td>

                            <td class="setup-col-total">
                                <strong>
                                    <?= h(number_format((float) ($row['install_total'] ?? 0), 2)) ?> บาท
                                </strong>
                            </td>

                            <td class="setup-col-actions assignment-row-actions assignment-icon-actions">
                                <a
                                    class="assignment-icon-btn view-slip assign-work"
                                    href="<?= h(app_system_url('manager/assignments.php?setup_id=' . urlencode($row['setup_id']))) ?>"
                                    title="มอบหมายงาน"
                                    aria-label="มอบหมายงาน"
                                >
                                    <?= manager_icon_svg('assign') ?>
                                    <span>มอบหมายงาน</span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>


<link rel="stylesheet" href="<?= h(app_asset_url('manager/assets/css/manager.css')) ?>?v=<?= h(asset_version('manager/assets/css/manager.css')) ?>">
<link rel="stylesheet" href="<?= h(app_asset_url('manager/assets/css/assignment_list.css')) ?>?v=<?= h(asset_version('manager/assets/css/assignment_list.css')) ?>">
<script src="<?= h(app_asset_url('manager/assets/js/assignment_list.js')) ?>?v=<?= h(asset_version('manager/assets/js/assignment_list.js')) ?>"></script>

<?php layout_footer(); ?>
