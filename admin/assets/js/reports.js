(function () {
  'use strict';

  const dataElement = document.getElementById('report-period-data');
  const card = document.querySelector('[data-completed-install-card]');
  if (!dataElement || !card) return;

  let reportData;
  try {
    reportData = JSON.parse(dataElement.textContent || '{}');
  } catch (error) {
    return;
  }

  const completedData = reportData && reportData.completed ? reportData.completed : {};
  const chart = card.querySelector('[data-completed-chart]');
  const summary = card.querySelector('[data-completed-summary]');
  const dateControl = card.querySelector('[data-completed-date-control]');
  const dateInput = card.querySelector('[data-completed-date]');
  const dateDisplayInput = card.querySelector('[data-completed-date-display]');
  const dateTrigger = card.querySelector('[data-completed-date-trigger]');
  const weekControl = card.querySelector('[data-completed-week-control]');
  const monthControl = card.querySelector('[data-completed-month-control]');
  const weekInput = card.querySelector('[data-completed-week]');
  const monthInput = card.querySelector('[data-completed-month]');
  const resetButton = card.querySelector('[data-completed-reset]');
  const tabs = Array.from(card.querySelectorAll('[data-completed-period]'));
  let activePeriod = 'day';

  const storageKeys = {
    scope: 'adminCompletedInstallReport.session',
    period: 'adminCompletedInstallReport.period',
    day: 'adminCompletedInstallReport.day',
    week: 'adminCompletedInstallReport.week',
    month: 'adminCompletedInstallReport.month',
  };
  const sessionKey = String(reportData.sessionKey || '');
  let storage = null;
  try {
    storage = window.sessionStorage;
  } catch (error) {
    storage = null;
  }

  const storageGet = (key) => {
    if (!storage) return '';
    try {
      return storage.getItem(key) || '';
    } catch (error) {
      return '';
    }
  };

  const storageSet = (key, value) => {
    if (!storage) return;
    try {
      storage.setItem(key, value);
    } catch (error) {
      // Storage can be unavailable in privacy-restricted browser contexts.
    }
  };

  const storageRemove = (key) => {
    if (!storage) return;
    try {
      storage.removeItem(key);
    } catch (error) {
      // Storage can be unavailable in privacy-restricted browser contexts.
    }
  };

  if (storage && sessionKey && storageGet(storageKeys.scope) !== sessionKey) {
    [storageKeys.period, storageKeys.day, storageKeys.week, storageKeys.month].forEach(storageRemove);
    storageSet(storageKeys.scope, sessionKey);
  }

  const numberFormat = (value) => (Number(value) || 0).toLocaleString('en-US');

  const formatDisplayDate = (isoDate) => {
    const match = /^([0-9]{4})-([0-9]{2})-([0-9]{2})$/.exec(String(isoDate || ''));
    return match ? `${match[3]}/${match[2]}/${match[1]}` : '-';
  };

  const parseDisplayDate = (displayDate) => {
    const match = /^([0-9]{1,2})\/([0-9]{1,2})\/([0-9]{4})$/.exec(String(displayDate || '').trim());
    if (!match) return '';

    const day = match[1].padStart(2, '0');
    const month = match[2].padStart(2, '0');
    const year = match[3];
    const isoDate = `${year}-${month}-${day}`;
    const parsed = new Date(`${isoDate}T00:00:00Z`);
    if (
      Number.isNaN(parsed.getTime())
      || parsed.getUTCFullYear() !== Number(year)
      || parsed.getUTCMonth() + 1 !== Number(month)
      || parsed.getUTCDate() !== Number(day)
    ) {
      return '';
    }

    return isoDate;
  };

  const addDays = (isoDate, days) => {
    const date = new Date(`${isoDate}T00:00:00Z`);
    if (Number.isNaN(date.getTime())) return '';
    date.setUTCDate(date.getUTCDate() + days);
    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-${String(date.getUTCDate()).padStart(2, '0')}`;
  };

  const weekToIsoDate = (weekValue) => {
    const match = /^(\d{4})-W(\d{2})$/.exec(String(weekValue || ''));
    if (!match) return '';
    const year = Number(match[1]);
    const week = Number(match[2]);
    if (week < 1 || week > 53) return '';

    const januaryFourth = new Date(Date.UTC(year, 0, 4));
    const isoDay = januaryFourth.getUTCDay() || 7;
    januaryFourth.setUTCDate(januaryFourth.getUTCDate() - isoDay + 1 + ((week - 1) * 7));
    return `${januaryFourth.getUTCFullYear()}-${String(januaryFourth.getUTCMonth() + 1).padStart(2, '0')}-${String(januaryFourth.getUTCDate()).padStart(2, '0')}`;
  };

  const formatMonthDisplay = (monthValue) => {
    const match = /^(\d{4})-(\d{2})$/.exec(String(monthValue || ''));
    return match ? `${match[2]}/${match[1]}` : '-';
  };

  const defaultSelection = {
    day: dateInput && dateInput.value ? dateInput.value : (completedData.day && completedData.day.date) || '',
    week: weekInput && weekInput.value ? weekInput.value : '',
    month: monthInput && monthInput.value ? monthInput.value : '',
  };

  const isInInputRange = (value, input, pattern) => {
    if (!input || !pattern.test(String(value || ''))) return false;
    return (!input.min || value >= input.min) && (!input.max || value <= input.max);
  };

  const restoreSelection = () => {
    const storedPeriod = storageGet(storageKeys.period);
    if (['day', 'week', 'month'].includes(storedPeriod)) {
      activePeriod = storedPeriod;
    }

    const storedDay = storageGet(storageKeys.day);
    if (dateInput && isInInputRange(storedDay, dateInput, /^\d{4}-\d{2}-\d{2}$/)) {
      dateInput.value = storedDay;
    } else if (dateInput) {
      dateInput.value = defaultSelection.day;
    }

    const storedWeek = storageGet(storageKeys.week);
    if (weekInput && isInInputRange(storedWeek, weekInput, /^\d{4}-W\d{2}$/)) {
      weekInput.value = storedWeek;
    } else if (weekInput) {
      weekInput.value = defaultSelection.week;
    }

    const storedMonth = storageGet(storageKeys.month);
    if (monthInput && isInInputRange(storedMonth, monthInput, /^\d{4}-\d{2}$/)) {
      monthInput.value = storedMonth;
    } else if (monthInput) {
      monthInput.value = defaultSelection.month;
    }

    if (dateDisplayInput && dateInput) {
      dateDisplayInput.value = formatDisplayDate(dateInput.value);
    }
  };

  const persistSelection = () => {
    storageSet(storageKeys.scope, sessionKey);
    storageSet(storageKeys.period, activePeriod);
    if (dateInput && dateInput.value) storageSet(storageKeys.day, dateInput.value);
    if (weekInput && weekInput.value) storageSet(storageKeys.week, weekInput.value);
    if (monthInput && monthInput.value) storageSet(storageKeys.month, monthInput.value);
  };

  const clearStoredSelection = () => {
    [storageKeys.period, storageKeys.day, storageKeys.week, storageKeys.month].forEach(storageRemove);
    storageSet(storageKeys.scope, sessionKey);
  };

  restoreSelection();

  const renderBars = (labels, values, period) => {
    chart.replaceChildren();
    chart.dataset.period = period;

    const safeLabels = Array.isArray(labels) ? labels : [];
    const safeValues = Array.isArray(values) ? values.map((value) => Math.max(0, Number(value) || 0)) : [];
    if (safeLabels.length === 0 || safeValues.length === 0) {
      return 0;
    }

    const total = safeValues.reduce((sum, value) => sum + value, 0);

    const maxValue = safeValues.reduce((max, value) => Math.max(max, value), 0);
    const bars = document.createElement('div');
    bars.className = 'completed-install-bars';
    bars.style.setProperty('--completed-bar-count', String(safeLabels.length));

    safeLabels.forEach((labelText, index) => {
      const item = document.createElement('div');
      item.className = 'completed-install-bar-item';

      const stage = document.createElement('div');
      stage.className = 'completed-install-bar-stage';
      const track = document.createElement('div');
      track.className = 'completed-install-bar-track';
      const fill = document.createElement('span');
      fill.className = 'completed-install-bar-fill';
      const value = safeValues[index] || 0;
      item.classList.toggle('is-zero', value === 0);
      const heightPercent = maxValue > 0 ? (value / maxValue) * 100 : 0;
      const visualHeight = value > 0 ? Math.max(8, Math.min(100, heightPercent)) : 0;
      fill.style.setProperty('--completed-bar-height', `${visualHeight}%`);

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
      label.textContent = labelText || '-';
      item.append(stage, label);
      bars.appendChild(item);
    });

    chart.appendChild(bars);
    return total;
  };

  const render = () => {
    const periodData = completedData[activePeriod] || {};
    let labels = periodData.labels || [];
    let values = periodData.values || [];
    let summaryText = '';

    if (activePeriod === 'day') {
      const selectedDate = dateInput && dateInput.value ? dateInput.value : periodData.date;
      values = periodData.dates && Array.isArray(periodData.dates[selectedDate])
        ? periodData.dates[selectedDate]
        : [0, 0, 0];
      summaryText = `วันที่ ${formatDisplayDate(selectedDate)} เสร็จแล้ว`;
      if (dateControl) dateControl.hidden = false;
      if (weekControl) weekControl.hidden = true;
      if (monthControl) monthControl.hidden = true;
    } else if (activePeriod === 'week') {
      const selectedWeek = weekInput && weekInput.value
        ? weekInput.value
        : periodData.weekStart;
      const weekStart = weekToIsoDate(selectedWeek) || periodData.weekStart || '';
      values = periodData.weeks && Array.isArray(periodData.weeks[weekStart])
        ? periodData.weeks[weekStart]
        : [0, 0, 0, 0, 0, 0, 0];
      const weekEnd = addDays(weekStart, 6);
      summaryText = `สัปดาห์ ${formatDisplayDate(weekStart)} - ${formatDisplayDate(weekEnd)} เสร็จแล้ว`;
      if (dateControl) dateControl.hidden = true;
      if (weekControl) weekControl.hidden = false;
      if (monthControl) monthControl.hidden = true;
    } else {
      const selectedMonth = monthInput && monthInput.value
        ? monthInput.value
        : `${periodData.year || ''}-01`;
      const selectedYear = selectedMonth.slice(0, 4);
      values = periodData.years && Array.isArray(periodData.years[selectedYear])
        ? periodData.years[selectedYear]
        : Array(12).fill(0);
      summaryText = `เดือน ${formatMonthDisplay(selectedMonth)} เสร็จแล้ว`;
      if (dateControl) dateControl.hidden = true;
      if (weekControl) weekControl.hidden = true;
      if (monthControl) monthControl.hidden = false;
    }

    const total = renderBars(labels, values, activePeriod);
    if (summary) summary.textContent = `${summaryText} ${numberFormat(total)} งาน`;
    tabs.forEach((tab) => {
      const isActive = tab.dataset.completedPeriod === activePeriod;
      tab.classList.toggle('is-active', isActive);
      tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  };

  tabs.forEach((tab) => {
    tab.addEventListener('click', (event) => {
      event.preventDefault();
      const nextPeriod = tab.dataset.completedPeriod;
      if (!nextPeriod || !completedData[nextPeriod]) return;
      activePeriod = nextPeriod;
      persistSelection();
      render();
    });
  });

  if (dateInput) {
    dateInput.addEventListener('change', () => {
      if (dateDisplayInput) dateDisplayInput.value = formatDisplayDate(dateInput.value);
      persistSelection();
      if (activePeriod === 'day') render();
    });
  }

  if (weekInput) {
    weekInput.addEventListener('change', () => {
      persistSelection();
      if (activePeriod === 'week') render();
    });
  }

  if (monthInput) {
    monthInput.addEventListener('change', () => {
      persistSelection();
      if (activePeriod === 'month') render();
    });
  }

  if (resetButton) {
    resetButton.addEventListener('click', () => {
      clearStoredSelection();
      activePeriod = 'day';
      if (dateInput) dateInput.value = defaultSelection.day;
      if (weekInput) weekInput.value = defaultSelection.week;
      if (monthInput) monthInput.value = defaultSelection.month;
      if (dateDisplayInput && dateInput) dateDisplayInput.value = formatDisplayDate(dateInput.value);
      persistSelection();
      render();
    });
  }

  if (dateTrigger && dateInput) {
    dateTrigger.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      try {
        if (typeof dateInput.showPicker === 'function') {
          dateInput.showPicker();
          return;
        }
      } catch (error) {
        // Fall back to the native date control when showPicker is unavailable.
      }

      dateInput.focus({ preventScroll: true });
      dateInput.click();
    });
  }

  if (dateDisplayInput && dateInput) {
    dateDisplayInput.addEventListener('input', () => {
      dateDisplayInput.setCustomValidity('');
    });

    dateDisplayInput.addEventListener('change', () => {
      const nextIsoDate = parseDisplayDate(dateDisplayInput.value);
      const isInRange = nextIsoDate !== ''
        && (!dateInput.min || nextIsoDate >= dateInput.min)
        && (!dateInput.max || nextIsoDate <= dateInput.max);

      if (!isInRange) {
        dateDisplayInput.setCustomValidity('กรุณาระบุวันที่เป็น วว/ดด/ปปปป และอยู่ในช่วงวันที่ที่เลือกได้');
        dateDisplayInput.value = formatDisplayDate(dateInput.value);
        return;
      }

      dateDisplayInput.setCustomValidity('');
      dateInput.value = nextIsoDate;
      dateDisplayInput.value = formatDisplayDate(nextIsoDate);
      persistSelection();
      if (activePeriod === 'day') render();
    });

    dateDisplayInput.value = formatDisplayDate(dateInput.value);
  }

  persistSelection();
  render();
})();
