<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('2');

function table_exists(mysqli $conn, string $table): bool
{
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
}

function column_exists(mysqli $conn, string $table, string $column): bool
{
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
}

function prepare_setup_tables(mysqli $conn): void
{
  if (!table_exists($conn, 'install_detail')) {
    $conn->query("
            CREATE TABLE install_detail (
                detail_id INT AUTO_INCREMENT PRIMARY KEY,
                setup_id CHAR(11) NOT NULL,
                pro_id CHAR(10) NOT NULL,
                install_qty INT NOT NULL DEFAULT 1,
                install_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                install_total DECIMAL(10,2) NOT NULL DEFAULT 0.00
            )
        ");
  }

  if (!column_exists($conn, 'install_detail', 'install_qty')) {
    $conn->query("ALTER TABLE install_detail ADD COLUMN install_qty INT NOT NULL DEFAULT 1");
  }

  if (!column_exists($conn, 'install_detail', 'install_price')) {
    $conn->query("ALTER TABLE install_detail ADD COLUMN install_price DECIMAL(10,2) NOT NULL DEFAULT 0.00");
  }

  if (!column_exists($conn, 'install_detail', 'install_total')) {
    $conn->query("ALTER TABLE install_detail ADD COLUMN install_total DECIMAL(10,2) NOT NULL DEFAULT 0.00");
  }

  if (table_exists($conn, 'setup')) {
    if (!column_exists($conn, 'setup', 'setup_address')) {
      $conn->query("ALTER TABLE setup ADD COLUMN setup_address TEXT NULL");
    }

    if (!column_exists($conn, 'setup', 'setup_note')) {
      $conn->query("ALTER TABLE setup ADD COLUMN setup_note TEXT NULL");
    }

    if (!column_exists($conn, 'setup', 'created_at')) {
      $conn->query("ALTER TABLE setup ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    }
  } else {
    $conn->query("
            CREATE TABLE setup (
                setup_id CHAR(11) PRIMARY KEY,
                user_id CHAR(13) NOT NULL,
                pro_id CHAR(10) NOT NULL,
                setup_date DATE NOT NULL,
                setup_location TEXT NULL,
                setup_status INT(1) NOT NULL DEFAULT 0,
                setup_address TEXT NULL,
                setup_note TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
  }
}

function make_setup_id(mysqli $conn): string
{
  $prefix = 'SET-';

  for ($i = 1; $i <= 9999999; $i++) {
    $running_no = str_pad((string) $i, 7, '0', STR_PAD_LEFT);
    $setup_id = $prefix . $running_no;

    $stmt = $conn->prepare("
            SELECT setup_id
            FROM setup
            WHERE setup_id = ?
            LIMIT 1
        ");
    $stmt->bind_param('s', $setup_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
      return $setup_id;
    }
  }

  throw new Exception('ไม่สามารถสร้างรหัสงานติดตั้งใหม่ได้');
}

function icon_svg(string $name): string
{
  $icons = [
    'search' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20L16.65 16.65"></path></svg>',
    'user' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>',
    'box' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M21 8l-9-5-9 5 9 5 9-5z"></path><path d="M3 8v8l9 5 9-5V8"></path><path d="M12 13v8"></path></svg>',
    'calendar' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4"></path><path d="M8 2v4"></path><path d="M3 10h18"></path></svg>',
    'plus' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>',
    'minus' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14"></path></svg>',
    'trash' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>',
    'check' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 6L9 17l-5-5"></path></svg>',
    'arrow' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14"></path><path d="M13 5l7 7-7 7"></path></svg>',
    'file' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h5"></path></svg>',
    'map' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 21s7-4.4 7-11a7 7 0 1 0-14 0c0 6.6 7 11 7 11z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>',
    'back' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 12H5"></path><path d="M12 5l-7 7 7 7"></path></svg>',
    ];

  return $icons[$name] ?? '';
}

prepare_setup_tables($conn);

$customers = [];
$customers_result = $conn->query("
    SELECT user_id, user_name, user_fullname, user_phone, user_email, user_address
    FROM `user`
    WHERE user_role = 0
    ORDER BY user_id DESC
");

while ($row = $customers_result->fetch_assoc()) {
  $customers[] = $row;
}

$product_types = [];
$product_types_result = $conn->query("
    SELECT protype_id, protype_name
    FROM product_type
    ORDER BY protype_id DESC
");

while ($row = $product_types_result->fetch_assoc()) {
  $product_types[] = $row;
}

$products = [];
$products_result = $conn->query("
    SELECT 
        p.pro_id,
        p.pro_name,
        p.pro_price,
        p.pro_price_install,
        p.protype_id,
        pt.protype_name
    FROM product p
    LEFT JOIN product_type pt ON p.protype_id = pt.protype_id
    ORDER BY p.pro_id DESC
");

while ($row = $products_result->fetch_assoc()) {
  $products[] = $row;
}

$latest_customer_id = '';
$latest_customer_result = $conn->query("
    SELECT user_id
    FROM `user`
    WHERE user_role = 0
    ORDER BY user_id DESC
    LIMIT 1
");
if ($latest_customer_result && $latest_customer_result->num_rows > 0) {
  $latest_customer_row = $latest_customer_result->fetch_assoc();
  $latest_customer_id = $latest_customer_row['user_id'] ?? '';
}

$latest_product_type_id = '';
$latest_product_type_result = $conn->query("
    SELECT protype_id
    FROM product_type
    ORDER BY protype_id DESC
    LIMIT 1
");
if ($latest_product_type_result && $latest_product_type_result->num_rows > 0) {
  $latest_product_type_row = $latest_product_type_result->fetch_assoc();
  $latest_product_type_id = $latest_product_type_row['protype_id'] ?? '';
}

$latest_product_id = '';
$latest_product_result = $conn->query("
    SELECT pro_id
    FROM product
    ORDER BY pro_id DESC
    LIMIT 1
");
if ($latest_product_result && $latest_product_result->num_rows > 0) {
  $latest_product_row = $latest_product_result->fetch_assoc();
  $latest_product_id = $latest_product_row['pro_id'] ?? '';
}

function setup_status_name($status): string
{
  return match ((string) $status) {
    '0' => 'สร้างใบงานแล้ว',
    '1' => 'มอบหมายงานแล้ว',
    '2' => 'ช่างรับงานแล้ว',
    '3' => 'กำลังติดตั้ง',
    '4' => 'ติดตั้งเสร็จสิ้น',
    default => 'ไม่ทราบสถานะ',
  };
}

function setup_status_badge($status): string
{
  return match ((string) $status) {
    '0' => 'orange',
    '1' => 'blue',
    '2' => 'green',
    '3' => 'purple',
    '4' => 'green',
    default => 'orange',
  };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $setup_id = trim($_POST['setup_id'] ?? '');
  $user_id = trim($_POST['user_id'] ?? '');
  // ไม่แสดงช่องวันที่ในหน้าแบบฟอร์ม ให้ระบบบันทึกวันที่ปัจจุบันอัตโนมัติ
  $setup_date = date('Y-m-d');
  $setup_address = trim($_POST['setup_address'] ?? '');
  $setup_note = trim($_POST['setup_note'] ?? '');

  $product_ids = $_POST['product_ids'] ?? [];
  $product_qtys = $_POST['product_qtys'] ?? [];

  if (
    $setup_id === '' ||
    $user_id === '' ||
    $setup_address === '' ||
    !is_array($product_ids) ||
    !is_array($product_qtys) ||
    count($product_ids) === 0
  ) {
    redirect_to(app_system_url('finance/create_setup.php?status=error'));
  }

  if (!preg_match('/^SET-[0-9]{7}$/', $setup_id)) {
    redirect_to(app_system_url('finance/create_setup.php?status=error'));
  }

  try {
    $check_customer = $conn->prepare("
            SELECT user_id
            FROM `user`
            WHERE user_id = ?
              AND user_role = 0
            LIMIT 1
        ");
    $check_customer->bind_param('s', $user_id);
    $check_customer->execute();

    if ($check_customer->get_result()->num_rows !== 1) {
      redirect_to(app_system_url('finance/create_setup.php?status=error'));
    }

    $clean_items = [];
    $seen = [];

    foreach ($product_ids as $index => $pro_id_raw) {
      $pro_id = trim((string) $pro_id_raw);
      $qty = (int) ($product_qtys[$index] ?? 0);

      if ($pro_id === '' || $qty <= 0) {
        continue;
      }

      if (isset($seen[$pro_id])) {
        $clean_items[$seen[$pro_id]]['qty'] += $qty;
        continue;
      }

      $clean_items[] = [
        'pro_id' => $pro_id,
        'qty' => $qty,
      ];

      $seen[$pro_id] = count($clean_items) - 1;
    }

    if (count($clean_items) === 0) {
      redirect_to(app_system_url('finance/create_setup.php?status=error'));
    }

    $validated_items = [];

    foreach ($clean_items as $item) {
      $check_product = $conn->prepare("
                SELECT pro_id, pro_price_install
                FROM product
                WHERE pro_id = ?
                LIMIT 1
            ");
      $check_product->bind_param('s', $item['pro_id']);
      $check_product->execute();

      $product_result = $check_product->get_result();

      if ($product_result->num_rows !== 1) {
        redirect_to(app_system_url('finance/create_setup.php?status=error'));
      }

      $product = $product_result->fetch_assoc();
      $price = (float) $product['pro_price_install'];
      $qty = (int) $item['qty'];

      $validated_items[] = [
        'pro_id' => $product['pro_id'],
        'qty' => $qty,
        'price' => $price,
        'total' => $price * $qty,
      ];
    }

    $first_pro_id = $validated_items[0]['pro_id'];
    $setup_status = 0;
    $setup_location = $setup_address;

    $conn->begin_transaction();

    $stmt = $conn->prepare("
            INSERT INTO setup
            (
                setup_id,
                user_id,
                pro_id,
                setup_date,
                setup_location,
                setup_status,
                setup_address,
                setup_note
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

    $stmt->bind_param(
      'sssssiss',
      $setup_id,
      $user_id,
      $first_pro_id,
      $setup_date,
      $setup_location,
      $setup_status,
      $setup_address,
      $setup_note
    );
    $stmt->execute();

    $stmt_detail = $conn->prepare("
            INSERT INTO install_detail
            (
                setup_id,
                pro_id,
                install_qty,
                install_price,
                install_total
            )
            VALUES (?, ?, ?, ?, ?)
        ");

    foreach ($validated_items as $item) {
      $stmt_detail->bind_param(
        'ssidd',
        $setup_id,
        $item['pro_id'],
        $item['qty'],
        $item['price'],
        $item['total']
      );
      $stmt_detail->execute();
    }

    $conn->commit();

    redirect_to(app_system_url('finance/setup_slip.php?id=' . urlencode($setup_id)));
  } catch (Throwable $e) {
    try {
      $conn->rollback();
    } catch (Throwable $rollbackError) {
      // skip rollback error
    }

    redirect_to(app_system_url('finance/create_setup.php?status=error'));
  }
}

$default_setup_id = make_setup_id($conn);

layout_header('สร้างงานติดตั้ง', 'setup');
// page_head('สร้างงานติดตั้ง', 'หน้าหลัก > สร้างงานติดตั้ง');

$customers_json = json_encode($customers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$products_json = json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$product_types_json = json_encode($product_types, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$latest_customer_json = json_encode($latest_customer_id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$latest_product_type_json = json_encode($latest_product_type_id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$latest_product_json = json_encode($latest_product_id, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>

<?= flash_message() ?>

<!-- CSS ของหน้านี้แยกไปไว้ในไฟล์ style.css แล้ว -->

<div class="cs-create-page-head cs-create-page-head-simple">
  <div>
    <h1>สร้างใบงานติดตั้ง</h1>
    <p>เลือกข้อมูลลูกค้าและสินค้า เพื่อออกใบงานติดตั้งใหม่</p>
  </div>
</div>

<form class="create-setup-v2" method="POST" action="<?= h(app_system_url('finance/create_setup.php')) ?>"
  onsubmit="return beforeCreateSetupSubmit()">
  <div id="topTotalText" style="display:none;">0.00 บาท</div>

  <div class="cs-layout">
    <section class="cs-card">
      <div class="cs-card-head">
        <div class="cs-step">1</div>
        <div>
          <h2>เลือกลูกค้า</h2>
          <p>ค้นหาจากรหัส ชื่อ เบอร์โทร อีเมล หรือที่อยู่ แล้วเลือกลูกค้าสำหรับงานนี้</p>
        </div>
      </div>

      <div class="cs-card-body">
        <div class="cs-search-box">
          <?= icon_svg('search') ?>
          <input type="text" id="customerSearch" placeholder="ค้นหาลูกค้า..." autocomplete="off">
        </div>

        <div class="cs-table-wrap cs-choice-table-wrap">
          <table class="cs-data-table cs-customer-table">
            <thead>
              <tr>
                <th>รหัสลูกค้า</th>
                <th>ชื่อลูกค้า</th>
                <th>เบอร์โทร</th>
                <th>อีเมล</th>
                <th>ที่อยู่</th>
                <th style="width:92px;">เลือก</th>
              </tr>
            </thead>
            <tbody id="customerList"></tbody>
          </table>
        </div>

        <div class="cs-selected-customer cs-selected-customer-inline" id="selectedCustomerBox">
          <div class="cs-selected-inline-content">
            <strong>ลูกค้าที่เลือก</strong>
            <span id="selectedCustomerLine">-</span>
          </div>

          <button class="cs-change-btn" type="button" onclick="clearCustomerSelection()">
            <?= icon_svg('user') ?>
            เปลี่ยนลูกค้า
          </button>
        </div>

        <input type="hidden" id="user_id" name="user_id" required>
      </div>
    </section>

    <section class="cs-card">
      <div class="cs-card-head">
        <div class="cs-step">2</div>
        <div>
          <h2>เพิ่มรายการสินค้า</h2>
          <p>ค้นหาและกรองประเภทสินค้า แล้วเพิ่มเข้าในรายการติดตั้งด้านขวา</p>
        </div>
      </div>

      <div class="cs-card-body cs-product-area">
        <div>
          <div class="cs-search-box">
            <?= icon_svg('search') ?>
            <input type="text" id="productSearch" placeholder="ค้นหาสินค้า รหัสสินค้า หรือประเภท..." autocomplete="off">
          </div>

          <div class="cs-type-tabs" id="typeTabs"></div>
          <div class="cs-table-wrap cs-choice-table-wrap">
            <table class="cs-data-table cs-product-table">
              <thead>
                <tr>
                  <th>รหัสสินค้า</th>
                  <th>ชื่อสินค้า</th>
                  <th>ประเภทสินค้า</th>
                  <th>ค่าติดตั้ง</th>
                  <th style="width:92px;">เพิ่ม</th>
                </tr>
              </thead>
              <tbody id="productList"></tbody>
            </table>
          </div>
        </div>

        <aside class="cs-cart-panel cs-cart-panel-table">
          <h3>รายการติดตั้งที่เลือก</h3>
          <div class="cs-table-wrap">
            <table class="cs-data-table cs-cart-table">
              <thead>
                <tr>
                  <th>สินค้า</th>
                  <th style="width:160px;">จำนวน</th>
                  <th style="width:140px;">ราคา/หน่วย</th>
                  <th style="width:140px;">รวม</th>
                  <th style="width:80px;">ลบ</th>
                </tr>
              </thead>
              <tbody id="cartList">
                <tr><td colspan="5" class="cs-empty-cell">ยังไม่มีรายการสินค้า</td></tr>
              </tbody>
            </table>
          </div>

          <div class="cs-cart-summary">
            <span>รวมทั้งหมด</span>
            <b id="cartTotalText">0.00 บาท</b>
          </div>
        </aside>
      </div>
    </section>
  </div>

  <section class="cs-card cs-install-details-card" id="installDetailsCard" hidden>
    <div class="cs-card-head">
      <div class="cs-step">3</div>
      <div>
        <h2>ข้อมูลการติดตั้ง</h2>
        <p>ระบบจะดึงที่อยู่จากลูกค้าให้อัตโนมัติ แต่สามารถแก้ไขเป็นที่อยู่ติดตั้งจริงได้</p>
      </div>
    </div>

    <div class="cs-card-body">
      <div class="cs-form-grid cs-install-form-grid">
        <div class="cs-form-field">
          <label for="setup_id">รหัสใบงานติดตั้ง</label>
          <input class="cs-plain-input" type="text" id="setup_id" name="setup_id" value="<?= h($default_setup_id) ?>" readonly required>
        </div>

        <div class="cs-form-field cs-field-wide">
          <label for="setup_address">ที่อยู่สำหรับติดตั้ง</label>
          <textarea id="setup_address" name="setup_address" placeholder="กรอกที่อยู่สำหรับติดตั้ง" required></textarea>
        </div>

        <div class="cs-form-field cs-field-wide">
          <label for="setup_note">หมายเหตุ</label>
          <textarea id="setup_note" name="setup_note"
            placeholder="เช่น ช่วงเวลาที่สะดวก เบอร์ติดต่อเพิ่มเติม หรือรายละเอียดหน้างาน"></textarea>
        </div>
      </div>

      <div class="cs-hidden-inputs" id="selectedProductInputs"></div>

      <div class="cs-actions">
        <a class="cs-back-btn" href="<?= h(app_system_url('finance/index.php')) ?>">
          <?= icon_svg('back') ?>
          กลับหน้าหลัก
        </a>

        <button class="cs-save-btn" type="submit">
          <?= icon_svg('check') ?>
          บันทึกงานติดตั้ง
        </button>
      </div>
    </div>
  </section>
</form>

<div class="cs-modal-backdrop" id="customerPreviewModal" aria-hidden="true">
  <div class="cs-preview-modal-card" role="dialog" aria-modal="true" aria-labelledby="customerPreviewTitle">
    <button type="button" class="cs-preview-close" onclick="closeCustomerPreview()" aria-label="ปิด">×</button>
    <div class="cs-preview-head">
      <div class="cs-preview-icon"><?= icon_svg('user') ?></div>
      <div>
        <h3 id="customerPreviewTitle">รายละเอียดลูกค้า</h3>
        <p>ตรวจสอบข้อมูลลูกค้าก่อนเลือกสำหรับใบงานนี้</p>
      </div>
    </div>
    <div class="cs-preview-grid">
      <div class="cs-preview-item"><span>รหัสลูกค้า</span><strong id="previewCustomerId">-</strong></div>
      <div class="cs-preview-item"><span>ชื่อผู้ใช้</span><strong id="previewCustomerName">-</strong></div>
      <div class="cs-preview-item"><span>ชื่อ-นามสกุลจริง</span><strong id="previewCustomerFullname">-</strong></div>
      <div class="cs-preview-item"><span>เบอร์โทรศัพท์</span><strong id="previewCustomerPhone">-</strong></div>
      <div class="cs-preview-item"><span>อีเมล</span><strong id="previewCustomerEmail">-</strong></div>
      <div class="cs-preview-item cs-preview-full"><span>ที่อยู่</span><strong id="previewCustomerAddress">-</strong></div>
    </div>
    <div class="cs-preview-actions">
      <button type="button" class="cs-preview-cancel" onclick="closeCustomerPreview()">ยกเลิก</button>
      <button type="button" class="cs-preview-confirm" onclick="confirmCustomerSelection()"><?= icon_svg('check') ?> เลือกลูกค้านี้</button>
    </div>
  </div>
</div>

<div class="cs-modal-backdrop" id="productPreviewModal" aria-hidden="true">
  <div class="cs-preview-modal-card" role="dialog" aria-modal="true" aria-labelledby="productPreviewTitle">
    <button type="button" class="cs-preview-close" onclick="closeProductPreview()" aria-label="ปิด">×</button>
    <div class="cs-preview-head">
      <div class="cs-preview-icon orange"><?= icon_svg('box') ?></div>
      <div>
        <h3 id="productPreviewTitle">รายละเอียดสินค้า</h3>
        <p>ตรวจสอบข้อมูลสินค้าก่อนเพิ่มเข้ารายการติดตั้ง</p>
      </div>
    </div>
    <div class="cs-preview-grid">
      <div class="cs-preview-item"><span>รหัสสินค้า</span><strong id="previewProductId">-</strong></div>
      <div class="cs-preview-item"><span>ชื่อสินค้า</span><strong id="previewProductName">-</strong></div>
      <div class="cs-preview-item"><span>ประเภทสินค้า</span><strong id="previewProductType">-</strong></div>
      <div class="cs-preview-item"><span>ราคาสินค้า</span><strong id="previewProductPrice">-</strong></div>
      <div class="cs-preview-item"><span>ค่าติดตั้ง</span><strong id="previewProductInstallPrice">-</strong></div>
      <div class="cs-preview-item"><span>จำนวนที่จะเพิ่ม</span><strong>1 รายการ</strong></div>
    </div>
    <div class="cs-preview-actions">
      <button type="button" class="cs-preview-cancel" onclick="closeProductPreview()">ยกเลิก</button>
      <button type="button" class="cs-preview-confirm orange" onclick="confirmProductSelection()"><?= icon_svg('plus') ?> เพิ่มสินค้านี้</button>
    </div>
  </div>
</div>

<script>
  const customers = <?= $customers_json ?: '[]' ?>;
  const products = <?= $products_json ?: '[]' ?>;
  const productTypes = <?= $product_types_json ?: '[]' ?>;

  const latestCustomerId = <?= $latest_customer_json ?: "''" ?>;
  const latestProductTypeId = <?= $latest_product_type_json ?: "''" ?>;
  const latestProductId = <?= $latest_product_json ?: "''" ?>;

  let selectedCustomerId = '';
  let selectedTypeId = 'all';
  let cartItems = [];
  let previewCustomerId = '';
  let previewProductId = '';

  const dismissedNewStorageKey = 'installation_system_create_setup_seen_new_items_v1';

  function loadDismissedNewItems() {
    try {
      const saved = localStorage.getItem(dismissedNewStorageKey);
      const values = saved ? JSON.parse(saved) : [];
      return new Set(Array.isArray(values) ? values : []);
    } catch (error) {
      return new Set();
    }
  }

  const dismissedNewItems = loadDismissedNewItems();

  function saveDismissedNewItems() {
    try {
      localStorage.setItem(dismissedNewStorageKey, JSON.stringify([...dismissedNewItems]));
    } catch (error) {
      // ไม่ต้องทำอะไร ถ้า browser ปิด localStorage
    }
  }

  function dismissNewItem(type, id) {
    if (!type || !id) {
      return;
    }

    dismissedNewItems.add(newKey(type, id));
    saveDismissedNewItems();
  }

  const moneyFormatter = new Intl.NumberFormat('th-TH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });

  function normalizeText(value) {
    return String(value || '').toLowerCase().trim();
  }

  function formatMoney(value) {
    return moneyFormatter.format(Number(value || 0)) + ' บาท';
  }

  function newKey(type, id) {
    return `${type}:${id}`;
  }

  function isNewItem(type, id, latestId) {
    return id && latestId && id === latestId && !dismissedNewItems.has(newKey(type, id));
  }

  function renderCustomers() {
    const keyword = normalizeText(document.getElementById('customerSearch').value);
    const list = document.getElementById('customerList');

    const filtered = customers.filter(customer => {
      const text = normalizeText([
        customer.user_id,
        customer.user_name,
        customer.user_fullname,
        customer.user_phone,
        customer.user_email,
        customer.user_address
      ].join(' '));

      return text.includes(keyword);
    }).sort((a, b) => {
      if (a.user_id === latestCustomerId && b.user_id !== latestCustomerId) return -1;
      if (b.user_id === latestCustomerId && a.user_id !== latestCustomerId) return 1;
      return String(b.user_id || '').localeCompare(String(a.user_id || ''), 'th');
    }).slice(0, 30);

    if (filtered.length === 0) {
      list.innerHTML = '<tr><td colspan="6" class="cs-empty-cell">ไม่พบข้อมูลลูกค้า</td></tr>';
      return;
    }

    list.innerHTML = filtered.map(customer => {
      const fullName = customer.user_fullname || '-';
      const address = customer.user_address || '-';
      const active = selectedCustomerId === customer.user_id ? ' active' : '';
      const isNew = isNewItem('customer', customer.user_id, latestCustomerId) ? ' is-new-item' : '';

      return `
        <tr class="cs-select-row${active}${isNew}" data-new-type="customer" data-new-id="${escapeHtml(customer.user_id)}" onclick="openCustomerPreview('${escapeJs(customer.user_id)}')">
          <td><strong>${escapeHtml(customer.user_id)}</strong></td>
          <td><strong>${escapeHtml(customer.user_name || '-')}</strong><small>${escapeHtml(fullName)}</small></td>
          <td>${escapeHtml(customer.user_phone || '-')}</td>
          <td>${escapeHtml(customer.user_email || '-')}</td>
          <td>${escapeHtml(address)}</td>
          <td><button type="button" class="cs-row-select-btn" onclick="event.stopPropagation(); openCustomerPreview('${escapeJs(customer.user_id)}')">เลือก</button></td>
        </tr>
      `;
    }).join('');
  }

  function openCustomerPreview(userId) {
    const customer = customers.find(item => item.user_id === userId);

    if (!customer) {
      return;
    }

    previewCustomerId = customer.user_id;
    document.getElementById('previewCustomerId').textContent = customer.user_id || '-';
    document.getElementById('previewCustomerName').textContent = customer.user_name || '-';
    document.getElementById('previewCustomerFullname').textContent = customer.user_fullname || '-';
    document.getElementById('previewCustomerPhone').textContent = customer.user_phone || '-';
    document.getElementById('previewCustomerEmail').textContent = customer.user_email || '-';
    document.getElementById('previewCustomerAddress').textContent = customer.user_address || '-';

    document.getElementById('customerPreviewModal').classList.add('show');
    document.getElementById('customerPreviewModal').setAttribute('aria-hidden', 'false');
  }

  function closeCustomerPreview() {
    previewCustomerId = '';
    document.getElementById('customerPreviewModal').classList.remove('show');
    document.getElementById('customerPreviewModal').setAttribute('aria-hidden', 'true');
  }

  function confirmCustomerSelection() {
    if (!previewCustomerId) {
      return;
    }

    applyCustomerSelection(previewCustomerId);
    closeCustomerPreview();

    const productSection = document.getElementById('productSearch');
    if (productSection) {
      productSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
      setTimeout(() => productSection.focus(), 350);
    }
  }

  function applyCustomerSelection(userId) {
    const customer = customers.find(item => item.user_id === userId);

    if (!customer) {
      return;
    }

    dismissNewItem('customer', userId);
    selectedCustomerId = customer.user_id;

    document.getElementById('user_id').value = customer.user_id;
    document.getElementById('setup_address').value = customer.user_address || '';

    const displayName = customer.user_fullname || customer.user_name || '-';
    const selectedLine = [
      displayName,
      customer.user_phone ? `เบอร์โทร: ${customer.user_phone}` : '',
      customer.user_email ? `อีเมล: ${customer.user_email}` : '',
      customer.user_address ? `ที่อยู่: ${customer.user_address}` : ''
    ].filter(Boolean).join(' | ');

    document.getElementById('selectedCustomerLine').textContent = selectedLine || '-';
    document.getElementById('selectedCustomerBox').classList.add('show');
    document.querySelector('.create-setup-v2').classList.add('customer-collapsed');

    renderCustomers();
    updateInstallDetailsVisibility();
  }

  function clearCustomerSelection() {
    selectedCustomerId = '';
    document.getElementById('user_id').value = '';
    document.getElementById('setup_address').value = '';
    document.getElementById('selectedCustomerBox').classList.remove('show');
    document.getElementById('selectedCustomerLine').textContent = '-';
    document.querySelector('.create-setup-v2').classList.remove('customer-collapsed');
    document.getElementById('customerSearch').focus();
    renderCustomers();
    updateInstallDetailsVisibility();
  }

  function renderTypeTabs() {
    const tabs = document.getElementById('typeTabs');

    const typeButtons = [
      { protype_id: 'all', protype_name: 'ทั้งหมด' },
      ...productTypes
    ];

    tabs.innerHTML = typeButtons.map(type => {
      const active = selectedTypeId === type.protype_id ? ' active' : '';
      const isNew = type.protype_id !== 'all' && isNewItem('type', type.protype_id, latestProductTypeId) ? ' is-new-item' : '';

      return `
        <button type="button" class="cs-type-tab${active}${isNew}" data-new-type="type" data-new-id="${escapeHtml(type.protype_id)}" onclick="selectProductType('${escapeJs(type.protype_id)}')">
          ${escapeHtml(type.protype_name || '-')}
        </button>
      `;
    }).join('');
  }

  function selectProductType(typeId) {
    dismissNewItem('type', typeId);
    selectedTypeId = typeId;
    renderTypeTabs();
    renderProducts();
  }

  function renderProducts() {
    const keyword = normalizeText(document.getElementById('productSearch').value);
    const list = document.getElementById('productList');

    const filtered = products.filter(product => {
      const passType = selectedTypeId === 'all' || product.protype_id === selectedTypeId;
      const text = normalizeText([
        product.pro_id,
        product.pro_name,
        product.protype_name,
        product.pro_price_install
      ].join(' '));

      return passType && text.includes(keyword);
    }).sort((a, b) => {
      if (a.pro_id === latestProductId && b.pro_id !== latestProductId) return -1;
      if (b.pro_id === latestProductId && a.pro_id !== latestProductId) return 1;
      return String(b.pro_id || '').localeCompare(String(a.pro_id || ''), 'th');
    }).slice(0, 80);

    if (filtered.length === 0) {
      list.innerHTML = '<tr><td colspan="5" class="cs-empty-cell">ไม่พบข้อมูลสินค้า</td></tr>';
      return;
    }

    list.innerHTML = filtered.map(product => {
      const isNew = isNewItem('product', product.pro_id, latestProductId) ? ' is-new-item' : '';

      return `
        <tr class="cs-select-row${isNew}" data-new-type="product" data-new-id="${escapeHtml(product.pro_id)}" onclick="openProductPreview('${escapeJs(product.pro_id)}')">
          <td><strong>${escapeHtml(product.pro_id)}</strong></td>
          <td><strong>${escapeHtml(product.pro_name || '-')}</strong></td>
          <td>${escapeHtml(product.protype_name || '-')}</td>
          <td><b>${formatMoney(product.pro_price_install)}</b></td>
          <td>
            <button type="button" class="cs-row-add-btn" onclick="event.stopPropagation(); openProductPreview('${escapeJs(product.pro_id)}')">
              ${getIcon('plus')} เพิ่ม
            </button>
          </td>
        </tr>
      `;
    }).join('');
  }

  function openProductPreview(proId) {
    const product = products.find(item => item.pro_id === proId);

    if (!product) {
      return;
    }

    previewProductId = product.pro_id;
    document.getElementById('previewProductId').textContent = product.pro_id || '-';
    document.getElementById('previewProductName').textContent = product.pro_name || '-';
    document.getElementById('previewProductType').textContent = product.protype_name || '-';
    document.getElementById('previewProductPrice').textContent = formatMoney(product.pro_price || 0);
    document.getElementById('previewProductInstallPrice').textContent = formatMoney(product.pro_price_install || 0);

    document.getElementById('productPreviewModal').classList.add('show');
    document.getElementById('productPreviewModal').setAttribute('aria-hidden', 'false');
  }

  function closeProductPreview() {
    previewProductId = '';
    document.getElementById('productPreviewModal').classList.remove('show');
    document.getElementById('productPreviewModal').setAttribute('aria-hidden', 'true');
  }

  function confirmProductSelection() {
    if (!previewProductId) {
      return;
    }

    addProductToCart(previewProductId);
    closeProductPreview();

    const cartPanel = document.querySelector('.cs-cart-panel');
    if (cartPanel) {
      cartPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }

  function addProductToCart(proId) {
    const product = products.find(item => item.pro_id === proId);

    if (!product) {
      return;
    }

    dismissNewItem('product', proId);

    const existing = cartItems.find(item => item.pro_id === proId);

    if (existing) {
      existing.qty += 1;
    } else {
      cartItems.push({
        pro_id: product.pro_id,
        pro_name: product.pro_name,
        protype_name: product.protype_name || '-',
        price: Number(product.pro_price_install || 0),
        qty: 1
      });
    }

    renderProducts();
    renderCart();
  }

  function changeQty(proId, diff) {
    const item = cartItems.find(row => row.pro_id === proId);

    if (!item) {
      return;
    }

    item.qty += diff;

    if (item.qty <= 0) {
      cartItems = cartItems.filter(row => row.pro_id !== proId);
    }

    renderCart();
  }

  function setQty(proId, value) {
    const item = cartItems.find(row => row.pro_id === proId);

    if (!item) {
      return;
    }

    const qty = parseInt(value || '1', 10);
    item.qty = qty > 0 ? qty : 1;

    renderCart();
  }

  function removeProduct(proId) {
    cartItems = cartItems.filter(item => item.pro_id !== proId);
    renderCart();
  }

  function renderCart() {
    const cartList = document.getElementById('cartList');
    const inputBox = document.getElementById('selectedProductInputs');

    if (cartItems.length === 0) {
      cartList.innerHTML = '<tr><td colspan="5" class="cs-empty-cell">ยังไม่มีรายการสินค้า</td></tr>';
      inputBox.innerHTML = '';
      updateTotal();
      updateInstallDetailsVisibility();
      return;
    }

    cartList.innerHTML = cartItems.map(item => {
      const total = item.price * item.qty;

      return `
        <tr>
          <td><strong>${escapeHtml(item.pro_name)}</strong><small>${escapeHtml(item.pro_id)} | ${escapeHtml(item.protype_name)}</small></td>
          <td>
            <div class="cs-qty-row compact">
              <button type="button" class="cs-qty-btn" onclick="changeQty('${escapeJs(item.pro_id)}', -1)">${getIcon('minus')}</button>
              <input class="cs-qty-input" type="number" min="1" value="${item.qty}" oninput="setQty('${escapeJs(item.pro_id)}', this.value)">
              <button type="button" class="cs-qty-btn" onclick="changeQty('${escapeJs(item.pro_id)}', 1)">${getIcon('plus')}</button>
            </div>
          </td>
          <td>${formatMoney(item.price)}</td>
          <td><b>${formatMoney(total)}</b></td>
          <td><button type="button" class="cs-remove-btn" onclick="removeProduct('${escapeJs(item.pro_id)}')">${getIcon('trash')}</button></td>
        </tr>
      `;
    }).join('');

    inputBox.innerHTML = cartItems.map(item => `
      <input type="hidden" name="product_ids[]" value="${escapeHtml(item.pro_id)}">
      <input type="hidden" name="product_qtys[]" value="${item.qty}">
    `).join('');

    updateTotal();
    updateInstallDetailsVisibility();
  }

  function updateTotal() {
    const total = cartItems.reduce((sum, item) => sum + (item.price * item.qty), 0);

    const cartTotalText = document.getElementById('cartTotalText');
    const topTotalText = document.getElementById('topTotalText');
    if (cartTotalText) cartTotalText.textContent = formatMoney(total);
    if (topTotalText) topTotalText.textContent = formatMoney(total);
  }

  function updateInstallDetailsVisibility() {
    const installDetailsCard = document.getElementById('installDetailsCard');

    if (!installDetailsCard) {
      return;
    }

    const readyToCreate = Boolean(selectedCustomerId) && cartItems.length > 0;
    installDetailsCard.hidden = !readyToCreate;
    installDetailsCard.classList.toggle('is-ready', readyToCreate);
  }

  function showCreateSetupAlert(message) {
    if (typeof window.showPrettyAlert === 'function') {
      window.showPrettyAlert('warning', 'กรุณาตรวจสอบข้อมูล', message, 'ตกลง');
      return;
    }

    alert(message);
  }

  function beforeCreateSetupSubmit() {
    if (!document.getElementById('user_id').value) {
      showCreateSetupAlert('กรุณาเลือกลูกค้าก่อนบันทึกงานติดตั้ง');
      return false;
    }

    if (cartItems.length === 0) {
      showCreateSetupAlert('กรุณาเพิ่มรายการสินค้าอย่างน้อย 1 รายการ');
      return false;
    }

    if (!document.getElementById('setup_address').value.trim()) {
      showCreateSetupAlert('กรุณากรอกที่อยู่สำหรับติดตั้ง');
      return false;
    }

    return true;
  }

  function getIcon(name) {
    const icons = {
      plus: '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>',
      minus: '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14"></path></svg>',
      trash: '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path></svg>'
    };

    return icons[name] || '';
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function escapeJs(value) {
    return String(value ?? '').replaceAll('\\', '\\\\').replaceAll("'", "\\'");
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeCustomerPreview();
      closeProductPreview();
    }
  });

  document.querySelectorAll('.cs-modal-backdrop').forEach(function (modal) {
    modal.addEventListener('click', function (event) {
      if (event.target === modal) {
        closeCustomerPreview();
        closeProductPreview();
      }
    });
  });

  document.addEventListener('click', function (event) {
    const newItem = event.target.closest('[data-new-type][data-new-id].is-new-item');

    if (!newItem) {
      return;
    }

    dismissNewItem(newItem.dataset.newType, newItem.dataset.newId);
    newItem.classList.remove('is-new-item');
  });

document.getElementById('customerSearch').addEventListener('input', renderCustomers);
  document.getElementById('productSearch').addEventListener('input', renderProducts);

  renderCustomers();
  renderTypeTabs();
  renderProducts();
  renderCart();
</script>

<?php
layout_footer();