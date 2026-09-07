<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('1');

date_default_timezone_set('Asia/Bangkok');

$setupId = trim((string) ($_GET['id'] ?? $_GET['setup_id'] ?? ''));

if ($setupId === '') {
    redirect_to(app_system_url('manager/assignment_history.php'));
}

function manager_detail_date(?string $value, bool $withTime = false): string
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

function manager_detail_time(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '--';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('H:i', $timestamp) : $value;
}

function manager_detail_time_range(?string $start, ?string $end): string
{
    $startText = manager_detail_time($start);
    $endText = manager_detail_time($end);

    if ($startText === '--') {
        return '--';
    }

    if ($endText !== '--') {
        return $startText . ' - ' . $endText . ' น.';
    }

    return $startText . ' น.';
}

function manager_assignment_status_text($status): string
{
    return match ((string) $status) {
        '0' => 'ยังไม่มอบหมาย',
        '1' => 'มอบหมายงานแล้ว',
        '2' => 'ช่างรับงานแล้ว',
        '3' => 'ช่างปฏิเสธงาน',
        '4' => 'ยกเลิกการมอบหมาย',
        '5' => 'งานเสร็จสิ้น',
        default => 'ไม่ทราบสถานะ',
    };
}

$setupStmt = $conn->prepare("
    SELECT
        s.setup_id,
        s.customer_id AS user_id,
        s.sale_id,
        s.setup_date,
        s.setup_location,
        s.setup_address,
        s.setup_note,
        s.setup_status,
        s.created_at,

        c.customer_name AS user_name,
        c.customer_phone AS user_phone,
        c.customer_email AS user_email,
        c.customer_address AS user_address,

        a.assign_id,
        a.assign_by,
        a.assign_date,
        a.assign_install_date,
        a.assign_install_time,
        a.assign_install_end_time,
        a.assign_status,

        t.tech_id,
        COALESCE(NULLIF(TRIM(t.tech_name), ''), t.tech_id) AS technician_name,
        t.tech_phone AS technician_phone,
        COALESCE(NULLIF(TRIM(assigner.user_name), ''), a.assign_by) AS assigner_name
    FROM setup s
    LEFT JOIN customers c
        ON s.customer_id = c.customer_id
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
    LIMIT 1
");

$setupStmt->bind_param('s', $setupId);
$setupStmt->execute();
$setup = $setupStmt->get_result()->fetch_assoc();

if (!$setup) {
    redirect_to(app_system_url('manager/assignment_history.php?status=notfound'));
}

$items = [];
$detailStmt = $conn->prepare("
    SELECT
        d.pro_id,
        COALESCE(p.pro_name, d.pro_id) AS pro_name,
        COALESCE(d.install_qty, 1) AS install_qty,
        COALESCE(p.pro_price_install, 0) AS install_price,
        COALESCE(d.install_qty, 1) * COALESCE(p.pro_price_install, 0) AS install_total
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

$assignStatus = (string) ($setup['assign_status'] ?? '');
$managerStatusText = manager_assignment_status_text($assignStatus);
$managerStatusClass = $assignStatus === '4' ? 'cancelled' : 'progress';

$assignmentId = trim((string) ($setup['assign_id'] ?? ''));
$assignmentId = $assignmentId !== '' ? $assignmentId : '--';
$assignmentDate = manager_detail_date($setup['assign_date'] ?? null, true);
$assignerName = trim((string) ($setup['assigner_name'] ?? ''));
$assignerName = $assignerName !== '' ? $assignerName : '--';
$technicianName = trim((string) ($setup['technician_name'] ?? ''));
$technicianName = $technicianName !== '' ? $technicianName : '--';
$installDate = manager_detail_date($setup['assign_install_date'] ?? null);
$installTime = manager_detail_time_range(
    $setup['assign_install_time'] ?? null,
    $setup['assign_install_end_time'] ?? null
);
$installDateTime = $installDate !== '--' && $installTime !== '--'
    ? $installDate . ' ' . $installTime
    : '--';

$productReceive = null;
$receiveProofUrl = '';
if ($assignmentId !== '--') {
    $receiveStmt = $conn->prepare("
        SELECT
            receive_id,
            assign_id,
            receive_date,
            receive_status,
            receive_note,
            receive_proof
        FROM product_receive
        WHERE assign_id = ?
        LIMIT 1
    ");
    $receiveStmt->bind_param('s', $assignmentId);
    $receiveStmt->execute();
    $productReceive = $receiveStmt->get_result()->fetch_assoc() ?: null;

    if (!empty($productReceive['receive_proof'])) {
        $receiveProofUrl = app_public_url((string) $productReceive['receive_proof']);
    }
}

$assignmentHistory = [];
$historyStmt = $conn->prepare("
    SELECT
        a.assign_id,
        a.assign_date,
        a.assign_install_date,
        a.assign_install_time,
        a.assign_install_end_time,
        a.assign_status,
        a.tech_id,
        COALESCE(NULLIF(TRIM(t.tech_name), ''), a.tech_id, '--') AS technician_name
    FROM assignment a
    LEFT JOIN technicians t
        ON TRIM(a.tech_id) = TRIM(t.tech_id)
    WHERE a.setup_id = ?
    ORDER BY a.assign_date DESC, a.assign_id DESC
");
$historyStmt->bind_param('s', $setupId);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();

while ($history = $historyResult->fetch_assoc()) {
    $assignmentHistory[] = $history;
}

layout_header('รายละเอียดงานมอบหมาย', 'assignment_history');
?>

<link
  rel="stylesheet"
  href="<?= h(app_asset_url('sale/assets/css/setup_detail.css')) ?>?v=<?= h(asset_version('sale/assets/css/setup_detail.css')) ?>"
>

<section class="sale-detail-page manager-assignment-detail-page">
    <section class="sale-detail-summary-card">
        <div class="sale-detail-summary-head">
            <div>
                <a
                    class="sale-detail-back"
                    href="<?= h(app_system_url('manager/assignment_history.php?status=canceled')) ?>"
                >
                    ← ประวัติการมอบหมายงาน
                </a>

                <div class="sale-detail-summary-title-row">
                    <div class="sale-detail-summary-title">
                        <h1>สรุปงานมอบหมาย</h1>

                        <p class="sale-detail-setup-code">
                            รหัสใบงาน
                            <strong><?= h($setup['setup_id']) ?></strong>
                        </p>

                        <p class="sale-detail-summary-subtitle">
                            ตรวจสอบรายละเอียดใบงานและข้อมูลการมอบหมาย
                        </p>
                    </div>

                    <div class="sale-detail-status-area">
                        <span class="sale-detail-status <?= h($managerStatusClass) ?>">
                            สถานะงานนี้ :
                            <strong><?= h($managerStatusText) ?></strong>
                        </span>
                    </div>
                </div>
            </div>

            <a
                class="sale-detail-slip-btn"
                href="<?= h(app_system_url('sale/setup_slip.php?id=' . urlencode($setupId) . '&from=manager')) ?>"
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
                    <strong><?= h(manager_detail_date($setup['created_at'] ?? null, true)) ?></strong>
                </div>

                <div>
                    <span>จำนวนสินค้า</span>
                    <strong><?= h((string) $itemCount) ?> รายการ</strong>
                </div>

                <div class="wide">
                    <span>สถานที่ติดตั้ง</span>
                    <strong><?= h($setup['setup_address'] ?: ($setup['setup_location'] ?: '--')) ?></strong>
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

    <section class="sale-detail-process-card">
        <div class="sale-detail-process-head">
            <div>
                <h2>ข้อมูลการมอบหมายงาน</h2>
                <p>ข้อมูลล่าสุดที่หัวหน้าช่างเคยมอบหมายก่อนสถานะปัจจุบัน</p>
            </div>
        </div>

        <div class="sale-detail-process-row">
            <div>
                <span>รหัสมอบหมายงาน</span>
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
                <span>ช่างที่เคยมอบหมาย</span>
                <strong><?= h($technicianName) ?></strong>
            </div>

            <div>
                <span>วันที่ติดตั้ง</span>
                <strong><?= h($installDate) ?></strong>
            </div>

            <div>
                <span>เวลา</span>
                <strong><?= h($installTime) ?></strong>
            </div>
        </div>
    </section>

    <section class="sale-detail-process-card">
        <div class="sale-detail-process-head">
            <div>
                <h2>ข้อมูลการรับสินค้า</h2>
                <p>ข้อมูลยืนยันการรับสินค้าจากช่างที่ได้รับมอบหมาย</p>
            </div>
        </div>

        <?php if (!$productReceive): ?>
            <div class="sale-detail-process-row">
                <div>
                    <span>สถานะ</span>
                    <strong>ยังไม่ได้ยืนยันรับสินค้า</strong>
                </div>

                <div>
                    <span>ช่างผู้รับสินค้า</span>
                    <strong><?= h($technicianName) ?></strong>
                </div>

                <div>
                    <span>วันที่และเวลารับสินค้า</span>
                    <strong>--</strong>
                </div>

                <div>
                    <span>หมายเหตุ</span>
                    <strong>--</strong>
                </div>

                <div>
                    <span>หลักฐาน</span>
                    <strong>-</strong>
                </div>
            </div>
        <?php else: ?>
            <div class="sale-detail-process-row">
                <div>
                    <span>สถานะ</span>
                    <strong><?= (string) ($productReceive['receive_status'] ?? '') === '1' ? 'ยืนยันรับสินค้าแล้ว' : 'ไม่ทราบสถานะ' ?></strong>
                </div>

                <div>
                    <span>ช่างผู้รับสินค้า</span>
                    <strong><?= h($technicianName) ?></strong>
                </div>

                <div>
                    <span>วันที่และเวลารับสินค้า</span>
                    <strong><?= h(manager_detail_date($productReceive['receive_date'] ?? null, true)) ?></strong>
                </div>

                <div>
                    <span>หมายเหตุ</span>
                    <strong><?= h(trim((string) ($productReceive['receive_note'] ?? '')) !== '' ? $productReceive['receive_note'] : '--') ?></strong>
                </div>

                <div>
                    <span>หลักฐาน</span>
                    <?php if ($receiveProofUrl !== ''): ?>
                        <strong><a href="<?= h($receiveProofUrl) ?>" target="_blank" rel="noopener">ดูหลักฐาน</a></strong>
                    <?php else: ?>
                        <strong>-</strong>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="sale-detail-process-card">
        <div class="sale-detail-process-head">
            <div>
                <h2>ประวัติการมอบหมาย</h2>
                <p>รายการมอบหมายงานทั้งหมดของใบงานนี้</p>
            </div>
        </div>

        <div class="sale-detail-product-table">
            <div class="sale-detail-product-head">
                <span>รหัสมอบหมาย</span>
                <span>ชื่อช่าง</span>
                <span>วันที่มอบหมาย</span>
                <span>วันที่ติดตั้ง</span>
                <span>เวลา</span>
                <span>สถานะการมอบหมาย</span>
            </div>

            <?php if (count($assignmentHistory) === 0): ?>
                <div class="sale-detail-empty">ไม่พบประวัติการมอบหมาย</div>
            <?php endif; ?>

            <?php foreach ($assignmentHistory as $history): ?>
                <div class="sale-detail-product-row">
                    <strong><?= h($history['assign_id'] ?? '--') ?></strong>
                    <span><?= h($history['technician_name'] ?? '--') ?></span>
                    <span><?= h(manager_detail_date($history['assign_date'] ?? null, true)) ?></span>
                    <span><?= h(manager_detail_date($history['assign_install_date'] ?? null)) ?></span>
                    <span><?= h(manager_detail_time_range($history['assign_install_time'] ?? null, $history['assign_install_end_time'] ?? null)) ?></span>
                    <strong><?= h(manager_assignment_status_text($history['assign_status'] ?? '')) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</section>

<?php
layout_footer();
