<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('2');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_to(app_system_url('sale/index.php'));
}

$setupId = trim($_POST['setup_id'] ?? '');
$userId = trim($_POST['user_id'] ?? '');
$setupAddress = trim($_POST['setup_address'] ?? '');
$setupNote = trim($_POST['setup_note'] ?? '');
$itemsJson = $_POST['selected_items_json'] ?? '[]';
$saleId = $_SESSION['user_id'] ?? '';

if ($saleId === '') {
    redirect_to(app_system_url('login.php'));
}

if (
    !preg_match('/^SET-[0-9]{7}$/', $setupId) ||
    $userId === '' ||
    $setupAddress === ''
) {
    redirect_to(app_system_url('sale/index.php?status=error'));
}

$selectedItems = json_decode($itemsJson, true);

if (!is_array($selectedItems) || count($selectedItems) === 0) {
    redirect_to(app_system_url('sale/index.php?status=error'));
}

$transactionStarted = false;

try {
    $customerCheck = $conn->prepare("
        SELECT user_id
        FROM `user`
        WHERE user_id = ?
          AND user_role = 0
        LIMIT 1
    ");

    if (!$customerCheck) {
        throw new RuntimeException('ไม่สามารถตรวจสอบข้อมูลลูกค้าได้');
    }

    $customerCheck->bind_param('s', $userId);
    $customerCheck->execute();

    if ($customerCheck->get_result()->num_rows !== 1) {
        throw new RuntimeException('ไม่พบข้อมูลลูกค้า');
    }

    $validatedItems = [];
    $seen = [];

    $productCheck = $conn->prepare("
        SELECT pro_id, pro_price_install
        FROM product
        WHERE pro_id = ?
        LIMIT 1
    ");

    if (!$productCheck) {
        throw new RuntimeException('ไม่สามารถตรวจสอบข้อมูลสินค้าได้');
    }

    foreach ($selectedItems as $item) {
        $proId = trim((string) ($item['pro_id'] ?? ''));
        $qty = (int) ($item['qty'] ?? 0);

        if ($proId === '' || $qty <= 0) {
            continue;
        }

        $productCheck->bind_param('s', $proId);
        $productCheck->execute();
        $productRow = $productCheck->get_result()->fetch_assoc();

        if (!$productRow) {
            throw new RuntimeException('พบสินค้าที่ไม่มีอยู่ในระบบ');
        }

        if (isset($seen[$proId])) {
            $index = $seen[$proId];
            $validatedItems[$index]['qty'] += $qty;
            $validatedItems[$index]['total'] =
                $validatedItems[$index]['qty'] *
                $validatedItems[$index]['price'];
            continue;
        }

        $price = (float) $productRow['pro_price_install'];

        $validatedItems[] = [
            'pro_id' => $proId,
            'qty' => $qty,
            'price' => $price,
            'total' => $price * $qty,
        ];

        $seen[$proId] = count($validatedItems) - 1;
    }

    if (count($validatedItems) === 0) {
        throw new RuntimeException('กรุณาเลือกสินค้า');
    }

    $firstProductId = $validatedItems[0]['pro_id'];
    $setupDate = date('Y-m-d');
    $setupLocation = $setupAddress;

    // คงสถานะเดิมตาม flow ปัจจุบัน
    $setupStatus = 0;

    $conn->begin_transaction();
    $transactionStarted = true;

    $insertSetup = $conn->prepare("
        INSERT INTO setup (
            setup_id,
            user_id,
            sale_id,
            pro_id,
            setup_date,
            setup_location,
            setup_status,
            setup_address,
            setup_note,
            created_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    if (!$insertSetup) {
        throw new RuntimeException('ไม่สามารถเตรียมบันทึกใบงานได้');
    }

    $insertSetup->bind_param(
        'ssssssiss',
        $setupId,
        $userId,
        $saleId,
        $firstProductId,
        $setupDate,
        $setupLocation,
        $setupStatus,
        $setupAddress,
        $setupNote
    );

    if (!$insertSetup->execute()) {
        throw new RuntimeException('ไม่สามารถบันทึกใบงานได้');
    }

    $insertDetail = $conn->prepare("
        INSERT INTO install_detail (
            setup_id,
            pro_id,
            install_qty,
            install_price,
            install_total
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    if (!$insertDetail) {
        throw new RuntimeException('ไม่สามารถเตรียมบันทึกรายการสินค้าได้');
    }

    foreach ($validatedItems as $item) {
        $proId = $item['pro_id'];
        $qty = $item['qty'];
        $price = $item['price'];
        $total = $item['total'];

        $insertDetail->bind_param(
            'ssidd',
            $setupId,
            $proId,
            $qty,
            $price,
            $total
        );

        if (!$insertDetail->execute()) {
            throw new RuntimeException('ไม่สามารถบันทึกรายการสินค้าได้');
        }
    }

    $conn->commit();
    $transactionStarted = false;

    redirect_to(
        app_system_url(
            'sale/setups.php?status=created&id=' . urlencode($setupId)
        )
    );
} catch (Throwable $e) {
    if ($transactionStarted) {
        $conn->rollback();
    }

    die(
        '<pre style="padding:20px;font-size:16px;">' .
        htmlspecialchars($e->getMessage()) .
        '</pre>'
    );
}