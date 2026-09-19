(function () {
  'use strict';

  const payload = window.managerCompletedReport || {};
  const chart = document.querySelector('[data-manager-completed-chart]');
  const summary = document.querySelector('[data-manager-completed-summary]');
  const tabs = Array.from(document.querySelectorAll('[data-manager-period]'));
  const periodInput = document.querySelector('[data-manager-period-input]');

  if (!chart || !summary || tabs.length === 0) return;

  const labels = {
    day: ['เช้า', 'บ่าย', 'เย็น'],
    week: ['จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส', 'อา'],
    month: ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'],
  };

  let activePeriod = ['day', 'week', 'month'].includes(payload.initialPeriod)
    ? payload.initialPeriod
    : 'day';

  const periodValues = {
    day: payload.initialDay || '',
    week: payload.initialWeek || '',
    month: payload.initialMonth || '',
  };

  const periodInputConfig = {
    day: { type: 'date', label: 'เลือกวันที่' },
    week: { type: 'week', label: 'เลือกสัปดาห์' },
    month: { type: 'month', label: 'เลือกเดือน' },
  };

  const numberFormat = (value) => (Number(value) || 0).toLocaleString('en-US');

  const formatDate = (isoDate) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(isoDate || ''));
    return match ? `${match[3]}/${match[2]}/${match[1]}` : '-';
  };

  const weekToIsoDate = (weekValue) => {
    const match = /^(\d{4})-W(\d{2})$/.exec(String(weekValue || ''));
    if (!match) return '';

    const year = Number(match[1]);
    const week = Number(match[2]);
    const januaryFourth = new Date(Date.UTC(year, 0, 4));
    const isoDay = januaryFourth.getUTCDay() || 7;
    januaryFourth.setUTCDate(januaryFourth.getUTCDate() - isoDay + 1 + ((week - 1) * 7));

    return `${januaryFourth.getUTCFullYear()}-${String(januaryFourth.getUTCMonth() + 1).padStart(2, '0')}-${String(januaryFourth.getUTCDate()).padStart(2, '0')}`;
  };

  const syncPeriodInput = () => {
    if (!periodInput) return;

    const config = periodInputConfig[activePeriod];
    periodInput.type = config.type;
    periodInput.value = periodValues[activePeriod];
    periodInput.setAttribute('aria-label', config.label);
  };

  const rememberPeriodInput = () => {
    if (periodInput && periodInput.value) {
      periodValues[activePeriod] = periodInput.value;
    }
  };

  const renderBars = (barLabels, values) => {
    chart.replaceChildren();
    chart.dataset.period = activePeriod;

    const safeValues = Array.isArray(values)
      ? values.map((value) => Math.max(0, Number(value) || 0))
      : [];
    const maxValue = safeValues.reduce((max, value) => Math.max(max, value), 0);
    const bars = document.createElement('div');
    bars.className = 'completed-install-bars';
    bars.style.setProperty('--completed-bar-count', String(barLabels.length));

    barLabels.forEach((labelText, index) => {
      const value = safeValues[index] || 0;
      const item = document.createElement('div');
      item.className = 'completed-install-bar-item';
      item.classList.toggle('is-zero', value === 0);

      const stage = document.createElement('div');
      stage.className = 'completed-install-bar-stage';
      const track = document.createElement('div');
      track.className = 'completed-install-bar-track';
      const fill = document.createElement('span');
      fill.className = 'completed-install-bar-fill';
      const height = maxValue > 0 ? (value / maxValue) * 100 : 0;
      fill.style.setProperty('--completed-bar-height', `${value > 0 ? Math.max(8, height) : 0}%`);

      if (value > 0) {
        const valueElement = document.createElement('strong');
        valueElement.className = 'completed-install-bar-value';
        valueElement.textContent = numberFormat(value);
        fill.appendChild(valueElement);
      }

      track.appendChild(fill);
      stage.appendChild(track);
      const label = document.createElement('span');
      label.className = 'completed-install-bar-label';
      label.textContent = labelText;
      item.append(stage, label);
      bars.appendChild(item);
    });

    chart.appendChild(bars);
    return safeValues.reduce((total, value) => total + value, 0);
  };

  const render = () => {
    syncPeriodInput();

    const dayData = payload.day || {};
    const weekData = payload.week || {};
    const monthData = payload.month || {};
    let values = [];
    let summaryText = '';

    if (activePeriod === 'day') {
      const selectedDay = periodValues.day || payload.initialDay;
      values = (dayData.dates && dayData.dates[selectedDay]) || [0, 0, 0];
      summaryText = `วันที่ ${formatDate(selectedDay)} เสร็จแล้ว ${numberFormat(values.reduce((total, value) => total + (Number(value) || 0), 0))} งาน`;
    } else if (activePeriod === 'week') {
      const selectedWeek = periodValues.week || payload.initialWeek;
      const weekStart = weekToIsoDate(selectedWeek);
      values = (weekData.weeks && weekData.weeks[weekStart]) || Array(7).fill(0);
      summaryText = `สัปดาห์เริ่ม ${formatDate(weekStart)} เสร็จแล้ว ${numberFormat(values.reduce((total, value) => total + (Number(value) || 0), 0))} งาน`;
    } else {
      const selectedMonth = periodValues.month || payload.initialMonth;
      const monthMatch = /^(\d{4})-(\d{2})$/.exec(selectedMonth || '');
      const year = monthMatch ? monthMatch[1] : '';
      const valuesByYear = monthData.years && monthData.years[year];
      values = Array.isArray(valuesByYear) ? valuesByYear : Array(12).fill(0);
      summaryText = `เดือน ${monthMatch ? monthMatch[2] : '--'}/${year || '----'} เสร็จแล้ว ${numberFormat(values.reduce((total, value) => total + (Number(value) || 0), 0))} งาน`;
    }

    tabs.forEach((tab) => {
      const isActive = tab.dataset.managerPeriod === activePeriod;
      tab.classList.toggle('is-active', isActive);
      tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    summary.textContent = summaryText;
    renderBars(labels[activePeriod], values);
    const url = new URL(window.location.href);
    url.searchParams.set('period', activePeriod);
    url.searchParams.set('chart_' + activePeriod, periodValues[activePeriod] || '');
    url.searchParams.delete('ajax');
    url.searchParams.delete('section');
    window.history.replaceState({}, '', url);
  };

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      rememberPeriodInput();
      activePeriod = tab.dataset.managerPeriod || 'day';
      render();
    });
  });

  if (periodInput) {
    periodInput.addEventListener('change', () => {
      rememberPeriodInput();
      render();
    });
  }

  render();
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
