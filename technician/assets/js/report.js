(function () {
  'use strict';

  const page = document.querySelector('.technician-report-page');
  const form = page?.querySelector('[data-technician-report-filter-form]');
  if (!page || !form) return;

  const updatePrintDate = () => {
    page.querySelectorAll('[data-technician-report-print-date]').forEach((node) => {
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

  const formatNumber = (value) => Number(value || 0).toLocaleString('en-US');
  const periodSelect = form.querySelector('[data-technician-table-period]');
  const dateRange = form.querySelector('[data-technician-table-date-range]');
  const dateFrom = form.querySelector('[data-technician-table-date-from]');
  const dateTo = form.querySelector('[data-technician-table-date-to]');
  let controller = null;

  const setUrl = () => {
    const url = new URL(window.location.href);
    ['q', 'status', 'table_period', 'table_date_from', 'table_date_to'].forEach((key) => {
      const value = form.elements[key]?.value || '';
      if (value && !(key === 'table_period' && value === 'all')) url.searchParams.set(key, value);
      else url.searchParams.delete(key);
    });
    ['assigned_period', 'assigned_value', 'completed_period', 'completed_value', 'trend_week', 'ajax', 'section']
      .forEach((key) => url.searchParams.delete(key));
    window.history.replaceState({}, '', url);
  };

  const syncDateRange = () => {
    if (!dateRange || !periodSelect) return;
    dateRange.hidden = periodSelect.value !== 'custom';
    if (periodSelect.value !== 'custom') {
      if (dateFrom) dateFrom.value = '';
      if (dateTo) dateTo.value = '';
    }
  };

  const setBusy = (busy) => {
    page.classList.toggle('is-filter-loading', busy);
    page.querySelectorAll('.technician-report-summary-card, .technician-report-table-card')
      .forEach((panel) => panel.setAttribute('aria-busy', busy ? 'true' : 'false'));
  };

  const showError = () => {
    page.querySelector('.technician-report-async-error')?.remove();
    const message = document.createElement('p');
    message.className = 'technician-report-async-error';
    message.textContent = 'ไม่สามารถโหลดข้อมูลตามตัวกรองได้ กรุณาลองใหม่อีกครั้ง';
    form.insertAdjacentElement('afterend', message);
  };

  const request = async () => {
    controller?.abort();
    controller = new AbortController();
    const url = new URL(window.location.href);
    new FormData(form).forEach((value, key) => {
      if (value !== '') url.searchParams.set(key, value);
      else url.searchParams.delete(key);
    });
    url.searchParams.set('ajax', '1');
    url.searchParams.set('section', 'global');
    setBusy(true);
    try {
      const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
      if (!response.ok) throw new Error('request-failed');
      const payload = await response.json();
      if (!payload.success || payload.section !== 'global') throw new Error('invalid-response');
      return payload;
    } finally {
      setBusy(false);
      controller = null;
    }
  };

  const renderSummary = (metrics) => {
    page.querySelectorAll('[data-technician-summary-card]').forEach((card) => {
      const value = card.querySelector('strong');
      if (value) value.textContent = formatNumber(metrics?.[card.dataset.technicianSummaryCard] || 0);
    });
  };

  const renderJobRows = (selector, rows, emptyText) => {
    const body = page.querySelector(selector);
    if (!body) return;
    body.replaceChildren();
    if (!Array.isArray(rows) || rows.length === 0) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 6;
      td.innerHTML = `<div class="technician-report-empty-state technician-report-empty-state-compact"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><span>${emptyText}</span></div>`;
      tr.appendChild(td);
      body.appendChild(tr);
      return;
    }
    rows.forEach((item) => {
      const tr = document.createElement('tr');
      [item.setup_id, item.customer_name, item.product_text, item.install_date, item.install_time].forEach((value, index) => {
        const td = document.createElement('td');
        if (index === 0) {
          const strong = document.createElement('strong');
          strong.className = 'technician-report-job-id';
          strong.textContent = value || '--';
          td.appendChild(strong);
        } else {
          td.textContent = value || '--';
        }
        tr.appendChild(td);
      });
      const statusCell = document.createElement('td');
      const badge = document.createElement('span');
      badge.className = `badge technician-report-status-badge ${item.status_class || 'is-assigned'}`;
      badge.textContent = item.status_label || 'สถานะไม่ระบุ';
      statusCell.appendChild(badge);
      tr.appendChild(statusCell);
      body.appendChild(tr);
    });
  };

  const applyPayload = (payload) => {
    renderSummary(payload.metrics || {});
    renderJobRows('[data-technician-all-body]', payload.all_rows || [], 'ไม่มีงานที่ได้รับมอบหมาย');
    const allCount = page.querySelector('[data-technician-all-count]');
    if (allCount) allCount.textContent = `${formatNumber(payload.all_total)} รายการ`;
  };

  const load = () => {
    page.querySelector('.technician-report-async-error')?.remove();
    request()
      .then((payload) => {
        applyPayload(payload);
        setUrl();
      })
      .catch((error) => {
        if (error.name !== 'AbortError') showError();
      });
  };

  periodSelect?.addEventListener('change', syncDateRange);
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    load();
  });
  form.querySelector('[data-technician-report-filter-reset]')?.addEventListener('click', () => {
    form.reset();
    if (form.elements.q) form.elements.q.value = '';
    if (form.elements.status) form.elements.status.value = '';
    if (periodSelect) periodSelect.value = 'all';
    if (dateFrom) dateFrom.value = '';
    if (dateTo) dateTo.value = '';
    syncDateRange();
    load();
  });

  syncDateRange();
}());
