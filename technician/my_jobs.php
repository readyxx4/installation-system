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

if ($tech_id === '') {
    redirect_to(app_public_url('login.html?error=login'));
}

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
        return ['key' => 'done', 'label' => 'เสร็จแล้ว', 'class' => 'success'];
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
      AND a.assign_status IN (2, 5)
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
    'done' => 0,
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

    $status_counts['all'] += 1;
    if (isset($status_counts[$ui_status['key']])) {
        $status_counts[$ui_status['key']] += 1;
    }

    $job_rows[] = $job_row;
}

layout_header('งานของฉัน', 'my_jobs', 'รายการงานติดตั้งที่รับงานแล้ว สำหรับตรวจสอบและดำเนินการขั้นตอนถัดไป');
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
  <div class="page-header">
    <h2>งานของฉัน</h2>
    <p>รายการงานติดตั้งที่รับงานแล้ว สำหรับตรวจสอบและดำเนินการขั้นตอนถัดไป</p>
  </div>

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
      <button type="button" class="chip active" data-history-filter="all">
        ทั้งหมด <span class="chip-count"><?= h((string) $status_counts['all']) ?></span>
      </button>

      <button type="button" class="chip" data-history-filter="awaiting_receive">
        รอรับสินค้า <span class="chip-count"><?= h((string) $status_counts['awaiting_receive']) ?></span>
      </button>

      <button type="button" class="chip" data-history-filter="ready">
        พร้อมติดตั้ง <span class="chip-count"><?= h((string) $status_counts['ready']) ?></span>
      </button>

      <button type="button" class="chip" data-history-filter="done">
        เสร็จแล้ว <span class="chip-count"><?= h((string) $status_counts['done']) ?></span>
      </button>
    </div>

    <div class="text-xs text-muted flex items-center gap-4 technician-sort-note">
      <?= technician_my_jobs_icon_svg('info', 14, 'ref-icon-muted') ?>
      เรียงตามวันมอบหมายล่าสุด
    </div>
  </div>

  <div class="job-list" data-technician-job-list>
    <?php if (count($job_rows) === 0): ?>
      <div class="card">
        <div class="empty">
          <div class="empty-icon"><?= technician_my_jobs_icon_svg('clipboard-check', 30) ?></div>
          <h3>ยังไม่มีงานที่รับแล้ว</h3>
          <p>รายการงานติดตั้งที่รับงานแล้วจะแสดงที่นี่</p>
        </div>
      </div>
    <?php endif; ?>

    <?php if (count($job_rows) > 0): ?>
      <div class="card" data-history-search-empty style="display: none;">
        <div class="empty">
          <div class="empty-icon"><?= technician_my_jobs_icon_svg('search', 30) ?></div>
          <h3>ไม่พบงานที่ตรงกับการค้นหา</h3>
          <p>ลองปรับคำค้นหาหรือเปลี่ยนหมวดหมู่</p>
        </div>
      </div>
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
        $customer_initial = function_exists('mb_substr') ? mb_substr($customer_name, 0, 1, 'UTF-8') : substr($customer_name, 0, 1);
        $product_count = max(0, (int) ($row['item_count'] ?? 0));
      ?>

      <article
        class="job-card"
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
        <div class="job-card-head">
          <div class="flex items-center gap-12">
            <div class="job-id">
              <?= technician_my_jobs_icon_svg('hash', 13) ?>
              <strong><?= h($row['assign_id'] ?? '-') ?></strong>
            </div>

            <span class="badge <?= h($row['ui_status_class'] ?? 'blue') ?>">
              <span class="dot"></span>
              <?= h($row['ui_status_label'] ?? assign_status_name($row['assign_status'] ?? '')) ?>
            </span>
          </div>

          <div class="text-xs text-muted flex items-center gap-4">
            <?= technician_my_jobs_icon_svg('clock', 12, 'ref-icon-muted') ?>
            มอบหมาย <?= h($assign_date_text) ?>
          </div>
        </div>

        <div class="job-body">
          <div class="job-field">
            <div class="job-field-label"><?= technician_my_jobs_icon_svg('user', 12, 'ref-icon-muted') ?> ลูกค้า</div>
            <div class="job-field-value">
              <div class="customer-avatar"><?= h($customer_initial !== '' ? $customer_initial : '-') ?></div>
              <div>
                <div><?= h($customer_name) ?></div>
                <div class="job-field-sub"><?= h($row['customer_phone'] ?? '-') ?></div>
              </div>
            </div>
          </div>

          <div class="job-field">
            <div class="job-field-label"><?= technician_my_jobs_icon_svg('calendar', 12, 'ref-icon-muted') ?> วันติดตั้ง</div>
            <div class="job-field-value">
              <?= h($install_date_text) ?>
              <span class="job-field-sub inline-sub">· <?= h($install_time_text) ?></span>
            </div>
            <div class="job-field-sub address-sub">
              <?= technician_my_jobs_icon_svg('map-pin', 12, 'ref-icon-muted') ?>
              <?= h($install_address) ?>
            </div>
          </div>

          <div class="job-field">
            <div class="job-field-label"><?= technician_my_jobs_icon_svg('package', 12, 'ref-icon-muted') ?> รายการสินค้า</div>
            <div class="job-field-value product-summary-line">
              <span class="qty-pill"><?= h((string) $product_count) ?> รายการ</span>
              <div class="job-total">
                <span class="job-total-label">ยอดรวม</span>
                <span class="job-total-value">฿<?= h(number_format((float) ($row['install_total'] ?? 0), 2)) ?><span class="unit">บาท</span></span>
              </div>
            </div>
          </div>
        </div>

        <div class="job-card-foot">
          <div class="job-actions">
            <a
              class="btn btn-outline"
              href="<?= h(app_system_url('technician/job_detail.php?id=' . urlencode((string) ($row['assign_id'] ?? '')))) ?>"
            >
              <?= technician_my_jobs_icon_svg('eye', 14) ?>
              <span>รายละเอียด</span>
            </a>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
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
