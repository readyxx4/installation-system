<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('technician');

$tech_id = $_SESSION['user_id'] ?? '';
$warning_filter = trim($_GET['warning'] ?? '');
$allowed_warning_filters = ['near', 'urgent', 'today', 'overdue'];
if (!in_array($warning_filter, $allowed_warning_filters, true)) {
    $warning_filter = '';
}
$status_filter = trim((string) ($_GET['status'] ?? 'all'));
$allowed_status_filters = ['all', 'awaiting_receive', 'ready', 'installing', 'awaiting_review', 'done', 'history'];
if (!in_array($status_filter, $allowed_status_filters, true)) {
    $status_filter = 'all';
}

if ($tech_id === '') {
    redirect_to(app_public_url('login.html?error=login'));
}

$page_titles = [
    'all' => 'งานของฉัน',
    'awaiting_receive' => 'รอรับสินค้า',
    'ready' => 'พร้อมติดตั้ง',
    'installing' => 'กำลังติดตั้ง',
    'awaiting_review' => 'รอหัวหน้าช่างยืนยัน',
    'done' => 'งานเสร็จสิ้น',
    'history' => 'ประวัติงาน',
];
$page_subtitles = [
    'all' => 'รายการงานติดตั้งทั้งหมดของฉัน',
    'awaiting_receive' => 'งานที่รับงานแล้ว และรอยืนยันรับสินค้า',
    'ready' => 'งานที่รับสินค้าแล้ว และพร้อมเริ่มติดตั้ง',
    'installing' => 'งานที่เริ่มติดตั้งแล้ว และรอบันทึกผลการติดตั้ง',
    'awaiting_review' => 'งานที่บันทึกผลแล้ว และรอหัวหน้าช่างยืนยัน',
    'done' => 'งานที่ยืนยันเสร็จสิ้นแล้ว',
    'history' => 'งานที่เสร็จสิ้น ปฏิเสธ หรือถูกยกเลิกแล้ว',
];
$page_title = $page_titles[$status_filter] ?? $page_titles['all'];
$page_subtitle = $page_subtitles[$status_filter] ?? $page_subtitles['all'];
$menu_status_filters = ['awaiting_receive', 'ready', 'installing', 'history'];
$technician_active_menu = in_array($status_filter, $menu_status_filters, true)
    ? 'my_jobs_' . $status_filter
    : 'my_jobs';

function assign_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'ยังไม่มอบหมาย',
        '1' => 'มอบหมายแล้ว',
        '2' => 'ช่างรับงานแล้ว',
        '3' => 'ช่างปฏิเสธงาน',
        '4' => 'ยกเลิกแล้ว',
        '5' => 'เสร็จสิ้น',
        default => 'ไม่ทราบสถานะ',
    };
}

function technician_my_jobs_icon_svg(string $name, int $size = 14, string $class = ''): string
{
    $paths = [
        'search' => '<circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path>',
        'reset' => '<path d="M3 12a9 9 0 109-9 9.8 9.8 0 00-6.7 2.7"></path><path d="M3 4v6h6"></path>',
        'info' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4M12 8h.01"></path>',
        'clipboard-check' => '<rect x="8" y="3" width="8" height="4" rx="1"></rect><path d="M8 5H6a2 2 0 00-2 2v13a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-2"></path><path d="M9 14l2 2 4-4"></path>',
        'hash' => '<path d="M4 9h16M4 15h16M10 3L8 21M16 3l-2 18"></path>',
        'clock' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path>',
        'user' => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path>',
        'map-pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path><circle cx="12" cy="10" r="3"></circle>',
        'package' => '<path d="M16.5 9.4L7.5 4.2M21 16V8a2 2 0 00-1-1.7l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.7l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"></path><path d="M3.3 7L12 12l8.7-5M12 22V12"></path>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>',
    ];

    if (!isset($paths[$name])) {
        return '';
    }

    $classes = trim('ref-icon ' . $class);

    return '<svg class="' . h($classes) . '" width="' . h((string) $size) . '" height="' . h((string) $size) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}

function technician_my_jobs_ui_status(array $row): array
{
    $assign_status = (string) ($row['assign_status'] ?? '');
    $setup_status = (string) ($row['setup_status'] ?? '');
    $receive_status = (string) ($row['receive_status'] ?? '');

    if ($assign_status === '5' || $setup_status === '4') {
        return ['key' => 'done', 'label' => 'งานเสร็จสิ้นแล้ว', 'class' => 'success'];
    }

    if ($setup_status === '3' && (string) ($row['has_install_result'] ?? '0') === '1') {
        return ['key' => 'awaiting_review', 'label' => 'รอหัวหน้าช่างยืนยัน', 'class' => 'waiting'];
    }

    if ($setup_status === '3') {
        return ['key' => 'installing', 'label' => 'กำลังติดตั้ง', 'class' => 'info'];
    }

    if ($assign_status === '2' && $receive_status === '1' && $setup_status === '2') {
        return ['key' => 'ready', 'label' => 'พร้อมติดตั้ง', 'class' => 'green'];
    }

    if ($assign_status === '2' && $receive_status !== '1') {
        return ['key' => 'awaiting_receive', 'label' => 'รอรับสินค้า', 'class' => 'blue'];
    }

    return ['key' => 'accepted', 'label' => assign_status_name($assign_status), 'class' => 'blue'];
}

$stmt_jobs = $conn->prepare("
    SELECT
        a.assign_id,
        a.assign_date,
        a.assign_install_date,
        a.assign_install_time,
        a.assign_status,

        s.setup_id,
        s.setup_address,
        s.setup_location,
        s.setup_status,
        EXISTS (
            SELECT 1
            FROM installation_result ir
            WHERE ir.setup_id = s.setup_id
        ) AS has_install_result,

        c.customer_name,
        c.customer_phone,
        c.customer_address,

        pr.receive_status,

        COALESCE(ds.item_count, 0) AS item_count,
        COALESCE(ds.install_total, 0) AS install_total
    FROM assignment a
    LEFT JOIN setup s ON a.setup_id = s.setup_id
    LEFT JOIN customers c ON s.customer_id = c.customer_id
    LEFT JOIN product_receive pr ON a.assign_id = pr.assign_id
    LEFT JOIN (
        SELECT
            d.setup_id,
            COUNT(*) AS item_count,
            SUM(COALESCE(d.install_qty, 1) * COALESCE(pd.pro_price_install, 0)) AS install_total
        FROM install_detail d
        LEFT JOIN product pd ON d.pro_id = pd.pro_id
        GROUP BY d.setup_id
    ) ds ON s.setup_id = ds.setup_id
    WHERE a.tech_id = ?
      AND (a.assign_status IN (2, 3, 4, 5) OR s.setup_status = 4)
    ORDER BY a.assign_date DESC, a.assign_id DESC
");

$stmt_jobs->bind_param('s', $tech_id);
$stmt_jobs->execute();
$jobs_result = $stmt_jobs->get_result();
$job_rows = [];
$status_counts = [
    'all' => 0,
    'awaiting_receive' => 0,
    'ready' => 0,
    'installing' => 0,
    'awaiting_review' => 0,
    'done' => 0,
    'history' => 0,
];

while ($job_row = $jobs_result->fetch_assoc()) {
    $warning = assignment_install_due_warning(
        $job_row['assign_install_date'] ?? '',
        $job_row['assign_install_time'] ?? '',
        $job_row['assign_status'] ?? '',
        'technician'
    );

    if (!assignment_install_due_filter_match($warning_filter, $warning, $job_row['assign_status'] ?? '')) {
        continue;
    }

    $ui_status = technician_my_jobs_ui_status($job_row);
    $job_row['ui_status_key'] = $ui_status['key'];
    $job_row['ui_status_label'] = $ui_status['label'];
    $job_row['ui_status_class'] = $ui_status['class'];

    $is_history_job = in_array((string) ($job_row['assign_status'] ?? ''), ['3', '4', '5'], true)
        || (string) ($job_row['setup_status'] ?? '') === '4';

    if (!$is_history_job) {
        $status_counts['all'] += 1;
    }
    if (isset($status_counts[$ui_status['key']])) {
        $status_counts[$ui_status['key']] += 1;
    }
    if ($is_history_job) {
        $status_counts['history'] += 1;
    }

    if ($status_filter === 'history' && !$is_history_job) {
        continue;
    }

    if ($status_filter === 'all' && $is_history_job) {
        continue;
    }

    if ($status_filter !== 'all' && $status_filter !== 'history' && $ui_status['key'] !== $status_filter) {
        continue;
    }

    $job_rows[] = $job_row;
}

layout_header($page_title, $technician_active_menu, $page_subtitle);
?>

<link
  rel="stylesheet"
  href="<?= h(app_asset_url('sale/assets/css/setups.css')) ?>?v=<?= h(asset_version('sale/assets/css/setups.css')) ?>"
>
<link
  rel="stylesheet"
  href="<?= h(app_asset_url('admin/assets/css/table_actions.css')) ?>?v=<?= h(asset_version('admin/assets/css/table_actions.css')) ?>"
>
<link
  rel="stylesheet"
  href="<?= h(app_asset_url('technician/assets/css/technician.css')) ?>?v=<?= h(asset_version('technician/assets/css/technician.css')) ?>"
>
<link
  rel="stylesheet"
  href="<?= h(app_asset_url('technician/assets/css/jobs.css')) ?>?v=<?= h(asset_version('technician/assets/css/jobs.css')) ?>"
>
<link
  rel="stylesheet"
  href="<?= h(app_asset_url('technician/assets/css/my_jobs.css')) ?>?v=<?= h(asset_version('technician/assets/css/my_jobs.css')) ?>"
>

<?= flash_message() ?>

<section class="page technician-assignments-page technician-my-jobs-page">
  <form class="search-bar" action="javascript:void(0)">
    <div class="input-wrap">
      <span class="lead"><?= technician_my_jobs_icon_svg('search', 18, 'ref-icon-search') ?></span>
      <input
        id="myJobsSearchInput"
        class="input with-lead"
        type="search"
        data-history-search
        placeholder="ค้นหาจากรหัสงาน ชื่อลูกค้า เบอร์โทร วันที่ หรือที่อยู่..."
        autocomplete="off"
      >
    </div>

    <button class="btn btn-primary" type="submit" data-history-search-submit>
      <?= technician_my_jobs_icon_svg('search', 18, 'ref-icon-search') ?>
      <span>ค้นหา</span>
    </button>

    <button class="btn btn-outline" type="button" data-history-search-reset>
      <?= technician_my_jobs_icon_svg('reset', 18, 'ref-icon-muted') ?>
      <span>ล้างค้นหา</span>
    </button>
  </form>

  <div class="mb-16 flex items-center justify-between technician-filter-row">
    <div class="chip-group" aria-label="ตัวกรองสถานะงานของฉัน">
      <?php
      $status_tabs = [
          'all' => 'ทั้งหมด',
          'awaiting_receive' => 'รอรับสินค้า',
          'ready' => 'พร้อมติดตั้ง',
          'installing' => 'กำลังติดตั้ง',
          'awaiting_review' => 'รอหัวหน้าช่างยืนยัน',
          'done' => 'งานเสร็จสิ้น',
          'history' => 'ประวัติงาน',
      ];
      foreach ($status_tabs as $tab_key => $tab_label):
          $tab_url = app_system_url('technician/my_jobs.php?status=' . urlencode($tab_key));
      ?>
        <a
          class="chip <?= $status_filter === $tab_key ? 'active' : '' ?>"
          href="<?= h($tab_url) ?>"
          style="text-decoration: none;"
          data-history-filter="<?= h($tab_key) ?>"
          aria-current="<?= $status_filter === $tab_key ? 'page' : 'false' ?>"
        >
          <?= h($tab_label) ?> <span class="chip-count"><?= h((string) ($status_counts[$tab_key] ?? 0)) ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="text-xs text-muted flex items-center gap-4 technician-sort-note">
      <?= technician_my_jobs_icon_svg('info', 14, 'ref-icon-muted') ?>
      เรียงตามวันมอบหมายล่าสุด
    </div>
  </div>

  <div class="job-list my-jobs-table-wrap" data-technician-job-list>
    <table class="my-jobs-table">
      <thead>
        <tr>
          <th>รหัสงาน</th>
          <th>ลูกค้า</th>
          <th>สินค้า</th>
          <th>สถานะงาน</th>
          <th>วันที่ติดตั้ง</th>
          <th>เวลาติดตั้ง</th>
          <th>จัดการ</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($job_rows) === 0): ?>
          <tr>
            <td colspan="7">
              <div class="empty my-jobs-empty">
                <div class="empty-icon"><?= technician_my_jobs_icon_svg('clipboard-check', 30) ?></div>
                <h3><?= $status_filter === 'all' ? 'ยังไม่มีงานของฉัน' : 'ไม่พบงานในสถานะนี้' ?></h3>
                <p><?= $status_filter === 'all' ? 'รายการงานติดตั้งที่รับงานแล้วจะแสดงที่นี่' : 'ลองเลือกสถานะอื่นเพื่อดูรายการงาน' ?></p>
              </div>
            </td>
          </tr>
        <?php endif; ?>

        <?php if (count($job_rows) > 0): ?>
          <tr data-history-search-empty style="display: none;">
            <td colspan="7">
              <div class="empty my-jobs-empty">
                <div class="empty-icon"><?= technician_my_jobs_icon_svg('search', 30) ?></div>
                <h3>ไม่พบงานที่ตรงกับการค้นหา</h3>
                <p>ลองปรับคำค้นหาหรือเปลี่ยนหมวดหมู่</p>
              </div>
            </td>
          </tr>
        <?php endif; ?>

        <?php foreach ($job_rows as $row): ?>
          <?php
            $install_address = $row['setup_address'] ?: ($row['customer_address'] ?: ($row['setup_location'] ?? '-'));
            $assign_date_text = !empty($row['assign_date'])
              ? date('d/m/Y H:i', strtotime($row['assign_date']))
              : '-';

            $install_date_text = !empty($row['assign_install_date'])
              ? date('d/m/Y', strtotime($row['assign_install_date']))
              : '-';

            $install_time_text = !empty($row['assign_install_time'])
              ? date('H:i', strtotime($row['assign_install_time'])) . ' น.'
              : '-';

            $install_datetime_text = trim($install_date_text . ' ' . $install_time_text);
            $customer_name = trim((string) ($row['customer_name'] ?? '-'));
            $product_count = max(0, (int) ($row['item_count'] ?? 0));
            $detail_url = app_system_url('technician/job_detail.php?id=' . urlencode((string) ($row['assign_id'] ?? '')));
          ?>

          <tr
            class="my-job-row"
            data-detail-url="<?= h($detail_url) ?>"
            tabindex="0"
            aria-label="Open job details"
            data-history-item
            data-technician-history-card
            data-history-status="<?= h($row['ui_status_key'] ?? 'accepted') ?>"
            data-history-search-text="<?= h(strtolower(
                (string) ($row['assign_id'] ?? '') . ' ' .
                (string) ($row['setup_id'] ?? '') . ' ' .
                (string) ($row['customer_name'] ?? '') . ' ' .
                (string) ($row['customer_phone'] ?? '') . ' ' .
                $assign_date_text . ' ' .
                $install_datetime_text . ' ' .
                $install_address
            )) ?>"
          >
            <td class="my-job-id">
              <strong><?= h($row['assign_id'] ?? '-') ?></strong>
            </td>

            <td class="my-job-customer">
              <strong><?= h($customer_name) ?></strong>
            </td>

            <td class="my-job-products">
              <span class="qty-pill"><?= h((string) $product_count) ?> รายการ</span>
            </td>

            <td class="my-job-status">
              <span class="badge <?= h($row['ui_status_class'] ?? 'blue') ?>">
                <span class="dot"></span>
                <?= h($row['ui_status_label'] ?? assign_status_name($row['assign_status'] ?? '')) ?>
              </span>
            </td>

            <td class="my-job-install-date">
              <strong><?= h($install_date_text) ?></strong>
            </td>

            <td class="my-job-install-time" style="white-space: nowrap;">
              <strong><?= h($install_time_text) ?></strong>
            </td>

            <td class="my-job-actions-cell">
              <div class="job-actions">
                <a
                  class="btn btn-outline"
                  href="<?= h($detail_url) ?>"
                >
                  <?= technician_my_jobs_icon_svg('eye', 14) ?>
                  <span>รายละเอียด</span>
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<script
  src="<?= h(app_asset_url('sale/assets/js/setups.js')) ?>?v=<?= h(asset_version('sale/assets/js/setups.js')) ?>"
  defer
></script>
<script
  src="<?= h(app_asset_url('technician/assets/js/technician.js')) ?>?v=<?= h(asset_version('technician/assets/js/technician.js')) ?>"
  defer
></script>
<?php
layout_footer();
