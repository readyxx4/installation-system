(function () {
  'use strict';

  const root = document.querySelector('.admin-report-page');
  if (!root) return;

  const updatePrintDate = () => {
    root.querySelectorAll('[data-report-print-date]').forEach((node) => {
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

  const controllers = Object.create(null);
  const number = (value, decimals = 0) => Number(value || 0).toLocaleString('en-US', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  });

  const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const showMessage = (form, message = '') => {
    form.querySelector('[data-filter-message]')?.remove();
    if (!message) return;
    const node = document.createElement('p');
    node.className = 'report-filter-message';
    node.dataset.filterMessage = '';
    node.setAttribute('role', 'alert');
    node.textContent = message;
    form.appendChild(node);
  };

  const normalizeFilterCopy = () => {
    const statusLabels = {
      unassigned: 'ยังไม่ได้มอบหมาย',
      assigned: 'รอช่างรับงาน',
      accepted: 'ช่างรับงานแล้ว',
      received: 'ยืนยันรับสินค้าแล้ว',
      working: 'กำลังติดตั้ง',
      review: 'รอหัวหน้าช่างยืนยัน',
      done: 'เสร็จสิ้น',
      rejected: 'ช่างปฏิเสธงาน',
      cancelled: 'ยกเลิกแล้ว',
      in_progress: 'กำลังดำเนินการ'
    };
    root.querySelectorAll('[data-report-filter-form]:not([data-filter-disabled])').forEach((form) => {
      const section = form.dataset.filterSection;
      const search = form.querySelector('input[type="search"]');
      const searchLabel = form.querySelector('.report-filter-search .sr-only');
      const submitLabel = form.querySelector('.report-filter-submit span');
      const resetLabel = form.querySelector('.report-filter-reset');
      const selectLabel = form.querySelector('.report-filter-select .sr-only');
      const allOption = form.querySelector('select[name="status"] option[value=""]');
      if (section === 'overview-latest') {
        if (search) search.placeholder = 'ค้นหาใบงานล่าสุดจากรหัสหรือชื่อลูกค้า';
        if (searchLabel) searchLabel.textContent = 'ค้นหาใบงาน';
        if (selectLabel) selectLabel.textContent = 'กรองตามสถานะ';
        if (allOption) allOption.textContent = 'สถานะทั้งหมด';
      } else if (section === 'installation-latest') {
        if (search) search.placeholder = 'ค้นหารหัสใบงาน ลูกค้า พนักงานขาย หรือช่าง';
        if (searchLabel) searchLabel.textContent = 'ค้นหาใบงาน';
        if (selectLabel) selectLabel.textContent = 'กรองตามสถานะ';
        if (allOption) allOption.textContent = 'ทุกสถานะ';
      } else if (section === 'personnel') {
        if (search) search.placeholder = 'ค้นหารหัสหรือชื่อบุคลากร/ช่าง';
        if (searchLabel) searchLabel.textContent = 'ค้นหาบุคลากร';
        if (selectLabel) selectLabel.textContent = 'กรองตามประเภท';
      } else if (section === 'revenue') {
        if (selectLabel) selectLabel.textContent = 'กรองตามสถานะ';
        if (allOption) allOption.textContent = 'สถานะทั้งหมด';
      }
      form.querySelectorAll('select[name="status"] option').forEach((option) => {
        if (Object.prototype.hasOwnProperty.call(statusLabels, option.value)) {
          option.textContent = statusLabels[option.value];
        }
      });
      if (submitLabel) submitLabel.textContent = 'กรอง';
      if (resetLabel) resetLabel.textContent = 'ล้างตัวกรอง';
    });
  };

  const setUrlState = (section, params) => {
    const url = new URL(window.location.href);
    const keys = section === 'overview-latest'
      ? ['search', 'status', 'personnel_type', 'limit']
      : section === 'personnel'
      ? ['personnel_search', 'personnel_type']
      : section === 'revenue'
        ? ['status', 'fee_year']
        : ['search', 'status'];

    keys.forEach((key) => {
      const value = params[key] ?? '';
      if (value === '') url.searchParams.delete(key);
      else url.searchParams.set(key, value);
    });
    url.searchParams.delete('ajax');
    url.searchParams.delete('section');
    window.history.replaceState({}, '', url);
  };

  const readForm = (form) => Object.fromEntries(new FormData(form).entries());

  const applyOverviewPersonnelFilter = (form) => {
    if (form.dataset.filterSection !== 'overview-latest') return;

    const type = form.querySelector('select[name="personnel_type"]')?.value || 'all';
    const tableGrid = root.querySelector('[data-overview-personnel-tables]');
    if (!tableGrid) return;

    tableGrid.dataset.personnelType = type;
    tableGrid.classList.toggle('report-personnel-table-grid--single', type !== 'all');
    tableGrid.querySelectorAll('[data-personnel-table-type]').forEach((table) => {
      table.hidden = type !== 'all' && table.dataset.personnelTableType !== type;
    });
  };

  const showAllOverviewPersonnelTablesForPrint = () => {
    const tableGrid = root.querySelector('[data-overview-personnel-tables]');
    if (!tableGrid) return;

    tableGrid.dataset.personnelType = 'all';
    tableGrid.classList.remove('report-personnel-table-grid--single');
    tableGrid.querySelectorAll('[data-personnel-table-type]').forEach((table) => {
      table.hidden = false;
    });
  };

  const panelForForm = (form) => {
    const section = form.dataset.filterSection;
    if (section === 'installation-latest') {
      return root.querySelector('[data-filter-target="installation-latest"]');
    }
    if (section === 'overview-latest') {
      return root.querySelector('[data-filter-target="overview-latest"]');
    }
    if (section === 'personnel') {
      return root.querySelector('[data-personnel-tables]');
    }
    if (section === 'revenue') {
      return root.querySelector('[data-filter-target="revenue-status"]');
    }
    return form.closest('.report-table-panel');
  };

  const panelsForForm = (form) => {
    const panels = [];
    const primary = panelForForm(form);
    if (primary) panels.push(primary);
    if (form.dataset.filterSection === 'installation-latest') {
      const summary = root.querySelector('[data-installation-summary]');
      if (summary) panels.push(summary);
    }
    if (form.dataset.filterSection === 'personnel') {
      const summary = root.querySelector('[data-personnel-summary]');
      if (summary) panels.push(summary);
    }
    if (form.dataset.filterSection === 'revenue') {
      const summary = root.querySelector('.report-revenue-summary-grid');
      const monthlyPanel = root.querySelector('[data-filter-target="revenue-monthly"]');
      if (summary) panels.push(summary);
      if (monthlyPanel) panels.push(monthlyPanel);
    }
    return panels;
  };

  const request = async (form, params) => {
    const section = form.dataset.filterSection;
    controllers[section]?.abort();
    const controller = new AbortController();
    controllers[section] = controller;
    const url = new URL(window.location.href);
    url.searchParams.set('ajax', '1');
    url.searchParams.set('section', section);
    Object.entries(params).forEach(([name, value]) => {
      if (value === '') url.searchParams.delete(name);
      else url.searchParams.set(name, value);
    });

    const panels = panelsForForm(form);
    panels.forEach((panel) => panel.classList.add('is-filter-loading'));
    form.setAttribute('aria-busy', 'true');
    showMessage(form);

    try {
      const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        signal: controller.signal
      });
      if (!response.ok) throw new Error('request-failed');
      const payload = await response.json();
      if (!payload.success || payload.section !== section) throw new Error('invalid-response');
      return payload;
    } finally {
      if (controllers[section] === controller) {
        delete controllers[section];
        panels.forEach((panel) => panel.classList.remove('is-filter-loading'));
        form.removeAttribute('aria-busy');
      }
    }
  };

  const renderEmpty = (colspan, message) => (
    '<tr><td colspan="' + colspan + '"><div class="report-empty-state">' +
      '<i class="fa-regular fa-folder-open" aria-hidden="true"></i>' +
      '<span>' + escapeHtml(message) + '</span>' +
    '</div></td></tr>'
  );

  const renderLatest = (form, payload) => {
    const table = panelForForm(form)?.querySelector('table');
    const body = table?.querySelector('tbody');
    if (!body) return;
    const installation = form.dataset.filterSection === 'installation-latest';
    const rows = Array.isArray(payload.rows) ? payload.rows : [];
    if (!rows.length) {
      body.innerHTML = renderEmpty(installation ? 7 : 6, 'ไม่พบข้อมูลที่ตรงกับตัวกรอง');
    } else {
      body.innerHTML = rows.map((row) => {
        const status = row.status || {};
        const statusClass = 'tone-' + escapeHtml(status.tone || 'blue');
        const statusIcon = escapeHtml(status.icon || 'fa-circle-question');
        const statusLabel = escapeHtml(status.label || 'ไม่ระบุสถานะ');
        const common = [
          '<td><strong>' + escapeHtml(row.setup_id || '-') + '</strong></td>',
          '<td>' + escapeHtml(row.customer_name || '-') + '</td>',
          '<td>' + escapeHtml(row.seller_name || '-') + '</td>',
          '<td>' + escapeHtml(row.technician_name || '-') + '</td>'
        ];
        if (installation) {
          common.push('<td>' + escapeHtml(row.created_display || '-') + '</td>');
        }
        common.push('<td>' + escapeHtml(row.install_display || '-') + '</td>');
        common.push(
          '<td><span class="report-status-label"><i class="fa-solid ' + statusIcon + ' ' + statusClass +
          '" aria-hidden="true"></i>' + statusLabel + '</span></td>'
        );
        return '<tr>' + common.join('') + '</tr>';
      }).join('');
    }
    const result = panelForForm(form)?.querySelector('[data-result-value]');
    if (result) result.textContent = number(payload.count) + ' รายการ';
  };

  const renderOverviewPersonnelMetric = (payload) => {
    const metric = payload.overview_personnel_metric || {};
    if (!Object.prototype.hasOwnProperty.call(metric, 'label') || !Object.prototype.hasOwnProperty.call(metric, 'value')) return;

    const label = root.querySelector('[data-overview-personnel-metric-label]');
    const value = root.querySelector('[data-overview-personnel-metric]');
    if (label) label.textContent = metric.label;
    if (value) value.textContent = number(metric.value);
  };

  const renderOverviewDatasetMetrics = (form, payload) => {
    const metrics = payload.overview_dataset_metrics || {};
    root.querySelectorAll('[data-overview-metric]').forEach((node) => {
      const key = node.dataset.overviewMetric;
      if (key && Object.prototype.hasOwnProperty.call(metrics, key)) {
        node.textContent = number(metrics[key]);
      }
    });

    const description = panelForForm(form)?.querySelector('.report-panel-head p');
    if (description && typeof payload.overview_limit_description === 'string') {
      description.textContent = payload.overview_limit_description;
    }
  };

  const renderInstallation = (form, payload) => {
    renderLatest(form, payload);

    const metrics = payload.metrics || {};
    root.querySelectorAll('[data-installation-metric]').forEach((node) => {
        const key = node.dataset.installationMetric;
        if (key && Object.prototype.hasOwnProperty.call(metrics, key)) {
        node.textContent = number(metrics[key]);
        }
    });

    const metricLabels = payload.metric_labels || {};
    root.querySelectorAll('[data-installation-metric-label]').forEach((node) => {
      const key = node.dataset.installationMetricLabel;
      if (key && Object.prototype.hasOwnProperty.call(metricLabels, key)) {
        node.textContent = metricLabels[key];
        node.classList.toggle(
          'is-installation-received-label',
          key === 'in_progress' && metricLabels[key] === 'ยืนยันรับสินค้าแล้ว'
        );
      }
    });

  };

  const renderPersonnel = (form, payload) => {
    const type = String(payload.type || 'sale');
    const summaryCards = Array.isArray(payload.summary_cards) ? payload.summary_cards : [];
    const summary = root.querySelector('[data-personnel-summary]');
    if (summary) {
      summary.dataset.personnelType = type;
      summary.innerHTML = summaryCards.map((card) =>
        '<article class="report-metric-card">' +
          '<div class="report-metric-icon tone-' + escapeHtml(card.tone || 'blue') + '">' +
            '<i class="fa-solid ' + escapeHtml(card.icon || 'fa-chart-simple') + '" aria-hidden="true"></i>' +
          '</div>' +
          '<div class="report-metric-copy">' +
            '<span>' + escapeHtml(card.label || '-') + '</span>' +
            '<strong>' + number(card.value) + '</strong>' +
          '</div>' +
        '</article>'
      ).join('');
    }

    const tables = Array.isArray(payload.tables) ? payload.tables : [];
    const tablesPanel = root.querySelector('[data-personnel-tables]');
    if (!tablesPanel) return;

    tablesPanel.dataset.personnelType = type;
    tablesPanel.classList.toggle('report-personnel-table-grid--single', tables.length === 1);
    tablesPanel.innerHTML = tables.map((table) => {
      const columns = Array.isArray(table.columns) ? table.columns : [];
      const rows = Array.isArray(table.rows) ? table.rows : [];
      const tableType = String(table.type || type);
      const columnCount = columns.length || 1;
      const tableRows = rows.length ? rows.map((row) => {
        const stats = Array.isArray(row.stats) ? row.stats : [];
        const cells = [
          '<td><strong>' + escapeHtml(row.id || '-') + '</strong></td>',
          '<td><span class="report-status-label"><i class="fa-solid ' + escapeHtml(table.row_icon || 'fa-user') +
            ' tone-' + escapeHtml(table.row_tone || 'blue') + '" aria-hidden="true"></i>' +
            escapeHtml(row.name || '-') + '</span></td>'
        ];
        if (tableType === 'technician') {
          const ready = Boolean(row.ready);
          cells.push('<td><span class="report-readiness-badge ' + (ready ? 'is-ready' : 'is-busy') + '">' +
            (ready ? 'พร้อมรับงาน' : 'ไม่พร้อมรับงาน') + '</span></td>');
        }
        stats.forEach((stat) => {
          cells.push('<td class="report-number-cell">' + number(stat) + ' งาน</td>');
        });
        return '<tr>' + cells.join('') + '</tr>';
      }).join('') : renderEmpty(columnCount, table.empty_message || 'ไม่พบข้อมูลที่ตรงกับตัวกรอง');

      return '<article class="report-panel report-table-panel" data-personnel-table-type="' + escapeHtml(tableType) +
        '" aria-labelledby="personnel-table-' + escapeHtml(tableType) + '-title">' +
        '<div class="report-panel-head">' +
          '<div>' +
            '<h2 id="personnel-table-' + escapeHtml(tableType) + '-title">' + escapeHtml(table.title || 'สรุปบุคลากร') + '</h2>' +
            '<p>' + escapeHtml(table.description || '') + '</p>' +
          '</div>' +
        '</div>' +
        '<div class="report-table-wrap">' +
          '<table class="report-table personnel-table">' +
            '<thead><tr>' + columns.map((column) => '<th scope="col">' + escapeHtml(column) + '</th>').join('') + '</tr></thead>' +
            '<tbody>' + tableRows + '</tbody>' +
          '</table>' +
        '</div>' +
      '</article>';
    }).join('');
  };

  const renderRevenueUnified = (form, payload) => {
    const metrics = payload.metrics || {};
    root.querySelectorAll('[data-revenue-metric]').forEach((node) => {
      const key = node.dataset.revenueMetric;
      if (key && Object.prototype.hasOwnProperty.call(metrics, key)) {
        node.textContent = number(metrics[key], 2) + ' ฿';
      }
    });
    root.querySelectorAll('[data-revenue-result-count]').forEach((node) => {
      node.textContent = number(payload.count) + ' รายการ';
    });

    const statusPanel = root.querySelector('[data-filter-target="revenue-status"]');
    const statusBody = statusPanel?.querySelector('tbody');
    const statusRows = Array.isArray(payload.status_rows) ? payload.status_rows : [];
    if (statusBody) {
      statusBody.innerHTML = statusRows.length ? statusRows.map((row) => '<tr>' +
        '<td><span class="report-status-label"><i class="fa-solid ' + escapeHtml(row.icon || 'fa-circle') + ' tone-' + escapeHtml(row.tone || 'blue') + '" aria-hidden="true"></i>' + escapeHtml(row.label || '-') + '</span></td>' +
        '<td class="report-number-cell">' + number(row.total) + ' งาน</td>' +
        '<td class="report-money-cell">' + number(row.fee_total, 2) + ' ฿</td>' +
      '</tr>').join('') : renderEmpty(3, 'ไม่พบข้อมูลที่ตรงกับตัวกรอง');
    }

    const monthlyPanel = root.querySelector('[data-filter-target="revenue-monthly"]');
    const monthlyBody = monthlyPanel?.querySelector('tbody');
    const labels = Array.isArray(payload.labels) ? payload.labels : [];
    const monthly = Array.isArray(payload.monthly) ? payload.monthly : [];
    if (monthlyBody) {
      monthlyBody.innerHTML = labels.map((label, index) => {
        const item = monthly[index] || { jobs: 0, fee: 0 };
        const jobs = Number(item.jobs || 0);
        const fee = Number(item.fee || 0);
        return '<tr>' +
          '<td>' + escapeHtml(label) + '</td>' +
          '<td class="report-number-cell">' + number(jobs) + ' งาน</td>' +
          '<td class="report-money-cell">' + number(fee, 2) + ' ฿</td>' +
          '<td class="report-money-cell">' + number(jobs ? fee / jobs : 0, 2) + ' ฿</td>' +
        '</tr>';
      }).join('');
    }
    monthlyPanel?.querySelector('.report-panel-head p')?.replaceChildren(
      document.createTextNode('งานเสร็จสิ้นในปี ' + String(payload.year || ''))
    );
  };

  const renderTechnicians = (form, payload) => {
    const body = form.closest('.report-table-panel')?.querySelector('tbody');
    if (!body) return;
    const rows = Array.isArray(payload.rows) ? payload.rows : [];
    if (!rows.length) {
      body.innerHTML = renderEmpty(5, 'ไม่พบข้อมูลที่ตรงกับตัวกรอง');
    } else {
      body.innerHTML = rows.map((row) => {
        const ready = Number(row.tech_status) === 0;
        return '<tr>' +
          '<td><strong>' + escapeHtml(row.tech_id || '-') + '</strong></td>' +
          '<td><span class="report-status-label"><i class="fa-solid fa-screwdriver-wrench tone-green" aria-hidden="true"></i>' +
            escapeHtml(row.tech_display_name || '-') + '</span></td>' +
          '<td><span class="report-readiness-badge ' + (ready ? 'is-ready' : 'is-busy') + '">' +
            (ready ? 'พร้อมรับงาน' : 'ไม่พร้อมรับงาน') + '</span></td>' +
          '<td class="report-number-cell">' + number(row.assigned_total) + ' งาน</td>' +
          '<td class="report-number-cell">' + number(row.done_total) + ' งาน</td>' +
        '</tr>';
      }).join('');
    }
    const result = form.closest('.report-table-panel')?.querySelector('[data-result-value]');
    if (result) result.textContent = number(payload.count) + ' รายการ';
  };

  const renderRevenue = (form, payload) => {
    const panel = form.closest('.report-table-panel');
    const body = panel?.querySelector('tbody');
    if (!body) return;
    const labels = Array.isArray(payload.labels) ? payload.labels : [];
    const monthly = Array.isArray(payload.monthly) ? payload.monthly : [];
    body.innerHTML = labels.map((label, index) => {
      const item = monthly[index] || { jobs: 0, fee: 0 };
      const jobs = Number(item.jobs || 0);
      const fee = Number(item.fee || 0);
      return '<tr>' +
        '<td>' + escapeHtml(label) + '</td>' +
        '<td class="report-number-cell">' + number(jobs) + ' งาน</td>' +
        '<td class="report-money-cell">' + number(fee, 2) + ' ฿</td>' +
        '<td class="report-money-cell">' + number(jobs ? fee / jobs : 0, 2) + ' ฿</td>' +
      '</tr>';
    }).join('');
    panel.querySelector('.report-panel-head p')?.replaceChildren(
      document.createTextNode('งานเสร็จสิ้นในปี ' + escapeHtml(payload.year))
    );
  };

  const apply = async (form) => {
    const section = form.dataset.filterSection;
    const params = readForm(form);
    applyOverviewPersonnelFilter(form);
    try {
      const payload = await request(form, params);
      if (section === 'overview-latest') {
        renderLatest(form, payload);
        renderOverviewPersonnelMetric(payload);
        renderOverviewDatasetMetrics(form, payload);
      }
      else if (section === 'installation-latest') renderInstallation(form, payload);
      else if (section === 'personnel') renderPersonnel(form, payload);
      else if (section === 'revenue') renderRevenueUnified(form, payload);
      setUrlState(section, params);
    } catch (error) {
      if (error.name !== 'AbortError') showMessage(form, 'ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่อีกครั้ง');
    }
  };

  normalizeFilterCopy();
  window.addEventListener('beforeprint', () => {
    updatePrintDate();
    showAllOverviewPersonnelTablesForPrint();
  });
  window.addEventListener('afterprint', () => {
    const overviewFilter = root.querySelector('[data-filter-section="overview-latest"]');
    if (overviewFilter) applyOverviewPersonnelFilter(overviewFilter);
  });

  root.querySelectorAll('[data-report-filter-form]:not([data-filter-disabled])').forEach((form) => {
    applyOverviewPersonnelFilter(form);

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      apply(form);
    });

    form.querySelector('[data-filter-reset]')?.addEventListener('click', () => {
      form.querySelectorAll('input').forEach((input) => { input.value = ''; });
      form.querySelectorAll('select').forEach((select) => {
        if (select.hasAttribute('data-filter-preserve-on-reset')) return;
        select.value = select.dataset.filterDefaultValue || '';
      });
      apply(form);
    });
  });
})();
