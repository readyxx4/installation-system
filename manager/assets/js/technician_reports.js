(function () {
  'use strict';

  const page = document.querySelector('.manager-report-page');
  const form = page?.querySelector('[data-manager-report-filter-form]');
  const chartPanel = page?.querySelector('[data-manager-technician-chart]');
  const summaryPanel = page?.querySelector('[data-manager-technician-summary-panel]');
  if (!page || !form || !chartPanel || !summaryPanel) return;

  let controller = null;
  let sequence = 0;
  const number = (value) => Number(value || 0).toLocaleString('en-US');

  const setBusy = (value) => {
    [chartPanel, summaryPanel].forEach((panel) => {
      panel.classList.toggle('is-loading', value);
      panel.setAttribute('aria-busy', value ? 'true' : 'false');
    });
  };

  const showError = () => {
    [chartPanel, summaryPanel].forEach((panel) => {
      panel.querySelector('.manager-report-async-error')?.remove();
      const message = document.createElement('p');
      message.className = 'manager-report-async-error';
      message.textContent = 'ไม่สามารถโหลดข้อมูลส่วนช่างได้ กรุณาลองใหม่อีกครั้ง';
      panel.appendChild(message);
    });
  };

  const renderChart = (payload) => {
    const chart = page.querySelector('[data-manager-technician-chart-body]');
    const empty = page.querySelector('[data-manager-technician-empty]');
    if (!chart || !empty) return;

    const rows = Array.isArray(payload.rows) ? payload.rows : [];
    const max = Math.max(1, Number(payload.assigned_max || 0), Number(payload.completed_max || 0));
    const axis = chart.querySelectorAll('.manager-technician-chart-axis span');
    if (axis[0]) axis[0].textContent = number(max);
    if (axis[1]) axis[1].textContent = number(Math.round(max / 2));
    if (axis[2]) axis[2].textContent = '0';

    const groups = chart.querySelector('.manager-technician-cluster-groups');
    if (!groups) return;
    groups.replaceChildren();
    empty.hidden = rows.length > 0;
    chart.hidden = rows.length === 0;

    rows.forEach((item) => {
      const name = item.technician_name || item.technician_id || '-';
      const group = document.createElement('div');
      group.className = 'manager-technician-cluster-group';
      group.setAttribute('role', 'group');
      group.setAttribute('aria-label', name);

      const bars = document.createElement('div');
      bars.className = 'manager-technician-cluster-bars';
      [['assigned', 'is-assigned', 'ได้รับมอบหมาย'], ['completed', 'is-completed', 'เสร็จสิ้น']].forEach(([metric, tone, label]) => {
        const value = Math.max(0, Number(item[metric] || 0));
        const bar = document.createElement('div');
        bar.className = `manager-technician-cluster-bar ${tone}`;
        bar.style.setProperty('--bar-height', `${value / max * 100}%`);
        bar.setAttribute('role', 'img');
        bar.setAttribute('aria-label', `${name}: ${label} ${number(value)} งาน`);
        const valueElement = document.createElement('strong');
        valueElement.textContent = number(value);
        bar.appendChild(valueElement);
        bars.appendChild(bar);
      });

      const label = document.createElement('span');
      label.className = 'manager-technician-cluster-label';
      label.textContent = name;
      group.append(bars, label);
      groups.appendChild(group);
    });
  };

  const renderTable = (payload) => {
    const body = page.querySelector('[data-manager-technician-summary-body]');
    if (!body) return;
    const rows = Array.isArray(payload.rows) ? payload.rows : [];
    body.replaceChildren();

    if (!rows.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 5;
      td.className = 'empty-state';
      td.textContent = 'ไม่พบข้อมูลช่างตามตัวกรองที่เลือก';
      tr.appendChild(td);
      body.appendChild(tr);
      return;
    }

    rows.forEach((item) => {
      const tr = document.createElement('tr');
      const technicianCell = document.createElement('td');
      const idStrong = document.createElement('strong');
      idStrong.textContent = item.technician_id || '-';
      technicianCell.appendChild(idStrong);
      tr.appendChild(technicianCell);

      const nameCell = document.createElement('td');
      const name = document.createElement('span');
      name.className = 'manager-technician-summary-name';
      name.textContent = item.technician_name || '-';
      nameCell.appendChild(name);
      tr.appendChild(nameCell);

      tr.appendChild(Object.assign(document.createElement('td'), { textContent: number(item.in_progress) }));
      tr.appendChild(Object.assign(document.createElement('td'), { textContent: number(item.review) }));
      tr.appendChild(Object.assign(document.createElement('td'), { textContent: number(item.overdue) }));
      body.appendChild(tr);
    });
  };

  const load = async () => {
    const current = ++sequence;
    controller?.abort();
    controller = new AbortController();
    setBusy(true);
    const params = Object.fromEntries(new FormData(form).entries());
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
    url.searchParams.set('ajax', '1');
    url.searchParams.set('section', 'technician');
    try {
      const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
      if (!response.ok) throw new Error('request-failed');
      const payload = await response.json();
      if (current !== sequence || !payload.success) throw new Error('invalid-response');
      renderChart(payload);
      renderTable(payload);
    } catch (error) {
      if (error.name !== 'AbortError') showError();
    } finally {
      if (current === sequence) {
        setBusy(false);
        controller = null;
      }
    }
  };

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    load();
  });
}());
