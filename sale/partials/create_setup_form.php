<form
  id="createSetupForm"
  method="POST"
  action="<?= h(app_system_url('sale/create_setup.php')) ?>"
  autocomplete="off"
>
  <input type="hidden" name="setup_id" value="<?= h($setupId) ?>">
  <input type="hidden" name="user_id" id="selectedCustomerId">
  <input type="hidden" name="selected_items_json" id="selectedItemsJson" value="[]">

  <div class="setup-split-layout">
    <section class="setup-main-panel">
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
            <h2>ค้นหาและเลือกลูกค้า</h2>
            <p>ค้นหาจากชื่อ รหัสผู้ใช้ เบอร์โทรศัพท์ หรืออีเมล</p>
          </div>
        </div>

        <div class="setup-search-wrap">
          <?= create_setup_icon('search') ?>
          <input
            type="search"
            id="customerSearch"
            placeholder="พิมพ์ชื่อ เบอร์โทร หรือรหัสลูกค้า..."
          >
        </div>

        <div class="customer-search-results" id="customerResults"></div>
      </div>

      <div class="setup-tab-panel" id="productsPanel">
        <div class="setup-section-heading product-heading-row">
          <div>
            <h2>เลือกสินค้า</h2>
            <p>เพิ่มหรือลดจำนวนสินค้าได้ทันทีจากรายการ</p>
          </div>

          <div class="setup-search-wrap product-search">
            <?= create_setup_icon('search') ?>
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

        <div class="summary-customer-card" id="summaryCustomer">
          <div class="summary-customer-head">
            <div class="summary-customer-icon">
              <?= create_setup_icon('user') ?>
            </div>

            <div class="summary-customer-name">
              <small>ลูกค้า</small>
              <strong id="summaryCustomerName">ยังไม่ได้เลือกลูกค้า</strong>
            </div>
          </div>

          <div
            class="summary-customer-details"
            id="summaryCustomerDetails"
            hidden
          >
            <div class="summary-customer-row">
              <span>รหัสลูกค้า</span>
              <strong id="summaryCustomerCode">-</strong>
            </div>

            <div class="summary-customer-row">
              <span>เบอร์โทร</span>
              <strong id="summaryCustomerPhone">-</strong>
            </div>

            <div class="summary-customer-row">
              <span>อีเมล</span>
              <strong id="summaryCustomerEmail">-</strong>
            </div>

            <div class="summary-customer-row summary-customer-address">
              <span>ที่อยู่ลูกค้า</span>
              <strong id="summaryCustomerAddress">-</strong>
            </div>
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
            <?= create_setup_icon('map') ?>
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
            <?= create_setup_icon('note') ?>
            หมายเหตุ
          </label>

          <textarea
            id="setupNote"
            name="setup_note"
            rows="3"
            placeholder="รายละเอียดเพิ่มเติมสำหรับงานติดตั้ง (ถ้ามี)"
          ></textarea>
        </div>

        <button
          type="submit"
          class="setup-submit-btn"
          id="submitSetupBtn"
          disabled
        >
          <?= create_setup_icon('save') ?>
          บันทึกงานติดตั้ง
        </button>

        <p class="summary-submit-note" id="summarySubmitNote">
          กรุณาเลือกลูกค้า สินค้า และกรอกที่อยู่ให้ครบ
        </p>
      </div>
    </aside>
  </div>
</form>

<div class="product-detail-modal" id="productDetailModal" hidden>
  <div class="product-detail-backdrop" data-close-modal></div>

  <div
    class="product-detail-dialog"
    role="dialog"
    aria-modal="true"
    aria-labelledby="modalProductName"
  >
    <button
      type="button"
      class="product-modal-close"
      data-close-modal
      aria-label="ปิดรายละเอียดสินค้า"
    >
      ×
    </button>

    <div class="product-detail-icon">
      <?= create_setup_icon('box') ?>
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
</script>

<script
  src="<?= h(app_asset_url('sale/assets/js/create_setup.js')) ?>?v=<?= h(asset_version('sale/assets/js/create_setup.js')) ?>"
></script>