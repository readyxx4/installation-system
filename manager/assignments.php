<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('1');

function table_exists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function column_exists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function prepare_assignment_table(mysqli $conn): void
{
    if (!table_exists($conn, 'assignment')) {
        $conn->query("
            CREATE TABLE assignment (
                assign_id CHAR(11) PRIMARY KEY,
                setup_id CHAR(11) NOT NULL,
                tech_id CHAR(13) NOT NULL,
                user_id CHAR(13) NOT NULL,
                assign_by CHAR(13) NULL,
                assign_date DATETIME NOT NULL,
                assign_install_date DATE NULL,
                assign_install_time TIME NULL,
                assign_install_end_time TIME NULL,
                assign_status INT(1) NOT NULL DEFAULT 1
            )
        ");
        return;
    }

    if (!column_exists($conn, 'assignment', 'assign_id')) {
        $conn->query("ALTER TABLE assignment ADD COLUMN assign_id CHAR(11) NOT NULL");
    }
    if (!column_exists($conn, 'assignment', 'setup_id')) {
        $conn->query("ALTER TABLE assignment ADD COLUMN setup_id CHAR(11) NULL");
    }
    if (!column_exists($conn, 'assignment', 'tech_id')) {
        $conn->query("ALTER TABLE assignment ADD COLUMN tech_id CHAR(13) NULL");
    }
    if (!column_exists($conn, 'assignment', 'user_id')) {
        $conn->query("ALTER TABLE assignment ADD COLUMN user_id CHAR(13) NULL");
    }
    if (!column_exists($conn, 'assignment', 'assign_by')) {
        $conn->query("ALTER TABLE assignment ADD COLUMN assign_by CHAR(13) NULL AFTER user_id");
    }
    if (!column_exists($conn, 'assignment', 'assign_date')) {
        $conn->query("ALTER TABLE assignment ADD COLUMN assign_date DATETIME NULL");
    }
    if (!column_exists($conn, 'assignment', 'assign_install_date')) {
        $conn->query("ALTER TABLE assignment ADD COLUMN assign_install_date DATE NULL AFTER assign_date");
    }
    if (!column_exists($conn, 'assignment', 'assign_install_time')) {
        $conn->query("ALTER TABLE assignment ADD COLUMN assign_install_time TIME NULL AFTER assign_install_date");
    }
    if (!column_exists($conn, 'assignment', 'assign_install_end_time')) {
        $conn->query("ALTER TABLE assignment ADD COLUMN assign_install_end_time TIME NULL AFTER assign_install_time");
    }
    if (!column_exists($conn, 'assignment', 'assign_status')) {
        $conn->query("ALTER TABLE assignment ADD COLUMN assign_status INT(1) NOT NULL DEFAULT 1");
    }
}

function make_assign_id(mysqli $conn): string
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
    throw new Exception('ไม่สามารถสร้างรหัสการมอบหมายงานใหม่ได้');
}

function manager_icon_svg(string $name): string
{
    $icons = [
        'search' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20L16.65 16.65"></path></svg>',
        'calendar' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4"></path><path d="M8 2v4"></path><path d="M3 10h18"></path></svg>',
        'user' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>',
        'tool' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5 5L3 18l3 3 6.7-6.7a4 4 0 0 0 5-5l-2.4 2.4-3-3 2.4-2.4z"></path></svg>',
        'check' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6L9 17l-5-5"></path></svg>',
        'arrow' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14"></path><path d="M13 5l7 7-7 7"></path></svg>',
        'back' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 12H5"></path><path d="M12 5l-7 7 7 7"></path></svg>',
        'eye' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
        'close' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 6L6 18"></path><path d="M6 6l12 12"></path></svg>',
        'box' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 8l-9-5-9 5 9 5 9-5z"></path><path d="M3 8v8l9 5 9-5V8"></path><path d="M12 13v8"></path></svg>',
    ];
    return $icons[$name] ?? '';
}

function manager_thai_date(?string $date): string
{
    if (empty($date)) {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '-';
}

function manager_money($value): string
{
    return number_format((float) $value, 2) . ' บาท';
}

function assign_status_name($status): string
{
    return match ((string) $status) {
        '1' => 'มอบหมายแล้ว',
        '2' => 'ช่างรับงานแล้ว',
        '3' => 'ช่างปฏิเสธงาน',
        '4' => 'ยกเลิกการมอบหมาย',
        '5' => 'งานเสร็จสิ้น',
        default => 'ยังไม่มอบหมาย',
    };
}

function normalize_time_input(string $time): string
{
    $time = trim($time);
    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        return $time;
    }
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return substr($time, 0, 5);
    }
    return '';
}

function time_to_minutes(string $time): int
{
    $time = normalize_time_input($time);
    if ($time === '') {
        return -1;
    }
    [$hour, $minute] = array_map('intval', explode(':', $time));
    return ($hour * 60) + $minute;
}

function time_label(?string $start, ?string $end): string
{
    $start = normalize_time_input((string) $start);
    $end = normalize_time_input((string) $end);
    if ($start === '') {
        return '-';
    }
    return $end !== '' ? $start . ' - ' . $end . ' น.' : $start . ' น.';
}

prepare_assignment_table($conn);

$setup_id = trim($_GET['setup_id'] ?? $_POST['setup_id'] ?? '');

if ($setup_id === '') {
    redirect_to(app_system_url('manager/assignment_list.php?status=select_setup'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tech_id = trim($_POST['tech_id'] ?? '');
    $install_date = trim($_POST['assign_install_date'] ?? '');
    $install_time = normalize_time_input($_POST['assign_install_time'] ?? '');
    $install_end_time = normalize_time_input($_POST['assign_install_end_time'] ?? '');
    $assign_date = date('Y-m-d H:i:s');
    $assign_status = 1;

    if ($setup_id === '' || $tech_id === '' || $install_date === '' || $install_time === '' || $install_end_time === '') {
        redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=error'));
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $install_date)) {
        redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=error'));
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $install_time) || !preg_match('/^\d{2}:\d{2}$/', $install_end_time)) {
        redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=error'));
    }

    if (time_to_minutes($install_time) < 0 || time_to_minutes($install_end_time) <= time_to_minutes($install_time)) {
        redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=error'));
    }

    $install_time_db = $install_time . ':00';
    $install_end_time_db = $install_end_time . ':00';

    try {
        $stmt_setup = $conn->prepare("SELECT setup_id, user_id, setup_status FROM setup WHERE setup_id = ? LIMIT 1");
        $stmt_setup->bind_param('s', $setup_id);
        $stmt_setup->execute();
        $setup = $stmt_setup->get_result()->fetch_assoc();
        if (!$setup) {
            redirect_to(app_system_url('manager/assignment_list.php?status=notfound'));
        }

        $assign_by = $_SESSION['user_id'] ?? '';
        if ($assign_by === '') {
            redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=error'));
        }

        $active_stmt = $conn->prepare("
            SELECT
                a.assign_id,
                a.tech_id,
                a.assign_install_date,
                TIME_FORMAT(a.assign_install_time, '%H:%i') AS assign_install_time,
                TIME_FORMAT(a.assign_install_end_time, '%H:%i') AS assign_install_end_time,
                COALESCE(t.tech_status, 0) AS current_tech_status
            FROM assignment a
            LEFT JOIN technicians t ON a.tech_id = t.tech_id
            WHERE a.setup_id = ?
              AND a.assign_status IN (1, 2)
            ORDER BY a.assign_date DESC, a.assign_id DESC
            LIMIT 1
        ");
        $active_stmt->bind_param('s', $setup_id);
        $active_stmt->execute();
        $active_assignment = $active_stmt->get_result()->fetch_assoc();
        $is_limited_edit = $active_assignment && (int) ($active_assignment['current_tech_status'] ?? 0) !== 0;

        if ($is_limited_edit) {
            $locked_install_date = (string) ($active_assignment['assign_install_date'] ?? '');

            if ($locked_install_date === '') {
                redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=error'));
            }

            if ((string) $install_date !== $locked_install_date) {
                redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=time_only_edit'));
            }
        }

        $stmt_tech = $conn->prepare("SELECT tech_id, tech_status FROM technicians WHERE tech_id = ? LIMIT 1");
        $stmt_tech->bind_param('s', $tech_id);
        $stmt_tech->execute();
        $tech = $stmt_tech->get_result()->fetch_assoc();
        $is_editing_current_tech = $active_assignment && (string) ($active_assignment['tech_id'] ?? '') === (string) $tech_id;
        if (!$tech) {
            redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=tech_unavailable'));
        }

        if ((int) ($tech['tech_status'] ?? 0) !== 0 && !$is_editing_current_tech) {
            redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=tech_unavailable'));
        }

        $conflict_stmt = $conn->prepare("
            SELECT assign_id
            FROM assignment
            WHERE tech_id = ?
              AND assign_install_date = ?
              AND assign_status IN (1, 2)
              AND setup_id <> ?
              AND assign_install_time IS NOT NULL
              AND COALESCE(assign_install_end_time, ADDTIME(assign_install_time, '02:00:00')) IS NOT NULL
              AND assign_install_time < ?
              AND COALESCE(assign_install_end_time, ADDTIME(assign_install_time, '02:00:00')) > ?
            LIMIT 1
        ");
        $conflict_stmt->bind_param('sssss', $tech_id, $install_date, $setup_id, $install_end_time_db, $install_time_db);
        $conflict_stmt->execute();
        if ($conflict_stmt->get_result()->num_rows > 0) {
            redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=time_conflict'));
        }

        $conn->begin_transaction();

        if ($active_assignment) {
            $update_assign = $conn->prepare("
                UPDATE assignment
                SET tech_id = ?,
                    assign_by = ?,
                    assign_date = ?,
                    assign_install_date = ?,
                    assign_install_time = ?,
                    assign_install_end_time = ?,
                    assign_status = 1
                WHERE assign_id = ?
            ");
            $update_assign->bind_param('sssssss', $tech_id, $assign_by, $assign_date, $install_date, $install_time_db, $install_end_time_db, $active_assignment['assign_id']);
            $update_assign->execute();
        } else {
            $assign_id = make_assign_id($conn);
            $user_id = $setup['user_id'];

            $insert_assign = $conn->prepare("
                INSERT INTO assignment
                (assign_id, setup_id, tech_id, user_id, assign_by, assign_date, assign_install_date, assign_install_time, assign_install_end_time, assign_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert_assign->bind_param('sssssssssi', $assign_id, $setup_id, $tech_id, $user_id, $assign_by, $assign_date, $install_date, $install_time_db, $install_end_time_db, $assign_status);
            $insert_assign->execute();
        }

        $new_setup_status = 1;
        $update_setup = $conn->prepare("UPDATE setup SET setup_status = ? WHERE setup_id = ?");
        $update_setup->bind_param('is', $new_setup_status, $setup_id);
        $update_setup->execute();

        $conn->commit();
        redirect_to(app_system_url('manager/assignment_list.php?status=created'));
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // skip rollback error
        }
        redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=error'));
    }
}

$setup_stmt = $conn->prepare("
    SELECT
        s.setup_id,
        s.setup_date,
        s.setup_address,
        s.setup_location,
        s.setup_status,
        s.setup_note,
        s.created_at,
        u.user_id,
        u.user_name,
        u.user_fullname,
        u.user_phone,
        u.user_email,
        COALESCE(
            NULLIF(GROUP_CONCAT(DISTINCT p2.pro_name ORDER BY p2.pro_name SEPARATOR ', '), ''),
            p.pro_name,
            '-'
        ) AS product_names,
        COUNT(idt.detail_id) AS item_count,
        COALESCE(SUM(idt.install_total), 0) AS install_total
    FROM setup s
    LEFT JOIN `user` u ON s.user_id = u.user_id
    LEFT JOIN product p ON s.pro_id = p.pro_id
    LEFT JOIN install_detail idt ON s.setup_id = idt.setup_id
    LEFT JOIN product p2 ON idt.pro_id = p2.pro_id
    WHERE s.setup_id = ?
    GROUP BY
        s.setup_id,
        s.setup_date,
        s.setup_address,
        s.setup_location,
        s.setup_status,
        s.setup_note,
        s.created_at,
        u.user_id,
        u.user_name,
        u.user_fullname,
        u.user_phone,
        u.user_email,
        p.pro_name
    LIMIT 1
");
$setup_stmt->bind_param('s', $setup_id);
$setup_stmt->execute();
$selected_setup = $setup_stmt->get_result()->fetch_assoc();

if (!$selected_setup) {
    redirect_to(app_system_url('manager/assignment_list.php?status=notfound'));
}

$current_assignment_stmt = $conn->prepare("
    SELECT
        a.assign_id,
        a.tech_id,
        a.assign_date,
        a.assign_install_date,
        TIME_FORMAT(a.assign_install_time, '%H:%i') AS assign_install_time,
        TIME_FORMAT(a.assign_install_end_time, '%H:%i') AS assign_install_end_time,
        a.assign_status,
        t.tech_name,
        t.tech_fullname,
        t.tech_phone,
        t.tech_status
    FROM assignment a
    LEFT JOIN technicians t ON a.tech_id = t.tech_id
    WHERE a.setup_id = ?
      AND a.assign_status IN (1, 2)
    ORDER BY a.assign_date DESC, a.assign_id DESC
    LIMIT 1
");
$current_assignment_stmt->bind_param('s', $setup_id);
$current_assignment_stmt->execute();
$current_assignment = $current_assignment_stmt->get_result()->fetch_assoc();

$technicians_data = [];
$technicians_result = $conn->query("
    SELECT
        t.tech_id,
        t.tech_name,
        t.tech_fullname,
        t.tech_phone,
        t.tech_email,
        t.tech_status,
        COUNT(a.assign_id) AS total_queue
    FROM technicians t
    LEFT JOIN assignment a
        ON t.tech_id = a.tech_id
       AND a.assign_status IN (1, 2)
    GROUP BY
        t.tech_id,
        t.tech_name,
        t.tech_fullname,
        t.tech_phone,
        t.tech_email,
        t.tech_status
    ORDER BY t.tech_status ASC, t.tech_name ASC
");
while ($row = $technicians_result->fetch_assoc()) {
    $technicians_data[] = $row;
}

$tech_schedules_data = [];
$tech_schedules_result = $conn->query("
    SELECT
        a.assign_id,
        a.tech_id,
        a.setup_id,
        COALESCE(a.assign_install_date, DATE(a.assign_date)) AS assign_install_date,
        TIME_FORMAT(a.assign_install_time, '%H:%i') AS assign_install_time,
        TIME_FORMAT(a.assign_install_end_time, '%H:%i') AS assign_install_end_time,
        a.assign_status,
        u.user_name,
        u.user_fullname,
        u.user_phone,
        COALESCE(p.pro_name, '-') AS pro_name,
        s.setup_address,
        s.setup_location
    FROM assignment a
    LEFT JOIN setup s ON a.setup_id = s.setup_id
    LEFT JOIN `user` u ON a.user_id = u.user_id
    LEFT JOIN product p ON s.pro_id = p.pro_id
    WHERE a.assign_status IN (1, 2)
      AND COALESCE(a.assign_install_date, DATE(a.assign_date)) IS NOT NULL
    ORDER BY COALESCE(a.assign_install_date, DATE(a.assign_date)) ASC, a.assign_install_time ASC, a.assign_date ASC
");
while ($row = $tech_schedules_result->fetch_assoc()) {
    $tech_schedules_data[] = $row;
}

$selected_setup['customer_display'] = $selected_setup['user_fullname'] ?: ($selected_setup['user_name'] ?: '-');
$selected_setup['setup_address_display'] = $selected_setup['setup_address'] ?: ($selected_setup['setup_location'] ?: '-');
$selected_setup['setup_date_display'] = manager_thai_date($selected_setup['setup_date'] ?? null);
$selected_setup['created_at_display'] = manager_thai_date($selected_setup['created_at'] ?? null);
$selected_setup['install_total_display'] = manager_money($selected_setup['install_total'] ?? 0);
$selected_setup['item_count_display'] = (int) ($selected_setup['item_count'] ?? 0);

$current_tech_id = $current_assignment['tech_id'] ?? '';
$current_install_date = $current_assignment['assign_install_date'] ?? '';
$current_install_time = $current_assignment['assign_install_time'] ?? '';
$current_install_end_time = $current_assignment['assign_install_end_time'] ?? '';
$current_tech_unavailable = $current_assignment && (int) ($current_assignment['tech_status'] ?? 0) !== 0;

$selected_setup_json = json_encode($selected_setup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$current_assignment_json = json_encode($current_assignment ?: null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$technicians_json = json_encode($technicians_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tech_schedules_json = json_encode($tech_schedules_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$current_tech_json = json_encode($current_tech_id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$current_install_date_json = json_encode($current_install_date, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$current_install_time_json = json_encode($current_install_time, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$current_install_end_time_json = json_encode($current_install_end_time, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$current_tech_unavailable_json = json_encode($current_tech_unavailable, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

layout_header('มอบหมายงานช่าง', 'assignments');
?>

<div class="manager-assign-v2 manager-assign-er-page">
    <div class="manager-dashboard-head compact">
        <div>
            <span class="manager-eyebrow">Process 5</span>
            <h1>มอบหมายงานช่าง</h1>
            <p><?= $current_tech_unavailable ? 'ช่างเดิมถูกตั้งค่าไม่ว่าง จึงแก้ไขได้เฉพาะช่างและเวลาในวันเดิมเท่านั้น' : ($current_assignment ? 'งานนี้มอบหมายแล้ว สามารถแก้ไขช่าง วันที่ และเวลาได้' : 'เลือกช่างก่อน แล้วดูคิวงานของช่างคนนั้นเพื่อกำหนดวันที่ติดตั้ง') ?></p>
        </div>
    </div>

    <?= flash_message() ?>

    <form method="POST" action="<?= h(app_system_url('manager/assignments.php')) ?>" onsubmit="return beforeAssignSubmit()">
        <input type="hidden" id="setup_id" name="setup_id" value="<?= h($selected_setup['setup_id']) ?>" required>
        <input type="hidden" id="tech_id" name="tech_id" value="<?= h($current_tech_id) ?>" required>
        <input type="hidden" id="assign_install_date" name="assign_install_date" value="<?= h($current_install_date) ?>" required>
        <input type="hidden" id="assign_install_time" name="assign_install_time" value="<?= h($current_install_time) ?>" required>
        <input type="hidden" id="assign_install_end_time" name="assign_install_end_time" value="<?= h($current_install_end_time) ?>" required>

        <section class="manager-assign-panel manager-selected-setup-panel always-show">
            <div class="manager-panel-head">
                <div>
                    <h2>ใบงานติดตั้งที่เลือก</h2>
                    <p>ข้อมูลใบงานที่ส่งมาจากรายการใบงานติดตั้ง</p>
                </div>
                <a class="manager-soft-link" href="<?= h(app_system_url('finance/setup_slip.php?id=' . urlencode($selected_setup['setup_id']) . '&from=manager')) ?>">
                    <?= manager_icon_svg('eye') ?> ดูใบติดตั้ง
                </a>
            </div>

            <div class="assign-setup-summary-grid">
                <div>
                    <span>รหัสใบงาน</span>
                    <strong><?= h($selected_setup['setup_id']) ?></strong>
                </div>
                <div>
                    <span>ลูกค้า</span>
                    <strong><?= h($selected_setup['customer_display']) ?></strong>
                    <small><?= h($selected_setup['user_phone'] ?: '-') ?></small>
                </div>
                <div>
                    <span>สินค้า</span>
                    <strong><?= h($selected_setup['product_names'] ?: '-') ?></strong>
                </div>
                <div>
                    <span>จำนวน / รวมค่าติดตั้ง</span>
                    <strong><?= h((string) $selected_setup['item_count_display']) ?> รายการ</strong>
                    <small><?= h($selected_setup['install_total_display']) ?></small>
                </div>
                <div class="full">
                    <span>ที่อยู่ติดตั้ง</span>
                    <strong><?= h($selected_setup['setup_address_display']) ?></strong>
                </div>
                <?php if (!empty($current_assignment)): ?>
                    <div class="full current-assign-alert">
                        <span>การมอบหมายปัจจุบัน</span>
                        <strong>
                            <?= h(($current_assignment['tech_fullname'] ?: $current_assignment['tech_name']) ?: '-') ?>
                            วันที่ <?= h(manager_thai_date($current_assignment['assign_install_date'] ?? null)) ?>
                            เวลา <?= h(time_label($current_assignment['assign_install_time'] ?? '', $current_assignment['assign_install_end_time'] ?? '')) ?>
                            — <?= h(assign_status_name($current_assignment['assign_status'] ?? '')) ?>
                        </strong>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="manager-assign-panel manager-tech-panel show" id="techPanel">
            <div class="manager-panel-head">
                <div>
                    <h2>1. เลือกช่างติดตั้ง</h2>
                    <p><?= $current_tech_unavailable ? 'ช่างเดิมไม่ว่าง ระบบล็อกเฉพาะวันที่ไว้ แต่สามารถเปลี่ยนช่างและเวลาได้' : 'ช่างที่ตั้งค่าไม่ว่างยังแสดงชื่อ แต่ไม่สามารถเลือกมอบหมายงานใหม่ได้' ?></p>
                </div>
                <span class="manager-soft-badge" id="availableCountText">-</span>
            </div>

            <div class="manager-tech-checker">
                <div class="manager-assign-search">
                    <?= manager_icon_svg('search') ?>
                    <input type="text" id="techSearch" placeholder="ค้นหาช่าง ชื่อ เบอร์โทร หรืออีเมล..." autocomplete="off">
                </div>
            </div>

            <div class="manager-tech-list" id="techList"></div>
        </section>

        <section class="manager-assign-panel manager-schedule-panel" id="schedulePanel">
            <div class="manager-panel-head">
                <div>
                    <h2>2. ตารางเวลาช่างและเลือกวันที่ติดตั้ง</h2>
                    <p id="selectedTechScheduleText"><?= $current_tech_unavailable ? 'วันที่ได้รับมอบหมายเดิมจะแสดงเป็นสีเทา เลือกวันอื่นไม่ได้ แต่เปลี่ยนช่างและเวลาได้' : ($current_assignment ? 'สามารถเลือกช่าง วันที่ และเวลาใหม่ได้ตามต้องการ' : 'เลือกช่างก่อน ระบบจะแสดงจำนวนคิวและตารางวันว่างของช่างคนนั้น') ?></p>
                </div>
                <span class="manager-soft-badge" id="techQueueCountText">ยังไม่ได้เลือกช่าง</span>
            </div>

            <div class="manager-tech-queue-table-wrap" id="techQueueBox">
                <table class="manager-tech-queue-table">
                    <thead>
                        <tr>
                            <th>รหัสใบงาน</th>
                            <th>ลูกค้า</th>
                            <th>สินค้า</th>
                            <th>วันที่ติดตั้ง</th>
                            <th>เวลา</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody id="techQueueTableBody">
                        <tr><td colspan="6" class="manager-empty-cell">ยังไม่ได้เลือกช่าง</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="manager-calendar-toolbar">
                <button type="button" onclick="changeCalendarMonth(-1)">‹</button>
                <strong id="calendarMonthText">-</strong>
                <button type="button" onclick="changeCalendarMonth(1)">›</button>
                <span class="calendar-legend available">ว่าง</span>
                <span class="calendar-legend busy">มีคิว/ไม่ว่าง</span>
                <span class="calendar-legend holiday">วันหยุด</span>
                <?php if (!empty($current_tech_unavailable)): ?>
                    <span class="calendar-legend locked">วันที่เดิม</span>
                <?php endif; ?>
            </div>

            <div class="manager-calendar" id="installCalendar"></div>
            <div class="manager-selected-date" id="selectedDateBox">ยังไม่ได้เลือกวันที่ติดตั้ง</div>

            <div class="manager-time-panel" id="timePanel">
                <div class="manager-time-panel-head">
                    <div>
                        <h3>เลือกเวลาติดตั้ง</h3>
                        <p>เลือกช่วงเวลาที่ไม่ชนกับคิวงานเดิมของช่างในวันเดียวกัน</p>
                    </div>
                    <span class="manager-soft-badge" id="selectedTimeText">ยังไม่ได้เลือกเวลา</span>
                </div>

                <div class="manager-time-slots" id="timeSlots"></div>

                <div class="manager-custom-time-box">
                    <div>
                        <h4>กำหนดเวลาเอง</h4>
                        <p>ใช้ในกรณีที่หัวหน้าช่างต้องการกำหนดช่วงเวลาเฉพาะให้ช่างคนนี้</p>
                    </div>
                    <div class="manager-custom-time-grid">
                        <label>
                            เริ่ม
                            <input type="time" id="customStartTime" min="08:00" max="18:00" step="900">
                        </label>
                        <label>
                            สิ้นสุด
                            <input type="time" id="customEndTime" min="08:00" max="18:00" step="900">
                        </label>
                        <button type="button" class="manager-custom-time-btn" onclick="selectCustomTime()">ใช้เวลานี้</button>
                    </div>
                    <p class="manager-custom-time-note" id="customTimeNote">กำหนดเวลาได้ แต่ต้องไม่ทับกับคิวเดิมในวันเดียวกัน</p>
                </div>
            </div>
        </section>

        <div class="manager-form-actions sticky-actions">
            <a class="manager-action-btn" href="<?= h(app_system_url('manager/assignment_list.php')) ?>">
                <?= manager_icon_svg('back') ?>
                กลับรายการใบงาน
            </a>
            <button class="manager-action-btn primary" type="submit">
                <?= manager_icon_svg('check') ?>
                บันทึกการมอบหมาย
            </button>
        </div>
    </form>
</div>

<script>
const selectedSetup = <?= $selected_setup_json ?: '{}' ?>;
const currentAssignment = <?= $current_assignment_json ?: 'null' ?>;
const technicians = <?= $technicians_json ?: '[]' ?>;
const techSchedules = <?= $tech_schedules_json ?: '[]' ?>;
const initialTechId = <?= $current_tech_json ?: "''" ?>;
const initialInstallDate = <?= $current_install_date_json ?: "''" ?>;
const initialInstallTime = <?= $current_install_time_json ?: "''" ?>;
const initialInstallEndTime = <?= $current_install_end_time_json ?: "''" ?>;
const isEditMode = !!(currentAssignment && currentAssignment.assign_id);
const isLimitedEdit = <?= $current_tech_unavailable_json ?: 'false' ?>;
const lockedInstallDate = isLimitedEdit ? (initialInstallDate || '') : '';

let selectedTechId = initialTechId || '';
let currentQueueTechId = initialTechId || '';
let selectedInstallDate = initialInstallDate || '';
let calendarCursor = selectedInstallDate ? dateFromKey(selectedInstallDate) : new Date();
calendarCursor.setDate(1);

const thaiMonths = [
  'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
  'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
];
const weekdayNames = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
const availableTimeSlots = [
  { start: '09:00', end: '12:00', title: 'ช่วงเช้า', detail: '09:00 - 12:00' },
  { start: '13:00', end: '15:00', title: 'ช่วงบ่าย', detail: '13:00 - 15:00' },
  { start: '16:00', end: '18:00', title: 'ช่วงเย็น', detail: '16:00 - 18:00' }
];
let selectedInstallTime = initialInstallTime || '';
let selectedInstallEndTime = initialInstallEndTime || '';

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function normalizeText(value) {
  return String(value || '').toLowerCase().trim();
}

function pad2(value) {
  return String(value).padStart(2, '0');
}

function toDateKey(dateObj) {
  return `${dateObj.getFullYear()}-${pad2(dateObj.getMonth() + 1)}-${pad2(dateObj.getDate())}`;
}

function dateFromKey(value) {
  const parts = String(value || '').split('-').map(Number);
  if (parts.length !== 3 || parts.some(Number.isNaN)) return new Date();
  return new Date(parts[0], parts[1] - 1, parts[2]);
}

function formatDate(value) {
  if (!value) return '-';
  const parts = String(value).split('-');
  if (parts.length !== 3) return value;
  return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

function normalizeTime(value) {
  const text = String(value || '').trim();
  if (/^\d{2}:\d{2}$/.test(text)) return text;
  if (/^\d{2}:\d{2}:\d{2}$/.test(text)) return text.slice(0, 5);
  return '';
}

function timeToMinutes(value) {
  const time = normalizeTime(value);
  if (!time) return -1;
  const [hour, minute] = time.split(':').map(Number);
  return (hour * 60) + minute;
}

function timeRangeLabel(start, end) {
  const startText = normalizeTime(start);
  const endText = normalizeTime(end);
  if (!startText) return '-';
  return endText ? `${startText} - ${endText} น.` : `${startText} น.`;
}

function rangesOverlap(startA, endA, startB, endB) {
  const aStart = timeToMinutes(startA);
  const aEnd = timeToMinutes(endA);
  const bStart = timeToMinutes(startB);
  const bEnd = timeToMinutes(endB);
  if (aStart < 0 || aEnd <= aStart || bStart < 0 || bEnd <= bStart) return false;
  return aStart < bEnd && aEnd > bStart;
}

function formatMonthLabel(dateObj) {
  return `${thaiMonths[dateObj.getMonth()]} ${dateObj.getFullYear() + 543}`;
}

function isHoliday(dateKey) {
  const dateObj = dateFromKey(dateKey);
  const day = dateObj.getDay();
  return day === 0 || day === 6;
}

function isTechBaseAvailable(tech) {
  return Number(tech.tech_status || 0) === 0;
}

function isTechSelectable(tech) {
  if (!tech) return false;
  // กรณีแก้งานเดิมที่ช่างเดิมไม่ว่าง: เลือกช่างเดิมได้เพื่อแก้เวลา และเลือกช่างใหม่ที่พร้อมรับงานได้
  if (isLimitedEdit && String(tech.tech_id || '') === String(initialTechId || '')) return true;
  return isTechBaseAvailable(tech);
}

function getTechById(techId) {
  return technicians.find(item => item.tech_id === techId) || null;
}

function techDisplayName(tech) {
  return tech ? (tech.tech_fullname || tech.tech_name || '-') : '-';
}

function customerDisplayName(row) {
  return row.user_fullname || row.user_name || '-';
}

function getTechSlots(techId) {
  return techSchedules
    .filter(slot => slot.tech_id === techId)
    .sort((a, b) => String(a.assign_install_date || '').localeCompare(String(b.assign_install_date || '')));
}

function getTechSlotsOnDate(techId, dateKey) {
  return techSchedules.filter(slot => slot.tech_id === techId && slot.assign_install_date === dateKey);
}

function isTimeSlotBusy(techId, dateKey, startTime, endTime) {
  return getTechSlotsOnDate(techId, dateKey).some(slot => {
    if (String(slot.setup_id || '') === String(selectedSetup.setup_id || '')) return false;
    const slotStart = normalizeTime(slot.assign_install_time);
    const slotEnd = normalizeTime(slot.assign_install_end_time) || addMinutesToTime(slotStart, 120);
    return rangesOverlap(startTime, endTime, slotStart, slotEnd);
  });
}

function addMinutesToTime(timeValue, minutesToAdd) {
  const start = timeToMinutes(timeValue);
  if (start < 0) return '';
  const total = start + Number(minutesToAdd || 0);
  return `${pad2(Math.floor(total / 60))}:${pad2(total % 60)}`;
}

function assignmentStatusText(status) {
  const value = String(status ?? '');
  if (value === '1') return 'มอบหมายแล้ว';
  if (value === '2') return 'ช่างรับงานแล้ว';
  if (value === '5') return 'เสร็จสิ้น';
  return '-';
}

function renderTechnicians() {
  const keyword = normalizeText(document.getElementById('techSearch').value);
  const list = document.getElementById('techList');
  const countText = document.getElementById('availableCountText');

  const filtered = technicians.filter(tech => {
    const text = normalizeText([tech.tech_id, tech.tech_name, tech.tech_fullname, tech.tech_phone, tech.tech_email].join(' '));
    return text.includes(keyword);
  });

  const selectableCount = filtered.filter(isTechSelectable).length;
  countText.textContent = `แสดง ${filtered.length} คน | เลือกได้ ${selectableCount} คน`;

  if (filtered.length === 0) {
    list.innerHTML = '<div class="manager-empty-state">ไม่พบข้อมูลช่าง</div>';
    return;
  }

  list.innerHTML = filtered.map(tech => {
    const active = selectedTechId === tech.tech_id ? ' active' : '';
    const unavailable = !isTechSelectable(tech);
    const disabledClass = unavailable ? ' is-disabled' : '';
    const queueCount = Number(tech.total_queue || getTechSlots(tech.tech_id).length || 0);
    const statusText = !isTechBaseAvailable(tech)
      ? (isLimitedEdit && String(tech.tech_id || '') === String(initialTechId || '') ? 'ไม่ว่าง / แก้ได้เฉพาะเวลาในวันเดิม' : 'ไม่ว่าง')
      : 'พร้อมรับงาน';

    return `
      <article class="manager-tech-card${active}${disabledClass}">
        <div class="manager-tech-card-main" onclick="${unavailable ? `viewTechnicianQueue('${escapeHtml(tech.tech_id)}')` : `selectTechnician('${escapeHtml(tech.tech_id)}')`}">
          <div class="tech-avatar">${escapeHtml((techDisplayName(tech) || 'ช').slice(0, 1))}</div>
          <div>
            <strong>${escapeHtml(techDisplayName(tech))}</strong>
            <p>${escapeHtml(tech.tech_phone || '-')} | ${escapeHtml(tech.tech_email || '-')}</p>
            <small>สถานะ: ${escapeHtml(statusText)} | คิวที่ถูกมอบหมาย ${queueCount} คิว</small>
          </div>
        </div>
        <div class="manager-tech-actions">
          <button type="button" onclick="viewTechnicianQueue('${escapeHtml(tech.tech_id)}')">ดูคิว</button>
          <button type="button" class="select" ${unavailable ? 'disabled' : ''} onclick="selectTechnician('${escapeHtml(tech.tech_id)}')">
            ${unavailable ? 'เลือกไม่ได้' : 'เลือกช่างนี้'}
          </button>
        </div>
      </article>
    `;
  }).join('');
}

function viewTechnicianQueue(techId) {
  const tech = getTechById(techId);
  if (!tech) return;

  currentQueueTechId = techId;
  if (!isTechSelectable(tech)) {
    selectedTechId = '';
    selectedInstallDate = '';
    selectedInstallTime = '';
    selectedInstallEndTime = '';
    document.getElementById('tech_id').value = '';
    document.getElementById('assign_install_date').value = '';
    document.getElementById('assign_install_time').value = '';
    document.getElementById('assign_install_end_time').value = '';
    document.getElementById('selectedDateBox').textContent = 'ช่างคนนี้ถูกตั้งค่าว่าไม่ว่าง จึงเลือกมอบหมายไม่ได้';
  }

  document.getElementById('schedulePanel').classList.add('show');
  renderTechnicians();
  renderSelectedTechSchedule();
  renderCalendar();

  setTimeout(() => document.getElementById('schedulePanel').scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
}

function selectTechnician(techId) {
  const tech = getTechById(techId);
  if (!tech || !isTechSelectable(tech)) return;

  selectedTechId = techId;
  currentQueueTechId = techId;
  document.getElementById('tech_id').value = techId;

  if (isLimitedEdit && lockedInstallDate) {
    selectedInstallDate = lockedInstallDate;
    document.getElementById('assign_install_date').value = lockedInstallDate;
    document.getElementById('selectedDateBox').textContent = `วันที่ติดตั้งเดิม: ${formatDate(lockedInstallDate)} | เลือกวันอื่นไม่ได้ แต่เปลี่ยนช่างและเวลาได้`;
  } else {
    if (!selectedInstallDate && initialInstallDate) {
      selectedInstallDate = initialInstallDate;
    }
    document.getElementById('assign_install_date').value = selectedInstallDate || '';
    document.getElementById('selectedDateBox').textContent = selectedInstallDate
      ? `วันที่ติดตั้งที่เลือก: ${formatDate(selectedInstallDate)} | สามารถเลือกวันใหม่ได้จากปฏิทิน`
      : 'กรุณาเลือกวันที่ติดตั้งจากปฏิทิน';
  }

  selectedInstallTime = '';
  selectedInstallEndTime = '';
  document.getElementById('assign_install_time').value = '';
  document.getElementById('assign_install_end_time').value = '';
  document.getElementById('schedulePanel').classList.add('show');

  renderTechnicians();
  renderSelectedTechSchedule();
  renderCalendar();
  renderTimeSlots();

  setTimeout(() => document.getElementById('schedulePanel').scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
}

function renderSelectedTechSchedule() {
  const body = document.getElementById('techQueueTableBody');
  const title = document.getElementById('selectedTechScheduleText');
  const count = document.getElementById('techQueueCountText');
  const tech = getTechById(currentQueueTechId);

  if (!tech) {
    body.innerHTML = '<tr><td colspan="6" class="manager-empty-cell">ยังไม่ได้เลือกช่าง</td></tr>';
    title.textContent = 'เลือกช่างก่อน ระบบจะแสดงจำนวนคิวและตารางวันว่างของช่างคนนั้น';
    count.textContent = 'ยังไม่ได้เลือกช่าง';
    return;
  }

  const todayKey = toDateKey(new Date());
  const displayDate = selectedInstallDate || todayKey;
  const slots = getTechSlotsOnDate(tech.tech_id, displayDate)
    .sort((a, b) => String(a.assign_install_time || '00:00').localeCompare(String(b.assign_install_time || '00:00')));

  const statusText = isTechBaseAvailable(tech) ? 'พร้อมรับงาน' : 'ไม่ว่าง';
  title.textContent = `${techDisplayName(tech)} | สถานะ ${statusText} | วันที่ ${formatDate(displayDate)}`;
  count.textContent = `งานในวันนี้ ${slots.length} คิว`;

  if (slots.length === 0) {
    body.innerHTML = `<tr><td colspan="6" class="manager-empty-cell">ยังไม่มีงานที่ได้รับมอบหมายในวันที่ ${formatDate(displayDate)}</td></tr>`;
    return;
  }

  body.innerHTML = slots.map(slot => `
    <tr>
      <td>${escapeHtml(slot.setup_id || '-')}</td>
      <td>${escapeHtml(customerDisplayName(slot))}</td>
      <td>${escapeHtml(slot.pro_name || '-')}</td>
      <td>${escapeHtml(formatDate(slot.assign_install_date))}</td>
      <td><strong>${escapeHtml(timeRangeLabel(slot.assign_install_time, slot.assign_install_end_time))}</strong></td>
      <td><span class="manager-queue-status">${escapeHtml(assignmentStatusText(slot.assign_status))}</span></td>
    </tr>
  `).join('');
}
function changeCalendarMonth(diff) {
  calendarCursor.setMonth(calendarCursor.getMonth() + diff);
  calendarCursor.setDate(1);
  renderCalendar();
}

function renderCalendar() {
  const calendar = document.getElementById('installCalendar');
  const monthText = document.getElementById('calendarMonthText');
  monthText.textContent = formatMonthLabel(calendarCursor);

  const tech = getTechById(currentQueueTechId);
  if (!tech) {
    calendar.innerHTML = '<div class="manager-empty-state calendar-empty">เลือกช่างก่อนเพื่อดูตารางวันว่าง</div>';
    return;
  }

  const canSelectDate = !isLimitedEdit && selectedTechId === currentQueueTechId && isTechSelectable(tech);
  const year = calendarCursor.getFullYear();
  const month = calendarCursor.getMonth();
  const firstDay = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  let html = weekdayNames.map(day => `<div class="calendar-weekday">${day}</div>`).join('');
  for (let i = 0; i < firstDay; i++) html += '<div class="calendar-day empty"></div>';

  for (let day = 1; day <= daysInMonth; day++) {
    const dateObj = new Date(year, month, day);
    const dateKey = `${dateObj.getFullYear()}-${pad2(dateObj.getMonth() + 1)}-${pad2(dateObj.getDate())}`;
    const holiday = isHoliday(dateKey);
    const busySlots = getTechSlotsOnDate(currentQueueTechId, dateKey);
    const unavailableByStatus = !isTechSelectable(tech);
    const locked = isLimitedEdit && lockedInstallDate === dateKey;
    const lockedOtherDate = isLimitedEdit && lockedInstallDate !== dateKey;
    const selected = (selectedInstallDate === dateKey && selectedTechId === currentQueueTechId) || locked ? ' selected' : '';
    const statusClass = locked
      ? ' locked-date'
      : (lockedOtherDate ? ' locked-other-date busy' : (holiday ? ' holiday' : (busySlots.length || unavailableByStatus ? ' busy' : ' available')));
    const label = locked
      ? 'วันที่เดิม'
      : (lockedOtherDate ? 'แก้ไขวันไม่ได้' : (holiday ? 'วันหยุด' : (busySlots.length ? `${busySlots.length} คิว` : (unavailableByStatus ? 'ไม่ว่าง' : 'ว่าง'))));
    const disabled = isLimitedEdit || holiday || unavailableByStatus || !canSelectDate ? 'disabled' : '';

    html += `
      <button type="button" class="calendar-day${statusClass}${selected}" ${disabled} onclick="selectInstallDate('${dateKey}')">
        <span>${day}</span>
        <small>${label}</small>
      </button>
    `;
  }

  calendar.innerHTML = html;
}

function selectInstallDate(dateKey) {
  const tech = getTechById(currentQueueTechId);
  if (isLimitedEdit) return;
  if (!tech || selectedTechId !== currentQueueTechId || isHoliday(dateKey) || !isTechSelectable(tech)) {
    return;
  }

  selectedInstallDate = dateKey;
  selectedInstallTime = '';
  selectedInstallEndTime = '';
  document.getElementById('assign_install_date').value = dateKey;
  document.getElementById('assign_install_time').value = '';
  document.getElementById('assign_install_end_time').value = '';

  const busyCount = getTechSlotsOnDate(currentQueueTechId, dateKey).filter(slot => String(slot.setup_id || '') !== String(selectedSetup.setup_id || '')).length;
  document.getElementById('selectedDateBox').textContent = busyCount > 0
    ? `วันที่ติดตั้งที่เลือก: ${formatDate(dateKey)} | วันนี้มีคิวแล้ว ${busyCount} คิว กรุณาเลือกเวลาที่ไม่ชนกัน`
    : `วันที่ติดตั้งที่เลือก: ${formatDate(dateKey)} | ช่างยังไม่มีคิววันนี้`;

  renderCalendar();
  renderSelectedTechSchedule();
  renderTimeSlots();
}

function renderTimeSlots() {
  const box = document.getElementById('timeSlots');
  const panel = document.getElementById('timePanel');
  const text = document.getElementById('selectedTimeText');
  const customStart = document.getElementById('customStartTime');
  const customEnd = document.getElementById('customEndTime');
  const customNote = document.getElementById('customTimeNote');

  if (!box || !panel || !text) return;

  const currentTech = getTechById(currentQueueTechId);
  if (!currentQueueTechId || !selectedInstallDate || selectedTechId !== currentQueueTechId || !isTechSelectable(currentTech)) {
    panel.classList.remove('show');
    box.innerHTML = '';
    text.textContent = 'เลือกวันที่ก่อน แล้วระบบจะแสดงช่วงเวลาที่เลือกได้';
    if (customStart) customStart.value = '';
    if (customEnd) customEnd.value = '';
    if (customNote) customNote.textContent = 'กำหนดเวลาได้ แต่ต้องไม่ทับกับคิวเดิมในวันเดียวกัน';
    return;
  }

  panel.classList.add('show');
  text.textContent = selectedInstallTime && selectedInstallEndTime
    ? `เวลาที่เลือก: ${timeRangeLabel(selectedInstallTime, selectedInstallEndTime)}`
    : `เลือกช่วงเวลาสำหรับวันที่ ${formatDate(selectedInstallDate)}`;

  box.innerHTML = availableTimeSlots.map(slot => {
    const busy = isTimeSlotBusy(currentQueueTechId, selectedInstallDate, slot.start, slot.end);
    const active = selectedInstallTime === slot.start && selectedInstallEndTime === slot.end ? ' active' : '';
    const disabled = busy ? ' disabled' : '';
    const label = busy ? 'ชนกับคิวเดิม' : 'เลือกได้';

    return `
      <button type="button" class="manager-time-slot${active}${disabled}" ${busy ? 'disabled' : ''} onclick="selectInstallTime('${slot.start}', '${slot.end}')">
        <strong>${escapeHtml(slot.title)}</strong>
        <em>${escapeHtml(slot.detail)} น.</em>
        <span>${label}</span>
      </button>
    `;
  }).join('');
}

function selectInstallTime(startTime, endTime) {
  if (!selectedInstallDate || !currentQueueTechId) return;

  startTime = normalizeTime(startTime);
  endTime = normalizeTime(endTime);

  if (!startTime || !endTime || timeToMinutes(endTime) <= timeToMinutes(startTime)) {
    alert('กรุณาเลือกเวลาเริ่มต้นและเวลาสิ้นสุดให้ถูกต้อง');
    return;
  }

  if (isTimeSlotBusy(currentQueueTechId, selectedInstallDate, startTime, endTime)) {
    alert('ช่วงเวลานี้ชนกับคิวงานเดิม กรุณาเลือกช่วงเวลาอื่น');
    return;
  }

  selectedInstallTime = startTime;
  selectedInstallEndTime = endTime;
  document.getElementById('assign_install_time').value = startTime;
  document.getElementById('assign_install_end_time').value = endTime;
  document.getElementById('selectedTimeText').textContent = `เวลาที่เลือก: ${timeRangeLabel(startTime, endTime)}`;
  renderTimeSlots();
}

function selectCustomTime() {
  const start = normalizeTime(document.getElementById('customStartTime')?.value || '');
  const end = normalizeTime(document.getElementById('customEndTime')?.value || '');
  const note = document.getElementById('customTimeNote');

  if (!selectedInstallDate || !currentQueueTechId || selectedTechId !== currentQueueTechId || !isTechSelectable(getTechById(currentQueueTechId))) {
    if (note) note.textContent = 'กรุณาเลือกช่างและวันที่ก่อน';
    return;
  }

  if (!start || !end) {
    if (note) note.textContent = 'กรุณากรอกเวลาเริ่มและเวลาสิ้นสุด';
    return;
  }

  if (timeToMinutes(end) <= timeToMinutes(start)) {
    if (note) note.textContent = 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม';
    return;
  }

  if (isTimeSlotBusy(currentQueueTechId, selectedInstallDate, start, end)) {
    if (note) note.textContent = 'ช่วงเวลานี้ชนกับคิวงานเดิม กรุณาเลือกเวลาอื่น';
    return;
  }

  if (note) note.textContent = `ใช้เวลาที่กำหนดเอง: ${timeRangeLabel(start, end)}`;
  selectInstallTime(start, end);
}
function beforeAssignSubmit() {
  if (!document.getElementById('setup_id').value) {
    alert('ไม่พบใบงานติดตั้ง');
    return false;
  }
  if (!document.getElementById('tech_id').value) {
    alert('กรุณาเลือกช่างติดตั้ง');
    return false;
  }
  if (!document.getElementById('assign_install_date').value) {
    alert('กรุณาเลือกวันที่ติดตั้งจากตารางเวลาช่าง');
    return false;
  }
  if (!document.getElementById('assign_install_time').value || !document.getElementById('assign_install_end_time').value) {
    alert('กรุณาเลือกช่วงเวลาติดตั้ง');
    return false;
  }
  const startTime = document.getElementById('assign_install_time').value;
  const endTime = document.getElementById('assign_install_end_time').value;
  if (timeToMinutes(endTime) <= timeToMinutes(startTime)) {
    alert('เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม');
    return false;
  }
  if (isTimeSlotBusy(document.getElementById('tech_id').value, document.getElementById('assign_install_date').value, startTime, endTime)) {
    alert('ช่วงเวลานี้ชนกับคิวงานเดิม กรุณาเลือกช่วงเวลาอื่น');
    return false;
  }
  return true;
}

const techSearchInput = document.getElementById('techSearch');
if (techSearchInput) techSearchInput.addEventListener('input', renderTechnicians);

renderTechnicians();
renderSelectedTechSchedule();
renderCalendar();
renderTimeSlots();

if (initialTechId) {
  document.getElementById('schedulePanel').classList.add('show');
  document.getElementById('tech_id').value = initialTechId;
  document.getElementById('assign_install_date').value = initialInstallDate || '';
  if (initialInstallDate) {
    selectedInstallDate = initialInstallDate;
    document.getElementById('selectedDateBox').textContent = isLimitedEdit
      ? `วันที่ติดตั้งเดิม: ${formatDate(initialInstallDate)} | เลือกวันอื่นไม่ได้ แต่เปลี่ยนช่างและเวลาได้`
      : `วันที่ติดตั้งที่เลือก: ${formatDate(initialInstallDate)} | สามารถเลือกวันใหม่ได้จากปฏิทิน`;
  }
  if (initialInstallTime) {
    selectedInstallTime = initialInstallTime;
    selectedInstallEndTime = initialInstallEndTime || '';
    document.getElementById('assign_install_time').value = initialInstallTime;
    document.getElementById('assign_install_end_time').value = initialInstallEndTime || '';
  }
  renderSelectedTechSchedule();
  renderCalendar();
  renderTimeSlots();
}
</script>

<?php layout_footer(); ?>