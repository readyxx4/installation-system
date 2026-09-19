(function () {
  'use strict';

  const pages = Array.from(document.querySelectorAll('[data-admin-trend-section]'));
  if (!pages.length) return;
  const controllers = Object.create(null);
  const sequences = Object.create(null);
  const number = (value, decimals = 0) => Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

  const setUrl = (params) => {
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
    url.searchParams.delete('ajax'); url.searchParams.delete('section');
    window.history.replaceState({}, '', url);
  };

  const showError = (panel) => {
    panel.querySelector('.admin-report-async-error')?.remove();
    const message = document.createElement('p');
    message.className = 'admin-report-async-error';
    message.textContent = 'ไม่สามารถโหลดข้อมูลส่วนนี้ได้ กรุณาลองใหม่อีกครั้ง';
    panel.appendChild(message);
  };

  const request = async (panel, section, params) => {
    const key = panel.dataset.adminTrendSection;
    const sequence = (sequences[key] || 0) + 1;
    sequences[key] = sequence;
    controllers[key]?.abort();
    const controller = new AbortController(); controllers[key] = controller;
    panel.classList.add('is-loading'); panel.setAttribute('aria-busy', 'true');
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([name, value]) => url.searchParams.set(name, value));
    url.searchParams.set('ajax', '1'); url.searchParams.set('section', section);
    try {
      const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
      if (!response.ok) throw new Error('request-failed');
      const payload = await response.json();
      if (!payload.success || sequences[key] !== sequence) throw new Error('invalid-response');
      return payload;
    } finally {
      if (controllers[key] === controller) {
        delete controllers[key]; panel.classList.remove('is-loading'); panel.setAttribute('aria-busy', 'false');
      }
    }
  };

  const renderInstallation = (panel, payload) => {
    const max = Math.max(1, Number(payload.max || 0));
    const labels = payload.labels || [];
    ['created', 'completed'].forEach((series) => {
      const values = (payload[series] || []).map((value) => Number(value) || 0);
      const points = values.map((value, index) => (42 + index * 61) + ',' + (170 - (value / max) * 130).toFixed(2)).join(' ');
      panel.querySelector('.installation-trend-line--' + series)?.setAttribute('points', points);
      panel.querySelectorAll('.installation-trend-point--' + series).forEach((point, index) => {
        const value = values[index] || 0;
        point.setAttribute('cy', (170 - (value / max) * 130).toFixed(2));
        point.setAttribute('aria-label', labels[index] + ': ' + number(value) + ' งาน');
        const title = point.querySelector('title'); if (title) title.textContent = labels[index] + ': ' + number(value) + ' งาน';
      });
    });
    panel.querySelectorAll('.installation-trend-axis-label').forEach((label, index) => {
      const ratio = [1, 2 / 3, 1 / 3, 0][index] || 0;
      label.textContent = number(max * ratio);
    });
    const desc = panel.querySelector('#installation-trend-description'); if (desc) desc.textContent = 'จำนวนใบงานที่สร้างและเสร็จสิ้นในปี ' + payload.year;
  };

  const renderRevenue = (panel, payload) => {
    const monthly = Array.isArray(payload.monthly) ? payload.monthly : [];
    const max = Math.max(1, Number(payload.max || 0));
    const values = monthly.map((item) => Number(item?.fee || 0));
    const points = values.map((value, index) => (44 + index * 61) + ',' + (190 - (value / max) * 140).toFixed(2)).join(' ');
    panel.querySelector('.revenue-area-fill')?.setAttribute('points', '44,190 ' + points + ' 715,190');
    panel.querySelector('.revenue-area-line')?.setAttribute('points', points);
    panel.querySelectorAll('.revenue-area-point').forEach((point, index) => {
      const item = monthly[index] || { fee: 0 };
      point.setAttribute('cy', (190 - (Number(item.fee || 0) / max) * 140).toFixed(2));
      const title = point.querySelector('title'); if (title) title.textContent = (payload.labels || [])[index] + ': ' + number(item.fee, 2) + ' ฿';
    });
    panel.querySelectorAll('.revenue-area-axis-label').forEach((label, index) => { label.textContent = number((payload.axis || [])[index] || 0); });
    const desc = panel.querySelector('#revenue-trend-description'); if (desc) desc.textContent = 'ยอดค่าติดตั้งงานเสร็จสิ้นรายเดือนในปี ' + payload.year;
    const monthlyPanel = document.querySelector('[data-admin-revenue-monthly]');
    monthlyPanel?.querySelectorAll('tbody tr').forEach((row, index) => {
      const item = monthly[index] || { jobs: 0, fee: 0 };
      const jobs = Number(item.jobs || 0), fee = Number(item.fee || 0);
      const cells = row.querySelectorAll('td');
      if (cells[0]) cells[0].textContent = (payload.labels || [])[index] || '';
      if (cells[1]) cells[1].textContent = number(jobs) + ' งาน';
      if (cells[2]) cells[2].textContent = number(fee, 2) + ' ฿';
      if (cells[3]) cells[3].textContent = number(jobs ? fee / jobs : 0, 2) + ' ฿';
    });
    monthlyPanel?.querySelector('.report-panel-head p')?.replaceChildren(document.createTextNode('งานเสร็จสิ้นในปี ' + payload.year));
  };

  pages.forEach((panel) => {
    const form = panel.querySelector('[data-admin-report-year-form]');
    if (!form) return;
    const handleChange = () => {
      const field = form.querySelector('select').name;
      const section = panel.dataset.adminTrendSection === 'installation' ? 'installation-trend' : 'revenue-trend';
      request(panel, section, { [field]: form.querySelector('select').value })
        .then((payload) => {
          if (panel.dataset.adminTrendSection === 'installation') renderInstallation(panel, payload);
          else renderRevenue(panel, payload);
          setUrl({ report: panel.dataset.adminTrendSection === 'installation' ? 'installations' : 'revenue', [field]: payload.year });
        })
        .catch((error) => { if (error.name !== 'AbortError') showError(panel); });
    };
    form.querySelector('select')?.addEventListener('change', handleChange);
    form.addEventListener('submit', (event) => { event.preventDefault(); handleChange(); });
  });
})();
