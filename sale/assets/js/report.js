(function () {
  'use strict';

  const page = document.querySelector('.sale-report-page');
  if (!page) return;
  const controllers = Object.create(null);
  const sequences = Object.create(null);

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

  const panelFor = (section) => section === 'table'
    ? page.querySelector('.sale-report-table-panel')
    : page.querySelector('[data-sale-report-chart="' + section + '"]');

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

  const renderChart = (section, payload) => {
    const card = panelFor(section);
    const summary = card.querySelector('[data-sale-chart-summary]');
    const summaryValue = summary?.querySelector('strong');
    const summaryLabel = summary?.querySelector('span');
    const chartWrap = card.querySelector('[data-sale-chart-wrap]');
    const empty = card.querySelector('[data-sale-chart-empty]');
    if (summaryValue) summaryValue.textContent = Number(payload.total || 0).toLocaleString('en-US') + ' งาน';
    if (summaryLabel) summaryLabel.textContent = payload.period_label || '';
    if (chartWrap && payload.chart_html) {
      const parsed = new DOMParser().parseFromString(payload.chart_html, 'text/html').body.firstElementChild;
      if (parsed) chartWrap.replaceChildren(parsed);
    }
    if (empty) empty.hidden = Number(payload.total || 0) !== 0;
  };

  page.querySelectorAll('[data-sale-period-form]').forEach((form) => {
    const field = form.dataset.saleReportField;
    const input = form.querySelector('[data-sale-period-input]');
    const current = form.querySelector('[data-sale-period-current]');
    const tabs = Array.from(form.querySelectorAll('[data-sale-period-tab]'));
    if (!field || !input || !current || tabs.length === 0) return;
    let active = current.value || 'day';
    const values = { day: input.dataset.day || '', week: input.dataset.week || '', month: input.dataset.month || '' };
    const sync = () => {
      input.type = active === 'week' ? 'week' : active === 'month' ? 'month' : 'date';
      input.value = values[active] || '';
      current.value = active;
      tabs.forEach((tab) => {
        const selected = tab.dataset.salePeriodTab === active;
        tab.classList.toggle('is-active', selected);
        tab.setAttribute('aria-pressed', selected ? 'true' : 'false');
      });
    };
    const load = () => {
      if (input.value) values[active] = input.value;
      sync();
      request(field, { [field + '_period']: active, [field + '_value']: values[active] || '' })
        .then((payload) => {
          renderChart(field, payload);
          setUrl({ [field + '_period']: payload.period, [field + '_value']: payload.value });
        })
        .catch((error) => { if (error.name !== 'AbortError') errorMessage(panelFor(field)); });
    };
    tabs.forEach((tab) => tab.addEventListener('click', () => {
      const next = tab.dataset.salePeriodTab || 'day';
      if (next !== active) { active = next; load(); }
    }));
    input.addEventListener('change', load);
    form.addEventListener('submit', (event) => { event.preventDefault(); load(); });
    sync();
  });

  const tableForm = page.querySelector('[data-sale-report-table-form]');
  const tablePanel = panelFor('table');
  if (tableForm && tablePanel) {
    const renderTable = (payload) => {
      const body = tablePanel.querySelector('[data-sale-report-table-body]');
      if (!body) return;
      body.replaceChildren();
      if (!payload.rows?.length) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 7;
        cell.className = 'empty-state';
        cell.textContent = 'ยังไม่มีใบงานติดตั้ง';
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
      request('table', params).then((payload) => {
        renderTable(payload);
        setUrl({ q: params.q || '', status: params.status || '' });
        const clear = tableForm.querySelector('[data-sale-clear-filter]');
        if (clear) clear.hidden = !(params.q || params.status);
      }).catch((error) => { if (error.name !== 'AbortError') errorMessage(tablePanel); });
    });
    tableForm.querySelector('[data-sale-clear-filter]')?.addEventListener('click', (event) => {
      event.preventDefault();
      tableForm.querySelector('[name="q"]').value = '';
      tableForm.querySelector('[name="status"]').value = '';
      tableForm.dispatchEvent(new Event('submit', { cancelable: true }));
    });
  }
})();
