(function () {
  'use strict';

  const page = document.querySelector('.sale-report-page');
  if (!page) return;
  const controllers = Object.create(null);
  const sequences = Object.create(null);

  const updatePrintDate = () => {
    page.querySelectorAll('[data-sale-report-print-date]').forEach((node) => {
      const timezone = node.dataset.timezone || undefined;
      try {
        const printedDate = new Intl.DateTimeFormat('en-GB', {
          timeZone: timezone,
          day: '2-digit',
          month: '2-digit',
          year: 'numeric'
        }).format(new Date());
        node.textContent = `วันที่พิมพ์: ${printedDate}`;
      } catch (error) {
        node.textContent = `วันที่พิมพ์: ${new Date().toLocaleDateString('en-GB')}`;
      }
    });
  };

  updatePrintDate();
  window.addEventListener('beforeprint', updatePrintDate);

  const setUrl = (params) => {
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([key, value]) => {
      if (value === '' || value == null) url.searchParams.delete(key);
      else url.searchParams.set(key, value);
    });
    url.searchParams.delete('ajax');
    url.searchParams.delete('section');
    window.history.replaceState({}, '', url);
  };

  const panelFor = () => page.querySelector('.sale-report-table-panel');

  const busy = (panel, value) => {
    if (!panel) return;
    panel.classList.toggle('is-loading', value);
    panel.setAttribute('aria-busy', value ? 'true' : 'false');
    if (!value) panel.querySelector('.sale-report-async-error')?.remove();
  };

  const errorMessage = (panel) => {
    if (!panel) return;
    panel.querySelector('.sale-report-async-error')?.remove();
    const message = document.createElement('p');
    message.className = 'sale-report-async-error';
    message.textContent = 'ไม่สามารถโหลดข้อมูลส่วนนี้ได้ กรุณาลองใหม่อีกครั้ง';
    panel.appendChild(message);
  };

  const request = async (section, params) => {
    const panel = panelFor(section);
    const sequence = (sequences[section] || 0) + 1;
    sequences[section] = sequence;
    controllers[section]?.abort();
    const controller = new AbortController();
    controllers[section] = controller;
    busy(panel, true);
    const url = new URL(window.location.href);
    Object.entries(params || {}).forEach(([key, value]) => url.searchParams.set(key, value));
    url.searchParams.set('ajax', '1');
    url.searchParams.set('section', section);
    try {
      const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
      if (!response.ok) throw new Error('request-failed');
      const payload = await response.json();
      if (!payload.success || sequences[section] !== sequence) throw new Error('invalid-response');
      return payload;
    } finally {
      if (controllers[section] === controller) {
        delete controllers[section];
        busy(panel, false);
      }
    }
  };

  const tableForm = page.querySelector('[data-sale-report-table-form]');
  const tablePanel = panelFor('table');
  if (tableForm && tablePanel) {
    const periodSelect = tableForm.querySelector('[data-sale-created-period]');
    const dateRange = tableForm.querySelector('[data-sale-date-range]');
    const dateFrom = tableForm.querySelector('[data-sale-created-date-from]');
    const dateTo = tableForm.querySelector('[data-sale-created-date-to]');
    const syncDateRange = () => {
      const isCustom = periodSelect?.value === 'custom';
      if (dateRange) dateRange.hidden = !isCustom;
      [dateFrom, dateTo].forEach((input) => {
        if (input) input.disabled = !isCustom;
      });
    };

    periodSelect?.addEventListener('change', syncDateRange);
    syncDateRange();

    const renderSummary = (metrics = {}) => {
      const summary = page.querySelector('[data-sale-report-summary]');
      if (!summary) return;
      summary.querySelectorAll('[data-sale-report-metric]').forEach((node) => {
        const key = node.dataset.saleReportMetric;
        const value = Number(metrics[key] || 0);
        node.textContent = key === 'install_total'
          ? value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
          : value.toLocaleString('en-US');
      });
    };

    const renderTable = (payload) => {
      const body = tablePanel.querySelector('[data-sale-report-table-body]');
      if (!body) return;
      renderSummary(payload.metrics || {});
      const resultCount = page.querySelector('[data-sale-report-result-count]');
      if (resultCount) resultCount.textContent = `พบ ${Number(payload.count || 0).toLocaleString('en-US')} รายการ`;
      body.replaceChildren();
      if (!payload.rows?.length) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 7;
        cell.className = 'empty-state';
        cell.textContent = 'ไม่พบข้อมูลที่ตรงกับตัวกรอง';
        row.appendChild(cell); body.appendChild(row); return;
      }
      payload.rows.forEach((item) => {
        const row = document.createElement('tr');
        [item.setup_id, item.customer_name, item.created_display, item.install_display, item.technician_name].forEach((value, index) => {
          const cell = document.createElement('td');
          cell.textContent = value || '-';
          if (index === 0) cell.className = 'sale-report-setup-id';
          row.appendChild(cell);
        });
        const statusCell = document.createElement('td');
        const badge = document.createElement('span');
        badge.className = 'sale-report-status-badge tone-' + (item.status_tone || 'slate');
        badge.textContent = item.status_label || '-';
        statusCell.appendChild(badge); row.appendChild(statusCell);
        const money = document.createElement('td');
        money.className = 'sale-report-money'; money.textContent = item.install_display_money || '0.00 บาท';
        row.appendChild(money); body.appendChild(row);
      });
    };
    tableForm.addEventListener('submit', (event) => {
      event.preventDefault();
      const data = new FormData(tableForm);
      const params = Object.fromEntries(data.entries());
      params.created_period = periodSelect?.value || 'all';
      params.created_date_from = params.created_period === 'custom' ? (dateFrom?.value || '') : '';
      params.created_date_to = params.created_period === 'custom' ? (dateTo?.value || '') : '';
      request('table', params).then((payload) => {
        renderTable(payload);
        setUrl({
          q: params.q || '',
          status: params.status || '',
          created_period: params.created_period,
          created_date_from: params.created_date_from,
          created_date_to: params.created_date_to
        });
        const clear = tableForm.querySelector('[data-sale-clear-filter]');
        if (clear) clear.hidden = !(params.q || params.status || params.created_period !== 'all' || params.created_date_from || params.created_date_to);
      }).catch((error) => { if (error.name !== 'AbortError') errorMessage(tablePanel); });
    });
    tableForm.querySelector('[data-sale-clear-filter]')?.addEventListener('click', (event) => {
      event.preventDefault();
      tableForm.querySelector('[name="q"]').value = '';
      tableForm.querySelector('[name="status"]').value = '';
      if (periodSelect) periodSelect.value = 'all';
      if (dateFrom) dateFrom.value = '';
      if (dateTo) dateTo.value = '';
      syncDateRange();
      tableForm.dispatchEvent(new Event('submit', { cancelable: true }));
    });
  }
})();
