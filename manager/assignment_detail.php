<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('1');

date_default_timezone_set('Asia/Bangkok');

$setupId = trim((string) ($_POST['setup_id'] ?? $_GET['id'] ?? $_GET['setup_id'] ?? ''));

$assignmentDetailFallback = app_system_url('manager/assignment_history.php');
$assignmentDetailBackUrl = $assignmentDetailFallback;
$assignmentDetailReferer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
if ($assignmentDetailReferer !== '') {
    $assignmentDetailRefererParts = parse_url($assignmentDetailReferer);
    if (is_array($assignmentDetailRefererParts)) {
        $assignmentDetailRefererPath = (string) ($assignmentDetailRefererParts['path'] ?? '');
        $assignmentDetailRefererHost = (string) ($assignmentDetailRefererParts['host'] ?? '');
        $assignmentDetailCurrentHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $assignmentDetailRequestPath = parse_url(app_system_url('manager/assignment_detail.php'), PHP_URL_PATH);
        if (
            $assignmentDetailRefererPath !== ''
            && ($assignmentDetailRefererHost === '' || strcasecmp($assignmentDetailRefererHost, $assignmentDetailCurrentHost) === 0)
            && str_starts_with($assignmentDetailRefererPath, '/project/installation_system/manager/')
            && $assignmentDetailRefererPath !== $assignmentDetailRequestPath
        ) {
            $assignmentDetailBackUrl = $assignmentDetailReferer;

            $assignmentDetailQueryParams = [];
            parse_str((string) ($assignmentDetailRefererParts['query'] ?? ''), $assignmentDetailQueryParams);
            if (($assignmentDetailQueryParams['status'] ?? '') === 'created') {
                unset($assignmentDetailQueryParams['status']);
                $assignmentDetailRefererParts['query'] = http_build_query($assignmentDetailQueryParams);

                $assignmentDetailBackUrl = '';
                if (isset($assignmentDetailRefererParts['scheme'])) {
                    $assignmentDetailBackUrl .= $assignmentDetailRefererParts['scheme'] . '://';
                }
                if (isset($assignmentDetailRefererParts['user'])) {
                    $assignmentDetailBackUrl .= $assignmentDetailRefererParts['user'];
                    if (isset($assignmentDetailRefererParts['pass'])) {
                        $assignmentDetailBackUrl .= ':' . $assignmentDetailRefererParts['pass'];
                    }
                    $assignmentDetailBackUrl .= '@';
                }
                if (isset($assignmentDetailRefererParts['host'])) {
                    $assignmentDetailBackUrl .= $assignmentDetailRefererParts['host'];
                }
                if (isset($assignmentDetailRefererParts['port'])) {
                    $assignmentDetailBackUrl .= ':' . $assignmentDetailRefererParts['port'];
                }
                $assignmentDetailBackUrl .= $assignmentDetailRefererPath;
                if ($assignmentDetailRefererParts['query'] !== '') {
                    $assignmentDetailBackUrl .= '?' . $assignmentDetailRefererParts['query'];
                }
                if (isset($assignmentDetailRefererParts['fragment'])) {
                    $assignmentDetailBackUrl .= '#' . $assignmentDetailRefererParts['fragment'];
                }
            }
        }
    }
}

if ($setupId === '') {
    redirect_to(app_system_url('manager/assignment_history.php'));
}

$currentManagerId = trim((string) ($_SESSION['user_id'] ?? ''));
if ($currentManagerId === '') {
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
      AND a.assign_by = ?
    LIMIT 1
");

$setupStmt->bind_param('ss', $setupId, $currentManagerId);
$setupStmt->execute();
$setup = $setupStmt->get_result()->fetch_assoc();

if (!$setup) {
    redirect_to(app_system_url('manager/assignment_history.php?status=notfound'));
}

$installationResult = null;
$installationResultImages = [];
$installationResultStmt = $conn->prepare('SELECT result_id, result_image FROM installation_result WHERE setup_id = ? LIMIT 1');
$installationResultStmt->bind_param('s', $setupId);
$installationResultStmt->execute();
$installationResult = $installationResultStmt->get_result()->fetch_assoc() ?: null;

if ($installationResult && !empty($installationResult['result_image'])) {
    $resultPayload = json_decode((string) $installationResult['result_image'], true);
    $resultPaths = is_array($resultPayload) && isset($resultPayload['photos'])
        ? $resultPayload['photos']
        : $installationResult['result_image'];

    if (is_array($resultPaths)) {
        $flattenResultPaths = static function ($value) use (&$flattenResultPaths): array {
            if (is_string($value)) {
                return [trim($value)];
            }

            if (!is_array($value)) {
                return [];
            }

            $paths = [];
            foreach ($value as $child) {
                $paths = array_merge($paths, $flattenResultPaths($child));
            }

            return $paths;
        };

        foreach ($flattenResultPaths($resultPaths) as $resultPath) {
            if ($resultPath !== '') {
                $installationResultImages[] = app_public_url($resultPath);
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_installation_result') {
    $confirmed = false;

    try {
        $conn->begin_transaction();

        $lockSetupStmt = $conn->prepare('SELECT setup_status FROM setup WHERE setup_id = ? LIMIT 1 FOR UPDATE');
        $lockSetupStmt->bind_param('s', $setupId);
        $lockSetupStmt->execute();
        $lockedSetup = $lockSetupStmt->get_result()->fetch_assoc();

        $lockAssignmentStmt = $conn->prepare('
            SELECT assign_id, assign_by, assign_status
            FROM assignment
            WHERE setup_id = ?
            ORDER BY assign_date DESC, assign_id DESC
            LIMIT 1
            FOR UPDATE
        ');
        $lockAssignmentStmt->bind_param('s', $setupId);
        $lockAssignmentStmt->execute();
        $lockedAssignment = $lockAssignmentStmt->get_result()->fetch_assoc();

        $lockResultStmt = $conn->prepare('SELECT result_id FROM installation_result WHERE setup_id = ? LIMIT 1 FOR UPDATE');
        $lockResultStmt->bind_param('s', $setupId);
        $lockResultStmt->execute();
        $hasLockedResult = $lockResultStmt->get_result()->num_rows > 0;

        $lockedSetupStatus = (int) ($lockedSetup['setup_status'] ?? 0);
        $lockedAssignmentStatus = (int) ($lockedAssignment['assign_status'] ?? 0);

        if (
            $hasLockedResult
            && $lockedSetup
            && $lockedAssignment
            && (string) ($lockedAssignment['assign_by'] ?? '') === $currentManagerId
            && !in_array($lockedSetupStatus, [4, 5], true)
            && !in_array($lockedAssignmentStatus, [4, 5], true)
        ) {
            $doneSetupStatus = 4;
            $updateSetupStmt = $conn->prepare('UPDATE setup SET setup_status = ?, completed_at = COALESCE(completed_at, NOW()) WHERE setup_id = ? AND setup_status = ?');
            $updateSetupStmt->bind_param('isi', $doneSetupStatus, $setupId, $lockedSetupStatus);
            $updateSetupStmt->execute();

            $doneAssignStatus = 5;
            $updateAssignmentStmt = $conn->prepare('UPDATE assignment SET assign_status = ? WHERE assign_id = ? AND assign_status = ?');
            $updateAssignmentStmt->bind_param('isi', $doneAssignStatus, $lockedAssignment['assign_id'], $lockedAssignmentStatus);
            $updateAssignmentStmt->execute();

            $confirmed = $updateSetupStmt->affected_rows === 1 && $updateAssignmentStmt->affected_rows === 1;
        }

        if ($confirmed) {
            $conn->commit();
            redirect_to(app_system_url('manager/assignment_detail.php?id=' . urlencode($setupId) . '&status=installation_confirmed'));
        }

        $conn->rollback();
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            // Keep the original request safe even if rollback is unavailable.
        }
    }

    redirect_to(app_system_url('manager/assignment_detail.php?id=' . urlencode($setupId) . '&status=installation_confirm_failed'));
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

$assignmentId = trim((string) ($setup['assign_id'] ?? ''));
$assignmentId = $assignmentId !== '' ? $assignmentId : '--';
$assignmentDate = manager_detail_date($setup['assign_date'] ?? null);
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

$setupStatus = (string) ($setup['setup_status'] ?? '');
$canConfirmInstallation = $installationResult !== null
    && !in_array($setupStatus, ['4', '5'], true)
    && !in_array($assignStatus, ['4', '5'], true);
$confirmationStatus = (string) ($_GET['status'] ?? '');

if ($setupStatus === '5' || $assignStatus === '4') {
    $managerStatusText = 'ยกเลิกแล้ว';
    $managerStatusClass = 'cancelled';
} elseif ($setupStatus === '4' || $assignStatus === '5') {
    $managerStatusText = 'เสร็จสิ้น';
    $managerStatusClass = 'done';
} elseif ($installationResult && $setupStatus === '3') {
    $managerStatusText = 'รอหัวหน้าช่างยืนยัน';
    $managerStatusClass = 'review';
} elseif ($setupStatus === '3') {
    $managerStatusText = 'กำลังติดตั้ง';
    $managerStatusClass = 'working';
} elseif ((string) ($productReceive['receive_status'] ?? '') === '1') {
    $managerStatusText = 'ยืนยันรับสินค้าแล้ว';
    $managerStatusClass = 'ready';
} elseif ($setupStatus === '2') {
    $managerStatusText = 'ช่างรับงานแล้ว';
    $managerStatusClass = 'accepted';
} elseif ($assignStatus === '2') {
    $managerStatusText = 'ช่างกำลังไปรับสินค้า';
    $managerStatusClass = 'pickup';
} elseif ($assignStatus === '1') {
    $managerStatusText = 'รอช่างรับงาน';
    $managerStatusClass = 'waiting';
} elseif ($setupStatus === '0') {
    $managerStatusText = 'ยังไม่ได้มอบหมาย';
    $managerStatusClass = 'unassigned';
} else {
    $managerStatusText = manager_assignment_status_text($assignStatus);
    $managerStatusClass = match ($assignStatus) {
        '1' => 'assigned',
        '2' => 'accepted',
        '3' => 'rejected',
        '4' => 'cancelled',
        '5' => 'done',
        default => 'waiting',
    };
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
      AND a.assign_by = ?
    ORDER BY a.assign_date DESC, a.assign_id DESC
");
$historyStmt->bind_param('ss', $setupId, $currentManagerId);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();

while ($history = $historyResult->fetch_assoc()) {
    $assignmentHistory[] = $history;
}

layout_header('รายละเอียดงานมอบหมาย', 'assignment_history', 'ตรวจสอบรายละเอียดงานและดำเนินการตามขั้นตอน');
?>

<link
  rel="stylesheet"
  href="<?= h(app_asset_url('sale/assets/css/setup_detail.css')) ?>?v=<?= h(asset_version('sale/assets/css/setup_detail.css')) ?>"
>
<link
  rel="stylesheet"
  href="<?= h(app_asset_url('manager/assets/css/assignment_detail.css')) ?>?v=<?= h(asset_version('manager/assets/css/assignment_detail.css')) ?>"
>

<section class="sale-detail-page manager-assignment-detail-page">
    <?php if ($confirmationStatus === 'installation_confirmed'): ?>
        <div class="flash success" role="status">ยืนยันงานติดตั้งเรียบร้อยแล้ว</div>
    <?php elseif ($confirmationStatus === 'installation_confirm_failed'): ?>
        <div class="flash error" role="alert">ไม่สามารถยืนยันงานติดตั้งได้ กรุณาตรวจสอบสถานะงานอีกครั้ง</div>
    <?php endif; ?>

    <div class="manager-assignment-detail-layout">
      <div class="manager-assignment-detail-main">
    <div class="manager-detail-header">
        <div class="sale-detail-summary-head">
            <div>
                <a
                    class="sale-detail-back detail-back-link"
                    href="<?= h($assignmentDetailBackUrl) ?>"
                >
                    <svg class="ref-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"></path><path d="m12 19-7-7 7-7"></path></svg>
                    <span>กลับไปยังหน้าก่อนหน้า</span>
                </a>

                <div class="sale-detail-summary-title-row">
                    <div class="sale-detail-summary-title">
                        <p class="sale-detail-setup-code">
                            สรุปงานมอบหมาย
                        </p>
                        <p class="manager-detail-content-subtitle">รหัสใบงาน <strong><?= h($setup['setup_id']) ?></strong> · ตรวจสอบรายละเอียดใบงานและสถานะการมอบหมาย</p>

                    </div>

                </div>
            </div>

            <div class="manager-detail-header-actions">
                <span class="sale-detail-status <?= h($managerStatusClass) ?>"><strong><?= h($managerStatusText) ?></strong></span>
            </div>

        </div>
    </div>

    <section class="sale-detail-summary-card">

        <section class="sale-detail-customer-box customer-info">
            <div class="sale-detail-customer-main customer-info__body">
                <div class="sale-detail-customer-icon customer-info__avatar" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M20 21a8 8 0 0 0-16 0"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>

                <div class="sale-detail-customer-content customer-info__content">
                    <span class="customer-info__eyebrow">ลูกค้า</span>
                    <h3><?= h($setup['user_name'] ?? '--') ?></h3>

                    <div class="sale-detail-customer-contact customer-info__meta">
                        <span><svg class="ref-icon ref-icon-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9h16"></path><path d="M4 15h16"></path><path d="M10 3 8 21"></path><path d="M16 3 14 21"></path></svg><?= h($setup['user_id'] ?? '--') ?></span>
                        <span><svg class="ref-icon ref-icon-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.32 1.77.59 2.61a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.47-1.16a2 2 0 0 1 2.11-.45c.84.27 1.71.47 2.61.59A2 2 0 0 1 22 16.92z"></path></svg><?= h($setup['user_phone'] ?? '--') ?></span>
                        <span><svg class="ref-icon ref-icon-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 7l9 6 9-6"></path></svg><?= h($setup['user_email'] ?? '--') ?></span>
                    </div>

                    <div class="sale-detail-customer-address customer-info__address">
                        <span><svg class="ref-icon ref-icon-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg><span><?= h($setup['user_address'] ?? '--') ?></span></span>
                    </div>

                </div>
                <div class="customer-info__id">
                    <span>รหัสลูกค้า</span>
                    <strong><?= h($setup['user_id'] ?? '--') ?></strong>
                </div>
            </div>
        </section>

        <section class="product-table">
            <header class="product-table__header">
                <h2>รายการสินค้า</h2>
                <p><?= h((string) $itemCount) ?> รายการ · <?= h((string) array_sum(array_map(static fn (array $item): int => (int) ($item['install_qty'] ?? 0), $items))) ?> ชิ้น</p>
            </header>

            <div class="product-table__table" role="table">
                <div class="product-table__head" role="rowgroup">
                    <div class="product-table__row" role="row">
                        <span>สินค้า</span>
                        <span>จำนวน</span>
                        <span>ค่าติดตั้ง/หน่วย</span>
                        <span>รวม</span>
                    </div>
                </div>

                <div class="product-table__body" role="rowgroup">
                    <?php if ($itemCount === 0): ?>
                        <div class="product-table__empty">ไม่พบรายการสินค้า</div>
                    <?php endif; ?>

                    <?php foreach ($items as $item): ?>
                        <div class="product-table__row" role="row">
                            <div class="product-table__item">
                                <strong><?= h($item['pro_name'] ?? '--') ?></strong>
                            </div>

                            <span><span class="product-table__qty"><?= h((string) ($item['install_qty'] ?? 0)) ?> ชิ้น</span></span>

                            <span class="product-table__money">
                                <?= h(number_format((float) ($item['install_price'] ?? 0), 2)) ?> บาท
                            </span>

                            <strong class="product-table__money">
                                <?= h(number_format((float) ($item['install_total'] ?? 0), 2)) ?> บาท
                            </strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <footer class="product-table__total">
                <span>ยอดรวมค่าติดตั้ง</span>
                <strong><?= h(number_format($totalAmount, 2)) ?> บาท</strong>
            </footer>
        </section>

    </section>

        <section class="card installation-result-card" id="installation-result" aria-labelledby="installation-result-title">
            <header class="card-header installation-result-header">
                <div class="installation-result-heading">
                    <h2 id="installation-result-title">ผลการติดตั้ง</h2>
                    <p>รูปงานติดตั้งที่ช่างบันทึกไว้</p>
                </div>
                <?php if ($installationResultImages): ?>
                    <span class="installation-result-count"><?= h((string) count($installationResultImages)) ?> รูป</span>
                <?php endif; ?>
            </header>

            <div class="card-body installation-result-body">
                <div class="proof-gallery-block installation-result-content">
                    <span class="installation-result-label">รูปงานติดตั้ง</span>
                    <?php if ($installationResultImages): ?>
                        <div class="proof-gallery installation-result-gallery">
                            <?php foreach (array_slice($installationResultImages, 0, 3) as $photoIndex => $installationResultImage): ?>
                                <a
                                    class="installation-result-image-link"
                                    href="<?= h($installationResultImage) ?>"
                                    data-installation-result-image
                                    data-gallery-index="<?= h((string) $photoIndex) ?>"
                                    data-image-src="<?= h($installationResultImage) ?>"
                                    data-image-alt="รูปงานติดตั้งที่ <?= h((string) ($photoIndex + 1)) ?>"
                                >
                                    <img class="installation-result-image" src="<?= h($installationResultImage) ?>" alt="รูปงานติดตั้งที่ <?= h((string) ($photoIndex + 1)) ?>" loading="lazy">
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($installationResultImages) > 3): ?>
                            <button class="installation-result-more" type="button" data-installation-result-open>
                                ดูภาพทั้งหมด <?= h((string) count($installationResultImages)) ?> รูป
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="installation-result-empty">
                            <strong>ยังไม่มีรูปผลการติดตั้ง</strong>
                            <span>ไม่พบไฟล์รูปที่บันทึกไว้สำหรับงานนี้</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

      </div>
      <aside class="manager-assignment-detail-side">
    <section class="sale-detail-process-card">
        <div class="sale-detail-process-head">
            <div>
                <h2>การดำเนินการ</h2>
            </div>
        </div>

        <div class="sale-detail-process-row manager-detail-assignment-info">
            <div><span>รหัสมอบหมายงาน</span><strong><?= h($assignmentId) ?></strong></div>
            <div><span>วันที่มอบหมาย</span><strong><?= h($assignmentDate) ?></strong></div>
            <div><span>ผู้มอบหมายงาน</span><strong><?= h($assignerName) ?></strong></div>
            <div><span>ช่างที่ได้รับมอบหมาย</span><strong><?= h($technicianName) ?></strong></div>
            <div><span>วันที่ติดตั้ง</span><strong><?= h($installDate) ?></strong></div>
            <div><span>เวลา</span><strong><?= h($installTime) ?></strong></div>
        </div>

        <div class="manager-detail-process-total">
            <span>ค่าติดตั้ง</span>
            <strong><?= h(number_format($totalAmount, 2)) ?> บาท</strong>
        </div>

        <div class="manager-detail-action-stack">
            <?php if ($canConfirmInstallation): ?>
                <form method="post" action="<?= h(app_system_url('manager/assignment_detail.php?id=' . urlencode($setupId))) ?>" data-installation-confirm-form>
                    <input type="hidden" name="setup_id" value="<?= h($setupId) ?>">
                    <input type="hidden" name="action" value="confirm_installation_result">
                    <button class="sale-detail-slip-btn sale-detail-confirm-btn" type="button" data-installation-confirm-open>
                        ยืนยันงานติดตั้ง
                    </button>

                    <dialog class="manager-installation-confirm-modal" data-installation-confirm-modal aria-labelledby="installationConfirmTitle">
                        <div class="manager-installation-confirm-modal__header">
                            <h2 id="installationConfirmTitle">ยืนยันงานติดตั้ง</h2>
                            <button class="manager-installation-confirm-modal__close" type="button" data-installation-confirm-close aria-label="ปิดหน้าต่าง">&times;</button>
                        </div>
                        <div class="manager-installation-confirm-modal__body">
                            <p>คุณต้องการยืนยันให้งานติดตั้งนี้เสร็จสิ้นหรือไม่?</p>
                            <small>เมื่อยืนยันแล้ว งานจะถูกบันทึกเป็นงานเสร็จสิ้น</small>
                        </div>
                        <div class="manager-installation-confirm-modal__actions">
                            <button class="manager-installation-confirm-modal__cancel" type="button" data-installation-confirm-close>ยกเลิก</button>
                            <button class="sale-detail-slip-btn sale-detail-confirm-btn" type="submit" data-installation-confirm-submit>ยืนยันงานติดตั้ง</button>
                        </div>
                    </dialog>
                </form>
            <?php endif; ?>

            <a
                class="sale-detail-slip-btn"
                href="<?= h(app_system_url('sale/setup_slip.php?id=' . urlencode($setupId) . '&from=manager')) ?>"
            >
                ใบติดตั้ง
            </a>

        </div>
    </section>
      </aside>
    </div>



    <?php if ($installationResultImages): ?>
        <dialog class="installation-result-modal" data-installation-result-modal aria-labelledby="installation-result-modal-title">
            <div class="installation-result-modal-header">
                <h2 id="installation-result-modal-title">รูปงานติดตั้งทั้งหมด</h2>
                <button class="installation-result-modal-close" type="button" data-installation-result-close aria-label="ปิดรูปภาพ">×</button>
            </div>
            <div class="installation-result-modal-stage" data-installation-result-stage>
                <button class="installation-result-modal-nav is-prev" type="button" data-installation-result-prev aria-label="ดูรูปก่อนหน้า">‹</button>
                <img class="installation-result-modal-image" data-installation-result-modal-image alt="">
                <button class="installation-result-modal-nav is-next" type="button" data-installation-result-next aria-label="ดูรูปถัดไป">›</button>
            </div>
            <div class="installation-result-modal-grid" data-installation-result-grid>
                <?php foreach ($installationResultImages as $photoIndex => $installationResultImage): ?>
                    <button
                        class="installation-result-modal-thumb"
                        type="button"
                        data-installation-result-thumb
                        data-gallery-index="<?= h((string) $photoIndex) ?>"
                        data-image-src="<?= h($installationResultImage) ?>"
                        data-image-alt="รูปงานติดตั้งที่ <?= h((string) ($photoIndex + 1)) ?>"
                        aria-label="เปิดรูปงานติดตั้งที่ <?= h((string) ($photoIndex + 1)) ?>"
                    >
                        <img src="<?= h($installationResultImage) ?>" alt="" loading="lazy">
                    </button>
                <?php endforeach; ?>
            </div>
        </dialog>
    <?php endif; ?>


</section>

<script>
(() => {
    const openButton = document.querySelector('[data-installation-result-open]');
    const modal = document.querySelector('[data-installation-result-modal]');
    const closeButton = document.querySelector('[data-installation-result-close]');
    const modalStage = document.querySelector('[data-installation-result-stage]');
    const modalGrid = document.querySelector('[data-installation-result-grid]');
    const modalImage = document.querySelector('[data-installation-result-modal-image]');
    const previousButton = document.querySelector('[data-installation-result-prev]');
    const nextButton = document.querySelector('[data-installation-result-next]');
    const imageLinks = document.querySelectorAll('[data-installation-result-image]');
    const thumbnailButtons = document.querySelectorAll('[data-installation-result-thumb]');

    if (!modal || !closeButton || !modalStage || !modalGrid || !modalImage || !thumbnailButtons.length) {
        return;
    }

    let currentIndex = 0;
    const galleryItems = Array.from(thumbnailButtons);

    const showModal = () => {
        if (!modal.open) {
            modal.showModal();
        }
    };

    const renderImage = (index) => {
        const itemCount = galleryItems.length;
        if (!itemCount) {
            return;
        }

        currentIndex = (index + itemCount) % itemCount;
        const item = galleryItems[currentIndex];
        const imageSrc = item.dataset.imageSrc || '';
        if (!imageSrc) {
            return;
        }

        modalImage.src = imageSrc;
        modalImage.alt = item.dataset.imageAlt || 'รูปงานติดตั้ง';
        thumbnailButtons.forEach((thumbnail, thumbnailIndex) => {
            const isActive = thumbnailIndex === currentIndex;
            thumbnail.classList.toggle('is-active', isActive);
            if (isActive) {
                thumbnail.setAttribute('aria-current', 'true');
                thumbnail.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            } else {
                thumbnail.removeAttribute('aria-current');
            }
        });
        const hasMultipleImages = itemCount > 1;
        previousButton.hidden = !hasMultipleImages;
        nextButton.hidden = !hasMultipleImages;
    };

    imageLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            renderImage(Number(link.dataset.galleryIndex || 0));
            showModal();
            closeButton.focus();
        });
    });

    thumbnailButtons.forEach((thumbnail) => {
        thumbnail.addEventListener('click', () => {
            renderImage(Number(thumbnail.dataset.galleryIndex || 0));
        });
    });

    if (openButton) {
        openButton.addEventListener('click', () => {
            renderImage(0);
            showModal();
            closeButton.focus();
        });
    }

    previousButton.addEventListener('click', () => renderImage(currentIndex - 1));
    nextButton.addEventListener('click', () => renderImage(currentIndex + 1));
    closeButton.addEventListener('click', () => modal.close());
    modalStage.addEventListener('click', (event) => {
        if (event.target === modalStage) {
            modal.close();
        }
    });
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.close();
        }
    });
    modal.addEventListener('close', () => {
        modalImage.removeAttribute('src');
        modalImage.alt = '';
        thumbnailButtons.forEach((thumbnail) => {
            thumbnail.classList.remove('is-active');
            thumbnail.removeAttribute('aria-current');
        });
        currentIndex = 0;
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.open) {
            event.preventDefault();
            modal.close();
        }
    });
})();
</script>

<script>
(() => {
    const form = document.querySelector('[data-installation-confirm-form]');
    const openButton = document.querySelector('[data-installation-confirm-open]');
    const modal = document.querySelector('[data-installation-confirm-modal]');
    const confirmButton = document.querySelector('[data-installation-confirm-submit]');

    if (!form || !openButton || !modal || !confirmButton) {
        return;
    }

    const closeModal = () => {
        if (modal.open) {
            modal.close();
        }
    };

    openButton.addEventListener('click', () => {
        if (!modal.open) {
            modal.showModal();
        }
        modal.querySelector('[data-installation-confirm-close]')?.focus();
    });

    modal.querySelectorAll('[data-installation-confirm-close]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    form.addEventListener('submit', (event) => {
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        form.dataset.submitting = 'true';
        confirmButton.disabled = true;
        confirmButton.setAttribute('aria-disabled', 'true');
    });
})();
</script>

<?php
layout_footer();
