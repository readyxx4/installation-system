(() => {
  'use strict';

  const data = window.createSetupData || {};
  const initial = window.createSetupInitial || {};

  const customers = Array.isArray(data.customers) ? data.customers : [];
  const products = Array.isArray(data.products) ? data.products : [];

  const state = {
    selectedCustomer: null,
    quantities: new Map(),
  };

  const $ = (selector) => document.querySelector(selector);
  const $$ = (selector) => Array.from(document.querySelectorAll(selector));

  const form = $('#createSetupForm');
  if (!form) return;

  const customerSearch = $('#customerSearch');
  const customerResults = $('#customerResults');

  const productSearch = $('#productSearch');
  const productGrid = $('#productGrid');
  const productEmptyState = $('#productEmptyState');

  const selectedCustomerId = $('#selectedCustomerId');
  const selectedItemsJson = $('#selectedItemsJson');

  const summaryCustomerName = $('#summaryCustomerName');
  const summaryCustomerDetails = $('#summaryCustomerDetails');
  const summaryCustomerCode = $('#summaryCustomerCode');
  const summaryCustomerPhone = $('#summaryCustomerPhone');
  const summaryCustomerEmail = $('#summaryCustomerEmail');
  const summaryCustomerAddress = $('#summaryCustomerAddress');

  const summaryItems = $('#summaryItems');
  const summaryItemCount = $('#summaryItemCount');
  const summaryTotal = $('#summaryTotal');

  const sameAddressCheckbox = $('#sameAddressCheckbox');
  const setupAddress = $('#setupAddress');
  const setupNote = $('#setupNote');

  const submitSetupBtn = $('#submitSetupBtn');
  const summarySubmitNote = $('#summarySubmitNote');

  const productDetailModal = $('#productDetailModal');
  const modalProductType = $('#modalProductType');
  const modalProductName = $('#modalProductName');
  const modalProductCode = $('#modalProductCode');
  const modalProductPrice = $('#modalProductPrice');
  const modalInstallPrice = $('#modalInstallPrice');

  const money = (value) =>
    `${Number(value || 0).toLocaleString('th-TH', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })} บาท`;

  const escapeHtml = (value) =>
    String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');

  const userIcon = `
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M20 21a8 8 0 0 0-16 0"></path>
      <circle cx="12" cy="7" r="4"></circle>
    </svg>
  `;

  const boxIcon = `
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M21 8l-9-5-9 5 9 5 9-5z"></path>
      <path d="M3 8v8l9 5 9-5V8"></path>
      <path d="M12 13v8"></path>
    </svg>
  `;

  const eyeIcon = `
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"></path>
      <circle cx="12" cy="12" r="3"></circle>
    </svg>
  `;

  const minusIcon = `
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M5 12h14"></path>
    </svg>
  `;

  const plusIcon = `
    <svg viewBox="0 0 24 24" aria-hidden="true">
      <path d="M12 5v14"></path>
      <path d="M5 12h14"></path>
    </svg>
  `;

  function switchTab(tabName) {
    $$('.setup-tab').forEach((button) => {
      button.classList.toggle(
        'active',
        button.dataset.tab === tabName
      );
    });

    $('#customerPanel')?.classList.toggle(
      'active',
      tabName === 'customer'
    );

    $('#productsPanel')?.classList.toggle(
      'active',
      tabName === 'products'
    );
  }

  function renderCustomers() {
    const keyword = (customerSearch?.value || '')
      .trim()
      .toLowerCase();

    const filtered = customers.filter((customer) => {
      const haystack = [
        customer.user_name,
        customer.user_id,
        customer.user_phone,
        customer.user_email,
        customer.user_address,
      ]
        .join(' ')
        .toLowerCase();

      return haystack.includes(keyword);
    });

    if (!customerResults) return;

    if (filtered.length === 0) {
      customerResults.innerHTML = `
        <div class="setup-empty-state">
          ไม่พบลูกค้าที่ค้นหา
        </div>
      `;
      return;
    }

    customerResults.innerHTML = filtered
      .map((customer) => {
        const selected =
          String(state.selectedCustomer?.user_id || '') ===
          String(customer.user_id || '');

        return `
          <button
            type="button"
            class="customer-result-item${selected ? ' selected' : ''}"
            data-customer-id="${escapeHtml(customer.user_id)}"
            aria-pressed="${selected ? 'true' : 'false'}"
          >
            <span class="customer-result-avatar">
              ${userIcon}
            </span>

            <span class="customer-result-main">
              <strong>${escapeHtml(customer.user_name || '-')}</strong>

              <span>
                ${escapeHtml(customer.user_phone || '-')}
                ·
                ${escapeHtml(customer.user_email || '-')}
              </span>
            </span>
          </button>
        `;
      })
      .join('');
  }

  function updateCustomerSummary() {
    const customer = state.selectedCustomer;

    if (!customer) {
      if (selectedCustomerId) {
        selectedCustomerId.value = '';
      }

      if (summaryCustomerName) {
        summaryCustomerName.textContent =
          'ยังไม่ได้เลือกลูกค้า';
      }

      if (summaryCustomerCode) {
        summaryCustomerCode.textContent = '-';
      }

      if (summaryCustomerPhone) {
        summaryCustomerPhone.textContent = '-';
      }

      if (summaryCustomerEmail) {
        summaryCustomerEmail.textContent = '-';
      }

      if (summaryCustomerAddress) {
        summaryCustomerAddress.textContent = '-';
      }

      if (summaryCustomerDetails) {
        summaryCustomerDetails.hidden = true;
      }

      if (sameAddressCheckbox) {
        sameAddressCheckbox.checked = false;
        sameAddressCheckbox.disabled = true;
      }

      return;
    }

    if (selectedCustomerId) {
      selectedCustomerId.value =
        customer.user_id || '';
    }

    if (summaryCustomerName) {
      summaryCustomerName.textContent =
        customer.user_name || '-';
    }

    if (summaryCustomerCode) {
      summaryCustomerCode.textContent =
        customer.user_id || '-';
    }

    if (summaryCustomerPhone) {
      summaryCustomerPhone.textContent =
        customer.user_phone || '-';
    }

    if (summaryCustomerEmail) {
      summaryCustomerEmail.textContent =
        customer.user_email || '-';
    }

    if (summaryCustomerAddress) {
      summaryCustomerAddress.textContent =
        customer.user_address || '-';
    }

    if (summaryCustomerDetails) {
      summaryCustomerDetails.hidden = false;
    }

    if (sameAddressCheckbox) {
      sameAddressCheckbox.disabled = false;

      if (
        sameAddressCheckbox.checked &&
        setupAddress
      ) {
        setupAddress.value =
          customer.user_address || '';
      }
    }
  }

  function selectCustomer(customerId) {
    state.selectedCustomer =
      customers.find(
        (customer) =>
          String(customer.user_id) ===
          String(customerId)
      ) || null;

    updateCustomerSummary();
    renderCustomers();
    updateSubmitState();

    if (state.selectedCustomer) {
      switchTab('products');
    }
  }

  function getQuantity(productId) {
    return Number(
      state.quantities.get(String(productId)) || 0
    );
  }

  function setQuantity(productId, quantity) {
    const id = String(productId);
    const next = Math.max(
      0,
      Number(quantity) || 0
    );

    if (next === 0) {
      state.quantities.delete(id);
    } else {
      state.quantities.set(id, next);
    }

    renderProducts();
    renderSummary();
    updateHiddenItems();
    updateSubmitState();
  }

  function renderProducts() {
    const keyword = (productSearch?.value || '')
      .trim()
      .toLowerCase();

    const filtered = products.filter((product) => {
      const haystack = [
        product.pro_name,
        product.pro_id,
        product.protype_name,
      ]
        .join(' ')
        .toLowerCase();

      return haystack.includes(keyword);
    });

    if (productEmptyState) {
      productEmptyState.hidden =
        filtered.length !== 0;
    }

    if (!productGrid) return;

    productGrid.innerHTML = filtered
      .map((product) => {
        const qty = getQuantity(product.pro_id);
        const selected = qty > 0;

        return `
          <div
            class="product-list-item${selected ? ' selected' : ''}"
            data-product-id="${escapeHtml(product.pro_id)}"
          >
            <div class="product-list-icon">
              ${boxIcon}
            </div>

            <div class="product-list-info">
              <div class="product-list-type">
                ${escapeHtml(
                  product.protype_name || 'สินค้า'
                )}
              </div>

              <strong class="product-list-name">
                ${escapeHtml(product.pro_name || '-')}
              </strong>

              <div class="product-list-code">
                ${escapeHtml(product.pro_id || '-')}
              </div>
            </div>

            <button
              type="button"
              class="product-detail-btn"
              data-action="detail"
              data-product-id="${escapeHtml(product.pro_id)}"
              aria-label="ดูรายละเอียดสินค้า"
            >
              ${eyeIcon}
            </button>

            <div class="product-list-price">
              <small>ค่าติดตั้งต่อหน่วย</small>
              <strong>
                ${money(product.pro_price_install)}
              </strong>
            </div>

            <div class="qty-control">
              <button
                type="button"
                class="qty-btn"
                data-action="minus"
                data-product-id="${escapeHtml(product.pro_id)}"
              >
                ${minusIcon}
              </button>

              <span class="qty-value">
                ${qty}
              </span>

              <button
                type="button"
                class="qty-btn"
                data-action="plus"
                data-product-id="${escapeHtml(product.pro_id)}"
              >
                ${plusIcon}
              </button>
            </div>
          </div>
        `;
      })
      .join('');
  }

  function selectedProducts() {
    return products
      .map((product) => ({
        ...product,
        qty: getQuantity(product.pro_id),
      }))
      .filter(
        (product) => product.qty > 0
      );
  }

  function renderSummary() {
    const selected = selectedProducts();

    if (summaryItemCount) {
      summaryItemCount.textContent =
        `${selected.length} รายการ`;
    }

    if (!summaryItems) return;

    if (selected.length === 0) {
      summaryItems.innerHTML = `
        <div class="summary-empty">
          ยังไม่มีสินค้าที่เลือก
        </div>
      `;

      if (summaryTotal) {
        summaryTotal.textContent = money(0);
      }

      return;
    }

    summaryItems.innerHTML = selected
      .map((product) => {
        const unitPrice = Number(
          product.pro_price_install || 0
        );

        const total =
          unitPrice * product.qty;

        return `
          <div class="summary-item">
            <div class="summary-item-main">
              <strong>
                ${escapeHtml(product.pro_name || '-')}
              </strong>

              <span>
                จำนวน ${product.qty} ชิ้น
                · ค่าติดตั้ง ${money(unitPrice)}/ชิ้น
              </span>
            </div>

            <div class="summary-item-side">
              <strong>
                ${money(total)}
              </strong>

              <button
                type="button"
                class="summary-remove-btn"
                data-remove-product="${escapeHtml(product.pro_id)}"
              >
                ลบ
              </button>
            </div>
          </div>
        `;
      })
      .join('');

    const total = selected.reduce(
      (sum, product) =>
        sum +
        Number(product.pro_price_install || 0) *
          product.qty,
      0
    );

    if (summaryTotal) {
      summaryTotal.textContent =
        money(total);
    }
  }

  function updateHiddenItems() {
    const payload = selectedProducts().map(
      (product) => ({
        pro_id: product.pro_id,
        qty: product.qty,
      })
    );

    if (selectedItemsJson) {
      selectedItemsJson.value =
        JSON.stringify(payload);
    }
  }

  function updateSubmitState() {
    const hasCustomer =
      Boolean(state.selectedCustomer);

    const hasItems =
      selectedProducts().length > 0;

    const hasAddress =
      Boolean(setupAddress?.value.trim());

    const ready =
      hasCustomer &&
      hasItems &&
      hasAddress;

    if (submitSetupBtn) {
      submitSetupBtn.disabled = !ready;
    }

    if (summarySubmitNote) {
      if (ready) {
        summarySubmitNote.textContent =
          'ข้อมูลพร้อมสำหรับบันทึกใบงาน';
      } else {
        const missing = [];

        if (!hasCustomer) {
          missing.push('ลูกค้า');
        }

        if (!hasItems) {
          missing.push('สินค้า');
        }

        if (!hasAddress) {
          missing.push('ที่อยู่ติดตั้ง');
        }

        summarySubmitNote.textContent =
          `กรุณาเลือก/กรอก ${missing.join(', ')} ให้ครบ`;
      }
    }
  }

  function openProductModal(productId) {
    const product = products.find(
      (item) =>
        String(item.pro_id) ===
        String(productId)
    );

    if (!product || !productDetailModal) {
      return;
    }

    if (modalProductType) {
      modalProductType.textContent =
        product.protype_name || 'สินค้า';
    }

    if (modalProductName) {
      modalProductName.textContent =
        product.pro_name || '-';
    }

    if (modalProductCode) {
      modalProductCode.textContent =
        product.pro_id || '-';
    }

    if (modalProductPrice) {
      modalProductPrice.textContent =
        money(product.pro_price);
    }

    if (modalInstallPrice) {
      modalInstallPrice.textContent =
        money(product.pro_price_install);
    }

    productDetailModal.hidden = false;
  }

  function closeProductModal() {
    if (productDetailModal) {
      productDetailModal.hidden = true;
    }
  }

  $$('.setup-tab').forEach((button) => {
    button.addEventListener(
      'click',
      () => {
        switchTab(
          button.dataset.tab || 'customer'
        );
      }
    );
  });

  customerSearch?.addEventListener(
    'input',
    renderCustomers
  );

  productSearch?.addEventListener(
    'input',
    renderProducts
  );

  customerResults?.addEventListener(
    'click',
    (event) => {
      const button =
        event.target.closest(
          '[data-customer-id]'
        );

      if (!button) return;

      selectCustomer(
        button.dataset.customerId
      );
    }
  );

  productGrid?.addEventListener(
    'click',
    (event) => {
      const button =
        event.target.closest(
          '[data-action]'
        );

      if (!button) return;

      const productId =
        button.dataset.productId;

      const action =
        button.dataset.action;

      if (action === 'plus') {
        setQuantity(
          productId,
          getQuantity(productId) + 1
        );
      }

      if (action === 'minus') {
        setQuantity(
          productId,
          getQuantity(productId) - 1
        );
      }

      if (action === 'detail') {
        openProductModal(productId);
      }
    }
  );

  summaryItems?.addEventListener(
    'click',
    (event) => {
      const button =
        event.target.closest(
          '[data-remove-product]'
        );

      if (!button) return;

      setQuantity(
        button.dataset.removeProduct,
        0
      );
    }
  );

  sameAddressCheckbox?.addEventListener(
    'change',
    () => {
      if (!setupAddress) return;

      if (
        sameAddressCheckbox.checked &&
        state.selectedCustomer
      ) {
        setupAddress.value =
          state.selectedCustomer.user_address ||
          '';
      } else if (
        !sameAddressCheckbox.checked
      ) {
        setupAddress.value = '';
      }

      updateSubmitState();
    }
  );

  setupAddress?.addEventListener(
    'input',
    updateSubmitState
  );

  $$('[data-close-modal]').forEach(
    (element) => {
      element.addEventListener(
        'click',
        closeProductModal
      );
    }
  );

  document.addEventListener(
    'keydown',
    (event) => {
      if (
        event.key === 'Escape' &&
        productDetailModal &&
        !productDetailModal.hidden
      ) {
        closeProductModal();
      }
    }
  );

  form.addEventListener(
    'submit',
    (event) => {
      updateHiddenItems();
      updateSubmitState();

      if (submitSetupBtn?.disabled) {
        event.preventDefault();
      }
    }
  );

  /*
   * EDIT MODE
   * ถ้ามี window.createSetupInitial
   * ให้โหลดค่าของใบงานเดิมกลับมา
   */
  if (
    initial &&
    Object.keys(initial).length > 0
  ) {
    const initialCustomerId =
      String(initial.customerId || '');

    if (initialCustomerId !== '') {
      state.selectedCustomer =
        customers.find(
          (customer) =>
            String(customer.user_id) ===
            initialCustomerId
        ) || null;
    }

    if (Array.isArray(initial.items)) {
      initial.items.forEach((item) => {
        const productId =
          String(item?.pro_id || '');

        const qty = Math.max(
          0,
          Number(item?.qty) || 0
        );

        if (
          productId !== '' &&
          qty > 0
        ) {
          state.quantities.set(
            productId,
            qty
          );
        }
      });
    }

    if (setupAddress) {
      setupAddress.value =
        typeof initial.setupAddress ===
        'string'
          ? initial.setupAddress
          : '';
    }

    if (setupNote) {
      setupNote.value =
        typeof initial.setupNote ===
        'string'
          ? initial.setupNote
          : '';
    }
  }

  /*
   * เช็กว่า address เดิมตรงกับ
   * address ของลูกค้าหรือไม่
   */
  if (sameAddressCheckbox) {
    sameAddressCheckbox.disabled =
      !state.selectedCustomer;

    if (
      state.selectedCustomer &&
      setupAddress
    ) {
      const customerAddress =
        String(
          state.selectedCustomer
            .user_address || ''
        ).trim();

      const currentAddress =
        String(
          setupAddress.value || ''
        ).trim();

      sameAddressCheckbox.checked =
        customerAddress !== '' &&
        customerAddress ===
          currentAddress;
    }
  }

  renderCustomers();
  renderProducts();
  updateCustomerSummary();
  renderSummary();
  updateHiddenItems();
  updateSubmitState();
})();