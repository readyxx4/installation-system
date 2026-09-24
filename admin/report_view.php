<main class="admin-report-page">
    <table class="admin-print-document" role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <thead>
            <tr>
                <td>
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
                <p class="report-print-company-identifiers">
                    <span>เลขทะเบียนนิติบุคคล: <?= h($report_company_registration_no) ?></span>
                    <span aria-hidden="true">|</span>
                    <span>เลขประจำตัวผู้เสียภาษี: <?= h($report_company_tax_id) ?></span>
                </p>
            </div>
        </div>
        <div class="report-print-meta">
            <h2><?= h($report_print_title) ?></h2>
            <p data-report-print-date data-timezone="<?= h(date_default_timezone_get()) ?>">วันที่พิมพ์: <?= h($report_print_date) ?></p>
            <p class="report-print-period" data-report-print-period<?= $report_print_period === '' ? ' hidden' : '' ?>><?= h($report_print_period) ?></p>
        </div>
    </header>
                </td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>

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
            <?php $is_report_tab_active = $report === $key; ?>
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
                        <span<?= ($metric['key'] ?? '') === 'personnel' ? ' data-overview-personnel-metric-label' : '' ?>><?= h($metric['label']) ?></span>
                        <strong<?= ($metric['key'] ?? '') !== '' ? ' data-overview-metric="' . h($metric['key']) . '"' : '' ?><?= ($metric['key'] ?? '') === 'personnel' ? ' data-overview-personnel-metric' : '' ?>><?= h($metric['value']) ?></strong>
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
            <label class="report-filter-select">
                <span class="sr-only">กรองตามประเภทพนักงาน</span>
                <select name="personnel_type" data-filter-default-value="all">
                    <option value="all"<?= $personnel_filter_type === 'all' ? ' selected' : '' ?>>ทั้งหมด</option>
                    <option value="sale"<?= $personnel_filter_type === 'sale' ? ' selected' : '' ?>>พนักงานขาย</option>
                    <option value="manager"<?= $personnel_filter_type === 'manager' ? ' selected' : '' ?>>หัวหน้าช่าง</option>
                    <option value="technician"<?= $personnel_filter_type === 'technician' ? ' selected' : '' ?>>ช่างติดตั้ง</option>
                </select>
            </label>
            <label class="report-filter-date">
                <span>จากวันที่</span>
                <input type="date" name="date_from" value="<?= h($overview_date_from) ?>" aria-label="จากวันที่">
            </label>
            <label class="report-filter-date">
                <span>ถึงวันที่</span>
                <input type="date" name="date_to" value="<?= h($overview_date_to) ?>" aria-label="ถึงวันที่">
            </label>
            <button type="submit" class="report-filter-submit"><i class="fa-solid fa-filter" aria-hidden="true"></i><span>à¸à¸£à¸­à¸‡</span></button>
            <button type="button" class="report-filter-reset" data-filter-reset>à¸¥à¹‰à¸²à¸‡à¸•à¸±à¸§à¸à¸£à¸­à¸‡</button>
        </form>

        <?php if ($overview_date_filter_error !== ''): ?>
            <p class="report-filter-message" role="alert"><?= h($overview_date_filter_error) ?></p>
        <?php endif; ?>

        <?php $personnel_tables = $overview_personnel_tables; ?>
        <section class="report-table-panel-grid report-personnel-table-grid<?= count($personnel_tables) === 1 ? ' report-personnel-table-grid--single' : '' ?>" data-personnel-tables data-overview-personnel-tables data-personnel-type="all" aria-label="สรุปข้อมูลพนักงาน">
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

        <section class="report-panel report-table-panel report-latest-panel" data-filter-target="overview-latest" aria-labelledby="overview-latest-title">
            <div class="report-panel-head">
                <div>
                    <h2 id="overview-latest-title">ใบงานติดตั้งล่าสุด</h2>
                    <p><?= h($overview_limit_description) ?></p>
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

    <?php elseif ($report === 'product'): ?>
        <section class="report-metric-grid report-product-summary-grid" data-product-summary aria-label="สินค้าเด่น">
            <article class="report-metric-card">
                <div class="report-metric-icon tone-blue"><i class="fa-solid fa-boxes-stacked" aria-hidden="true"></i></div>
                <div class="report-metric-copy">
                    <span>สินค้าที่ถูกมอบหมายมากที่สุด</span>
                    <strong data-product-assigned-name><?= h($product_assigned_leader['product_name'] ?? '-') ?></strong>
                    <small data-product-assigned-value><?= h(number_format((int) ($product_assigned_leader['assigned_total'] ?? 0))) ?> งาน</small>
                </div>
            </article>
            <article class="report-metric-card">
                <div class="report-metric-icon tone-green"><i class="fa-solid fa-baht-sign" aria-hidden="true"></i></div>
                <div class="report-metric-copy">
                    <span>สินค้าที่ทำรายได้มากที่สุด</span>
                    <strong data-product-revenue-name><?= h($product_revenue_leader['product_name'] ?? '-') ?></strong>
                    <small data-product-revenue-value><?= h(number_format((float) ($product_revenue_leader['revenue_total'] ?? 0), 2)) ?> บาท</small>
                </div>
            </article>
        </section>

        <form class="report-filter-bar" data-report-filter-form data-filter-section="product">
            <label class="report-filter-select">
                <span class="sr-only">ประเภทสินค้า</span>
                <select name="product_type" data-filter-default-value="">
                    <option value="">ประเภทสินค้าทั้งหมด</option>
                    <?php foreach ($product_type_options as $product_type_option): ?>
                        <option value="<?= h($product_type_option['protype_id']) ?>"<?= $product_type_filter === $product_type_option['protype_id'] ? ' selected' : '' ?>><?= h($product_type_option['protype_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="report-filter-date">
                <span>จากวันที่</span>
                <input type="date" name="date_from" value="<?= h($product_date_from) ?>">
            </label>
            <label class="report-filter-date">
                <span>ถึงวันที่</span>
                <input type="date" name="date_to" value="<?= h($product_date_to) ?>">
            </label>
            <button type="submit" class="report-filter-submit"><i class="fa-solid fa-filter" aria-hidden="true"></i><span>กรอง</span></button>
            <button type="button" class="report-filter-reset" data-filter-reset>ล้างตัวกรอง</button>
            <?php if ($product_date_filter_error !== ''): ?>
                <p class="report-filter-error" role="alert"><?= h($product_date_filter_error) ?></p>
            <?php endif; ?>
        </form>

        <section class="report-panel report-product-top-five" data-filter-target="product-top-revenue" aria-labelledby="product-top-revenue-title">
            <div class="report-panel-head">
                <div>
                    <h2 id="product-top-revenue-title">Top 5 สินค้าที่ทำรายได้มากที่สุด</h2>
                    <p>เฉพาะงานติดตั้งที่เสร็จสิ้นแล้ว</p>
                </div>
            </div>
            <ol class="product-revenue-ranking" data-product-revenue-ranking>
                <?php if ($product_revenue_top_five === []): ?>
                    <li class="report-empty-state"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><span>ไม่พบข้อมูลที่ตรงกับตัวกรอง</span></li>
                <?php else: ?>
                    <?php $product_top_revenue = max(array_map(static fn (array $row): float => (float) $row['revenue_total'], $product_revenue_top_five)); ?>
                    <?php foreach ($product_revenue_top_five as $top_product): ?>
                        <?php $product_revenue_share = $product_top_revenue > 0 ? ((float) $top_product['revenue_total'] / $product_top_revenue) * 100 : 0; ?>
                        <li>
                            <span class="product-revenue-track"><span style="--product-revenue-share: <?= h(number_format($product_revenue_share, 2, '.', '')) ?>%"></span></span>
                            <strong><?= h($top_product['product_name']) ?></strong>
                            <b><?= h(number_format((float) $top_product['revenue_total'], 2)) ?> บาท</b>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ol>
        </section>

        <div class="product-report-panels">
        <section class="report-panel report-table-panel" data-filter-target="product-assigned-products" aria-labelledby="product-assigned-products-title">
            <div class="report-panel-head">
                <div>
                    <h2 id="product-assigned-products-title">สินค้าที่ถูกมอบหมายมากที่สุด</h2>
                    <p>นับจากใบงานที่มีการมอบหมายช่างล่าสุด</p>
                </div>
            </div>
            <div class="report-table-wrap">
                <table class="report-table product-assigned-products-table">
                    <thead>
                        <tr><th scope="col">อันดับ</th><th scope="col">รหัสสินค้า</th><th scope="col">ชื่อสินค้า</th><th scope="col">จำนวนงานที่ถูกมอบหมาย</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($product_assigned_products === []): ?>
                            <tr><td colspan="4"><div class="report-empty-state"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><span>ไม่พบข้อมูลที่ตรงกับตัวกรอง</span></div></td></tr>
                        <?php else: ?>
                            <?php foreach ($product_assigned_products as $product_rank => $product_assigned_product): ?>
                                <tr>
                                    <td class="report-number-cell"><?= h(number_format($product_rank + 1)) ?></td>
                                    <td><strong><?= h($product_assigned_product['product_id']) ?></strong></td>
                                    <td><?= h($product_assigned_product['product_name']) ?></td>
                                    <td class="report-number-cell"><?= h(number_format((int) $product_assigned_product['assigned_total'])) ?> งาน</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="report-panel report-table-panel" data-filter-target="product-revenue-products" aria-labelledby="product-revenue-products-title">
            <div class="report-panel-head">
                <div>
                    <h2 id="product-revenue-products-title">สินค้าที่ทำรายได้มากที่สุด</h2>
                    <p>เฉพาะงานติดตั้งที่เสร็จสิ้นแล้ว</p>
                </div>
            </div>
            <div class="report-table-wrap">
                <table class="report-table product-revenue-products-table">
                    <thead>
                        <tr><th scope="col">อันดับ</th><th scope="col">ชื่อสินค้า</th><th scope="col">จำนวนติดตั้ง</th><th scope="col">รายได้รวม</th></tr>
                    </thead>
                    <tbody>
                        <?php if ($product_revenue_products === []): ?>
                            <tr><td colspan="4"><div class="report-empty-state"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><span>ไม่พบข้อมูลที่ตรงกับตัวกรอง</span></div></td></tr>
                        <?php else: ?>
                            <?php foreach ($product_revenue_products as $product_rank => $product_revenue_product): ?>
                                <tr>
                                    <td class="report-number-cell"><?= h(number_format($product_rank + 1)) ?></td>
                                    <td><strong><?= h($product_revenue_product['product_name']) ?></strong></td>
                                    <td class="report-number-cell"><?= h(number_format((int) $product_revenue_product['install_quantity'])) ?> รายการ</td>
                                    <td class="report-money-cell"><?= h(number_format((float) $product_revenue_product['revenue_total'], 2)) ?> ฿</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        </div>

    <?php elseif ($report === 'revenue'): ?>
        <section class="report-metric-grid report-revenue-summary-grid" aria-label="ตัวชี้วัดค่าติดตั้ง">
            <?php $revenue_metric_keys = ['total', 'done', 'in_progress', 'cancelled', 'average']; ?>
            <?php foreach ($report_metrics as $metric_index => $metric): ?>
                <?php if (($revenue_metric_keys[$metric_index] ?? '') === 'average') continue; ?>
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
            <label class="report-filter-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <span class="sr-only">ค้นหาใบงานจากรหัสหรือชื่อลูกค้า</span>
                <input type="search" name="search" value="<?= h($report_filter_search) ?>" placeholder="ค้นหาใบงานจากรหัสหรือชื่อลูกค้า" autocomplete="off">
            </label>
            <label class="report-filter-select">
                <span class="sr-only">กรองตามสถานะ</span>
                <select name="status">
                    <option value=""<?= $report_filter_status === '' ? ' selected' : '' ?>>สถานะทั้งหมด</option>
                    <?php foreach ($report_workflow_status_meta as $status_key => $status_item): ?>
                        <option value="<?= h($status_key) ?>"<?= $report_filter_status === $status_key ? ' selected' : '' ?>><?= h($status_item['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="report-filter-date">
                <span>จากวันที่</span>
                <input type="date" name="date_from" value="<?= h($revenue_date_from) ?>">
            </label>
            <label class="report-filter-date">
                <span>ถึงวันที่</span>
                <input type="date" name="date_to" value="<?= h($revenue_date_to) ?>">
            </label>
            <button type="submit" class="report-filter-submit"><i class="fa-solid fa-filter" aria-hidden="true"></i><span>กรอง</span></button>
            <button type="button" class="report-filter-reset" data-filter-reset>ล้างตัวกรอง</button>
            <?php if ($revenue_date_filter_error !== ''): ?>
                <p class="report-filter-message" role="alert"><?= h($revenue_date_filter_error) ?></p>
            <?php endif; ?>
        </form>

        <section class="report-table-panel-grid" aria-label="รายละเอียดค่าติดตั้ง">
            <article class="report-panel report-table-panel" data-filter-target="revenue-status" aria-labelledby="revenue-status-title">
                <div class="report-panel-head">
                    <div>
                        <h2 id="revenue-status-title">ค่าติดตั้งตามสถานะงาน</h2>
                        <p>สรุปจำนวนงานและยอดค่าติดตั้งตามสถานะใน<?= h($revenue_period_label) ?></p>
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
                        <p><?= h($fee_monthly_description) ?></p>
                    </div>
                </div>
                <div class="report-table-wrap">
                    <table class="report-table revenue-monthly-table">
                        <thead>
                            <tr><th scope="col">เดือน</th><th scope="col">จำนวนงานเสร็จสิ้น</th><th scope="col">ค่าติดตั้งรวม</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($fee_monthly === []): ?>
                                <tr>
                                    <td colspan="3"><div class="report-empty-state"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><span>ไม่พบข้อมูลที่ตรงกับตัวกรอง</span></div></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($fee_monthly as $fee_month_row): ?>
                                    <tr>
                                        <td><?= h($fee_month_row['label']) ?></td>
                                        <td class="report-number-cell"><?= h(number_format($fee_month_row['jobs'])) ?> งาน</td>
                                        <td class="report-money-cell"><?= h(number_format($fee_month_row['fee'], 2)) ?> ฿</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

    <?php elseif ($report === 'users'): ?>
        <section class="report-metric-grid report-personnel-summary-grid" data-personnel-summary data-personnel-type="<?= h($personnel_view['type']) ?>" aria-label="ตัวชี้วัดพนักงาน">
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
                <span class="sr-only">ค้นหารหัสหรือชื่อพนักงาน</span>
                <input type="search" name="personnel_search" value="<?= h($personnel_filter_search) ?>" placeholder="ค้นหารหัสหรือชื่อ" autocomplete="off">
            </label>
            <button type="button" class="report-filter-reset" data-filter-reset>ล้างตัวกรอง</button>
        </form>

    <?php endif; ?>

                </td>
            </tr>
        </tbody>
    </table>
</main>
