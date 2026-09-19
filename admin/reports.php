<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

require_login('3');

function admin_report_prepare(mysqli $conn, string $sql, string $types = '', array $params = []): ?mysqli_stmt
{
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt || strlen($types) !== count($params)) {
            return null;
        }

        if ($types !== '') {
            $bind = [$types];
            foreach ($params as $index => $param) {
                $bind[] = &$params[$index];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }

        $stmt->execute();

        return $stmt;
    } catch (Throwable $e) {
        if (isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
        }

        return null;
    }
}

function admin_report_count(mysqli $conn, string $sql, string $types = '', array $params = []): int
{
    $stmt = admin_report_prepare($conn, $sql, $types, $params);
    if (!$stmt) {
        return 0;
    }

    try {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $result->free();
        $stmt->close();

        return max(0, (int) ($row['total'] ?? 0));
    } catch (Throwable $e) {
        $stmt->close();
        return 0;
    }
}

function admin_report_sum(mysqli $conn, string $sql, string $types = '', array $params = []): float
{
    $stmt = admin_report_prepare($conn, $sql, $types, $params);
    if (!$stmt) {
        return 0.0;
    }

    try {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $result->free();
        $stmt->close();

        return (float) ($row['total'] ?? 0);
    } catch (Throwable $e) {
        $stmt->close();
        return 0.0;
    }
}

function admin_report_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $rows = [];
    $stmt = admin_report_prepare($conn, $sql, $types, $params);
    if (!$stmt) {
        return $rows;
    }

    try {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $result->free();
        $stmt->close();
    } catch (Throwable $e) {
        $stmt->close();
        return [];
    }

    return $rows;
}

function admin_report_grouped_counts(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $counts = [];

    foreach (admin_report_rows($conn, $sql, $types, $params) as $row) {
        $status = (string) ($row['status'] ?? '');
        $counts[$status] = (int) ($row['total'] ?? 0);
    }

    return $counts;
}

function admin_report_valid_date(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return '';
    }

    return $date->format('Y-m-d') === $value ? $value : '';
}

function admin_report_url(string $report, string $startDate = '', string $endDate = ''): string
{
    $params = ['report' => $report];
    if ($startDate !== '') {
        $params['start_date'] = $startDate;
    }
    if ($endDate !== '') {
        $params['end_date'] = $endDate;
    }

    return app_system_url('admin/reports.php?' . http_build_query($params));
}

$report_categories = [
    'overview' => 'ภาพรวมระบบ',
    'users' => 'ผู้ใช้งาน',
    'installations' => 'ใบงานติดตั้ง',
    'sales' => 'พนักงานขาย',
    'technicians' => 'ช่างติดตั้ง',
    'revenue' => 'ค่าติดตั้ง',
];

// Keep the existing report keys valid for bookmarked URLs while presenting
// the four high-level sections in the report navigation.
$report_navigation_categories = [
    'overview' => 'ภาพรวม',
    'installations' => 'งานติดตั้ง',
    'users' => 'บุคลากร',
    'revenue' => 'ค่าติดตั้ง',
];

$report = strtolower(trim((string) ($_GET['report'] ?? 'overview')));
if (!array_key_exists($report, $report_categories)) {
    $report = 'overview';
}

// Keep legacy bookmarks working while using the consolidated Personnel report.
if ($report === 'sales' || $report === 'technicians') {
    $report = 'users';
}

$completed_install_period_options = [
    'day' => 'รายวัน',
    'week' => 'รายสัปดาห์',
    'month' => 'รายเดือน',
];
$completed_install_period = 'day';

$start_date = admin_report_valid_date((string) ($_GET['start_date'] ?? ''));
$end_date = admin_report_valid_date((string) ($_GET['end_date'] ?? ''));
if ($start_date !== '' && $end_date !== '' && $start_date > $end_date) {
    [$start_date, $end_date] = [$end_date, $start_date];
}

$setup_date_conditions = [];
$setup_date_params = [];
$setup_date_types = '';
if ($start_date !== '') {
    $setup_date_conditions[] = 's.created_at >= ?';
    $setup_date_params[] = $start_date . ' 00:00:00';
    $setup_date_types .= 's';
}
if ($end_date !== '') {
    $setup_date_conditions[] = 's.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $setup_date_params[] = $end_date;
    $setup_date_types .= 's';
}
$setup_date_where = $setup_date_conditions === [] ? '1=1' : implode(' AND ', $setup_date_conditions);

$completed_report_timezone = new DateTimeZone('Asia/Bangkok');
$completed_report_now = new DateTimeImmutable('now', $completed_report_timezone);
$completed_report_today = $completed_report_now->format('Y-m-d');
$completed_report_today_display = $completed_report_now->format('d/m/Y');
$completed_report_year = $completed_report_now->format('Y');
$completed_report_year_start = $completed_report_now->setDate((int) $completed_report_year, 1, 1)->setTime(0, 0, 0);
$completed_report_next_year_start = $completed_report_year_start->modify('+1 year');
$completed_report_week_start = $completed_report_now->modify('monday this week')->setTime(0, 0, 0);

/* completed_at is written when Manager confirms the installation result. */
$completed_setup_rows = [];
if ($report === 'overview') {
    $completed_setup_rows = admin_report_rows(
        $conn,
        "SELECT s.setup_id, s.completed_at,
            HOUR(s.completed_at) AS completed_hour,
            DATE(s.completed_at) AS completed_date
     FROM setup s
     WHERE s.setup_status = 4
       AND s.completed_at IS NOT NULL
     ORDER BY s.completed_at ASC, s.setup_id ASC",
        '',
        []
    );
}

$completed_day_counts = [];
$completed_week_counts = array_fill(0, 7, 0);
$completed_month_counts = array_fill(0, 12, 0);
$completed_week_counts_by_start = [];
$completed_month_counts_by_year = [];
foreach ($completed_setup_rows as $completed_row) {
    $completed_date = trim((string) ($completed_row['completed_date'] ?? ''));
    $completed_date_object = DateTimeImmutable::createFromFormat('!Y-m-d', $completed_date, $completed_report_timezone);
    if (!$completed_date_object) {
        continue;
    }

    $completed_date_key = $completed_date_object->format('Y-m-d');
    if (!isset($completed_day_counts[$completed_date_key])) {
        $completed_day_counts[$completed_date_key] = [0, 0, 0];
    }

    $completed_hour = (int) ($completed_row['completed_hour'] ?? 0);
    $completed_day_slot = $completed_hour < 12 ? 0 : ($completed_hour < 17 ? 1 : 2);
    $completed_day_counts[$completed_date_key][$completed_day_slot]++;

    $completed_week_start_object = $completed_date_object->modify('monday this week')->setTime(0, 0, 0);
    $completed_week_key = $completed_week_start_object->format('Y-m-d');
    if (!isset($completed_week_counts_by_start[$completed_week_key])) {
        $completed_week_counts_by_start[$completed_week_key] = array_fill(0, 7, 0);
    }
    $completed_week_offset = (int) $completed_week_start_object->diff($completed_date_object)->format('%r%a');
    if ($completed_week_offset >= 0 && $completed_week_offset < 7) {
        $completed_week_counts_by_start[$completed_week_key][$completed_week_offset]++;
        if ($completed_week_key === $completed_report_week_start->format('Y-m-d')) {
            $completed_week_counts[$completed_week_offset]++;
        }
    }

    $completed_year_key = $completed_date_object->format('Y');
    if (!isset($completed_month_counts_by_year[$completed_year_key])) {
        $completed_month_counts_by_year[$completed_year_key] = array_fill(0, 12, 0);
    }
    $completed_month_index = (int) $completed_date_object->format('n') - 1;
    $completed_month_counts_by_year[$completed_year_key][$completed_month_index]++;
    if ($completed_year_key === $completed_report_year) {
        $completed_month_counts[$completed_month_index]++;
    }
}

$completed_report_min_date = $completed_day_counts === []
    ? $completed_report_year_start->format('Y-m-d')
    : min(array_keys($completed_day_counts));
$completed_report_min_week_start = $completed_week_counts_by_start === []
    ? $completed_report_week_start->format('Y-m-d')
    : min(array_keys($completed_week_counts_by_start));
$completed_report_min_month = $completed_month_counts_by_year === []
    ? $completed_report_year . '-01'
    : min(array_keys($completed_month_counts_by_year)) . '-01';
$completed_report_min_week_input = DateTimeImmutable::createFromFormat('!Y-m-d', $completed_report_min_week_start, $completed_report_timezone)->format('o-\\WW');
$completed_report_current_week_input = $completed_report_week_start->format('o-\\WW');
$completed_report_min_month_input = substr($completed_report_min_month, 0, 7);
$completed_report_current_month_input = $completed_report_now->format('Y-m');

$completed_install_period_data = [
    'day' => [
        'date' => $completed_report_today,
        'minDate' => $completed_report_min_date,
        'maxDate' => $completed_report_today,
        'labels' => ['เช้า', 'บ่าย', 'เย็น'],
        'values' => $completed_day_counts[$completed_report_today] ?? [0, 0, 0],
        'dates' => $completed_day_counts,
    ],
    'week' => [
        'labels' => ['จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส', 'อา'],
        'values' => $completed_week_counts,
        'weekStart' => $completed_report_week_start->format('Y-m-d'),
        'weeks' => $completed_week_counts_by_start,
        'minWeek' => $completed_report_min_week_start,
        'maxWeek' => $completed_report_week_start->format('Y-m-d'),
    ],
    'month' => [
        'labels' => ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'],
        'values' => $completed_month_counts,
        'year' => $completed_report_year,
        'years' => $completed_month_counts_by_year,
        'minMonth' => $completed_report_min_month,
        'maxMonth' => $completed_report_current_month_input,
    ],
];
$report_period_data = [
    'sessionKey' => hash('sha256', session_id() . '|' . (string) ($_SESSION['user_id'] ?? '')),
    'completed' => $completed_install_period_data,
];

$assignment_date_conditions = [];
$assignment_date_params = [];
$assignment_date_types = '';
if ($start_date !== '') {
    $assignment_date_conditions[] = 'a.assign_date >= ?';
    $assignment_date_params[] = $start_date . ' 00:00:00';
    $assignment_date_types .= 's';
}
if ($end_date !== '') {
    $assignment_date_conditions[] = 'a.assign_date < DATE_ADD(?, INTERVAL 1 DAY)';
    $assignment_date_params[] = $end_date;
    $assignment_date_types .= 's';
}
$assignment_date_where = $assignment_date_conditions === [] ? '1=1' : implode(' AND ', $assignment_date_conditions);

$total_users = admin_report_count(
    $conn,
    "SELECT COUNT(*) AS total FROM `user` WHERE user_role IN (1, 2, 3)"
);
$total_sales = admin_report_count($conn, 'SELECT COUNT(*) AS total FROM `user` WHERE user_role = 2');
$total_technicians = admin_report_count($conn, 'SELECT COUNT(*) AS total FROM technicians');
$total_customers = admin_report_count($conn, 'SELECT COUNT(*) AS total FROM customers');
$total_product_types = admin_report_count($conn, 'SELECT COUNT(*) AS total FROM product_type');
$total_products = admin_report_count($conn, 'SELECT COUNT(*) AS total FROM product');

$total_setups = admin_report_count(
    $conn,
    "SELECT COUNT(*) AS total FROM setup s WHERE {$setup_date_where}",
    $setup_date_types,
    $setup_date_params
);
$total_install_revenue = admin_report_sum(
    $conn,
    "SELECT COALESCE(SUM(COALESCE(d.install_total, 0)), 0) AS total
     FROM install_detail d
     INNER JOIN setup s ON s.setup_id = d.setup_id
     WHERE {$setup_date_where}",
    $setup_date_types,
    $setup_date_params
);
$total_setups_all = admin_report_count($conn, 'SELECT COUNT(*) AS total FROM setup');
$total_install_revenue_all = admin_report_sum(
    $conn,
    'SELECT COALESCE(SUM(COALESCE(install_total, 0)), 0) AS total FROM install_detail'
);

$setup_status_counts = admin_report_grouped_counts(
    $conn,
    "SELECT CAST(s.setup_status AS CHAR) AS status, COUNT(DISTINCT s.setup_id) AS total
     FROM setup s
     LEFT JOIN assignment a
       ON a.setup_id = s.setup_id
      AND a.assign_id = (
          SELECT a2.assign_id
          FROM assignment a2
          WHERE a2.setup_id = s.setup_id
          ORDER BY a2.assign_date DESC, a2.assign_id DESC
          LIMIT 1
      )
     WHERE {$setup_date_where}
       AND (a.assign_status IS NULL OR a.assign_status NOT IN (4, 5))
     GROUP BY s.setup_status",
    $setup_date_types,
    $setup_date_params
);

if ($setup_status_counts === []) {
    $setup_status_counts = admin_report_grouped_counts(
        $conn,
        "SELECT CAST(s.setup_status AS CHAR) AS status, COUNT(*) AS total
         FROM setup s
         WHERE {$setup_date_where}
         GROUP BY s.setup_status",
        $setup_date_types,
        $setup_date_params
    );
}

$report_table_rows = admin_report_rows(
    $conn,
    "SELECT
        CASE
            WHEN s.setup_status = 5 OR a.assign_status = 4 THEN 'cancelled'
            WHEN s.setup_status = 4 OR a.assign_status = 5 THEN 'done'
            WHEN s.setup_status = 3 THEN 'working'
            WHEN s.setup_status = 2 THEN 'accepted'
            WHEN s.setup_status = 1 OR a.assign_status = 1 THEN 'assigned'
            ELSE 'unassigned'
        END AS report_status,
        COUNT(DISTINCT s.setup_id) AS total,
        COALESCE(SUM(COALESCE(detail_summary.install_total, 0)), 0) AS revenue
     FROM setup s
     LEFT JOIN assignment a
       ON a.setup_id = s.setup_id
      AND a.assign_id = (
          SELECT a2.assign_id
          FROM assignment a2
          WHERE a2.setup_id = s.setup_id
          ORDER BY a2.assign_date DESC, a2.assign_id DESC
          LIMIT 1
      )
     LEFT JOIN (
         SELECT setup_id, SUM(COALESCE(install_total, 0)) AS install_total
         FROM install_detail
         GROUP BY setup_id
     ) detail_summary ON detail_summary.setup_id = s.setup_id
     WHERE {$setup_date_where}
     GROUP BY report_status
     ORDER BY FIELD(report_status, 'unassigned', 'assigned', 'accepted', 'working', 'done', 'cancelled')",
    $setup_date_types,
    $setup_date_params
);

$status_meta = [
    'unassigned' => ['label' => 'ยังไม่ได้มอบหมาย', 'tone' => 'amber', 'icon' => 'fa-inbox'],
    'assigned' => ['label' => 'รอช่างรับงาน', 'tone' => 'blue', 'icon' => 'fa-clipboard-list'],
    'accepted' => ['label' => 'ช่างรับงานแล้ว', 'tone' => 'cyan', 'icon' => 'fa-hand'],
    'working' => ['label' => 'กำลังติดตั้ง', 'tone' => 'violet', 'icon' => 'fa-screwdriver-wrench'],
    'done' => ['label' => 'เสร็จสิ้น', 'tone' => 'green', 'icon' => 'fa-circle-check'],
    'cancelled' => ['label' => 'ยกเลิกแล้ว', 'tone' => 'red', 'icon' => 'fa-ban'],
];

$table_by_status = [];
foreach ($report_table_rows as $row) {
    $key = (string) ($row['report_status'] ?? '');
    if (isset($status_meta[$key])) {
        $table_by_status[$key] = [
            'total' => (int) ($row['total'] ?? 0),
            'revenue' => (float) ($row['revenue'] ?? 0),
        ];
    }
}

foreach (array_keys($status_meta) as $key) {
    if (!isset($table_by_status[$key])) {
        $table_by_status[$key] = ['total' => 0, 'revenue' => 0.0];
    }
}

/* Match the status badge palette used by Manager assignment history. */
$installation_status_meta = [
    'unassigned' => ['label' => 'ยังไม่ได้มอบหมาย', 'tone' => 'amber', 'color' => '#94a3b8'],
    'assigned' => ['label' => 'รอช่างรับงาน', 'tone' => 'blue', 'color' => '#a78bfa'],
    'accepted' => ['label' => 'ช่างรับงานแล้ว', 'tone' => 'cyan', 'color' => '#38bdf8'],
    'received' => ['label' => 'ยืนยันรับสินค้าแล้ว', 'tone' => 'yellow', 'color' => '#60a5fa'],
    'working' => ['label' => 'กำลังติดตั้ง', 'tone' => 'violet', 'color' => '#818cf8'],
    'review' => ['label' => 'รอหัวหน้าช่างยืนยัน', 'tone' => 'orange', 'color' => '#fb923c'],
    'done' => ['label' => 'เสร็จสิ้น', 'tone' => 'green', 'color' => '#34d399'],
    'rejected' => ['label' => 'ช่างปฏิเสธงาน', 'tone' => 'orange', 'color' => '#f97316'],
    'cancelled' => ['label' => 'ยกเลิกแล้ว', 'tone' => 'red', 'color' => '#f87171'],
];
$installation_status_rows = admin_report_rows(
    $conn,
    "SELECT
        CASE
            WHEN s.setup_status = 5 OR a.assign_status = 4 THEN 'cancelled'
            WHEN s.setup_status = 4 OR a.assign_status = 5 THEN 'done'
            WHEN a.assign_status = 3 THEN 'rejected'
            WHEN s.setup_status = 3 AND EXISTS (
                SELECT 1 FROM installation_result ir WHERE ir.setup_id = s.setup_id
            ) THEN 'review'
            WHEN s.setup_status = 3 THEN 'working'
            WHEN s.setup_status = 2 AND EXISTS (
                SELECT 1
                FROM product_receive pr
                WHERE pr.assign_id = a.assign_id AND pr.receive_status = 1
            ) THEN 'received'
            WHEN s.setup_status = 2 THEN 'accepted'
            WHEN s.setup_status = 1 OR a.assign_status = 1 THEN 'assigned'
            ELSE 'unassigned'
        END AS report_status,
        COUNT(DISTINCT s.setup_id) AS total
     FROM setup s
     LEFT JOIN assignment a
       ON a.setup_id = s.setup_id
      AND a.assign_id = (
          SELECT a2.assign_id
          FROM assignment a2
          WHERE a2.setup_id = s.setup_id
          ORDER BY a2.assign_date DESC, a2.assign_id DESC
          LIMIT 1
      )
     WHERE {$setup_date_where}
     GROUP BY report_status
     ORDER BY FIELD(report_status, 'unassigned', 'assigned', 'accepted', 'received', 'working', 'review', 'done', 'rejected', 'cancelled')",
    $setup_date_types,
    $setup_date_params
);
$installation_status_raw_counts = [];
$installation_status_by_key = [];
foreach ($installation_status_rows as $row) {
    $status_key = (string) ($row['report_status'] ?? '');
    $installation_status_raw_counts[$status_key] = ($installation_status_raw_counts[$status_key] ?? 0)
        + (int) ($row['total'] ?? 0);
    if (isset($installation_status_meta[$status_key])) {
        $installation_status_by_key[$status_key] = ($installation_status_by_key[$status_key] ?? 0)
            + (int) ($row['total'] ?? 0);
    }
}
foreach (array_keys($installation_status_meta) as $status_key) {
    $installation_status_by_key[$status_key] = $installation_status_by_key[$status_key] ?? 0;
}

$installation_report_total = array_sum($installation_status_by_key);
$installation_donut_stops = [];
$installation_donut_labels = [];
$installation_donut_cursor = 0.0;
if ($installation_report_total > 0) {
    foreach ($installation_status_meta as $status_key => $status_item) {
        $status_total = $installation_status_by_key[$status_key];
        $status_share = ($status_total / $installation_report_total) * 100;
        $status_next = $installation_donut_cursor + $status_share;
        $installation_donut_stops[] = $status_item['color'] . ' ' . number_format($installation_donut_cursor, 2, '.', '') . '% ' . number_format($status_next, 2, '.', '') . '%';

        if ($status_total > 0 && $status_share >= 8) {
            $status_midpoint = $installation_donut_cursor + ($status_share / 2);
            $status_angle = deg2rad(($status_midpoint / 100) * 360 - 90);
            $installation_donut_labels[] = [
                'key' => $status_key,
                'label' => $status_item['label'],
                'total' => $status_total,
                'x' => round(cos($status_angle) * 40, 2),
                'y' => round(sin($status_angle) * 40, 2),
            ];
        }

        $installation_donut_cursor = $status_next;
    }
}
$installation_donut_gradient = $installation_report_total > 0
    ? implode(', ', $installation_donut_stops)
    : '#edf2f7 0 100%';

$completed_setup_count = $table_by_status['done']['total'];
$cancelled_setup_count = $table_by_status['cancelled']['total'];
$ongoing_setup_count = $table_by_status['assigned']['total']
    + $table_by_status['accepted']['total']
    + $table_by_status['working']['total'];
$report_total = array_sum(array_column($table_by_status, 'total'));
$report_completion_rate = $report_total > 0 ? round(($completed_setup_count / $report_total) * 100, 1) : 0;
$report_average_install = $report_total > 0 ? $total_install_revenue / $report_total : 0;

$period_label = 'ทุกช่วงเวลา';
if ($start_date !== '' && $end_date !== '') {
    $period_label = $start_date . ' – ' . $end_date;
} elseif ($start_date !== '') {
    $period_label = 'ตั้งแต่ ' . $start_date;
} elseif ($end_date !== '') {
    $period_label = 'ถึง ' . $end_date;
}

$report_metrics = [];
$sales_report_rows = [];
$technicians_report_rows = [];

if ($report === 'users') {
    $sales_report_rows = admin_report_rows(
        $conn,
        "SELECT
            u.user_id AS seller_id,
            u.user_name AS seller_name,
            COUNT(DISTINCT s.setup_id) AS setup_total,
            COALESCE(SUM(CASE WHEN s.setup_id IS NOT NULL AND (s.setup_status = 4 OR a.assign_status = 5) THEN 1 ELSE 0 END), 0) AS done_total,
            COALESCE(SUM(CASE WHEN s.setup_id IS NOT NULL AND (s.setup_status = 5 OR a.assign_status = 4) THEN 1 ELSE 0 END), 0) AS cancelled_total,
            COALESCE(SUM(COALESCE(detail_summary.install_total, 0)), 0) AS revenue_total
         FROM `user` u
         LEFT JOIN setup s
           ON s.sale_id = u.user_id
          AND {$setup_date_where}
         LEFT JOIN assignment a
           ON a.setup_id = s.setup_id
          AND a.assign_id = (
              SELECT a2.assign_id
              FROM assignment a2
              WHERE a2.setup_id = s.setup_id
              ORDER BY a2.assign_date DESC, a2.assign_id DESC
              LIMIT 1
          )
         LEFT JOIN (
             SELECT setup_id, SUM(COALESCE(install_total, 0)) AS install_total
             FROM install_detail
             GROUP BY setup_id
         ) detail_summary ON detail_summary.setup_id = s.setup_id
         WHERE u.user_role = 2
         GROUP BY u.user_id, u.user_name
         ORDER BY setup_total DESC, u.user_name",
        $setup_date_types,
        $setup_date_params
    );

    $technicians_report_rows = admin_report_rows(
        $conn,
        "SELECT
            t.tech_id,
            COALESCE(NULLIF(t.tech_fullname, ''), NULLIF(t.tech_name, ''), '-') AS tech_display_name,
            COUNT(a.assign_id) AS assigned_total,
            COALESCE(SUM(CASE WHEN a.assign_status = 2 THEN 1 ELSE 0 END), 0) AS accepted_total,
            COALESCE(SUM(CASE WHEN a.assign_status = 5 OR s.setup_status = 4 THEN 1 ELSE 0 END), 0) AS done_total,
            COALESCE(SUM(CASE WHEN a.assign_status = 3 THEN 1 ELSE 0 END), 0) AS rejected_total
         FROM technicians t
         LEFT JOIN assignment a
           ON a.tech_id = t.tech_id
          AND a.assign_id = (
              SELECT a2.assign_id
              FROM assignment a2
              WHERE a2.setup_id = a.setup_id
              ORDER BY a2.assign_date DESC, a2.assign_id DESC
              LIMIT 1
          )
          AND {$assignment_date_where}
         LEFT JOIN setup s ON s.setup_id = a.setup_id
         GROUP BY t.tech_id, t.tech_fullname, t.tech_name
         ORDER BY assigned_total DESC, tech_display_name",
        $assignment_date_types,
        $assignment_date_params
    );

    $sales_chart_max = 1;
    foreach ($sales_report_rows as $sales_row) {
        $sales_chart_max = max(
            $sales_chart_max,
            (int) ($sales_row['setup_total'] ?? 0),
            (int) ($sales_row['done_total'] ?? 0),
            (int) ($sales_row['cancelled_total'] ?? 0)
        );
    }
    $technicians_chart_max = 1;
    foreach ($technicians_report_rows as $technician_row) {
        $technicians_chart_max = max(
            $technicians_chart_max,
            (int) ($technician_row['assigned_total'] ?? 0),
            (int) ($technician_row['done_total'] ?? 0),
            (int) ($technician_row['rejected_total'] ?? 0)
        );
    }
    $sales_created_total = array_sum(array_map(static fn (array $row): int => (int) ($row['setup_total'] ?? 0), $sales_report_rows));
    $sales_done_total = array_sum(array_map(static fn (array $row): int => (int) ($row['done_total'] ?? 0), $sales_report_rows));
    $sales_cancelled_total = array_sum(array_map(static fn (array $row): int => (int) ($row['cancelled_total'] ?? 0), $sales_report_rows));
    $technicians_assigned_total = array_sum(array_map(static fn (array $row): int => (int) ($row['assigned_total'] ?? 0), $technicians_report_rows));
    $technicians_done_total = array_sum(array_map(static fn (array $row): int => (int) ($row['done_total'] ?? 0), $technicians_report_rows));
    $technicians_rejected_total = array_sum(array_map(static fn (array $row): int => (int) ($row['rejected_total'] ?? 0), $technicians_report_rows));
}

if ($report === 'installations') {
    $installation_workflow_status_meta = [
        'unassigned' => ['label' => 'ยังไม่ได้มอบหมาย', 'tone' => 'amber', 'icon' => 'fa-inbox'],
        'assigned' => ['label' => 'รอช่างรับงาน', 'tone' => 'blue', 'icon' => 'fa-clipboard-list'],
        'accepted' => ['label' => 'ช่างรับงานแล้ว', 'tone' => 'cyan', 'icon' => 'fa-hand'],
        'received' => ['label' => 'ยืนยันรับสินค้าแล้ว', 'tone' => 'yellow', 'icon' => 'fa-box-open'],
        'working' => ['label' => 'กำลังติดตั้ง', 'tone' => 'violet', 'icon' => 'fa-screwdriver-wrench'],
        'review' => ['label' => 'รอหัวหน้าช่างยืนยัน', 'tone' => 'orange', 'icon' => 'fa-clock'],
        'done' => ['label' => 'เสร็จสิ้น', 'tone' => 'green', 'icon' => 'fa-circle-check'],
        'rejected' => ['label' => 'ปฏิเสธงาน', 'tone' => 'orange', 'icon' => 'fa-user-slash'],
        'cancelled' => ['label' => 'ยกเลิกแล้ว', 'tone' => 'red', 'icon' => 'fa-ban'],
    ];
    $installation_workflow_counts = [];
    foreach (array_keys($installation_workflow_status_meta) as $status_key) {
        $installation_workflow_counts[$status_key] = (int) ($installation_status_raw_counts[$status_key] ?? 0);
    }

    $installation_in_progress_count = $installation_workflow_counts['assigned']
        + $installation_workflow_counts['accepted']
        + $installation_workflow_counts['received']
        + $installation_workflow_counts['working']
        + $installation_workflow_counts['review'];

    $installation_year_input = trim((string) ($_GET['install_year'] ?? ''));
    $installation_year = preg_match('/^\d{4}$/', $installation_year_input)
        ? (int) $installation_year_input
        : (int) date('Y');
    $installation_year = max(2000, min(2100, $installation_year));
    $installation_year_start = sprintf('%04d-01-01 00:00:00', $installation_year);
    $installation_next_year_start = sprintf('%04d-01-01 00:00:00', $installation_year + 1);
    $installation_trend_created = array_fill(1, 12, 0);
    $installation_trend_completed = array_fill(1, 12, 0);

    foreach (admin_report_rows(
        $conn,
        'SELECT MONTH(s.created_at) AS month_index, COUNT(DISTINCT s.setup_id) AS total
         FROM setup s
         WHERE s.created_at >= ? AND s.created_at < ?
         GROUP BY MONTH(s.created_at)',
        'ss',
        [$installation_year_start, $installation_next_year_start]
    ) as $row) {
        $month_index = (int) ($row['month_index'] ?? 0);
        if ($month_index >= 1 && $month_index <= 12) {
            $installation_trend_created[$month_index] = (int) ($row['total'] ?? 0);
        }
    }

    foreach (admin_report_rows(
        $conn,
        'SELECT MONTH(s.completed_at) AS month_index, COUNT(DISTINCT s.setup_id) AS total
         FROM setup s
         WHERE s.setup_status = 4
           AND s.completed_at IS NOT NULL
           AND s.completed_at >= ? AND s.completed_at < ?
         GROUP BY MONTH(s.completed_at)',
        'ss',
        [$installation_year_start, $installation_next_year_start]
    ) as $row) {
        $month_index = (int) ($row['month_index'] ?? 0);
        if ($month_index >= 1 && $month_index <= 12) {
            $installation_trend_completed[$month_index] = (int) ($row['total'] ?? 0);
        }
    }

    $installation_trend_labels = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $installation_trend_max = max(1, ...array_values($installation_trend_created), ...array_values($installation_trend_completed));
    $installation_trend_points = ['created' => [], 'completed' => []];
    for ($month = 1; $month <= 12; $month++) {
        $x = 42 + (($month - 1) * 61);
        foreach (['created' => $installation_trend_created[$month], 'completed' => $installation_trend_completed[$month]] as $series_key => $value) {
            $y = 170 - (($value / $installation_trend_max) * 130);
            $installation_trend_points[$series_key][] = [
                'x' => $x,
                'y' => round($y, 2),
                'value' => $value,
            ];
        }
    }
    $installation_trend_point_strings = [];
    foreach ($installation_trend_points as $series_key => $points) {
        $installation_trend_point_strings[$series_key] = implode(' ', array_map(
            static fn (array $point): string => $point['x'] . ',' . $point['y'],
            $points
        ));
    }
    $installation_year_options = range(max(2000, (int) date('Y') - 4), (int) date('Y'));
    if (!in_array($installation_year, $installation_year_options, true)) {
        $installation_year_options[] = $installation_year;
        sort($installation_year_options);
    }

    $installation_detail_rows = admin_report_rows(
        $conn,
        "SELECT
            s.setup_id,
            c.customer_name,
            seller.user_name AS seller_name,
            COALESCE(NULLIF(TRIM(t.tech_fullname), ''), NULLIF(TRIM(t.tech_name), ''), '-') AS technician_name,
            s.created_at,
            a.assign_install_date,
            s.setup_status,
            a.assign_status,
            CASE
                WHEN s.setup_status = 5 OR a.assign_status = 4 THEN 'cancelled'
                WHEN a.assign_status = 3 THEN 'rejected'
                WHEN s.setup_status = 4 OR a.assign_status = 5 THEN 'done'
                WHEN s.setup_status = 3 AND EXISTS (
                    SELECT 1 FROM installation_result ir WHERE ir.setup_id = s.setup_id
                ) THEN 'review'
                WHEN s.setup_status = 3 THEN 'working'
                WHEN s.setup_status = 2 AND EXISTS (
                    SELECT 1 FROM product_receive pr
                    WHERE pr.assign_id = a.assign_id AND pr.receive_status = 1
                ) THEN 'received'
                WHEN s.setup_status = 2 THEN 'accepted'
                WHEN s.setup_status = 1 OR a.assign_status = 1 THEN 'assigned'
                ELSE 'unassigned'
            END AS report_status,
            COALESCE(detail_summary.install_total, 0) AS install_total
         FROM setup s
         LEFT JOIN customers c ON c.customer_id = s.customer_id
         LEFT JOIN `user` seller ON seller.user_id = s.sale_id
         LEFT JOIN assignment a
           ON a.setup_id = s.setup_id
          AND a.assign_id = (
              SELECT a2.assign_id
              FROM assignment a2
              WHERE a2.setup_id = s.setup_id
              ORDER BY a2.assign_date DESC, a2.assign_id DESC
              LIMIT 1
          )
         LEFT JOIN technicians t ON t.tech_id = a.tech_id
         LEFT JOIN (
             SELECT setup_id, SUM(COALESCE(install_total, 0)) AS install_total
             FROM install_detail
             GROUP BY setup_id
         ) detail_summary ON detail_summary.setup_id = s.setup_id
         WHERE {$setup_date_where}
         ORDER BY s.created_at DESC, s.setup_id DESC",
        $setup_date_types,
        $setup_date_params
    );

    $report_metrics = [
        ['label' => 'ใบงานทั้งหมด', 'value' => number_format($total_setups), 'detail' => $period_label, 'tone' => 'blue', 'icon' => 'fa-layer-group'],
        ['label' => 'กำลังดำเนินการ', 'value' => number_format($installation_in_progress_count), 'detail' => 'รวมงานที่ยังไม่จบใน workflow', 'tone' => 'violet', 'icon' => 'fa-bars-progress'],
        ['label' => 'เสร็จสิ้น', 'value' => number_format($installation_workflow_counts['done']), 'detail' => 'ปิดงานแล้ว', 'tone' => 'green', 'icon' => 'fa-circle-check'],
        ['label' => 'ยกเลิกแล้ว', 'value' => number_format($installation_workflow_counts['cancelled']), 'detail' => 'ไม่รวมงานปฏิเสธ', 'tone' => 'red', 'icon' => 'fa-ban'],
    ];
}

if ($report === 'revenue') {
    $fee_year_input = trim((string) ($_GET['fee_year'] ?? ''));
    $fee_year = preg_match('/^\d{4}$/', $fee_year_input) ? (int) $fee_year_input : (int) date('Y');
    $fee_year = max(2000, min(2100, $fee_year));
    $fee_year_start = sprintf('%04d-01-01 00:00:00', $fee_year);
    $fee_next_year_start = sprintf('%04d-01-01 00:00:00', $fee_year + 1);
    $fee_year_options = range(max(2000, (int) date('Y') - 4), (int) date('Y'));
    if (!in_array($fee_year, $fee_year_options, true)) {
        $fee_year_options[] = $fee_year;
        sort($fee_year_options);
    }

    /* Always reduce install_detail to one row per setup before summing fees. */
    $fee_detail_summary_sql = '(SELECT setup_id, SUM(COALESCE(install_total, 0)) AS install_total
                                FROM install_detail
                                GROUP BY setup_id)';
    $fee_setup_rows = admin_report_rows(
        $conn,
        "SELECT s.setup_id, s.setup_status, s.completed_at,
                COALESCE(detail_summary.install_total, 0) AS install_total
         FROM setup s
         INNER JOIN {$fee_detail_summary_sql} detail_summary
                 ON detail_summary.setup_id = s.setup_id
         WHERE {$setup_date_where}",
        $setup_date_types,
        $setup_date_params
    );

    $fee_total = 0.0;
    $fee_completed_total = 0.0;
    $fee_cancelled_total = 0.0;
    $fee_setup_count = count($fee_setup_rows);
    foreach ($fee_setup_rows as $fee_setup_row) {
        $setup_fee = (float) ($fee_setup_row['install_total'] ?? 0);
        $fee_total += $setup_fee;
        if ((int) ($fee_setup_row['setup_status'] ?? 0) === 4 && !empty($fee_setup_row['completed_at'])) {
            $fee_completed_total += $setup_fee;
        }
        if ((int) ($fee_setup_row['setup_status'] ?? 0) === 5) {
            $fee_cancelled_total += $setup_fee;
        }
    }
    $fee_average = $fee_setup_count > 0 ? $fee_total / $fee_setup_count : 0.0;

    $fee_status_rows = admin_report_rows(
        $conn,
        "SELECT
            CASE
                WHEN s.setup_status = 5 OR a.assign_status = 4 THEN 'cancelled'
                WHEN s.setup_status = 4 OR a.assign_status = 5 THEN 'done'
                WHEN a.assign_status = 3 THEN 'rejected'
                WHEN s.setup_status = 3 AND EXISTS (
                    SELECT 1 FROM installation_result ir WHERE ir.setup_id = s.setup_id
                ) THEN 'review'
                WHEN s.setup_status = 3 THEN 'working'
                WHEN s.setup_status = 2 AND EXISTS (
                    SELECT 1 FROM product_receive pr
                    WHERE pr.assign_id = a.assign_id AND pr.receive_status = 1
                ) THEN 'received'
                WHEN s.setup_status = 2 THEN 'accepted'
                WHEN s.setup_status = 1 OR a.assign_status = 1 THEN 'assigned'
                ELSE 'unassigned'
            END AS report_status,
            COUNT(DISTINCT s.setup_id) AS total,
            COALESCE(SUM(COALESCE(detail_summary.install_total, 0)), 0) AS fee_total
         FROM setup s
         INNER JOIN {$fee_detail_summary_sql} detail_summary
                 ON detail_summary.setup_id = s.setup_id
         LEFT JOIN assignment a
           ON a.setup_id = s.setup_id
          AND a.assign_id = (
              SELECT a2.assign_id
              FROM assignment a2
              WHERE a2.setup_id = s.setup_id
              ORDER BY a2.assign_date DESC, a2.assign_id DESC
              LIMIT 1
          )
         WHERE {$setup_date_where}
         GROUP BY report_status
         ORDER BY FIELD(report_status, 'unassigned', 'assigned', 'accepted', 'received', 'working', 'review', 'done', 'rejected', 'cancelled')",
        $setup_date_types,
        $setup_date_params
    );
    $fee_status_by_key = [];
    foreach (array_keys($installation_status_meta) as $fee_status_key) {
        $fee_status_by_key[$fee_status_key] = ['total' => 0, 'fee_total' => 0.0];
    }
    foreach ($fee_status_rows as $fee_status_row) {
        $fee_status_key = (string) ($fee_status_row['report_status'] ?? '');
        if (isset($fee_status_by_key[$fee_status_key])) {
            $fee_status_by_key[$fee_status_key] = [
                'total' => (int) ($fee_status_row['total'] ?? 0),
                'fee_total' => (float) ($fee_status_row['fee_total'] ?? 0),
            ];
        }
    }

    $fee_month_labels = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $fee_monthly = [];
    for ($month = 1; $month <= 12; $month++) {
        $fee_monthly[$month] = ['jobs' => 0, 'fee' => 0.0];
    }
    foreach (admin_report_rows(
        $conn,
        "SELECT MONTH(s.completed_at) AS month_index,
                COUNT(DISTINCT s.setup_id) AS job_total,
                COALESCE(SUM(detail_summary.install_total), 0) AS fee_total
         FROM setup s
         INNER JOIN {$fee_detail_summary_sql} detail_summary
                 ON detail_summary.setup_id = s.setup_id
         WHERE s.setup_status = 4
           AND s.completed_at IS NOT NULL
           AND s.completed_at >= ? AND s.completed_at < ?
         GROUP BY MONTH(s.completed_at)",
        'ss',
        [$fee_year_start, $fee_next_year_start]
    ) as $fee_month_row) {
        $fee_month_index = (int) ($fee_month_row['month_index'] ?? 0);
        if ($fee_month_index >= 1 && $fee_month_index <= 12) {
            $fee_monthly[$fee_month_index] = [
                'jobs' => (int) ($fee_month_row['job_total'] ?? 0),
                'fee' => (float) ($fee_month_row['fee_total'] ?? 0),
            ];
        }
    }

    $fee_area_max = max(1.0, ...array_column($fee_monthly, 'fee'));
    $fee_area_points = [];
    for ($month = 1; $month <= 12; $month++) {
        $x = 44 + (($month - 1) * 61);
        $y = 190 - (($fee_monthly[$month]['fee'] / $fee_area_max) * 140);
        $fee_area_points[] = round($x, 2) . ',' . round($y, 2);
    }
    $fee_area_point_string = implode(' ', $fee_area_points);
    $fee_area_fill_points = '44,190 ' . $fee_area_point_string . ' 715,190';
    $fee_area_axis_values = [
        (int) round($fee_area_max),
        (int) round($fee_area_max * .66),
        (int) round($fee_area_max * .33),
        0,
    ];

    $report_metrics = [
        ['label' => 'ค่าติดตั้งรวมทั้งหมด', 'value' => number_format($fee_total, 2) . ' ฿', 'detail' => 'รวมใบงานที่มีรายการค่าติดตั้ง', 'tone' => 'violet', 'icon' => 'fa-baht-sign'],
        ['label' => 'ค่าติดตั้งงานเสร็จสิ้น', 'value' => number_format($fee_completed_total, 2) . ' ฿', 'detail' => 'จากงานที่ปิดเรียบร้อย', 'tone' => 'green', 'icon' => 'fa-circle-check'],
        ['label' => 'ค่าติดตั้งงานยกเลิก', 'value' => number_format($fee_cancelled_total, 2) . ' ฿', 'detail' => 'จากงานที่ยกเลิก', 'tone' => 'red', 'icon' => 'fa-ban'],
        ['label' => 'ค่าเฉลี่ยค่าติดตั้งต่อใบงาน', 'value' => number_format($fee_average, 2) . ' ฿', 'detail' => 'เฉลี่ยจากใบงานที่มีรายการติดตั้ง', 'tone' => 'blue', 'icon' => 'fa-chart-simple'],
    ];
}

$ajax_section = trim((string) ($_GET['section'] ?? ''));
if ((string) ($_GET['ajax'] ?? '') === '1' && $report === 'installations' && $ajax_section === 'installation-trend') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'section' => $ajax_section,
        'year' => $installation_year,
        'labels' => array_values($installation_trend_labels),
        'created' => array_values(array_map('intval', $installation_trend_created)),
        'completed' => array_values(array_map('intval', $installation_trend_completed)),
        'max' => (int) $installation_trend_max,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
if ((string) ($_GET['ajax'] ?? '') === '1' && $report === 'revenue' && $ajax_section === 'revenue-trend') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'section' => $ajax_section,
        'year' => $fee_year,
        'labels' => array_values($fee_month_labels),
        'monthly' => array_values($fee_monthly),
        'max' => (float) $fee_area_max,
        'axis' => array_values($fee_area_axis_values),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$page_title = $report === 'overview'
    ? 'รายงานภาพรวมระบบ'
    : ($report === 'users' ? 'รายงานบุคลากร' : 'รายงาน' . $report_categories[$report]);
$page_subtitle = $report === 'overview'
    ? 'สรุปข้อมูลผู้ใช้งาน ใบงานติดตั้ง สถานะงาน และค่าติดตั้งของระบบ'
    : ($report === 'users'
        ? 'สรุปผลงานพนักงานขายและช่างติดตั้งจากข้อมูลจริงของระบบ'
        : 'มุมมองรายงานหมวด' . $report_categories[$report] . 'สำหรับผู้ดูแลระบบ');

layout_header('รายงาน', 'reports', 'ศูนย์รวมรายงานระบบสำหรับผู้ดูแล');
?>
<link
    rel="stylesheet"
    href="<?= h(app_asset_url('admin/assets/css/reports.css')) ?>?v=<?= h(asset_version('admin/assets/css/reports.css')) ?>"
>

<main class="admin-report-page<?= $report === 'revenue' ? ' revenue-report' : '' ?>">
    <header class="report-header">
        <div class="report-header-copy">
            <h1><?= h($page_title) ?></h1>
            <p><?= h($page_subtitle) ?></p>
        </div>
        <div class="report-header-actions">
            <button type="button" class="report-print-btn" onclick="window.print()">
                <i class="fa-solid fa-print" aria-hidden="true"></i>
                <span>พิมพ์ใบรายงาน</span>
            </button>
        </div>
    </header>

    <nav class="report-category-nav" aria-label="หมวดรายงาน">
        <?php foreach ($report_navigation_categories as $key => $label): ?>
            <a
                class="report-category-link<?= $report === $key ? ' is-active' : '' ?>"
                href="<?= h(admin_report_url($key, $start_date, $end_date)) ?>"
                <?= $report === $key ? 'aria-current="page"' : '' ?>
            >
                <?= h($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($report === 'overview'): ?>
        <section class="report-metric-grid report-overview-metric-grid" aria-label="ตัวชี้วัดภาพรวมระบบ">
            <?php
            $metrics = [
                ['label' => 'พนักงานทั้งหมด', 'value' => number_format($total_users), 'detail' => 'จากเมนูจัดการพนักงาน', 'tone' => 'blue', 'icon' => 'fa-users'],
                ['label' => 'ช่างติดตั้งทั้งหมด', 'value' => number_format($total_technicians), 'detail' => 'จากเมนูจัดการข้อมูลช่าง', 'tone' => 'green', 'icon' => 'fa-screwdriver-wrench'],
                ['label' => 'ลูกค้าทั้งหมด', 'value' => number_format($total_customers), 'detail' => 'จากเมนูจัดการข้อมูลลูกค้า', 'tone' => 'amber', 'icon' => 'fa-address-card'],
                ['label' => 'ประเภทสินค้าทั้งหมด', 'value' => number_format($total_product_types), 'detail' => 'จากเมนูประเภทสินค้า', 'tone' => 'violet', 'icon' => 'fa-tags'],
                ['label' => 'สินค้าทั้งหมด', 'value' => number_format($total_products), 'detail' => 'จากเมนูสินค้า', 'tone' => 'cyan', 'icon' => 'fa-box'],
            ];
            foreach ($metrics as $metric):
            ?>
                <article class="report-metric-card">
                    <div class="report-metric-icon tone-<?= h($metric['tone']) ?>">
                        <i class="fa-solid <?= h($metric['icon']) ?>" aria-hidden="true"></i>
                    </div>
                    <div class="report-metric-copy">
                        <span><?= h($metric['label']) ?></span>
                        <strong><?= h($metric['value']) ?></strong>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="report-dual-chart-grid" aria-label="สรุปรายงานงานติดตั้งและงานที่เสร็จแล้ว">
            <article class="report-panel report-insight-card">
                <div class="report-insight-header">
                    <div>
                        <h2>สรุปรายงานงานติดตั้ง</h2>
                        <p>ภาพรวมสถานะงานติดตั้งทั้งหมดในช่วงวันที่เลือก</p>
                    </div>
                </div>

                <div class="installation-report-body">
                    <div class="installation-donut-area">
                        <div class="installation-summary-donut-wrap">
                            <div
                                class="installation-summary-donut"
                                style="background: conic-gradient(<?= h($installation_donut_gradient) ?>);"
                                role="img"
                                aria-label="งานทั้งหมด <?= h(number_format($installation_report_total)) ?> งาน แบ่งตามสถานะ"
                            >
                                <div class="installation-summary-donut-center">
                                    <strong><?= h(number_format($installation_report_total)) ?></strong>
                                    <span>งานทั้งหมด</span>
                                </div>
                                <?php foreach ($installation_donut_labels as $donut_label): ?>
                                    <span
                                        class="installation-summary-count-label"
                                        style="--label-x: <?= h(number_format($donut_label['x'], 2, '.', '')) ?>%; --label-y: <?= h(number_format($donut_label['y'], 2, '.', '')) ?>%;"
                                        title="<?= h($donut_label['label']) ?>: <?= h(number_format($donut_label['total'])) ?> งาน"
                                        aria-label="<?= h($donut_label['label']) ?> <?= h(number_format($donut_label['total'])) ?> งาน"
                                    >
                                        <?= h(number_format($donut_label['total'])) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="installation-summary-legend installation-legend-list">
                        <?php foreach ($installation_status_meta as $status_key => $status_item): ?>
                            <div class="installation-summary-legend-item">
                                <span class="installation-summary-dot tone-<?= h($status_item['tone']) ?>" style="background: <?= h($status_item['color']) ?>;" aria-hidden="true"></span>
                                <strong class="installation-summary-legend-label"><?= h($status_item['label']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>

            <article class="report-panel report-insight-card completed-install-card" data-completed-install-card>
                <div class="report-insight-header completed-install-header">
                    <div>
                        <h2>งานติดตั้งเสร็จแล้ว</h2>
                        <p>สรุปจำนวนงานที่เสร็จสิ้นตามช่วงเวลา</p>
                    </div>
                    <div class="completed-install-controls">
                        <div class="completed-period-tabs" role="group" aria-label="ช่วงเวลางานติดตั้งเสร็จแล้ว">
                            <?php foreach ($completed_install_period_options as $period_key => $period_label): ?>
                                <button
                                    type="button"
                                    class="completed-period-tab<?= $completed_install_period === $period_key ? ' is-active' : '' ?>"
                                    data-completed-period="<?= h($period_key) ?>"
                                    aria-pressed="<?= $completed_install_period === $period_key ? 'true' : 'false' ?>"
                                >
                                    <?= h($period_label) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <button
                            type="button"
                            class="completed-reset-btn"
                            data-completed-reset
                            aria-label="รีเซ็ตช่วงเวลางานติดตั้งเสร็จแล้ว"
                        >
                            <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                            <span>รีเฟรช</span>
                        </button>
                    </div>
                </div>

                <div class="completed-summary-row">
                    <div class="completed-install-summary" data-completed-summary aria-live="polite"></div>
                    <div class="completed-period-control" data-completed-period-control>
                        <label class="completed-date-control" data-completed-date-control>
                            <span>วันที่</span>
                            <div class="completed-date-picker">
                                <input
                                    type="text"
                                    data-completed-date-display
                                    value="<?= h($completed_report_today_display) ?>"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    placeholder="วว/ดด/ปปปป"
                                    pattern="[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}"
                                    aria-label="วันที่ในรูปแบบวัน เดือน ปี"
                                >
                                <button
                                    type="button"
                                    class="completed-date-trigger"
                                    data-completed-date-trigger
                                    aria-label="เปิดปฏิทินเลือกวันที่"
                                >
                                    <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                </button>
                                <input
                                    type="date"
                                    class="completed-date-native"
                                    data-completed-date
                                    value="<?= h($completed_report_today) ?>"
                                    min="<?= h($completed_report_min_date) ?>"
                                    max="<?= h($completed_report_today) ?>"
                                    tabindex="-1"
                                    aria-label="เลือกวันที่สำหรับรายงานงานติดตั้งเสร็จแล้ว"
                                >
                            </div>
                        </label>
                        <label class="completed-range-control" data-completed-week-control hidden>
                            <span>สัปดาห์</span>
                            <input
                                type="week"
                                class="completed-range-input"
                                data-completed-week
                                value="<?= h($completed_report_current_week_input) ?>"
                                min="<?= h($completed_report_min_week_input) ?>"
                                max="<?= h($completed_report_current_week_input) ?>"
                                aria-label="เลือกสัปดาห์สำหรับรายงานงานติดตั้งเสร็จแล้ว"
                            >
                        </label>
                        <label class="completed-range-control" data-completed-month-control hidden>
                            <span>เดือน</span>
                            <input
                                type="month"
                                class="completed-range-input"
                                data-completed-month
                                value="<?= h($completed_report_current_month_input) ?>"
                                min="<?= h($completed_report_min_month_input) ?>"
                                max="<?= h($completed_report_current_month_input) ?>"
                                aria-label="เลือกเดือนสำหรับรายงานงานติดตั้งเสร็จแล้ว"
                            >
                        </label>
                    </div>
                </div>
                <div class="completed-install-chart" data-completed-chart data-period="day" aria-live="polite"></div>
            </article>
        </section>


        <section class="report-panel report-overview-summary-panel" aria-labelledby="overview-summary-title">
            <div class="report-panel-head">
                <div>
                    <h2 id="overview-summary-title">สรุปผลการดำเนินงาน</h2>
                    <p>ภาพรวมการปิดงานและค่าติดตั้งในช่วงวันที่เลือก</p>
                </div>
            </div>
            <div class="report-overview-summary-grid">
                <div class="report-overview-summary-item">
                    <span>ค่าติดตั้งรวม</span>
                    <strong><?= h(number_format($total_install_revenue, 2)) ?> ฿</strong>
                </div>
                <div class="report-overview-summary-item">
                    <span>งานเสร็จสิ้น</span>
                    <strong><?= h(number_format($completed_setup_count)) ?> งาน</strong>
                </div>
                <div class="report-overview-summary-item">
                    <span>งานยกเลิก</span>
                    <strong><?= h(number_format($cancelled_setup_count)) ?> งาน</strong>
                </div>
                <div class="report-overview-summary-item">
                    <span>อัตราปิดงาน</span>
                    <strong><?= h((string) $report_completion_rate) ?>%</strong>
                </div>
                <div class="report-overview-summary-item">
                    <span>เฉลี่ยต่อใบงาน</span>
                    <strong><?= h(number_format($report_average_install, 2)) ?> ฿</strong>
                </div>
            </div>
        </section>

    <?php else: ?>
        <?php if ($report !== 'users'): ?>
            <section class="report-metric-grid" aria-label="ตัวชี้วัดรายงาน<?= h($report_categories[$report]) ?>">
                <?php foreach ($report_metrics as $metric): ?>
                    <article class="report-metric-card">
                        <div class="report-metric-icon tone-<?= h($metric['tone']) ?>">
                            <i class="fa-solid <?= h($metric['icon']) ?>" aria-hidden="true"></i>
                        </div>
                        <div class="report-metric-copy">
                            <span><?= h($metric['label']) ?></span>
                            <strong><?= h($metric['value']) ?></strong>
                            <?php if ($report !== 'installations' && $report !== 'revenue'): ?>
                                <small><?= h($metric['detail']) ?></small>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if ($report === 'installations'): ?>
            <section class="report-panel installation-trend-panel" aria-labelledby="installation-trend-title" data-admin-trend-section="installation">
                <div class="report-panel-head installation-report-head">
                    <div>
                        <h2 id="installation-trend-title">แนวโน้มใบงานติดตั้ง</h2>
                        <p>เปรียบเทียบใบงานที่สร้างและงานที่เสร็จสิ้นรายเดือน</p>
                    </div>
                    <form class="installation-year-control" method="get" action="<?= h(app_system_url('admin/reports.php')) ?>" data-admin-report-year-form>
                        <input type="hidden" name="report" value="installations">
                        <?php if ($start_date !== ''): ?><input type="hidden" name="start_date" value="<?= h($start_date) ?>"><?php endif; ?>
                        <?php if ($end_date !== ''): ?><input type="hidden" name="end_date" value="<?= h($end_date) ?>"><?php endif; ?>
                        <label for="installation-report-year">ปี</label>
                        <select id="installation-report-year" name="install_year">
                            <?php foreach (array_reverse($installation_year_options) as $year_option): ?>
                                <option value="<?= h((string) $year_option) ?>"<?= $installation_year === $year_option ? ' selected' : '' ?>><?= h((string) $year_option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="installation-trend-legend" aria-label="คำอธิบายเส้นกราฟ">
                    <span><i class="installation-trend-swatch installation-trend-swatch--created" aria-hidden="true"></i>ใบงานที่สร้าง</span>
                    <span><i class="installation-trend-swatch installation-trend-swatch--completed" aria-hidden="true"></i>งานที่เสร็จสิ้น</span>
                </div>
                <div class="installation-trend-chart-wrap">
                    <svg class="installation-trend-chart" viewBox="0 0 770 220" role="img" aria-labelledby="installation-trend-title installation-trend-description">
                        <desc id="installation-trend-description">จำนวนใบงานที่สร้างและเสร็จสิ้นในปี <?= h((string) $installation_year) ?></desc>
                        <?php foreach ([40, 83.33, 126.67, 170] as $grid_y): ?>
                            <?php $grid_value = (int) round($installation_trend_max * ((170 - $grid_y) / 130)); ?>
                            <line class="installation-trend-gridline" x1="28" y1="<?= h((string) $grid_y) ?>" x2="740" y2="<?= h((string) $grid_y) ?>"></line>
                            <text class="installation-trend-axis-label" x="22" y="<?= h((string) ($grid_y + 4)) ?>" text-anchor="end"><?= h(number_format($grid_value)) ?></text>
                        <?php endforeach; ?>
                        <polyline class="installation-trend-line installation-trend-line--created" points="<?= h($installation_trend_point_strings['created']) ?>"></polyline>
                        <polyline class="installation-trend-line installation-trend-line--completed" points="<?= h($installation_trend_point_strings['completed']) ?>"></polyline>
                        <?php foreach (['created' => 'ใบงานที่สร้าง', 'completed' => 'งานที่เสร็จสิ้น'] as $series_key => $series_label): ?>
                            <?php foreach ($installation_trend_points[$series_key] as $month_index => $point): ?>
                                <circle
                                    class="installation-trend-point installation-trend-point--<?= h($series_key) ?>"
                                    cx="<?= h((string) $point['x']) ?>"
                                    cy="<?= h((string) $point['y']) ?>"
                                    r="4"
                                    tabindex="0"
                                    aria-label="<?= h($series_label) ?> <?= h($installation_trend_labels[$month_index]) ?> <?= h(number_format($point['value'])) ?> งาน"
                                >
                                    <title><?= h($series_label) ?> · <?= h($installation_trend_labels[$month_index]) ?>: <?= h(number_format($point['value'])) ?> งาน</title>
                                </circle>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <?php foreach ($installation_trend_labels as $month_index => $month_label): ?>
                            <text class="installation-trend-month-label" x="<?= h((string) (42 + ($month_index * 61))) ?>" y="202" text-anchor="middle"><?= h($month_label) ?></text>
                        <?php endforeach; ?>
                    </svg>
                </div>
            </section>

            <section class="report-panel installation-status-panel" aria-labelledby="installation-status-title">
                <div class="report-panel-head installation-report-head">
                    <div>
                        <h2 id="installation-status-title">จำนวนงานตามสถานะ</h2>
                        <p>เปรียบเทียบปริมาณใบงานในแต่ละขั้นตอนของ workflow</p>
                    </div>
                    <span class="report-panel-total"><?= h(number_format($total_setups)) ?> งาน</span>
                </div>
                <?php $installation_status_max = max(1, ...array_values($installation_workflow_counts)); ?>
                <div class="installation-status-bars">
                    <?php foreach ($installation_workflow_status_meta as $status_key => $status_item): ?>
                        <?php
                        $status_total = $installation_workflow_counts[$status_key];
                        $status_width = $status_total > 0 ? max(8, round(($status_total / $installation_status_max) * 100, 1)) : 0;
                        ?>
                        <div class="installation-status-row<?= $status_total === 0 ? ' is-zero' : '' ?>">
                            <div class="installation-status-label">
                                <span><i class="fa-solid <?= h($status_item['icon']) ?> tone-<?= h($status_item['tone']) ?>" aria-hidden="true"></i><?= h($status_item['label']) ?></span>
                                <strong><?= h(number_format($status_total)) ?> งาน</strong>
                            </div>
                            <div class="installation-status-track"><span class="installation-status-fill" style="width: <?= h((string) $status_width) ?>%;"></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="report-panel report-table-panel report-data-panel installation-detail-panel" aria-labelledby="installation-detail-title">
                <div class="report-panel-head">
                    <div>
                        <h2 id="installation-detail-title">รายละเอียดใบงานติดตั้ง</h2>
                        <p>แสดงใบงานล่าสุดโดยใช้ assignment ล่าสุดต่อใบงาน</p>
                    </div>
                    <span class="report-table-period"><i class="fa-regular fa-calendar" aria-hidden="true"></i><?= h($period_label) ?></span>
                </div>
                <div class="report-table-wrap">
                    <table class="report-table installation-detail-table">
                        <thead>
                            <tr>
                                <th scope="col">รหัสใบงาน</th>
                                <th scope="col">ลูกค้า</th>
                                <th scope="col">พนักงานขาย</th>
                                <th scope="col">ช่างล่าสุด</th>
                                <th scope="col">วันที่สร้าง</th>
                                <th scope="col">วันที่ติดตั้ง</th>
                                <th scope="col">สถานะปัจจุบัน</th>
                                <th scope="col">ค่าติดตั้ง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($installation_detail_rows === []): ?>
                                <tr><td colspan="8"><div class="report-empty-state"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><span>ยังไม่มีใบงานในช่วงวันที่เลือก</span></div></td></tr>
                            <?php else: ?>
                                <?php foreach ($installation_detail_rows as $installation_row): ?>
                                    <?php
                                    $detail_status_key = (string) ($installation_row['report_status'] ?? 'unassigned');
                                    $detail_status = $installation_workflow_status_meta[$detail_status_key] ?? ['label' => 'ไม่ระบุสถานะ', 'tone' => 'blue', 'icon' => 'fa-circle-question'];
                                    $created_display = !empty($installation_row['created_at']) ? date('d/m/Y', strtotime((string) $installation_row['created_at'])) : '-';
                                    $install_display = !empty($installation_row['assign_install_date']) ? date('d/m/Y', strtotime((string) $installation_row['assign_install_date'])) : '-';
                                    ?>
                                    <tr>
                                        <td><strong><?= h($installation_row['setup_id']) ?></strong></td>
                                        <td><?= h($installation_row['customer_name'] ?: '-') ?></td>
                                        <td><?= h($installation_row['seller_name'] ?: '-') ?></td>
                                        <td><?= h($installation_row['technician_name'] ?: '-') ?></td>
                                        <td><?= h($created_display) ?></td>
                                        <td><?= h($install_display) ?></td>
                                        <td><span class="report-status-label"><i class="fa-solid <?= h($detail_status['icon']) ?> tone-<?= h($detail_status['tone']) ?>" aria-hidden="true"></i><?= h($detail_status['label']) ?></span></td>
                                        <td><?= h(number_format((float) ($installation_row['install_total'] ?? 0), 2)) ?> ฿</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        <?php elseif ($report === 'revenue'): ?>
            <section class="report-panel revenue-area-panel" aria-labelledby="revenue-trend-title" data-admin-trend-section="revenue">
                <div class="report-panel-head revenue-area-header">
                    <div>
                        <h2 id="revenue-trend-title">แนวโน้มค่าติดตั้ง</h2>
                        <p>ยอดค่าติดตั้งของงานเสร็จสิ้น แยกตามเดือนในปีที่เลือก</p>
                    </div>
                    <form class="revenue-year-control" method="get" action="<?= h(app_system_url('admin/reports.php')) ?>" data-admin-report-year-form>
                        <input type="hidden" name="report" value="revenue">
                        <?php if ($start_date !== ''): ?><input type="hidden" name="start_date" value="<?= h($start_date) ?>"><?php endif; ?>
                        <?php if ($end_date !== ''): ?><input type="hidden" name="end_date" value="<?= h($end_date) ?>"><?php endif; ?>
                        <label for="revenue-report-year">ปี</label>
                        <select id="revenue-report-year" name="fee_year">
                            <?php foreach (array_reverse($fee_year_options) as $year_option): ?>
                                <option value="<?= h((string) $year_option) ?>"<?= $fee_year === $year_option ? ' selected' : '' ?>><?= h((string) $year_option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="revenue-area-legend" aria-label="คำอธิบายกราฟค่าติดตั้ง">
                    <span><i class="revenue-area-swatch" aria-hidden="true"></i>งานติดตั้งเสร็จสิ้น</span>
                </div>
                <div class="revenue-area-chart-wrap">
                    <svg class="revenue-area-chart" viewBox="0 0 760 235" role="img" aria-labelledby="revenue-trend-title revenue-trend-description">
                        <desc id="revenue-trend-description">ยอดค่าติดตั้งงานเสร็จสิ้นรายเดือนในปี <?= h((string) $fee_year) ?></desc>
                        <?php foreach ([40, 90, 140, 190] as $grid_index => $grid_y): ?>
                            <line class="revenue-area-gridline" x1="44" y1="<?= h((string) $grid_y) ?>" x2="715" y2="<?= h((string) $grid_y) ?>"></line>
                            <text class="revenue-area-axis-label" x="36" y="<?= h((string) ($grid_y + 4)) ?>" text-anchor="end"><?= h(number_format($fee_area_axis_values[$grid_index], 0)) ?></text>
                        <?php endforeach; ?>
                        <polygon class="revenue-area-fill" points="<?= h($fee_area_fill_points) ?>"></polygon>
                        <polyline class="revenue-area-line" points="<?= h($fee_area_point_string) ?>"></polyline>
                        <?php for ($month = 1; $month <= 12; $month++): ?>
                            <?php
                            $point_x = 44 + (($month - 1) * 61);
                            $point_y = 190 - (($fee_monthly[$month]['fee'] / $fee_area_max) * 140);
                            ?>
                            <circle class="revenue-area-point" cx="<?= h((string) $point_x) ?>" cy="<?= h((string) round($point_y, 2)) ?>" r="3.5">
                                <title><?= h($fee_month_labels[$month - 1]) ?>: <?= h(number_format($fee_monthly[$month]['fee'], 2)) ?> ฿</title>
                            </circle>
                            <text class="revenue-area-month-label" x="<?= h((string) $point_x) ?>" y="218" text-anchor="middle"><?= h($fee_month_labels[$month - 1]) ?></text>
                        <?php endfor; ?>
                    </svg>
                </div>
            </section>

            <section class="revenue-report-grid" aria-label="รายละเอียดค่าติดตั้ง">
                <article class="report-panel revenue-status-panel" aria-labelledby="revenue-status-title">
                    <div class="report-panel-head">
                        <div>
                            <h2 id="revenue-status-title">ค่าติดตั้งตามสถานะงาน</h2>
                            <p>เปรียบเทียบยอดค่าติดตั้งจากสถานะปัจจุบันใน<?= h($period_label) ?></p>
                        </div>
                    </div>
                    <?php
                    $fee_status_values = array_map(static fn (array $item): float => (float) $item['fee_total'], $fee_status_by_key);
                    $fee_status_max = max(1.0, ...array_values($fee_status_values));
                    ?>
                    <div class="revenue-status-bars">
                        <?php foreach ($installation_status_meta as $status_key => $status_item): ?>
                            <?php
                            $status_fee = (float) $fee_status_by_key[$status_key]['fee_total'];
                            $status_width = $status_fee > 0 ? max(8, round(($status_fee / $fee_status_max) * 100, 1)) : 0;
                            ?>
                            <div class="revenue-status-row<?= $status_fee <= 0 ? ' is-zero' : '' ?>">
                                <div class="revenue-status-label">
                                    <span><i class="fa-solid <?= h($status_item['icon'] ?? 'fa-circle') ?>" aria-hidden="true"></i><?= h($status_item['label']) ?></span>
                                    <strong><?= h(number_format($status_fee, 2)) ?> ฿</strong>
                                </div>
                                <div class="revenue-status-track"><span class="revenue-status-fill" style="width: <?= h((string) $status_width) ?>%;"></span></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="report-panel report-table-panel revenue-monthly-panel" aria-labelledby="revenue-monthly-title" data-admin-revenue-monthly>
                    <div class="report-panel-head">
                        <div>
                            <h2 id="revenue-monthly-title">สรุปค่าติดตั้งรายเดือน</h2>
                            <p>งานเสร็จสิ้นในปี <?= h((string) $fee_year) ?></p>
                        </div>
                    </div>
                    <div class="report-table-wrap">
                        <table class="report-table revenue-monthly-table">
                            <thead>
                                <tr><th scope="col">เดือน</th><th scope="col">จำนวนงานเสร็จสิ้น</th><th scope="col">ค่าติดตั้งรวม</th><th scope="col">ค่าเฉลี่ยต่อใบงาน</th></tr>
                            </thead>
                            <tbody>
                                <?php for ($month = 1; $month <= 12; $month++): ?>
                                    <?php
                                    $month_jobs = $fee_monthly[$month]['jobs'];
                                    $month_fee = $fee_monthly[$month]['fee'];
                                    $month_average = $month_jobs > 0 ? $month_fee / $month_jobs : 0;
                                    ?>
                                    <tr>
                                        <td><?= h($fee_month_labels[$month - 1]) ?></td>
                                        <td><?= h(number_format($month_jobs)) ?> งาน</td>
                                        <td><?= h(number_format($month_fee, 2)) ?> ฿</td>
                                        <td><?= h(number_format($month_average, 2)) ?> ฿</td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        <?php elseif ($report === 'users'): ?>
            <section class="personnel-section" aria-labelledby="personnel-sales-title">
                <div class="personnel-section-header">
                    <div>
                        <h2 id="personnel-sales-title">พนักงานขาย</h2>
                        <p>วัดผลงานจากใบงานที่พนักงานขายสร้างใน<?= h($period_label) ?></p>
                    </div>
                    <span class="report-table-period"><i class="fa-solid fa-user-tie" aria-hidden="true"></i><?= h(number_format($total_sales)) ?> คน</span>
                </div>
                <div class="personnel-kpi-grid">
                    <article class="report-metric-card personnel-kpi-card">
                        <div class="report-metric-icon tone-violet"><i class="fa-solid fa-user-tie" aria-hidden="true"></i></div>
                        <div class="report-metric-copy"><span>พนักงานขาย</span><strong><?= h(number_format($total_sales)) ?></strong></div>
                    </article>
                    <article class="report-metric-card personnel-kpi-card">
                        <div class="report-metric-icon tone-blue"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i></div>
                        <div class="report-metric-copy"><span>ใบงานที่สร้าง</span><strong><?= h(number_format($sales_created_total)) ?></strong></div>
                    </article>
                    <article class="report-metric-card personnel-kpi-card">
                        <div class="report-metric-icon tone-green"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                        <div class="report-metric-copy"><span>งานเสร็จ</span><strong><?= h(number_format($sales_done_total)) ?></strong></div>
                    </article>
                    <article class="report-metric-card personnel-kpi-card">
                        <div class="report-metric-icon tone-red"><i class="fa-solid fa-ban" aria-hidden="true"></i></div>
                        <div class="report-metric-copy"><span>งานยกเลิก</span><strong><?= h(number_format($sales_cancelled_total)) ?></strong></div>
                    </article>
                </div>
                <article class="report-panel personnel-chart-panel" aria-labelledby="sales-chart-title">
                    <div class="report-panel-head">
                        <div>
                            <h3 id="sales-chart-title">ใบงานที่สร้างแยกตามพนักงานขาย</h3>
                            <p>แท่งแบบกลุ่มช่วยเทียบงานสร้าง เสร็จ และยกเลิกในแต่ละคน</p>
                        </div>
                        <div class="personnel-chart-legend" aria-label="คำอธิบายกราฟพนักงานขาย">
                            <span><i class="personnel-series-dot personnel-series-dot--created" aria-hidden="true"></i>สร้าง</span>
                            <span><i class="personnel-series-dot personnel-series-dot--done" aria-hidden="true"></i>เสร็จ</span>
                            <span><i class="personnel-series-dot personnel-series-dot--cancelled" aria-hidden="true"></i>ยกเลิก</span>
                        </div>
                    </div>
                    <div class="personnel-grouped-bars">
                        <?php if ($sales_report_rows === []): ?>
                            <div class="report-empty-state"><i class="fa-regular fa-user" aria-hidden="true"></i><span>ยังไม่มีข้อมูลพนักงานขาย</span></div>
                        <?php else: ?>
                            <?php foreach ($sales_report_rows as $sales_row): ?>
                                <div class="personnel-chart-row">
                                    <strong class="personnel-chart-name"><?= h($sales_row['seller_name'] ?: '-') ?></strong>
                                    <div class="personnel-chart-series">
                                        <?php foreach ([
                                            ['key' => 'setup_total', 'label' => 'สร้าง', 'class' => 'created'],
                                            ['key' => 'done_total', 'label' => 'เสร็จ', 'class' => 'done'],
                                            ['key' => 'cancelled_total', 'label' => 'ยกเลิก', 'class' => 'cancelled'],
                                        ] as $series): ?>
                                            <?php
                                            $series_value = (int) ($sales_row[$series['key']] ?? 0);
                                            $series_width = $series_value > 0 ? max(6, round(($series_value / $sales_chart_max) * 100, 1)) : 0;
                                            ?>
                                            <div class="personnel-series-row" aria-label="<?= h($series['label']) ?> <?= h(number_format($series_value)) ?> งาน">
                                                <span class="personnel-series-label"><?= h($series['label']) ?></span>
                                                <div class="personnel-series-track"><span class="personnel-series-bar personnel-series-bar--<?= h($series['class']) ?>" style="width: <?= h((string) $series_width) ?>%;"></span></div>
                                                <strong><?= h(number_format($series_value)) ?></strong>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </article>
                <article class="report-panel report-table-panel personnel-table-panel" aria-labelledby="sales-table-title">
                    <div class="report-panel-head">
                        <div>
                            <h3 id="sales-table-title">รายละเอียดผลงานพนักงานขาย</h3>
                            <p>ค่าติดตั้งรวมอ้างอิงจากรายการของใบงานที่พนักงานขายสร้าง</p>
                        </div>
                    </div>
                    <div class="report-table-wrap">
                        <table class="report-table personnel-table">
                            <thead><tr><th scope="col">ชื่อ</th><th scope="col">ใบงานที่สร้าง</th><th scope="col">เสร็จ</th><th scope="col">ยกเลิก</th><th scope="col">ค่าติดตั้งรวม</th></tr></thead>
                            <tbody>
                                <?php if ($sales_report_rows === []): ?>
                                    <tr><td colspan="5"><div class="report-empty-state"><i class="fa-regular fa-user" aria-hidden="true"></i><span>ยังไม่มีข้อมูลพนักงานขาย</span></div></td></tr>
                                <?php else: ?>
                                    <?php foreach ($sales_report_rows as $sales_row): ?>
                                        <tr>
                                            <td><span class="report-status-label"><i class="fa-solid fa-user-tie tone-violet" aria-hidden="true"></i><?= h($sales_row['seller_name'] ?: '-') ?></span></td>
                                            <td><?= h(number_format((int) $sales_row['setup_total'])) ?> งาน</td>
                                            <td><?= h(number_format((int) $sales_row['done_total'])) ?> งาน</td>
                                            <td><?= h(number_format((int) $sales_row['cancelled_total'])) ?> งาน</td>
                                            <td><?= h(number_format((float) $sales_row['revenue_total'], 2)) ?> ฿</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>

            <section class="personnel-section" aria-labelledby="personnel-technician-title">
                <div class="personnel-section-header">
                    <div>
                        <h2 id="personnel-technician-title">ช่างติดตั้ง</h2>
                        <p>วัดผลงานจากการมอบหมายงานล่าสุดต่อใบงานใน<?= h($period_label) ?></p>
                    </div>
                    <span class="report-table-period"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i><?= h(number_format($total_technicians)) ?> คน</span>
                </div>
                <div class="personnel-kpi-grid personnel-technician-kpis">
                    <article class="report-metric-card personnel-kpi-card">
                        <div class="report-metric-icon tone-green"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></div>
                        <div class="report-metric-copy"><span>ช่างติดตั้ง</span><strong><?= h(number_format($total_technicians)) ?></strong></div>
                    </article>
                    <article class="report-metric-card personnel-kpi-card">
                        <div class="report-metric-icon tone-blue"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i></div>
                        <div class="report-metric-copy"><span>ได้รับมอบหมาย</span><strong><?= h(number_format($technicians_assigned_total)) ?></strong></div>
                    </article>
                    <article class="report-metric-card personnel-kpi-card">
                        <div class="report-metric-icon tone-green"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                        <div class="report-metric-copy"><span>งานเสร็จสิ้น</span><strong><?= h(number_format($technicians_done_total)) ?></strong></div>
                    </article>
                    <article class="report-metric-card personnel-kpi-card">
                        <div class="report-metric-icon tone-red"><i class="fa-solid fa-user-slash" aria-hidden="true"></i></div>
                        <div class="report-metric-copy"><span>งานที่ปฏิเสธ</span><strong><?= h(number_format($technicians_rejected_total)) ?></strong></div>
                    </article>
                </div>
                <article class="report-panel personnel-chart-panel" aria-labelledby="technician-chart-title">
                    <div class="report-panel-head">
                        <div>
                            <h3 id="technician-chart-title">ผลงานช่างติดตั้งรายคน</h3>
                            <p>เปรียบเทียบงานที่ได้รับมอบหมาย เสร็จสิ้น และปฏิเสธ โดยใช้สีแยกตาม series</p>
                        </div>
                        <div class="personnel-chart-legend" aria-label="คำอธิบายกราฟช่างติดตั้ง">
                            <span><i class="personnel-series-dot personnel-series-dot--assigned" aria-hidden="true"></i>มอบหมาย</span>
                            <span><i class="personnel-series-dot personnel-series-dot--done" aria-hidden="true"></i>เสร็จ</span>
                            <span><i class="personnel-series-dot personnel-series-dot--rejected" aria-hidden="true"></i>ปฏิเสธ</span>
                        </div>
                    </div>
                    <div class="personnel-grouped-bars">
                        <?php if ($technicians_report_rows === []): ?>
                            <div class="report-empty-state"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i><span>ยังไม่มีข้อมูลช่างติดตั้ง</span></div>
                        <?php else: ?>
                            <?php foreach ($technicians_report_rows as $technician_row): ?>
                                <div class="personnel-chart-row">
                                    <strong class="personnel-chart-name"><?= h($technician_row['tech_display_name']) ?></strong>
                                    <div class="personnel-chart-series">
                                        <?php foreach ([
                                            ['key' => 'assigned_total', 'label' => 'มอบหมาย', 'class' => 'assigned'],
                                            ['key' => 'done_total', 'label' => 'เสร็จ', 'class' => 'done'],
                                            ['key' => 'rejected_total', 'label' => 'ปฏิเสธ', 'class' => 'rejected'],
                                        ] as $series): ?>
                                            <?php
                                            $series_value = (int) ($technician_row[$series['key']] ?? 0);
                                            $series_width = $series_value > 0 ? max(6, round(($series_value / $technicians_chart_max) * 100, 1)) : 0;
                                            ?>
                                            <div class="personnel-series-row" aria-label="<?= h($series['label']) ?> <?= h(number_format($series_value)) ?> งาน">
                                                <span class="personnel-series-label"><?= h($series['label']) ?></span>
                                                <div class="personnel-series-track"><span class="personnel-series-bar personnel-series-bar--<?= h($series['class']) ?>" style="width: <?= h((string) $series_width) ?>%;"></span></div>
                                                <strong><?= h(number_format($series_value)) ?></strong>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </article>
                <article class="report-panel report-table-panel personnel-table-panel" aria-labelledby="technician-table-title">
                    <div class="report-panel-head">
                        <div>
                            <h3 id="technician-table-title">รายละเอียดผลงานช่างติดตั้ง</h3>
                            <p>นับ assignment ล่าสุดต่อใบงาน จึงไม่ซ้ำเมื่อมีการเปลี่ยนช่าง</p>
                        </div>
                    </div>
                    <div class="report-table-wrap">
                        <table class="report-table personnel-table">
                            <thead><tr><th scope="col">ชื่อ</th><th scope="col">มอบหมาย</th><th scope="col">รับงาน</th><th scope="col">เสร็จ</th><th scope="col">ปฏิเสธ</th></tr></thead>
                            <tbody>
                                <?php if ($technicians_report_rows === []): ?>
                                    <tr><td colspan="5"><div class="report-empty-state"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i><span>ยังไม่มีข้อมูลช่างติดตั้ง</span></div></td></tr>
                                <?php else: ?>
                                    <?php foreach ($technicians_report_rows as $technician_row): ?>
                                        <tr>
                                            <td><span class="report-status-label"><i class="fa-solid fa-screwdriver-wrench tone-green" aria-hidden="true"></i><?= h($technician_row['tech_display_name']) ?></span></td>
                                            <td><?= h(number_format((int) $technician_row['assigned_total'])) ?> งาน</td>
                                            <td><?= h(number_format((int) $technician_row['accepted_total'])) ?> งาน</td>
                                            <td><?= h(number_format((int) $technician_row['done_total'])) ?> งาน</td>
                                            <td><?= h(number_format((int) $technician_row['rejected_total'])) ?> งาน</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($report === 'overview'): ?>
        <script type="application/json" id="report-period-data"><?= json_encode($report_period_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?></script>
        <script src="<?= h(app_asset_url('admin/assets/js/reports.js')) ?>?v=<?= h(asset_version('admin/assets/js/reports.js')) ?>"></script>
    <?php elseif ($report === 'installations' || $report === 'revenue'): ?>
        <script src="<?= h(app_asset_url('admin/assets/js/report_filters.js')) ?>?v=<?= h(asset_version('admin/assets/js/report_filters.js')) ?>"></script>
    <?php endif; ?>
</main>

<?php layout_footer(); ?>

