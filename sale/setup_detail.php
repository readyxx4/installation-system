<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('2');

$currentSaleId = trim((string) ($_SESSION['user_id'] ?? ''));
$setupId = trim((string) ($_GET['id'] ?? ''));

if ($currentSaleId === '') {
    redirect_to(app_system_url('login.php'));
}

if ($setupId === '') {
    redirect_to(app_system_url('sale/setup_history.php'));
}

function sale_detail_column_exists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function sale_detail_date(?string $value, bool $withTime = false): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '--';
    }

    $timestamp = strtotime($value);

    if (!$timestamp) {
        return $value;
    }

    return $withTime
        ? date('d/m/Y H:i', $timestamp)
        : date('d/m/Y', $timestamp);
}

function sale_detail_time(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '--';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('H:i', $timestamp) : $value;
}

$installDateSelect = sale_detail_column_exists($conn, 'assignment', 'assign_install_date')
    ? 'a.assign_install_date'
    : 'NULL AS assign_install_date';

$installTimeSelect = sale_detail_column_exists($conn, 'assignment', 'assign_install_time')
    ? 'a.assign_install_time'
    : 'NULL AS assign_install_time';

$setupStmt = $conn->prepare("
    SELECT
        s.setup_id,
        s.user_id,
        s.sale_id,
        s.setup_date,
        s.setup_location,
        s.setup_address,
        s.setup_note,
        s.setup_status,
        s.created_at,

        u.user_name,
        u.user_phone,
        u.user_email,
        u.user_address,

        a.assign_id,
        a.assign_by,
        a.assign_date,
        a.assign_status,
        {$installDateSelect},
        {$installTimeSelect},

        t.tech_id,
        COALESCE(NULLIF(TRIM(t.tech_fullname), ''), NULLIF(TRIM(t.tech_name), ''), t.tech_id) AS technician_name,
        t.tech_phone AS technician_phone,
        COALESCE(NULLIF(TRIM(assigner.user_name), ''), a.assign_by) AS assigner_name
    FROM setup s
    LEFT JOIN `user` u
        ON s.user_id = u.user_id
    LEFT JOIN assignment a
        ON a.setup_id = s.setup_id
       AND a.assign_id = (
            SELECT aa.assign_id
            FROM assignment aa
            WHERE aa.setup_id = s.setup_id
            ORDER BY aa.assign_date DESC, aa.assign_id DESC
            LIMIT 1
       )
    LEFT JOIN technicians t
        ON TRIM(a.tech_id) = TRIM(t.tech_id)
    LEFT JOIN `user` assigner
        ON a.assign_by = assigner.user_id
    WHERE s.setup_id = ?
      AND s.sale_id = ?
    LIMIT 1
");

$setupStmt->bind_param('ss', $setupId, $currentSaleId);
$setupStmt->execute();
$setup = $setupStmt->get_result()->fetch_assoc();

if (!$setup) {
    redirect_to(app_system_url('sale/setup_history.php?status=notfound'));
}

$items = [];

$detailStmt = $conn->prepare("
    SELECT
        d.pro_id,
        COALESCE(p.pro_name, d.pro_id) AS pro_name,
        COALESCE(d.install_qty, 1) AS install_qty,
        COALESCE(d.install_price, p.pro_price_install, 0) AS install_price,
        COALESCE(
            d.install_total,
            COALESCE(d.install_qty, 1) * COALESCE(d.install_price, p.pro_price_install, 0)
        ) AS install_total
    FROM install_detail d
    LEFT JOIN product p
        ON d.pro_id = p.pro_id
    WHERE d.setup_id = ?
    ORDER BY d.detail_id ASC
");

$detailStmt->bind_param('s', $setupId);
$detailStmt->execute();
$detailResult = $detailStmt->get_result();

while ($item = $detailResult->fetch_assoc()) {
    $items[] = $item;
}

$itemCount = count($items);
$totalAmount = 0.0;

foreach ($items as $item) {
    $totalAmount += (float) ($item['install_total'] ?? 0);
}

/*
 * Sale เห็นเฉพาะสถานะภาพรวม
 * ไม่แสดงสถานะย่อยของ workflow หัวหน้าช่าง
 */
$setupStatus = (int) ($setup['setup_status'] ?? 0);
$hasAssignment = !empty($setup['assign_id']);

if ($setupStatus === 5) {
    $saleStatusText = 'ยกเลิก';
    $saleStatusClass = 'cancelled';
} elseif ($setupStatus === 2) {
    $saleStatusText = 'เสร็จสิ้น';
    $saleStatusClass = 'done';
} elseif ($hasAssignment) {
    $saleStatusText = 'อยู่ระหว่างดำเนินการ';
    $saleStatusClass = 'progress';
} else {
    $saleStatusText = 'รอมอบหมายงาน';
    $saleStatusClass = 'waiting';
}

$assignmentId = '--';
$assignmentDate = '--';
$assignerName = '--';
$technicianName = '--';
$installDate = '--';
$installTime = '--';
$installDateTime = '--';

if ($hasAssignment) {
    $assignmentId = trim((string) ($setup['assign_id'] ?? ''));
    if ($assignmentId === '') {
        $assignmentId = '--';
    }

    $assignmentDate = sale_detail_date(
        $setup['assign_date'] ?? null,
        true
    );

    $assignerName = trim((string) ($setup['assigner_name'] ?? ''));
    if ($assignerName === '') {
        $assignerName = '--';
    }

    $technicianName = trim((string) ($setup['technician_name'] ?? ''));
    if ($technicianName === '') {
        $technicianName = '--';
    }

    $installDate = sale_detail_date(
        $setup['assign_install_date'] ?? null
    );

    $installTime = sale_detail_time(
        $setup['assign_install_time'] ?? null
    );

    if ($installDate !== '--' && $installTime !== '--') {
        $installDateTime = $installDate . ' ' . $installTime;
    }
}

layout_header('รายละเอียดใบงานติดตั้ง', 'setup_history');
?>

<link
  rel="stylesheet"
  href="<?= h(app_asset_url('sale/assets/css/setup_detail.css')) ?>?v=<?= h(asset_version('sale/assets/css/setup_detail.css')) ?>"
>

<section class="sale-detail-page">
    <!-- การ์ดสรุปใบงาน -->
    <section class="sale-detail-summary-card">
        <div class="sale-detail-summary-head">
            <div>
                <a
                    class="sale-detail-back"
                    href="<?= h(app_system_url('sale/setup_history.php')) ?>"
                >
                    ← ประวัติใบงาน
                </a>

                <div class="sale-detail-summary-title-row">
                    <div class="sale-detail-summary-title">
                        <h1>สรุปใบงาน</h1>

                        <p class="sale-detail-setup-code">
                            รหัสใบงาน
                            <strong><?= h($setup['setup_id']) ?></strong>
                        </p>

                        <p class="sale-detail-summary-subtitle">
                            ตรวจสอบรายละเอียดใบงานติดตั้ง
                        </p>
                    </div>

                    <div class="sale-detail-status-area">
                        <span class="sale-detail-status <?= h($saleStatusClass) ?>">
                            สถานะใบงานนี้ :
                            <strong><?= h($saleStatusText) ?></strong>
                        </span>
                    </div>
                </div>
            </div>

            <a
                class="sale-detail-slip-btn"
                href="<?= h(app_system_url('sale/setup_slip.php?id=' . urlencode($setupId))) ?>"
            >
                ดูใบติดตั้ง
            </a>
        </div>

        <div class="sale-detail-customer-box">
            <div class="sale-detail-customer-main">
                <div class="sale-detail-customer-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M20 21a8 8 0 0 0-16 0"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>

                <div class="sale-detail-customer-content">
                    <div class="sale-detail-customer-name">
                        <small>ลูกค้า</small>
                        <strong><?= h($setup['user_name'] ?? '--') ?></strong>
                    </div>

                    <div class="sale-detail-customer-contact">
                        <div>
                            <span>เบอร์โทร</span>
                            <strong><?= h($setup['user_phone'] ?? '--') ?></strong>
                        </div>

                        <div>
                            <span>อีเมล</span>
                            <strong><?= h($setup['user_email'] ?? '--') ?></strong>
                        </div>

                        <div>
                            <span>ที่อยู่ลูกค้า</span>
                            <strong><?= h($setup['user_address'] ?? '--') ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sale-detail-customer-id">
                <span>รหัสลูกค้า</span>
                <strong><?= h($setup['user_id'] ?? '--') ?></strong>
            </div>
        </div>

        <div class="sale-detail-work-box">
            <div class="sale-detail-work-meta">
                <div>
                    <span>วันที่สร้างใบงาน</span>
                    <strong><?= h(sale_detail_date($setup['created_at'] ?? null, true)) ?></strong>
                </div>

                <div>
                    <span>จำนวนสินค้า</span>
                    <strong><?= h((string) $itemCount) ?> รายการ</strong>
                </div>

                <div class="wide">
                    <span>ที่อยู่ลูกค้า</span>
                    <strong><?= h($setup['user_address'] ?? '--') ?></strong>
                </div>
            </div>

            <div class="sale-detail-product-table">
                <div class="sale-detail-product-head">
                    <span>สินค้า</span>
                    <span>จำนวน</span>
                    <span>ค่าติดตั้ง/หน่วย</span>
                    <span>รวม</span>
                </div>

                <?php if ($itemCount === 0): ?>
                    <div class="sale-detail-empty">ไม่พบรายการสินค้า</div>
                <?php endif; ?>

                <?php foreach ($items as $item): ?>
                    <div class="sale-detail-product-row">
                        <div class="sale-detail-product-main">
                            <strong><?= h($item['pro_name'] ?? '--') ?></strong>
                        </div>

                        <span><?= h((string) ($item['install_qty'] ?? 0)) ?> ชิ้น</span>

                        <span>
                            <?= h(number_format((float) ($item['install_price'] ?? 0), 2)) ?> บาท
                        </span>

                        <strong class="sale-detail-product-total">
                            <?= h(number_format((float) ($item['install_total'] ?? 0), 2)) ?> บาท
                        </strong>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="sale-detail-work-bottom">
                <div class="sale-detail-note">
                    <span>หมายเหตุ</span>
                    <strong>
                        <?= h(
                            trim((string) ($setup['setup_note'] ?? '')) !== ''
                                ? $setup['setup_note']
                                : '--'
                        ) ?>
                    </strong>
                </div>

                <div class="sale-detail-total-row">
                    <span>รวมค่าติดตั้ง</span>
                    <strong><?= h(number_format($totalAmount, 2)) ?> บาท</strong>
                </div>
            </div>
        </div>
    </section>

    <!-- การ์ดข้อมูลการดำเนินงาน แยกออกมา -->
    <section class="sale-detail-process-card">
        <div class="sale-detail-process-head">
            <div>
                <h2>ข้อมูลการมอบหมายงาน</h2>
                <p>ข้อมูลหลังหัวหน้าช่างดำเนินการมอบหมาย</p>
            </div>
        </div>

        <div class="sale-detail-process-row">
            <div>
                <span>รหัสงานมอบหมาย</span>
                <strong><?= h($assignmentId) ?></strong>
            </div>

            <div>
                <span>วันที่มอบหมาย</span>
                <strong><?= h($assignmentDate) ?></strong>
            </div>

            <div>
                <span>ผู้มอบหมายงาน</span>
                <strong><?= h($assignerName) ?></strong>
            </div>

            <div>
                <span>ช่างผู้ติดตั้ง</span>
                <strong><?= h($technicianName) ?></strong>
            </div>

            <div>
                <span>วันที่-เวลา</span>
                <strong><?= h($installDateTime) ?></strong>
            </div>
        </div>
    </section>
</section>

<?php
layout_footer();
