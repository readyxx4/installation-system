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

function manager_icon_svg(string $name): string
{
    $icons = [
        'search' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20L16.65 16.65"></path></svg>',
        'calendar' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4"></path><path d="M8 2v4"></path><path d="M3 10h18"></path></svg>',
        'clock' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>',
        'clipboard' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="8" y="2" width="8" height="4" rx="1"></rect><path d="M9 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-3"></path><path d="M8 12h8"></path><path d="M8 16h6"></path></svg>',
        'check' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6L9 17l-5-5"></path></svg>',
        'users' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
        'tool' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5 5L3 18l3 3 6.7-6.7a4 4 0 0 0 5-5l-2.4 2.4-3-3 2.4-2.4z"></path></svg>',
        'list' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path></svg>',
        'plus' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>',
        'arrow' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14"></path><path d="M13 5l7 7-7 7"></path></svg>',
        'box' => '<svg class="manager-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 8l-9-5-9 5 9 5 9-5z"></path><path d="M3 8v8l9 5 9-5V8"></path><path d="M12 13v8"></path></svg>',
    ];

    return $icons[$name] ?? '';
}

$total_setups = safe_count($conn, "SELECT COUNT(*) AS total FROM setup");
$total_waiting_setups = safe_count($conn, "
    SELECT COUNT(*) AS total
    FROM setup s
    WHERE s.setup_status = 0
      AND NOT EXISTS (
          SELECT 1
          FROM assignment a
          WHERE a.setup_id = s.setup_id
            AND a.assign_status = 4
      )
");
$total_assigned = safe_count($conn, "SELECT COUNT(*) AS total FROM assignment WHERE assign_status = 1");
$total_accepted = safe_count($conn, "SELECT COUNT(*) AS total FROM assignment WHERE assign_status = 2");
$total_done = safe_count($conn, "SELECT COUNT(*) AS total FROM assignment WHERE assign_status = 5");
$total_technicians_ready = safe_count($conn, "SELECT COUNT(*) AS total FROM technicians WHERE tech_status = 0");
$total_technicians_busy = safe_count($conn, "SELECT COUNT(*) AS total FROM technicians WHERE tech_status = 1");
$total_technicians = safe_count($conn, "SELECT COUNT(*) AS total FROM technicians");
$total_rejected = safe_count($conn, "SELECT COUNT(*) AS total FROM assignment WHERE assign_status = 3");
$total_cancelled = safe_count($conn, "SELECT COUNT(*) AS total FROM assignment WHERE assign_status = 4");

layout_header('หน้าหลักหัวหน้าช่าง', 'dashboard');
?>

<div class="admin-dashboard-v2 manager-home-v2">
  <div class="admin-dashboard-top">
    <div>
      <h1>ภาพรวมหัวหน้าช่าง</h1>
      <p>สรุปงานติดตั้ง การมอบหมายงาน และสถานะความพร้อมของช่างติดตั้ง</p>
    </div>

    <div class="admin-dashboard-actions">
      <div class="admin-date-pill">
        <i class="fa-regular fa-calendar"></i>
        <?= h(date('d/m/Y')) ?>
      </div>
    </div>
  </div>

  <div class="admin-summary-grid manager-home-summary-grid">
    <div class="admin-summary-card">
      <div>
        <span>งานติดตั้งทั้งหมด</span>
        <strong><?= h((string) $total_setups) ?></strong>
        <small>รายการงานในระบบ</small>
      </div>
      <div class="summary-icon">
        <i class="fa-solid fa-box"></i>
      </div>
    </div>

    <div class="admin-summary-card">
      <div>
        <span>ยังไม่ได้มอบหมาย</span>
        <strong><?= h((string) $total_waiting_setups) ?></strong>
        <small>งานที่ต้องเลือกช่าง</small>
      </div>
      <div class="summary-icon">
        <i class="fa-regular fa-clock"></i>
      </div>
    </div>

    <div class="admin-summary-card">
      <div>
        <span>มอบหมายงานแล้ว</span>
        <strong><?= h((string) $total_assigned) ?></strong>
        <small>รอช่างยืนยันรับงาน</small>
      </div>
      <div class="summary-icon">
        <i class="fa-solid fa-clipboard-list"></i>
      </div>
    </div>

    <div class="admin-summary-card">
      <div>
        <span>ช่างรับงานแล้ว</span>
        <strong><?= h((string) $total_accepted) ?></strong>
        <small>อยู่ระหว่างดำเนินงาน</small>
      </div>
      <div class="summary-icon">
        <i class="fa-solid fa-circle-check"></i>
      </div>
    </div>

    <div class="admin-summary-card">
      <div>
        <span>ช่างติดตั้ง</span>
        <strong><?= h((string) $total_technicians) ?></strong>
        <small>จำนวนช่างทั้งหมด</small>
      </div>
      <div class="summary-icon">
        <i class="fa-solid fa-users"></i>
      </div>
    </div>
  </div>

  <div class="admin-dashboard-info-grid manager-home-grid">
    <section class="admin-widget manager-home-panel status-panel">
      <div class="admin-widget-head">
        <div>
          <h2>สถานะช่างติดตั้ง</h2>
          <p>สรุปความพร้อมของช่างก่อนมอบหมายงาน</p>
        </div>
      </div>

      <div class="admin-mini-list manager-status-list">
        <div class="admin-mini-item manager-status-row ready">
          <div class="admin-mini-main">
            <strong>พร้อมรับงาน</strong>
            <span><?= h((string) $total_technicians_ready) ?> คน</span>
          </div>
          <span class="badge green">พร้อม</span>
        </div>

        <div class="admin-mini-item manager-status-row busy">
          <div class="admin-mini-main">
            <strong>ไม่พร้อมรับงาน</strong>
            <span><?= h((string) $total_technicians_busy) ?> คน</span>
          </div>
          <span class="badge red">ไม่พร้อมรับงาน</span>
        </div>

        <div class="admin-mini-item manager-status-row done">
          <div class="admin-mini-main">
            <strong>งานเสร็จสิ้น</strong>
            <span><?= h((string) $total_done) ?> รายการ</span>
          </div>
          <span class="badge blue">สำเร็จ</span>
        </div>
      </div>
    </section>
  </div>
</div>

<?php layout_footer(); ?>
