(function () {
  'use strict';

  const page = document.querySelector('.technician-report-page');
  if (!page) return;

  const controllers = Object.create(null);
  const sequences = Object.create(null);
  const formatNumber = (value) => Number(value || 0).toLocaleString('en-US');

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

  const panelFor = (section) => section === 'trend'
    ? page.querySelector('.technician-report-trend-card')
    : page.querySelectorAll('.technician-report-chart-card')[section === 'assigned' ? 0 : 1];

  const setBusy = (panel, busy) => {
    if (!panel) return;
    panel.classList.toggle('is-loading', busy);
    panel.setAttribute('aria-busy', busy ? 'true' : 'false');
    if (!busy) panel.querySelector('.technician-report-async-error')?.remove();
  };

  const showError = (panel) => {
    if (!panel) return;
    panel.querySelector('.technician-report-async-error')?.remove();
    const message = document.createElement('p');
    message.className = 'technician-report-async-error';
    message.textContent = 'ไม่สามารถโหลดข้อมูลส่วนนี้ได้ กรุณาลองใหม่อีกครั้ง';
    panel.appendChild(message);
  };

  const request = async (section, params) => {
    const panel = panelFor(section);
    const seq = (sequences[section] || 0) + 1;
    sequences[section] = seq;
    controllers[section]?.abort();
    const controller = new AbortController();
    controllers[section] = controller;
    setBusy(panel, true);
    const url = new URL(window.location.href);
    Object.entries(params || {}).forEach(([key, value]) => url.searchParams.set(key, value));
    url.searchParams.set('ajax', '1');
    url.searchParams.set('section', section);
    try {
      const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
      if (!response.ok) throw new Error('request-failed');
      const payload = await response.json();
      if (!payload.success || sequences[section] !== seq) throw new Error('invalid-response');
      return payload;
    } finally {
      if (controllers[section] === controller) {
        delete controllers[section];
        setBusy(panel, false);
      }
    }
  };

  const renderBars = (card, payload) => {
    const chart = card.querySelector('.technician-report-bar-chart');
    const wrap = card.querySelector('.technician-report-bar-chart-wrap');
    const summary = card.querySelector('.technician-report-chart-summary strong');
    const range = card.querySelector('.technician-report-chart-summary span');
    if (!chart || !wrap) return;
    const values = Array.isArray(payload.values) ? payload.values.map((value) => Number(value) || 0) : [];
    const labels = Array.isArray(payload.labels) ? payload.labels : [];
    const max = Math.max(1, ...values);
    chart.replaceChildren();
    chart.style.setProperty('--technician-bar-count', String(labels.length));
    chart.classList.toggle('is-empty', Number(payload.total || 0) === 0);
    wrap.classList.toggle('is-empty', Number(payload.total || 0) === 0);
    values.forEach((value, index) => {
      const item = document.createElement('div');
      item.className = 'technician-report-bar-item' + (value === 0 ? ' is-zero' : '');
      const stage = document.createElement('div');
      stage.className = 'technician-report-bar-stage';
      const fill = document.createElement('div');
      fill.className = 'technician-report-bar-fill';
      fill.style.height = value > 0 ? Math.max(8, (value / max) * 100) + '%' : '0%';
      if (value > 0) {
        const strong = document.createElement('strong');
        strong.textContent = formatNumber(value);
        fill.appendChild(strong);
      }
      stage.appendChild(fill);
      const label = document.createElement('span');
      label.textContent = labels[index] || '';
      item.append(stage, label);
      chart.appendChild(item);
    });
    let empty = wrap.querySelector('.technician-report-chart-empty');
    if (!empty) {
      empty = document.createElement('p');
      empty.className = 'technician-report-chart-empty';
      empty.textContent = 'ยังไม่มีข้อมูลในช่วงเวลานี้';
      wrap.appendChild(empty);
    }
    empty.hidden = Number(payload.total || 0) !== 0;
    if (summary) summary.textContent = formatNumber(payload.total) + ' งาน';
    if (range) range.textContent = payload.range_label || '';
  };

  page.querySelectorAll('[data-technician-period-form]').forEach((form) => {
    const field = form.querySelector('[data-technician-period-field]');
    const input = form.querySelector('[data-technician-period-input]');
    const options = Array.from(form.querySelectorAll('[data-technician-period-option]'));
    if (!field || !input || options.length === 0) return;
    const section = field.name.replace('_period', '');
    let active = field.value === 'month' ? 'month' : 'week';
    const values = { week: input.dataset.weekValue || '', month: input.dataset.monthValue || '' };
    const sync = () => {
      field.value = active;
      input.type = active === 'month' ? 'month' : 'week';
      input.value = values[active] || '';
      options.forEach((option) => {
        const selected = option.dataset.technicianPeriodOption === active;
        option.classList.toggle('is-active', selected);
        option.setAttribute('aria-pressed', selected ? 'true' : 'false');
      });
    };
    const load = () => {
      if (input.value) values[active] = input.value;
      sync();
      request(section, { [section + '_period']: active, [section + '_value']: values[active] || '' })
        .then((payload) => {
          renderBars(form.closest('.technician-report-chart-card'), payload);
          setUrl({ [section + '_period']: payload.period, [section + '_value']: payload.value });
        })
        .catch((error) => {
          if (error.name !== 'AbortError') showError(panelFor(section));
        });
    };
    options.forEach((option) => option.addEventListener('click', () => {
      const next = option.dataset.technicianPeriodOption === 'month' ? 'month' : 'week';
      if (next !== active) { active = next; load(); }
    }));
    input.addEventListener('change', load);
    form.addEventListener('submit', (event) => { event.preventDefault(); load(); });
    sync();
  });

  const trend = page.querySelector('.technician-report-trend-card');
  if (trend) {
    const renderTrend = (payload) => {
      const svg = trend.querySelector('.technician-report-line-chart');
      if (!svg) return;
      const values = (payload.values || []).map((value) => Number(value) || 0);
      const max = Math.max(1, ...values);
      const width = 820, height = 280, left = 52, right = 24, top = 24, bottom = 48;
      const plotWidth = width - left - right, plotHeight = height - top - bottom;
      const xs = values.map((_, index) => left + plotWidth * (index / 6));
      const ys = values.map((value) => top + plotHeight - (value / max) * plotHeight);
      const line = svg.querySelector('.technician-report-line');
      if (line) line.setAttribute('points', xs.map((x, i) => x.toFixed(2) + ',' + ys[i].toFixed(2)).join(' '));
      svg.querySelectorAll('.technician-report-grid-line').forEach((element, index) => {
        const ratio = index === 0 ? 0 : index === 1 ? .5 : 1;
        const y = top + plotHeight - plotHeight * ratio;
        element.setAttribute('y1', y); element.setAttribute('y2', y);
      });
      svg.querySelectorAll('.technician-report-y-label').forEach((element, index) => {
        const ratio = index === 0 ? 0 : index === 1 ? .5 : 1;
        element.setAttribute('y', top + plotHeight - plotHeight * ratio + 4);
        element.textContent = formatNumber(Math.round(max * ratio));
      });
      svg.querySelectorAll('.technician-report-point').forEach((element, index) => {
        element.setAttribute('cx', xs[index]); element.setAttribute('cy', ys[index]);
        const title = element.querySelector('title');
        if (title) title.textContent = (payload.labels || [])[index] + ': ' + formatNumber(values[index]) + ' งาน';
      });
      const range = trend.querySelector('.technician-report-week-controls span');
      if (range) range.textContent = (payload.start || '') + ' - ' + (payload.end || '');
      const previous = trend.querySelector('[data-technician-trend-arrow="previous"]');
      const next = trend.querySelector('[data-technician-trend-arrow="next"]');
      if (previous) previous.dataset.week = payload.previous_week || '';
      if (next) next.dataset.week = payload.next_week || '';
    };
    const loadTrend = (week) => request('trend', { trend_week: week })
      .then((payload) => { renderTrend(payload); setUrl({ trend_week: payload.week }); })
      .catch((error) => { if (error.name !== 'AbortError') showError(trend); });
    trend.querySelectorAll('[data-technician-trend-arrow]').forEach((arrow) => arrow.addEventListener('click', (event) => {
      event.preventDefault();
      if (arrow.dataset.week) loadTrend(arrow.dataset.week);
    }));
  }
})();
