<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('1');

date_default_timezone_set('Asia/Bangkok');

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
        redirect_to(app_system_url('manager/assignment_list.php?status=schema_missing'));
        return;
    }

    $required_columns = [
        'assign_id',
        'setup_id',
        'tech_id',
        'customer_id',
        'assign_by',
        'assign_date',
        'assign_install_date',
        'assign_install_time',
        'assign_install_end_time',
        'assign_status',
    ];

    foreach ($required_columns as $column) {
        if (!column_exists($conn, 'assignment', $column)) {
            redirect_to(app_system_url('manager/assignment_list.php?status=schema_missing'));
        }
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
        '1' => 'มอบหมายงานแล้ว',
        '2' => 'ช่างรับงานแล้ว',
        '3' => 'ช่างปฏิเสธงาน',
        '4' => 'ยกเลิกแล้ว',
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

function assignment_time_slots(): array
{
    return [
        'morning' => ['start' => '09:00', 'end' => '12:00'],
        'afternoon' => ['start' => '13:00', 'end' => '15:00'],
        'evening' => ['start' => '16:00', 'end' => '18:00'],
        'full_day' => ['start' => '09:00', 'end' => '18:00'],
    ];
}

prepare_assignment_table($conn);

$setup_id = trim($_GET['setup_id'] ?? $_POST['setup_id'] ?? '');

if ($setup_id === '') {
    redirect_to(app_system_url('manager/assignment_list.php?status=select_setup'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tech_id = trim($_POST['tech_id'] ?? '');
    $install_date = trim($_POST['assign_install_date'] ?? '');
    $time_slot = trim($_POST['assign_time_slot'] ?? '');
    $setup_note = trim($_POST['setup_note'] ?? '');
    $confirm_tech_change = ($_POST['confirm_tech_change'] ?? '') === '1';
    $assign_date = date('Y-m-d H:i:s');
    $assign_status = 1;

    $time_slots = assignment_time_slots();

    if ($setup_id === '' || $tech_id === '' || $install_date === '' || !isset($time_slots[$time_slot])) {
        redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=error'));
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $install_date)) {
        redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=error'));
    }

    $today_bangkok = (new DateTimeImmutable('today', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
    if ($install_date < $today_bangkok) {
        redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=past_date'));
    }

    $install_time = $time_slots[$time_slot]['start'];
    $install_end_time = $time_slots[$time_slot]['end'];
    $install_time_db = $install_time . ':00';
    $install_end_time_db = $install_end_time . ':00';

    $bangkok_timezone = new DateTimeZone('Asia/Bangkok');
    $selected_install_date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $install_date,
        $bangkok_timezone
    );

    if (!$selected_install_date) {
        redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=error'));
    }

    $selected_weekday = (int) $selected_install_date->format('N');
    if ($selected_weekday >= 6) {
        redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=holiday'));
    }

    $selected_end_datetime = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i:s',
        $install_date . ' ' . $install_end_time_db,
        $bangkok_timezone
    );
    $now_bangkok = new DateTimeImmutable('now', $bangkok_timezone);

    if (!$selected_end_datetime || $selected_end_datetime <= $now_bangkok) {
        redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=time_passed'));
    }

    try {
        $stmt_setup = $conn->prepare("SELECT setup_id, customer_id, setup_status FROM setup WHERE setup_id = ? LIMIT 1");
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
                a.assign_status,
                COALESCE(t.tech_status, 0) AS current_tech_status
            FROM assignment a
            LEFT JOIN technicians t ON a.tech_id = t.tech_id
            WHERE a.setup_id = ?
              AND a.assign_status IN (1, 2, 4, 5)
            ORDER BY a.assign_date DESC, a.assign_id DESC
            LIMIT 1
        ");
        $active_stmt->bind_param('s', $setup_id);
        $active_stmt->execute();
        $active_assignment = $active_stmt->get_result()->fetch_assoc();
        $setup_status = (int) ($setup['setup_status'] ?? 0);
        $current_assign_status = (int) ($active_assignment['assign_status'] ?? 0);

        if ($current_assign_status === 4) {
            redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=canceled_readonly'));
        }

        if ($setup_status === 4 || $current_assign_status === 5) {
            redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=readonly'));
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

        if ($active_assignment && $setup_status === 3) {
            if (!$is_editing_current_tech || (string) $install_date !== (string) $active_assignment['assign_install_date']) {
                redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=assignment_locked'));
            }
        }

        if ($active_assignment && $current_assign_status === 2 && !$is_editing_current_tech && !$confirm_tech_change) {
            redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=confirm_tech_change'));
        }

        $conflict_stmt = $conn->prepare("
            SELECT assign_id
            FROM assignment
            WHERE tech_id = ?
              AND assign_install_date = ?
              AND assign_status IN (1, 2)
              AND assign_id <> ?
              AND assign_install_time IS NOT NULL
              AND COALESCE(assign_install_end_time, ADDTIME(assign_install_time, '02:00:00')) IS NOT NULL
              AND assign_install_time < ?
              AND COALESCE(assign_install_end_time, ADDTIME(assign_install_time, '02:00:00')) > ?
            LIMIT 1
        ");
        $current_assign_id = (string) ($active_assignment['assign_id'] ?? '');
        $conflict_stmt->bind_param('sssss', $tech_id, $install_date, $current_assign_id, $install_end_time_db, $install_time_db);
        $conflict_stmt->execute();
        if ($conflict_stmt->get_result()->num_rows > 0) {
            redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=time_conflict'));
        }

        $conn->begin_transaction();

        if ($active_assignment) {
            $next_assign_status = (!$is_editing_current_tech && $current_assign_status === 2) ? 1 : $current_assign_status;
            $next_setup_status = (!$is_editing_current_tech && $current_assign_status === 2) ? 1 : $setup_status;
            $update_assign = $conn->prepare("
                UPDATE assignment
                SET tech_id = ?,
                    assign_by = ?,
                    assign_date = ?,
                    assign_install_date = ?,
                    assign_install_time = ?,
                    assign_install_end_time = ?,
                    assign_status = ?
                WHERE assign_id = ?
            ");
            $update_assign->bind_param('ssssssis', $tech_id, $assign_by, $assign_date, $install_date, $install_time_db, $install_end_time_db, $next_assign_status, $active_assignment['assign_id']);
            $update_assign->execute();

            $update_setup_status = $conn->prepare("UPDATE setup SET setup_status = ? WHERE setup_id = ?");
            $update_setup_status->bind_param('is', $next_setup_status, $setup_id);
            $update_setup_status->execute();
        } else {
            $assign_id = make_assign_id($conn);
            $customer_id = $setup['customer_id'] ?? null;

            if ($customer_id === null || $customer_id === '') {
                redirect_to(app_system_url('manager/assignments.php?setup_id=' . urlencode($setup_id) . '&status=error'));
            }

            $insert_assign = $conn->prepare("
                INSERT INTO assignment
                (assign_id, setup_id, tech_id, customer_id, assign_by, assign_date, assign_install_date, assign_install_time, assign_install_end_time, assign_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert_assign->bind_param('sssssssssi', $assign_id, $setup_id, $tech_id, $customer_id, $assign_by, $assign_date, $install_date, $install_time_db, $install_end_time_db, $assign_status);
            $insert_assign->execute();
        }

        $new_setup_status = $active_assignment ? null : 1;
        $update_setup = $conn->prepare("UPDATE setup SET setup_note = ?" . ($new_setup_status === null ? '' : ', setup_status = ?') . " WHERE setup_id = ?");
        if ($new_setup_status === null) {
            $update_setup->bind_param('ss', $setup_note, $setup_id);
        } else {
            $update_setup->bind_param('sis', $setup_note, $new_setup_status, $setup_id);
        }
        $update_setup->execute();

        $conn->commit();
        redirect_to(app_system_url('manager/assignment_history.php?status=created'));
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
        c.customer_id AS user_id,
        c.customer_name AS user_name,
        c.customer_phone AS user_phone,
        c.customer_email AS user_email,
        c.customer_address AS user_address,
        COALESCE(
            NULLIF(GROUP_CONCAT(DISTINCT p2.pro_name ORDER BY p2.pro_name SEPARATOR ', '), ''),
            p.pro_name,
            '-'
        ) AS product_names,
        COUNT(idt.detail_id) AS item_count,
        COALESCE(SUM(COALESCE(idt.install_qty, 1) * COALESCE(p2.pro_price_install, 0)), 0) AS install_total
    FROM setup s
    LEFT JOIN customers c ON s.customer_id = c.customer_id
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
        c.customer_id,
        c.customer_name,
        c.customer_phone,
        c.customer_email,
        c.customer_address,
        p.pro_name
    LIMIT 1
");
$setup_stmt->bind_param('s', $setup_id);
$setup_stmt->execute();
$selected_setup = $setup_stmt->get_result()->fetch_assoc();

if (!$selected_setup) {
    redirect_to(app_system_url('manager/assignment_list.php?status=notfound'));
}

$product_items = [];
$product_items_stmt = $conn->prepare("
    SELECT
        d.pro_id,
        COALESCE(p.pro_name, d.pro_id) AS pro_name,
        COALESCE(pt.protype_name, '-') AS protype_name,
        COALESCE(d.install_qty, 1) AS install_qty,
        COALESCE(p.pro_price_install, 0) AS install_price,
        COALESCE(d.install_qty, 1) * COALESCE(p.pro_price_install, 0) AS install_total
    FROM install_detail d
    LEFT JOIN product p ON d.pro_id = p.pro_id
    LEFT JOIN product_type pt ON p.protype_id = pt.protype_id
    WHERE d.setup_id = ?
    ORDER BY d.detail_id ASC
");
$product_items_stmt->bind_param('s', $setup_id);
$product_items_stmt->execute();
$product_items_result = $product_items_stmt->get_result();
while ($product_item = $product_items_result->fetch_assoc()) {
    $product_items[] = $product_item;
}

if (count($product_items) === 0 && !empty($selected_setup['product_names'])) {
    $product_items[] = [
        'pro_id' => '-',
        'pro_name' => $selected_setup['product_names'],
        'protype_name' => '-',
        'install_qty' => 1,
        'install_price' => (float) ($selected_setup['pro_price_install'] ?? 0),
        'install_total' => (float) ($selected_setup['pro_price_install'] ?? 0),
    ];
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
        t.tech_status,
        m.user_name AS manager_name
    FROM assignment a
    LEFT JOIN technicians t ON a.tech_id = t.tech_id
    LEFT JOIN `user` m ON a.assign_by = m.user_id
    WHERE a.setup_id = ?
      AND a.assign_status IN (1, 2, 4, 5)
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
       AND EXISTS (
           SELECT 1
           FROM setup s_active
           WHERE s_active.setup_id = a.setup_id
       )
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
        c.customer_name AS user_name,
        c.customer_phone AS user_phone,
        (
            SELECT COUNT(*)
            FROM install_detail idc
            WHERE idc.setup_id = a.setup_id
        ) AS item_count,
        COALESCE(p.pro_name, '-') AS pro_name,
        s.setup_address,
        s.setup_location
    FROM assignment a
    INNER JOIN setup s ON a.setup_id = s.setup_id
    LEFT JOIN customers c ON s.customer_id = c.customer_id
    LEFT JOIN product p ON s.pro_id = p.pro_id
    WHERE a.assign_status IN (1, 2)
      AND COALESCE(a.assign_install_date, DATE(a.assign_date)) IS NOT NULL
    ORDER BY COALESCE(a.assign_install_date, DATE(a.assign_date)) ASC, a.assign_install_time ASC, a.assign_date ASC
");
while ($row = $tech_schedules_result->fetch_assoc()) {
    $tech_schedules_data[] = $row;
}

$selected_setup['customer_display'] = $selected_setup['user_name'] ?: '-';
$selected_setup['setup_address_display'] = $selected_setup['setup_address'] ?: ($selected_setup['setup_location'] ?: '-');
$selected_setup['setup_date_display'] = manager_thai_date($selected_setup['setup_date'] ?? null);
$selected_setup['created_at_display'] = manager_thai_date($selected_setup['created_at'] ?? null);
$selected_setup['install_total'] = array_sum(array_map(
    static fn(array $item): float => (float) ($item['install_total'] ?? 0),
    $product_items
));
$selected_setup['install_total_display'] = manager_money($selected_setup['install_total'] ?? 0);
$selected_setup['item_count_display'] = (int) ($selected_setup['item_count'] ?? 0);

$current_tech_id = $current_assignment['tech_id'] ?? '';
$current_install_date = $current_assignment['assign_install_date'] ?? '';
$current_install_time = $current_assignment['assign_install_time'] ?? '';
$current_install_end_time = $current_assignment['assign_install_end_time'] ?? '';
$today_bangkok = (new DateTimeImmutable('today', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
$current_tech_unavailable = $current_assignment && (int) ($current_assignment['tech_status'] ?? 0) !== 0;
$current_setup_status = (int) ($selected_setup['setup_status'] ?? 0);
$current_assign_status = (int) ($current_assignment['assign_status'] ?? 0);
$is_canceled_assignment = $current_assign_status === 4;

$current_assignment_overdue = false;
if (
    $current_assignment
    && in_array($current_assign_status, [1, 2], true)
    && !empty($current_assignment['assign_install_date'])
    && !empty($current_assignment['assign_install_end_time'])
) {
    $overdue_timezone = new DateTimeZone('Asia/Bangkok');
    $deadline = DateTimeImmutable::createFromFormat(
        'Y-m-d H:i',
        $current_assignment['assign_install_date'] . ' ' . $current_assignment['assign_install_end_time'],
        $overdue_timezone
    );

    if ($deadline instanceof DateTimeImmutable) {
        $current_assignment_overdue = $deadline < new DateTimeImmutable('now', $overdue_timezone);
    }
}

$is_read_only = $is_canceled_assignment || $current_setup_status === 4 || $current_assign_status === 5;
$can_change_tech = !$is_read_only && $current_setup_status !== 3;
$can_change_date = !$is_read_only && $current_setup_status !== 3;
$can_change_time = !$is_read_only;
$show_unavailable_tech_warning = $current_assignment && $current_tech_unavailable && !$is_read_only;
$unavailable_tech_name = $show_unavailable_tech_warning
    ? ($current_assignment['tech_name'] ?: '-')
    : '';
$unavailable_tech_reason = 'ถูกตั้งสถานะเป็นไม่พร้อมรับงาน';

$selected_setup_json = json_encode($selected_setup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$current_assignment_json = json_encode($current_assignment ?: null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$technicians_json = json_encode($technicians_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$tech_schedules_json = json_encode($tech_schedules_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$current_tech_json = json_encode($current_tech_id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$current_install_date_json = json_encode($current_install_date, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$current_install_time_json = json_encode($current_install_time, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$current_install_end_time_json = json_encode($current_install_end_time, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$current_tech_unavailable_json = json_encode($current_tech_unavailable, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$assignment_permissions_json = json_encode([
    'readOnly' => $is_read_only,
    'canChangeTech' => $can_change_tech,
    'canChangeDate' => $can_change_date,
    'canChangeTime' => $can_change_time,
    'requiresTechChangeConfirmation' => $current_assign_status === 2,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$today_bangkok_json = json_encode($today_bangkok, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

layout_header('มอบหมายงานช่าง', 'assignments');
?>

<div class="manager-assign-v2 manager-assign-er-page">
    <?php if ($show_unavailable_tech_warning): ?>
        <section class="manager-unavailable-tech-warning" aria-label="คำเตือนช่างไม่พร้อมรับงาน">
            <div class="manager-unavailable-warning-icon" aria-hidden="true">*</div>
            <div class="manager-unavailable-warning-copy">
                <strong>คำเตือน *</strong>
                <span>
                    ช่างคนนี้: <?= h($unavailable_tech_name) ?>
                    ไม่สามารถรับงานใหม่ได้ เนื่องจาก <?= h($unavailable_tech_reason) ?>
                    หากต้องการเปลี่ยนช่างคนใหม่ กรุณากดปุ่ม "เปลี่ยนช่าง"
                </span>
            </div>
            <a class="manager-unavailable-warning-action" href="#techPanel">
                เปลี่ยนช่าง
            </a>
        </section>
    <?php endif; ?>

    <div class="assignment-sale-header manager-assignment-page-header">
        <div class="admin-dashboard-top">
            <div>
                <h1><?= $current_assignment ? 'แก้ไขการมอบหมายงาน' : 'มอบหมายงานช่าง' ?></h1>
                <p><?= $is_canceled_assignment ? 'งานนี้ถูกยกเลิกแล้ว ดูประวัติได้อย่างเดียว' : ($is_read_only ? 'งานเสร็จสิ้นแล้ว ดูรายละเอียดได้อย่างเดียว' : ($current_assignment ? 'ตรวจสอบและปรับข้อมูลการมอบหมายงาน' : 'ตรวจสอบข้อมูลใบงาน แล้วเลือกช่าง วัน และเวลาติดตั้ง')) ?></p>
            </div>

            <div class="admin-dashboard-actions">
                <div class="admin-date-pill">
                    <i class="fa-regular fa-calendar"></i>
                    <?= h(date('d/m/Y')) ?>
                </div>
            </div>
        </div>
    </div>

    <?= flash_message() ?>

    <?php $page_status = trim($_GET['status'] ?? ''); ?>
    <?php if ($page_status === 'time_passed'): ?>
        <div class="manager-page-alert danger">
            ช่วงเวลาที่เลือกผ่านไปแล้ว กรุณาเลือกช่วงเวลาใหม่
        </div>
    <?php elseif ($page_status === 'holiday'): ?>
        <div class="manager-page-alert warning">
            วันเสาร์และวันอาทิตย์เป็นวันหยุด กรุณาเลือกวันทำงาน
        </div>
    <?php elseif ($page_status === 'canceled_readonly'): ?>
        <div class="manager-page-alert warning">
            งานนี้ถูกยกเลิกแล้ว จึงไม่สามารถแก้ไขการมอบหมายได้
        </div>
    <?php endif; ?>

    <?php if ($current_assignment_overdue): ?>
        <div class="manager-page-alert warning manager-overdue-action-alert">
            <div class="manager-overdue-alert-icon">!</div>
            <div class="manager-overdue-alert-copy">
                <strong>งานนี้เกินกำหนดแล้ว</strong>
                <span>กรุณาตรวจสอบการมอบหมาย และเลือกช่าง วัน หรือเวลาใหม่ก่อนบันทึกการแก้ไข</span>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= h(app_system_url('manager/assignments.php')) ?>" onsubmit="return beforeAssignSubmit()">
        <input type="hidden" id="setup_id" name="setup_id" value="<?= h($selected_setup['setup_id']) ?>" required>
        <input type="hidden" id="tech_id" name="tech_id" value="<?= h($current_tech_id) ?>" required>
        <input type="hidden" id="assign_install_date" name="assign_install_date" value="<?= h($current_install_date) ?>" required>
        <input type="hidden" id="assign_time_slot" name="assign_time_slot" value="" required>
        <input type="hidden" id="confirm_tech_change" name="confirm_tech_change" value="0">

        <div class="manager-assignment-step-one">
        <section class="manager-assign-panel manager-selected-setup-panel always-show">
            <div class="manager-work-summary-head">
                <div>
                    <h2>สรุปใบงาน</h2>
                    <p class="manager-work-code">
                        รหัสใบงาน
                        <strong><?= h($selected_setup['setup_id']) ?></strong>
                    </p>
                    <p class="manager-work-subtitle">ตรวจสอบรายละเอียดใบงานก่อนดำเนินการมอบหมาย</p>
                </div>

                <a
                    class="manager-soft-link"
                    href="<?= h(app_system_url('sale/setup_slip.php?id=' . urlencode($selected_setup['setup_id']) . '&from=manager')) ?>"
                >
                    <?= manager_icon_svg('eye') ?>
                    ดูใบติดตั้ง
                </a>
            </div>

            <div class="manager-work-customer">
                <div class="manager-work-customer-icon">
                    <i class="fa-regular fa-user"></i>
                </div>

                <div class="manager-work-customer-main">
                    <span>ลูกค้า</span>
                    <strong><?= h($selected_setup['customer_display']) ?></strong>

                    <div class="manager-work-contact-list">
                        <div>
                            <span>เบอร์โทร</span>
                            <strong><?= h($selected_setup['user_phone'] ?: '--') ?></strong>
                        </div>

                        <div>
                            <span>อีเมล</span>
                            <strong><?= h($selected_setup['user_email'] ?: '--') ?></strong>
                        </div>

                        <div>
                            <span>ที่อยู่ลูกค้า</span>
                            <strong><?= h($selected_setup['user_address'] ?: '--') ?></strong>
                        </div>
                    </div>
                </div>

                <div class="manager-work-customer-id">
                    <span>รหัสลูกค้า</span>
                    <strong><?= h($selected_setup['user_id'] ?: '--') ?></strong>
                </div>
            </div>

            <div class="manager-work-detail-box">
                <div class="manager-work-meta">
                    <div>
                        <span>วันที่สร้างใบงาน</span>
                        <strong><?= h(date('d/m/Y H:i', strtotime($selected_setup['created_at']))) ?></strong>
                    </div>

                    <div>
                        <span>จำนวนสินค้า</span>
                        <strong><?= h((string) ((int) ($selected_setup['item_count'] ?? 0))) ?> รายการ</strong>
                    </div>

                    <div class="wide">
                        <span>ที่อยู่ติดตั้ง</span>
                        <strong><?= h($selected_setup['setup_address_display']) ?></strong>
                    </div>
                </div>

                <div class="manager-work-product-table">
                    <div class="manager-work-product-head">
                        <span>สินค้า</span>
                        <span>จำนวน</span>
                        <span>ค่าติดตั้ง/หน่วย</span>
                        <span>รวม</span>
                    </div>

                    <?php foreach ($product_items as $product_item): ?>
                        <div class="manager-work-product-row">
                            <strong><?= h($product_item['pro_name'] ?? '--') ?></strong>

                            <span>
                                <?= h((string) ($product_item['install_qty'] ?? 0)) ?> ชิ้น
                            </span>

                            <span>
                                <?= h(number_format((float) ($product_item['install_price'] ?? 0), 2)) ?> บาท
                            </span>

                            <strong>
                                <?= h(number_format((float) ($product_item['install_total'] ?? 0), 2)) ?> บาท
                            </strong>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="manager-work-bottom">
                    <div>
                        <span>หมายเหตุ</span>
                        <strong>
                            <?= h(
                                trim((string) ($selected_setup['setup_note'] ?? '')) !== ''
                                    ? $selected_setup['setup_note']
                                    : '--'
                            ) ?>
                        </strong>
                    </div>

                    <div class="manager-work-total">
                        <span>รวมค่าติดตั้ง</span>
                        <strong><?= h($selected_setup['install_total_display']) ?></strong>
                    </div>
                </div>
            </div>

            <?php if (!empty($current_assignment)): ?>
                <div class="install-summary-current">
                    <strong>การมอบหมายปัจจุบัน:</strong>
                    <span>
                        <?= h($current_assignment['tech_name'] ?: '-') ?>
                        · <?= h(manager_thai_date($current_assignment['assign_install_date'] ?? null)) ?>
                        · <?= h(time_label($current_assignment['assign_install_time'] ?? '', $current_assignment['assign_install_end_time'] ?? '')) ?>
                        · <?= h(assign_status_name($current_assignment['assign_status'] ?? '')) ?>
                    </span>

                    <?php if ($current_assignment_overdue): ?>
                        <span class="manager-overdue-badge">เกินกำหนด</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!$is_canceled_assignment): ?>
        <section class="manager-assign-panel manager-tech-panel show" id="techPanel">
            <div class="manager-panel-head">
                <div>
                    <h2>เลือกช่างติดตั้ง</h2>
                    <p><?= $is_read_only ? 'งานเสร็จสิ้นแล้ว จึงไม่สามารถเปลี่ยนช่างได้' : 'กรุณาเลือกช่างสำหรับงานติดตั้งนี้' ?></p>
                </div>
                <div class="manager-tech-status-legend" aria-label="สถานะช่าง">
                    <span class="manager-tech-legend-item is-ready">
                        <i class="manager-tech-status-dot" aria-hidden="true"></i>
                        พร้อมรับงาน
                    </span>
                    <span class="manager-tech-legend-item is-unavailable">
                        <i class="manager-tech-status-dot" aria-hidden="true"></i>
                        ไม่พร้อมรับงาน
                    </span>
                </div>
                <span class="manager-soft-badge manager-tech-count-source" id="availableCountText" aria-hidden="true">-</span>
            </div>

            <div class="manager-tech-checker">
                <div class="manager-assign-search">
                    <?= manager_icon_svg('search') ?>
                    <input type="text" id="techSearch" placeholder="ค้นหาช่าง ชื่อ เบอร์โทร หรืออีเมล..." autocomplete="off">
                </div>
            </div>

            <div class="manager-tech-list" id="techList"></div>
        </section>
        </div>

        <section class="manager-assign-panel manager-schedule-panel manager-step-panel" id="schedulePanel">
            <div class="manager-panel-head">
                <div>
                    <h2>เลือกวันติดตั้ง</h2>
                    <p id="selectedTechScheduleText"><?= $is_read_only ? 'งานเสร็จสิ้นแล้ว ดูตารางเวลาได้อย่างเดียว' : ($current_setup_status === 3 ? 'กำลังติดตั้ง: แก้ไขได้เฉพาะเวลาและหมายเหตุ' : 'เลือกช่างก่อน ระบบจะแสดงตารางงานและวันที่เลือกได้') ?></p>
                </div>
                <span class="manager-soft-badge" id="techQueueCountText">ยังไม่ได้เลือกช่าง</span>
            </div>

            <div class="manager-tech-queue-table-wrap" id="techQueueBox">
                <table class="manager-tech-queue-table manager-tech-week-table">
                    <thead>
                        <tr>
                            <th>รหัสใบงาน</th>
                            <th>ลูกค้า</th>
                            <th>จำนวนสินค้า</th>
                            <th>วันที่-เวลาที่ติดตั้ง</th>
                        </tr>
                    </thead>
                    <tbody id="techQueueTableBody">
                        <tr><td colspan="4" class="manager-empty-cell">ยังไม่ได้เลือกช่าง</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="manager-calendar-toolbar">
                <button type="button" data-calendar-month="-1">‹</button>
                <strong id="calendarMonthText">-</strong>
                <button type="button" data-calendar-month="1">›</button>
                <span class="calendar-legend available">ว่าง</span>
                <span class="calendar-legend partial">มีคิว</span>
                <span class="calendar-legend busy">คิวเต็ม</span>
                <span class="calendar-legend holiday">วันหยุด</span>
            </div>

            <div class="manager-calendar" id="installCalendar"></div>
            <div class="manager-selected-date" id="selectedDateBox">ยังไม่ได้เลือกวันที่ติดตั้ง</div>

            <div class="manager-time-panel" id="timePanel">
                <div class="manager-time-panel-head">
                    <div>
                        <h3>เลือกช่วงเวลาติดตั้ง</h3>
                        <!-- <p>เลือกช่วงเวลาว่างได้ 1 ช่วง</p> -->
                    </div>
                    <span class="manager-soft-badge" id="selectedTimeText">ยังไม่ได้เลือกเวลา</span>
                </div>

                <label class="manager-full-day-option" id="fullDayOption">
                    <input type="checkbox" id="fullDayCheckbox">
                    <span>
                        <strong>เหมาทั้งวัน</strong>
                        <small>เลือกทั้ง 3 ช่วงเวลา</small>
                    </span>
                </label>

                <div class="manager-time-slots" id="timeSlots"></div>

                <div class="manager-note-card">
                    <div class="manager-note-head">
                        <div>
                            <label for="setup_note">หมายเหตุเพิ่มเติม</label>
                            <p>ระบุรายละเอียดเพิ่มเติมสำหรับการติดตั้ง (ถ้ามี)</p>
                        </div>
                        <span id="setupNoteCounter">0/500</span>
                    </div>
                    <textarea
                        id="setup_note"
                        name="setup_note"
                        maxlength="500"
                        rows="4"
                        placeholder="กรอกรายละเอียดเพิ่มเติมเกี่ยวกับงานติดตั้ง..."
                        <?= $is_read_only ? 'readonly' : '' ?>
                    ><?= h($selected_setup['setup_note'] ?? '') ?></textarea>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <div class="manager-form-actions sticky-actions">
            <a class="manager-action-btn" href="<?= h(app_system_url('manager/assignment_list.php')) ?>">
                <?= manager_icon_svg('back') ?>
                กลับรายการใบงาน
            </a>
            <?php if (!$is_read_only): ?>
                <button class="manager-action-btn primary" id="saveAssignmentButton" type="submit" disabled>
                    <?= manager_icon_svg('check') ?>
                    <?= $current_assignment ? 'บันทึกการแก้ไข' : 'บันทึกการมอบหมาย' ?>
                </button>
            <?php endif; ?>
        </div>
    </form>
</div>


<link rel="stylesheet" href="<?= h(app_asset_url('manager/assets/css/manager.css')) ?>?v=<?= h(asset_version('manager/assets/css/manager.css')) ?>">
<link rel="stylesheet" href="<?= h(app_asset_url('manager/assets/css/assignments.css')) ?>?v=<?= h(asset_version('manager/assets/css/assignments.css')) ?>">

<?php if (!$is_canceled_assignment): ?>
<script>
window.assignmentPageData = {
  selectedSetup: <?= $selected_setup_json ?: '{}' ?>,
  currentAssignment: <?= $current_assignment_json ?: 'null' ?>,
  technicians: <?= $technicians_json ?: '[]' ?>,
  techSchedules: <?= $tech_schedules_json ?: '[]' ?>,
  initialTechId: <?= $current_tech_json ?: "''" ?>,
  initialInstallDate: <?= $current_install_date_json ?: "''" ?>,
  initialInstallTime: <?= $current_install_time_json ?: "''" ?>,
  initialInstallEndTime: <?= $current_install_end_time_json ?: "''" ?>,
  assignmentPermissions: <?= $assignment_permissions_json ?: '{}' ?>,
  serverToday: <?= $today_bangkok_json ?: "''" ?>
};
</script>
<script src="<?= h(app_asset_url('manager/assets/js/assignments.js')) ?>?v=<?= h(asset_version('manager/assets/js/assignments.js')) ?>"></script>
<script>
(function () {
    const techInput = document.getElementById('tech_id');
    const techList = document.getElementById('techList');
    const schedulePanel = document.getElementById('schedulePanel');

    if (!techInput || !schedulePanel) return;

    let lastTechId = String(techInput.value || '');

    function syncAssignmentStep(shouldScroll) {
        const currentTechId = String(techInput.value || '').trim();
        const hasTech = currentTechId !== '';

        schedulePanel.classList.toggle('is-step-visible', hasTech);
        schedulePanel.setAttribute('aria-hidden', hasTech ? 'false' : 'true');

        if (hasTech && shouldScroll) {
            window.setTimeout(function () {
                schedulePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 180);
        }

        lastTechId = currentTechId;
    }

    // Existing assignment: show date/time immediately. New assignment: keep step 2 hidden.
    syncAssignmentStep(false);

    if (techList) {
        techList.addEventListener('click', function (event) {
            const selectButton = event.target.closest('button, a');
            if (!selectButton) return;

            window.setTimeout(function () {
                const newTechId = String(techInput.value || '').trim();
                syncAssignmentStep(newTechId !== '' && newTechId !== lastTechId);
            }, 80);
        });
    }

    techInput.addEventListener('change', function () {
        syncAssignmentStep(true);
    });

    // assignments.js changes the hidden input value as a property, which does not always fire "change".
    // A lightweight watcher keeps the step UI in sync without altering the original assignment logic.
    window.setInterval(function () {
        const currentTechId = String(techInput.value || '').trim();
        if (currentTechId !== lastTechId) {
            syncAssignmentStep(currentTechId !== '');
        }
    }, 250);
})();
</script>
<?php endif; ?>

<?php layout_footer(); ?>
