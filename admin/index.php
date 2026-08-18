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
$total_customers = count_users_by_role($conn, [0]);
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
        user_id,
        user_name,
        user_phone,
        user_email
    FROM `user`
    WHERE user_role = 0
    ORDER BY user_id DESC
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

<div class="admin-dashboard-v2">

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

    <div class="admin-dashboard-main-grid">

        <div class="admin-widget technician-performance-widget">
            <div class="admin-widget-head">
                <div>
                    <h2>ประสิทธิภาพช่างติดตั้ง</h2>
                    <p>ภาพรวมจำนวนงานและคะแนนจากลูกค้า</p>
                </div>

                <a class="admin-widget-link" href="<?= h(app_system_url('admin/technicians.php')) ?>">
                    ดูทั้งหมด
                </a>
            </div>

            <div class="technician-performance-table">
                <div class="technician-performance-head">
                    <span>ช่างติดตั้ง</span>
                    <span>งานที่รับ</span>
                    <span>คะแนนรีวิว</span>
                </div>

                <?php if (!$technician_performance || $technician_performance->num_rows === 0): ?>

                    <div class="admin-empty-mini">
                        ยังไม่มีข้อมูลช่าง
                    </div>

                <?php else: ?>

                    <?php $rank = 1; ?>
                    <?php while ($tech = $technician_performance->fetch_assoc()): ?>

                        <div class="technician-performance-row">
                            <div class="technician-profile-cell">
                                <span class="technician-rank"><?= h((string) $rank) ?></span>
                                <div class="admin-mini-avatar">
                                    <i class="fa-solid fa-screwdriver-wrench"></i>
                                </div>

                                <div class="admin-mini-main">
                                    <strong><?= h($tech['tech_name']) ?></strong>
                                    <span>
                                        <i class="technician-status-dot <?= (string) ($tech['tech_status'] ?? '') === '0' ? 'ready' : 'unavailable' ?>" aria-hidden="true"></i>
                                        <?= h(tech_status_name($tech['tech_status'])) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="technician-job-count">
                                <strong><?= h((string) (int) ($tech['job_count'] ?? 0)) ?></strong>
                                <span>งาน</span>
                            </div>

                            <div class="technician-review-score">
                                <?php if ((int) ($tech['review_count'] ?? 0) > 0 && $tech['avg_rating'] !== null): ?>
                                    <strong><?= h(number_format((float) $tech['avg_rating'], 1)) ?> ★</strong>
                                    <span><?= h((string) (int) $tech['review_count']) ?> รีวิว</span>
                                <?php else: ?>
                                    <strong>-</strong>
                                    <span>ยังไม่มีรีวิว</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php $rank++; ?>
                    <?php endwhile; ?>

                <?php endif; ?>

            </div>
        </div>

        <div class="admin-widget admin-status-overview-widget">
            <div class="admin-widget-head">
                <div>
                    <h2>ภาพรวมงานติดตั้ง</h2>
                    <p>สถานะงานทั้งหมดในระบบ</p>
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

            <div class="admin-status-chart" aria-label="กราฟภาพรวมสถานะงานติดตั้ง">
                <?php foreach ($status_overview as $item): ?>
                    <?php
                        $count = (int) $item['count'];
                        $height = max(8, (int) round(($count / $status_max_count) * 100));
                    ?>
                    <div class="admin-status-bar-item">
                        <div class="admin-status-bar-track">
                            <span
                                class="admin-status-bar <?= h($item['badge']) ?>"
                                style="height: <?= h((string) $height) ?>%;"
                            ></span>
                        </div>
                        <span class="admin-status-bar-label"><?= h($item['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="admin-widget admin-latest-users-widget">
            <div class="admin-widget-head">
                <div>
                    <h2>พนักงานล่าสุด</h2>
                    <p>บัญชีพนักงานที่เพิ่มล่าสุด</p>
                </div>

                <a class="admin-widget-link" href="<?= h(app_system_url('admin/users.php')) ?>">
                    ดูทั้งหมด
                </a>
            </div>

            <div class="admin-mini-list">

                <?php if (!$recent_users || $recent_users->num_rows === 0): ?>

                    <div class="admin-empty-mini">
                        ยังไม่มีข้อมูลพนักงาน
                    </div>

                <?php else: ?>

                    <?php while ($user = $recent_users->fetch_assoc()): ?>

                        <div class="admin-mini-item">
                            <div class="admin-mini-avatar">
                                <i class="fa-solid fa-user-tie"></i>
                            </div>

                            <div class="admin-mini-main">
                                <strong><?= h($user['user_name']) ?></strong>
                                <span><?= h($user['user_phone'] ?: '-') ?></span>
                            </div>

                            <span class="admin-user-role">
                                <?= h(role_name($user['user_role'])) ?>
                            </span>
                        </div>

                    <?php endwhile; ?>

                <?php endif; ?>

            </div>
        </div>

        <div class="admin-widget admin-latest-customers-widget">
            <div class="admin-widget-head">
                <div>
                    <h2>ลูกค้าล่าสุด</h2>
                    <p>ข้อมูลลูกค้าที่เพิ่มล่าสุด</p>
                </div>

                <a class="admin-widget-link" href="<?= h(app_system_url('admin/customers.php')) ?>">
                    ดูทั้งหมด
                </a>
            </div>

            <div class="admin-mini-list">

                <?php if (!$recent_customers || $recent_customers->num_rows === 0): ?>

                    <div class="admin-empty-mini">
                        ยังไม่มีลูกค้า
                    </div>

                <?php else: ?>

                    <?php while ($customer = $recent_customers->fetch_assoc()): ?>

                        <div class="admin-mini-item">
                            <div class="admin-mini-avatar">
                                <i class="fa-solid fa-user"></i>
                            </div>

                            <div class="admin-mini-main">
                                <strong><?= h($customer['user_name']) ?></strong>
                                <span><?= h($customer['user_phone'] ?: ($customer['user_email'] ?: '-')) ?></span>
                            </div>

                            <small><?= h($customer['user_id']) ?></small>
                        </div>

                    <?php endwhile; ?>

                <?php endif; ?>

            </div>
        </div>

    </div>

</div>

<?php layout_footer(); ?>
