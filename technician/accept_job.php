<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('technician');

$tech_id = $_SESSION['user_id'] ?? '';
$focus_assign_id = trim($_GET['focus'] ?? '');

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

function assign_status_badge($status): string
{
    return match ((string) $status) {
        '0' => 'orange',
        '1' => 'blue',
        '2' => 'green',
        '3' => 'red',
        '4' => 'red',
        '5' => 'green',
        default => 'red',
    };
}

function setup_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'รอมอบหมายงาน',
        '1' => 'มอบหมายงานแล้ว',
        '2' => 'ช่างรับงานแล้ว',
        '3' => 'กำลังติดตั้ง',
        '4' => 'เสร็จสิ้น',
        default => 'ไม่ทราบสถานะ',
    };
}

function technician_accept_icon_svg(string $name, int $size = 14, string $class = ''): string
{
    $paths = [
        'search' => '<circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path>',
        'info' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4M12 8h.01"></path>',
        'clipboard-check' => '<rect x="8" y="3" width="8" height="4" rx="1"></rect><path d="M8 5H6a2 2 0 00-2 2v13a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-2"></path><path d="M9 14l2 2 4-4"></path>',
        'hash' => '<path d="M4 9h16M4 15h16M10 3L8 21M16 3l-2 18"></path>',
        'clock' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path>',
        'user' => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path>',
        'map-pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path><circle cx="12" cy="10" r="3"></circle>',
        'package' => '<path d="M16.5 9.4L7.5 4.2M21 16V8a2 2 0 00-1-1.7l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.7l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"></path><path d="M3.3 7L12 12l8.7-5M12 22V12"></path>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>',
        'check' => '<path d="M20 6L9 17l-5-5"></path>',
        'x' => '<path d="M18 6L6 18M6 6l12 12"></path>',
    ];

    if (!isset($paths[$name])) {
        return '';
    }

    $classes = trim('ref-icon ' . $class);

    return '<svg class="' . h($classes) . '" width="' . h((string) $size) . '" height="' . h((string) $size) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}

function technician_accept_is_urgent($installDate, $installTime): bool
{
    $installDate = trim((string) $installDate);
    $installTime = trim((string) $installTime);

    if ($installDate === '') {
        return false;
    }

    $timezone = new DateTimeZone('Asia/Bangkok');
    $now = new DateTimeImmutable('now', $timezone);

    if ($installTime !== '') {
        $time = $installTime;
        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            $time .= ':00';
        }

        $installAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $installDate . ' ' . $time, $timezone);
        if (!$installAt) {
            $installAt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $installDate . ' ' . substr($time, 0, 5), $timezone);
        }

        if ($installAt instanceof DateTimeImmutable) {
            return $installAt >= $now && $installAt <= $now->modify('+24 hours');
        }
    }

    $installDay = DateTimeImmutable::createFromFormat('!Y-m-d', $installDate, $timezone);
    if (!$installDay) {
        return false;
    }

    $today = $now->setTime(0, 0, 0);
    $tomorrow = $today->modify('+1 day');

    return $installDay >= $today && $installDay <= $tomorrow;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assign_id = trim($_POST['assign_id'] ?? '');
    $action = trim($_POST['action'] ?? '');

    if ($assign_id === '' || !in_array($action, ['accept', 'reject'], true)) {
        redirect_to(app_system_url('technician/accept_job.php?status=error'));
    }

    try {
        $stmt = $conn->prepare("
            SELECT assign_id, setup_id, tech_id, assign_status
            FROM assignment
            WHERE assign_id = ?
              AND tech_id = ?
            LIMIT 1
        ");

        $stmt->bind_param('ss', $assign_id, $tech_id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows !== 1) {
            redirect_to(app_system_url('technician/accept_job.php?status=error'));
        }

        $assignment = $result->fetch_assoc();

        if ((int) $assignment['assign_status'] !== 1) {
            redirect_to(app_system_url('technician/accept_job.php?status=error'));
        }

        $setup_id = $assignment['setup_id'];

        $conn->begin_transaction();

        if ($action === 'accept') {
            $assign_status = 2;
            $setup_status = 2;

            $update_assign = $conn->prepare("
                UPDATE assignment
                SET assign_status = ?
                WHERE assign_id = ?
                  AND tech_id = ?
            ");

            $update_assign->bind_param(
                'iss',
                $assign_status,
                $assign_id,
                $tech_id
            );

            $update_assign->execute();

            $update_setup = $conn->prepare("
                UPDATE setup
                SET setup_status = ?
                WHERE setup_id = ?
            ");

            $update_setup->bind_param(
                'is',
                $setup_status,
                $setup_id
            );

            $update_setup->execute();

            $conn->commit();

            redirect_to(app_system_url('technician/my_jobs.php?status=accept_updated'));
        }

        if ($action === 'reject') {
            $assign_status = 3;
            $setup_status = 0;

            $update_assign = $conn->prepare("
                UPDATE assignment
                SET assign_status = ?
                WHERE assign_id = ?
                  AND tech_id = ?
            ");

            $update_assign->bind_param(
                'iss',
                $assign_status,
                $assign_id,
                $tech_id
            );

            $update_assign->execute();

            $update_setup = $conn->prepare("
                UPDATE setup
                SET setup_status = ?
                WHERE setup_id = ?
            ");

            $update_setup->bind_param(
                'is',
                $setup_status,
                $setup_id
            );

            $update_setup->execute();

            $conn->commit();

            redirect_to(app_system_url('technician/accept_job.php?status=updated'));
        }
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // ข้าม
        }

        redirect_to(app_system_url('technician/accept_job.php?status=error'));
    }
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
        c.customer_email,
        c.customer_address,

        p.pro_name,

        COALESCE(ds.item_count, 0) AS item_count,
        COALESCE(ds.install_total, 0) AS install_total
    FROM assignment a
    LEFT JOIN setup s ON a.setup_id = s.setup_id
    LEFT JOIN customers c ON s.customer_id = c.customer_id
    LEFT JOIN product p ON s.pro_id = p.pro_id
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
      AND a.assign_status = 1
    ORDER BY a.assign_date DESC, a.assign_id DESC
");

$stmt_jobs->bind_param('s', $tech_id);
$stmt_jobs->execute();
$jobs_result = $stmt_jobs->get_result();
$job_rows = [];
$urgent_job_count = 0;
while ($job_row = $jobs_result->fetch_assoc()) {
    $job_row['is_accept_urgent'] = technician_accept_is_urgent(
        $job_row['assign_install_date'] ?? '',
        $job_row['assign_install_time'] ?? ''
    );

    if ($job_row['is_accept_urgent']) {
        $urgent_job_count++;
    }

    $job_rows[] = $job_row;
}

layout_header('ยืนยันการรับงาน', 'accept_job', 'ตรวจสอบงานที่ได้รับมอบหมาย แล้วเลือกรับหรือปฏิเสธ');
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
  href="<?= h(app_asset_url('technician/assets/css/accept_job.css')) ?>?v=<?= h(asset_version('technician/assets/css/accept_job.css')) ?>"
>

<?= flash_message() ?>

<section class="page technician-assignments-page">
  <div class="page-header">
    <h1>ยืนยันการรับงานติดตั้ง</h1>
    <p>ตรวจสอบงานที่ได้รับมอบหมายจากหัวหน้างานล่วงหน้า แล้วเลือกรับงานหรือปฏิเสธงาน</p>
  </div>

  <form class="search-bar" action="javascript:void(0)">
    <div class="input-wrap">
      <span class="lead"><?= technician_accept_icon_svg('search', 18, 'ref-icon-search') ?></span>
      <input
        id="acceptJobSearchInput"
        class="input with-lead"
        type="search"
        data-history-search
        placeholder="ค้นหาจากรหัสงาน ชื่อลูกค้า เบอร์โทร วันที่ หรือที่อยู่..."
        autocomplete="off"
      >
    </div>

    <button class="btn btn-primary" type="submit" data-history-search-submit>
      <?= technician_accept_icon_svg('search', 18, 'ref-icon-search') ?>
      <span>ค้นหา</span>
    </button>

    <a
      class="btn btn-outline"
      href="<?= h(app_system_url('technician/accept_job.php')) ?>"
      data-history-search-reset
    >
      <span>ล้างค้นหา</span>
    </a>
  </form>

  <div class="mb-16 flex items-center justify-between technician-filter-row">
    <div class="chip-group" aria-label="ตัวกรองสถานะงานที่รอยืนยัน">
      <button type="button" class="chip active" data-history-filter="all">
        ทั้งหมด <span class="chip-count"><?= h((string) count($job_rows)) ?></span>
      </button>

      <button type="button" class="chip" data-history-filter="urgent">
        งานด่วน <span class="chip-count"><?= h((string) $urgent_job_count) ?></span>
      </button>
    </div>

    <div class="text-xs text-muted flex items-center gap-4 technician-sort-note">
      <?= technician_accept_icon_svg('info', 14, 'ref-icon-muted') ?>
      เรียงตามวันมอบหมายล่าสุด
    </div>
  </div>

  <div class="job-list" data-technician-job-list>
    <?php if (count($job_rows) === 0): ?>
      <div class="card">
        <div class="empty">
          <div class="empty-icon"><?= technician_accept_icon_svg('clipboard-check', 30) ?></div>
          <h3>ไม่พบงานที่ตรงกัน</h3>
          <p>ยังไม่มีงานที่รอยืนยันการรับงาน</p>
        </div>
      </div>
    <?php endif; ?>

    <?php if (count($job_rows) > 0): ?>
      <div class="card" data-history-search-empty style="display: none;">
        <div class="empty">
          <div class="empty-icon"><?= technician_accept_icon_svg('search', 30) ?></div>
          <h3>ไม่พบงานที่ตรงกัน</h3>
          <p>ลองปรับคำค้นหาหรือเปลี่ยนหมวดหมู่</p>
        </div>
      </div>
    <?php endif; ?>

    <?php foreach ($job_rows as $row): ?>
      <?php
        $is_focused_row = $focus_assign_id !== '' && hash_equals((string) ($row['assign_id'] ?? ''), $focus_assign_id);
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
        $job_warning_badge = (bool) ($row['is_accept_urgent'] ?? false);
      ?>

      <article
        class="job-card <?= $is_focused_row ? 'technician-focused-assignment-row' : '' ?>"
        <?= $is_focused_row ? 'data-technician-focus-row="true"' : '' ?>
        data-history-item
        data-technician-history-card
        data-history-status="<?= !empty($row['is_accept_urgent']) ? 'urgent' : 'assigned' ?>"
        data-history-search-text="<?= h(strtolower(
            (string) ($row['assign_id'] ?? '') . ' ' .
            (string) ($row['setup_id'] ?? '') . ' ' .
            (string) ($row['customer_name'] ?? '') . ' ' .
            (string) ($row['customer_phone'] ?? '') . ' ' .
            (string) ($row['assign_install_date'] ?? '') . ' ' .
            (string) ($row['assign_install_time'] ?? '') . ' ' .
            $assign_date_text . ' ' .
            $install_datetime_text . ' ' .
            $install_address
        )) ?>"
      >
        <div class="job-card-head">
          <div class="flex items-center gap-12">
            <div class="job-id">
              <?= technician_accept_icon_svg('hash', 13) ?>
              <strong><?= h($row['assign_id'] ?? '-') ?></strong>
            </div>

            <?php if ($job_warning_badge): ?>
              <span class="badge warning accept-urgent-badge"><span class="dot"></span>งานด่วน</span>
            <?php endif; ?>
          </div>

          <div class="text-xs text-muted flex items-center gap-4">
            <?= technician_accept_icon_svg('clock', 12, 'ref-icon-muted') ?>
            มอบหมาย <?= h($assign_date_text) ?>
          </div>
        </div>

        <div class="job-body">
          <div class="job-field">
            <div class="job-field-label"><?= technician_accept_icon_svg('user', 12, 'ref-icon-muted') ?> ลูกค้า</div>
            <div class="job-field-value">
              <div class="customer-avatar"><?= h($customer_initial !== '' ? $customer_initial : '-') ?></div>
              <div>
                <div><?= h($customer_name) ?></div>
                <div class="job-field-sub"><?= h($row['customer_phone'] ?? '-') ?></div>
              </div>
            </div>
          </div>

          <div class="job-field">
            <div class="job-field-label"><?= technician_accept_icon_svg('calendar', 12, 'ref-icon-muted') ?> วันติดตั้ง</div>
            <div class="job-field-value">
              <?= h($install_date_text) ?>
              <span class="job-field-sub inline-sub">· <?= h($install_time_text) ?></span>
            </div>
            <div class="job-field-sub address-sub">
              <?= technician_accept_icon_svg('map-pin', 12, 'ref-icon-muted') ?>
              <?= h($install_address) ?>
            </div>
          </div>

          <div class="job-field">
            <div class="job-field-label"><?= technician_accept_icon_svg('package', 12, 'ref-icon-muted') ?> รายการสินค้า</div>
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
              <?= technician_accept_icon_svg('eye', 14) ?>
              <span>รายละเอียด</span>
            </a>

            <form class="cs-inline-action-form" method="POST" action="<?= h(app_system_url('technician/accept_job.php')) ?>">
              <input type="hidden" name="assign_id" value="<?= h($row['assign_id']) ?>">
              <input type="hidden" name="action" value="accept">

              <button
                class="btn btn-success"
                type="submit"
                data-technician-confirm="ยืนยันรับงานนี้หรือไม่?"
              >
                <?= technician_accept_icon_svg('check', 14) ?>
                <span>รับงาน</span>
              </button>
            </form>

            <form class="cs-inline-action-form" method="POST" action="<?= h(app_system_url('technician/accept_job.php')) ?>">
              <input type="hidden" name="assign_id" value="<?= h($row['assign_id']) ?>">
              <input type="hidden" name="action" value="reject">

              <button
                class="btn btn-danger-ghost"
                type="submit"
                data-technician-confirm="ต้องการปฏิเสธงานนี้หรือไม่?"
              >
                <?= technician_accept_icon_svg('x', 14) ?>
                <span>ปฏิเสธ</span>
              </button>
            </form>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<script
  src="<?= h(app_asset_url('technician/assets/js/technician.js')) ?>?v=<?= h(asset_version('technician/assets/js/technician.js')) ?>"
  defer
></script>
<?php
layout_footer();





