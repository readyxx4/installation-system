<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login(['1', '2', '3']);

$setup_id = trim($_GET['id'] ?? '');
$from_manager = trim($_GET['from'] ?? '') === 'manager';

if ($setup_id === '') {
    redirect_to(app_system_url('sale/setups.php?status=error'));
}

function slip_table_exists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function slip_column_exists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function slip_thai_date(?string $date): string
{
    if (empty($date)) {
        return '-';
    }

    $ts = strtotime($date);
    if (!$ts) {
        return '-';
    }

    $months = [
        1 => 'มกราคม',
        2 => 'กุมภาพันธ์',
        3 => 'มีนาคม',
        4 => 'เมษายน',
        5 => 'พฤษภาคม',
        6 => 'มิถุนายน',
        7 => 'กรกฎาคม',
        8 => 'สิงหาคม',
        9 => 'กันยายน',
        10 => 'ตุลาคม',
        11 => 'พฤศจิกายน',
        12 => 'ธันวาคม'
    ];

    $day = (int) date('j', $ts);
    $month = $months[(int) date('n', $ts)];
    $year = (int) date('Y', $ts) + 543;

    return $day . ' ' . $month . ' ' . $year;
}

function slip_money($value): string
{
    return number_format((float) $value, 2) . ' บาท';
}

function thai_baht_text(float $amount): string
{
    $amount = round($amount, 2);
    $formatted = number_format($amount, 2, '.', '');
    [$integerPart, $decimalPart] = explode('.', $formatted);

    $digits = [
        0 => 'ศูนย์',
        1 => 'หนึ่ง',
        2 => 'สอง',
        3 => 'สาม',
        4 => 'สี่',
        5 => 'ห้า',
        6 => 'หก',
        7 => 'เจ็ด',
        8 => 'แปด',
        9 => 'เก้า',
    ];

    $units = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];

    $readSixDigits = function (string $number) use ($digits, $units): string {
        $number = str_pad($number, 6, '0', STR_PAD_LEFT);
        $text = '';

        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $number[$i];
            $position = 5 - $i;

            if ($digit === 0) {
                continue;
            }

            if ($position === 1) {
                if ($digit === 1) {
                    $text .= 'สิบ';
                } elseif ($digit === 2) {
                    $text .= 'ยี่สิบ';
                } else {
                    $text .= $digits[$digit] . 'สิบ';
                }
                continue;
            }

            if ($position === 0) {
                if ($digit === 1 && $text !== '') {
                    $text .= 'เอ็ด';
                } else {
                    $text .= $digits[$digit];
                }
                continue;
            }

            $text .= $digits[$digit] . $units[$position];
        }

        return $text;
    };

    $readInteger = function (string $number) use (&$readInteger, $readSixDigits): string {
        $number = ltrim($number, '0');

        if ($number === '') {
            return 'ศูนย์';
        }

        if (strlen($number) <= 6) {
            return $readSixDigits($number);
        }

        $head = substr($number, 0, -6);
        $tail = substr($number, -6);

        $text = $readInteger($head) . 'ล้าน';

        if ((int) $tail > 0) {
            $text .= $readSixDigits($tail);
        }

        return $text;
    };

    $result = $readInteger($integerPart) . 'บาท';

    if ((int) $decimalPart === 0) {
        return $result . 'ถ้วน';
    }

    return $result . $readSixDigits($decimalPart) . 'สตางค์';
}

$install_date_select = slip_column_exists($conn, 'assignment', 'assign_install_date') ? 'a.assign_install_date' : 'NULL AS assign_install_date';
$install_time_select = slip_column_exists($conn, 'assignment', 'assign_install_time') ? 'a.assign_install_time' : 'NULL AS assign_install_time';

$stmt = $conn->prepare("
    SELECT
        s.setup_id,
        s.setup_date,
        s.setup_location,
        s.setup_address,
        s.setup_note,
        s.setup_status,
        s.created_at,

        u.user_id,
        u.user_name,
        u.user_phone,
        u.user_email,
        u.user_address,

        p.pro_id AS main_pro_id,
        p.pro_name AS main_pro_name,
        p.pro_price_install AS main_pro_price_install,
        pt.protype_name AS main_protype_name,

        a.assign_id,
        a.assign_date,
        a.assign_status,
        {$install_date_select},
        {$install_time_select},

        t.tech_id,
        t.tech_name,
        t.tech_fullname,
        t.tech_phone,
        t.tech_email
    FROM setup s
    LEFT JOIN `user` u ON s.user_id = u.user_id
    LEFT JOIN product p ON s.pro_id = p.pro_id
    LEFT JOIN product_type pt ON p.protype_id = pt.protype_id
    LEFT JOIN assignment a ON s.setup_id = a.setup_id AND a.assign_status IN (1, 2, 5)
    LEFT JOIN technicians t ON a.tech_id = t.tech_id
    WHERE s.setup_id = ?
    ORDER BY a.assign_date DESC
    LIMIT 1
");
$stmt->bind_param('s', $setup_id);
$stmt->execute();
$setup = $stmt->get_result()->fetch_assoc();

if (!$setup) {
    redirect_to(app_system_url('sale/setups.php?status=notfound'));
}

$items = [];

if (slip_table_exists($conn, 'install_detail')) {
    $detail_sql = "
        SELECT
            d.pro_id,
            COALESCE(p.pro_name, d.pro_id) AS pro_name,
            COALESCE(pt.protype_name, '-') AS protype_name,
            COALESCE(d.install_qty, 1) AS install_qty,
            COALESCE(d.install_price, p.pro_price_install, 0) AS install_price,
            COALESCE(d.install_total, COALESCE(d.install_qty, 1) * COALESCE(d.install_price, p.pro_price_install, 0)) AS install_total
        FROM install_detail d
        LEFT JOIN product p ON d.pro_id = p.pro_id
        LEFT JOIN product_type pt ON p.protype_id = pt.protype_id
        WHERE d.setup_id = ?
        ORDER BY d.detail_id ASC
    ";

    $detail_stmt = $conn->prepare($detail_sql);
    $detail_stmt->bind_param('s', $setup_id);
    $detail_stmt->execute();
    $detail_result = $detail_stmt->get_result();

    while ($row = $detail_result->fetch_assoc()) {
        $items[] = $row;
    }
}

if (count($items) === 0) {
    $price = (float) ($setup['main_pro_price_install'] ?? 0);
    $items[] = [
        'pro_id' => $setup['main_pro_id'] ?? '-',
        'pro_name' => $setup['main_pro_name'] ?? '-',
        'protype_name' => $setup['main_protype_name'] ?? '-',
        'install_qty' => 1,
        'install_price' => $price,
        'install_total' => $price,
    ];
}

$total_amount = 0;
foreach ($items as $item) {
    $total_amount += (float) ($item['install_total'] ?? 0);
}

$company_name = 'ห้างโอวเปงฮง จำกัด';
$company_address = 'บริการติดตั้งและจัดการงานติดตั้งเครื่องใช้ไฟฟ้า';
$logo_path = 'uploads/system/owpenghong_logo.jpg';

if (slip_table_exists($conn, 'system')) {
    $system_result = $conn->query("SELECT system_name, system_desc, system_logo FROM system LIMIT 1");
    if ($system_result && $system_result->num_rows > 0) {
        $system = $system_result->fetch_assoc();
        if (!empty($system['system_name'])) {
            $company_name = $system['system_name'];
        }
        if (!empty($system['system_desc'])) {
            $company_address = $system['system_desc'];
        }
        if (!empty($system['system_logo'])) {
            $logo_path = $system['system_logo'];
        }
    }
}


/*
 * ข้อมูลบริษัทสำหรับเอกสารใบติดตั้ง
 * อ้างอิงสำนักงานใหญ่ของ บริษัท ห้างโอวเปงฮง (2009) จำกัด
 */
$slip_company_name = 'บริษัท ห้างโอวเปงฮง (2009) จำกัด';
$slip_company_branch = 'สำนักงานใหญ่';
$slip_company_address = '311-315 หมู่ที่ 2 ถนนราชนิกูล ตำบลในเมือง อำเภอบ้านไผ่ จังหวัดขอนแก่น 40110';
$slip_company_phone = '043-272-136';
$slip_company_tax_id = '0405551001144';

$display_install_date = !empty($setup['assign_install_date']) ? $setup['assign_install_date'] : ($setup['setup_date'] ?? null);

$slip_back_url = $from_manager
    ? app_system_url('manager/assignment_list.php')
    : app_system_url('sale/setups.php');

layout_header('ใบติดตั้ง', 'setups');
?>

<link rel="stylesheet"
    href="<?= h(app_asset_url('sale/assets/css/setup_slip.css')) ?>?v=<?= h(asset_version('sale/assets/css/setup_slip.css')) ?>">

<div class="install-slip-page">
    <div class="install-slip-toolbar no-print manager-slip-toolbar">
        <a class="install-slip-back" href="#" onclick="history.back(); return false;">
            ย้อนกลับ
        </a>

        <button class="install-slip-print" type="button" onclick="window.print()">
            พิมพ์ใบติดตั้ง
        </button>
    </div>

    <section class="install-slip-paper">
        <header class="install-slip-header">
            <div class="install-slip-company">
                <div class="install-slip-logo">
                    <img src="<?= h(app_asset_url($logo_path)) ?>" alt="โอวเปงฮง">
                </div>

                <div class="install-slip-company-text">
                    <div class="install-slip-company-title">
                        <strong><?= h($slip_company_name) ?></strong>
                        <span>(<?= h($slip_company_branch) ?>)</span>
                    </div>

                    <p><?= h($slip_company_address) ?></p>

                    <p>
                        โทร. <?= h($slip_company_phone) ?>
                        &nbsp;&nbsp;
                        เลขประจำตัวผู้เสียภาษี <?= h($slip_company_tax_id) ?>
                    </p>
                </div>
            </div>

            <div class="install-slip-doc-title">
                <strong>ใบติดตั้ง</strong>
            </div>
        </header>

        <section class="install-slip-customer-block">
            <div class="install-slip-customer-box">
                <div>
                    <span>ชื่อลูกค้า :</span>
                    <strong><?= h($setup['user_name'] ?? '-') ?></strong>
                </div>

                <div>
                    <span>ที่อยู่ติดตั้ง :</span>
                    <strong><?= h($setup['setup_address'] ?: ($setup['setup_location'] ?? '-')) ?></strong>
                </div>

                <div>
                    <span>โทร :</span>
                    <strong><?= h($setup['user_phone'] ?? '-') ?></strong>
                </div>
            </div>

            <div class="install-slip-doc-meta">
                <div>
                    <span>เลขที่เอกสาร</span>
                    <strong><?= h($setup['setup_id']) ?></strong>
                </div>

                <div>
                    <span>วันที่สร้าง</span>
                    <strong><?= h(slip_thai_date($setup['created_at'] ?? null)) ?></strong>
                </div>
            </div>
        </section>

        <section class="install-slip-products">
            <table class="install-slip-table">
                <thead>
                    <tr>
                        <th class="col-no">ลำดับ</th>
                        <th class="col-item">รายละเอียดสินค้า</th>
                        <th class="col-qty">จำนวน</th>
                        <th class="col-unit">ค่าติดตั้ง/หน่วย</th>
                        <th class="col-net">จำนวนเงิน</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($items as $index => $item): ?>
                        <tr>
                            <td class="center"><?= h((string) ($index + 1)) ?></td>

                            <td class="item-detail">
                                <strong><?= h($item['pro_name'] ?? '-') ?></strong>
                            </td>

                            <td class="center">
                                <?= h((string) ($item['install_qty'] ?? 1)) ?>
                            </td>

                            <td class="right">
                                <?= h(number_format((float) ($item['install_price'] ?? 0), 2)) ?>
                            </td>


                            <td class="right">
                                <?= h(number_format((float) ($item['install_total'] ?? 0), 2)) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="install-slip-summary-row">
            <div class="install-slip-note">
                <div class="install-slip-note-row">
                    <span>หมายเหตุ :</span>
                    <strong><?= h($setup['setup_note'] ?: '-') ?></strong>
                </div>

                <div class="install-slip-amount-text">
                    <span>จำนวนเงินเป็นตัวอักษร :</span>
                    <strong><?= h(thai_baht_text($total_amount)) ?></strong>
                </div>
            </div>

            <div class="install-slip-totals">
                <div class="grand-total">
                    <span>รวมค่าติดตั้งทั้งหมด</span>
                    <strong><?= h(number_format($total_amount, 2)) ?> บาท</strong>
                </div>
            </div>
        </section>

        <footer class="install-slip-footer">
            <div class="install-slip-signature">
                <p></p>
                <strong>ผู้จัดทำใบงาน</strong>
                <small>พนักงานขาย</small>
                <span>วันที่ ____ / ____ / ______</span>
            </div>

            <div class="install-slip-signature">
                <p></p>
                <strong>ช่างผู้ติดตั้ง</strong>
                <small>ผู้ดำเนินงานติดตั้ง</small>
                <span>วันที่ ____ / ____ / ______</span>
            </div>

            <div class="install-slip-signature">
                <p></p>
                <strong>ลูกค้า / ผู้รับงาน</strong>
                <small>ผู้ตรวจรับงานติดตั้ง</small>
                <span>วันที่ ____ / ____ / ______</span>
            </div>
        </footer>
    </section>
</div>


<?php layout_footer(); ?>