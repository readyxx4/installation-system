(function () {
  'use strict';

  const updatePrintDate = () => {
    document.querySelectorAll('[data-manager-report-print-date]').forEach((node) => {
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

})();

(function () {
  'use strict';
  const page = document.querySelector('.manager-report-page');
  const form = page?.querySelector('[data-manager-report-filter-form]');
  const panel = page?.querySelector('[data-manager-report-table-panel]');
  if (!page || !form || !panel) return;

  let controller = null;
  let sequence = 0;
  const setUrl = (params) => {
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([key, value]) => {
      if (value === '' || value == null) url.searchParams.delete(key);
      else url.searchParams.set(key, value);
    });
    url.searchParams.delete('ajax'); url.searchParams.delete('section');
    window.history.replaceState({}, '', url);
  };
  const showError = () => {
    panel.querySelector('.manager-report-async-error')?.remove();
    const message = document.createElement('p');
    message.className = 'manager-report-async-error';
    message.textContent = 'ไม่สามารถโหลดข้อมูลส่วนนี้ได้ กรุณาลองใหม่อีกครั้ง';
    panel.appendChild(message);
  };
  const render = (payload) => {
    const body = panel.querySelector('[data-manager-report-table-body]');
    if (!body) return;
    body.replaceChildren();
    const rows = Array.isArray(payload.rows) ? payload.rows : [];
    if (!rows.length) {
      const tr = document.createElement('tr'); const td = document.createElement('td');
      td.colSpan = 6; td.className = 'empty-state'; td.textContent = 'ไม่พบใบงานตามเงื่อนไขที่เลือก';
      tr.appendChild(td); body.appendChild(tr);
    } else rows.forEach((item) => {
      const tr = document.createElement('tr');
      [item.setup_id, item.customer_name, item.technician_display, item.install_date_display, item.install_time_display].forEach((value) => {
        const td = document.createElement('td'); td.textContent = value || '-'; tr.appendChild(td);
      });
      const td = document.createElement('td'); const badge = document.createElement('span');
      badge.className = 'report-status-label';
      const icon = document.createElement('i'); icon.className = 'fa-solid ' + (item.status_icon || 'fa-circle-question') + ' tone-' + (item.status_tone || 'slate'); icon.setAttribute('aria-hidden', 'true');
      badge.append(icon, document.createTextNode(item.status_label || '-')); td.appendChild(badge); tr.appendChild(td); body.appendChild(tr);
    });
    const total = panel.querySelector('[data-manager-table-total]');
    if (total) total.textContent = Number(payload.total || 0).toLocaleString('en-US') + ' รายการ';
  };
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const current = ++sequence;
    controller?.abort(); controller = new AbortController();
    panel.classList.add('is-loading'); panel.setAttribute('aria-busy', 'true');
    const params = Object.fromEntries(new FormData(form).entries());
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
    url.searchParams.set('ajax', '1'); url.searchParams.set('section', 'table');
    try {
      const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
      if (!response.ok) throw new Error('request-failed');
      const payload = await response.json();
      if (current !== sequence || !payload.success) throw new Error('invalid-response');
      render(payload); setUrl({ q: params.q || '', status: params.status || '' });
    } catch (error) {
      if (error.name !== 'AbortError') showError();
    } finally {
      if (current === sequence) { panel.classList.remove('is-loading'); panel.setAttribute('aria-busy', 'false'); }
    }
  });
  form.querySelector('.manager-report-reset')?.addEventListener('click', (event) => {
    event.preventDefault();
    form.querySelector('[name="q"]').value = '';
    form.querySelector('[name="status"]').value = '';
    form.dispatchEvent(new Event('submit', { cancelable: true }));
  });
})();
