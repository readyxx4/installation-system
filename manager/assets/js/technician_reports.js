(function () {
  'use strict';

  const page = document.querySelector('.manager-report-page');
  if (!page) return;
  const controllers = Object.create(null);
  const sequences = Object.create(null);
  const number = (value) => Number(value || 0).toLocaleString('en-US');
  const panelFor = (section) => section === 'trend'
    ? page.querySelector('[data-manager-weekly-trend]')
    : page.querySelector('.manager-technician-chart-grid');

  const setUrl = (params) => {
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([key, value]) => {
      if (value === '' || value == null) url.searchParams.delete(key);
      else url.searchParams.set(key, value);
    });
    url.searchParams.delete('ajax'); url.searchParams.delete('section');
    window.history.replaceState({}, '', url);
  };

  const busy = (panel, value) => {
    if (!panel) return;
    panel.classList.toggle('is-loading', value);
    panel.setAttribute('aria-busy', value ? 'true' : 'false');
    if (!value) panel.querySelector('.manager-report-async-error')?.remove();
  };

  const showError = (panel) => {
    if (!panel) return;
    panel.querySelector('.manager-report-async-error')?.remove();
    const message = document.createElement('p');
    message.className = 'manager-report-async-error';
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
    url.searchParams.set('ajax', '1'); url.searchParams.set('section', section);
    try {
      const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
      if (!response.ok) throw new Error('request-failed');
      const payload = await response.json();
      if (!payload.success || sequences[section] !== sequence) throw new Error('invalid-response');
      return payload;
    } finally {
      if (controllers[section] === controller) {
        delete controllers[section]; busy(panel, false);
      }
    }
  };

  const renderTechnicians = (payload) => {
    ['assigned', 'completed'].forEach((metric) => {
      const card = page.querySelector('[data-manager-technician-card="' + metric + '"]');
      if (!card) return;
      card.querySelector('.manager-technician-empty-state')?.remove();
      let bars = card.querySelector('.manager-technician-bars');
      if (!bars) {
        bars = document.createElement('div');
        bars.className = 'manager-technician-bars';
        bars.setAttribute('role', 'list');
        card.appendChild(bars);
      }
      bars.replaceChildren();
      const rows = Array.isArray(payload.rows) ? payload.rows : [];
      const max = Math.max(1, Number(payload[metric + '_max'] || 0));
      const total = Number(payload[metric + '_total'] || 0);
      const totalElement = card.querySelector('[data-manager-technician-total="' + metric + '"]');
      if (totalElement) totalElement.textContent = number(total) + ' งาน';
      if (total === 0) {
        bars.remove();
        const empty = document.createElement('div');
        empty.className = 'report-empty-state manager-technician-empty-state';
        empty.textContent = 'ไม่พบข้อมูลในช่วงเวลาที่เลือก';
        card.appendChild(empty);
        return;
      }
      rows.forEach((item) => {
        const value = Number(item[metric] || 0);
        const row = document.createElement('div');
        row.className = 'manager-technician-bar-row' + (value === 0 ? ' is-zero' : '');
        row.setAttribute('role', 'listitem');
        row.title = (item.technician_name || '-') + ': ' + number(value) + ' งาน';
        const label = document.createElement('div'); label.className = 'manager-technician-bar-label';
        const name = document.createElement('span'); name.textContent = item.technician_name || '-';
        const amount = document.createElement('strong'); amount.textContent = number(value) + ' งาน';
        label.append(name, amount);
        const track = document.createElement('div'); track.className = 'manager-technician-bar-track';
        const fill = document.createElement('span'); fill.style.width = (value / max * 100) + '%';
        track.appendChild(fill); row.append(label, track); bars.appendChild(row);
      });
    });
  };

  const form = page.querySelector('[data-manager-technician-period-form]');
  const input = page.querySelector('[data-manager-technician-period-input]');
  const periodField = page.querySelector('[data-manager-technician-period]');
  const options = Array.from(page.querySelectorAll('[data-manager-technician-period-option]'));
  if (form && input && periodField && options.length) {
    let active = periodField.value === 'week' || periodField.value === 'all' ? periodField.value : 'month';
    const values = { week: input.dataset.weekValue || '', month: input.dataset.monthValue || '' };
    const sync = () => {
      const all = active === 'all';
      input.disabled = all; input.hidden = all; periodField.value = active;
      if (!all) { input.type = active === 'week' ? 'week' : 'month'; input.value = values[active] || ''; }
      options.forEach((option) => {
        const selected = option.dataset.managerTechnicianPeriodOption === active;
        option.classList.toggle('is-active', selected); option.setAttribute('aria-pressed', selected ? 'true' : 'false');
      });
    };
    const load = () => {
      if (!input.disabled && input.value) values[active] = input.value;
      sync();
      request('technician', { tech_filter_period: active, tech_filter_value: active === 'all' ? '' : values[active] || '' })
        .then((payload) => {
          renderTechnicians(payload);
          setUrl({ tech_filter_period: payload.period, tech_filter_value: payload.period === 'all' ? '' : (payload.period === 'week' ? payload.week : payload.month) });
        })
        .catch((error) => { if (error.name !== 'AbortError') showError(panelFor('technician')); });
    };
    options.forEach((option) => option.addEventListener('click', () => {
      const next = option.dataset.managerTechnicianPeriodOption || 'month';
      if (next !== active) { active = next; load(); }
    }));
    input.addEventListener('change', load);
    form.addEventListener('submit', (event) => { event.preventDefault(); load(); });
    sync();
  }

  const trend = page.querySelector('[data-manager-weekly-trend]');
  if (trend) {
    const renderTrend = (payload) => {
      const svg = trend.querySelector('.manager-weekly-trend-chart');
      if (!svg) return;
      const values = (payload.values || []).map((value) => Number(value) || 0);
      const max = Math.max(1, ...values);
      const width = 760, height = 238, left = 44, right = 18, top = 24, bottom = 46;
      const plotWidth = width - left - right, plotHeight = height - top - bottom;
      const xs = values.map((_, index) => left + plotWidth * (index / 6));
      const ys = values.map((value) => top + plotHeight - (value / max) * plotHeight);
      const line = svg.querySelector('.manager-weekly-trend-line');
      if (line) line.setAttribute('points', xs.map((x, i) => x.toFixed(2) + ',' + ys[i].toFixed(2)).join(' '));
      svg.querySelectorAll('.manager-weekly-trend-grid-line').forEach((element, index) => {
        const ratio = index === 0 ? 0 : index === 1 ? .5 : 1;
        const y = top + plotHeight - plotHeight * ratio; element.setAttribute('y1', y); element.setAttribute('y2', y);
      });
      svg.querySelectorAll('.manager-weekly-trend-y-label').forEach((element, index) => {
        const ratio = index === 0 ? 0 : index === 1 ? .5 : 1;
        element.setAttribute('y', top + plotHeight - plotHeight * ratio + 4); element.textContent = number(Math.round(max * ratio));
      });
      svg.querySelectorAll('.manager-weekly-trend-point').forEach((element, index) => {
        element.setAttribute('cx', xs[index]); element.setAttribute('cy', ys[index]);
        const title = element.querySelector('title'); if (title) title.textContent = (payload.labels || [])[index] + ': ' + number(values[index]) + ' งาน';
      });
      const range = trend.querySelector('.manager-weekly-trend-controls span');
      if (range) range.textContent = (payload.start || '') + ' - ' + (payload.end || '');
      trend.querySelector('[data-manager-weekly-trend-arrow="previous"]')?.setAttribute('data-week', payload.previous_week || '');
      trend.querySelector('[data-manager-weekly-trend-arrow="next"]')?.setAttribute('data-week', payload.next_week || '');
    };
    const load = (week) => request('trend', { tech_trend_week: week })
      .then((payload) => { renderTrend(payload); setUrl({ tech_trend_week: payload.week }); })
      .catch((error) => { if (error.name !== 'AbortError') showError(trend); });
    trend.querySelectorAll('[data-manager-weekly-trend-arrow]').forEach((arrow) => arrow.addEventListener('click', (event) => {
      event.preventDefault(); if (arrow.dataset.week) load(arrow.dataset.week);
    }));
  }
})();
