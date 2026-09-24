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
$dashboard_admin_name = trim((string) ($_SESSION['user_name'] ?? ''));
$dashboard_admin_name = $dashboard_admin_name !== '' ? $dashboard_admin_name : 'ผู้ดูแลระบบ';
$dashboard_employee_role_labels = [
    '1' => 'หัวหน้าช่าง',
    '2' => 'พนักงานขาย',
    '3' => 'ผู้ดูแลระบบ',
];

layout_header('หน้าหลัก', 'dashboard', 'ภาพรวมข้อมูลและสถานะการทำงานของระบบ');
?>

<link
    rel="stylesheet"
    href="<?= h(app_asset_url('admin/assets/css/dashboard.css')) ?>?v=<?= h(asset_version('admin/assets/css/dashboard.css')) ?>"
>

<main class="admin-dashboard-summary-page" aria-label="ภาพรวมข้อมูลและการจัดการระบบ">
    <header class="admin-dashboard-greeting">
        <h1>สวัสดี, <?= h($dashboard_admin_name) ?> 👋</h1>
        <p>ภาพรวมข้อมูลและการจัดการระบบวันนี้</p>
    </header>

    <section class="admin-dashboard-summary-grid" aria-label="สรุปข้อมูลระบบ">
        <article class="admin-dashboard-stat admin-dashboard-stat--blue">
            <span class="admin-dashboard-stat__icon"><i class="fa-solid fa-user-tie" aria-hidden="true"></i></span>
            <div><span>พนักงาน</span><strong><?= h((string) $total_system_users) ?></strong></div>
        </article>
        <article class="admin-dashboard-stat admin-dashboard-stat--green">
            <span class="admin-dashboard-stat__icon"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i></span>
            <div><span>ช่าง</span><strong><?= h((string) $total_technicians) ?></strong></div>
        </article>
        <article class="admin-dashboard-stat admin-dashboard-stat--orange">
            <span class="admin-dashboard-stat__icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
            <div><span>ลูกค้า</span><strong><?= h((string) $total_customers) ?></strong></div>
        </article>
        <article class="admin-dashboard-stat admin-dashboard-stat--slate">
            <span class="admin-dashboard-stat__icon"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i></span>
            <div><span>สินค้า</span><strong><?= h((string) $total_products) ?></strong></div>
        </article>
        <article class="admin-dashboard-stat admin-dashboard-stat--blue">
            <span class="admin-dashboard-stat__icon"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i></span>
            <div><span>งานติดตั้ง</span><strong><?= h((string) $total_setups) ?></strong></div>
        </article>
    </section>

    <section class="admin-dashboard-directory-grid" aria-label="ข้อมูลบุคลากร">
        <section class="admin-dashboard-directory-card" aria-labelledby="admin-technician-directory-title">
            <header class="admin-dashboard-directory-card__header">
                <div>
                    <h2 id="admin-technician-directory-title">ข้อมูลช่างติดตั้ง</h2>
                    <p>สถานะความพร้อมของช่างในระบบ</p>
                </div>
                <a href="<?= h(app_system_url('admin/technicians.php')) ?>">ดูทั้งหมด <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </header>
            <div class="admin-dashboard-directory-card__list">
                <?php if (!$technician_performance || $technician_performance->num_rows === 0): ?>
                    <p class="admin-dashboard-directory-card__empty">ยังไม่มีข้อมูลช่างติดตั้ง</p>
                <?php else: ?>
                    <?php while ($technician = $technician_performance->fetch_assoc()): ?>
                        <?php
                        $technician_name = trim((string) ($technician['tech_name'] ?? ''));
                        $technician_initial = function_exists('mb_substr') ? mb_substr($technician_name, 0, 1, 'UTF-8') : substr($technician_name, 0, 1);
                        $is_technician_ready = (string) ($technician['tech_status'] ?? '') === '0';
                        ?>
                        <article class="admin-dashboard-directory-item">
                            <span class="admin-dashboard-directory-item__avatar admin-dashboard-directory-item__avatar--technician"><?= h($technician_initial !== '' ? $technician_initial : '-') ?></span>
                            <div class="admin-dashboard-directory-item__copy">
                                <strong><?= h($technician_name !== '' ? $technician_name : '-') ?></strong>
                                <span><?= h((string) ($technician['tech_id'] ?? '-')) ?></span>
                            </div>
                            <span class="admin-dashboard-directory-item__status<?= $is_technician_ready ? ' is-ready' : ' is-unavailable' ?>"><?= h(tech_status_name($technician['tech_status'] ?? null)) ?></span>
                        </article>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="admin-dashboard-directory-card" aria-labelledby="admin-employee-directory-title">
            <header class="admin-dashboard-directory-card__header">
                <div>
                    <h2 id="admin-employee-directory-title">ข้อมูลพนักงาน</h2>
                    <p>จัดการบัญชีพนักงานในระบบ</p>
                </div>
                <a href="<?= h(app_system_url('admin/users.php')) ?>">ดูทั้งหมด <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </header>
            <div class="admin-dashboard-directory-card__list">
                <?php if (!$recent_users || $recent_users->num_rows === 0): ?>
                    <p class="admin-dashboard-directory-card__empty">ยังไม่มีข้อมูลพนักงาน</p>
                <?php else: ?>
                    <?php while ($employee = $recent_users->fetch_assoc()): ?>
                        <?php
                        $employee_name = trim((string) ($employee['user_name'] ?? ''));
                        $employee_initial = function_exists('mb_substr') ? mb_substr($employee_name, 0, 1, 'UTF-8') : substr($employee_name, 0, 1);
                        $employee_role = $dashboard_employee_role_labels[(string) ($employee['user_role'] ?? '')] ?? 'ไม่ทราบสิทธิ์';
                        $employee_edit_url = app_system_url('admin/user_edit.php?id=' . urlencode((string) ($employee['user_id'] ?? '')));
                        ?>
                        <article class="admin-dashboard-directory-item">
                            <span class="admin-dashboard-directory-item__avatar admin-dashboard-directory-item__avatar--employee"><?= h($employee_initial !== '' ? $employee_initial : '-') ?></span>
                            <div class="admin-dashboard-directory-item__copy">
                                <strong><?= h($employee_name !== '' ? $employee_name : '-') ?></strong>
                                <span><?= h($employee_role) ?></span>
                            </div>
                            <a class="admin-dashboard-directory-item__edit" href="<?= h($employee_edit_url) ?>">แก้ไข</a>
                        </article>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </section>
    </section>
</main>

<?php layout_footer(); ?>
