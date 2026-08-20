<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('2');

function make_setup_id(mysqli $conn): string
{
    $result = $conn->query("
        SELECT MAX(CAST(SUBSTRING(setup_id, 5) AS UNSIGNED)) AS max_number
        FROM setup
        WHERE setup_id REGEXP '^SET-[0-9]{7}$'
    ");

    if (!$result) {
        throw new RuntimeException('ไม่สามารถตรวจสอบรหัสใบงานล่าสุดได้');
    }

    $row = $result->fetch_assoc();
    $nextNumber = ((int) ($row['max_number'] ?? 0)) + 1;

    if ($nextNumber > 9999999) {
        throw new RuntimeException('รหัสใบงานเกินจำนวนที่ระบบรองรับ');
    }

    return 'SET-' . str_pad((string) $nextNumber, 7, '0', STR_PAD_LEFT);
}

function create_setup_icon(string $name): string
{
    $icons = [
        'search' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>',
        'user' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>',
        'box' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 8l-9-5-9 5 9 5 9-5z"></path><path d="M3 8v8l9 5 9-5V8"></path><path d="M12 13v8"></path></svg>',
        'map' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s7-4.4 7-11a7 7 0 1 0-14 0c0 6.6 7 11 7 11z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>',
        'note' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4z"></path><path d="M8 9h8"></path><path d="M8 13h8"></path><path d="M8 17h5"></path></svg>',
        'save' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><path d="M17 21v-8H7v8"></path><path d="M7 3v5h8"></path></svg>',
        'plus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>',
        'minus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"></path></svg>',
        'eye' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
        'trash' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path></svg>',
    ];

    return $icons[$name] ?? '';
}

$customers = [];
$customerResult = $conn->query("
    SELECT user_id, user_name, user_phone, user_email, user_address
    FROM `user`
    WHERE user_role = 0
    ORDER BY user_name ASC, user_id ASC
");
if ($customerResult) {
    while ($row = $customerResult->fetch_assoc()) {
        $customers[] = $row;
    }
}

$products = [];
$productResult = $conn->query("
    SELECT
        p.pro_id,
        p.pro_name,
        p.pro_price,
        p.pro_price_install,
        p.protype_id,
        pt.protype_name
    FROM product p
    LEFT JOIN product_type pt
        ON p.protype_id = pt.protype_id
    ORDER BY pt.protype_name ASC, p.pro_name ASC
");
if ($productResult) {
    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
    }
}

$setupId = make_setup_id($conn);

layout_header('สร้างใบงานติดตั้ง', 'create_setup');
?>

<link rel="stylesheet"
  href="<?= h(app_asset_url('sale/assets/css/dashboard.css')) ?>?v=<?= h(asset_version('sale/assets/css/dashboard.css')) ?>">

<link rel="stylesheet"
  href="<?= h(app_asset_url('sale/assets/css/create_setup.css')) ?>?v=<?= h(asset_version('sale/assets/css/create_setup.css')) ?>">

<?= flash_message() ?>

<div class="admin-dashboard-v2 sales-dashboard-page">
  <div class="admin-dashboard-top">
    <div>
      <h1>สร้างใบงานติดตั้ง</h1>
      <p>เลือกข้อมูลลูกค้า สินค้า และรายละเอียดการติดตั้งก่อนบันทึกใบงาน</p>
    </div>

    <div class="admin-dashboard-actions">
      <div class="admin-date-pill">
        <i class="fa-regular fa-calendar"></i>
        <?= h(date('d/m/Y')) ?>
      </div>
    </div>
  </div>

  <div class="setup-page setup-work-page setup-create-page">
    <?php require __DIR__ . '/partials/create_setup_form.php'; ?>
  </div>
</div>

<?php layout_footer(); ?>