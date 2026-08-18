(() => {
  'use strict';

  const data = window.createSetupData || {
    customers: [],
    products: [],
  };

  const state = {
    customer: null,
    items: new Map(),
  };

  const money = new Intl.NumberFormat('th-TH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

  const escapeHtml = (value) =>
    String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');

  /* =========================================================
     DOM Elements
     ========================================================= */

  const tabs = document.querySelectorAll('.setup-tab');

  const panels = {
    customer: document.getElementById('customerPanel'),
    products: document.getElementById('productsPanel'),
  };

  const customerSearch =
    document.getElementById('customerSearch');

  const customerResults =
    document.getElementById('customerResults');

  const selectedCustomerCard =
    document.getElementById('selectedCustomerCard');

  const selectedCustomerId =
    document.getElementById('selectedCustomerId');

  const selectedCustomerName =
    document.getElementById('selectedCustomerName');

  const selectedCustomerCode =
    document.getElementById('selectedCustomerCode');

  const selectedCustomerPhone =
    document.getElementById('selectedCustomerPhone');

  const selectedCustomerEmail =
    document.getElementById('selectedCustomerEmail');

  const selectedCustomerAddress =
    document.getElementById('selectedCustomerAddress');

  const changeCustomerBtn =
    document.getElementById('changeCustomerBtn');

  const summaryCustomerName =
    document.getElementById('summaryCustomerName');

  const productSearch =
    document.getElementById('productSearch');

  const productGrid =
    document.getElementById('productGrid');

  const productEmptyState =
    document.getElementById('productEmptyState');

  const summaryItems =
    document.getElementById('summaryItems');

  const summaryItemCount =
    document.getElementById('summaryItemCount');

  const summaryTotal =
    document.getElementById('summaryTotal');

  const selectedItemsJson =
    document.getElementById('selectedItemsJson');

  const sameAddressCheckbox =
    document.getElementById('sameAddressCheckbox');

  const setupAddress =
    document.getElementById('setupAddress');

  const setupNote =
    document.getElementById('setupNote');

  const createSetupForm =
    document.getElementById('createSetupForm');

  const submitSetupBtn =
    document.getElementById('submitSetupBtn');

  const modal =
    document.getElementById('productDetailModal');

  const modalProductType =
    document.getElementById('modalProductType');

  const modalProductName =
    document.getElementById('modalProductName');

  const modalProductCode =
    document.getElementById('modalProductCode');

  const modalProductPrice =
    document.getElementById('modalProductPrice');

  const modalInstallPrice =
    document.getElementById('modalInstallPrice');

  /* =========================================================
     Tab
     ========================================================= */

  function switchTab(tabName) {
    tabs.forEach((tab) => {
      tab.classList.toggle(
        'active',
        tab.dataset.tab === tabName
      );
    });

    Object.entries(panels).forEach(([name, panel]) => {
      if (!panel) {
        return;
      }

      panel.classList.toggle(
        'active',
        name === tabName
      );
    });
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      switchTab(tab.dataset.tab);
    });
  });

  /* =========================================================
     Customer
     ========================================================= */

  function customerSearchText(customer) {
    return [
      customer.user_id,
      customer.user_name,
      customer.user_phone,
      customer.user_email,
      customer.user_address,
    ]
      .join(' ')
      .toLowerCase();
  }

  function renderCustomerResults(query = '') {
    if (!customerResults) {
      return;
    }

    const normalizedQuery =
      String(query).trim().toLowerCase();

    const matches = data.customers.filter(
      (customer) => {
        if (!normalizedQuery) {
          return true;
        }

        return customerSearchText(customer).includes(
          normalizedQuery
        );
      }
    );

    if (matches.length === 0) {
      customerResults.innerHTML = `
        <div class="setup-empty-state">
          ไม่พบข้อมูลลูกค้า
        </div>
      `;

      return;
    }

    customerResults.innerHTML = matches
      .map((customer) => {
        const isSelected =
          state.customer &&
          String(state.customer.user_id) ===
            String(customer.user_id);

        return `
          <button
            type="button"
            class="customer-result-item${
              isSelected ? ' selected' : ''
            }"
            data-customer-id="${escapeHtml(
              customer.user_id
            )}"
          >
            <div class="customer-result-avatar">
              <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  d="M20 21a8 8 0 0 0-16 0"
                ></path>
                <circle
                  cx="12"
                  cy="7"
                  r="4"
                ></circle>
              </svg>
            </div>

            <div class="customer-result-main">
              <strong>
                ${escapeHtml(
                  customer.user_name || '-'
                )}
              </strong>

              <span>
                ${escapeHtml(
                  customer.user_phone || '-'
                )}
                ·
                ${escapeHtml(
                  customer.user_email || '-'
                )}
              </span>
            </div>

            <div class="customer-result-code">
              ${escapeHtml(customer.user_id)}
            </div>
          </button>
        `;
      })
      .join('');
  }

  function selectCustomer(customerId) {
    const customer = data.customers.find(
      (item) =>
        String(item.user_id) ===
        String(customerId)
    );

    if (!customer) {
      return;
    }

    state.customer = customer;

    if (selectedCustomerId) {
      selectedCustomerId.value =
        customer.user_id || '';
    }

    if (selectedCustomerName) {
      selectedCustomerName.textContent =
        customer.user_name || '-';
    }

    if (selectedCustomerCode) {
      selectedCustomerCode.textContent =
        customer.user_id || '-';
    }

    if (selectedCustomerPhone) {
      selectedCustomerPhone.textContent =
        customer.user_phone || '-';
    }

    if (selectedCustomerEmail) {
      selectedCustomerEmail.textContent =
        customer.user_email || '-';
    }

    if (selectedCustomerAddress) {
      selectedCustomerAddress.textContent =
        customer.user_address || '-';
    }

    if (summaryCustomerName) {
      summaryCustomerName.textContent =
        customer.user_name || '-';
    }

    if (selectedCustomerCard) {
      selectedCustomerCard.hidden = false;
    }

    renderCustomerResults(
      customerSearch?.value || ''
    );

    if (
      sameAddressCheckbox &&
      sameAddressCheckbox.checked &&
      setupAddress
    ) {
      setupAddress.value =
        customer.user_address || '';

      setupAddress.readOnly = true;
    }

    updateSubmitState();
  }

  function clearCustomer() {
    state.customer = null;

    if (selectedCustomerId) {
      selectedCustomerId.value = '';
    }

    if (selectedCustomerCard) {
      selectedCustomerCard.hidden = true;
    }

    if (summaryCustomerName) {
      summaryCustomerName.textContent =
        'ยังไม่ได้เลือกลูกค้า';
    }

    if (
      sameAddressCheckbox &&
      sameAddressCheckbox.checked &&
      setupAddress
    ) {
      setupAddress.value = '';
      setupAddress.readOnly = false;
    }

    if (customerSearch) {
      customerSearch.value = '';
      customerSearch.focus();
    }

    renderCustomerResults('');
    updateSubmitState();
  }

  if (customerSearch) {
    customerSearch.addEventListener(
      'input',
      () => {
        renderCustomerResults(
          customerSearch.value
        );
      }
    );
  }

  if (customerResults) {
    customerResults.addEventListener(
      'click',
      (event) => {
        const customerItem =
          event.target.closest(
            '[data-customer-id]'
          );

        if (!customerItem) {
          return;
        }

        selectCustomer(
          customerItem.dataset.customerId
        );
      }
    );
  }

  if (changeCustomerBtn) {
    changeCustomerBtn.addEventListener(
      'click',
      clearCustomer
    );
  }

  /* =========================================================
     Product
     ========================================================= */

  function getProduct(productId) {
    return data.products.find(
      (product) =>
        String(product.pro_id) ===
        String(productId)
    );
  }

  function getQty(productId) {
    return (
      state.items.get(String(productId)) || 0
    );
  }

  function setQty(productId, qty) {
    const normalizedQty = Math.max(
      0,
      Number.parseInt(qty, 10) || 0
    );

    if (normalizedQty === 0) {
      state.items.delete(String(productId));
    } else {
      state.items.set(
        String(productId),
        normalizedQty
      );
    }

    renderProducts(
      productSearch?.value || ''
    );

    renderSummary();
  }

  function productSearchText(product) {
    return [
      product.pro_id,
      product.pro_name,
      product.protype_name,
      product.protype_id,
    ]
      .join(' ')
      .toLowerCase();
  }

  function renderProducts(query = '') {
  if (!productGrid) {
    return;
  }

  const normalizedQuery =
    String(query).trim().toLowerCase();

  const products = data.products.filter(
    (product) => {
      if (!normalizedQuery) {
        return true;
      }

      return productSearchText(product).includes(
        normalizedQuery
      );
    }
  );

  if (productEmptyState) {
    productEmptyState.hidden =
      products.length > 0;
  }

  if (products.length === 0) {
    productGrid.innerHTML = '';
    return;
  }

  productGrid.innerHTML = products
    .map((product) => {
      const qty = getQty(product.pro_id);
      const installPrice = Number(
        product.pro_price_install || 0
      );

      const isSelected = qty > 0;

      return `
        <article
          class="product-list-item${
            isSelected ? ' selected' : ''
          }"
        >
          <div class="product-list-icon">
            <svg
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path d="M21 8l-9-5-9 5 9 5 9-5z"></path>
              <path d="M3 8v8l9 5 9-5V8"></path>
              <path d="M12 13v8"></path>
            </svg>
          </div>

          <div class="product-list-info">
            <div class="product-list-type">
              ${escapeHtml(
                product.protype_name ||
                  'ไม่ระบุประเภท'
              )}
            </div>

            <strong class="product-list-name">
              ${escapeHtml(
                product.pro_name || '-'
              )}
            </strong>

            <div class="product-list-code">
              ${escapeHtml(
                product.pro_id || '-'
              )}
            </div>
          </div>

          <div class="product-list-price">
            <small>ค่าติดตั้งต่อหน่วย</small>

            <strong>
              ${money.format(installPrice)}
              บาท
            </strong>
          </div>

          <button
            type="button"
            class="product-detail-btn"
            data-product-detail="${escapeHtml(
              product.pro_id
            )}"
            aria-label="ดูรายละเอียดสินค้า"
          >
            <svg
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path
                d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"
              ></path>
              <circle
                cx="12"
                cy="12"
                r="3"
              ></circle>
            </svg>
          </button>

          <div class="qty-control">
            <button
              type="button"
              class="qty-btn"
              data-qty-action="minus"
              data-product-id="${escapeHtml(
                product.pro_id
              )}"
              aria-label="ลดจำนวนสินค้า"
            >
              <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path d="M5 12h14"></path>
              </svg>
            </button>

            <span class="qty-value">
              ${qty}
            </span>

            <button
              type="button"
              class="qty-btn"
              data-qty-action="plus"
              data-product-id="${escapeHtml(
                product.pro_id
              )}"
              aria-label="เพิ่มจำนวนสินค้า"
            >
              <svg
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path d="M12 5v14"></path>
                <path d="M5 12h14"></path>
              </svg>
            </button>
          </div>
        </article>
      `;
    })
    .join('');
}

  if (productSearch) {
    productSearch.addEventListener(
      'input',
      () => {
        renderProducts(productSearch.value);
      }
    );
  }

  if (productGrid) {
    productGrid.addEventListener(
      'click',
      (event) => {
        const qtyButton =
          event.target.closest(
            '[data-qty-action]'
          );

        if (qtyButton) {
          const productId =
            qtyButton.dataset.productId;

          const action =
            qtyButton.dataset.qtyAction;

          const currentQty =
            getQty(productId);

          if (action === 'plus') {
            setQty(
              productId,
              currentQty + 1
            );
          } else {
            setQty(
              productId,
              currentQty - 1
            );
          }

          return;
        }

        const detailButton =
          event.target.closest(
            '[data-product-detail]'
          );

        if (detailButton) {
          openProductModal(
            detailButton.dataset.productDetail
          );
        }
      }
    );
  }

  /* =========================================================
     Summary
     ========================================================= */

  function renderSummary() {
    const items = Array.from(
      state.items.entries()
    )
      .map(([productId, qty]) => {
        const product =
          getProduct(productId);

        if (!product) {
          return null;
        }

        return {
          product,
          qty,
        };
      })
      .filter(Boolean);

    const totalQty = items.reduce(
      (sum, item) => sum + item.qty,
      0
    );

    const totalPrice = items.reduce(
      (sum, item) =>
        sum +
        Number(
          item.product.pro_price_install || 0
        ) *
          item.qty,
      0
    );

    if (summaryItemCount) {
      summaryItemCount.textContent =
        `${totalQty} รายการ`;
    }

    if (summaryTotal) {
      summaryTotal.textContent =
        `${money.format(totalPrice)} บาท`;
    }

    if (selectedItemsJson) {
      selectedItemsJson.value =
        JSON.stringify(
          items.map((item) => ({
            pro_id: item.product.pro_id,
            qty: item.qty,
          }))
        );
    }

    if (!summaryItems) {
      updateSubmitState();
      return;
    }

    if (items.length === 0) {
      summaryItems.innerHTML = `
        <div class="summary-empty">
          ยังไม่มีสินค้าที่เลือก
        </div>
      `;
    } else {
      summaryItems.innerHTML = items
        .map((item) => {
          const price = Number(
            item.product.pro_price_install || 0
          );

          const subtotal =
            price * item.qty;

          return `
            <div class="summary-item">
              <div class="summary-item-main">
                <strong>
                  ${escapeHtml(
                    item.product.pro_name || '-'
                  )}
                </strong>

                <span>
                  ${item.qty}
                  ×
                  ${money.format(price)}
                  บาท
                </span>
              </div>

              <div class="summary-item-side">
                <strong>
                  ${money.format(subtotal)}
                  บาท
                </strong>

                <button
                  type="button"
                  class="summary-remove-btn"
                  data-remove-product="${escapeHtml(
                    item.product.pro_id
                  )}"
                  aria-label="ลบสินค้า"
                >
                  ลบ
                </button>
              </div>
            </div>
          `;
        })
        .join('');
    }

    updateSubmitState();
  }

  if (summaryItems) {
    summaryItems.addEventListener(
      'click',
      (event) => {
        const removeButton =
          event.target.closest(
            '[data-remove-product]'
          );

        if (!removeButton) {
          return;
        }

        setQty(
          removeButton.dataset.removeProduct,
          0
        );
      }
    );
  }

  /* =========================================================
     Address
     ========================================================= */

  if (sameAddressCheckbox) {
    sameAddressCheckbox.addEventListener(
      'change',
      () => {
        if (!setupAddress) {
          return;
        }

        if (sameAddressCheckbox.checked) {
          setupAddress.value =
            state.customer?.user_address || '';

          setupAddress.readOnly =
            Boolean(state.customer);
        } else {
          setupAddress.readOnly = false;
        }

        updateSubmitState();
      }
    );
  }

  if (setupAddress) {
    setupAddress.addEventListener(
      'input',
      updateSubmitState
    );
  }

  /* =========================================================
     Submit validation
     ========================================================= */

  function updateSubmitState() {
    if (!submitSetupBtn) {
      return;
    }

    const hasCustomer =
      Boolean(state.customer);

    const hasProducts =
      state.items.size > 0;

    const hasAddress =
      Boolean(
        setupAddress &&
          setupAddress.value.trim() !== ''
      );

    submitSetupBtn.disabled = !(
      hasCustomer &&
      hasProducts &&
      hasAddress
    );
  }

  /* =========================================================
     Product Modal
     ========================================================= */

  function openProductModal(productId) {
    const product = getProduct(productId);

    if (!product || !modal) {
      return;
    }

    if (modalProductType) {
      modalProductType.textContent =
        product.protype_name ||
        'ไม่ระบุประเภท';
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
        `${money.format(
          Number(product.pro_price || 0)
        )} บาท`;
    }

    if (modalInstallPrice) {
      modalInstallPrice.textContent =
        `${money.format(
          Number(
            product.pro_price_install || 0
          )
        )} บาท`;
    }

    modal.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function closeProductModal() {
    if (!modal) {
      return;
    }

    modal.hidden = true;
    document.body.style.overflow = '';
  }

  if (modal) {
    modal.addEventListener(
      'click',
      (event) => {
        const closeElement =
          event.target.closest(
            '[data-close-modal]'
          );

        if (closeElement) {
          closeProductModal();
        }
      }
    );
  }

  document.addEventListener(
    'keydown',
    (event) => {
      if (
        event.key === 'Escape' &&
        modal &&
        !modal.hidden
      ) {
        closeProductModal();
      }
    }
  );

  /* =========================================================
     Form submit
     ========================================================= */

  if (createSetupForm) {
    createSetupForm.addEventListener(
      'submit',
      (event) => {
        if (!state.customer) {
          event.preventDefault();

          switchTab('customer');

          if (customerSearch) {
            customerSearch.focus();
          }

          return;
        }

        if (state.items.size === 0) {
          event.preventDefault();

          switchTab('products');

          if (productSearch) {
            productSearch.focus();
          }

          return;
        }

        if (
          !setupAddress ||
          setupAddress.value.trim() === ''
        ) {
          event.preventDefault();

          if (setupAddress) {
            setupAddress.focus();
          }

          return;
        }

        if (submitSetupBtn) {
          submitSetupBtn.disabled = true;
          submitSetupBtn.textContent =
            'กำลังบันทึก...';
        }
      }
    );
  }

  /* =========================================================
     Initial render
     ========================================================= */

  if (selectedCustomerCard) {
    selectedCustomerCard.hidden = true;
  }

  if (modal) {
    modal.hidden = true;
  }

  renderCustomerResults('');
  renderProducts('');
  renderSummary();

  const initial = window.createSetupInitial || null;

  if (initial) {
    if (initial.customerId) {
      selectCustomer(initial.customerId);
    }

    if (setupAddress && typeof initial.setupAddress === 'string') {
      setupAddress.value = initial.setupAddress;
      setupAddress.readOnly = false;
    }

    if (setupNote && typeof initial.setupNote === 'string') {
      setupNote.value = initial.setupNote;
    }

    if (Array.isArray(initial.items)) {
      initial.items.forEach((item) => {
        const productId = String(item.pro_id || '');
        const qty = Number.parseInt(item.qty, 10) || 0;

        if (productId && qty > 0) {
          state.items.set(productId, qty);
        }
      });

      renderProducts(productSearch?.value || '');
      renderSummary();
    }
  }
})();
