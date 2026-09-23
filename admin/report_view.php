<main class="admin-report-page">
    <header class="report-print-document-header" aria-label="หัวเอกสารรายงาน">
        <div class="report-print-brand">
            <?php if ($report_system_logo_url !== ''): ?>
                <img class="report-print-logo" src="<?= h($report_system_logo_url) ?>" alt="โลโก้บริษัท">
            <?php else: ?>
                <span class="report-print-logo-placeholder" aria-label="ไม่มีโลโก้บริษัท">-</span>
            <?php endif; ?>
            <div class="report-print-company-copy">
                <p class="report-print-company-name"><?= h($report_system_name) ?></p>
                <p class="report-print-company-address"><?= h($report_company_address) ?></p>
                <p class="report-print-company-tax">เลขประจำตัวผู้เสียภาษี: <?= h($report_company_tax_id) ?></p>
            </div>
        </div>
        <div class="report-print-meta">
            <h2><?= h($report_print_title) ?></h2>
            <p data-report-print-date data-timezone="<?= h(date_default_timezone_get()) ?>">วันที่พิมพ์: <?= h($report_print_date) ?></p>
        </div>
    </header>

    <header class="report-header">
        <div class="report-header-copy">
            <h1><?= h($page_title) ?></h1>
            <p><?= h($page_subtitle) ?></p>
        </div>
        <div class="report-header-actions">
            <button type="button" class="report-print-btn" onclick="window.print()">
                <i class="fa-solid fa-print" aria-hidden="true"></i>
                <span>พิมพ์ใบรายงาน</span>
            </button>
        </div>
    </header>

    <nav class="report-category-nav" aria-label="หมวดรายงาน">
        <?php foreach ($report_navigation_categories as $key => $label): ?>
            <?php $is_report_tab_active = ($report === 'users' && $key === 'personnel') || $report === $key; ?>
            <a
                class="report-category-link<?= $is_report_tab_active ? ' is-active' : '' ?>"
                href="<?= h(admin_report_url($key, $start_date, $end_date)) ?>"
                <?= $is_report_tab_active ? 'aria-current="page"' : '' ?>
            >
                <?= h($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($report === 'overview'): ?>
        <section class="report-metric-grid report-overview-metric-grid" aria-label="ตัวชี้วัดภาพรวมระบบ">
            <?php foreach ($overview_metrics as $metric): ?>
                <article class="report-metric-card">
                    <div class="report-metric-icon tone-<?= h($metric['tone']) ?>">
                        <i class="fa-solid <?= h($metric['icon']) ?>" aria-hidden="true"></i>
                    </div>
                    <div class="report-metric-copy">
                        <span><?= h($metric['label']) ?></span>
                        <strong><?= h($metric['value']) ?></strong>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <form class="report-filter-bar" data-report-filter-form data-filter-section="overview-latest">
            <label class="report-filter-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <span class="sr-only">à¸„à¹‰à¸™à¸«à¸²à¹ƒà¸šà¸‡à¸²à¸™</span>
                <input type="search" name="search" value="<?= h($report_filter_search) ?>" placeholder="ค้นหาใบงานล่าสุดจากรหัสหรือชื่อลูกค้า" autocomplete="off">
            </label>
            <label class="report-filter-select">
                <span class="sr-only">à¸à¸£à¸­à¸‡à¸•à¸²à¸¡à¸ªà¸–à¸²à¸™à¸°</span>
                <select name="status">
                    <option value=""<?= $report_filter_status === '' ? ' selected' : '' ?>>à¸ªà¸–à¸²à¸™à¸°à¸—à¸±à¹‰à¸‡à¸«à¸¡à¸”</option>
                    <option value="unassigned"<?= $report_filter_status === 'unassigned' ? ' selected' : '' ?>>à¸ªà¸£à¹‰à¸²à¸‡à¹ƒà¸šà¸‡à¸²à¸™à¹à¸¥à¹‰à¸§ / à¸¢à¸±à¸‡à¹„à¸¡à¹ˆà¹„à¸”à¹‰à¸¡à¸­à¸šà¸«à¸¡à¸²à¸¢</option>
                    <option value="in_progress"<?= $report_filter_status === 'in_progress' ? ' selected' : '' ?>>à¸à¸³à¸¥à¸±à¸‡à¸”à¸³à¹€à¸™à¸´à¸™à¸à¸²à¸£</option>
                    <option value="done"<?= $report_filter_status === 'done' ? ' selected' : '' ?>>à¹€à¸ªà¸£à¹‡à¸ˆà¸ªà¸´à¹‰à¸™</option>
                    <option value="cancelled"<?= $report_filter_status === 'cancelled' ? ' selected' : '' ?>>à¸¢à¸à¹€à¸¥à¸´à¸à¹à¸¥à¹‰à¸§</option>
                </select>
            </label>
            <button type="submit" class="report-filter-submit"><i class="fa-solid fa-filter" aria-hidden="true"></i><span>à¸à¸£à¸­à¸‡</span></button>
            <button type="button" class="report-filter-reset" data-filter-reset>à¸¥à¹‰à¸²à¸‡à¸•à¸±à¸§à¸à¸£à¸­à¸‡</button>
        </form>

        <section class="report-panel report-table-panel report-latest-panel" data-filter-target="overview-latest" aria-labelledby="overview-latest-title">
            <div class="report-panel-head">
                <div>
                    <h2 id="overview-latest-title">ใบงานติดตั้งล่าสุด</h2>
                    <p>10 ใบงานที่สร้างล่าสุด พร้อมสถานะปัจจุบัน</p>
                </div>
                <span class="report-table-period report-result-count" data-result-count="overview-latest"><i class="fa-regular fa-clock" aria-hidden="true"></i><span data-result-value><?= h(number_format(count($latest_installation_rows))) ?> รายการ</span></span>
            </div>
            <div class="report-table-wrap">
                <table class="report-table report-latest-table">
                    <thead>
                        <tr>
                            <th scope="col">รหัสใบงาน</th>
                            <th scope="col">ลูกค้า</th>
                            <th scope="col">พนักงานขาย</th>
                            <th scope="col">ช่างล่าสุด</th>
                            <th scope="col">วันที่ติดตั้ง</th>
                            <th scope="col">สถานะปัจจุบัน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($latest_installation_rows === []): ?>
                            <tr><td colspan="6"><div class="report-empty-state"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><span><?= ($report_filter_search !== '' || $report_filter_status !== '') ? 'ไม่พบข้อมูลที่ตรงกับตัวกรอง' : 'ยังไม่มีใบงานติดตั้ง' ?></span></div></td></tr>
                        <?php else: ?>
                            <?php foreach ($latest_installation_rows as $latest_row): ?>
                                <?php
                                $latest_status = $report_workflow_status_meta[(string) ($latest_row['report_status'] ?? '')] ?? ['label' => 'ไม่ระบุสถานะ', 'tone' => 'blue', 'icon' => 'fa-circle-question'];
                                $latest_install_date = !empty($latest_row['assign_install_date']) ? date('d/m/Y', strtotime((string) $latest_row['assign_install_date'])) : '-';
                                ?>
                                <tr>
                                    <td><strong><?= h($latest_row['setup_id']) ?></strong></td>
                                    <td><?= h($latest_row['customer_name'] ?: '-') ?></td>
                                    <td><?= h($latest_row['seller_name'] ?: '-') ?></td>
                                    <td><?= h($latest_row['technician_name'] ?: '-') ?></td>
                                    <td><?= h($latest_install_date) ?></td>
                                    <td><span class="report-status-label"><i class="fa-solid <?= h($latest_status['icon']) ?> tone-<?= h($latest_status['tone']) ?>" aria-hidden="true"></i><?= h($latest_status['label']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php elseif ($report === 'installations'): ?>
        <section class="report-metric-grid report-installation-summary-grid" data-installation-summary aria-label="ตัวชี้วัดใบงานติดตั้ง">
            <?php foreach ($report_metrics as $metric): ?>
                <article class="report-metric-card">
                    <div class="report-metric-icon tone-<?= h($metric['tone']) ?>">
                        <i class="fa-solid <?= h($metric['icon']) ?>" aria-hidden="true"></i>
                    </div>
                    <div class="report-metric-copy">
                        <span<?= (($metric['key'] ?? '') === 'in_progress' && ($metric['label'] ?? '') === 'ยืนยันรับสินค้าแล้ว') ? ' class="is-installation-received-label"' : '' ?> data-installation-metric-label="<?= h($metric['key'] ?? '') ?>"><?= h($metric['label']) ?></span>
                        <strong data-installation-metric="<?= h($metric['key'] ?? '') ?>"><?= h($metric['value']) ?></strong>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <form class="report-filter-bar report-installation-filter-bar" data-report-filter-form data-filter-section="installation-latest">
            <label class="report-filter-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <span class="sr-only">à¸„à¹‰à¸™à¸«à¸²à¹ƒà¸šà¸‡à¸²à¸™</span>
                <input type="search" name="search" value="<?= h($report_filter_search) ?>" placeholder="à¸„à¹‰à¸™à¸«à¸²à¸£à¸«à¸±à¸ªà¹ƒà¸šà¸‡à¸²à¸™ à¸¥à¸¹à¸à¸„à¹‰à²² à¸žà¸™à¸±à¸à¸‡à¸²à¸™à¸‚à¸²à¸¢ à¸«à¸£à¸·à¸­à¸Šà¹ˆà¸²à¸‡" autocomplete="off">
            </label>
            <label class="report-filter-select">
                <span class="sr-only">à¸à¸£à¸­à¸‡à¸•à¸²à¸¡à¸ªà¸–à¸²à¸™à¸°</span>
                <select name="status">
                    <option value=""<?= $report_filter_status === '' ? ' selected' : '' ?>>à¸—à¸¸à¸à¸ªà¸–à¸²à¸™à¸°</option>
                    <?php foreach ($report_workflow_status_meta as $status_key => $status_item): ?>
                        <option value="<?= h($status_key) ?>"<?= $report_filter_status === $status_key ? ' selected' : '' ?>><?= h($status_item['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="report-filter-submit"><i class="fa-solid fa-filter" aria-hidden="true"></i><span>à¸à¸£à¸­à¸‡</span></button>
            <button type="button" class="report-filter-reset" data-filter-reset>à¸¥à¹‰à¸²à¸‡à¸•à¸±à¸§à¸à¸£à¸­à¸‡</button>
        </form>

        <section class="report-panel report-table-panel report-latest-panel" data-filter-target="installation-latest" aria-labelledby="installation-latest-title">
            <div class="report-panel-head">
                <div>
                    <h2 id="installation-latest-title">ใบงานติดตั้งล่าสุด</h2>
                    <p>เรียงจากวันที่สร้างใบงานใหม่สุด และใช้ assignment ล่าสุดต่อใบงาน</p>
                </div>
                <span class="report-table-period report-result-count" data-result-count="installation-latest"><i class="fa-regular fa-calendar" aria-hidden="true"></i><span data-result-value><?= h(number_format($installation_filtered_total)) ?> รายการ</span></span>
            </div>
            <div class="report-table-wrap">
                <table class="report-table installation-detail-table">
                    <thead>
                        <tr>
                            <th scope="col">รหัสใบงาน</th>
                            <th scope="col">ลูกค้า</th>
                            <th scope="col">พนักงานขาย</th>
                            <th scope="col">ช่างล่าสุด</th>
                            <th scope="col">วันที่สร้าง</th>
                            <th scope="col">วันที่ติดตั้ง</th>
                            <th scope="col">สถานะปัจจุบัน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($installation_detail_rows === []): ?>
                            <tr><td colspan="7"><div class="report-empty-state"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><span><?= ($report_filter_search !== '' || $report_filter_status !== '') ? 'ไม่พบข้อมูลที่ตรงกับตัวกรอง' : 'ยังไม่มีใบงานติดตั้ง' ?></span></div></td></tr>
                        <?php else: ?>
                            <?php foreach ($installation_detail_rows as $installation_row): ?>
                                <?php
                                $detail_status = $report_workflow_status_meta[(string) ($installation_row['report_status'] ?? '')] ?? ['label' => 'ไม่ระบุสถานะ', 'tone' => 'blue', 'icon' => 'fa-circle-question'];
                                $created_display = !empty($installation_row['created_at']) ? date('d/m/Y', strtotime((string) $installation_row['created_at'])) : '-';
                                $install_display = !empty($installation_row['assign_install_date']) ? date('d/m/Y', strtotime((string) $installation_row['assign_install_date'])) : '-';
                                ?>
                                <tr>
                                    <td><strong><?= h($installation_row['setup_id']) ?></strong></td>
                                    <td><?= h($installation_row['customer_name'] ?: '-') ?></td>
                                    <td><?= h($installation_row['seller_name'] ?: '-') ?></td>
                                    <td><?= h($installation_row['technician_name'] ?: '-') ?></td>
                                    <td><?= h($created_display) ?></td>
                                    <td><?= h($install_display) ?></td>
                                    <td><span class="report-status-label"><i class="fa-solid <?= h($detail_status['icon']) ?> tone-<?= h($detail_status['tone']) ?>" aria-hidden="true"></i><?= h($detail_status['label']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    <?php elseif ($report === 'revenue'): ?>
        <section class="report-metric-grid report-revenue-summary-grid" aria-label="ตัวชี้วัดค่าติดตั้ง">
            <?php $revenue_metric_keys = ['total', 'done', 'in_progress', 'cancelled', 'average']; ?>
            <?php foreach ($report_metrics as $metric_index => $metric): ?>
                <article class="report-metric-card">
                    <div class="report-metric-icon tone-<?= h($metric['tone']) ?>">
                        <i class="fa-solid <?= h($metric['icon']) ?>" aria-hidden="true"></i>
                    </div>
                    <div class="report-metric-copy">
                        <span><?= h($metric['label']) ?></span>
                        <strong data-revenue-metric="<?= h($revenue_metric_keys[$metric_index] ?? '') ?>"><?= h($metric['value']) ?></strong>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <form class="report-filter-bar" data-report-filter-form data-filter-section="revenue">
            <label class="report-filter-select">
                <span class="sr-only">กรองตามสถานะ</span>
                <select name="status">
                    <option value=""<?= $report_filter_status === '' ? ' selected' : '' ?>>สถานะทั้งหมด</option>
                    <?php foreach ($report_workflow_status_meta as $status_key => $status_item): ?>
                        <option value="<?= h($status_key) ?>"<?= $report_filter_status === $status_key ? ' selected' : '' ?>><?= h($status_item['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="report-filter-select">
                <span>ปี</span>
                <select name="fee_year" data-filter-default-value="<?= h((string) date('Y')) ?>">
                    <?php foreach (array_reverse($fee_year_options) as $year_option): ?>
                        <option value="<?= h((string) $year_option) ?>"<?= $fee_year === $year_option ? ' selected' : '' ?>><?= h((string) $year_option) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="report-filter-submit"><i class="fa-solid fa-filter" aria-hidden="true"></i><span>กรอง</span></button>
            <button type="button" class="report-filter-reset" data-filter-reset>ล้างตัวกรอง</button>
        </form>

        <section class="report-table-panel-grid" aria-label="รายละเอียดค่าติดตั้ง">
            <article class="report-panel report-table-panel" data-filter-target="revenue-status" aria-labelledby="revenue-status-title">
                <div class="report-panel-head">
                    <div>
                        <h2 id="revenue-status-title">ค่าติดตั้งตามสถานะงาน</h2>
                        <p>สรุปจำนวนงานและยอดค่าติดตั้งตามสถานะใน<?= h($period_label) ?></p>
                    </div>
                    <span class="report-table-period report-result-count"><i class="fa-solid fa-baht-sign" aria-hidden="true"></i><span data-revenue-result-count><?= h(number_format($fee_setup_count)) ?> รายการ</span></span>
                </div>
                <div class="report-table-wrap">
                    <table class="report-table report-status-table report-fee-status-table">
                        <thead>
                            <tr><th scope="col">สถานะ</th><th scope="col">จำนวนงาน</th><th scope="col">ค่าติดตั้ง</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fee_status_table_meta as $status_key => $status_item): ?>
                                <tr>
                                    <td><span class="report-status-label"><i class="fa-solid <?= h($report_workflow_status_meta[$status_key]['icon'] ?? 'fa-circle') ?> tone-<?= h($status_item['tone']) ?>" aria-hidden="true"></i><?= h($status_item['label']) ?></span></td>
                                    <td class="report-number-cell"><?= h(number_format($fee_status_by_key[$status_key]['total'] ?? 0)) ?> งาน</td>
                                    <td class="report-money-cell"><?= h(number_format((float) ($fee_status_by_key[$status_key]['fee_total'] ?? 0), 2)) ?> ฿</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="report-panel report-table-panel revenue-monthly-panel" data-filter-target="revenue-monthly" aria-labelledby="revenue-monthly-title">
                <div class="report-panel-head">
                    <div>
                        <h2 id="revenue-monthly-title">สรุปค่าติดตั้งรายเดือน</h2>
                        <p>งานเสร็จสิ้นในปี <?= h((string) $fee_year) ?></p>
                    </div>
                </div>
                <div class="report-table-wrap">
                    <table class="report-table revenue-monthly-table">
                        <thead>
                            <tr><th scope="col">เดือน</th><th scope="col">จำนวนงานเสร็จสิ้น</th><th scope="col">ค่าติดตั้งรวม</th><th scope="col">ค่าเฉลี่ยต่อใบงาน</th></tr>
                        </thead>
                        <tbody>
                            <?php for ($month = 1; $month <= 12; $month++): ?>
                                <?php
                                $month_jobs = $fee_monthly[$month]['jobs'];
                                $month_fee = $fee_monthly[$month]['fee'];
                                $month_average = $month_jobs > 0 ? $month_fee / $month_jobs : 0;
                                ?>
                                <tr>
                                    <td><?= h($fee_month_labels[$month - 1]) ?></td>
                                    <td class="report-number-cell"><?= h(number_format($month_jobs)) ?> งาน</td>
                                    <td class="report-money-cell"><?= h(number_format($month_fee, 2)) ?> ฿</td>
                                    <td class="report-money-cell"><?= h(number_format($month_average, 2)) ?> ฿</td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

    <?php elseif ($report === 'users'): ?>
        <section class="report-metric-grid report-personnel-summary-grid" data-personnel-summary data-personnel-type="<?= h($personnel_view['type']) ?>" aria-label="ตัวชี้วัดบุคลากร">
            <?php foreach ($personnel_view['summary_cards'] as $metric): ?>
                <article class="report-metric-card">
                    <div class="report-metric-icon tone-<?= h($metric['tone']) ?>">
                        <i class="fa-solid <?= h($metric['icon']) ?>" aria-hidden="true"></i>
                    </div>
                    <div class="report-metric-copy">
                        <span><?= h($metric['label']) ?></span>
                        <strong><?= h(number_format((int) $metric['value'])) ?></strong>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <form class="report-filter-bar" data-report-filter-form data-filter-section="personnel">
            <label class="report-filter-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <span class="sr-only">ค้นหารหัสหรือชื่อบุคลากร</span>
                <input type="search" name="personnel_search" value="<?= h($personnel_filter_search) ?>" placeholder="ค้นหารหัสหรือชื่อ" autocomplete="off">
            </label>
            <label class="report-filter-select">
                <span class="sr-only">กรองตามประเภท</span>
                <select name="personnel_type">
                    <option value=""<?= $personnel_filter_type === 'all' ? ' selected' : '' ?>>ทั้งหมด</option>
                    <option value="sale"<?= $personnel_filter_type === 'sale' ? ' selected' : '' ?>>พนักงานขาย</option>
                    <option value="manager"<?= $personnel_filter_type === 'manager' ? ' selected' : '' ?>>หัวหน้าช่าง</option>
                    <option value="technician"<?= $personnel_filter_type === 'technician' ? ' selected' : '' ?>>ช่างติดตั้ง</option>
                </select>
            </label>
            <button type="submit" class="report-filter-submit"><i class="fa-solid fa-filter" aria-hidden="true"></i><span>กรอง</span></button>
            <button type="button" class="report-filter-reset" data-filter-reset>ล้างตัวกรอง</button>
        </form>

        <?php $personnel_tables = $personnel_view['tables']; ?>
        <section class="report-table-panel-grid report-personnel-table-grid<?= count($personnel_tables) === 1 ? ' report-personnel-table-grid--single' : '' ?>" data-personnel-tables data-personnel-type="<?= h($personnel_view['type']) ?>" aria-label="สรุปข้อมูลบุคลากร">
            <?php foreach ($personnel_tables as $personnel_table): ?>
                <article class="report-panel report-table-panel" data-personnel-table-type="<?= h($personnel_table['type']) ?>" aria-labelledby="personnel-table-<?= h($personnel_table['type']) ?>-title">
                    <div class="report-panel-head">
                        <div>
                            <h2 id="personnel-table-<?= h($personnel_table['type']) ?>-title"><?= h($personnel_table['title']) ?></h2>
                            <p><?= h($personnel_table['description']) ?></p>
                        </div>
                    </div>
                    <div class="report-table-wrap">
                        <table class="report-table personnel-table">
                            <thead>
                                <tr>
                                    <?php foreach ($personnel_table['columns'] as $column): ?>
                                        <th scope="col"><?= h($column) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($personnel_table['rows'] === []): ?>
                                    <tr><td colspan="<?= h((string) count($personnel_table['columns'])) ?>"><div class="report-empty-state"><i class="<?= h($personnel_table['empty_icon']) ?>" aria-hidden="true"></i><span><?= h($personnel_table['empty_message']) ?></span></div></td></tr>
                                <?php else: ?>
                                    <?php foreach ($personnel_table['rows'] as $row): ?>
                                        <tr>
                                            <td><strong><?= h($row['id']) ?></strong></td>
                                            <td><span class="report-status-label"><i class="fa-solid <?= h($personnel_table['row_icon']) ?> tone-<?= h($personnel_table['row_tone']) ?>" aria-hidden="true"></i><?= h($row['name']) ?></span></td>
                                            <?php if ($personnel_table['type'] === 'technician'): ?>
                                                <td><span class="report-readiness-badge<?= !empty($row['ready']) ? ' is-ready' : ' is-busy' ?>"><?= !empty($row['ready']) ? 'พร้อมรับงาน' : 'ไม่พร้อมรับงาน' ?></span></td>
                                            <?php endif; ?>
                                            <?php foreach ($row['stats'] as $stat): ?>
                                                <td class="report-number-cell"><?= h(number_format((int) $stat)) ?> งาน</td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
