<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function h($value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_base_url(): string
{
  $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
  $folder = '/installation_system';
  $pos = strpos($script, $folder);

  if ($pos !== false) {
    return substr($script, 0, $pos + strlen($folder));
  }

  $root = rtrim(dirname($script), '/');
  return $root === '/' ? '' : $root;
}

function app_public_url(string $path = ""): string
{
  return app_base_url() . '/' . ltrim($path, '/');
}

function app_system_url(string $path = ""): string
{
  return app_public_url($path);
}

function app_asset_url(string $path = ""): string
{
  $path = ltrim($path, '/');
  return app_public_url($path);
}

function asset_version(string $path): string
{
  $file = __DIR__ . '/' . ltrim($path, '/');

  if (file_exists($file)) {
    return (string) filemtime($file);
  }

  return (string) time();
}

function redirect_to(string $url): void
{
  header("Location: " . $url);
  exit;
}

/*
|--------------------------------------------------------------------------
| Role Helper
|--------------------------------------------------------------------------
| user_role:
| 1 = หัวหน้าช่าง
| 2 = พนักงานขาย
| 3 = ผู้ดูแลระบบ
|
| ช่างติดตั้ง ใช้ค่า session เป็น 'technician'
|--------------------------------------------------------------------------
*/

function role_key($role): string
{
  return (string) $role;
}

function role_name($role): string
{
  return match (role_key($role)) {
    '1' => 'หัวหน้าช่าง',
    '2' => 'พนักงานขาย',
    '3' => 'ผู้ดูแลระบบ',
    'technician' => 'ช่างติดตั้ง',
    default => 'ไม่ทราบสิทธิ์',
  };
}

function role_dashboard($role): string
{
  return match (role_key($role)) {
    '1' => app_system_url('manager/assignment_list.php'),
    '2' => app_system_url('sale/index.php'),
    '3' => app_system_url('admin/index.php'),
    'technician' => app_system_url('technician/index.php'),
    default => app_public_url('login.html?error=role'),
  };
}

function selected($current, $value): string
{
  return (string) $current === (string) $value ? 'selected' : '';
}

function assignment_install_business_days_left(string $installDate, ?DateTimeImmutable $now = null): ?int
{
  $installDate = trim($installDate);
  if ($installDate === '') {
    return null;
  }

  $timezone = new DateTimeZone('Asia/Bangkok');
  $today = ($now ?? new DateTimeImmutable('now', $timezone))
    ->setTimezone($timezone)
    ->setTime(0, 0, 0);

  $targetDate = DateTimeImmutable::createFromFormat('!Y-m-d', $installDate, $timezone);
  if (!$targetDate) {
    return null;
  }

  if ($targetDate <= $today) {
    return 0;
  }

  $days = 0;
  for ($cursor = $today->modify('+1 day'); $cursor <= $targetDate; $cursor = $cursor->modify('+1 day')) {
    if ((int) $cursor->format('N') <= 5) {
      $days++;
    }
  }

  return $days;
}

function assignment_install_due_text(string $key): string
{
  $texts = [
    'near' => '\u0e43\u0e01\u0e25\u0e49\u0e16\u0e36\u0e07\u0e01\u0e33\u0e2b\u0e19\u0e14',
    'urgent' => '\u0e40\u0e23\u0e48\u0e07\u0e14\u0e48\u0e27\u0e19',
    'today' => '\u0e16\u0e36\u0e07\u0e01\u0e33\u0e2b\u0e19\u0e14\u0e27\u0e31\u0e19\u0e19\u0e35\u0e49',
    'overdue' => '\u0e40\u0e01\u0e34\u0e19\u0e01\u0e33\u0e2b\u0e19\u0e14',
    'manager_change_tech' => '\u0e04\u0e27\u0e23\u0e1e\u0e34\u0e08\u0e32\u0e23\u0e13\u0e32\u0e40\u0e1b\u0e25\u0e35\u0e48\u0e22\u0e19\u0e0a\u0e48\u0e32\u0e07',
    'manager_not_accepted_today' => '\u0e27\u0e31\u0e19\u0e15\u0e34\u0e14\u0e15\u0e31\u0e49\u0e07\u0e41\u0e25\u0e49\u0e27\u0e22\u0e31\u0e07\u0e44\u0e21\u0e48\u0e23\u0e31\u0e1a\u0e07\u0e32\u0e19',
  ];

  if (!isset($texts[$key])) {
    return '';
  }

  return json_decode('"' . $texts[$key] . '"') ?: '';
}

function assignment_install_due_warning($installDate, $installTime, $assignStatus, string $viewer = 'technician', ?DateTimeImmutable $now = null): array
{
  $installDate = trim((string) $installDate);
  $installTime = trim((string) $installTime);
  $assignStatus = (string) $assignStatus;

  $normal = [
    'level' => 'normal',
    'label' => '',
    'badge' => '',
    'business_days_left' => null,
  ];

  if ($installDate === '' || !in_array($assignStatus, ['1', '2'], true)) {
    return $normal;
  }

  $timezone = new DateTimeZone('Asia/Bangkok');
  $current = ($now ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone);
  $timeForDeadline = $installTime !== '' ? $installTime : '23:59:59';
  if (preg_match('/^\d{2}:\d{2}$/', $timeForDeadline) === 1) {
    $timeForDeadline .= ':00';
  }

  $deadline = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $installDate . ' ' . $timeForDeadline, $timezone);
  if (!$deadline) {
    $deadline = DateTimeImmutable::createFromFormat('Y-m-d H:i', $installDate . ' ' . substr($timeForDeadline, 0, 5), $timezone);
  }

  if ($deadline instanceof DateTimeImmutable && $deadline < $current) {
    return [
      'level' => 'overdue',
      'label' => assignment_install_due_text('overdue'),
      'badge' => 'red',
      'business_days_left' => 0,
    ];
  }

  if ($current->format('Y-m-d') === $installDate) {
    if ($viewer === 'manager' && $assignStatus === '1') {
      return [
        'level' => 'urgent',
        'label' => assignment_install_due_text('manager_not_accepted_today'),
        'badge' => 'red',
        'business_days_left' => 0,
      ];
    }

    return [
      'level' => 'today',
      'label' => assignment_install_due_text('today'),
      'badge' => 'orange',
      'business_days_left' => 0,
    ];
  }

  $businessDaysLeft = assignment_install_business_days_left($installDate, $current);
  if ($businessDaysLeft === 2) {
    return [
      'level' => 'near',
      'label' => assignment_install_due_text('near'),
      'badge' => 'yellow',
      'business_days_left' => $businessDaysLeft,
    ];
  }

  if ($businessDaysLeft === 1) {
    return [
      'level' => 'urgent',
      'label' => ($viewer === 'manager' && $assignStatus === '1')
        ? assignment_install_due_text('manager_change_tech')
        : assignment_install_due_text('urgent'),
      'badge' => ($viewer === 'manager' && $assignStatus === '1') ? 'red' : 'orange',
      'business_days_left' => $businessDaysLeft,
    ];
  }

  return [
    'level' => 'normal',
    'label' => '',
    'badge' => '',
    'business_days_left' => $businessDaysLeft,
  ];
}

function assignment_install_due_badge(array $warning): string
{
  if (($warning['level'] ?? 'normal') === 'normal' || ($warning['label'] ?? '') === '') {
    return '';
  }

  $badge = $warning['badge'] ?? 'orange';
  return '<span class="badge ' . h($badge) . ' install-due-badge">' . h($warning['label']) . '</span>';
}
function assignment_install_due_filter_match(string $filter, array $warning, $assignStatus): bool
{
  $filter = trim($filter);
  if ($filter === '') {
    return true;
  }

  $level = (string) ($warning['level'] ?? 'normal');
  $daysLeft = $warning['business_days_left'] ?? null;
  $assignStatus = (string) $assignStatus;

  return match ($filter) {
    'near' => $level === 'near',
    'urgent' => $level === 'urgent' && (int) $daysLeft === 1,
    'today' => $level === 'today' || ($level === 'urgent' && (int) $daysLeft === 0 && $assignStatus === '1'),
    'overdue' => $level === 'overdue',
    'reassign' => $assignStatus === '1' && $level === 'urgent' && (int) $daysLeft === 1,
    default => true,
  };
}
function flash_message(): string
{
  $status = $_GET['status'] ?? '';

  $messages = [
    'created' => ['success', 'เพิ่มข้อมูลสำเร็จ', 'ระบบได้บันทึกข้อมูลใหม่เรียบร้อยแล้ว'],
    'updated' => ['success', 'แก้ไขข้อมูลสำเร็จ', 'ระบบได้อัปเดตข้อมูลเรียบร้อยแล้ว'],
    'accept_updated' => ['success', 'รับงานสำเร็จ', 'ระบบบันทึกการรับงานเรียบร้อยแล้ว'],
    'deleted' => ['success', 'ลบข้อมูลสำเร็จ', 'ระบบได้ลบข้อมูลเรียบร้อยแล้ว'],

    'error' => ['error', 'เกิดข้อผิดพลาด', 'กรุณาตรวจสอบข้อมูลอีกครั้ง แล้วลองใหม่'],
    'duplicate' => ['warning', 'ข้อมูลซ้ำในระบบ', 'ข้อมูลนี้มีอยู่ในระบบแล้ว กรุณาตรวจสอบอีกครั้ง'],
    'duplicate_id' => ['warning', 'รหัสซ้ำในระบบ', 'รหัสนี้มีอยู่ในระบบแล้ว'],
    'duplicate_name' => ['warning', 'ชื่อพนักงานซ้ำ', 'ชื่อพนักงานนี้ถูกใช้แล้ว กรุณาเปลี่ยนชื่อใหม่'],
    'product_duplicate_name' => ['warning', 'ชื่อสินค้าซ้ำ', 'ชื่อสินค้านี้มีอยู่ในระบบแล้ว กรุณาใช้ชื่อสินค้าอื่น'],
    'duplicate_phone' => ['warning', 'เบอร์โทรศัพท์ซ้ำ', 'เบอร์โทรศัพท์นี้มีอยู่ในระบบแล้ว'],
    'duplicate_email' => ['warning', 'อีเมลซ้ำ', 'อีเมลนี้มีอยู่ในระบบแล้ว'],

    'phone' => ['warning', 'เบอร์โทรศัพท์ไม่ถูกต้อง', 'กรุณากรอกเบอร์โทรศัพท์ให้ครบ 10 หลัก'],
    'email' => ['warning', 'อีเมลไม่ถูกต้อง', 'กรุณากรอกอีเมลให้ถูกต้อง'],
    'role' => ['warning', 'สิทธิ์ไม่ถูกต้อง', 'กรุณาเลือกสิทธิ์การใช้งานให้ถูกต้อง'],

    'manager_assigned' => ['error', 'ไม่สามารถลบพนักงานได้', 'เนื่องจากหัวหน้าช่างคนนี้ได้ทำการมอบหมายงานไปแล้ว'],
    'tech_assigned' => ['error', 'ไม่สามารถลบข้อมูลช่างได้', 'เนื่องจากช่างคนนี้ถูกมอบหมายงานแล้ว'],

    'customer_linked' => ['error', 'ไม่สามารถลบข้อมูลลูกค้าได้', 'เนื่องจากลูกค้าคนนี้มีงานติดตั้งหรือข้อมูลที่เกี่ยวข้องอยู่'],
    'customer_delete_disabled' => ['warning', 'ไม่เปิดใช้งานการลบลูกค้า', 'ให้ใช้การระงับบัญชีแทน เพื่อเก็บข้อมูลลูกค้าและประวัติทั้งหมดไว้ครบถ้วน'],
    'customer_suspended' => ['success', 'ระงับบัญชีลูกค้าแล้ว', 'ลูกค้าคนนี้จะไม่สามารถเข้าสู่ระบบได้จนกว่าจะกู้คืนบัญชี'],
    'customer_restored' => ['success', 'กู้คืนบัญชีลูกค้าแล้ว', 'ลูกค้าคนนี้สามารถเข้าสู่ระบบได้ตามปกติ'],
    'user_linked' => ['error', 'ไม่สามารถลบพนักงานได้', 'เนื่องจากพนักงานนี้มีข้อมูลที่เชื่อมต่อกับรายการอื่นอยู่'],

    'product_linked' => ['error', 'ไม่สามารถลบสินค้าได้', 'เนื่องจากสินค้านี้ถูกใช้ในงานติดตั้งแล้ว'],
    'product_type_linked' => ['error', 'ไม่สามารถลบประเภทสินค้าได้', 'เนื่องจากมีสินค้าอยู่ในประเภทนี้'],

    'time_conflict' => ['warning', 'ไม่สามารถบันทึกได้', 'ช่วงเวลานี้มีงานของช่างแล้ว กรุณาเลือกช่วงเวลาอื่น'],
    'past_date' => ['warning', 'ไม่สามารถเลือกวันที่ผ่านมาแล้วได้', 'กรุณาเลือกวันที่ปัจจุบันหรือวันในอนาคต'],
    'tech_unavailable' => ['warning', 'ช่างไม่พร้อมรับงาน', 'กรุณาเลือกช่างที่พร้อมรับงาน หรือคงช่างเดิมของงานนี้ไว้'],
    'confirm_tech_change' => ['warning', 'ต้องยืนยันการเปลี่ยนช่าง', 'งานนี้ถูกช่างรับงานแล้ว กรุณายืนยันก่อนเปลี่ยนช่าง'],
    'assignment_locked' => ['warning', 'แก้ไขไม่ได้ตามสถานะงาน', 'สถานะงานปัจจุบันจำกัดการแก้ไขข้อมูลบางรายการ'],
    'readonly' => ['warning', 'งานเสร็จสิ้นแล้ว', 'ดูรายละเอียดได้อย่างเดียว ไม่สามารถแก้ไขหรือยกเลิกได้'],
  ];

  if (!isset($messages[$status])) {
    return '';
  }

  [$type, $title, $text] = $messages[$status];

  $icon = match ($type) {
    'success' => '✓',
    'warning' => '!',
    'error' => '×',
    default => 'i',
  };

  $buttonText = match ($type) {
    'success' => 'ตกลง',
    'warning' => 'รับทราบ',
    'error' => 'ลองอีกครั้ง',
    default => 'ตกลง',
  };

  return '
    <div class="pretty-alert-overlay show" id="prettyAlert">
      <div class="pretty-alert-card pretty-alert-' . h($type) . '">
        <div class="pretty-alert-icon">' . h($icon) . '</div>

        <h2>' . h($title) . '</h2>

        <p>' . h($text) . '</p>

        <div class="pretty-alert-line"></div>

        <button type="button" class="pretty-alert-btn" onclick="closePrettyAlert()">
          ' . h($buttonText) . '
        </button>
      </div>
    </div>

    <script>
      function closePrettyAlert() {
        const alertBox = document.getElementById("prettyAlert");

        if (alertBox) {
          alertBox.classList.remove("show");
          setTimeout(() => alertBox.remove(), 200);
        }
      }
    </script>
  ';
}

function technician_shell_icon_svg(string $name, int $size = 20): string
{
  $paths = [
    'home' => '<path d="M3 12l9-9 9 9"></path><path d="M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10"></path>',
    'clipboard-check' => '<rect x="8" y="3" width="8" height="4" rx="1"></rect><path d="M8 5H6a2 2 0 00-2 2v13a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-2"></path><path d="M9 14l2 2 4-4"></path>',
    'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"></path><path d="M2 13h20"></path>',
    'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path>',
    'logout' => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"></path>',
  ];

  if (!isset($paths[$name])) {
    return '';
  }

  return '<svg class="shell-icon" width="' . h((string) $size) . '" height="' . h((string) $size) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}
function nav_item(
  string $label,
  string $icon,
  string $url,
  string $activeKey,
  string $active,
  bool $disabled = false
): void {
  $isActive = $active === $activeKey;
  $class = 'nav-link shell-nav-item';

  if ($isActive) {
    $class .= ' active';
  }

  if ($disabled) {
    $class .= ' placeholder';
    $url = 'javascript:void(0)';
  }

  echo '<a class="' . h($class) . '" href="' . h($url) . '"';

  if ($isActive) {
    echo ' aria-current="page"';
  }

  if ($disabled) {
    echo ' title="เมนูนี้ยังไม่ได้เปิดใช้งาน"';
  }

  echo '>';

  /*
  |--------------------------------------------------------------------------
  | $icon เป็น HTML ของ Font Awesome
  | จึงไม่ใช้ h() กับตัว icon
  |--------------------------------------------------------------------------
  */
  echo '<span class="nav-icon" aria-hidden="true">' . $icon . '</span>';
  echo '<span class="nav-text nav-label">' . h($label) . '</span>';

  if ($disabled) {
    echo '<span class="nav-soon nav-badge">เร็ว ๆ นี้</span>';
  }

  echo '</a>';
}

function layout_header(string $title, string $active = 'dashboard', ?string $subtitle = null): void
{
  $userName = $_SESSION['user_name'] ?? 'ผู้ใช้งาน';
  $role = $_SESSION['user_role'] ?? '';
  $roleKey = role_key($role);
  $roleClass = 'role-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $roleKey);
  $initial = mb_substr($userName, 0, 1, 'UTF-8');
  $systemName = 'ห้างโอวเปงฮง จำกัด';
  $systemDesc = 'Installation System';
  $systemLogo = '';

  try {
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
      $sysResult = $GLOBALS['conn']->query("
        SELECT
          system_name,
          system_logo,
          system_desc
        FROM `system`
        LIMIT 1
      ");

      if ($sysResult && $sysResult->num_rows > 0) {
        $sys = $sysResult->fetch_assoc();

        $systemName = $sys['system_name'] ?: $systemName;
        $systemDesc = $sys['system_desc'] ?: $systemDesc;
        $systemLogo = $sys['system_logo'] ?: '';
      }
    }
  } catch (Throwable $e) {
    // ใช้ค่าเดิม ถ้าดึงข้อมูลระบบไม่ได้
  }
  ?>

  <!DOCTYPE html>
  <html lang="th">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= h($title) ?> - <?= h($systemName) ?></title>

    <link
      rel="stylesheet"
      href="<?= h(app_asset_url('style.css')) ?>?v=<?= h(asset_version('style.css')) ?>"
    >

    <!-- Font Awesome -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >
  </head>

  <body class="app-body <?= h($roleClass) ?>">

    <div class="app-shell">

      <aside class="sidebar app-sidebar">

        <?php
        $systemLogoUrl = '';

        if ($systemLogo !== '') {
          if (preg_match('/^https?:\/\//i', $systemLogo)) {
            $systemLogoUrl = $systemLogo;
          } else {
            $systemLogoUrl = app_public_url($systemLogo);
          }
        }
        ?>

        <!-- Brand -->
        <div class="brand shell-brand">

          <?php if ($systemLogoUrl !== ''): ?>

            <div class="brand-logo">
              <img
                src="<?= h($systemLogoUrl) ?>"
                alt="โลโก้ระบบ"
              >
            </div>

          <?php else: ?>

            <div class="brand-mark">O</div>

          <?php endif; ?>

          <div class="brand-copy shell-brand-copy">
            <strong>
              <?= h($systemName) ?>
            </strong>

            <span>
              <?= h($systemDesc) ?>
            </span>
          </div>

        </div>

        <!-- หน้าหลัก: แสดงกับ Role อื่น ยกเว้นพนักงานขาย -->
        <?php if ($roleKey !== '1' && $roleKey !== '2'): ?>
          <?php
          nav_item(
            'หน้าหลัก',
            $roleKey === 'technician' ? technician_shell_icon_svg('home', 20) : '<i class="fa-solid fa-house"></i>',
            role_dashboard($role),
            'dashboard',
            $active
          );
          ?>
        <?php endif; ?>

        <!-- ====================================== -->
        <!-- หัวหน้าช่าง -->
        <!-- ====================================== -->

        <?php if ($roleKey === '1'): ?>

          <div class="nav-section-title">
            เมนูหัวหน้าช่าง
          </div>

          <?php
          nav_item(
            'มอบหมายงาน',
            '<i class="fa-solid fa-clipboard-list"></i>',
            app_system_url('manager/assignment_list.php'),
            'assignment_list',
            $active
          );

          nav_item(
            'รายการมอบหมายงาน',
            '<i class="fa-solid fa-clock-rotate-left"></i>',
            app_system_url('manager/assignment_history.php'),
            'assignment_history',
            $active
          );
          ?>


        <!-- ====================================== -->
        <!-- พนักงานขาย -->
        <!-- ====================================== -->

        <?php elseif ($roleKey === '2'): ?>

          <div class="nav-section-title">
            เมนูพนักงานขาย
          </div>

          <?php
          nav_item(
            'สร้างใบงานติดตั้ง',
            '<i class="fa-solid fa-file-circle-plus"></i>',
            app_system_url('sale/index.php'),
            'create_setup',
            $active
          );

          nav_item(
            'รายการงานติดตั้ง',
            '<i class="fa-solid fa-file-lines"></i>',
            app_system_url('sale/setups.php'),
            'setups',
            $active
          );

          nav_item(
            'ประวัติใบงาน',
            '<i class="fa-solid fa-clock-rotate-left"></i>',
            app_system_url('sale/setup_history.php'),
            'setup_history',
            $active
          );
          ?>

          <!--
          <?php
          nav_item(
            'บันทึกการจ่ายสินค้า',
            '<i class="fa-solid fa-boxes-stacked"></i>',
            app_system_url('sale/payment.php'),
            'payment',
            $active
          );

          nav_item(
            'รายงาน',
            '<i class="fa-solid fa-chart-column"></i>',
            app_system_url('sale/report.php'),
            'report',
            $active
          );
          ?>
          -->


        <!-- ====================================== -->
        <!-- ผู้ดูแลระบบ -->
        <!-- ====================================== -->

        <?php elseif ($roleKey === '3'): ?>

          <div class="nav-section-title">
            เมนู
          </div>

          <?php
          nav_item(
            'จัดการพนักงาน',
            '<i class="fa-solid fa-users"></i>',
            app_system_url('admin/users.php'),
            'users',
            $active
          );

          nav_item(
            'จัดการข้อมูลช่าง',
            '<i class="fa-solid fa-screwdriver-wrench"></i>',
            app_system_url('admin/technicians.php'),
            'technicians',
            $active
          );

          nav_item(
            'จัดการข้อมูลลูกค้า',
            '<i class="fa-solid fa-address-book"></i>',
            app_system_url('admin/customers.php'),
            'customers',
            $active
          );

          nav_item(
            'ประเภทสินค้า',
            '<i class="fa-solid fa-table-cells-large"></i>',
            app_system_url('admin/product_types.php'),
            'product_types',
            $active
          );

          nav_item(
            'สินค้า',
            '<i class="fa-solid fa-box"></i>',
            app_system_url('admin/products.php'),
            'products',
            $active
          );

          nav_item(
            'ข้อมูลระบบ',
            '<i class="fa-solid fa-gear"></i>',
            app_system_url('admin/system.php'),
            'system',
            $active
          );
          ?>


        <!-- ====================================== -->
        <!-- ช่างติดตั้ง -->
        <!-- ====================================== -->

        <?php elseif ($roleKey === 'technician'): ?>

          <div class="nav-section-title">
            เมนูช่างติดตั้ง
          </div>

          <?php
          nav_item(
            'ยืนยันการรับงาน',
            technician_shell_icon_svg('clipboard-check', 20),
            app_system_url('technician/accept_job.php'),
            'accept_job',
            $active
          );

          nav_item(
            'งานของฉัน',
            technician_shell_icon_svg('briefcase', 20),
            app_system_url('technician/my_jobs.php'),
            'my_jobs',
            $active
          );
          ?>

        <?php endif; ?>


        <!-- ====================================== -->
        <!-- Profile -->
        <!-- ====================================== -->

        <div class="sidebar-profile sidebar-user-card">

          <div class="sidebar-profile-avatar">
            <?= h($initial) ?>
          </div>

          <div class="sidebar-profile-info">

            <strong>
              <?= h($userName) ?>
            </strong>

            <span>
              <?= h(role_name($role)) ?>
            </span>

          </div>

        </div>


        <!-- ====================================== -->
        <!-- Account -->
        <!-- ====================================== -->

        <div class="account-menu sidebar-account">

          <div class="nav-section-title">
            <?= $roleKey === '3' ? 'บัญชีพนักงาน' : 'บัญชีผู้ใช้' ?>
          </div>

          <a
            class="nav-link shell-nav-item logout-menu shell-logout"
            href="<?= h(app_system_url('logout.php')) ?>"
            onclick="return confirm('ต้องการออกจากระบบจริงหรือไม่?')"
          >

            <span class="nav-icon">
              <?= $roleKey === 'technician' ? technician_shell_icon_svg('logout', 16) : '<i class="fa-solid fa-right-from-bracket"></i>' ?>
            </span>

            <span class="nav-text">
              ออกจากระบบ
            </span>

          </a>

        </div>

      </aside>


      <!-- ======================================== -->
      <!-- Main Content -->
      <!-- ======================================== -->

      <main class="main">

        <header class="topbar app-topbar admin-topbar-clean shell-topbar">
          <div class="topbar-title shell-topbar-title">
            <h1><?= h($title) ?></h1>
            <p><?= h($subtitle ?? role_name($role)) ?></p>
          </div>

          <div class="topbar-actions shell-topbar-actions">
            <div class="topbar-date shell-topbar-date shell-topbar-pill">
              <?= technician_shell_icon_svg('calendar', 16) ?>
              <span><?= h(date('d/m/Y')) ?></span>
            </div>

            <div class="topbar-user shell-topbar-user">
              <span class="topbar-avatar shell-topbar-avatar"><?= h($initial) ?></span>
              <span class="topbar-user-copy shell-topbar-user-copy">
                <strong><?= h($userName) ?></strong>
                <small><?= h(role_name($role)) ?></small>
              </span>
            </div>
          </div>
        </header>

        <section class="content">

          <?php
}


function layout_footer(): void
{
  ?>

        </section>

      </main>

    </div>

  </body>

  </html>

  <?php
}


function page_head(
  string $title,
  string $subtitle = '',
  ?string $buttonUrl = null,
  string $buttonText = '+ เพิ่มใหม่'
): void {
  ?>

  <div class="page-head">

    <div class="page-title">

      <h1>
        <?= h($title) ?>
      </h1>

      <div class="breadcrumb">
        หน้าหลัก › <?= h($title) ?><?= $subtitle ? ' › ' . h($subtitle) : '' ?>
      </div>

    </div>

    <?php if ($buttonUrl): ?>

      <a
        class="btn"
        href="<?= h($buttonUrl) ?>"
      >
        <?= h($buttonText) ?>
      </a>

    <?php endif; ?>

  </div>

  <?php
}







