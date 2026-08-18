<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('1');

date_default_timezone_set('Asia/Bangkok');

$keyword = trim($_GET['q'] ?? '');
$search = $keyword;
$status_filter = trim($_GET['status'] ?? 'all');
$allowed_status_filters = ['all', 'canceled', 'assigned', 'accepted', 'working', 'done'];
if (!in_array($status_filter, $allowed_status_filters, true)) {
    $status_filter = 'all';
}


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

    return setup_status_name($row['setup_status']);
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

$base_sql = "\n    SELECT\n        s.setup_id,\n        s.user_id,\n        s.pro_id,\n        s.setup_date,\n        s.setup_location,\n        s.setup_address,\n        s.setup_note,\n        s.setup_status,\n        s.created_at,\n\n        u.user_name,\n        u.user_phone,\n        u.user_email,\n        u.user_address,\n\n        p.pro_name,\n        p.pro_price_install,\n\n        a.assign_id,\n        a.assign_date,\n        a.assign_status,\n        {$assign_install_date_select},\n        {$assign_install_time_select},\n\n        t.tech_id,\n        t.tech_name,\n        t.tech_fullname,\n        t.tech_phone,\n        t.tech_email,\n        t.tech_status,\n\n        m.user_name AS manager_name,\n\n        COUNT(idt.detail_id) AS item_count,\n        COALESCE(SUM(idt.install_total), 0) AS install_total\n\n    FROM setup s\n    LEFT JOIN `user` u ON s.user_id = u.user_id\n    LEFT JOIN product p ON s.pro_id = p.pro_id\n    {$latest_assignment_join}\n    LEFT JOIN technicians t ON a.tech_id = t.tech_id\n    LEFT JOIN `user` m ON a.assign_by = m.user_id\n    LEFT JOIN install_detail idt ON s.setup_id = idt.setup_id\n";

$install_date_group_sql = $has_install_date ? "        a.assign_install_date,\n" : "";
$install_time_group_sql = $has_install_time ? "        a.assign_install_time,\n" : "";
$install_end_time_group_sql = $has_install_end_time ? "        a.assign_install_end_time,\n" : "";

$group_sql = "\n    GROUP BY\n        s.setup_id,\n        s.user_id,\n        s.pro_id,\n        s.setup_date,\n        s.setup_location,\n        s.setup_address,\n        s.setup_note,\n        s.setup_status,\n        s.created_at,\n        u.user_name,\n        u.user_phone,\n        u.user_email,\n        u.user_address,\n        p.pro_name,\n        p.pro_price_install,\n        a.assign_id,\n        a.assign_date,\n        a.assign_status,\n{$install_date_group_sql}{$install_time_group_sql}{$install_end_time_group_sql}        t.tech_id,\n        t.tech_name,\n        t.tech_fullname,\n        t.tech_phone,\n        t.tech_email,\n        t.tech_status,\n        m.user_name\n";

if ($search !== '') {
    $like = '%' . $search . '%';

    $stmt = $conn->prepare($base_sql . "\n        WHERE s.setup_id LIKE ?\n           OR u.user_name LIKE ?\n           OR u.user_phone LIKE ?\n           OR u.user_email LIKE ?\n           OR p.pro_name LIKE ?\n           OR t.tech_name LIKE ?\n           OR t.tech_fullname LIKE ?\n           OR t.tech_phone LIKE ?\n           OR m.user_name LIKE ?\n    " . $group_sql . "\n        ORDER BY
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


$status_tabs = [
    'all' => ['label' => 'ทั้งหมด', 'status' => null],
    'assigned' => ['label' => 'มอบหมายงานแล้ว', 'status' => '1'],
    'accepted' => ['label' => 'ช่างรับงานแล้ว', 'status' => '2'],
    'working' => ['label' => 'กำลังติดตั้ง', 'status' => '3'],
    'done' => ['label' => 'เสร็จสิ้น', 'status' => '4'],
    'canceled' => ['label' => 'ยกเลิกแล้ว', 'status' => 'canceled'],
];

function assignment_history_row(array $row): bool
{
    $is_canceled = !empty($row['assign_id'])
        && (string) ($row['assign_status'] ?? '') === '4';

    if ($is_canceled) {
        return true;
    }

    if (!empty($row['is_overdue'])) {
        return false;
    }

    return (string) ($row['setup_status'] ?? '') !== '0';
}

$status_counts = [];
foreach ($status_tabs as $key => $tab) {
    if ($key === 'all') {
        $status_counts[$key] = count(array_filter($setups, 'assignment_history_row'));
        continue;
    }

    $status_counts[$key] = count(array_filter($setups, function ($row) use ($key, $tab) {
        if (!assignment_history_row($row)) {
            return false;
        }

        if ($key === 'canceled') {
            return !empty($row['assign_id']) && (string) ($row['assign_status'] ?? '') === '4';
        }

        if (!empty($row['assign_id']) && (string) ($row['assign_status'] ?? '') === '4') {
            return false;
        }

        return (string) ($row['setup_status'] ?? '') === (string) $tab['status'];
    }));
}

$visible_setups = array_values(array_filter($setups, function ($row) use ($status_filter, $status_tabs) {
    if (!assignment_history_row($row)) {
        return false;
    }

    if ($status_filter === 'all') {
        return true;
    }

    if ($status_filter === 'canceled') {
        return !empty($row['assign_id']) && (string) ($row['assign_status'] ?? '') === '4';
    }

    if (!empty($row['assign_id']) && (string) ($row['assign_status'] ?? '') === '4') {
        return false;
    }

    $status = $status_tabs[$status_filter]['status'] ?? null;
    return (string) ($row['setup_status'] ?? '') === (string) $status;
}));
function assignment_status_url(string $status, string $keyword): string
{
    $params = [];
    if ($keyword !== '') {
        $params['q'] = $keyword;
    }
    if ($status !== 'all') {
        $params['status'] = $status;
    }

    $query = http_build_query($params);
    return app_system_url('manager/assignment_history.php' . ($query ? '?' . $query : ''));
}

$setups_json = json_encode($visible_setups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

layout_header('ประวัติการมอบหมายงาน', 'assignment_history');
?>

<div class="manager-list-page manager-list-detail-page assignment-from-setup-page assignment-list-no-tophead assignment-history-page">
    <?= flash_message() ?>

    <section class="manager-panel manager-list-panel assignment-from-setup-card">
        <div class="manager-panel-head manager-list-panel-head assignment-list-head-action">
            <div>
                <h2>ประวัติการมอบหมายงาน</h2>
                <p>ตรวจสอบประวัติการมอบหมายและสถานะงานย้อนหลัง</p>
            </div>

        </div>

        <form class="assignment-search-form assignment-toolbar-card" method="GET" action="<?= h(app_system_url('manager/assignment_history.php')) ?>">
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

                <a class="btn-reset assignment-reset-btn" href="<?= h(app_system_url('manager/assignment_history.php')) ?>">
                    <?= manager_icon_svg('reset') ?>
                    ล้างค้นหา
                </a>
            </div>
        </form>

        <div class="assignment-status-tabs">
            <?php foreach ($status_tabs as $key => $tab): ?>
                <a
                    class="assignment-status-tab status-<?= h($key) ?> <?= $status_filter === $key ? 'active' : '' ?>"
                    href="<?= h(assignment_status_url($key, $keyword)) ?>"
                >
                    <span><?= h($tab['label']) ?></span>
                    <?php $tab_count = (int) ($status_counts[$key] ?? 0); ?>
                    <?php if ($key === 'all'): ?>
                        <b><?= h((string) $tab_count) ?></b>
                    <?php elseif ($tab_count > 0): ?>
                        <span class="status-count-badge"><?= h((string) $tab_count) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="table-wrap manager-table-wrap assignment-table-wrap">
            <table class="data-table manager-table assignment-detail-table setup-source-table">
                <thead>
                    <tr>
                        <th>รหัสใบงาน</th>
                        <th>ลูกค้า</th>
                        <th>สินค้า</th>
                        <th>วันที่ติดตั้ง</th>
                        <th>เวลา</th>
                        <th>ช่าง</th>
                        <th>ผู้มอบหมาย</th>
                        <th>สถานะ</th>
                        <th class="text-center">รายละเอียด</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($visible_setups) === 0): ?>
                        <tr>
                            <td colspan="9" class="empty-state">ยังไม่มีประวัติการมอบหมายงาน</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($visible_setups as $row): ?>
                        <tr>
                            <td><strong><?= h($row['setup_id']) ?></strong></td>

                            <td><strong><?= h($row['customer_display']) ?></strong></td>

                            <td><?= h($row['pro_name'] ?: '-') ?></td>

                            <td><strong><?= h($row['install_date_display']) ?></strong></td>

                            <td><?= h($row['install_time_display']) ?></td>

                            <td>
                                <?php if (!empty($row['tech_id'])): ?>
                                    <?php $tech_unavailable = assignment_has_unavailable_active_tech($row); ?>
                                    <strong class="history-tech-name">
                                        <span
                                            class="history-tech-dot <?= $tech_unavailable ? 'is-unavailable' : 'is-ready' ?>"
                                            title="<?= $tech_unavailable ? 'ไม่พร้อมรับงานใหม่' : 'พร้อมรับงาน' ?>"
                                            aria-label="<?= $tech_unavailable ? 'ไม่พร้อมรับงานใหม่' : 'พร้อมรับงาน' ?>"
                                        ></span>
                                        <?= h($row['tech_display']) ?>
                                    </strong>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td><?= h($row['manager_display']) ?></td>

                            <td>
                                <span class="badge <?= h($row['status_badge']) ?>">
                                    <?= h($row['status_name']) ?>
                                </span>
                            </td>

                            <td class="assignment-row-actions assignment-icon-actions">
                                <a
                                    class="assignment-icon-btn view-slip history-detail-btn"
                                    href="<?= h(app_system_url('manager/assignments.php?setup_id=' . urlencode($row['setup_id']))) ?>"
                                    title="ดูรายละเอียด"
                                    aria-label="ดูรายละเอียด"
                                >
                                    <?= manager_icon_svg('eye') ?>
                                    <span>ดูรายละเอียด</span>
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
<link rel="stylesheet" href="<?= h(app_asset_url('manager/assets/css/assignment_history.css')) ?>?v=<?= h(asset_version('manager/assets/css/assignment_history.css')) ?>">

<?php layout_footer(); ?>
