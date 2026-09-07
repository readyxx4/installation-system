<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('technician');

$currentTechId = trim((string) ($_SESSION['user_id'] ?? ''));
$assignId = trim((string) ($_GET['id'] ?? ''));

if ($currentTechId === '') {
    redirect_to(app_public_url('login.html?error=login'));
}

if ($assignId === '') {
    redirect_to(app_system_url('technician/accept_job.php'));
}

function technician_job_detail_date(?string $value, bool $withTime = false): string
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

function technician_job_detail_time(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '--';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('H:i', $timestamp) : $value;
}

function technician_assignment_status_text($status): string
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

function technician_assignment_status_class($status): string
{
    return match ((string) $status) {
        '1' => 'waiting',
        '2' => 'progress',
        '5' => 'done',
        '3', '4' => 'cancelled',
        default => 'waiting',
    };
}

function technician_detail_icon_svg(string $name, int $size = 16, string $class = ''): string
{
    $paths = [
        'arrow-left' => '<path d="M19 12H5"></path><path d="M12 19l-7-7 7-7"></path>',
        'check' => '<path d="M20 6L9 17l-5-5"></path>',
        'user' => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
        'phone' => '<path d="M22 16.92v3a2 2 0 01-2.18 2 19.8 19.8 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.8 19.8 0 012.12 4.18 2 2 0 014.11 2h3a2 2 0 012 1.72c.12.9.32 1.77.59 2.61a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.47-1.16a2 2 0 012.11-.45c.84.27 1.71.47 2.61.59A2 2 0 0122 16.92z"></path>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 7l9 6 9-6"></path>',
        'map-pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path><circle cx="12" cy="10" r="3"></circle>',
        'package' => '<path d="M16.5 9.4L7.5 4.2M21 16V8a2 2 0 00-1-1.7l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.7l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"></path><path d="M3.3 7L12 12l8.7-5M12 22V12"></path>',
        'truck' => '<path d="M10 17h4V5H2v12h3"></path><path d="M14 17h1m4 0h3v-6l-3-4h-5v10h2"></path><circle cx="7.5" cy="17.5" r="2.5"></circle><circle cx="17.5" cy="17.5" r="2.5"></circle>',
        'file' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8M8 17h5"></path>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path>',
        'alert' => '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"></path><path d="M12 9v4M12 17h.01"></path>',
        'hash' => '<path d="M4 9h16"></path><path d="M4 15h16"></path><path d="M10 3L8 21"></path><path d="M16 3l-2 18"></path>',
    ];

    if (!isset($paths[$name])) {
        return '';
    }

    $classes = trim('ref-icon ' . $class);

    return '<svg class="' . h($classes) . '" width="' . h((string) $size) . '" height="' . h((string) $size) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}

function technician_detail_ui_status(array $job, ?array $productReceive): array
{
    $assignStatus = (string) ($job['assign_status'] ?? '');
    $setupStatus = (string) ($job['setup_status'] ?? '');
    $receiveStatus = (string) ($productReceive['receive_status'] ?? '');

    if ($assignStatus === '5' || $setupStatus === '4') {
        return ['label' => 'เสร็จแล้ว', 'class' => 'success'];
    }

    if ($setupStatus === '3') {
        return ['label' => 'กำลังติดตั้ง', 'class' => 'info'];
    }

    if ($assignStatus === '2' && $receiveStatus === '1' && $setupStatus === '2') {
        return ['label' => 'พร้อมติดตั้ง', 'class' => 'green'];
    }

    if ($assignStatus === '2' && $receiveStatus !== '1') {
        return ['label' => 'รอรับสินค้า', 'class' => 'blue'];
    }

    return ['label' => technician_assignment_status_text($assignStatus), 'class' => 'blue'];
}
function make_receive_id(mysqli $conn): string
{
    $prefix = 'REC-';

    for ($i = 1; $i <= 9999999; $i++) {
        $receiveId = $prefix . str_pad((string) $i, 7, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare("SELECT receive_id FROM product_receive WHERE receive_id = ? LIMIT 1");
        $stmt->bind_param('s', $receiveId);
        $stmt->execute();

        if ($stmt->get_result()->num_rows === 0) {
            return $receiveId;
        }
    }

    throw new RuntimeException('Cannot generate receive_id.');
}

function technician_receive_uploaded_files(string $fieldName): array
{
    if (empty($_FILES[$fieldName]['name'])) {
        return [];
    }

    $files = $_FILES[$fieldName];
    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];
    $uploaded = [];

    foreach ($names as $index => $name) {
        $error = $errors[$index] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE || trim((string) $name) === '') {
            continue;
        }

        $uploaded[] = [
            'name' => (string) $name,
            'tmp_name' => (string) ($tmpNames[$index] ?? ''),
            'error' => $error,
            'size' => (int) ($sizes[$index] ?? 0),
        ];
    }

    return $uploaded;
}

function technician_prepare_receive_proofs(array $uploadedFiles, array &$savedFiles = [], ?int $maxFiles = 5): array
{
    if ($maxFiles !== null && count($uploadedFiles) > $maxFiles) {
        throw new RuntimeException('Proof limit exceeded.');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $uploadDir = __DIR__ . '/../uploads/product_receive';
    if (count($uploadedFiles) > 0 && !is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        throw new RuntimeException('Cannot create upload directory.');
    }

    $prepared = [];
    foreach ($uploadedFiles as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Invalid upload file.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmpName) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!isset($allowedTypes[$mime])) {
            throw new RuntimeException('Invalid proof file type.');
        }

        $fileName = 'receive_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowedTypes[$mime];
        $target = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file($tmpName, $target)) {
            throw new RuntimeException('Cannot save proof file.');
        }

        $prepared[] = [
            'path' => 'uploads/product_receive/' . $fileName,
            'file' => $target,
        ];
        $savedFiles[] = $target;
    }

    return $prepared;
}

if (empty($_SESSION['technician_start_job_csrf'])) {
    $_SESSION['technician_start_job_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start_job') {
    $startJobMessage = 'ไม่สามารถเริ่มงานได้ กรุณาตรวจสอบว่างานพร้อมติดตั้งแล้วและลองอีกครั้ง';
    $startJobSucceeded = false;
    $startJobToken = $_POST['csrf_token'] ?? null;

    if (is_string($startJobToken) && hash_equals($_SESSION['technician_start_job_csrf'], $startJobToken)) {
        try {
            // Update the status and append the actual start time together. The status
            // guard also prevents repeated submissions from overwriting the first start.
            $startJobStmt = $conn->prepare("
                UPDATE setup s
                INNER JOIN assignment a ON a.setup_id = s.setup_id
                SET s.setup_status = 3,
                    s.setup_note = CONCAT(
                        COALESCE(s.setup_note, ''),
                        CASE WHEN COALESCE(s.setup_note, '') = '' THEN '' ELSE CHAR(10) END,
                        'เริ่มงานจริง: ', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s')
                    )
                WHERE a.assign_id = ?
                  AND a.tech_id = ?
                  AND a.assign_status = 2
                  AND s.setup_status = 2
                  AND EXISTS (
                      SELECT 1 FROM product_receive pr
                      WHERE pr.assign_id = a.assign_id AND pr.receive_status = 1
                  )
            ");
            $startJobStmt->bind_param('ss', $assignId, $currentTechId);
            if ($startJobStmt->execute() && $startJobStmt->affected_rows === 1) {
                $startJobSucceeded = true;
                $startJobMessage = 'เริ่มงานแล้ว สถานะเปลี่ยนเป็นกำลังติดตั้ง';
            }
        } catch (Throwable $e) {
            error_log('Technician start job failed: ' . $e->getMessage());
        }
    } else {
        $startJobMessage = 'คำขอหมดอายุ กรุณาลองกดเริ่มงานอีกครั้ง';
    }

    $_SESSION['technician_start_job_flash'] = [
        'assign_id' => $assignId,
        'success' => $startJobSucceeded,
        'message' => $startJobMessage,
    ];
    redirect_to(app_system_url('technician/job_detail.php?id=' . urlencode($assignId)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_product_receive') {
    $postAssignId = trim((string) ($_POST['assign_id'] ?? ''));
    $receiveNote = trim((string) ($_POST['receive_note'] ?? ''));
    $preparedProofs = [];

    if ($postAssignId === '') {
        redirect_to(app_system_url('technician/job_detail.php?id=' . urlencode($assignId) . '&receive=error'));
    }

    try {
        $ownershipStmt = $conn->prepare("
            SELECT assign_id, tech_id, assign_status
            FROM assignment
            WHERE assign_id = ?
              AND tech_id = ?
            LIMIT 1
        ");
        $ownershipStmt->bind_param('ss', $postAssignId, $currentTechId);
        $ownershipStmt->execute();
        $assignment = $ownershipStmt->get_result()->fetch_assoc();

        if (!$assignment || (int) ($assignment['assign_status'] ?? 0) !== 2) {
            redirect_to(app_system_url('technician/job_detail.php?id=' . urlencode($postAssignId) . '&receive=error'));
        }

        $existingReceiveStmt = $conn->prepare("
            SELECT receive_id
            FROM product_receive
            WHERE assign_id = ?
            LIMIT 1
        ");
        $existingReceiveStmt->bind_param('s', $postAssignId);
        $existingReceiveStmt->execute();

        if ($existingReceiveStmt->get_result()->num_rows > 0) {
            redirect_to(app_system_url('technician/job_detail.php?id=' . urlencode($postAssignId) . '&receive=exists'));
        }

        $uploadedProofs = array_merge(
            technician_receive_uploaded_files('receive_proofs'),
            technician_receive_uploaded_files('receive_camera_proofs')
        );
        $preparedProofs = technician_prepare_receive_proofs($uploadedProofs);

        $conn->begin_transaction();

        $receiveId = make_receive_id($conn);
        $receiveStatus = 1;

        $insertReceiveStmt = $conn->prepare("
            INSERT INTO product_receive
                (receive_id, assign_id, receive_date, receive_status, receive_note, receive_proof)
            VALUES (?, ?, NOW(), ?, ?, NULL)
        ");
        $insertReceiveStmt->bind_param(
            'ssis',
            $receiveId,
            $postAssignId,
            $receiveStatus,
            $receiveNote
        );
        $insertReceiveStmt->execute();

        if (count($preparedProofs) > 0) {
            $insertProofStmt = $conn->prepare("
                INSERT INTO product_receive_proof
                    (receive_id, proof_path, proof_date)
                VALUES (?, ?, NOW())
            ");

            foreach ($preparedProofs as $proof) {
                $proofPath = $proof['path'];
                $insertProofStmt->bind_param('ss', $receiveId, $proofPath);
                $insertProofStmt->execute();
            }
        }

        $conn->commit();

        redirect_to(app_system_url('technician/job_detail.php?id=' . urlencode($postAssignId) . '&receive=created'));
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
        }

        foreach ($preparedProofs as $proof) {
            if (!empty($proof['file']) && is_file($proof['file'])) {
                @unlink($proof['file']);
            }
        }

        redirect_to(app_system_url('technician/job_detail.php?id=' . urlencode($postAssignId !== '' ? $postAssignId : $assignId) . '&receive=error'));
    }
}

$jobStmt = $conn->prepare(""
    . "SELECT\n"
    . "    a.assign_id,\n"
    . "    a.assign_date,\n"
    . "    a.assign_install_date,\n"
    . "    a.assign_install_time,\n"
    . "    a.assign_status,\n"
    . "\n"
    . "    s.setup_id,\n"
    . "    s.setup_address,\n"
    . "    s.setup_location,\n"
    . "    s.setup_note,\n"
    . "    s.setup_status,\n"
    . "    s.created_at,\n"
    . "\n"
    . "    c.customer_id,\n"
    . "    c.customer_name,\n"
    . "    c.customer_phone,\n"
    . "    c.customer_email,\n"
    . "    c.customer_address\n"
    . "FROM assignment a\n"
    . "LEFT JOIN setup s ON a.setup_id = s.setup_id\n"
    . "LEFT JOIN customers c ON s.customer_id = c.customer_id\n"
    . "WHERE a.assign_id = ?\n"
    . "  AND a.tech_id = ?\n"
    . "LIMIT 1"
);

$jobStmt->bind_param('ss', $assignId, $currentTechId);
$jobStmt->execute();
$job = $jobStmt->get_result()->fetch_assoc();

if (!$job) {
    redirect_to(app_system_url('technician/accept_job.php?status=notfound'));
}

if (!in_array((string) ($job['assign_status'] ?? ''), ['1', '2'], true)) {
    redirect_to(app_system_url('technician/accept_job.php?status=notfound'));
}

$items = [];
$totalAmount = 0.0;
$totalQty = 0;
$setupId = trim((string) ($job['setup_id'] ?? ''));

if ($setupId !== '') {
    $detailStmt = $conn->prepare(""
        . "SELECT\n"
        . "    d.detail_id,\n"
        . "    d.pro_id,\n"
        . "    COALESCE(p.pro_name, d.pro_id) AS pro_name,\n"
        . "    COALESCE(d.install_qty, 1) AS install_qty,\n"
        . "    COALESCE(p.pro_price_install, 0) AS install_price,\n"
        . "    COALESCE(d.install_qty, 1) * COALESCE(p.pro_price_install, 0) AS install_total\n"
        . "FROM install_detail d\n"
        . "LEFT JOIN product p ON d.pro_id = p.pro_id\n"
        . "WHERE d.setup_id = ?\n"
        . "ORDER BY d.detail_id ASC"
    );

    $detailStmt->bind_param('s', $setupId);
    $detailStmt->execute();
    $detailResult = $detailStmt->get_result();

    while ($item = $detailResult->fetch_assoc()) {
        $items[] = $item;
        $totalAmount += (float) ($item['install_total'] ?? 0);
        $totalQty += (int) ($item['install_qty'] ?? 0);
    }
}

$itemCount = count($items);

$installError = '';
$installNote = is_string($_POST['install_note'] ?? null) ? trim($_POST['install_note']) : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_install') {
    $installProofFiles = [];
    $installTransaction = false;
    $installSaved = false;
    try {
        $token = $_POST['csrf_token'] ?? null;
        if (!is_string($token) || !hash_equals($_SESSION['technician_start_job_csrf'], $token)) {
            throw new RuntimeException('คำขอหมดอายุ กรุณาเลือกภาพและบันทึกอีกครั้ง');
        }
        if (strlen($installNote) > 15000) {
            throw new RuntimeException('หมายเหตุยาวเกินไป กรุณาลดความยาวแล้วลองอีกครั้ง');
        }
        $categories = ['installation' => 'รูปงานติดตั้ง', 'wide_angle' => 'รูปมุมกว้าง', 'additional' => 'รูปเพิ่มเติม'];
        $installUploads = ['installation' => [], 'wide_angle' => [], 'additional' => []];
        if (!$items) {
            throw new RuntimeException('ไม่พบรายการสินค้าในใบงาน ไม่สามารถบันทึกผลได้');
        }
        $installationFiles = technician_receive_uploaded_files('installation_proofs');
        $maxInstallationFiles = min(10, count($items));
        if (!$installationFiles) {
            throw new RuntimeException('กรุณาเพิ่มรูปงานติดตั้งอย่างน้อย 1 รูป');
        }
        if (count($installationFiles) > $maxInstallationFiles) {
            throw new RuntimeException('อัปโหลดรูปงานติดตั้งได้สูงสุด ' . $maxInstallationFiles . ' รูป');
        }
        $expectedCount = filter_var($_POST['installation_proofs_count'] ?? null, FILTER_VALIDATE_INT);
        if ($expectedCount === false || $expectedCount !== count($installationFiles)) {
            throw new RuntimeException('รูปงานติดตั้งส่งมาไม่ครบ กรุณาเลือกภาพอีกครั้ง');
        }
        foreach ($installationFiles as $installationFile) {
            if (($installationFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($installationFile['size'] ?? 0) > 5 * 1024 * 1024) {
                throw new RuntimeException('รูปงานติดตั้งอัปโหลดไม่สำเร็จหรือมีขนาดเกิน 5 MB');
            }
        }
        $installUploads['installation'] = $installationFiles;
        $conn->begin_transaction();
        $installTransaction = true;
        $lockInstall = $conn->prepare("
            SELECT s.setup_id
            FROM setup s INNER JOIN assignment a ON a.setup_id = s.setup_id
            WHERE a.assign_id = ? AND a.tech_id = ?
              AND a.assign_status = 2 AND s.setup_status = 3
            FOR UPDATE
        ");
        $lockInstall->bind_param('ss', $assignId, $currentTechId);
        $lockInstall->execute();
        $installJob = $lockInstall->get_result()->fetch_assoc();
        if (!$installJob) {
            throw new RuntimeException('บันทึกได้เฉพาะงานของคุณที่กำลังติดตั้งเท่านั้น');
        }
        $installSetupId = (string) $installJob['setup_id'];
        $existingInstall = $conn->prepare('SELECT result_id FROM installation_result WHERE setup_id = ? LIMIT 1 FOR UPDATE');
        $existingInstall->bind_param('s', $installSetupId);
        $existingInstall->execute();
        if ($existingInstall->get_result()->num_rows > 0) {
            throw new RuntimeException('งานนี้บันทึกผลการติดตั้งแล้ว ไม่สามารถบันทึกซ้ำได้');
        }
        $photos = [];
        foreach ($installUploads as $category => $uploadedFiles) {
            try {
                $proofs = technician_prepare_receive_proofs($uploadedFiles, $installProofFiles, null);
            } catch (Throwable $uploadError) {
                throw new RuntimeException('อัปโหลด' . $categories[$category] . 'ไม่สำเร็จ กรุณาตรวจสอบไฟล์ JPG, PNG หรือ WebP และลองอีกครั้ง', 0, $uploadError);
            }
            $photos[$category] = array_column($proofs, 'path');
        }
        $resultImage = json_encode([
            'version' => 1,
            'photos' => $photos,
            'note' => $installNote,
            'submitted_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format(DateTimeInterface::ATOM),
            'submitted_by' => $currentTechId,
            'assignment_id' => $assignId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $resultId = 'IR' . bin2hex(random_bytes(4));
        $insertInstall = $conn->prepare('INSERT INTO installation_result (result_id, setup_id, result_image) VALUES (?, ?, ?)');
        $insertInstall->bind_param('sss', $resultId, $installSetupId, $resultImage);
        if (!$insertInstall->execute()) {
            throw new RuntimeException('ไม่สามารถบันทึกผลได้ กรุณาลองอีกครั้ง');
        }
        $conn->commit();
        $installTransaction = false;
        $installSaved = true;
    } catch (Throwable $e) {
        if ($installTransaction) {
            $conn->rollback();
        }
        foreach ($installProofFiles as $proofFile) {
            if (is_file($proofFile)) {
                @unlink($proofFile);
            }
        }
        $installError = $e instanceof RuntimeException && !($e instanceof mysqli_sql_exception)
            ? $e->getMessage()
            : 'บันทึกไม่สำเร็จ กรุณาเลือกภาพและลองอีกครั้ง';
        error_log('Installation result failed: ' . $e->getMessage());
    }
    if ($installSaved) {
        redirect_to(app_system_url('technician/job_detail.php?id=' . urlencode($assignId)));
    }
}

$installResultStmt = $conn->prepare('SELECT result_id, result_image FROM installation_result WHERE setup_id = ? LIMIT 1');
$installResultStmt->bind_param('s', $job['setup_id']);
$installResultStmt->execute();
$installResultRow = $installResultStmt->get_result()->fetch_assoc() ?: null;
$hasInstallResult = $installResultRow !== null;
$installResultPhotos = [];
$installResultNote = '';
if ($installResultRow) {
    $installResultData = json_decode((string) ($installResultRow['result_image'] ?? ''), true);
    $storedInstallPhotos = [];
    if (is_array($installResultData) && isset($installResultData['photos']) && is_array($installResultData['photos'])) {
        $storedInstallPhotos = $installResultData['photos']['installation'] ?? [];
    }
    if (is_array($storedInstallPhotos)) {
        foreach ($storedInstallPhotos as $storedInstallPhoto) {
            if (is_string($storedInstallPhoto) && trim($storedInstallPhoto) !== '') {
                $installResultPhotos[] = app_public_url($storedInstallPhoto);
            }
        }
    }
    if (is_array($installResultData) && isset($installResultData['note']) && is_string($installResultData['note'])) {
        $installResultNote = $installResultData['note'];
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['install'] ?? '') === 'complete' && empty($_POST)) {
    $installError = 'ข้อมูลอัปโหลดเกินขนาดที่เซิร์ฟเวอร์รองรับ กรุณาลดขนาดรูปแล้วลองอีกครั้ง';
}

$installAddress = trim((string) ($job['setup_address'] ?: ($job['setup_location'] ?? '')));
if ($installAddress === '') {
    $installAddress = '--';
}

$installDate = technician_job_detail_date($job['assign_install_date'] ?? null);
$installTime = technician_job_detail_time($job['assign_install_time'] ?? null);
$installDateTime = '--';

if ($installDate !== '--' && $installTime !== '--') {
    $installDateTime = $installDate . ' ' . $installTime;
} elseif ($installDate !== '--') {
    $installDateTime = $installDate;
}

$productReceive = null;
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
$receiveStmt->bind_param('s', $assignId);
$receiveStmt->execute();
$productReceive = $receiveStmt->get_result()->fetch_assoc() ?: null;
$canConfirmReceive = (string) ($job['assign_status'] ?? '') === '2';
$receiveProofs = [];

if ($productReceive) {
    $proofStmt = $conn->prepare("
        SELECT proof_id, proof_path, proof_date
        FROM product_receive_proof
        WHERE receive_id = ?
        ORDER BY proof_id ASC
        LIMIT 5
    ");
    $proofStmt->bind_param('s', $productReceive['receive_id']);
    $proofStmt->execute();
    $proofResult = $proofStmt->get_result();

    while ($proof = $proofResult->fetch_assoc()) {
        $proof['proof_url'] = app_public_url((string) $proof['proof_path']);
        $receiveProofs[] = $proof;
    }
}

$installStatusText = technician_assignment_status_text($job['assign_status'] ?? '');
if ((string) ($job['setup_status'] ?? '') === '3') {
    $installStatusText = 'กำลังติดตั้ง';
} elseif ((string) ($productReceive['receive_status'] ?? '') === '1') {
    $installStatusText = 'รับสินค้าแล้ว';
}

$jobWarning = assignment_install_due_warning(
    $job['assign_install_date'] ?? '',
    $job['assign_install_time'] ?? '',
    $job['assign_status'] ?? '',
    'technician'
);
$jobWarningLevel = (string) ($jobWarning['level'] ?? 'normal');
$jobWarningDaysLeft = (int) ($jobWarning['business_days_left'] ?? -1);
$jobWarningText = '';
$jobWarningClass = '';

if ($jobWarningLevel === 'overdue') {
    $jobWarningText = ((string) ($job['assign_status'] ?? '') === '1')
        ? 'งานนี้ยังไม่ได้ตอบรับและเลยกำหนดติดตั้งแล้ว'
        : 'งานนี้เลยกำหนดติดตั้งแล้ว';
    $jobWarningClass = 'indigo';
} elseif ($jobWarningLevel === 'today') {
    $jobWarningText = 'งานนี้ถึงกำหนดติดตั้งวันนี้';
    $jobWarningClass = 'orange';
} elseif ($jobWarningLevel === 'urgent' && $jobWarningDaysLeft === 1) {
    $jobWarningText = 'งานนี้ใกล้ถึงกำหนดติดตั้ง';
    $jobWarningClass = 'orange';
} elseif ($jobWarningLevel === 'near') {
    $jobWarningText = 'งานนี้ใกล้ถึงกำหนดติดตั้ง';
    $jobWarningClass = 'yellow';
}

$detailUiStatus = technician_detail_ui_status($job, $productReceive);
$hasReceivedProducts = (string) ($productReceive['receive_status'] ?? '') === '1';
$isInstallDone = (string) ($job['setup_status'] ?? '') === '4' || (string) ($job['assign_status'] ?? '') === '5';
$isInstalling = (string) ($job['setup_status'] ?? '') === '3';
$isAwaitingInstallReview = $isInstalling && $hasInstallResult;
if ($isAwaitingInstallReview) {
    $installStatusText = 'รอหัวหน้างานตรวจสอบ';
    $detailUiStatus = ['label' => 'รอหัวหน้างานตรวจสอบ', 'class' => 'waiting'];
}
$isInstallCompleteMode = ($_GET['install'] ?? '') === 'complete' && $isInstalling && $canConfirmReceive && !$hasInstallResult;
$hasAcceptedJob = in_array((string) ($job['assign_status'] ?? ''), ['2', '5'], true);
$isPreAcceptJob = (string) ($job['assign_status'] ?? '') === '1';
$isReceiveConfirmMode = (string) ($_GET['receive'] ?? '') === 'confirm' && !$hasReceivedProducts && $canConfirmReceive;
$showUrgentHeaderBadge = $isPreAcceptJob && in_array($jobWarningLevel, ['near', 'urgent', 'today', 'overdue'], true);
$detailBackUrl = $isPreAcceptJob
    ? app_system_url('technician/accept_job.php')
    : app_system_url('technician/my_jobs.php');
$detailBackLabel = $isPreAcceptJob ? 'กลับไปยังยืนยันการรับงาน' : 'กลับไปยังงานของฉัน';
$detailTitle = $isPreAcceptJob
    ? 'รายละเอียดงาน · ' . (string) ($job['assign_id'] ?? '--')
    : 'สรุปใบงาน · ' . (string) ($job['setup_id'] ?? '--');
$detailSubtitle = $isPreAcceptJob
    ? 'ตรวจสอบข้อมูลก่อนตัดสินใจรับงาน'
    : ($isReceiveConfirmMode ? 'ตรวจสอบสินค้าและอัปโหลดหลักฐานการรับสินค้า' : 'ตรวจสอบและดำเนินการขั้นตอนถัดไป');

$timelineSteps = [
    [
        'label' => 'มอบหมายงาน',
        'state' => 'done',
        'meta' => technician_job_detail_date($job['assign_date'] ?? null, true),
    ],
    [
        'label' => 'รับงาน',
        'state' => $hasAcceptedJob ? 'done' : 'active',
        'meta' => $hasAcceptedJob ? technician_assignment_status_text($job['assign_status'] ?? '') : 'รอรับงาน',
    ],
    [
        'label' => 'รับสินค้า',
        'state' => $hasReceivedProducts ? 'done' : ($hasAcceptedJob ? 'active' : 'future'),
        'meta' => $productReceive ? technician_job_detail_date($productReceive['receive_date'] ?? null, true) : 'ยังไม่ได้รับสินค้า',
    ],
    [
        'label' => $isAwaitingInstallReview ? 'รอหัวหน้างานตรวจสอบ' : ($isInstalling ? 'กำลังติดตั้ง' : 'ติดตั้งเสร็จ'),
        'state' => $isInstallDone ? 'done' : ($isAwaitingInstallReview ? 'active' : (($hasReceivedProducts || $isInstalling) ? 'active' : 'future')),
        'meta' => $isInstallDone ? 'เสร็จสิ้น' : ($isAwaitingInstallReview ? 'ช่างบันทึกผลแล้ว · รอตรวจสอบและอนุมัติ' : ($isInstalling ? 'กำลังติดตั้ง' : 'รอดำเนินการ')),
    ],
];

$nextStepTitle = $hasReceivedProducts ? 'พร้อมติดตั้ง — ติดตั้งวันที่ ' . $installDate . ' เวลา ' . $installTime . ' น.' : 'ขั้นตอนถัดไป: รับสินค้าจากคลัง';
$nextStepText = $hasReceivedProducts
    ? 'คุณได้ยืนยันรับสินค้าแล้ว โปรดเตรียมอุปกรณ์และไปยังจุดติดตั้งตามกำหนด'
    : 'กรุณาไปรับสินค้าที่คลัง และตรวจนับสินค้าให้ครบก่อนยืนยันการรับสินค้า';

if ($isInstalling) {
    if ($isAwaitingInstallReview) {
        $nextStepTitle = 'ช่างบันทึกผลการติดตั้งเรียบร้อยแล้ว';
        $nextStepText = 'กำลังรอหัวหน้างานตรวจสอบและอนุมัติ';
    } else {
        $nextStepTitle = 'กำลังติดตั้ง';
        $nextStepText = 'อยู่ระหว่างดำเนินการติดตั้ง กรุณาตรวจสอบอุปกรณ์และความเรียบร้อยของงานก่อนส่งมอบให้ลูกค้า';
    }
}

$startJobFlash = $_SESSION['technician_start_job_flash'] ?? null;
if ($startJobFlash && ($startJobFlash['assign_id'] ?? '') === $assignId) {
    unset($_SESSION['technician_start_job_flash']);
} else {
    $startJobFlash = null;
}

layout_header('รายละเอียดงานติดตั้ง', $isPreAcceptJob ? 'accept_job' : 'my_jobs', $detailSubtitle);
?>

<link
  rel="stylesheet"
  href="<?= h(app_asset_url('technician/assets/css/technician.css')) ?>?v=<?= h(asset_version('technician/assets/css/technician.css')) ?>"
>

<link
  rel="stylesheet"
  href="<?= h(app_asset_url('technician/assets/css/job_detail.css')) ?>?v=<?= h(asset_version('technician/assets/css/job_detail.css')) ?>"
>

<?php if ($isInstallCompleteMode): ?>
<section class="page technician-detail-page install-complete-page">
    <div class="detail-page-header install-header">
        <div class="install-header-copy">
            <a class="detail-back-link" href="<?= h(app_system_url('technician/job_detail.php?id=' . urlencode($assignId))) ?>"><?= technician_detail_icon_svg('arrow-left', 16) ?><span>กลับไปรายละเอียดงาน</span></a>
            <div class="detail-title-row"><div><h2><i class="fa-solid fa-camera" aria-hidden="true"></i> บันทึกผลการติดตั้ง</h2><p>อัปโหลดรูปหลักฐานของสินค้าแต่ละรายการก่อนบันทึกผลการติดตั้ง</p></div></div>
        </div>
        <aside class="install-photo-tips" aria-labelledby="install-photo-tips-heading">
            <h3 id="install-photo-tips-heading"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i> คำแนะนำในการถ่ายรูป</h3>
            <ul>
                <li>ถ่ายให้เห็นสินค้าที่ติดตั้งครบทั้งชิ้น</li>
                <li>ภาพต้องชัด ไม่เบลอ</li>
                <li>ถ่ายในบริเวณที่มีแสงเพียงพอ</li>
                <li>หากมีหลายจุดติดตั้ง ให้ถ่ายทุกจุด</li>
                <li>สามารถอัปโหลดได้หลายรูปตามความเหมาะสม</li>
                <li>รองรับไฟล์ JPG, PNG และ WebP</li>
            </ul>
        </aside>
    </div>
    <form class="receive-confirm-form" id="installCompleteForm" method="POST" enctype="multipart/form-data" action="<?= h(app_system_url('technician/job_detail.php?id=' . urlencode($assignId) . '&install=complete')) ?>">
        <?php if ($installError !== ''): ?>
            <div class="action-panel__state is-warning" role="alert"><?= h($installError) ?> กรุณาเลือกภาพอีกครั้งก่อนบันทึก</div>
        <?php endif; ?>
        <input type="hidden" name="action" value="complete_install">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['technician_start_job_csrf']) ?>">
        
        <section class="card install-evidence-card">
            <div class="install-evidence-heading">
                <div class="install-section-heading"><span class="install-step">1</span><div><h3>รูปงานติดตั้ง <span class="install-required">(บังคับ)</span></h3><p class="install-section-description">อัปโหลดรูปหลักฐานของสินค้าแต่ละรายการก่อนบันทึกผลการติดตั้ง</p></div></div>
                <span class="install-total" aria-live="polite"><span data-total-photo-count>0</span> รูป</span>
            </div>
        <div class="install-products">
            <input type="file" name="installation_proofs[]" accept="image/jpeg,image/png,image/webp" multiple hidden data-gallery-files>
            <input type="hidden" name="installation_proofs_count" value="0" data-gallery-count-input>
            <div class="install-upload-strip" data-gallery-preview aria-live="polite"></div>
            <button class="install-add" type="button" data-gallery-add-photo aria-label="เพิ่มรูปงานติดตั้ง"><i class="fa-solid fa-plus" aria-hidden="true"></i><span>เพิ่มรูป</span></button>
        </div>
        <p class="install-help install-format">รองรับ JPG, PNG และ WebP ขนาดไฟล์ไม่เกิน 5 MB ต่อรูป</p>
        <p class="install-error" data-gallery-form-error role="alert"></p>
        <?php if (!$items): ?><p class="install-error" role="alert">ไม่พบรายการสินค้าในใบงาน ไม่สามารถบันทึกผลได้</p><?php endif; ?>
        <section class="install-note-card">
            <div class="install-section-heading"><span class="install-step">2</span><div><h3><label for="install-note-text">หมายเหตุ <span class="install-required">(ไม่บังคับ)</span></label></h3><p class="install-section-description">ระบุรายละเอียดเพิ่มเติมเกี่ยวกับงานติดตั้ง (หากมี)</p></div></div>
            <textarea id="install-note-text" name="install_note" rows="3" maxlength="5000" placeholder="เช่น ตำแหน่งการติดตั้ง อุปกรณ์ที่ใช้งาน หรือปัญหาที่พบระหว่างติดตั้ง" aria-describedby="install-note-counter"><?= h($installNote) ?></textarea>
            <div class="install-note-counter" id="install-note-counter"><span data-note-count>0</span>/5000</div>
        </section>
        <p class="install-error" data-form-error role="alert"></p>
        <div class="install-actions">
            <button class="receive-confirm-submit" type="submit" <?= !$items ? 'disabled' : '' ?>><i class="fa-solid fa-check" aria-hidden="true"></i><span>บันทึกผลการติดตั้ง</span></button>
            <a class="action-panel__link" href="<?= h(app_system_url('technician/job_detail.php?id=' . urlencode($assignId))) ?>">ยกเลิก</a>
        </div>
        </section>
    </form>
    <input type="file" accept="image/*" capture="environment" data-camera class="install-camera-input">
    <input type="file" accept="image/jpeg,image/png,image/webp" multiple data-album hidden>
    <dialog class="install-source" aria-labelledby="install-source-heading">
        <h3 id="install-source-heading">เพิ่มรูปงานติดตั้ง</h3>
        <button class="install-add" type="button" data-source-album><i class="fa-solid fa-images" aria-hidden="true"></i> เลือกจากอัลบั้ม</button>
        <button class="install-add" type="button" data-source-close><i class="fa-solid fa-xmark" aria-hidden="true"></i> ยกเลิก</button>
    </dialog>
    <dialog class="install-camera-preview" aria-labelledby="install-camera-preview-heading">
        <h3 id="install-camera-preview-heading">ตรวจสอบภาพถ่าย</h3>
        <img data-camera-preview-image alt="ตัวอย่างภาพถ่ายงานติดตั้ง">
        <div class="install-camera-preview-actions">
            <button class="install-add" type="button" data-camera-retake><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> ถ่ายใหม่</button>
            <button class="receive-confirm-submit" type="button" data-camera-confirm><i class="fa-solid fa-check" aria-hidden="true"></i> ใช้ภาพนี้</button>
        </div>
    </dialog>
    <section class="install-gallery" aria-labelledby="install-gallery-heading" hidden>
        <div class="install-gallery-header"><div><h2 id="install-gallery-heading" tabindex="-1">รูปงานติดตั้งทั้งหมด</h2><p data-gallery-name hidden></p></div><button class="install-add" type="button" data-gallery-close aria-label="ปิดหน้านี้"><i class="fa-solid fa-xmark" aria-hidden="true"></i> ปิดหน้านี้</button></div>
        <button class="install-add" type="button" data-gallery-add><i class="fa-solid fa-plus" aria-hidden="true"></i> เพิ่มรูป</button>
        <p class="install-error" data-gallery-error role="alert"></p>
        <p data-gallery-count aria-live="polite"></p>
        <div class="install-photo-grid" data-gallery-grid></div>
    </section>
    <dialog class="install-image-viewer" aria-label="ดูรูปหลักฐานขนาดใหญ่">
        <button class="install-add" type="button" data-image-close aria-label="ปิดรูป"><i class="fa-solid fa-xmark" aria-hidden="true"></i> ปิดรูป</button>
        <img data-image-full alt="">
        <p data-image-caption></p>
    </dialog>
</section>
<style>
body.app-body.role-technician .technician-detail-page.install-complete-page { box-sizing: border-box; width: 100%; max-width: 1600px; padding-inline: 32px; margin-inline: auto; }
body.app-body.role-technician .install-complete-page .install-header { display: grid; grid-template-columns: minmax(0, 1fr); align-items: start; gap: 20px; margin-bottom: 24px; }
.install-complete-page .install-header-copy { min-width: 0; padding-block: 4px; }
body.app-body.role-technician .install-complete-page .install-header-copy .detail-title-row { margin-top: 24px; }
body.app-body.role-technician .install-complete-page .install-header-copy .detail-title-row p { margin-top: 12px; line-height: 1.7; }
.install-complete-page .install-photo-tips { padding: 20px 24px; border: 1px solid #dbeafe; border-radius: 12px; background: #eff6ff; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); color: #334155; }
.install-complete-page .install-photo-tips h3 { display: flex; align-items: center; gap: 10px; margin: 0 0 12px; font-size: 16px; font-weight: 600; line-height: 1.5; color: #1d4ed8; }
.install-complete-page .install-photo-tips ul { margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.7; }
.install-complete-page .install-photo-tips li + li { margin-top: 4px; }
@media (max-width: 900px) {
    body.app-body.role-technician .install-complete-page .install-header { grid-template-columns: minmax(0, 1fr); gap: 20px; }
}
@media (max-width: 600px) {
    body.app-body.role-technician .technician-detail-page.install-complete-page { padding-inline: 16px; }
    .install-complete-page .install-photo-tips { padding: 16px; }
    body.app-body.role-technician .install-complete-page .install-header-copy .detail-title-row { margin-top: 18px; }
}
.install-complete-page [hidden] { display: none !important; }
.install-complete-page .detail-page-header { margin-bottom: 16px; }
.install-complete-page .install-help { color: #64748b; font-size: 13px; line-height: 1.5; }
.install-complete-page .install-products { display: grid; gap: 16px; }
.install-complete-page .install-product { padding: 20px; border: 1px solid #dbe3ee; border-radius: 12px; background: #fff; box-shadow: none; }
.install-complete-page .install-product-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.install-complete-page .install-product-header h3 { margin: 0; font-size: 16px; line-height: 1.5; overflow-wrap: anywhere; }
.install-complete-page .install-photo-badge { flex-shrink: 0; padding: 5px 9px; border-radius: 8px; background: #f1f5f9; color: #475569; font-size: 12px; }
.install-complete-page .install-photo-badge.has-photos { background: #eff6ff; color: #1d4ed8; }
.install-complete-page .install-add { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 8px 14px; border: 1px solid #bfdbfe; border-radius: 9px; background: #fff; color: #1d4ed8; font: inherit; font-size: 14px; cursor: pointer; }
.install-complete-page .install-add:hover { background: #eff6ff; }
.install-complete-page .install-photo-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; margin-bottom: 14px; }
.install-complete-page .install-photo-grid:empty { display: none; }
.install-complete-page .install-photo-grid figure { position: relative; min-width: 0; margin: 0; }
.install-complete-page .install-photo-grid img { display: block; width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 9px; }
.install-complete-page .install-remove { position: absolute; top: 4px; right: 4px; width: 40px; height: 40px; display: grid; place-items: center; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; color: #b91c1c; cursor: pointer; }
.install-complete-page .install-more { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; aspect-ratio: 1; border: 1px solid #bfdbfe; border-radius: 9px; background: #eff6ff; color: #1d4ed8; font: inherit; cursor: pointer; }
.install-complete-page .install-more strong { font-size: 22px; }
.install-complete-page .install-error { color: #b91c1c; font-size: 14px; line-height: 1.5; }
.install-complete-page .install-error:empty { display: none; }
body.app-body.role-technician .technician-detail-page.install-complete-page .install-note { display: grid; gap: 8px; margin-top: 20px; }
body.app-body.role-technician .technician-detail-page.install-complete-page .install-note textarea { width: 100%; min-height: 80px; resize: vertical; }
.install-complete-page .install-actions { display: grid; gap: 8px; margin-top: 16px; }
body.app-body.role-technician .technician-detail-page.install-complete-page .install-actions > * { min-height: 44px; }
.install-complete-page button:focus-visible, .install-complete-page a:focus-visible { outline: 2px solid #2563eb; outline-offset: 3px; }
.install-complete-page dialog { box-sizing: border-box; padding: 24px; border: 1px solid #dbe3ee; border-radius: 12px; background: #fff; color: #334155; }
.install-complete-page dialog::backdrop { background: rgb(15 23 42 / 45%); }
.install-complete-page .install-source { width: min(380px,calc(100% - 24px)); }
.install-complete-page .install-source h3 { margin: 0 0 16px; font-size: 18px; }
.install-complete-page .install-source .install-add { display: flex; width: 100%; margin-top: 8px; }
.install-complete-page .install-source h3 { text-align: center; }
.install-complete-page .install-source .install-add { width: min(100%, 320px); margin: 10px auto 0; }
.install-complete-page .install-gallery { width: min(1000px,calc(100% - 32px)); max-height: 90dvh; }
.install-complete-page .install-gallery-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 16px; }
.install-complete-page .install-gallery-header h2 { margin: 0; font-size: 20px; }
.install-complete-page [data-gallery-name] { overflow-wrap: anywhere; }
@media(max-width:600px) {
 .install-complete-page .install-product { padding: 16px; }
 .install-complete-page .install-photo-grid { grid-template-columns: repeat(3,minmax(0,1fr)); gap: 8px; }
 .install-complete-page .install-more { grid-column: 1 / -1; aspect-ratio: auto; min-height: 48px; flex-direction: row; }
 .install-complete-page .install-remove { width: 36px; height: 36px; }
 .install-complete-page .install-product-header { flex-wrap: wrap; }
 .install-complete-page .install-source { width: 100%; max-width: 100%; margin: auto 0 0; border-radius: 16px 16px 0 0; padding-bottom: max(24px,env(safe-area-inset-bottom)); }
 .install-complete-page .install-gallery { width: 100%; max-width: 100%; height: 100dvh; max-height: 100dvh; margin: 0; border: 0; border-radius: 0; padding: 16px; }
 .install-complete-page .install-gallery-header { position: sticky; top: -16px; padding-block: 16px; background: #fff; z-index: 1; }
}

/* Evidence review views, scoped to installation completion only. */
.install-complete-page .detail-title-row h2 { display: flex; align-items: center; gap: 10px; }
.install-complete-page .detail-title-row h2 > i { color: #2563eb; font-size: 22px; }
.install-complete-page .install-product { padding: 22px; }
.install-complete-page .install-product-header { align-items: center; margin-bottom: 18px; }
.install-complete-page .install-product-header h3 { display: flex; align-items: center; gap: 12px; font-size: 17px; }
.install-complete-page .install-product-icon { flex-shrink: 0; width: 38px; height: 38px; display: grid; place-items: center; border-radius: 10px; background: #eff6ff; color: #2563eb; }
.install-complete-page .install-photo-badge { display: inline-flex; align-items: center; gap: 6px; }
.install-complete-page .install-open-image { display: block; width: 100%; padding: 0; border: 0; border-radius: 9px; background: #f1f5f9; cursor: zoom-in; }
.install-complete-page .install-gallery { width: 100%; max-height: none; margin: 0; padding: 0; }
.install-complete-page .install-gallery-header { padding: 0 0 16px; border-bottom: 1px solid #dbe3ee; }
.install-complete-page [data-gallery-name] { font-size: 17px; margin: 8px 0 0; color: #475569; }
.install-complete-page [data-gallery-grid] { grid-template-columns: repeat(4,minmax(0,1fr)); margin-top: 16px; }
.install-complete-page .install-image-viewer { width: min(1000px,calc(100% - 24px)); max-height: 94dvh; padding: 16px; }
.install-complete-page .install-image-viewer > button { display: flex; margin-left: auto; margin-bottom: 12px; }
.install-complete-page [data-image-full] { display: block; max-width: 100%; max-height: 72dvh; margin: auto; object-fit: contain; border-radius: 8px; }
.install-complete-page [data-image-caption] { margin: 12px 0 0; text-align: center; overflow-wrap: anywhere; }
@media (max-width:900px) {
 .install-complete-page [data-gallery-grid] { grid-template-columns: repeat(2,minmax(0,1fr)); }
}
@media (max-width:600px) {
 .install-complete-page .install-product { padding: 16px; }
 .install-complete-page .install-product-header { align-items: flex-start; gap: 10px; }
 .install-complete-page .install-product-header h3 { font-size: 16px; }
 .install-complete-page .install-gallery { height: auto; min-height: 85dvh; max-height: none; padding: 0; }
 .install-complete-page .install-gallery-header { position: static; padding: 0 0 16px; flex-wrap: wrap; }
 .install-complete-page [data-gallery-grid] { grid-template-columns: repeat(2,minmax(0,1fr)); }
}
@media (max-width:360px) {
 .install-complete-page [data-gallery-grid] { grid-template-columns: minmax(0,1fr); }
}

/* Completion-form mockup: presentation only. */
.install-complete-page #installCompleteForm .install-product,
.install-complete-page #installCompleteForm .install-note-card { padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; box-shadow: 0 1px 3px rgba(15,23,42,.04); }
.install-complete-page .install-section-heading { display: flex; align-items: flex-start; gap: 14px; min-width: 0; }
.install-complete-page .install-step { display: grid; place-items: center; flex: 0 0 36px; width: 36px; height: 36px; border-radius: 50%; background: #2563eb; color: #fff; font-weight: 600; }
.install-complete-page #installCompleteForm .install-section-heading h3 { display: block; margin: 0; font-size: 17px; line-height: 1.5; }
.install-complete-page #installCompleteForm .install-note-card h3 label { display: inline-flex; flex-direction: row; align-items: baseline; gap: 6px; }
.install-complete-page .install-required { font-size: 13px; font-weight: 400; color: #64748b; }
.install-complete-page .install-product-name { margin: 4px 0; color: #334155; font-size: 14px; overflow-wrap: anywhere; }
.install-complete-page .install-section-description { margin: 5px 0 0; font-size: 13px; line-height: 1.6; color: #64748b; }
.install-complete-page .install-upload-strip { display: flex; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-top: 20px; }
.install-complete-page #installCompleteForm [data-product-preview] { display: contents; }
.install-complete-page #installCompleteForm [data-product-preview]:empty { display: none; }
.install-complete-page #installCompleteForm [data-product-preview] > figure,
.install-complete-page #installCompleteForm .install-more,
.install-complete-page #installCompleteForm [data-add-photo] { flex: 0 0 120px; width: 120px; height: 120px; aspect-ratio: 1; }
.install-complete-page #installCompleteForm .install-more { display: flex; flex-direction: column; }
.install-complete-page #installCompleteForm [data-add-photo] { display: flex; flex-direction: column; gap: 10px; padding: 12px; border: 1px dashed #94a3b8; border-radius: 9px; background: #fff; color: #64748b; }
.install-complete-page #installCompleteForm [data-add-photo] i { font-size: 22px; color: #2563eb; }
.install-complete-page #installCompleteForm [data-add-photo]:hover { background: #eff6ff; border-color: #2563eb; color: #1d4ed8; }
.install-complete-page .install-format { margin: 14px 0 0; }
.install-complete-page .install-note-card { margin-top: 20px; }
body.app-body.role-technician .technician-detail-page.install-complete-page #install-note-text { display: block; width: 100%; min-height: 100px; margin-top: 18px; padding: 12px 14px; border: 1px solid #d5dde8; border-radius: 9px; font: inherit; font-size: 14px; line-height: 1.6; resize: vertical; box-sizing: border-box; }
.install-complete-page .install-note-counter { text-align: right; margin-top: 8px; color: #64748b; font-size: 12px; }
body.app-body.role-technician .technician-detail-page.install-complete-page .install-actions > * { min-height: 52px; }
@media (max-width:600px) {
 .install-complete-page #installCompleteForm .install-product,
 .install-complete-page #installCompleteForm .install-note-card { padding: 16px; }
 .install-complete-page .install-upload-strip { gap: 8px; }
 .install-complete-page #installCompleteForm [data-product-preview] > figure,
 .install-complete-page #installCompleteForm .install-more,
 .install-complete-page #installCompleteForm [data-add-photo] { flex-basis: calc((100% - 16px) / 3); width: calc((100% - 16px) / 3); height: auto; min-height: 0; aspect-ratio: 1; }
 .install-complete-page .install-section-heading { gap: 10px; }
}

/* One shared card; product rows retain the existing upload hooks. */
.install-complete-page #installCompleteForm .install-evidence-card { display: flex; flex-direction: column; align-items: stretch; width: 100%; min-width: 0; box-sizing: border-box; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background: #fff; box-shadow: 0 1px 3px rgba(15,23,42,.04); }
.install-complete-page #installCompleteForm .install-evidence-card > * { width: 100%; min-width: 0; box-sizing: border-box; }
.install-complete-page .install-evidence-heading { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; padding-bottom: 22px; }
.install-complete-page .install-total { flex-shrink: 0; padding-top: 8px; color: #1d4ed8; font-weight: 600; font-size: 14px; }
.install-complete-page #installCompleteForm .install-products { display: block; }
.install-complete-page #installCompleteForm .install-evidence-card .install-product { margin: 0; padding: 22px 0; border: 0; border-top: 1px solid #e2e8f0; border-radius: 0; background: transparent; box-shadow: none; }
.install-complete-page #installCompleteForm .install-evidence-card .install-note-card { padding: 22px 0 0; margin: 0; border: 0; border-top: 1px solid #e2e8f0; border-radius: 0; background: transparent; box-shadow: none; }
.install-complete-page #installCompleteForm .install-photo-grid img,
.install-complete-page #installCompleteForm .install-open-image,
.install-complete-page #installCompleteForm .install-more,
.install-complete-page #installCompleteForm [data-add-photo] { border-radius: 12px; }
@media (max-width:600px) {
 .install-complete-page #installCompleteForm .install-evidence-card { padding: 16px; }
 .install-complete-page .install-evidence-heading { gap: 12px; }
}

/* Compact product rows; gallery and upload behavior remain unchanged. */
.install-complete-page #installCompleteForm .install-evidence-card .install-products { display: block; }
.install-complete-page #installCompleteForm .install-evidence-card .install-product { display: flex; flex-direction: column; align-items: stretch; width: 100%; min-width: 0; box-sizing: border-box; margin: 0; padding: 10px 0; border: 0; border-top: 1px solid #e2e8f0; border-radius: 0; background: transparent; box-shadow: none; text-align: left; }
.install-complete-page #installCompleteForm .install-product-header { display: block; order: 0; width: 100%; margin: 0 0 6px; }
.install-complete-page #installCompleteForm .install-product-header h3 { display: flex; flex-direction: row; align-items: center; justify-content: flex-start; gap: 8px; margin: 0; font-size: 14px; line-height: 20px; }
.install-complete-page #installCompleteForm .install-product .install-product-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 20px; width: 20px; height: 20px; border-radius: 0; background: transparent; color: #64748b; font-size: 16px; }
.install-complete-page #installCompleteForm [data-photo-count],
.install-complete-page #installCompleteForm .install-product .install-format { display: none; }
.install-complete-page #installCompleteForm .install-product .install-upload-strip { display: flex; flex-direction: row; flex-wrap: nowrap; direction: ltr; align-items: flex-start; justify-content: flex-start; order: 1; width: 100%; min-width: 0; box-sizing: border-box; gap: 8px; margin: 0; overflow-x: auto; padding: 3px; }
.install-complete-page #installCompleteForm .install-product [data-product-preview] { display: flex; flex-direction: row; flex-wrap: nowrap; flex: 0 0 auto; order: 0; gap: 8px; width: auto; margin: 0; }
.install-complete-page #installCompleteForm .install-product [data-add-photo] { order: 1; margin: 0; }
.install-complete-page #installCompleteForm .install-product > .install-error { order: 2; }
.install-complete-page #installCompleteForm .install-product [data-product-preview]:empty { display: none; }
.install-complete-page #installCompleteForm .install-evidence-card .install-product [data-product-preview] > figure,
.install-complete-page #installCompleteForm .install-evidence-card .install-product .install-more,
.install-complete-page #installCompleteForm .install-evidence-card .install-product [data-add-photo] { flex: 0 0 90px; width: 90px; height: 90px; min-height: 90px; box-sizing: border-box; aspect-ratio: 1; }
.install-complete-page #installCompleteForm .install-product .install-open-image,
.install-complete-page #installCompleteForm .install-product .install-photo-grid img { width: 90px; height: 90px; object-fit: cover; border-radius: 10px; }
.install-complete-page #installCompleteForm .install-evidence-card .install-product .install-more,
.install-complete-page #installCompleteForm .install-evidence-card .install-product [data-add-photo] { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px; padding: 8px; border: 1px solid #d5dde8; border-radius: 10px; background: #fff; color: #2563eb; font-size: 12px; }
.install-complete-page #installCompleteForm .install-product .install-more strong { font-size: 18px; }
.install-complete-page #installCompleteForm .install-product [data-add-photo] i { font-size: 20px; }
.install-complete-page #installCompleteForm .install-product [data-add-photo]:hover,
.install-complete-page #installCompleteForm .install-product .install-more:hover { background: #eff6ff; border-color: #93c5fd; }
.install-complete-page #installCompleteForm .install-product .install-remove { top: 3px; right: 3px; width: 28px; height: 28px; border-radius: 7px; }
.install-complete-page #installCompleteForm .install-product .install-error { margin: 6px 0 0; }

/* Single evidence gallery presentation; product state and upload controls remain unchanged. */
.install-complete-page #installCompleteForm .install-evidence-card .install-products {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 12px;
    padding-top: 20px;
    border-top: 1px solid #e2e8f0;
}
.install-complete-page #installCompleteForm .install-evidence-card .install-product {
    display: contents;
}
.install-complete-page #installCompleteForm .install-product-header,
.install-complete-page #installCompleteForm .install-product .install-format {
    display: none;
}
.install-complete-page #installCompleteForm .install-product .install-upload-strip {
    display: contents;
}
.install-complete-page #installCompleteForm .install-product [data-product-preview] {
    display: contents;
}
.install-complete-page #installCompleteForm .install-evidence-card .install-product [data-product-preview] > figure,
.install-complete-page #installCompleteForm .install-evidence-card .install-product .install-more,
.install-complete-page #installCompleteForm .install-evidence-card .install-product [data-add-photo] {
    flex: 0 0 120px;
    width: 120px;
    height: 120px;
    min-height: 120px;
    margin: 0;
}
.install-complete-page #installCompleteForm .install-product .install-error {
    flex: 1 0 100%;
    margin: 0;
}
.install-complete-page #installCompleteForm .install-evidence-card .install-note-card {
    margin-top: 20px;
}
@media (max-width: 600px) {
    .install-complete-page #installCompleteForm .install-evidence-card .install-products {
        gap: 8px;
        padding-top: 16px;
    }
    .install-complete-page #installCompleteForm .install-evidence-card .install-product [data-product-preview] > figure,
    .install-complete-page #installCompleteForm .install-evidence-card .install-product .install-more,
    .install-complete-page #installCompleteForm .install-evidence-card .install-product [data-add-photo] {
        flex-basis: 90px;
        width: 90px;
        height: 90px;
        min-height: 90px;
    }
}

/* Gallery sizing: four equal slots per row, with action tiles in the same flow. */
.install-complete-page #installCompleteForm .install-evidence-card .install-products {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    align-items: stretch;
    gap: 12px;
    overflow: visible;
}
.install-complete-page #installCompleteForm .install-evidence-card .install-product [data-product-preview] > figure,
.install-complete-page #installCompleteForm .install-evidence-card .install-product .install-more,
.install-complete-page #installCompleteForm .install-evidence-card .install-product [data-add-photo] {
    width: 100%;
    min-width: 0;
    height: auto;
    min-height: 0;
    aspect-ratio: 1;
}
.install-complete-page #installCompleteForm .install-evidence-card .install-product .install-more {
    display: flex;
    flex-direction: column;
    grid-column: auto;
}
.install-complete-page #installCompleteForm .install-product .install-error {
    grid-column: 1 / -1;
}
@media (max-width: 760px) {
    .install-complete-page #installCompleteForm .install-evidence-card .install-products {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }
}
@media (max-width: 480px) {
    .install-complete-page #installCompleteForm .install-evidence-card .install-products {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }
}

/* Shared gallery controls for the single installation photo set. */
.install-complete-page #installCompleteForm .install-products {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    align-items: stretch;
}
.install-complete-page #installCompleteForm .install-upload-strip[data-gallery-preview],
.install-complete-page #installCompleteForm .install-upload-strip[data-gallery-preview] > [data-gallery-preview] {
    display: contents;
}
.install-complete-page #installCompleteForm .install-products > [data-gallery-preview] {
    display: contents;
}
.install-complete-page #installCompleteForm .install-products > [data-gallery-add-photo],
.install-complete-page #installCompleteForm .install-products [data-gallery-preview] > figure {
    width: 100%;
    min-width: 0;
    aspect-ratio: 1;
    box-sizing: border-box;
}
.install-complete-page #installCompleteForm .install-products > [data-gallery-add-photo] {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 6px;
    min-height: 0;
    padding: 12px;
    border: 1px dashed #94a3b8;
    border-radius: 12px;
    background: #fff;
    color: #2563eb;
}
.install-complete-page #installCompleteForm .install-products > [data-gallery-add-photo] i { font-size: 24px; }
.install-complete-page #installCompleteForm .install-products [data-gallery-preview] > figure { position: relative; margin: 0; }
.install-complete-page #installCompleteForm .install-products [data-gallery-preview] > figure img { width: 100%; height: 100%; object-fit: cover; border-radius: 12px; }
.install-complete-page #installCompleteForm .install-gallery-overlay {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    padding: 10px;
    border-radius: 12px;
    background: rgb(15 23 42 / 58%);
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    text-align: center;
    pointer-events: none;
}
.install-complete-page .install-camera-preview { width: min(420px, calc(100% - 24px)); }
.install-complete-page .install-camera-preview h3 { margin: 0 0 16px; }
.install-complete-page .install-camera-preview img { display: block; width: 100%; max-height: 60dvh; object-fit: contain; border-radius: 12px; background: #f1f5f9; }
.install-complete-page .install-camera-preview-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-top: 16px; }
.install-complete-page .install-camera-preview-actions > * { min-height: 44px; }
.install-complete-page .install-camera-input {
    position: absolute;
    left: -9999px;
    width: 1px;
    height: 1px;
    opacity: 0;
}
.install-complete-page .install-source {
    position: fixed;
    inset: 50% auto auto 50%;
    z-index: 1000;
    margin: 0;
    transform: translate(-50%, -50%);
}
.install-complete-page .install-source::backdrop {
    position: fixed;
    inset: 0;
    background: rgb(15 23 42 / 45%);
}
@media (max-width: 760px) {
    .install-complete-page #installCompleteForm .install-products { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
}
@media (max-width: 480px) {
    .install-complete-page #installCompleteForm .install-products { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
    .install-complete-page .install-camera-preview { width: 100%; max-width: 100%; margin: auto 0 0; border-radius: 16px 16px 0 0; padding-bottom: max(20px, env(safe-area-inset-bottom)); }
    .install-complete-page .install-camera-preview-actions { grid-template-columns: 1fr; }
}
body.app-body.role-technician .technician-detail-page.install-complete-page #install-note-text {
    min-height: 90px;
    padding: 16px;
}
body.app-body.role-technician .technician-detail-page.install-complete-page #install-note-text::placeholder {
    color: #94a3b8;
    opacity: 1;
}
.install-complete-page #installCompleteForm .install-note-card .install-section-description {
    margin-top: 8px;
}
.install-complete-page #installCompleteForm .install-actions {
    gap: 16px;
    margin-top: 24px;
}
.install-complete-page #installCompleteForm .install-actions > * {
    min-height: 56px;
    height: 56px;
    border-radius: 12px;
    box-sizing: border-box;
}
.install-complete-page #installCompleteForm .install-actions .receive-confirm-submit {
    transition: background-color .15s ease, transform .1s ease, box-shadow .15s ease;
}
.install-complete-page #installCompleteForm .install-actions .receive-confirm-submit:hover {
    filter: brightness(.94);
}
.install-complete-page #installCompleteForm .install-actions .receive-confirm-submit:active,
.install-complete-page #installCompleteForm .install-actions .action-panel__link:active {
    transform: translateY(1px);
}
.install-complete-page #installCompleteForm .install-actions .receive-confirm-submit:disabled {
    background: #cbd5e1;
    border-color: #cbd5e1;
    color: #64748b;
    cursor: not-allowed;
    filter: none;
}
.install-complete-page #installCompleteForm .install-actions .action-panel__link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 0 16px;
    border: 1.5px solid #2563eb;
    background: #fff;
    color: #2563eb;
    text-decoration: none;
    transition: background-color .15s ease, transform .1s ease, box-shadow .15s ease;
}
.install-complete-page #installCompleteForm .install-actions .action-panel__link:hover {
    background: #eff6ff;
}
</style>
<script>
(() => {
 const page=document.querySelector('.install-complete-page'), form=page.querySelector('form');
 const source=page.querySelector('.install-source'), gallery=page.querySelector('.install-gallery');
 const camera=page.querySelector('[data-camera]'), album=page.querySelector('[data-album]');
 const cameraPreview=page.querySelector('.install-camera-preview'), cameraImage=page.querySelector('[data-camera-preview-image]');
 const formError=page.querySelector('[data-form-error]'), galleryError=page.querySelector('[data-gallery-error]');
 const preview=page.querySelector('[data-gallery-preview]'), addPhoto=page.querySelector('[data-gallery-add-photo]');
 const filesInput=page.querySelector('[data-gallery-files]'), countInput=page.querySelector('[data-gallery-count-input]');
 const galleryGrid=page.querySelector('[data-gallery-grid]'), viewer=page.querySelector('.install-image-viewer');
 const maxFiles=Math.min(10, <?= (int) $itemCount ?>);
 let files=[], pendingCamera=null, previousScroll=0;
 const icon=name=>{const i=document.createElement('i');i.className='fa-solid '+name;i.setAttribute('aria-hidden','true');return i;};
 const setError=message=>{formError.textContent=message;galleryError.textContent=message;};
 const openViewer=(entry,index)=>{const image=page.querySelector('[data-image-full]');image.src=entry.url;image.alt='รูปงานติดตั้งที่ '+(index+1);page.querySelector('[data-image-caption]').textContent=image.alt;viewer.showModal();};
 const showGallery=()=>{gallery.hidden=false;form.hidden=true;page.querySelector('.detail-page-header').hidden=true;renderGallery();previousScroll=window.scrollY;page.querySelector('#install-gallery-heading').focus();window.scrollTo(0,0);};
 const closeGallery=()=>{gallery.hidden=true;form.hidden=false;page.querySelector('.detail-page-header').hidden=false;addPhoto.focus({preventScroll:true});window.scrollTo(0,previousScroll);};
 const thumbnail=(entry,index,full=false)=>{
  const figure=document.createElement('figure'),open=document.createElement('button'),image=document.createElement('img'),remove=document.createElement('button');
  image.src=entry.url;image.alt='รูปงานติดตั้งที่ '+(index+1);open.type='button';open.className='install-open-image';open.setAttribute('aria-label','ดูรูปงานติดตั้งที่ '+(index+1));open.append(image);
  open.addEventListener('click',()=>{if(!full&&index===2&&files.length>3){showGallery();}else{openViewer(entry,index);}});
  remove.type='button';remove.className='install-remove';remove.setAttribute('aria-label','ลบรูปงานติดตั้งที่ '+(index+1));remove.append(icon('fa-xmark'));
  remove.addEventListener('click',event=>{event.stopPropagation();files.splice(index,1);URL.revokeObjectURL(entry.url);sync();});
  figure.append(open,remove);
  if(!full&&index===2&&files.length>3){const overlay=document.createElement('span');overlay.className='install-gallery-overlay';overlay.textContent='+'+(files.length-3)+' รูป ดูทั้งหมด';figure.append(overlay);}
  return figure;
 };
 const render=()=>{form.querySelector('button[type="submit"]').disabled=files.length===0;preview.replaceChildren();files.slice(0,3).forEach((entry,index)=>preview.append(thumbnail(entry,index)));};
 const renderGallery=()=>{galleryGrid.replaceChildren();files.forEach((entry,index)=>galleryGrid.append(thumbnail(entry,index,true)));page.querySelector('[data-gallery-count]').textContent=files.length+' รูป';};
 const sync=()=>{const transfer=new DataTransfer();files.forEach(entry=>transfer.items.add(entry.file));filesInput.files=transfer.files;countInput.value=String(files.length);const total=page.querySelector('[data-total-photo-count]');if(total)total.textContent=String(files.length);render();if(!gallery.hidden)renderGallery();};
 const addSelected=selected=>{
  const invalidType=selected.some(file=>!['image/jpeg','image/png','image/webp'].includes(file.type));
  const invalidSize=selected.some(file=>file.size>5*1024*1024);
  if(invalidType){setError('กรุณาเลือกไฟล์ JPG, PNG หรือ WebP เท่านั้น');return;}
  if(invalidSize){setError('แต่ละรูปต้องมีขนาดไม่เกิน 5 MB');return;}
  if(files.length+selected.length>maxFiles){setError('อัปโหลดรูปได้สูงสุด '+maxFiles+' รูป');return;}
  selected.forEach(file=>files.push({file,url:URL.createObjectURL(file)}));setError('');sync();
 };
 const choose=()=>{if(files.length>=maxFiles){setError('อัปโหลดรูปได้สูงสุด '+maxFiles+' รูป');return;}source.showModal();};
 addPhoto.addEventListener('click',choose);
 page.querySelector('[data-source-camera]')?.addEventListener('click',()=>{source.close();requestAnimationFrame(()=>camera.click());});
 page.querySelector('[data-source-album]').addEventListener('click',()=>{source.close();album.click();});
 page.querySelector('[data-source-close]').addEventListener('click',()=>source.close());
 page.querySelector('[data-gallery-add]').addEventListener('click',choose);
 page.querySelector('[data-gallery-close]').addEventListener('click',closeGallery);
 page.querySelector('[data-image-close]').addEventListener('click',()=>viewer.close());
 page.querySelector('[data-camera-retake]').addEventListener('click',()=>{cameraPreview.close();pendingCamera=null;camera.value='';});
 page.querySelector('[data-camera-confirm]').addEventListener('click',()=>{if(pendingCamera){addSelected([pendingCamera]);}cameraPreview.close();pendingCamera=null;camera.value='';});
 camera.addEventListener('change',()=>{const selected=Array.from(camera.files||[]);if(!selected.length)return;const file=selected[0];if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>5*1024*1024){addSelected(selected);camera.value='';return;}pendingCamera=file;cameraImage.src=URL.createObjectURL(file);cameraPreview.showModal();});
 album.addEventListener('change',()=>{addSelected(Array.from(album.files||[]));album.value='';});
 form.addEventListener('submit',event=>{if(!files.length){event.preventDefault();setError('กรุณาเพิ่มรูปงานติดตั้งอย่างน้อย 1 รูป');addPhoto.focus();}});
 sync();
 window.addEventListener('pagehide',event=>{if(!event.persisted)files.forEach(entry=>URL.revokeObjectURL(entry.url));});
})();
</script>
<script>
(() => {
    const note = document.getElementById('install-note-text');
    const count = document.querySelector('[data-note-count]');
    if (!note || !count) return;
    const updateCount = () => { count.textContent = String(note.value.length); };
    note.addEventListener('input', updateCount);
    window.addEventListener('pageshow', updateCount);
    updateCount();
})();
</script>
<script>
(() => {
    const form = document.getElementById('installCompleteForm');
    const total = form?.querySelector('[data-total-photo-count]');
    const countInput = form?.querySelector('[data-gallery-count-input]');
    if (!total || !countInput) return;
    total.textContent = countInput.value || '0';
})();
</script>
<?php layout_footer(); exit; endif; ?>

<?php if ($isReceiveConfirmMode): ?>
<section class="page technician-detail-page receive-confirm-page">
    <div class="detail-page-header">
        <a class="detail-back-link" href="<?= h(app_system_url('technician/job_detail.php?id=' . urlencode((string) ($job['assign_id'] ?? '')))) ?>">
            <?= technician_detail_icon_svg('arrow-left', 16) ?>
            <span>กลับไปรายละเอียดงาน</span>
        </a>

        <div class="detail-title-row">
            <div>
                <h2>ยืนยันการรับสินค้า</h2>
                <p>ตรวจสอบรายการสินค้าและอัปโหลดหลักฐานก่อนยืนยันรับสินค้าจากคลัง</p>
            </div>
            <div class="detail-badges" aria-label="สถานะงาน">
                <span class="badge blue detail-status-badge">รอรับสินค้า</span>
            </div>
        </div>
    </div>

    <form
        id="receiveActionForm"
        class="receive-confirm-form"
        method="POST"
        action="<?= h(app_system_url('technician/job_detail.php?id=' . urlencode((string) ($job['assign_id'] ?? '')))) ?>"
        enctype="multipart/form-data"
    >
        <input type="hidden" name="action" value="confirm_product_receive">
        <input type="hidden" name="assign_id" value="<?= h($job['assign_id'] ?? '') ?>">

        <div class="receive-confirm-layout">
            <div class="receive-confirm-main">
                <section class="card receive-checklist-card">
                    <div class="card-header">
                        <div>
                            <h2>รายการสินค้าที่ต้องรับ</h2>
                            <p><?= h((string) $itemCount) ?> รายการ · <?= h((string) $totalQty) ?> ชิ้น</p>
                        </div>
                        <button class="receive-checklist-toggle" type="button" data-receive-toggle-all <?= $itemCount === 0 ? 'disabled' : '' ?>>
                            เลือกทั้งหมด
                        </button>
                    </div>

                    <div class="receive-checklist-list">
                        <?php if ($itemCount === 0): ?>
                            <div class="product-table__empty">ไม่พบรายการสินค้า</div>
                        <?php endif; ?>

                        <?php foreach ($items as $item): ?>
                            <label class="receive-checklist-item">
                                <span class="receive-checklist-copy">
                                    <strong><?= h($item['pro_name'] ?? '--') ?></strong>
                                    <span><?= h($item['pro_id'] ?? '--') ?></span>
                                </span>
                                <span class="receive-checklist-qty"><?= h((string) ($item['install_qty'] ?? 0)) ?> ชิ้น</span>
                                <input type="checkbox" name="checked_items[]" value="<?= h($item['pro_id'] ?? '') ?>" required>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="card receive-note-card">
                    <div class="card-header">
                        <div>
                            <h2>หมายเหตุเพิ่มเติม</h2>
                        </div>
                    </div>

                    <div class="card-body">
                        <label class="receive-note-field">
                            <span>หมายเหตุ</span>
                            <textarea name="receive_note" rows="4" placeholder="หมายเหตุเพิ่มเติม (ถ้ามี)"></textarea>
                        </label>
                    </div>
                </section>
            </div>

            <aside class="receive-confirm-side">
                <section class="action-panel">
                    <header class="action-panel__header">
                        <h2>สรุปการยืนยัน</h2>
                    </header>

                    <div class="action-panel__body">
                        <div class="action-panel__summary receive-confirm-count">
                            <div class="action-panel__summary-row">
                                <span>ตรวจนับสินค้า</span>
                                <strong><span data-receive-checked-count>0</span>/<?= h((string) $itemCount) ?></strong>
                            </div>
                        </div>

                        <div class="action-panel__state is-warning">
                            <?= technician_detail_icon_svg('alert', 18) ?>
                            <div>
                                <strong>ตรวจสอบข้อมูลให้ครบถ้วน</strong>
                                <span>กรุณาติ๊กตรวจรับสินค้าให้ครบทุกชิ้นก่อนยืนยัน</span>
                            </div>
                        </div>

                        <button class="receive-confirm-submit" type="submit" disabled data-receive-submit>
                            <?= technician_detail_icon_svg('package', 16) ?>
                            <span>ยืนยันรับสินค้า</span>
                        </button>
                        <a class="action-panel__link" href="<?= h(app_system_url('technician/job_detail.php?id=' . urlencode((string) ($job['assign_id'] ?? '')))) ?>">
                            <?= technician_detail_icon_svg('arrow-left', 16) ?>
                            <span>ยกเลิก</span>
                        </a>
                    </div>
                </section>
            </aside>
        </div>
    </form>
</section>

<script>
(() => {
    const form = document.getElementById('receiveActionForm');
    const checkboxes = Array.from(document.querySelectorAll('.receive-checklist-item input[type="checkbox"]'));
    const checkedCount = document.querySelector('[data-receive-checked-count]');
    const submit = document.querySelector('[data-receive-submit]');
    const toggleAll = document.querySelector('[data-receive-toggle-all]');

    if (!form || !submit) {
        return;
    }

    const syncReceiveState = () => {
        const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
        const isComplete = checkboxes.length > 0 && selected === checkboxes.length;

        if (checkedCount) {
            checkedCount.textContent = String(selected);
        }

        submit.disabled = !isComplete;

        if (toggleAll) {
            toggleAll.disabled = checkboxes.length === 0;
            toggleAll.textContent = isComplete ? 'ยกเลิกทั้งหมด' : 'เลือกทั้งหมด';
        }
    };

    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', syncReceiveState));
    if (toggleAll) {
        toggleAll.addEventListener('click', () => {
            const shouldCheck = !checkboxes.every((checkbox) => checkbox.checked);
            checkboxes.forEach((checkbox) => {
                checkbox.checked = shouldCheck;
            });
            syncReceiveState();
        });
    }
    syncReceiveState();
})();
</script>

<?php
layout_footer();
exit;
endif;
?>

<section class="page technician-detail-page">
    <div class="detail-page-header">
        <a class="detail-back-link" href="<?= h($detailBackUrl) ?>">
            <?= technician_detail_icon_svg('arrow-left', 16) ?>
            <span><?= h($detailBackLabel) ?></span>
        </a>

        <div class="detail-title-row">
            <div>
                <h2><?= h($detailTitle) ?></h2>
                <p><?= h($detailSubtitle) ?></p>
            </div>

            <div class="detail-badges" aria-label="สถานะงาน">
                <?php if ($isPreAcceptJob): ?>
                    <span class="badge waiting detail-status-badge">รอยืนยันรับงาน</span>
                    <?php if ($showUrgentHeaderBadge): ?>
                        <span class="badge warning detail-urgent-badge"><span class="dot"></span>งานด่วน</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge <?= h($detailUiStatus['class'] ?? 'blue') ?> detail-status-badge">
                        <?= h($detailUiStatus['label'] ?? $installStatusText) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($jobWarningText !== ''): ?>
        <div class="detail-alert <?= h($jobWarningClass) ?>">
            <?= technician_detail_icon_svg('alert', 18) ?>
            <strong><?= h($jobWarningText) ?></strong>
        </div>
    <?php endif; ?>

    <section class="card timeline-card">
        <div class="card-body">
            <div class="timeline" aria-label="ลำดับการดำเนินงาน">
            <?php foreach ($timelineSteps as $index => $step): ?>
                <div class="timeline-step is-<?= h($step['state']) ?>">
                    <div class="timeline-node">
                        <?php if ($step['state'] === 'done'): ?>
                            <?= technician_detail_icon_svg('check', 14) ?>
                        <?php else: ?>
                            <?= h((string) ($index + 1)) ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($index < count($timelineSteps) - 1): ?>
                        <div class="timeline-line"></div>
                    <?php endif; ?>
                    <div class="timeline-label"><?= h($step['label']) ?></div>
                    <div class="timeline-meta"><?= h($step['meta']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        </div>
    </section>

    <section class="callout <?= ($hasReceivedProducts || $hasInstallResult) ? 'is-ready' : 'is-warning' ?>">
        <div class="callout-icon">
            <?= technician_detail_icon_svg(($hasReceivedProducts || $hasInstallResult) ? 'check' : 'truck', 18) ?>
        </div>
        <div class="callout-content">
            <h2><?= h($nextStepTitle) ?></h2>
            <p><?= h($nextStepText) ?></p>
        </div>
    </section>

    <div class="detail-grid">
        <div class="detail-main">
            <?php
                $customerName = trim((string) ($job['customer_name'] ?? '--'));
                $customerInitial = function_exists('mb_substr') ? mb_substr($customerName, 0, 1, 'UTF-8') : substr($customerName, 0, 1);
            ?>
            <section class="customer-info">
                <header class="customer-info__header">
                    <h2>ข้อมูลลูกค้า</h2>
                </header>

                <div class="customer-info__body">
                    <div class="customer-info__avatar"><?= h($customerInitial !== '' ? $customerInitial : '-') ?></div>
                    <div class="customer-info__content">
                        <h3><?= h($customerName) ?></h3>
                        <div class="customer-info__meta">
                            <span><?= technician_detail_icon_svg('hash', 16, 'ref-icon-muted') ?><?= h($job['customer_id'] ?? '--') ?></span>
                            <span><?= technician_detail_icon_svg('phone', 16, 'ref-icon-muted') ?><?= h($job['customer_phone'] ?? '--') ?></span>
                            <span><?= technician_detail_icon_svg('mail', 16, 'ref-icon-muted') ?><?= h($job['customer_email'] ?? '--') ?></span>
                        </div>
                        <div class="customer-info__address">
                            <span><?= technician_detail_icon_svg('map-pin', 16, 'ref-icon-muted') ?><?= h($job['customer_address'] ?? '--') ?></span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="product-table">
                <header class="product-table__header">
                    <h2>รายการสินค้า</h2>
                    <p><?= h((string) $itemCount) ?> รายการ · <?= h((string) $totalQty) ?> ชิ้น</p>
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

                    <?php if ($itemCount === 0): ?>
                        <div class="product-table__empty">ไม่พบรายการสินค้า</div>
                    <?php endif; ?>

                    <div class="product-table__body" role="rowgroup">
                        <?php foreach ($items as $item): ?>
                            <div class="product-table__row" role="row">
                                <div class="product-table__item">
                                    <strong><?= h($item['pro_name'] ?? '--') ?></strong>
                                </div>
                                <span><span class="product-table__qty"><?= h((string) ($item['install_qty'] ?? 0)) ?> ชิ้น</span></span>
                                <span class="product-table__money"><?= h(number_format((float) ($item['install_price'] ?? 0), 2)) ?></span>
                                <strong class="product-table__money"><?= h(number_format((float) ($item['install_total'] ?? 0), 2)) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <footer class="product-table__total">
                    <span>ยอดรวมค่าติดตั้ง</span>
                    <strong>฿<?= h(number_format($totalAmount, 2)) ?></strong>
                </footer>
            </section>

            <?php if ($hasInstallResult): ?>
                <section class="card installation-result-card" id="installation-result">
                    <header class="card-header">
                        <div>
                            <h2>ผลการติดตั้ง</h2>
                            <p>รูปงานติดตั้งที่ช่างบันทึกไว้</p>
                        </div>
                    </header>
                    <div class="card-body">
                        <div class="proof-gallery-block">
                            <span>รูปงานติดตั้ง</span>
                            <?php if ($installResultPhotos): ?>
                                <div class="proof-gallery">
                                    <?php foreach ($installResultPhotos as $photoIndex => $photoUrl): ?>
                                        <a href="<?= h($photoUrl) ?>" target="_blank" rel="noopener">
                                            <img src="<?= h($photoUrl) ?>" alt="รูปงานติดตั้งที่ <?= h((string) ($photoIndex + 1)) ?>">
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="product-table__empty">ไม่พบรูปงานติดตั้ง</p>
                            <?php endif; ?>
                            <?php if ($installResultNote !== ''): ?>
                                <div class="installation-result-note">
                                    <strong>หมายเหตุ</strong>
                                    <p><?= nl2br(h($installResultNote)) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

        </div>

        <aside class="detail-side">
            <section class="action-panel">
                <?php if ($isPreAcceptJob): ?>
                    <header class="action-panel__header">
                        <h2>การดำเนินการ</h2>
                    </header>

                    <div class="action-panel__body action-panel__body--summary">
                        <div class="action-panel__summary action-panel__install-info">
                            <div class="action-panel__summary-row">
                                <span>รหัสมอบหมายงาน</span>
                                <strong><?= h($job['assign_id'] ?? '--') ?></strong>
                            </div>
                            <div class="action-panel__summary-row">
                                <span>วันที่มอบหมาย</span>
                                <strong><?= h(technician_job_detail_date($job['assign_date'] ?? null, true)) ?></strong>
                            </div>
                            <div class="action-panel__summary-row">
                                <span>วันที่ / เวลาติดตั้ง</span>
                                <strong><?= h($installDateTime) ?></strong>
                            </div>
                            <div class="action-panel__summary-total action-panel__summary-total--compact">
                                <span>ค่าติดตั้ง</span>
                                <strong>฿<?= h(number_format($totalAmount, 2)) ?></strong>
                            </div>
                        </div>

                        <div class="action-panel__actions">
                            <form class="action-panel__decision-form" method="POST" action="<?= h(app_system_url('technician/accept_job.php')) ?>">
                                <input type="hidden" name="assign_id" value="<?= h($job['assign_id'] ?? '') ?>">
                                <input type="hidden" name="action" value="accept">
                                <button class="action-panel__decision action-panel__decision--accept" type="submit" data-technician-confirm="ยืนยันรับงานนี้หรือไม่?">
                                    <?= technician_detail_icon_svg('check', 16) ?>
                                    <span>รับงานติดตั้งนี้</span>
                                </button>
                            </form>

                            <form class="action-panel__decision-form" method="POST" action="<?= h(app_system_url('technician/accept_job.php')) ?>">
                                <input type="hidden" name="assign_id" value="<?= h($job['assign_id'] ?? '') ?>">
                                <input type="hidden" name="action" value="reject">
                                <button class="action-panel__decision action-panel__decision--reject" type="submit" data-technician-confirm="ต้องการปฏิเสธงานนี้หรือไม่?">
                                    <span>ปฏิเสธงาน</span>
                                </button>
                            </form>
                        </div>

                        <div class="action-panel__notice">
                            <?= technician_detail_icon_svg('alert', 18) ?>
                            <span>หลังกดรับงาน ระบบจะแจ้งเตือนหัวหน้าและย้ายงานไปยัง "งานของฉัน"</span>
                        </div>
                    </div>
                <?php else: ?>
                    <header class="action-panel__header">
                        <h2>การดำเนินการ</h2>
                    </header>

                    <div class="action-panel__body">
                        <div class="action-panel__summary action-panel__install-info">
                            <div class="action-panel__summary-row">
                                <span>รหัสมอบหมายงาน</span>
                                <strong><?= h($job['assign_id'] ?? '--') ?></strong>
                            </div>
                            <div class="action-panel__summary-row">
                                <span>วันที่มอบหมาย</span>
                                <strong><?= h(technician_job_detail_date($job['assign_date'] ?? null, true)) ?></strong>
                            </div>
                            <div class="action-panel__summary-row">
                                <span>วันที่ / เวลาติดตั้ง</span>
                                <strong><?= h($installDateTime) ?></strong>
                            </div>
                            <div class="action-panel__summary-total action-panel__summary-total--compact">
                                <span>ค่าติดตั้ง</span>
                                <strong>฿<?= h(number_format($totalAmount, 2)) ?></strong>
                            </div>
                        </div>

                        <div class="action-panel__state <?= ($hasReceivedProducts || $hasInstallResult) ? 'is-ready' : 'is-warning' ?>">
                            <?= technician_detail_icon_svg(($hasReceivedProducts || $hasInstallResult) ? 'check' : 'truck', 18) ?>
                            <div>
                                <?php if ($hasInstallResult): ?>
                                    <strong>ช่างบันทึกผลการติดตั้งเรียบร้อยแล้ว</strong>
                                    <span>กำลังรอหัวหน้างานตรวจสอบและอนุมัติ</span>
                                <?php else: ?>
                                    <strong><?= $hasReceivedProducts ? 'รับสินค้าแล้ว' : 'ยังไม่ได้รับสินค้าจากคลัง' ?></strong>
                                    <span><?= $hasReceivedProducts ? 'สามารถดูใบติดตั้งและดำเนินงานตามขั้นตอนถัดไป' : 'กรุณายืนยันรับสินค้าก่อนวันติดตั้ง' ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($startJobFlash): ?>
                            <div class="action-panel__state <?= $startJobFlash['success'] ? 'is-ready' : 'is-warning' ?>" role="status">
                                <span><?= h($startJobFlash['message']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($hasReceivedProducts && $canConfirmReceive && (string) ($job['setup_status'] ?? '') === '2'): ?>
                            <form class="action-panel__decision-form" method="POST" action="<?= h(app_system_url('technician/job_detail.php?id=' . urlencode($assignId))) ?>">
                                <input type="hidden" name="action" value="start_job">
                                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['technician_start_job_csrf']) ?>">
                                <button class="action-panel__submit" type="submit" data-technician-confirm="ยืนยันเริ่มติดตั้งงานนี้หรือไม่?">
                                    <i class="fa-solid fa-wrench" aria-hidden="true"></i>
                                    <span>เริ่มงาน</span>
                                </button>
                            </form>
                        <?php elseif ($isInstalling && $canConfirmReceive && !$isInstallDone && !$hasInstallResult): ?>
                            <a class="action-panel__submit" href="<?= h(app_system_url('technician/job_detail.php?id=' . urlencode($assignId) . '&install=complete')) ?>">
                                <i class="fa-solid fa-check" aria-hidden="true"></i>
                                <span>ติดตั้งเสร็จ</span>
                            </a>
                        <?php elseif ($hasInstallResult): ?>
                            <a class="action-panel__submit" href="#installation-result">
                                <i class="fa-solid fa-images" aria-hidden="true"></i>
                                <span>ดูผลการติดตั้ง</span>
                            </a>
                        <?php endif; ?>

                        <?php if (!$hasReceivedProducts && $canConfirmReceive): ?>
                            <a class="action-panel__submit" href="<?= h(app_system_url('technician/job_detail.php?id=' . urlencode((string) ($job['assign_id'] ?? '')) . '&receive=confirm')) ?>">
                                <?= technician_detail_icon_svg('package', 16) ?>
                                <span>ยืนยันรับสินค้า</span>
                            </a>
                        <?php endif; ?>

                        <a class="action-panel__link" href="<?= h(app_system_url('technician/setup_slip.php?id=' . urlencode($setupId))) ?>">
                            <?= technician_detail_icon_svg('file', 16) ?>
                            <span>ใบติดตั้ง</span>
                        </a>

                        <a class="action-panel__link action-panel__phone" href="tel:<?= h(preg_replace('/\D+/', '', (string) ($job['customer_phone'] ?? ''))) ?>">
                            <?= technician_detail_icon_svg('phone', 16) ?>
                            <span>โทรหาลูกค้า</span>
                        </a>
                    </div>
                <?php endif; ?>
            </section>
        </aside>
    </div>
</section>

<script>
(() => {
    const inputs = Array.from(document.querySelectorAll('[data-receive-proof-input]'));
    const preview = document.querySelector('[data-receive-proof-preview]');

    if (!inputs.length || !preview) {
        return;
    }

    const renderPreview = () => {
        preview.innerHTML = '';
        const files = inputs.flatMap((input) => Array.from(input.files || []));

        files.slice(0, 5).forEach((file) => {
            if (!file.type.startsWith('image/')) {
                return;
            }

            const image = document.createElement('img');
            image.src = URL.createObjectURL(file);
            image.alt = 'ตัวอย่างหลักฐานการรับสินค้า';
            image.onload = () => URL.revokeObjectURL(image.src);
            preview.appendChild(image);
        });

        if (files.length > 5) {
            const warning = document.createElement('strong');
            warning.textContent = 'เลือกได้สูงสุด 5 รูป';
            preview.appendChild(warning);
        }
    };

    inputs.forEach((input) => input.addEventListener('change', renderPreview));
})();
</script>

<?php
layout_footer();
