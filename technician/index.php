<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('technician');

$tech_id = $_SESSION['user_id'] ?? '';

function safe_count(mysqli $conn, string $sql, string $tech_id): int
{
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $tech_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return (int) ($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function assign_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'ยังไม่มอบหมาย',
        '1' => 'มอบหมายแล้ว',
        '2' => 'ช่างรับงานแล้ว',
        '3' => 'ช่างปฏิเสธงาน',
        '4' => 'ยกเลิกแล้ว',
        '5' => 'เสร็จสิ้น',
        default => 'ไม่ทราบสถานะ',
    };
}

function assign_status_badge($status): string
{
    return match ((string) $status) {
        '0' => 'orange',
        '1' => 'blue',
        '2' => 'green',
        '3' => 'red',
        '4' => 'red',
        '5' => 'green',
        default => 'red',
    };
}

$total_assigned = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM assignment
    WHERE tech_id = ?
      AND assign_status = 1
", $tech_id);

$total_accepted = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM assignment
    WHERE tech_id = ?
      AND assign_status = 2
", $tech_id);

$total_rejected = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM assignment
    WHERE tech_id = ?
      AND assign_status = 3
", $tech_id);

$total_finished = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM assignment
    WHERE tech_id = ?
      AND assign_status = 5
", $tech_id);

$stmt_jobs = $conn->prepare("
    SELECT
        a.assign_id,
        a.assign_date,
        a.assign_install_date,
        a.assign_install_time,
        a.assign_status,

        s.setup_id,
        s.setup_address,
        s.setup_location,

        c.customer_name,
        c.customer_phone,

        p.pro_name,
        COALESCE(NULLIF(ds.item_count, 0), 1) AS item_count
    FROM assignment a
    LEFT JOIN setup s ON a.setup_id = s.setup_id
    LEFT JOIN customers c ON s.customer_id = c.customer_id
    LEFT JOIN product p ON s.pro_id = p.pro_id
    LEFT JOIN (
        SELECT
            d.setup_id,
            SUM(COALESCE(d.install_qty, 1)) AS item_count
        FROM install_detail d
        GROUP BY d.setup_id
    ) ds ON s.setup_id = ds.setup_id
    WHERE a.tech_id = ?
    ORDER BY a.assign_date DESC, a.assign_id DESC
    LIMIT 5
");

$stmt_jobs->bind_param('s', $tech_id);
$stmt_jobs->execute();
$recent_jobs = $stmt_jobs->get_result();
$stmt_warning_jobs = $conn->prepare("
    SELECT
        assign_install_date,
        assign_install_time,
        assign_status
    FROM assignment
    WHERE tech_id = ?
      AND assign_status IN (1, 2)
");
$stmt_warning_jobs->bind_param('s', $tech_id);
$stmt_warning_jobs->execute();
$warning_jobs = $stmt_warning_jobs->get_result();

$technician_warning_counts = [
    'waiting_near' => 0,
    'waiting_urgent' => 0,
    'waiting_today' => 0,
    'waiting_overdue' => 0,
    'accepted_near' => 0,
    'accepted_urgent' => 0,
    'accepted_today' => 0,
    'accepted_overdue' => 0,
];

while ($warning_row = $warning_jobs->fetch_assoc()) {
    $warning = assignment_install_due_warning(
        $warning_row['assign_install_date'] ?? '',
        $warning_row['assign_install_time'] ?? '',
        $warning_row['assign_status'] ?? '',
        'technician'
    );

    $assignStatus = (string) ($warning_row['assign_status'] ?? '');
    $warningLevel = (string) ($warning['level'] ?? 'normal');
    $businessDaysLeft = (int) ($warning['business_days_left'] ?? -1);

    if ($assignStatus === '1') {
        if ($warningLevel === 'near') {
            $technician_warning_counts['waiting_near']++;
        } elseif ($warningLevel === 'urgent' && $businessDaysLeft === 1) {
            $technician_warning_counts['waiting_urgent']++;
        } elseif ($warningLevel === 'today') {
            $technician_warning_counts['waiting_today']++;
        } elseif ($warningLevel === 'overdue') {
            $technician_warning_counts['waiting_overdue']++;
        }
        continue;
    }

    if ($assignStatus === '2') {
        if ($warningLevel === 'near') {
            $technician_warning_counts['accepted_near']++;
        } elseif ($warningLevel === 'urgent' && $businessDaysLeft === 1) {
            $technician_warning_counts['accepted_urgent']++;
        } elseif ($warningLevel === 'today') {
            $technician_warning_counts['accepted_today']++;
        } elseif ($warningLevel === 'overdue') {
            $technician_warning_counts['accepted_overdue']++;
        }
    }
}

$technician_warning_cards = [
    [
        'count' => $technician_warning_counts['waiting_near'],
        'label' => html_entity_decode('&#3591;&#3634;&#3609;&#3619;&#3629;&#3619;&#3633;&#3610;&#3651;&#3585;&#3621;&#3657;&#3606;&#3638;&#3591;&#3585;&#3635;&#3627;&#3609;&#3604;', ENT_QUOTES, 'UTF-8'),
        'subtext' => html_entity_decode('&#3605;&#3619;&#3623;&#3592;&#3626;&#3629;&#3610;&#3649;&#3621;&#3632;&#3605;&#3629;&#3610;&#3619;&#3633;&#3610;&#3591;&#3634;&#3609;', ENT_QUOTES, 'UTF-8'),
        'class' => 'yellow',
        'icon' => 'fa-clock',
        'url' => app_system_url('technician/accept_job.php?warning=near'),
    ],
    [
        'count' => $technician_warning_counts['waiting_urgent'],
        'label' => html_entity_decode('&#3591;&#3634;&#3609;&#3619;&#3629;&#3619;&#3633;&#3610;&#3648;&#3619;&#3656;&#3591;&#3604;&#3656;&#3623;&#3609;', ENT_QUOTES, 'UTF-8'),
        'subtext' => html_entity_decode('&#3605;&#3619;&#3623;&#3592;&#3626;&#3629;&#3610;&#3649;&#3621;&#3632;&#3605;&#3629;&#3610;&#3619;&#3633;&#3610;&#3591;&#3634;&#3609;', ENT_QUOTES, 'UTF-8'),
        'class' => 'orange',
        'icon' => 'fa-triangle-exclamation',
        'url' => app_system_url('technician/accept_job.php?warning=urgent'),
    ],
    [
        'count' => $technician_warning_counts['waiting_today'],
        'label' => html_entity_decode('&#3591;&#3634;&#3609;&#3588;&#3657;&#3634;&#3591;&#3605;&#3629;&#3610;&#3619;&#3633;&#3610;&#3623;&#3633;&#3609;&#3609;&#3637;&#3657;', ENT_QUOTES, 'UTF-8'),
        'subtext' => html_entity_decode('&#3605;&#3619;&#3623;&#3592;&#3626;&#3629;&#3610;&#3649;&#3621;&#3632;&#3605;&#3629;&#3610;&#3619;&#3633;&#3610;&#3591;&#3634;&#3609;', ENT_QUOTES, 'UTF-8'),
        'class' => 'orange',
        'icon' => 'fa-calendar-day',
        'url' => app_system_url('technician/accept_job.php?warning=today'),
    ],
    [
        'count' => $technician_warning_counts['waiting_overdue'],
        'label' => html_entity_decode('&#3591;&#3634;&#3609;&#3588;&#3657;&#3634;&#3591;&#3605;&#3629;&#3610;&#3619;&#3633;&#3610;&#3607;&#3637;&#3656;&#3648;&#3621;&#3618;&#3585;&#3635;&#3627;&#3609;&#3604;', ENT_QUOTES, 'UTF-8'),
        'subtext' => html_entity_decode('&#3605;&#3619;&#3623;&#3592;&#3626;&#3629;&#3610;&#3649;&#3621;&#3632;&#3605;&#3629;&#3610;&#3619;&#3633;&#3610;&#3591;&#3634;&#3609;', ENT_QUOTES, 'UTF-8'),
        'class' => 'indigo',
        'icon' => 'fa-circle-exclamation',
        'url' => app_system_url('technician/accept_job.php?warning=overdue'),
    ],
    [
        'count' => $technician_warning_counts['accepted_near'],
        'label' => html_entity_decode('&#3591;&#3634;&#3609;&#3619;&#3633;&#3610;&#3649;&#3621;&#3657;&#3623;&#3651;&#3585;&#3621;&#3657;&#3606;&#3638;&#3591;&#3585;&#3635;&#3627;&#3609;&#3604;', ENT_QUOTES, 'UTF-8'),
        'subtext' => html_entity_decode('&#3591;&#3634;&#3609;&#3607;&#3637;&#3656;&#3619;&#3633;&#3610;&#3649;&#3621;&#3657;&#3623;', ENT_QUOTES, 'UTF-8'),
        'class' => 'yellow',
        'icon' => 'fa-clock',
        'url' => app_system_url('technician/my_jobs.php?warning=near'),
    ],
    [
        'count' => $technician_warning_counts['accepted_urgent'],
        'label' => html_entity_decode('&#3591;&#3634;&#3609;&#3619;&#3633;&#3610;&#3649;&#3621;&#3657;&#3623;&#3648;&#3619;&#3656;&#3591;&#3604;&#3656;&#3623;&#3609;', ENT_QUOTES, 'UTF-8'),
        'subtext' => html_entity_decode('&#3591;&#3634;&#3609;&#3607;&#3637;&#3656;&#3619;&#3633;&#3610;&#3649;&#3621;&#3657;&#3623;', ENT_QUOTES, 'UTF-8'),
        'class' => 'orange',
        'icon' => 'fa-triangle-exclamation',
        'url' => app_system_url('technician/my_jobs.php?warning=urgent'),
    ],
    [
        'count' => $technician_warning_counts['accepted_today'],
        'label' => html_entity_decode('&#3591;&#3634;&#3609;&#3606;&#3638;&#3591;&#3585;&#3635;&#3627;&#3609;&#3604;&#3623;&#3633;&#3609;&#3609;&#3637;&#3657;', ENT_QUOTES, 'UTF-8'),
        'subtext' => html_entity_decode('&#3591;&#3634;&#3609;&#3607;&#3637;&#3656;&#3619;&#3633;&#3610;&#3649;&#3621;&#3657;&#3623;', ENT_QUOTES, 'UTF-8'),
        'class' => 'orange',
        'icon' => 'fa-calendar-day',
        'url' => app_system_url('technician/my_jobs.php?warning=today'),
    ],
    [
        'count' => $technician_warning_counts['accepted_overdue'],
        'label' => html_entity_decode('&#3591;&#3634;&#3609;&#3648;&#3585;&#3636;&#3609;&#3585;&#3635;&#3627;&#3609;&#3604;', ENT_QUOTES, 'UTF-8'),
        'subtext' => html_entity_decode('&#3591;&#3634;&#3609;&#3607;&#3637;&#3656;&#3619;&#3633;&#3610;&#3649;&#3621;&#3657;&#3623;', ENT_QUOTES, 'UTF-8'),
        'class' => 'red',
        'icon' => 'fa-circle-exclamation',
        'url' => app_system_url('technician/my_jobs.php?warning=overdue'),
    ],
];
$technician_warning_cards = array_values(array_filter($technician_warning_cards, static function (array $card): bool {
    return (int) ($card['count'] ?? 0) > 0;
}));
$technician_warning_total = array_sum(array_map(static function (array $card): int {
    return (int) ($card['count'] ?? 0);
}, $technician_warning_cards));

function technician_dashboard_format_date(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : $value;
}

function technician_dashboard_format_time(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '-';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('H:i', $timestamp) . ' น.' : $value;
}

function technician_waiting_notification_label(array $warning): string
{
    $level = (string) ($warning['level'] ?? 'normal');
    $daysLeft = (int) ($warning['business_days_left'] ?? -1);

    if ($level === 'overdue') {
        return html_entity_decode('&#3591;&#3634;&#3609;&#3588;&#3657;&#3634;&#3591;&#3605;&#3629;&#3610;&#3619;&#3633;&#3610;&#3607;&#3637;&#3656;&#3648;&#3621;&#3618;&#3585;&#3635;&#3627;&#3609;&#3604;', ENT_QUOTES, 'UTF-8');
    }

    if ($level === 'today') {
        return html_entity_decode('&#3591;&#3634;&#3609;&#3588;&#3657;&#3634;&#3591;&#3605;&#3629;&#3610;&#3619;&#3633;&#3610;&#3623;&#3633;&#3609;&#3609;&#3637;&#3657;', ENT_QUOTES, 'UTF-8');
    }

    if ($level === 'urgent' && $daysLeft === 1) {
        return html_entity_decode('&#3591;&#3634;&#3609;&#3619;&#3629;&#3619;&#3633;&#3610;&#3648;&#3619;&#3656;&#3591;&#3604;&#3656;&#3623;&#3609;', ENT_QUOTES, 'UTF-8');
    }

    if ($level === 'near') {
        return html_entity_decode('&#3591;&#3634;&#3609;&#3619;&#3629;&#3619;&#3633;&#3610;&#3651;&#3585;&#3621;&#3657;&#3606;&#3638;&#3591;&#3585;&#3635;&#3627;&#3609;&#3604;', ENT_QUOTES, 'UTF-8');
    }

    return '';
}

function technician_waiting_notification_class(array $warning): string
{
    return match ((string) ($warning['level'] ?? 'normal')) {
        'overdue' => 'indigo',
        'today', 'urgent' => 'orange',
        'near' => 'yellow',
        default => 'blue',
    };
}

function technician_waiting_notification_priority(array $warning): int
{
    $level = (string) ($warning['level'] ?? 'normal');
    $daysLeft = (int) ($warning['business_days_left'] ?? -1);

    if ($level === 'overdue') {
        return 0;
    }

    if ($level === 'urgent' && $daysLeft === 1) {
        return 1;
    }

    if ($level === 'today') {
        return 2;
    }

    if ($level === 'near') {
        return 3;
    }

    return 9;
}

$technician_notification_jobs = [];
$stmt_notification_jobs = $conn->prepare("
    SELECT
        a.assign_id,
        a.assign_install_date,
        a.assign_install_time,
        a.assign_status,
        c.customer_name
    FROM assignment a
    LEFT JOIN setup s ON a.setup_id = s.setup_id
    LEFT JOIN customers c ON s.customer_id = c.customer_id
    WHERE a.tech_id = ?
      AND a.assign_status = 1
");
$stmt_notification_jobs->bind_param('s', $tech_id);
$stmt_notification_jobs->execute();
$notification_jobs_result = $stmt_notification_jobs->get_result();

while ($notification_row = $notification_jobs_result->fetch_assoc()) {
    $notification_warning = assignment_install_due_warning(
        $notification_row['assign_install_date'] ?? '',
        $notification_row['assign_install_time'] ?? '',
        $notification_row['assign_status'] ?? '',
        'technician'
    );
    $notification_label = technician_waiting_notification_label($notification_warning);

    if ($notification_label === '') {
        continue;
    }

    $technician_notification_jobs[] = [
        'assign_id' => (string) ($notification_row['assign_id'] ?? ''),
        'label' => $notification_label,
        'class' => technician_waiting_notification_class($notification_warning),
        'priority' => technician_waiting_notification_priority($notification_warning),
        'url' => app_system_url('technician/accept_job.php?focus=' . rawurlencode((string) ($notification_row['assign_id'] ?? ''))),
    ];
}

usort($technician_notification_jobs, static function (array $a, array $b): int {
    $priorityCompare = ((int) ($a['priority'] ?? 9)) <=> ((int) ($b['priority'] ?? 9));
    if ($priorityCompare !== 0) {
        return $priorityCompare;
    }

    $aDate = (string) ($a['install_date'] ?? '');
    $bDate = (string) ($b['install_date'] ?? '');
    if ($aDate !== $bDate) {
        return strcmp($aDate, $bDate);
    }

    return strcmp((string) ($a['install_time'] ?? ''), (string) ($b['install_time'] ?? ''));
});

$technician_notification_total = count($technician_notification_jobs);

$technician_name = trim((string) ($_SESSION['user_name'] ?? 'ช่างติดตั้ง'));
$recent_job_rows = [];
while ($recent_row = $recent_jobs->fetch_assoc()) {
    $recent_job_rows[] = $recent_row;
}

$latest_waiting_jobs = array_slice(array_values(array_filter($recent_job_rows, static function (array $row): bool {
    return (string) ($row['assign_status'] ?? '') === '1';
})), 0, 3);

$latest_active_jobs = array_slice(array_values(array_filter($recent_job_rows, static function (array $row): bool {
    return (string) ($row['assign_status'] ?? '') === '2';
})), 0, 3);

layout_header('หน้าหลักช่างติดตั้ง', 'dashboard');
?>
<link
  rel="stylesheet"
  href="<?= h(app_asset_url('admin/assets/css/dashboard.css')) ?>?v=<?= h(asset_version('admin/assets/css/dashboard.css')) ?>"
>
<link
  rel="stylesheet"
  href="<?= h(app_asset_url('technician/assets/css/technician.css')) ?>?v=<?= h(asset_version('technician/assets/css/technician.css')) ?>"
>

<div class="admin-dashboard-v2 admin-dashboard-summary-page technician-dashboard-page technician-reference-dashboard">
  <div class="admin-dashboard-top technician-reference-page-head">
    <div>
      <h1>สวัสดี, <?= h($technician_name) ?> 👋</h1>
      <p>ภาพรวมงานติดตั้งของคุณวันนี้</p>
    </div>

    <div class="admin-dashboard-actions">
      <div class="admin-date-pill technician-date-subtle">
        <i class="fa-regular fa-calendar"></i>
        <?= h(date('d/m/Y')) ?>
      </div>
      <div class="technician-notification-menu">
        <button
          type="button"
          class="admin-date-pill technician-bell-pill"
          data-technician-notification-toggle
          title="<?= h(html_entity_decode('&#3585;&#3634;&#3619;&#3649;&#3592;&#3657;&#3591;&#3648;&#3605;&#3639;&#3629;&#3609;', ENT_QUOTES, 'UTF-8')) ?>"
          aria-label="<?= h(html_entity_decode('&#3585;&#3634;&#3619;&#3649;&#3592;&#3657;&#3591;&#3648;&#3605;&#3639;&#3629;&#3609;', ENT_QUOTES, 'UTF-8')) ?>"
          aria-expanded="false"
        >
          <i class="fa-regular fa-bell"></i>
          <?php if ($technician_notification_total > 0): ?>
            <span><?= h((string) $technician_notification_total) ?></span>
          <?php endif; ?>
        </button>
      </div>
    </div>
  </div>

  <div class="technician-notification-dropdown" data-technician-notification-dropdown>
    <div class="technician-notification-dropdown-head">
      <?= h(html_entity_decode('&#3585;&#3634;&#3619;&#3649;&#3592;&#3657;&#3591;&#3648;&#3605;&#3639;&#3629;&#3609;', ENT_QUOTES, 'UTF-8')) ?>
    </div>

    <?php if ($technician_notification_total > 0): ?>
      <div class="technician-notification-items">
        <?php foreach ($technician_notification_jobs as $job): ?>
          <a href="<?= h($job['url']) ?>" class="technician-notification-item technician-notification-job <?= h($job['class']) ?>">
            <span class="technician-notification-dot">
              <i class="fa-regular fa-clipboard"></i>
            </span>
            <span class="technician-notification-copy">
              <strong><?= h((string) $job['assign_id']) ?></strong>
              <span class="technician-notification-status"><?= h((string) $job['label']) ?></span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="technician-notification-empty">
        <i class="fa-regular fa-bell"></i>
        <strong><?= h(html_entity_decode('&#3652;&#3617;&#3656;&#3617;&#3637;&#3585;&#3634;&#3619;&#3649;&#3592;&#3657;&#3591;&#3648;&#3605;&#3639;&#3629;&#3609;', ENT_QUOTES, 'UTF-8')) ?></strong>
        <small><?= h(html_entity_decode('&#3586;&#3603;&#3632;&#3609;&#3637;&#3657;&#3652;&#3617;&#3656;&#3617;&#3637;&#3591;&#3634;&#3609;&#3607;&#3637;&#3656;&#3605;&#3657;&#3629;&#3591;&#3592;&#3633;&#3604;&#3585;&#3634;&#3619;', ENT_QUOTES, 'UTF-8')) ?></small>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($technician_notification_total > 0): ?>
    <div class="technician-floating-notification" data-technician-notification role="status" aria-live="polite">
      <div class="technician-floating-notification-icon">
        <i class="fa-regular fa-bell"></i>
      </div>
      <div class="technician-floating-notification-body">
        <strong><?= h('มีงานที่ต้องจัดการ') ?> <?= h((string) $technician_notification_total) ?> <?= h(html_entity_decode('&#3591;&#3634;&#3609;', ENT_QUOTES, 'UTF-8')) ?></strong>
      </div>
    </div>
  <?php endif; ?>

  <div class="admin-summary-grid technician-reference-summary-grid">
    <a class="admin-summary-card tech-dashboard-stat tech-stat-waiting" href="<?= h(app_system_url('technician/accept_job.php')) ?>" title="งานรอยืนยันรับ" aria-label="งานรอยืนยันรับ">
      <div class="summary-icon"><i class="fa-regular fa-clock fa-fw summary-icon-clock"></i></div>
      <div>
        <span>งานรอยืนยันรับ</span>
        <strong><?= h((string) $total_assigned) ?></strong>
        <small>งานที่ได้รับมอบหมายใหม่</small>
      </div>
    </a>

    <a class="admin-summary-card tech-dashboard-stat tech-stat-active" href="<?= h(app_system_url('technician/my_jobs.php')) ?>" title="งานกำลังดำเนินการ" aria-label="งานกำลังดำเนินการ">
      <div class="summary-icon"><i class="fa-solid fa-screwdriver-wrench fa-fw summary-icon-tools"></i></div>
      <div>
        <span>งานกำลังดำเนินการ</span>
        <strong><?= h((string) $total_accepted) ?></strong>
        <small>งานที่รับแล้วใน flow ปัจจุบัน</small>
      </div>
    </a>

    <div class="admin-summary-card tech-dashboard-stat tech-stat-finished">
      <div class="summary-icon"><i class="fa-solid fa-trophy fa-fw summary-icon-trophy"></i></div>
      <div>
        <span>เสร็จเดือนนี้</span>
        <strong><?= h((string) $total_finished) ?></strong>
        <small>งานติดตั้งที่เสร็จแล้ว</small>
      </div>
    </div>

    <div class="admin-summary-card tech-dashboard-stat tech-stat-total">
      <div class="summary-icon"><i class="fa-solid fa-arrow-trend-up fa-fw summary-icon-trend"></i></div>
      <div>
        <span>ยอดรวมทั้งหมด</span>
        <strong><?= h((string) ($total_assigned + $total_accepted + $total_rejected + $total_finished)) ?></strong>
        <small>สรุปภาพรวมจากงานทั้งหมด</small>
      </div>
    </div>
  </div>

  <div class="technician-dashboard-columns">
    <section class="panel technician-latest-panel technician-dashboard-card-list-panel">
      <div class="panel-title-row">
        <div>
          <div class="panel-title">งานรอยืนยันรับล่าสุด</div>
          <p>งานใหม่ที่หัวหน้าช่างมอบหมาย</p>
        </div>
        <a class="btn-small" href="<?= h(app_system_url('technician/accept_job.php')) ?>">
          <i class="fa-solid fa-arrow-right"></i>
          ดูทั้งหมด
        </a>
      </div>

      <div class="technician-mini-job-list">
        <?php if (count($latest_waiting_jobs) === 0): ?>
          <div class="technician-mini-empty">ยังไม่มีงานรอยืนยันรับ</div>
        <?php endif; ?>

        <?php foreach ($latest_waiting_jobs as $row): ?>
          <?php
            $customer_name = trim((string) ($row['customer_name'] ?? '-'));
            $customer_initial = function_exists('mb_substr') ? mb_substr($customer_name, 0, 1, 'UTF-8') : substr($customer_name, 0, 1);
          ?>
          <a class="technician-mini-job" href="<?= h(app_system_url('technician/job_detail.php?id=' . urlencode((string) ($row['assign_id'] ?? '')))) ?>">
            <span class="technician-mini-job-icon"><?= h($customer_initial !== '' ? $customer_initial : '-') ?></span>
            <span class="technician-mini-job-copy">
              <strong><?= h($customer_name) ?></strong>
              <small><?= h($row['assign_id'] ?? '-') ?> · ติดตั้ง <?= h(technician_dashboard_format_date($row['assign_install_date'] ?? '')) ?> <?= h(technician_dashboard_format_time($row['assign_install_time'] ?? '')) ?></small>
            </span>
            <em class="technician-mini-status status-waiting">รอยืนยันรับ</em>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="panel technician-latest-panel technician-dashboard-card-list-panel">
      <div class="panel-title-row">
        <div>
          <div class="panel-title">งานที่กำลังดำเนินการ</div>
          <p>งานที่รับแล้วและรอดำเนินการต่อ</p>
        </div>
        <a class="btn-small" href="<?= h(app_system_url('technician/my_jobs.php')) ?>">
          <i class="fa-solid fa-arrow-right"></i>
          ดูทั้งหมด
        </a>
      </div>

      <div class="technician-mini-job-list">
        <?php if (count($latest_active_jobs) === 0): ?>
          <div class="technician-mini-empty">ยังไม่มีงานที่กำลังดำเนินการ</div>
        <?php endif; ?>

        <?php foreach ($latest_active_jobs as $row): ?>
          <?php
            $customer_name = trim((string) ($row['customer_name'] ?? '-'));
            $customer_initial = function_exists('mb_substr') ? mb_substr($customer_name, 0, 1, 'UTF-8') : substr($customer_name, 0, 1);
          ?>
          <a class="technician-mini-job" href="<?= h(app_system_url('technician/job_detail.php?id=' . urlencode((string) ($row['assign_id'] ?? '')))) ?>">
            <span class="technician-mini-job-icon"><?= h($customer_initial !== '' ? $customer_initial : '-') ?></span>
            <span class="technician-mini-job-copy">
              <strong><?= h($customer_name) ?></strong>
              <small><?= h($row['assign_id'] ?? '-') ?> · ติดตั้ง <?= h(technician_dashboard_format_date($row['assign_install_date'] ?? '')) ?> <?= h(technician_dashboard_format_time($row['assign_install_time'] ?? '')) ?></small>
            </span>
            <em class="technician-mini-status status-active">พร้อมติดตั้ง</em>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</div><script src="<?= h(app_asset_url('technician/assets/js/technician.js')) ?>?v=<?= h(asset_version('technician/assets/js/technician.js')) ?>"></script>
<?php
layout_footer();
