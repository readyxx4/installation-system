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
| 0 = ลูกค้า
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
    '0' => 'ลูกค้า',
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
    '0' => app_system_url('customer/index.php'),
    '1' => app_system_url('manager/index.php'),
    '2' => app_system_url('finance/index.php'),
    '3' => app_system_url('admin/index.php'),
    'technician' => app_system_url('technician/index.php'),
    default => app_public_url('login.html?error=role'),
  };
}

function selected($current, $value): string
{
  return (string) $current === (string) $value ? 'selected' : '';
}

function flash_message(): string
{
  $status = $_GET['status'] ?? '';

  $messages = [
    'created' => ['success', 'เพิ่มข้อมูลสำเร็จ', 'ระบบได้บันทึกข้อมูลใหม่เรียบร้อยแล้ว'],
    'updated' => ['success', 'แก้ไขข้อมูลสำเร็จ', 'ระบบได้อัปเดตข้อมูลเรียบร้อยแล้ว'],
    'deleted' => ['success', 'ลบข้อมูลสำเร็จ', 'ระบบได้ลบข้อมูลเรียบร้อยแล้ว'],

    'error' => ['error', 'เกิดข้อผิดพลาด', 'กรุณาตรวจสอบข้อมูลอีกครั้ง แล้วลองใหม่'],
    'duplicate' => ['warning', 'ข้อมูลซ้ำในระบบ', 'ข้อมูลนี้มีอยู่ในระบบแล้ว กรุณาตรวจสอบอีกครั้ง'],
    'duplicate_id' => ['warning', 'รหัสซ้ำในระบบ', 'รหัสนี้มีอยู่ในระบบแล้ว'],
    'duplicate_name' => ['warning', 'ชื่อผู้ใช้ซ้ำ', 'ชื่อผู้ใช้นี้ถูกใช้แล้ว กรุณาเปลี่ยนชื่อใหม่'],
    'duplicate_phone' => ['warning', 'เบอร์โทรศัพท์ซ้ำ', 'เบอร์โทรศัพท์นี้มีอยู่ในระบบแล้ว'],
    'duplicate_email' => ['warning', 'อีเมลซ้ำ', 'อีเมลนี้มีอยู่ในระบบแล้ว'],

    'phone' => ['warning', 'เบอร์โทรศัพท์ไม่ถูกต้อง', 'กรุณากรอกเบอร์โทรศัพท์ให้ครบ 10 หลัก'],
    'email' => ['warning', 'อีเมลไม่ถูกต้อง', 'กรุณากรอกอีเมลให้ถูกต้อง'],
    'role' => ['warning', 'สิทธิ์ไม่ถูกต้อง', 'กรุณาเลือกสิทธิ์การใช้งานให้ถูกต้อง'],

    'manager_assigned' => ['error', 'ไม่สามารถลบผู้ใช้ได้', 'เนื่องจากหัวหน้าช่างคนนี้ได้ทำการมอบหมายงานไปแล้ว'],
    'tech_assigned' => ['error', 'ไม่สามารถลบข้อมูลช่างได้', 'เนื่องจากช่างคนนี้ถูกมอบหมายงานแล้ว'],

    'customer_linked' => ['error', 'ไม่สามารถลบข้อมูลลูกค้าได้', 'เนื่องจากลูกค้าคนนี้มีงานติดตั้งหรือข้อมูลที่เกี่ยวข้องอยู่'],
    'user_linked' => ['error', 'ไม่สามารถลบผู้ใช้ได้', 'เนื่องจากผู้ใช้นี้มีข้อมูลที่เชื่อมต่อกับรายการอื่นอยู่'],

    'product_linked' => ['error', 'ไม่สามารถลบสินค้าได้', 'เนื่องจากสินค้านี้ถูกใช้ในงานติดตั้งแล้ว'],
    'product_type_linked' => ['error', 'ไม่สามารถลบประเภทสินค้าได้', 'เนื่องจากมีสินค้าอยู่ในประเภทนี้'],
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

function nav_item(
  string $label,
  string $icon,
  string $url,
  string $activeKey,
  string $active,
  bool $disabled = false
): void {
  $class = 'nav-link';

  if ($active === $activeKey) {
    $class .= ' active';
  }

  if ($disabled) {
    $class .= ' placeholder';
    $url = 'javascript:void(0)';
  }

  echo '<a class="' . h($class) . '" href="' . h($url) . '"';

  if ($disabled) {
    echo ' title="เมนูนี้ยังไม่ได้เปิดใช้งาน"';
  }

  echo '>';
  echo '<span class="nav-icon">' . h($icon) . '</span>';
  echo '<span class="nav-text">' . h($label) . '</span>';

  if ($disabled) {
    echo '<span class="nav-soon">เร็ว ๆ นี้</span>';
  }

  echo '</a>';
}

function layout_header(string $title, string $active = 'dashboard'): void
{
  $userName = $_SESSION['user_name'] ?? 'ผู้ใช้งาน';
  $role = $_SESSION['user_role'] ?? '';
  $roleKey = role_key($role);
  $roleClass = 'role-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $roleKey);
  $initial = mb_substr($userName, 0, 1, 'UTF-8');
  ?>
  <!DOCTYPE html>
  <html lang="th">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> - ห้างโอวเปงฮง จำกัด</title>

    <link rel="stylesheet" href="<?= h(app_asset_url('style.css')) ?>?v=<?= h(asset_version('style.css')) ?>">
  </head>

  <body class="app-body <?= h($roleClass) ?>">

    <div class="app-shell">

      <aside class="sidebar">
        <?php
        $systemName = 'ห้างโอวเปงฮง จำกัด';
        $systemDesc = 'Installation System';
        $systemLogo = '';

        try {
          if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            $sysResult = $GLOBALS['conn']->query("
            SELECT system_name, system_logo, system_desc
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

        $systemLogoUrl = '';

        if ($systemLogo !== '') {
          if (preg_match('/^https?:\/\//i', $systemLogo)) {
            $systemLogoUrl = $systemLogo;
          } else {
            $systemLogoUrl = app_public_url($systemLogo);
          }
        }
        ?>

        <div class="brand">
          <?php if ($systemLogoUrl !== ''): ?>
            <div class="brand-logo">
              <img src="<?= h($systemLogoUrl) ?>" alt="โลโก้ระบบ">
            </div>
          <?php else: ?>
            <div class="brand-mark">O</div>
          <?php endif; ?>

          <div>
            <strong>
              <?= h($systemName) ?>
            </strong>
            <span>
              <?= h($systemDesc) ?>
            </span>
          </div>
        </div>

        <?php nav_item('หน้าหลัก', '⌂', role_dashboard($role), 'dashboard', $active); ?>

        <?php if ($roleKey === '0'): ?>
          <div class="nav-section-title">เมนูลูกค้า</div>
          <?php nav_item('ติดตามการติดตั้ง', '🔎', '#', 'track', $active, true); ?>
          <?php nav_item('ยืนยันการรับสินค้า', '📦', '#', 'receive_product', $active, true); ?>
          <?php nav_item('ยืนยันผลการติดตั้ง', '✅', '#', 'confirm_result', $active, true); ?>
          <?php nav_item('ประเมินผลการติดตั้ง', '★', '#', 'review', $active, true); ?>

        <?php elseif ($roleKey === '1'): ?>
          <div class="nav-section-title">เมนูหัวหน้าช่าง</div>
          <?php nav_item('รายการมอบหมายงาน', '📄', app_system_url('manager/assignment_list.php'), 'assignment_list', $active); ?>

        <?php elseif ($roleKey === '2'): ?>
          <div class="nav-section-title">เมนูพนักงานขาย</div>
          <?php nav_item('รายการงานติดตั้ง', '📄', app_system_url('finance/setups.php'), 'setups', $active); ?>
          <?php nav_item('สร้างงานติดตั้ง', '➕', app_system_url('finance/create_setup.php'), 'setup', $active); ?>
          <?php nav_item('บันทึกการจ่ายสินค้า', '📦', app_system_url('finance/payment.php'), 'payment', $active); ?>
          <?php nav_item('รายงาน', '📊', app_system_url('finance/report.php'), 'report', $active); ?>

        <?php elseif ($roleKey === '3'): ?>
          <div class="nav-section-title">เมนู</div>
          <?php nav_item('จัดการผู้ใช้', '👥', app_system_url('admin/users.php'), 'users', $active); ?>
          <?php nav_item('จัดการข้อมูลช่าง', '🧰', app_system_url('admin/technicians.php'), 'technicians', $active); ?>
          <?php nav_item('จัดการข้อมูลลูกค้า', '🙋', app_system_url('admin/customers.php'), 'customers', $active); ?>
          <?php nav_item('ประเภทสินค้า', '▦', app_system_url('admin/product_types.php'), 'product_types', $active); ?>
          <?php nav_item('สินค้า', '📦', app_system_url('admin/products.php'), 'products', $active); ?>
          <?php nav_item('ข้อมูลระบบ', '⚙', app_system_url('admin/system.php'), 'system', $active); ?>

        <?php elseif ($roleKey === 'technician'): ?>
          <div class="nav-section-title">เมนูช่างติดตั้ง</div>
          <?php nav_item('ยืนยันการรับงาน', '✅', app_system_url('technician/accept_job.php'), 'accept_job', $active); ?>
          <?php nav_item('งานของฉัน', '📋', app_system_url('technician/my_jobs.php'), 'my_jobs', $active); ?>
        <?php endif; ?>

        <div class="sidebar-profile">
          <div class="sidebar-profile-avatar">
            <?= h($initial) ?>
          </div>

          <div class="sidebar-profile-info">
            <strong><?= h($userName) ?></strong>
            <span><?= h(role_name($role)) ?></span>
          </div>
        </div>

        <div class="account-menu">
          <div class="nav-section-title">บัญชีผู้ใช้</div>

          <a class="nav-link logout-menu" href="<?= h(app_system_url('logout.php')) ?>"
            onclick="return confirm('ต้องการออกจากระบบจริงหรือไม่?')">
            <span class="nav-icon">↻</span>
            <span class="nav-text">ออกจากระบบ</span>
          </a>
        </div>
      </aside>

      <main class="main">
        <header class="topbar admin-topbar-clean"></header>

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
      <h1><?= h($title) ?></h1>
      <div class="breadcrumb">
        หน้าหลัก › <?= h($title) ?><?= $subtitle ? ' › ' . h($subtitle) : '' ?>
      </div>
    </div>

    <?php if ($buttonUrl): ?>
      <a class="btn" href="<?= h($buttonUrl) ?>"><?= h($buttonText) ?></a>
    <?php endif; ?>
  </div>
  <?php
}