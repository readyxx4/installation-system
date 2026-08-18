<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('2');

function edit_setup_icon(string $name): string
{
    $icons = [
        'back' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5"></path><path d="M12 19l-7-7 7-7"></path></svg>',
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

function edit_setup_can_modify(array $setup): bool
{
    return (string) ($setup['setup_status'] ?? '') === '0'
        && empty($setup['assign_id']);
}

$setupId = trim($_GET['id'] ?? $_POST['setup_id'] ?? '');

if ($setupId === '') {
    redirect_to(app_system_url('sale/setups.php?status=error'));
}

$setupStmt = $conn->prepare("
    SELECT
        s.setup_id,
        s.user_id,
        s.pro_id,
        s.setup_status,
        s.setup_address,
        s.setup_note,
        a.assign_id,
        a.assign_status
    FROM setup s
    LEFT JOIN assignment a
      ON a.setup_id = s.setup_id
     AND a.assign_id = (
        SELECT a2.assign_id
        FROM assignment a2
        WHERE a2.setup_id = s.setup_id
        ORDER BY a2.assign_date DESC, a2.assign_id DESC
        LIMIT 1
     )
    WHERE s.setup_id = ?
    LIMIT 1
");
$setupStmt->bind_param('s', $setupId);
$setupStmt->execute();
$setup = $setupStmt->get_result()->fetch_assoc();

if (!$setup) {
    redirect_to(app_system_url('sale/setups.php?status=notfound'));
}

if (!edit_setup_can_modify($setup)) {
    redirect_to(app_system_url('sale/setups.php?status=assignment_locked'));
}

$customers = [];
$customerResult = $conn->query("
    SELECT user_id, user_name, user_phone, user_email, user_address
    FROM `user`
    WHERE user_role = 0
    ORDER BY user_name ASC, user_id ASC
");

while ($row = $customerResult->fetch_assoc()) {
    $customers[] = $row;
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

while ($row = $productResult->fetch_assoc()) {
    $products[] = $row;
}

$currentItems = [];
$detailStmt = $conn->prepare("
    SELECT pro_id, COALESCE(install_qty, 1) AS qty
    FROM install_detail
    WHERE setup_id = ?
    ORDER BY detail_id ASC
");
$detailStmt->bind_param('s', $setupId);
$detailStmt->execute();
$detailResult = $detailStmt->get_result();

while ($row = $detailResult->fetch_assoc()) {
    $currentItems[] = [
        'pro_id' => $row['pro_id'],
        'qty' => (int) ($row['qty'] ?? 1),
    ];
}

if (count($currentItems) === 0 && !empty($setup['pro_id'])) {
    $currentItems[] = [
        'pro_id' => $setup['pro_id'],
        'qty' => 1,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = trim($_POST['user_id'] ?? '');
    $setupAddress = trim($_POST['setup_address'] ?? '');
    $setupNote = trim($_POST['setup_note'] ?? '');
    $itemsJson = $_POST['selected_items_json'] ?? '[]';

    if ($userId === '' || $setupAddress === '') {
        redirect_to(app_system_url('sale/edit_setup.php?id=' . urlencode($setupId) . '&status=error'));
    }

    $selectedItems = json_decode($itemsJson, true);

    if (!is_array($selectedItems) || count($selectedItems) === 0) {
        redirect_to(app_system_url('sale/edit_setup.php?id=' . urlencode($setupId) . '&status=error'));
    }

    try {
        $freshStmt = $conn->prepare("
            SELECT
                s.setup_id,
                s.setup_status,
                a.assign_id,
                a.assign_status
            FROM setup s
            LEFT JOIN assignment a
              ON a.setup_id = s.setup_id
             AND a.assign_id = (
                SELECT a2.assign_id
                FROM assignment a2
                WHERE a2.setup_id = s.setup_id
                ORDER BY a2.assign_date DESC, a2.assign_id DESC
                LIMIT 1
             )
            WHERE s.setup_id = ?
            LIMIT 1
        ");
        $freshStmt->bind_param('s', $setupId);
        $freshStmt->execute();
        $freshSetup = $freshStmt->get_result()->fetch_assoc();

        if (!$freshSetup || !edit_setup_can_modify($freshSetup)) {
            redirect_to(app_system_url('sale/setups.php?status=assignment_locked'));
        }

        $customerCheck = $conn->prepare("
            SELECT user_id
            FROM `user`
            WHERE user_id = ?
              AND user_role = 0
            LIMIT 1
        ");
        $customerCheck->bind_param('s', $userId);
        $customerCheck->execute();

        if ($customerCheck->get_result()->num_rows !== 1) {
            throw new RuntimeException('ไม่พบข้อมูลลูกค้า');
        }

        $validatedItems = [];
        $seen = [];

        $productCheck = $conn->prepare("
            SELECT pro_id, pro_price_install
            FROM product
            WHERE pro_id = ?
            LIMIT 1
        ");

        foreach ($selectedItems as $item) {
            $proId = trim((string) ($item['pro_id'] ?? ''));
            $qty = (int) ($item['qty'] ?? 0);

            if ($proId === '' || $qty <= 0) {
                continue;
            }

            $productCheck->bind_param('s', $proId);
            $productCheck->execute();
            $productRow = $productCheck->get_result()->fetch_assoc();

            if (!$productRow) {
                throw new RuntimeException('พบสินค้าที่ไม่มีอยู่ในระบบ');
            }

            if (isset($seen[$proId])) {
                $validatedItems[$seen[$proId]]['qty'] += $qty;
                $validatedItems[$seen[$proId]]['total'] =
                    $validatedItems[$seen[$proId]]['qty'] *
                    $validatedItems[$seen[$proId]]['price'];
                continue;
            }

            $price = (float) $productRow['pro_price_install'];
            $validatedItems[] = [
                'pro_id' => $proId,
                'qty' => $qty,
                'price' => $price,
                'total' => $price * $qty,
            ];
            $seen[$proId] = count($validatedItems) - 1;
        }

        if (count($validatedItems) === 0) {
            throw new RuntimeException('กรุณาเลือกสินค้า');
        }

        $firstProductId = $validatedItems[0]['pro_id'];

        $conn->begin_transaction();

        $updateSetup = $conn->prepare("
            UPDATE setup
            SET
                user_id = ?,
                pro_id = ?,
                setup_location = ?,
                setup_address = ?,
                setup_note = ?
            WHERE setup_id = ?
              AND setup_status = 0
        ");
        $updateSetup->bind_param(
            'ssssss',
            $userId,
            $firstProductId,
            $setupAddress,
            $setupAddress,
            $setupNote,
            $setupId
        );
        $updateSetup->execute();

        if ($updateSetup->affected_rows < 0) {
            throw new RuntimeException('ไม่สามารถแก้ไขใบงานได้');
        }

        $deleteDetails = $conn->prepare("DELETE FROM install_detail WHERE setup_id = ?");
        $deleteDetails->bind_param('s', $setupId);
        $deleteDetails->execute();

        $insertDetail = $conn->prepare("
            INSERT INTO install_detail (
                setup_id,
                pro_id,
                install_qty,
                install_price,
                install_total
            )
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($validatedItems as $item) {
            $insertDetail->bind_param(
                'ssidd',
                $setupId,
                $item['pro_id'],
                $item['qty'],
                $item['price'],
                $item['total']
            );
            $insertDetail->execute();
        }

        $conn->commit();

        redirect_to(app_system_url('sale/edit_setup.php?id=' . urlencode($setupId) . '&status=updated'));
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
        }

        redirect_to(app_system_url('sale/edit_setup.php?id=' . urlencode($setupId) . '&status=error'));
    }
}

layout_header('แก้ไขใบงานติดตั้ง', 'setups');
?>

<link
  rel="stylesheet"
  href="<?= h(app_asset_url('sale/assets/css/create_setup.css')) ?>?v=<?= h(asset_version('sale/assets/css/create_setup.css')) ?>"
>

<?= flash_message() ?>

<div class="setup-page setup-work-page setup-edit-page">

  <form
    id="createSetupForm"
    method="POST"
    action="<?= h(app_system_url('sale/edit_setup.php?id=' . urlencode($setupId))) ?>"
    autocomplete="off"
  >
    <input type="hidden" name="setup_id" value="<?= h($setupId) ?>">
    <input type="hidden" name="user_id" id="selectedCustomerId">
    <input type="hidden" name="selected_items_json" id="selectedItemsJson" value="[]">

    <div class="setup-split-layout">
      <section class="setup-main-panel setup-edit-main-panel">
        <a class="setup-frame-back" href="<?= h(app_system_url('sale/setups.php')) ?>" aria-label="กลับไปรายการใบงานติดตั้ง">
          <?= edit_setup_icon('back') ?>
        </a>
        <div class="setup-tabs" role="tablist">
          <button type="button" class="setup-tab active" data-tab="customer">
            <span>1</span>
            เลือกลูกค้า
          </button>
          <button type="button" class="setup-tab" data-tab="products">
            <span>2</span>
            เลือกสินค้า
          </button>
        </div>

        <div class="setup-tab-panel active" id="customerPanel">
          <div class="setup-section-heading">
            <div>
              <h2>แก้ไขลูกค้าในใบงาน</h2>
              <p>เปลี่ยนลูกค้าได้เฉพาะใบงานที่ยังไม่ได้มอบหมาย</p>
            </div>
          </div>

          <div class="setup-search-wrap">
            <?= edit_setup_icon('search') ?>
            <input
              type="search"
              id="customerSearch"
              placeholder="พิมพ์ชื่อ เบอร์โทร หรือรหัสลูกค้า..."
            >
          </div>

          <div class="customer-search-results" id="customerResults"></div>

          <div class="customer-selected-card" id="selectedCustomerCard" hidden>
            <div class="customer-avatar">
              <?= edit_setup_icon('user') ?>
            </div>
            <div class="customer-selected-info">
              <small>ลูกค้าที่เลือก</small>
              <h3 id="selectedCustomerName">-</h3>
              <div class="customer-meta">
                <span id="selectedCustomerCode">-</span>
                <span id="selectedCustomerPhone">-</span>
                <span id="selectedCustomerEmail">-</span>
              </div>
              <p id="selectedCustomerAddress">-</p>
            </div>
            <button type="button" class="change-customer-btn" id="changeCustomerBtn">
              ปรับ
            </button>
          </div>
        </div>

        <div class="setup-tab-panel" id="productsPanel">
          <div class="setup-section-heading product-heading-row">
            <div>
              <h2>แก้ไขสินค้า</h2>
              <p>เปลี่ยนสินค้าและจำนวนได้ก่อนใบงานถูกมอบหมาย</p>
            </div>

            <div class="setup-search-wrap product-search">
              <?= edit_setup_icon('search') ?>
              <input
                type="search"
                id="productSearch"
                placeholder="ค้นหาสินค้า หรือประเภทสินค้า..."
              >
            </div>
          </div>

          <div class="product-grid" id="productGrid"></div>
          <div class="setup-empty-state" id="productEmptyState" hidden>
            ไม่พบสินค้าที่ค้นหา
          </div>
        </div>
      </section>

      <aside class="setup-summary-sidebar">
        <div class="summary-card sticky-summary">
          <div class="summary-title-row">
            <div>
              <h2>สรุปใบงาน</h2>
              <p>ตรวจสอบข้อมูลก่อนบันทึก</p>
            </div>
            <span class="summary-count" id="summaryItemCount">0 รายการ</span>
          </div>

          <div class="summary-customer-mini" id="summaryCustomerMini">
            <?= edit_setup_icon('user') ?>
            <div>
              <small>ลูกค้า</small>
              <strong id="summaryCustomerName">ยังไม่ได้เลือกลูกค้า</strong>
            </div>
          </div>

          <div class="summary-items" id="summaryItems">
            <div class="summary-empty">
              ยังไม่มีสินค้าที่เลือก
            </div>
          </div>

          <div class="summary-total-row">
            <span>รวมค่าติดตั้ง</span>
            <strong id="summaryTotal">0.00 บาท</strong>
          </div>

          <div class="summary-form-section">
            <label class="same-address-check">
              <input type="checkbox" id="sameAddressCheckbox">
              <span>ใช้ที่อยู่เดียวกับลูกค้า</span>
            </label>

            <label for="setupAddress">
              <?= edit_setup_icon('map') ?>
              ที่อยู่สำหรับติดตั้ง
            </label>
            <textarea
              id="setupAddress"
              name="setup_address"
              rows="4"
              placeholder="กรอกบ้านเลขที่ หมู่ ถนน ตำบล อำเภอ จังหวัด และรหัสไปรษณีย์"
              required
            ></textarea>

            <label for="setupNote">
              <?= edit_setup_icon('note') ?>
              หมายเหตุ
            </label>
            <textarea
              id="setupNote"
              name="setup_note"
              rows="3"
              placeholder="รายละเอียดเพิ่มเติมสำหรับงานติดตั้ง (ถ้ามี)"
            ></textarea>
          </div>

          <button type="submit" class="setup-submit-btn" id="submitSetupBtn">
            <?= edit_setup_icon('save') ?>
            บันทึกการแก้ไข
          </button>

          <p class="summary-submit-note">
            แก้ไขได้เฉพาะใบงานที่ยังไม่ได้มอบหมาย
          </p>
        </div>
      </aside>
    </div>
  </form>
</div>

<div class="product-detail-modal" id="productDetailModal" hidden>
  <div class="product-detail-backdrop" data-close-modal></div>
  <div class="product-detail-dialog" role="dialog" aria-modal="true">
    <button type="button" class="product-modal-close" data-close-modal>×</button>
    <div class="product-detail-icon">
      <?= edit_setup_icon('box') ?>
    </div>
    <small id="modalProductType">ประเภทสินค้า</small>
    <h2 id="modalProductName">ชื่อสินค้า</h2>
    <div class="modal-product-code" id="modalProductCode">-</div>
    <div class="modal-price-grid">
      <div>
        <span>ราคาสินค้า</span>
        <strong id="modalProductPrice">0.00 บาท</strong>
      </div>
      <div>
        <span>ค่าติดตั้ง</span>
        <strong id="modalInstallPrice">0.00 บาท</strong>
      </div>
    </div>
  </div>
</div>

<script>
window.createSetupData = {
  customers: <?= json_encode($customers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  products: <?= json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
};
window.createSetupInitial = {
  customerId: <?= json_encode($setup['user_id'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  setupAddress: <?= json_encode((string) ($setup['setup_address'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  setupNote: <?= json_encode((string) ($setup['setup_note'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  items: <?= json_encode($currentItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
};
</script>
<script
  src="<?= h(app_asset_url('sale/assets/js/create_setup.js')) ?>?v=<?= h(asset_version('sale/assets/js/create_setup.js')) ?>"
></script>

<?php layout_footer(); ?>
