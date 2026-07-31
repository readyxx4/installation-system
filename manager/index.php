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
$total_waiting_setups = safe_count($conn, "SELECT COUNT(*) AS total FROM setup WHERE setup_status = 0");
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

<div class="manager-home-v2">
  <div class="manager-home-head">
    <div>
      <h1>ภาพรวมหัวหน้าช่าง</h1>
      <p>สรุปงานติดตั้ง การมอบหมายงาน และความพร้อมของช่างติดตั้ง</p>
    </div>

    <div class="manager-home-tools">
      <div class="manager-search-pill">
        <?= manager_icon_svg('search') ?>
        <span>ค้นหาข้อมูลงานติดตั้ง...</span>
      </div>
      <div class="manager-date-pill">
        <?= manager_icon_svg('calendar') ?>
        <strong><?= h(date('d/m/Y')) ?></strong>
      </div>
    </div>
  </div>

  <div class="manager-home-summary-grid">
    <div class="manager-home-summary-card green">
      <div>
        <p>งานติดตั้งทั้งหมด</p>
        <h2><?= h((string) $total_setups) ?></h2>
        <span>รายการงานในระบบ</span>
      </div>
      <div class="summary-icon"><?= manager_icon_svg('box') ?></div>
    </div>

    <div class="manager-home-summary-card orange">
      <div>
        <p>รอมอบหมายงาน</p>
        <h2><?= h((string) $total_waiting_setups) ?></h2>
        <span>งานที่ต้องเลือกช่าง</span>
      </div>
      <div class="summary-icon"><?= manager_icon_svg('clock') ?></div>
    </div>

    <div class="manager-home-summary-card blue">
      <div>
        <p>มอบหมายแล้ว</p>
        <h2><?= h((string) $total_assigned) ?></h2>
        <span>รอช่างยืนยันรับงาน</span>
      </div>
      <div class="summary-icon"><?= manager_icon_svg('clipboard') ?></div>
    </div>

    <div class="manager-home-summary-card cyan">
      <div>
        <p>ช่างรับงานแล้ว</p>
        <h2><?= h((string) $total_accepted) ?></h2>
        <span>อยู่ระหว่างดำเนินงาน</span>
      </div>
      <div class="summary-icon"><?= manager_icon_svg('check') ?></div>
    </div>

    <div class="manager-home-summary-card purple">
      <div>
        <p>ช่างติดตั้ง</p>
        <h2><?= h((string) $total_technicians) ?></h2>
        <span>จำนวนช่างทั้งหมด</span>
      </div>
      <div class="summary-icon"><?= manager_icon_svg('users') ?></div>
    </div>
  </div>

  <div class="manager-home-grid">
    <section class="manager-home-panel menu-panel">
      <div class="manager-home-panel-head">
        <div>
          <h2>เมนูสำหรับหัวหน้าช่าง</h2>
          <p>ใช้สำหรับมอบหมายงานให้ช่าง และตรวจสอบรายการมอบหมายงาน</p>
        </div>
        <span class="manager-soft-badge">งานรอจัดการ <?= h((string) $total_waiting_setups) ?> รายการ</span>
      </div>

      <div class="manager-home-menu-grid">
        <a class="manager-home-menu-card" href="<?= h(app_system_url('manager/assignments.php')) ?>">
          <div class="menu-icon-box"><?= manager_icon_svg('plus') ?></div>
          <h3>มอบหมายงานช่าง</h3>
          <p>เลือกงานติดตั้งที่รอมอบหมาย แล้วเลือกช่างที่พร้อมรับงาน</p>
        </a>

        <a class="manager-home-menu-card" href="<?= h(app_system_url('manager/assignment_list.php')) ?>">
          <div class="menu-icon-box"><?= manager_icon_svg('list') ?></div>
          <h3>รายการมอบหมายงาน</h3>
          <p>ตรวจสอบสถานะงานที่มอบหมายแล้ว และติดตามการรับงานของช่าง</p>
        </a>
      </div>
    </section>

    <section class="manager-home-panel status-panel">
      <div class="manager-home-panel-head">
        <div>
          <h2>สถานะช่างติดตั้ง</h2>
          <p>สรุปความพร้อมของช่างก่อนมอบหมายงาน</p>
        </div>
      </div>

      <div class="manager-status-list">
        <div class="manager-status-row ready">
          <div>
            <strong>พร้อมรับงาน</strong>
            <span><?= h((string) $total_technicians_ready) ?> คน</span>
          </div>
          <b>พร้อม</b>
        </div>

        <div class="manager-status-row busy">
          <div>
            <strong>ไม่พร้อมรับงาน</strong>
            <span><?= h((string) $total_technicians_busy) ?> คน</span>
          </div>
          <b>ไม่พร้อม</b>
        </div>

        <div class="manager-status-row done">
          <div>
            <strong>งานเสร็จสิ้น</strong>
            <span><?= h((string) $total_done) ?> รายการ</span>
          </div>
          <b>สำเร็จ</b>
        </div>
      </div>
    </section>
  </div>

  <div class="manager-home-grid lower manager-full-summary-row">
    <section class="manager-home-panel manager-assignment-summary-panel">
      <div class="manager-home-panel-head">
        <div>
          <h2>สรุปสถานะการมอบหมาย</h2>
          <p>ภาพรวมจำนวนงานตามขั้นตอนของหัวหน้าช่าง</p>
        </div>
      </div>

      <div class="manager-mini-stat-grid manager-mini-stat-grid-wide">
        <div class="manager-mini-stat wait">
          <span>รอมอบหมาย</span>
          <strong><?= h((string) $total_waiting_setups) ?></strong>
        </div>
        <div class="manager-mini-stat assigned">
          <span>มอบหมายแล้ว</span>
          <strong><?= h((string) $total_assigned) ?></strong>
        </div>
        <div class="manager-mini-stat accepted">
          <span>ช่างรับงาน</span>
          <strong><?= h((string) $total_accepted) ?></strong>
        </div>
        <div class="manager-mini-stat rejected">
          <span>ปฏิเสธ/ยกเลิก</span>
          <strong><?= h((string) ($total_rejected + $total_cancelled)) ?></strong>
        </div>
      </div>
    </section>
  </div>
</div>

<?php layout_footer(); ?>