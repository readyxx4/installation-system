<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

function count_table(mysqli $conn, string $table): int
{
    try {
        $result = $conn->query("SELECT COUNT(*) AS total FROM `$table`");
        $row = $result->fetch_assoc();
        return (int) ($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function count_users_by_role(mysqli $conn, array $roles): int
{
    if (empty($roles)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $types = str_repeat('i', count($roles));

    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM `user`
            WHERE user_role IN ($placeholders)
        ");

        $stmt->bind_param($types, ...$roles);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return (int) ($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function safe_result(mysqli $conn, string $sql)
{
    try {
        return $conn->query($sql);
    } catch (Throwable $e) {
        return false;
    }
}

function table_exists(mysqli $conn, string $table): bool
{
    try {
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
    } catch (Throwable $e) {
        return false;
    }
}

function column_exists(mysqli $conn, string $table, string $column): bool
{
    try {
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
    } catch (Throwable $e) {
        return false;
    }
}

function tech_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'พร้อมรับงาน',
        '1' => 'ไม่พร้อมรับงาน',
        default => 'ไม่ทราบสถานะ',
    };
}

function tech_status_badge($status): string
{
    return match ((string) $status) {
        '0' => 'green',
        '1' => 'red',
        default => 'orange',
    };
}

function setup_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'ยังไม่ได้มอบหมาย',
        '1' => 'มอบหมายงานแล้ว',
        '2' => 'ช่างรับงานแล้ว',
        '3' => 'กำลังติดตั้ง',
        '4' => 'เสร็จสิ้น',
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
        default => 'slate',
    };
}

function dashboard_assignment_is_overdue(array $row): bool
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

$total_system_users = count_users_by_role($conn, [1, 2, 3]);
$total_customers = count_table($conn, 'customers');
$total_technicians = count_table($conn, 'technicians');
$total_product_types = count_table($conn, 'product_type');
$total_products = count_table($conn, 'product');
$total_setups = count_table($conn, 'setup');
$dashboard_updated_at = date('H:i');

$total_technicians_ready = 0;
$total_technicians_unavailable = 0;
$technician_status_result = safe_result($conn, "
    SELECT tech_status, COUNT(*) AS total
    FROM technicians
    GROUP BY tech_status
");

if ($technician_status_result) {
    while ($row = $technician_status_result->fetch_assoc()) {
        if ((string) ($row['tech_status'] ?? '') === '1') {
            $total_technicians_unavailable += (int) ($row['total'] ?? 0);
        } else {
            $total_technicians_ready += (int) ($row['total'] ?? 0);
        }
    }
}

$has_assignment_table = table_exists($conn, 'assignment');
$has_assignment_install_date = $has_assignment_table && column_exists($conn, 'assignment', 'assign_install_date');
$has_assignment_install_time = $has_assignment_table && column_exists($conn, 'assignment', 'assign_install_time');
$has_assignment_install_end_time = $has_assignment_table && column_exists($conn, 'assignment', 'assign_install_end_time');

$setup_rows = [];
$status_counts = [
    'unassigned' => 0,
    'assigned' => 0,
    'accepted' => 0,
    'working' => 0,
    'done' => 0,
    'overdue' => 0,
    'canceled' => 0,
];

if (table_exists($conn, 'setup')) {
    $assign_install_date_select = $has_assignment_install_date ? 'a.assign_install_date' : 'NULL AS assign_install_date';
    $assign_install_time_select = $has_assignment_install_time ? 'a.assign_install_time' : 'NULL AS assign_install_time';
    $assign_install_end_time_select = $has_assignment_install_end_time ? 'a.assign_install_end_time' : 'NULL AS assign_install_end_time';
    $latest_assignment_join = $has_assignment_table ? "
        LEFT JOIN assignment a
            ON a.setup_id = s.setup_id
           AND a.assign_id = (
                SELECT a2.assign_id
                FROM assignment a2
                WHERE a2.setup_id = s.setup_id
                ORDER BY a2.assign_date DESC, a2.assign_id DESC
                LIMIT 1
           )
    " : "
        LEFT JOIN (SELECT NULL AS assign_id, NULL AS setup_id, NULL AS assign_status) a
            ON 1 = 0
    ";

    $setup_overview_result = safe_result($conn, "
        SELECT
            s.setup_id,
            s.setup_status,
            s.created_at,
            a.assign_id,
            a.assign_status,
            {$assign_install_date_select},
            {$assign_install_time_select},
            {$assign_install_end_time_select}
        FROM setup s
        {$latest_assignment_join}
        ORDER BY s.created_at DESC, s.setup_id DESC
    ");

    if ($setup_overview_result) {
        while ($row = $setup_overview_result->fetch_assoc()) {
            $row['is_overdue'] = dashboard_assignment_is_overdue($row);
            $setup_rows[] = $row;

            if (!empty($row['is_overdue'])) {
                $status_counts['overdue']++;
            }

            if (!empty($row['assign_id']) && (string) ($row['assign_status'] ?? '') === '4') {
                $status_counts['canceled']++;
                continue;
            }

            $setup_status = (string) ($row['setup_status'] ?? '0');
            if ($setup_status === '0') {
                $status_counts['unassigned']++;
            } elseif ($setup_status === '1') {
                $status_counts['assigned']++;
            } elseif ($setup_status === '2') {
                $status_counts['accepted']++;
            } elseif ($setup_status === '3') {
                $status_counts['working']++;
            } elseif ($setup_status === '4') {
                $status_counts['done']++;
            }
        }
    }
}

$technician_performance = safe_result($conn, "
    SELECT
        t.tech_id,
        t.tech_name,
        t.tech_phone,
        t.tech_status,
        COALESCE(job_stats.job_count, 0) AS job_count,
        review_stats.avg_rating,
        COALESCE(review_stats.review_count, 0) AS review_count
    FROM technicians t
    LEFT JOIN (
        SELECT
            tech_id,
            COUNT(DISTINCT assign_id) AS job_count
        FROM assignment
        WHERE tech_id IS NOT NULL
          AND assign_status IN (1, 2, 5)
        GROUP BY tech_id
    ) job_stats ON job_stats.tech_id = t.tech_id
    LEFT JOIN (
        SELECT
            latest_assignment.tech_id,
            AVG(r.review_rating) AS avg_rating,
            COUNT(r.review_id) AS review_count
        FROM review r
        INNER JOIN (
            SELECT
                a.setup_id,
                a.tech_id
            FROM assignment a
            WHERE a.tech_id IS NOT NULL
              AND a.assign_status IN (1, 2, 5)
              AND a.assign_id = (
                    SELECT a2.assign_id
                    FROM assignment a2
                    WHERE a2.setup_id = a.setup_id
                      AND a2.tech_id IS NOT NULL
                      AND a2.assign_status IN (1, 2, 5)
                    ORDER BY a2.assign_date DESC, a2.assign_id DESC
                    LIMIT 1
              )
        ) latest_assignment ON latest_assignment.setup_id = r.setup_id
        WHERE r.review_rating IS NOT NULL
        GROUP BY latest_assignment.tech_id
    ) review_stats ON review_stats.tech_id = t.tech_id
    ORDER BY job_count DESC, avg_rating DESC, t.tech_name ASC, t.tech_id ASC
    LIMIT 5
");

$recent_users = safe_result($conn, "
    SELECT
        user_id,
        user_name,
        user_phone,
        user_email,
        user_role
    FROM `user`
    WHERE user_role IN (1, 2, 3)
    ORDER BY user_id DESC
    LIMIT 5
");

$recent_customers = safe_result($conn, "
    SELECT
        customer_id AS user_id,
        customer_name AS user_name,
        customer_phone AS user_phone,
        customer_email AS user_email,
        customer_status
    FROM customers
    ORDER BY customer_id DESC
    LIMIT 5
");

$status_overview = [
    'done' => ['label' => 'เสร็จสิ้น', 'count' => $status_counts['done'], 'badge' => 'green'],
    'working' => ['label' => 'กำลังติดตั้ง', 'count' => $status_counts['working'], 'badge' => 'purple'],
    'accepted' => ['label' => 'ช่างรับงานแล้ว', 'count' => $status_counts['accepted'], 'badge' => 'cyan'],
    'assigned' => ['label' => 'มอบหมายงานแล้ว', 'count' => $status_counts['assigned'], 'badge' => 'blue'],
    'unassigned' => ['label' => 'ยังไม่ได้มอบหมาย', 'count' => $status_counts['unassigned'], 'badge' => 'orange'],
];
$status_count_values = array_map('intval', array_column($status_overview, 'count'));
$status_max_count = max([1, ...$status_count_values]);

layout_header('หน้าหลักผู้ดูแลระบบ', 'dashboard');
?>

<link
    rel="stylesheet"
    href="<?= h(app_asset_url('admin/assets/css/dashboard.css')) ?>?v=<?= h(asset_version('admin/assets/css/dashboard.css')) ?>"
>

<style>
    .admin-dashboard-summary-page {
        height: auto;
        min-height: calc(100vh - 40px);
        overflow: visible;
    }

    .admin-dashboard-summary-page .admin-dashboard-top {
        min-height: auto;
        padding: 18px 22px;
    }

    .admin-dashboard-summary-page .admin-summary-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 12px;
        margin-top: 14px;
    }

    body.app-body.role-3 .admin-dashboard-summary-page .admin-summary-card {
        align-items: flex-start !important;
        height: auto !important;
        min-height: 84px !important;
        max-height: none !important;
        padding: 14px !important;
        border-radius: 12px !important;
    }

    body.app-body.role-3 .admin-dashboard-summary-page .admin-summary-card > div:first-child {
        display: block !important;
    }

    body.app-body.role-3 .admin-dashboard-summary-page .admin-summary-card span,
    body.app-body.role-3 .admin-dashboard-summary-page .admin-summary-card p {
        font-size: 12px !important;
        line-height: 1.35 !important;
    }

    body.app-body.role-3 .admin-dashboard-summary-page .admin-summary-card strong,
    body.app-body.role-3 .admin-dashboard-summary-page .admin-summary-card h2 {
        margin: 5px 0 4px !important;
        font-size: 26px !important;
        line-height: 1.1 !important;
    }

    body.app-body.role-3 .admin-dashboard-summary-page .admin-summary-card small {
        font-size: 11px !important;
        line-height: 1.35 !important;
    }

    .admin-dashboard-brief-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 14px;
        margin-top: 14px;
    }

    .admin-dashboard-summary-page .admin-brief-card {
        min-height: 0;
        padding: 18px;
    }

    .admin-dashboard-summary-page .admin-status-chart {
        display: none;
    }

    .admin-dashboard-summary-page .admin-status-metrics {
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 0;
        margin-top: 12px;
    }

    .admin-dashboard-summary-page .admin-simple-list {
        display: grid;
        gap: 10px;
        margin-top: 12px;
    }

    .admin-dashboard-summary-page .admin-simple-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #E2E8F0;
    }

    .admin-dashboard-summary-page .admin-simple-row:last-child {
        border-bottom: 0;
    }

    .admin-dashboard-summary-page .admin-simple-row span {
        color: #64748B;
        font-size: 13px;
        font-weight: 700;
    }

    .admin-dashboard-summary-page .admin-simple-row strong {
        color: #0F3F7A;
        font-size: 15px;
        font-weight: 900;
        white-space: nowrap;
    }

    @media (max-width: 1100px) {
        .admin-dashboard-summary-page .admin-summary-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .admin-dashboard-brief-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .admin-dashboard-summary-page .admin-summary-grid,
        .admin-dashboard-summary-page .admin-status-metrics {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="admin-dashboard-v2 admin-dashboard-summary-page">

    <div class="admin-dashboard-top">
        <div>
            <h1>ภาพรวมผู้ดูแลระบบ</h1>
            <p>สรุปข้อมูลพนักงาน บุคลากร สินค้า และสถานะระบบ</p>
        </div>

        <div class="admin-dashboard-actions">
            <div class="admin-date-pill">
                <i class="fa-regular fa-calendar"></i>
                <?= h(date('d/m/Y')) ?>
            </div>
            <div class="admin-date-pill admin-update-pill">
                <i class="fa-regular fa-clock"></i>
                อัปเดตล่าสุด <?= h($dashboard_updated_at) ?>
            </div>
        </div>
    </div>

    <div class="admin-summary-grid">
        <div class="admin-summary-card blue">
            <div>
                <span>พนักงาน</span>
                <strong><?= h((string) $total_system_users) ?></strong>
                <small>บัญชีผู้ใช้งานระบบ</small>
            </div>
            <div class="summary-icon"><i class="fa-solid fa-user-tie"></i></div>
        </div>

        <div class="admin-summary-card green">
            <div>
                <span>ช่าง</span>
                <strong><?= h((string) $total_technicians) ?></strong>
                <small>พร้อมรับงาน <?= h((string) $total_technicians_ready) ?></small>
            </div>
            <div class="summary-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
        </div>

        <div class="admin-summary-card cyan">
            <div>
                <span>ลูกค้า</span>
                <strong><?= h((string) $total_customers) ?></strong>
                <small>ข้อมูลลูกค้าทั้งหมด</small>
            </div>
            <div class="summary-icon"><i class="fa-solid fa-users"></i></div>
        </div>

        <div class="admin-summary-card orange">
            <div>
                <span>สินค้า</span>
                <strong><?= h((string) $total_products) ?></strong>
                <small>ประเภทสินค้า <?= h((string) $total_product_types) ?></small>
            </div>
            <div class="summary-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
        </div>

        <div class="admin-summary-card purple">
            <div>
                <span>งานติดตั้ง</span>
                <strong><?= h((string) $total_setups) ?></strong>
                <small>เสร็จสิ้น <?= h((string) $status_counts['done']) ?></small>
            </div>
            <div class="summary-icon"><i class="fa-solid fa-clipboard-check"></i></div>
        </div>
    </div>

    <div class="admin-dashboard-brief-grid">
        <div class="admin-widget admin-brief-card">
            <div class="admin-widget-head">
                <div>
                    <h2>สรุปสถานะงานติดตั้ง</h2>
                    <p>จำนวนงานตามสถานะปัจจุบัน</p>
                </div>
            </div>

            <div class="admin-status-metrics">
                <?php foreach ($status_overview as $item): ?>
                    <div class="admin-status-metric">
                        <span>
                            <i class="admin-status-dot <?= h($item['badge']) ?>" aria-hidden="true"></i>
                            <?= h($item['label']) ?>
                        </span>
                        <strong><?= h((string) $item['count']) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

</div>

<?php layout_footer(); ?>
