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
    'personnel' => 'บุคลากร',
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
    'personnel' => 'บุคลากร',
    'revenue' => 'ค่าติดตั้ง',
];

$report = strtolower(trim((string) ($_GET['report'] ?? 'overview')));
if (!array_key_exists($report, $report_categories)) {
    $report = 'overview';
}

// Keep legacy bookmarks working while using the consolidated Personnel report.
if ($report === 'personnel' || $report === 'sales' || $report === 'technicians') {
    $report = 'users';
}

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

$report_filter_search = trim((string) ($_GET['search'] ?? ''));
$report_filter_search = function_exists('mb_substr') ? mb_substr($report_filter_search, 0, 120) : substr($report_filter_search, 0, 120);
$report_filter_status = trim((string) ($_GET['status'] ?? ''));
$personnel_filter_search = trim((string) ($_GET['personnel_search'] ?? ''));
$personnel_filter_search = function_exists('mb_substr') ? mb_substr($personnel_filter_search, 0, 120) : substr($personnel_filter_search, 0, 120);
$personnel_filter_type = trim((string) ($_GET['personnel_type'] ?? ''));
if ($personnel_filter_type === '') {
    $personnel_filter_type = 'all';
}
if (!in_array($personnel_filter_type, ['all', 'sale', 'manager', 'technician'], true)) {
    $personnel_filter_type = 'all';
}

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
$total_admins = admin_report_count($conn, 'SELECT COUNT(*) AS total FROM `user` WHERE user_role = 3');
$total_managers = admin_report_count($conn, 'SELECT COUNT(*) AS total FROM `user` WHERE user_role = 1');
$total_sales = admin_report_count($conn, 'SELECT COUNT(*) AS total FROM `user` WHERE user_role = 2');
$total_technicians = admin_report_count($conn, 'SELECT COUNT(*) AS total FROM technicians');
$total_ready_technicians = admin_report_count($conn, 'SELECT COUNT(*) AS total FROM technicians WHERE tech_status = 0');
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
$total_personnel = $total_sales + $total_managers + $total_technicians;

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

$report_status_palette = [
    'unassigned' => ['tone' => 'amber', 'color' => '#D97706'],
    'assigned' => ['tone' => 'blue', 'color' => '#2563EB'],
    'accepted' => ['tone' => 'cyan', 'color' => '#0891B2'],
    'received' => ['tone' => 'indigo', 'color' => '#4F46E5'],
    'working' => ['tone' => 'violet', 'color' => '#7C3AED'],
    'review' => ['tone' => 'orange', 'color' => '#EA580C'],
    'done' => ['tone' => 'green', 'color' => '#16A34A'],
    'rejected' => ['tone' => 'brown', 'color' => '#9A3412'],
    'cancelled' => ['tone' => 'red', 'color' => '#DC2626'],
    'overdue' => ['tone' => 'crimson', 'color' => '#BE123C'],
];

$status_meta = [
    'unassigned' => ['label' => 'ยังไม่ได้มอบหมาย', 'tone' => $report_status_palette['unassigned']['tone'], 'icon' => 'fa-inbox'],
    'assigned' => ['label' => 'รอช่างรับงาน', 'tone' => $report_status_palette['assigned']['tone'], 'icon' => 'fa-clipboard-list'],
    'accepted' => ['label' => 'ช่างรับงานแล้ว', 'tone' => $report_status_palette['accepted']['tone'], 'icon' => 'fa-hand'],
    'working' => ['label' => 'กำลังติดตั้ง', 'tone' => $report_status_palette['working']['tone'], 'icon' => 'fa-screwdriver-wrench'],
    'done' => ['label' => 'เสร็จสิ้น', 'tone' => $report_status_palette['done']['tone'], 'icon' => 'fa-circle-check'],
    'cancelled' => ['label' => 'ยกเลิกแล้ว', 'tone' => $report_status_palette['cancelled']['tone'], 'icon' => 'fa-ban'],
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

/* Shared visual palette for every Admin Reports status component. */
$installation_status_meta = [
    'unassigned' => ['label' => 'ยังไม่ได้มอบหมาย', 'tone' => $report_status_palette['unassigned']['tone'], 'color' => $report_status_palette['unassigned']['color']],
    'assigned' => ['label' => 'รอช่างรับงาน', 'tone' => $report_status_palette['assigned']['tone'], 'color' => $report_status_palette['assigned']['color']],
    'accepted' => ['label' => 'ช่างรับงานแล้ว', 'tone' => $report_status_palette['accepted']['tone'], 'color' => $report_status_palette['accepted']['color']],
    'received' => ['label' => 'ยืนยันรับสินค้าแล้ว', 'tone' => $report_status_palette['received']['tone'], 'color' => $report_status_palette['received']['color']],
    'working' => ['label' => 'กำลังติดตั้ง', 'tone' => $report_status_palette['working']['tone'], 'color' => $report_status_palette['working']['color']],
    'review' => ['label' => 'รอหัวหน้าช่างยืนยัน', 'tone' => $report_status_palette['review']['tone'], 'color' => $report_status_palette['review']['color']],
    'done' => ['label' => 'เสร็จสิ้น', 'tone' => $report_status_palette['done']['tone'], 'color' => $report_status_palette['done']['color']],
    'rejected' => ['label' => 'ช่างปฏิเสธงาน', 'tone' => $report_status_palette['rejected']['tone'], 'color' => $report_status_palette['rejected']['color']],
    'cancelled' => ['label' => 'ยกเลิกแล้ว', 'tone' => $report_status_palette['cancelled']['tone'], 'color' => $report_status_palette['cancelled']['color']],
];
$report_workflow_status_meta = [
    'unassigned' => ['label' => 'ยังไม่ได้มอบหมาย', 'tone' => $report_status_palette['unassigned']['tone'], 'icon' => 'fa-inbox'],
    'assigned' => ['label' => 'รอช่างรับงาน', 'tone' => $report_status_palette['assigned']['tone'], 'icon' => 'fa-clipboard-list'],
    'accepted' => ['label' => 'ช่างรับงานแล้ว', 'tone' => $report_status_palette['accepted']['tone'], 'icon' => 'fa-hand'],
    'received' => ['label' => 'ยืนยันรับสินค้าแล้ว', 'tone' => $report_status_palette['received']['tone'], 'icon' => 'fa-box-open'],
    'working' => ['label' => 'กำลังติดตั้ง', 'tone' => $report_status_palette['working']['tone'], 'icon' => 'fa-screwdriver-wrench'],
    'review' => ['label' => 'รอหัวหน้าช่างยืนยัน', 'tone' => $report_status_palette['review']['tone'], 'icon' => 'fa-clock'],
    'done' => ['label' => 'เสร็จสิ้น', 'tone' => $report_status_palette['done']['tone'], 'icon' => 'fa-circle-check'],
    'rejected' => ['label' => 'ช่างปฏิเสธงาน', 'tone' => $report_status_palette['rejected']['tone'], 'icon' => 'fa-user-slash'],
    'cancelled' => ['label' => 'ยกเลิกแล้ว', 'tone' => $report_status_palette['cancelled']['tone'], 'icon' => 'fa-ban'],
];
$report_status_case_sql = "CASE
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
END";
$report_filter_status_options = $report === 'overview'
    ? ['unassigned', 'in_progress', 'done', 'cancelled']
    : (in_array($report, ['installations', 'revenue'], true) ? array_keys($report_workflow_status_meta) : []);
if ($report_filter_status !== 'in_progress' && !in_array($report_filter_status, $report_filter_status_options, true)) {
    $report_filter_status = '';
}
$latest_filter_conditions = ['1=1'];
$latest_filter_params = [];
$latest_filter_types = '';
if ($report_filter_search !== '') {
    $search_like = '%' . $report_filter_search . '%';
    if ($report === 'overview') {
        $latest_filter_conditions[] = '(latest.setup_id LIKE ? OR latest.customer_name LIKE ?)';
        $latest_filter_params = [$search_like, $search_like];
        $latest_filter_types = 'ss';
    } else {
        $latest_filter_conditions[] = '(latest.setup_id LIKE ? OR latest.customer_name LIKE ? OR latest.seller_name LIKE ? OR latest.technician_name LIKE ?)';
        $latest_filter_params = [$search_like, $search_like, $search_like, $search_like];
        $latest_filter_types = 'ssss';
    }
}
if ($report_filter_status === 'in_progress') {
    $latest_filter_conditions[] = "latest.report_status IN ('assigned', 'accepted', 'received', 'working', 'review')";
} elseif ($report_filter_status !== '') {
    $latest_filter_conditions[] = 'latest.report_status = ?';
    $latest_filter_params[] = $report_filter_status;
    $latest_filter_types .= 's';
}
$latest_filter_where = implode(' AND ', $latest_filter_conditions);
$latest_installation_rows_params = array_merge($setup_date_params, $latest_filter_params);
$latest_installation_rows_types = $setup_date_types . $latest_filter_types;
$latest_installation_source_sql = "SELECT
    s.setup_id,
    COALESCE(NULLIF(TRIM(c.customer_name), ''), '-') AS customer_name,
    COALESCE(NULLIF(TRIM(seller.user_name), ''), '-') AS seller_name,
    COALESCE(NULLIF(TRIM(t.tech_fullname), ''), NULLIF(TRIM(t.tech_name), ''), '-') AS technician_name,
    s.created_at,
    a.assign_install_date,
    {$report_status_case_sql} AS report_status
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
 WHERE {$setup_date_where}";
$latest_installation_rows = [];
if ($report === 'overview' || $report === 'installations') {
    $latest_installation_rows = admin_report_rows(
        $conn,
        "SELECT latest.*
         FROM ({$latest_installation_source_sql}) latest
         WHERE {$latest_filter_where}
         ORDER BY latest.created_at DESC, latest.setup_id DESC
         LIMIT 10",
        $latest_installation_rows_types,
        $latest_installation_rows_params
    );
}
$installation_status_rows = admin_report_rows(
    $conn,
    "SELECT latest.report_status, COUNT(*) AS total
     FROM ({$latest_installation_source_sql}) latest
     WHERE {$latest_filter_where}
     GROUP BY latest.report_status
     ORDER BY FIELD(latest.report_status, 'unassigned', 'assigned', 'accepted', 'received', 'working', 'review', 'done', 'rejected', 'cancelled')",
    $latest_installation_rows_types,
    $latest_installation_rows_params
);
$installation_status_raw_counts = [];
foreach ($installation_status_rows as $row) {
    $status_key = (string) ($row['report_status'] ?? '');
    $installation_status_raw_counts[$status_key] = ($installation_status_raw_counts[$status_key] ?? 0)
        + (int) ($row['total'] ?? 0);
}

$completed_setup_count = $table_by_status['done']['total'];
$cancelled_setup_count = $table_by_status['cancelled']['total'];
$ongoing_setup_count = $table_by_status['assigned']['total']
    + $table_by_status['accepted']['total']
    + $table_by_status['working']['total'];
$report_total = array_sum(array_column($table_by_status, 'total'));
$report_completion_rate = $report_total > 0 ? round(($completed_setup_count / $report_total) * 100, 1) : 0;
$report_average_install = $report_total > 0 ? $total_install_revenue / $report_total : 0;
$overview_metrics = [
    ['label' => 'ใบงานติดตั้งทั้งหมด', 'value' => number_format($total_setups_all), 'tone' => 'blue', 'icon' => 'fa-clipboard-list'],
    ['label' => 'บุคลากรทั้งหมด', 'value' => number_format($total_personnel), 'tone' => 'cyan', 'icon' => 'fa-users'],
    ['label' => 'ลูกค้าทั้งหมด', 'value' => number_format($total_customers), 'tone' => 'amber', 'icon' => 'fa-address-card'],
    ['label' => 'สินค้าทั้งหมด', 'value' => number_format($total_products), 'tone' => 'violet', 'icon' => 'fa-box'],
    ['label' => 'งานเสร็จสิ้นทั้งหมด', 'value' => number_format($completed_setup_count), 'tone' => 'green', 'icon' => 'fa-circle-check'],
];

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
$manager_report_rows = [];
$technicians_report_rows = [];
$technicians_table_rows = [];
$sales_table_rows = [];
$manager_table_rows = [];
$technician_table_rows = [];
$personnel_tables_by_type = [];
$personnel_summary_cards_by_type = [];
$personnel_view = [
    'type' => $personnel_filter_type,
    'summary_cards' => [],
    'tables' => [],
];
$personnel_user_conditions = ['1=1'];
$personnel_user_params = [];
$personnel_user_types = '';
$personnel_tech_conditions = ['1=1'];
$personnel_tech_params = [];
$personnel_tech_types = '';
if ($personnel_filter_search !== '') {
    $personnel_search_like = '%' . $personnel_filter_search . '%';
    $personnel_user_conditions[] = '(u.user_id LIKE ? OR u.user_name LIKE ?)';
    $personnel_user_params = [$personnel_search_like, $personnel_search_like];
    $personnel_user_types = 'ss';
    $personnel_tech_conditions[] = '(t.tech_id LIKE ? OR t.tech_name LIKE ?)';
    $personnel_tech_params = [$personnel_search_like, $personnel_search_like];
    $personnel_tech_types = 'ss';
}
$personnel_user_filter_where = implode(' AND ', $personnel_user_conditions);
$personnel_tech_filter_where = implode(' AND ', $personnel_tech_conditions);
$personnel_sum_stat = static function (array $rows, int $index): int {
    return array_sum(array_map(static fn (array $row): int => (int) ($row['stats'][$index] ?? 0), $rows));
};

if ($report === 'users') {
    if (in_array($personnel_filter_type, ['all', 'sale'], true)) {
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
               AND {$personnel_user_filter_where}
             GROUP BY u.user_id, u.user_name
             ORDER BY setup_total DESC, u.user_name",
            $setup_date_types . $personnel_user_types,
            array_merge($setup_date_params, $personnel_user_params)
        );
        $sales_table_rows = array_map(static function (array $row): array {
            return [
                'id' => (string) ($row['seller_id'] ?? '-'),
                'name' => (string) ($row['seller_name'] ?? '-'),
                'stats' => [
                    (int) ($row['setup_total'] ?? 0),
                    (int) ($row['done_total'] ?? 0),
                    (int) ($row['cancelled_total'] ?? 0),
                ],
            ];
        }, $sales_report_rows);
        $personnel_summary_cards_by_type['sale'] = [
            ['label' => 'พนักงานขายทั้งหมด', 'value' => count($sales_table_rows), 'tone' => 'cyan', 'icon' => 'fa-user-tag'],
            ['label' => 'ใบงานที่สร้าง', 'value' => $personnel_sum_stat($sales_table_rows, 0), 'tone' => 'blue', 'icon' => 'fa-clipboard-list'],
            ['label' => 'เสร็จสิ้น', 'value' => $personnel_sum_stat($sales_table_rows, 1), 'tone' => 'green', 'icon' => 'fa-circle-check'],
            ['label' => 'ยกเลิกแล้ว', 'value' => $personnel_sum_stat($sales_table_rows, 2), 'tone' => 'red', 'icon' => 'fa-ban'],
        ];
        $personnel_tables_by_type['sale'] = [
            'type' => 'sale',
            'title' => 'สรุปพนักงานขาย',
            'description' => 'ใบงานที่สร้างและผลลัพธ์ตามช่วงวันที่เลือก',
            'row_icon' => 'fa-user-tie',
            'row_tone' => 'cyan',
            'empty_icon' => 'fa-regular fa-user',
            'empty_message' => $personnel_filter_search !== '' ? 'ไม่พบข้อมูลที่ตรงกับตัวกรอง' : 'ยังไม่มีข้อมูลพนักงานขาย',
            'columns' => ['รหัสพนักงาน', 'ชื่อ', 'ใบงานที่สร้าง', 'เสร็จสิ้น', 'ยกเลิก'],
            'rows' => $sales_table_rows,
        ];
    }

    if (in_array($personnel_filter_type, ['all', 'manager'], true)) {
        $manager_report_rows = admin_report_rows(
            $conn,
            "SELECT
                u.user_id AS manager_id,
                u.user_name AS manager_name,
                COUNT(DISTINCT a.setup_id) AS assigned_total,
                COUNT(DISTINCT CASE
                    WHEN s.setup_status = 4 AND s.completed_at IS NOT NULL THEN a.setup_id
                END) AS done_total
             FROM `user` u
             LEFT JOIN assignment a
               ON a.assign_by = u.user_id
              AND a.assign_id = (
                  SELECT a2.assign_id
                  FROM assignment a2
                  WHERE a2.setup_id = a.setup_id
                  ORDER BY a2.assign_date DESC, a2.assign_id DESC
                  LIMIT 1
              )
              AND {$assignment_date_where}
             LEFT JOIN setup s ON s.setup_id = a.setup_id
             WHERE u.user_role = 1
               AND {$personnel_user_filter_where}
             GROUP BY u.user_id, u.user_name
             ORDER BY assigned_total DESC, u.user_name",
            $assignment_date_types . $personnel_user_types,
            array_merge($assignment_date_params, $personnel_user_params)
        );
        $manager_table_rows = array_map(static function (array $row): array {
            return [
                'id' => (string) ($row['manager_id'] ?? '-'),
                'name' => (string) ($row['manager_name'] ?? '-'),
                'stats' => [
                    (int) ($row['assigned_total'] ?? 0),
                    (int) ($row['done_total'] ?? 0),
                ],
            ];
        }, $manager_report_rows);
        $personnel_summary_cards_by_type['manager'] = [
            ['label' => 'หัวหน้าช่างทั้งหมด', 'value' => count($manager_table_rows), 'tone' => 'amber', 'icon' => 'fa-user-tie'],
            ['label' => 'งานที่มอบหมาย', 'value' => $personnel_sum_stat($manager_table_rows, 0), 'tone' => 'blue', 'icon' => 'fa-clipboard-list'],
            ['label' => 'งานที่เสร็จสิ้น', 'value' => $personnel_sum_stat($manager_table_rows, 1), 'tone' => 'green', 'icon' => 'fa-circle-check'],
        ];
        $personnel_tables_by_type['manager'] = [
            'type' => 'manager',
            'title' => 'สรุปหัวหน้าช่าง',
            'description' => 'นับ assignment ล่าสุดต่อใบงานตามผู้มอบหมาย',
            'row_icon' => 'fa-user-tie',
            'row_tone' => 'amber',
            'empty_icon' => 'fa-regular fa-user',
            'empty_message' => $personnel_filter_search !== '' ? 'ไม่พบข้อมูลที่ตรงกับตัวกรอง' : 'ยังไม่มีข้อมูลหัวหน้าช่าง',
            'columns' => ['รหัสพนักงาน', 'ชื่อ', 'งานที่มอบหมาย', 'เสร็จสิ้น'],
            'rows' => $manager_table_rows,
        ];
    }

    if (in_array($personnel_filter_type, ['all', 'technician'], true)) {
        $technicians_report_rows = admin_report_rows(
            $conn,
            "SELECT
                t.tech_id,
                COALESCE(NULLIF(t.tech_name, ''), NULLIF(t.tech_fullname, ''), '-') AS tech_display_name,
                t.tech_status,
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
             WHERE {$personnel_tech_filter_where}
             GROUP BY t.tech_id, t.tech_fullname, t.tech_name, t.tech_status
             ORDER BY assigned_total DESC, tech_display_name",
            $assignment_date_types . $personnel_tech_types,
            array_merge($assignment_date_params, $personnel_tech_params)
        );
        $technicians_table_rows = $technicians_report_rows;
        $technician_table_rows = array_map(static function (array $row): array {
            return [
                'id' => (string) ($row['tech_id'] ?? '-'),
                'name' => (string) ($row['tech_display_name'] ?? '-'),
                'ready' => (int) ($row['tech_status'] ?? 1) === 0,
                'stats' => [
                    (int) ($row['assigned_total'] ?? 0),
                    (int) ($row['done_total'] ?? 0),
                ],
            ];
        }, $technicians_table_rows);
        $personnel_summary_cards_by_type['technician'] = [
            ['label' => 'ช่างติดตั้งทั้งหมด', 'value' => count($technician_table_rows), 'tone' => 'green', 'icon' => 'fa-screwdriver-wrench'],
            ['label' => 'ช่างพร้อมรับงาน', 'value' => count(array_filter($technician_table_rows, static fn (array $row): bool => !empty($row['ready']))), 'tone' => 'green', 'icon' => 'fa-circle-check'],
            ['label' => 'ได้รับมอบหมาย', 'value' => $personnel_sum_stat($technician_table_rows, 0), 'tone' => 'blue', 'icon' => 'fa-clipboard-list'],
            ['label' => 'เสร็จสิ้น', 'value' => $personnel_sum_stat($technician_table_rows, 1), 'tone' => 'green', 'icon' => 'fa-circle-check'],
        ];
        $personnel_tables_by_type['technician'] = [
            'type' => 'technician',
            'title' => 'สรุปช่างติดตั้ง',
            'description' => 'นับ assignment ล่าสุดต่อใบงาน และแยกตาม tech_id',
            'row_icon' => 'fa-screwdriver-wrench',
            'row_tone' => 'green',
            'empty_icon' => 'fa-solid fa-screwdriver-wrench',
            'empty_message' => $personnel_filter_search !== '' ? 'ไม่พบข้อมูลที่ตรงกับตัวกรอง' : 'ยังไม่มีข้อมูลช่างติดตั้ง',
            'columns' => ['รหัสช่าง', 'ชื่อ', 'ความพร้อม', 'ได้รับมอบหมาย', 'เสร็จสิ้น'],
            'rows' => $technician_table_rows,
        ];
    }

    if ($personnel_filter_type === 'all') {
        $personnel_view['summary_cards'] = [
            ['label' => 'บุคลากรทั้งหมด', 'value' => count($sales_table_rows) + count($manager_table_rows) + count($technician_table_rows), 'tone' => 'blue', 'icon' => 'fa-users'],
            ['label' => 'พนักงานขาย', 'value' => count($sales_table_rows), 'tone' => 'cyan', 'icon' => 'fa-user-tag'],
            ['label' => 'หัวหน้าช่าง', 'value' => count($manager_table_rows), 'tone' => 'amber', 'icon' => 'fa-user-tie'],
            ['label' => 'ช่างติดตั้ง', 'value' => count($technician_table_rows), 'tone' => 'green', 'icon' => 'fa-screwdriver-wrench'],
        ];
        $personnel_view['tables'] = [
            $personnel_tables_by_type['sale'],
            $personnel_tables_by_type['manager'],
            $personnel_tables_by_type['technician'],
        ];
    } else {
        $personnel_view['summary_cards'] = $personnel_summary_cards_by_type[$personnel_filter_type];
        $personnel_view['tables'] = [$personnel_tables_by_type[$personnel_filter_type]];
    }
}

$installation_filtered_total = 0;
if ($report === 'installations') {
    $installation_workflow_status_meta = [
        'unassigned' => ['label' => 'ยังไม่ได้มอบหมาย', 'tone' => $report_status_palette['unassigned']['tone'], 'icon' => 'fa-inbox'],
        'assigned' => ['label' => 'รอช่างรับงาน', 'tone' => $report_status_palette['assigned']['tone'], 'icon' => 'fa-clipboard-list'],
        'accepted' => ['label' => 'ช่างรับงานแล้ว', 'tone' => $report_status_palette['accepted']['tone'], 'icon' => 'fa-hand'],
        'received' => ['label' => 'ยืนยันรับสินค้าแล้ว', 'tone' => $report_status_palette['received']['tone'], 'icon' => 'fa-box-open'],
        'working' => ['label' => 'กำลังติดตั้ง', 'tone' => $report_status_palette['working']['tone'], 'icon' => 'fa-screwdriver-wrench'],
        'review' => ['label' => 'รอหัวหน้าช่างยืนยัน', 'tone' => $report_status_palette['review']['tone'], 'icon' => 'fa-clock'],
        'done' => ['label' => 'เสร็จสิ้น', 'tone' => $report_status_palette['done']['tone'], 'icon' => 'fa-circle-check'],
        'rejected' => ['label' => 'ปฏิเสธงาน', 'tone' => $report_status_palette['rejected']['tone'], 'icon' => 'fa-user-slash'],
        'cancelled' => ['label' => 'ยกเลิกแล้ว', 'tone' => $report_status_palette['cancelled']['tone'], 'icon' => 'fa-ban'],
    ];
    $installation_workflow_counts = [];
    foreach (array_keys($installation_workflow_status_meta) as $status_key) {
        $installation_workflow_counts[$status_key] = (int) ($installation_status_raw_counts[$status_key] ?? 0);
    }

    $installation_filtered_total = array_sum($installation_workflow_counts);

    $installation_in_progress_count = $installation_workflow_counts['assigned']
        + $installation_workflow_counts['accepted']
        + $installation_workflow_counts['received']
        + $installation_workflow_counts['working']
        + $installation_workflow_counts['review'];

    $installation_metric_labels = [
        'total' => 'งานทั้งหมด',
        'unassigned' => 'รอมอบหมาย',
        'in_progress' => 'กำลังดำเนินการ',
        'review' => 'รอหัวหน้าช่างยืนยัน',
        'done' => 'เสร็จสิ้น',
        'cancelled' => 'ยกเลิกแล้ว',
    ];
    $installation_metric_key_by_status = [
        'unassigned' => 'unassigned',
        'assigned' => 'in_progress',
        'accepted' => 'in_progress',
        'received' => 'in_progress',
        'working' => 'in_progress',
        'review' => 'review',
        'done' => 'done',
        'rejected' => 'total',
        'cancelled' => 'cancelled',
    ];
    if ($report_filter_status !== '' && isset($installation_metric_key_by_status[$report_filter_status])) {
        $metric_key = $installation_metric_key_by_status[$report_filter_status];
        $installation_metric_labels[$metric_key] = $report_workflow_status_meta[$report_filter_status]['label'];
    }

    $installation_detail_rows = $latest_installation_rows;

    $report_metrics = [
        ['key' => 'total', 'label' => $installation_metric_labels['total'], 'value' => number_format($installation_filtered_total), 'tone' => 'blue', 'icon' => 'fa-layer-group'],
        ['key' => 'unassigned', 'label' => $installation_metric_labels['unassigned'], 'value' => number_format($installation_workflow_counts['unassigned']), 'tone' => 'amber', 'icon' => 'fa-inbox'],
        ['key' => 'in_progress', 'label' => $installation_metric_labels['in_progress'], 'value' => number_format($installation_in_progress_count), 'tone' => 'violet', 'icon' => 'fa-bars-progress'],
        ['key' => 'review', 'label' => $installation_metric_labels['review'], 'value' => number_format($installation_workflow_counts['review']), 'tone' => 'orange', 'icon' => 'fa-clock'],
        ['key' => 'done', 'label' => $installation_metric_labels['done'], 'value' => number_format($installation_workflow_counts['done']), 'tone' => 'green', 'icon' => 'fa-circle-check'],
        ['key' => 'cancelled', 'label' => $installation_metric_labels['cancelled'], 'value' => number_format($installation_workflow_counts['cancelled']), 'tone' => 'red', 'icon' => 'fa-ban'],
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
    // Preserve the Revenue tab's established status precedence while adding its filters.
    $fee_report_status_case_sql = "CASE
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
    END";
    $fee_source_sql = "SELECT s.setup_id, s.setup_status, s.completed_at,
                              COALESCE(detail_summary.install_total, 0) AS install_total,
                              {$fee_report_status_case_sql} AS report_status
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
                       WHERE {$setup_date_where}";
    $fee_monthly_source_sql = "SELECT s.setup_id, s.setup_status, s.completed_at,
                                      COALESCE(detail_summary.install_total, 0) AS install_total,
                                      {$fee_report_status_case_sql} AS report_status
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
                               WHERE 1=1";
    $fee_status_filter_conditions = ['1=1'];
    $fee_status_filter_params = [];
    $fee_status_filter_types = '';
    if ($report_filter_status === 'in_progress') {
        $fee_status_filter_conditions[] = "fee.report_status IN ('assigned', 'accepted', 'received', 'working', 'review')";
    } elseif ($report_filter_status !== '') {
        $fee_status_filter_conditions[] = 'fee.report_status = ?';
        $fee_status_filter_params[] = $report_filter_status;
        $fee_status_filter_types = 's';
    }
    $fee_status_filter_where = implode(' AND ', $fee_status_filter_conditions);
    $fee_filtered_rows_params = array_merge($setup_date_params, $fee_status_filter_params);
    $fee_filtered_rows_types = $setup_date_types . $fee_status_filter_types;
    $fee_setup_rows = admin_report_rows(
        $conn,
        "SELECT fee.*
         FROM ({$fee_source_sql}) fee
         WHERE {$fee_status_filter_where}",
        $fee_filtered_rows_types,
        $fee_filtered_rows_params
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
        "SELECT fee.report_status,
                COUNT(*) AS total,
                COALESCE(SUM(fee.install_total), 0) AS fee_total
         FROM ({$fee_source_sql}) fee
         WHERE {$fee_status_filter_where}
         GROUP BY report_status
         ORDER BY FIELD(report_status, 'unassigned', 'assigned', 'accepted', 'received', 'working', 'review', 'done', 'rejected', 'cancelled')",
        $fee_filtered_rows_types,
        $fee_filtered_rows_params
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
    $fee_status_table_meta = $installation_status_meta;
    if ($report_filter_status !== '') {
        $fee_status_keys = $report_filter_status === 'in_progress'
            ? ['assigned', 'accepted', 'received', 'working', 'review']
            : [$report_filter_status];
        $fee_status_table_meta = array_intersect_key($installation_status_meta, array_flip($fee_status_keys));
    }
    $fee_in_progress_total = 0.0;
    foreach (['assigned', 'accepted', 'received', 'working', 'review'] as $in_progress_status_key) {
        $fee_in_progress_total += (float) ($fee_status_by_key[$in_progress_status_key]['fee_total'] ?? 0);
    }

    $fee_month_labels = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $fee_monthly = [];
    for ($month = 1; $month <= 12; $month++) {
        $fee_monthly[$month] = ['jobs' => 0, 'fee' => 0.0];
    }
    foreach (admin_report_rows(
        $conn,
        "SELECT MONTH(fee.completed_at) AS month_index,
                COUNT(*) AS job_total,
                COALESCE(SUM(fee.install_total), 0) AS fee_total
         FROM ({$fee_monthly_source_sql}) fee
         WHERE fee.setup_status = 4
           AND fee.completed_at IS NOT NULL
           AND fee.completed_at >= ? AND fee.completed_at < ?
           AND {$fee_status_filter_where}
         GROUP BY MONTH(fee.completed_at)",
        'ss' . $fee_status_filter_types,
        array_merge([$fee_year_start, $fee_next_year_start], $fee_status_filter_params)
    ) as $fee_month_row) {
        $fee_month_index = (int) ($fee_month_row['month_index'] ?? 0);
        if ($fee_month_index >= 1 && $fee_month_index <= 12) {
            $fee_monthly[$fee_month_index] = [
                'jobs' => (int) ($fee_month_row['job_total'] ?? 0),
                'fee' => (float) ($fee_month_row['fee_total'] ?? 0),
            ];
        }
    }

    $report_metrics = [
        ['label' => 'ค่าติดตั้งรวมทั้งหมด', 'value' => number_format($fee_total, 2) . ' ฿', 'detail' => 'รวมใบงานที่มีรายการค่าติดตั้ง', 'tone' => 'violet', 'icon' => 'fa-baht-sign'],
        ['label' => 'ค่าติดตั้งงานเสร็จสิ้น', 'value' => number_format($fee_completed_total, 2) . ' ฿', 'detail' => 'จากงานที่ปิดเรียบร้อย', 'tone' => 'green', 'icon' => 'fa-circle-check'],
        ['label' => 'ค่าติดตั้งงานกำลังดำเนินการ', 'value' => number_format($fee_in_progress_total, 2) . ' ฿', 'detail' => 'จากงานที่อยู่ระหว่างดำเนินการ', 'tone' => 'blue', 'icon' => 'fa-bars-progress'],
        ['label' => 'ค่าติดตั้งงานยกเลิก', 'value' => number_format($fee_cancelled_total, 2) . ' ฿', 'detail' => 'จากงานที่ยกเลิก', 'tone' => 'red', 'icon' => 'fa-ban'],
        ['label' => 'ค่าเฉลี่ยค่าติดตั้งต่อใบงาน', 'value' => number_format($fee_average, 2) . ' ฿', 'detail' => 'เฉลี่ยจากใบงานที่มีรายการติดตั้ง', 'tone' => 'blue', 'icon' => 'fa-chart-simple'],
    ];
}

$ajax_section = trim((string) ($_GET['section'] ?? ''));
if ((string) ($_GET['ajax'] ?? '') === '1') {
    if (($report === 'overview' && $ajax_section === 'overview-latest') || ($report === 'installations' && $ajax_section === 'installation-latest')) {
        $ajax_latest_rows = array_map(static function (array $row) use ($report_workflow_status_meta): array {
            $status = $report_workflow_status_meta[(string) ($row['report_status'] ?? '')] ?? ['label' => 'ไม่ระบุสถานะ', 'tone' => 'blue', 'icon' => 'fa-circle-question'];
            return [
                'setup_id' => (string) ($row['setup_id'] ?? '-'),
                'customer_name' => (string) ($row['customer_name'] ?? '-'),
                'seller_name' => (string) ($row['seller_name'] ?? '-'),
                'technician_name' => (string) ($row['technician_name'] ?? '-'),
                'created_display' => !empty($row['created_at']) ? date('d/m/Y', strtotime((string) $row['created_at'])) : '-',
                'install_display' => !empty($row['assign_install_date']) ? date('d/m/Y', strtotime((string) $row['assign_install_date'])) : '-',
                'status' => [
                    'label' => $status['label'],
                    'tone' => $status['tone'],
                    'icon' => $status['icon'],
                ],
            ];
        }, $latest_installation_rows);
        $ajax_installation_metrics = [];
        $ajax_installation_metric_labels = [];
        if ($report === 'installations') {
            $ajax_installation_metrics = [
                'total' => $installation_filtered_total,
                'unassigned' => $installation_workflow_counts['unassigned'],
                'in_progress' => $installation_in_progress_count,
                'review' => $installation_workflow_counts['review'],
                'done' => $installation_workflow_counts['done'],
                'cancelled' => $installation_workflow_counts['cancelled'],
            ];
            $ajax_installation_metric_labels = $installation_metric_labels;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'section' => $ajax_section,
            'count' => $report === 'installations' ? $installation_filtered_total : count($ajax_latest_rows),
            'rows' => $ajax_latest_rows,
            'metrics' => $ajax_installation_metrics,
            'metric_labels' => $ajax_installation_metric_labels,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($report === 'users' && $ajax_section === 'personnel') {
        $personnel_result_count = array_sum(array_map(
            static fn (array $table): int => count($table['rows'] ?? []),
            $personnel_view['tables']
        ));
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'section' => $ajax_section,
            'type' => $personnel_view['type'],
            'summary_cards' => $personnel_view['summary_cards'],
            'tables' => $personnel_view['tables'],
            'count' => $personnel_result_count,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($report === 'revenue' && $ajax_section === 'revenue') {
        $ajax_fee_status_rows = [];
        foreach ($fee_status_table_meta as $status_key => $status_item) {
            $workflow_status = $report_workflow_status_meta[$status_key] ?? [];
            $ajax_fee_status_rows[] = [
                'key' => $status_key,
                'label' => $status_item['label'],
                'tone' => $status_item['tone'],
                'icon' => $workflow_status['icon'] ?? 'fa-circle',
                'total' => (int) ($fee_status_by_key[$status_key]['total'] ?? 0),
                'fee_total' => (float) ($fee_status_by_key[$status_key]['fee_total'] ?? 0),
            ];
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'section' => $ajax_section,
            'metrics' => [
                'total' => $fee_total,
                'done' => $fee_completed_total,
                'in_progress' => $fee_in_progress_total,
                'cancelled' => $fee_cancelled_total,
                'average' => $fee_average,
            ],
            'year' => $fee_year,
            'labels' => array_values($fee_month_labels),
            'monthly' => array_values($fee_monthly),
            'status_rows' => $ajax_fee_status_rows,
            'count' => $fee_setup_count,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$report_print_titles = [
    'overview' => 'รายงานภาพรวมระบบ',
    'installations' => 'รายงานงานติดตั้ง',
    'users' => 'รายงานบุคลากร',
    'revenue' => 'รายงานค่าติดตั้ง',
];
$report_print_title = $report_print_titles[$report] ?? $report_print_titles['overview'];
$report_system = system_company_data($conn);
$report_system_logo_url = $report_system['system_logo_url'];
$report_system_name = $report_system['system_name'];
$report_company_address = $report_system['company_address'];
$report_company_tax_id = $report_system['tax_id'];
$report_print_date = (new DateTimeImmutable('now'))->format('d/m/Y');

$page_title = 'รายงานภาพรวมระบบ';
$page_subtitle = 'สรุปข้อมูลผู้ใช้งาน ใบงานติดตั้ง สถานะงาน และค่าติดตั้งของระบบ';

layout_header('รายงาน', 'reports', 'ศูนย์รวมรายงานระบบสำหรับผู้ดูแล');
?>
<link
    rel="stylesheet"
    href="<?= h(app_asset_url('admin/assets/css/reports.css')) ?>?v=<?= h(asset_version('admin/assets/css/reports.css')) ?>"
>

<?php include __DIR__ . '/report_view.php'; ?>
<script src="<?= h(app_asset_url('admin/assets/js/report_filters.js')) ?>?v=<?= h(asset_version('admin/assets/js/report_filters.js')) ?>"></script>

<?php layout_footer(); ?>

