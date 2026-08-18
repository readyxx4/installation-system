<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('1');

function safe_count(mysqli $conn, string $sql): int
{
    try {
        $result = $conn->query($sql);
        $row = $result ? $result->fetch_assoc() : [];
        return (int) ($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function safe_rows(mysqli $conn, string $sql): array
{
    try {
        $result = $conn->query($sql);
        if (!$result) {
            return [];
        }

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

function manager_home_thai_date(?string $date): string
{
    if (empty($date)) {
        return '-';
    }

    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '-';
}

function manager_home_time(?string $time): string
{
    if (empty($time)) {
        return '-';
    }

    $ts = strtotime($time);
    return $ts ? date('H:i', $ts) . ' น.' : '-';
}

function manager_home_is_overdue(array $row): bool
{
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
        $start_ts = strtotime($install_date . ' ' . $install_start);
        if ($start_ts !== false) {
            $install_end = date('H:i:s', $start_ts + 7200);
        }
    }

    $compare_time = $install_end !== '' ? $install_end : ($install_start !== '' ? $install_start : '23:59:59');
    $deadline = strtotime($install_date . ' ' . $compare_time);

    return $deadline !== false && $deadline < time();
}

function manager_home_status_text(array $row): string
{
    if (manager_home_is_overdue($row)) {
        return 'เกินกำหนด';
    }

    if (empty($row['assign_id'])) {
        return 'ยังไม่ได้มอบหมาย';
    }

    return match ((string) ($row['assign_status'] ?? '')) {
        '1' => 'มอบหมายงานแล้ว',
        '2' => 'ช่างรับงานแล้ว',
        '3' => 'ช่างปฏิเสธงาน',
        '4' => 'ยกเลิกแล้ว',
        '5' => 'งานเสร็จสิ้น',
        default => 'ยังไม่ได้มอบหมาย',
    };
}

$total_setups = safe_count($conn, "SELECT COUNT(*) AS total FROM setup");

$total_waiting_setups = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM setup s
    LEFT JOIN assignment a
      ON a.assign_id = (
          SELECT a2.assign_id
          FROM assignment a2
          WHERE a2.setup_id = s.setup_id
          ORDER BY a2.assign_date DESC, a2.assign_id DESC
          LIMIT 1
      )
    WHERE s.setup_status = 0
      AND (a.assign_id IS NULL OR a.assign_status <> 4)
");

$total_assigned = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM setup s
    LEFT JOIN assignment a
      ON a.assign_id = (
          SELECT a2.assign_id
          FROM assignment a2
          WHERE a2.setup_id = s.setup_id
          ORDER BY a2.assign_date DESC, a2.assign_id DESC
          LIMIT 1
      )
    WHERE a.assign_status = 1
");

$total_accepted = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM setup s
    LEFT JOIN assignment a
      ON a.assign_id = (
          SELECT a2.assign_id
          FROM assignment a2
          WHERE a2.setup_id = s.setup_id
          ORDER BY a2.assign_date DESC, a2.assign_id DESC
          LIMIT 1
      )
    WHERE a.assign_status = 2
");

$total_installing = safe_count($conn, "SELECT COUNT(*) AS total FROM setup WHERE setup_status = 3");
$total_done = safe_count($conn, "SELECT COUNT(*) AS total FROM setup WHERE setup_status = 4");

$total_technicians_ready = safe_count($conn, "SELECT COUNT(*) AS total FROM technicians WHERE tech_status = 0");
$total_technicians_busy = safe_count($conn, "SELECT COUNT(*) AS total FROM technicians WHERE tech_status = 1");
$total_technicians = safe_count($conn, "SELECT COUNT(*) AS total FROM technicians");

$dashboard_rows = safe_rows($conn, "
    SELECT
        s.setup_id,
        s.setup_status,
        s.created_at,
        u.user_name,
        p.pro_name,
        a.assign_id,
        a.assign_status,
        a.assign_install_date,
        TIME_FORMAT(a.assign_install_time, '%H:%i:%s') AS assign_install_time,
        TIME_FORMAT(a.assign_install_end_time, '%H:%i:%s') AS assign_install_end_time,
        t.tech_id,
        t.tech_name,
        t.tech_fullname,
        t.tech_status
    FROM setup s
    LEFT JOIN `user` u ON s.user_id = u.user_id
    LEFT JOIN product p ON s.pro_id = p.pro_id
    LEFT JOIN assignment a
      ON a.assign_id = (
          SELECT a2.assign_id
          FROM assignment a2
          WHERE a2.setup_id = s.setup_id
          ORDER BY a2.assign_date DESC, a2.assign_id DESC
          LIMIT 1
      )
    LEFT JOIN technicians t ON a.tech_id = t.tech_id
    ORDER BY s.created_at DESC, s.setup_id DESC
");

$actionable_jobs = [];
$total_overdue = 0;

foreach ($dashboard_rows as $row) {
    $is_overdue = manager_home_is_overdue($row);

    if ($is_overdue) {
        $total_overdue++;
        $row['is_overdue'] = true;
        $actionable_jobs[] = $row;
    }
}

/*
 * Dashboard แสดงเฉพาะ 3 งานที่เกินกำหนดนานที่สุด
 * งานที่เลยกำหนดมานานกว่าจะอยู่ด้านบน
 */
usort($actionable_jobs, static function (array $a, array $b): int {
    $a_date = trim((string) ($a['assign_install_date'] ?? ''));
    $b_date = trim((string) ($b['assign_install_date'] ?? ''));

    $a_time = trim((string) ($a['assign_install_end_time'] ?? ''));
    if ($a_time === '') {
        $a_time = trim((string) ($a['assign_install_time'] ?? '23:59:59'));
    }

    $b_time = trim((string) ($b['assign_install_end_time'] ?? ''));
    if ($b_time === '') {
        $b_time = trim((string) ($b['assign_install_time'] ?? '23:59:59'));
    }

    $a_deadline = strtotime(($a_date ?: '9999-12-31') . ' ' . ($a_time ?: '23:59:59')) ?: PHP_INT_MAX;
    $b_deadline = strtotime(($b_date ?: '9999-12-31') . ' ' . ($b_time ?: '23:59:59')) ?: PHP_INT_MAX;

    if ($a_deadline === $b_deadline) {
        return strcmp((string) ($a['setup_id'] ?? ''), (string) ($b['setup_id'] ?? ''));
    }

    return $a_deadline <=> $b_deadline;
});

$actionable_jobs = array_slice($actionable_jobs, 0, 3);

$today = date('Y-m-d');
$today_jobs = array_values(array_filter($dashboard_rows, static function (array $row) use ($today): bool {
    return (string) ($row['assign_install_date'] ?? '') === $today
        && in_array((int) ($row['assign_status'] ?? 0), [1, 2], true);
}));

usort($today_jobs, static function (array $a, array $b): int {
    return strcmp((string) ($a['assign_install_time'] ?? ''), (string) ($b['assign_install_time'] ?? ''));
});

$today_job_count = count($today_jobs);
$today_jobs = array_slice($today_jobs, 0, 5);

$technicians = safe_rows($conn, "
    SELECT
        tech_id,
        tech_name,
        tech_fullname,
        tech_status
    FROM technicians
    ORDER BY tech_status ASC, tech_fullname ASC, tech_name ASC
");

$technicians_preview = array_slice($technicians, 0, 6);

/* ปฏิทินตารางติดตั้ง */
$calendar_year = (int) date('Y');
$calendar_month = (int) date('n');
$calendar_today = (int) date('j');
$calendar_first_day = (int) date('w', strtotime(sprintf('%04d-%02d-01', $calendar_year, $calendar_month)));
$calendar_days_in_month = (int) date('t', strtotime(sprintf('%04d-%02d-01', $calendar_year, $calendar_month)));

$thai_months = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
];

$calendar_month_name = $thai_months[$calendar_month] ?? '';

$install_dates_in_month = [];
foreach ($dashboard_rows as $row) {
    $install_date = trim((string) ($row['assign_install_date'] ?? ''));
    if ($install_date === '') {
        continue;
    }

    $ts = strtotime($install_date);
    if ($ts === false) {
        continue;
    }

    if ((int) date('Y', $ts) === $calendar_year && (int) date('n', $ts) === $calendar_month) {
        $install_dates_in_month[(int) date('j', $ts)] = true;
    }
}

/* แสดงงานวันนี้ก่อน ถ้าไม่มีให้แสดงงานนัดหมายถัดไป */
$schedule_agenda = array_values(array_filter($dashboard_rows, static function (array $row) use ($today): bool {
    $install_date = trim((string) ($row['assign_install_date'] ?? ''));
    $assign_status = (int) ($row['assign_status'] ?? 0);

    return $install_date !== ''
        && $install_date >= $today
        && in_array($assign_status, [1, 2], true);
}));

usort($schedule_agenda, static function (array $a, array $b): int {
    $a_key = (string) ($a['assign_install_date'] ?? '') . ' ' . (string) ($a['assign_install_time'] ?? '');
    $b_key = (string) ($b['assign_install_date'] ?? '') . ' ' . (string) ($b['assign_install_time'] ?? '');
    return strcmp($a_key, $b_key);
});

$schedule_agenda = array_slice($schedule_agenda, 0, 3);


/* งานใหม่ที่ยังไม่ได้มอบหมาย — ใช้แสดงใน Dashboard สูงสุด 3 งาน */
$unassigned_jobs = array_values(array_filter($dashboard_rows, static function (array $row): bool {
    $setup_status = (string) ($row['setup_status'] ?? '');
    $assign_id = $row['assign_id'] ?? null;
    $assign_status = (string) ($row['assign_status'] ?? '');

    return $setup_status === '0'
        && (empty($assign_id) || $assign_status !== '4');
}));

usort($unassigned_jobs, static function (array $a, array $b): int {
    $a_created = (string) ($a['created_at'] ?? '');
    $b_created = (string) ($b['created_at'] ?? '');

    if ($a_created === $b_created) {
        return strcmp((string) ($b['setup_id'] ?? ''), (string) ($a['setup_id'] ?? ''));
    }

    return strcmp($b_created, $a_created);
});

$unassigned_jobs = array_slice($unassigned_jobs, 0, 3);

$thai_short_months = [
    1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
    5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
    9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.',
];

$thai_weekdays = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];

/* ข้อมูลตารางติดตั้งรายวันสำหรับปฏิทิน Dashboard */
$calendar_schedule_map = [];

foreach ($dashboard_rows as $row) {
    $install_date = trim((string) ($row['assign_install_date'] ?? ''));
    $assign_status = (int) ($row['assign_status'] ?? 0);

    if ($install_date === '' || !in_array($assign_status, [1, 2], true)) {
        continue;
    }

    $ts = strtotime($install_date);
    if ($ts === false
        || (int) date('Y', $ts) !== $calendar_year
        || (int) date('n', $ts) !== $calendar_month) {
        continue;
    }

    $tech_name = trim((string) ($row['tech_fullname'] ?? ''));
    if ($tech_name === '') {
        $tech_name = (string) ($row['tech_name'] ?? '-');
    }

    $calendar_schedule_map[$install_date][] = [
        'setup_id' => (string) ($row['setup_id'] ?? ''),
        'customer' => (string) ($row['user_name'] ?? '-'),
        'product' => (string) ($row['pro_name'] ?? '-'),
        'time' => manager_home_time($row['assign_install_time'] ?? null),
        'tech' => $tech_name,
        'tech_ready' => (int) ($row['tech_status'] ?? 1) === 0,
        'url' => app_system_url(
            'manager/assignments.php?setup_id=' . urlencode((string) ($row['setup_id'] ?? ''))
        ),
    ];
}

/* เรียงเวลา และ Dashboard แสดงไม่เกิน 2 งานต่อวันที่เลือก */
foreach ($calendar_schedule_map as &$day_jobs) {
    usort($day_jobs, static function (array $a, array $b): int {
        return strcmp((string) ($a['time'] ?? ''), (string) ($b['time'] ?? ''));
    });
    $day_jobs = array_slice($day_jobs, 0, 2);
}
unset($day_jobs);

$calendar_schedule_json = json_encode(
    $calendar_schedule_map,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

layout_header('หน้าหลักหัวหน้าช่าง', 'dashboard');

?>

<div class="manager-home">
    <header class="manager-home-header">
        <div class="manager-home-heading">
            <h1>ภาพรวมหัวหน้าช่าง</h1>
            <p>ติดตามงานที่ต้องมอบหมาย ตารางติดตั้ง และความพร้อมของช่าง</p>
        </div>

        <div class="manager-home-header-actions">
            <div class="manager-home-pill">
                <i class="fa-regular fa-calendar"></i>
                <?= h(date('d/m/Y')) ?>
            </div>
            <div class="manager-home-pill">
                <i class="fa-regular fa-clock"></i>
                อัปเดตล่าสุด <?= h(date('H:i')) ?>
            </div>
        </div>
    </header>

    <div class="manager-home-row manager-home-row-top">
        <!-- ตารางติดตั้ง -->
        <section class="manager-home-card manager-schedule-card">
            <div class="manager-card-head">
                <div>
                    <h2>ตารางติดตั้ง</h2>
                    <p>งานติดตั้งที่กำลังจะมาถึงและวันนัดหมายในเดือนนี้</p>
                </div>
            </div>

            <div class="manager-schedule-body">
                <div class="manager-schedule-list">
                    <div class="manager-schedule-today">
                        <strong id="managerSelectedDateLabel">
                            <?= h(
                                ($thai_weekdays[(int) date('w')] ?? '') . ' ' .
                                date('j') . ' ' .
                                ($thai_short_months[(int) date('n')] ?? '')
                            ) ?>
                        </strong>
                    </div>

                    <div class="manager-schedule-items" id="managerScheduleItems"></div>
                </div>

                <div class="manager-mini-calendar">
                    <div class="manager-mini-calendar-title"><?= h($calendar_month_name) ?></div>

                    <div class="manager-mini-weekdays">
                        <span>อา</span><span>จ</span><span>อ</span><span>พ</span>
                        <span>พฤ</span><span>ศ</span><span>ส</span>
                    </div>

                    <div class="manager-mini-days">
                        <?php for ($blank = 0; $blank < $calendar_first_day; $blank++): ?>
                            <span class="manager-mini-day empty"></span>
                        <?php endfor; ?>

                        <?php for ($day = 1; $day <= $calendar_days_in_month; $day++): ?>
                            <?php
                                $is_today = $day === $calendar_today;
                                $has_job = !empty($install_dates_in_month[$day]);
                                $calendar_date_value = sprintf(
                                    '%04d-%02d-%02d',
                                    $calendar_year,
                                    $calendar_month,
                                    $day
                                );
                            ?>
                            <button
                                type="button"
                                class="manager-mini-day <?= $is_today ? 'today selected' : '' ?> <?= $has_job ? 'has-job' : '' ?>"
                                data-calendar-date="<?= h($calendar_date_value) ?>"
                                data-calendar-day="<?= h((string) $day) ?>"
                                aria-label="เลือกวันที่ <?= h((string) $day) ?>"
                            >
                                <?= h((string) $day) ?>
                            </button>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>        </section>

        <!-- งานที่ต้องมอบหมาย -->
        <section class="manager-home-card manager-assign-card">
            <div class="manager-card-head">
                <div>
                    <h2>รายการงานที่ต้องมอบหมาย</h2>
                    <p>งานใหม่ที่ยังไม่ได้กำหนดช่างผู้รับผิดชอบ</p>
                </div>
                <a href="<?= h(app_system_url('manager/assignment_list.php?status=unassigned')) ?>">
                    ดูทั้งหมด <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <div class="manager-assign-list">
                <?php if (count($unassigned_jobs) === 0): ?>
                    <div class="manager-home-empty">ไม่มีงานที่รอมอบหมาย</div>
                <?php endif; ?>

                <?php foreach ($unassigned_jobs as $job): ?>
                    <article class="manager-assign-item">
                        <div class="manager-assign-copy">
                            <div class="manager-inline-head">
                                <strong><?= h((string) $job['setup_id']) ?></strong>
                                <span class="manager-badge orange">รอมอบหมาย</span>
                            </div>
                            <span><?= h((string) ($job['user_name'] ?: '-')) ?></span>
                            <small>
                                <?= h((string) ($job['pro_name'] ?: '-')) ?>
                                · สร้าง <?= h(manager_home_thai_date($job['created_at'] ?? null)) ?>
                            </small>
                        </div>

                        <a
                            class="manager-btn assign-warning"
                            href="<?= h(app_system_url('manager/assignments.php?setup_id=' . urlencode((string) $job['setup_id']))) ?>"
                        >
                            มอบหมาย
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <div class="manager-home-row manager-home-row-bottom">
        <!-- งานเกินกำหนด -->
        <section class="manager-home-card manager-task-card">
            <div class="manager-card-head">
                <div>
                    <h2>งานที่ต้องจัดการ</h2>
                    <p>3 งานที่เกินกำหนดนานที่สุด</p>
                </div>
                <a href="<?= h(app_system_url('manager/assignment_list.php?status=overdue')) ?>">
                    ดูทั้งหมด <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <div class="manager-task-list">
                <?php if (count($actionable_jobs) === 0): ?>
                    <div class="manager-home-empty">ไม่มีงานเกินกำหนด</div>
                <?php endif; ?>

                <?php foreach ($actionable_jobs as $job): ?>
                    <article class="manager-task-item">
                        <div class="manager-task-copy">
                            <div class="manager-inline-head">
                                <strong><?= h((string) $job['setup_id']) ?></strong>
                                <span class="manager-badge orange">เกินกำหนด</span>
                            </div>
                            <span>
                                <?= h((string) ($job['user_name'] ?: '-')) ?>
                                · <?= h((string) ($job['pro_name'] ?: '-')) ?>
                            </span>
                            <small>
                                กำหนดเดิม:
                                <?= h(manager_home_thai_date($job['assign_install_date'] ?? null)) ?>
                                <?= h(manager_home_time($job['assign_install_time'] ?? null)) ?>
                            </small>
                        </div>

                        <a
                            class="manager-btn warning"
                            href="<?= h(app_system_url('manager/assignments.php?setup_id=' . urlencode((string) $job['setup_id']))) ?>"
                        >
                            แก้ไขงาน
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- สถานะช่าง -->
        <section class="manager-home-card manager-tech-card">
            <div class="manager-card-head">
                <div>
                    <h2>สถานะช่างติดตั้ง</h2>
                    <p>ตรวจสอบความพร้อมก่อนมอบหมายงาน</p>
                </div>
                <span class="manager-badge blue"><?= h((string) $total_technicians) ?> คน</span>
            </div>

            <div class="manager-tech-summary">
                <div class="manager-status-box">
                    <span class="manager-status-dot ready"></span>
                    <div>
                        <strong><?= h((string) $total_technicians_ready) ?></strong>
                        <small>พร้อมรับงาน</small>
                    </div>
                </div>

                <div class="manager-status-box">
                    <span class="manager-status-dot unavailable"></span>
                    <div>
                        <strong><?= h((string) $total_technicians_busy) ?></strong>
                        <small>ไม่พร้อมรับงาน</small>
                    </div>
                </div>
            </div>

            <div class="manager-tech-grid">
                <?php if (count($technicians_preview) === 0): ?>
                    <div class="manager-home-empty">ยังไม่มีข้อมูลช่างติดตั้ง</div>
                <?php endif; ?>

                <?php foreach ($technicians_preview as $tech): ?>
                    <?php
                        $is_ready = (int) ($tech['tech_status'] ?? 1) === 0;
                        $tech_name = trim((string) ($tech['tech_fullname'] ?? ''));
                        if ($tech_name === '') {
                            $tech_name = (string) ($tech['tech_name'] ?? '-');
                        }
                    ?>
                    <div
                        class="manager-tech-chip"
                        title="<?= $is_ready ? 'พร้อมรับงาน' : 'ไม่พร้อมรับงาน' ?>"
                    >
                        <span class="manager-status-dot <?= $is_ready ? 'ready' : 'unavailable' ?>"></span>
                        <strong><?= h($tech_name) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>

<script>
(() => {
    const scheduleMap = <?= $calendar_schedule_json ?: '{}' ?>;
    const itemsBox = document.getElementById('managerScheduleItems');
    const dateLabel = document.getElementById('managerSelectedDateLabel');
    const dayButtons = Array.from(document.querySelectorAll('[data-calendar-date]'));

    if (!itemsBox || !dateLabel || dayButtons.length === 0) {
        return;
    }

    const monthShort = <?= json_encode($thai_short_months[$calendar_month] ?? '', JSON_UNESCAPED_UNICODE) ?>;
    const weekdays = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const renderDate = (dateValue, dayValue, clickedButton) => {
        dayButtons.forEach((button) => button.classList.remove('selected'));
        clickedButton.classList.add('selected');

        const dateObj = new Date(`${dateValue}T00:00:00`);
        const weekday = weekdays[dateObj.getDay()] ?? '';
        dateLabel.textContent = `${weekday} ${dayValue} ${monthShort}`;

        const jobs = Array.isArray(scheduleMap[dateValue])
            ? scheduleMap[dateValue].slice(0, 2)
            : [];

        if (jobs.length === 0) {
            itemsBox.innerHTML = `
                <div class="manager-schedule-empty-day">
                    ไม่มีงานติดตั้งในวันที่เลือก
                </div>
            `;
            return;
        }

        itemsBox.innerHTML = jobs.map((job) => `
            <a class="manager-schedule-item" href="${escapeHtml(job.url)}">
                <span class="manager-schedule-mark blue"></span>
                <div class="manager-schedule-copy">
                    <strong>${escapeHtml(job.time)}</strong>
                    <span>${escapeHtml(job.customer)}</span>
                    <small>${escapeHtml(job.product)}</small>
                    <em>
                        <span class="technician-status-dot ${job.tech_ready ? 'ready' : 'unavailable'}"></span>
                        ${escapeHtml(job.tech)}
                    </em>
                </div>
            </a>
        `).join('');
    };

    dayButtons.forEach((button) => {
        button.addEventListener('click', () => {
            renderDate(
                button.dataset.calendarDate,
                button.dataset.calendarDay,
                button
            );
        });
    });

    const initialButton =
        dayButtons.find((button) => button.classList.contains('today')) ||
        dayButtons[0];

    renderDate(
        initialButton.dataset.calendarDate,
        initialButton.dataset.calendarDay,
        initialButton
    );
})();
</script>

<link rel="stylesheet" href="<?= h(app_asset_url('manager/assets/css/dashboard.css')) ?>?v=<?= h(asset_version('manager/assets/css/dashboard.css')) ?>">

<?php layout_footer(); ?>
