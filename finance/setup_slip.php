<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login(['1', '2', '3']);

$setup_id = trim($_GET['id'] ?? '');
$from_manager = trim($_GET['from'] ?? '') === 'manager';

if ($setup_id === '') {
    redirect_to(app_system_url('finance/setups.php?status=error'));
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
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
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
    redirect_to(app_system_url('finance/setups.php?status=notfound'));
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

$display_install_date = !empty($setup['assign_install_date']) ? $setup['assign_install_date'] : ($setup['setup_date'] ?? null);

$slip_back_url = $from_manager
    ? app_system_url('manager/assignment_list.php')
    : app_system_url('finance/setups.php');

$assign_url = app_system_url('manager/assignments.php?setup_id=' . urlencode($setup['setup_id']));
$cancel_assign_url = app_system_url('manager/assignment_list.php?action=cancel&id=' . urlencode($setup['setup_id']));

layout_header('ใบติดตั้ง', 'setups');
?>

<div class="install-slip-page">
    <div class="install-slip-toolbar no-print manager-slip-toolbar">
        <a class="install-slip-back" href="<?= h($slip_back_url) ?>">
            <?= $from_manager ? 'กลับ' : 'กลับ' ?>
        </a>

        <?php if ($from_manager): ?>
            <?php if ((string) ($setup['setup_status'] ?? '') === '0'): ?>
                <a class="install-slip-manager-assign" href="<?= h($assign_url) ?>">
                    มอบหมายงานติดตั้ง
                </a>
                <a class="install-slip-manager-cancel" href="<?= h($slip_back_url) ?>">
                    ยกเลิก
                </a>
            <?php else: ?>
                <a class="install-slip-manager-edit" href="<?= h($assign_url) ?>">
                    แก้ไขการมอบหมาย
                </a>
                <?php if ((string) ($setup['setup_status'] ?? '') !== '4'): ?>
                    <a
                        class="install-slip-manager-cancel"
                        href="<?= h($cancel_assign_url) ?>"
                        onclick="return confirm('ยืนยันการยกเลิกการมอบหมายงานนี้หรือไม่?')"
                    >
                        ยกเลิกงาน
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <!-- <button class="install-slip-print" type="button" onclick="window.print()">
            พิมพ์ใบติดตั้ง
        </button> -->
    </div>

    <section class="install-slip-paper">
        <header class="install-slip-header">
            <div class="install-slip-brand">
                <div class="install-slip-logo">
                    <img src="<?= h(app_asset_url($logo_path)) ?>" alt="<?= h($company_name) ?>">
                </div>

                <div class="install-slip-brand-text">
                    <h1><?= h($company_name) ?></h1>
                    <p>Installation System</p>
                </div>
            </div>

            <div class="install-slip-title">
                <span>เอกสารงานติดตั้ง</span>
                <strong>ใบติดตั้ง</strong>
            </div>
        </header>

        <div class="install-slip-summary">
            <div>
                <span>รหัสงานติดตั้ง</span>
                <strong><?= h($setup['setup_id']) ?></strong>
            </div>


            <div>
                <span>รวมค่าติดตั้ง</span>
                <strong><?= h(slip_money($total_amount)) ?></strong>
            </div>
        </div>

        <section class="install-slip-section">
            <h2>
                <span class="install-slip-section-icon">●</span>
                ข้อมูลลูกค้า
            </h2>

            <div class="install-slip-info-grid">

                <div>
                    <span>ชื่อผู้ใช้</span>
                    <strong><?= h($setup['user_name'] ?? '-') ?></strong>
                </div>

                <div>
                    <span>เบอร์โทรศัพท์</span>
                    <strong><?= h($setup['user_phone'] ?? '-') ?></strong>
                </div>

                <div>
                    <span>อีเมล</span>
                    <strong><?= h($setup['user_email'] ?? '-') ?></strong>
                </div>

                <div class="full">
                    <span>ที่อยู่ลูกค้า</span>
                    <strong><?= nl2br(h($setup['user_address'] ?: '-')) ?></strong>
                </div>
            </div>
        </section>

        <section class="install-slip-section">
            <h2>
                <span class="install-slip-section-icon">●</span>
                สถานที่ติดตั้งและรายละเอียดหน้างาน
            </h2>

            <div class="install-slip-info-grid install-slip-location-grid">
                <div>
                    <span>ที่อยู่สำหรับติดตั้ง</span>
                    <strong><?= nl2br(h($setup['setup_address'] ?: ($setup['setup_location'] ?? '-'))) ?></strong>
                </div>

                <div>
                    <span>หมายเหตุ</span>
                    <strong><?= nl2br(h($setup['setup_note'] ?: '-')) ?></strong>
                </div>
            </div>
        </section>

        <section class="install-slip-section">
            <h2>
                <span class="install-slip-section-icon">●</span>
                รายการสินค้าและค่าติดตั้ง
            </h2>

            <table class="install-slip-table">
                <thead>
                    <tr>
                        <th class="center">ลำดับ</th>
                        <th>รหัสสินค้า</th>
                        <th>รายการสินค้า</th>
                        <th>ประเภท</th>
                        <th class="center">จำนวน</th>
                        <th class="right">ค่าติดตั้ง/หน่วย</th>
                        <th class="right">รวม</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($items as $index => $item): ?>
                        <tr>
                            <td class="center"><?= h((string) ($index + 1)) ?></td>
                            <td><?= h($item['pro_id'] ?? '-') ?></td>
                            <td><?= h($item['pro_name'] ?? '-') ?></td>
                            <td><?= h($item['protype_name'] ?? '-') ?></td>
                            <td class="center"><?= h((string) ($item['install_qty'] ?? 1)) ?></td>
                            <td class="right"><?= h(number_format((float) ($item['install_price'] ?? 0), 2)) ?></td>
                            <td class="right"><?= h(number_format((float) ($item['install_total'] ?? 0), 2)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

                <tfoot>
                    <tr>
                        <td colspan="6" class="right">รวมค่าติดตั้งทั้งสิ้น</td>
                        <td class="right total"><?= h(number_format((float) $total_amount, 2)) ?></td>
                    </tr>
                </tfoot>
            </table>
        </section>

        <section class="install-slip-note">
            <h2>
                <span class="install-slip-section-icon">●</span>
                หมายเหตุ
            </h2>
            <div class="install-slip-note-lines">
                <div></div>
                <div></div>
            </div>
        </section>

        <section class="install-slip-signatures">
            <div>
                <strong>ลงชื่อผู้รับงาน (ลูกค้า)</strong>
                <p></p>
                <span>( ______________________________ )</span>
                <small>วันที่ ______ / ______ / ______</small>
            </div>

            <div>
                <strong>ลงชื่อผู้ติดตั้ง (เจ้าหน้าที่)</strong>
                <p></p>
                <span>( ______________________________ )</span>
                <small>วันที่ ______ / ______ / ______</small>
            </div>
        </section>

        <footer class="install-slip-footer">
            <span>โทรศัพท์ 012-345-6789</span>
            <span>opphenghong.install@gmail.com</span>
            <span><?= h($company_address) ?></span>
        </footer>
    </section>
</div>

<style>
/* ===== Installation Slip Clean A4 - Single Row Detail ===== */
.install-slip-page {
    width: 210mm;
    max-width: 210mm;
    margin: 0 auto;
    padding-bottom: 16px;
}

.install-slip-toolbar {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-bottom: 12px;
}

.install-slip-back,
.install-slip-print {
    min-height: 40px;
    padding: 0 18px;
    border-radius: 12px;
    border: 1px solid #d4d4d8;
    text-decoration: none;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.install-slip-back {
    background: #ffffff;
    color: #27272a;
}

.install-slip-print {
    background: #111827;
    color: #ffffff;
}

.install-slip-paper {
    width: 210mm;
    min-height: 297mm;
    max-height: 297mm;
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #d4d4d8;
    padding: 10mm 11mm;
    box-sizing: border-box;
    color: #111827;
    box-shadow: 0 16px 42px rgba(15, 23, 42, 0.12);
}

.install-slip-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12mm;
    padding-bottom: 8mm;
    border-bottom: 2px solid #111827;
}

.install-slip-brand {
    display: flex;
    align-items: center;
    gap: 8mm;
    min-width: 0;
}

.install-slip-logo {
    width: 26mm;
    height: 26mm;
    border-radius: 50%;
    overflow: hidden;
    flex: 0 0 26mm;
}

.install-slip-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.install-slip-brand-text h1 {
    margin: 2mm 0 1mm;
    font-size: 24px;
    line-height: 1.12;
    color: #111827;
    font-weight: 900;
}

.install-slip-brand-text p {
    margin: 0;
    font-size: 12px;
    color: #404040;
    font-weight: 500;
}

.install-slip-title {
    min-width: 54mm;
    border: 1px solid #bdbdbd;
    border-radius: 5mm;
    padding: 4mm 6mm;
    text-align: center;
}

.install-slip-title span {
    display: block;
    font-size: 11px;
    color: #111827;
    font-weight: 700;
    margin-bottom: 1mm;
}

.install-slip-title strong {
    display: block;
    font-size: 28px;
    line-height: 1;
    color: #000000;
    font-weight: 900;
}

.install-slip-summary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8mm;
    margin: 7mm 0 6mm;
}

.install-slip-summary div {
    border: 1px solid #cfcfcf;
    border-radius: 4mm;
    padding: 4mm 5mm;
    min-height: 18mm;
    box-sizing: border-box;
}

.install-slip-summary span,
.install-slip-info-grid span {
    display: block;
    color: #525252;
    font-size: 10px;
    font-weight: 700;
}

.install-slip-summary strong {
    color: #111827;
    font-size: 15px;
    font-weight: 900;
}

.install-slip-section {
    margin-top: 6mm;
}

.install-slip-section h2,
.install-slip-note h2 {
    margin: 0 0 3mm;
    padding-bottom: 2.2mm;
    border-bottom: 2px solid #111827;
    font-size: 17px;
    color: #111827;
    font-weight: 900;
    display: block;
}

.install-slip-section-icon {
    display: none !important;
}

.install-slip-info-grid,
.install-slip-location-grid {
    display: block;
    border-top: 1px solid #d4d4d8;
    border-bottom: 1px solid #d4d4d8;
}

.install-slip-info-grid div,
.install-slip-location-grid div,
.install-slip-info-grid .full {
    display: flex;
    align-items: flex-start;
    gap: 7mm;
    min-height: auto;
    padding: 2.7mm 3mm;
    border: 0 !important;
    border-bottom: 1px solid #e5e7eb !important;
    box-sizing: border-box;
}

.install-slip-info-grid div:last-child,
.install-slip-location-grid div:last-child {
    border-bottom: 0 !important;
}

.install-slip-info-grid span,
.install-slip-location-grid span {
    width: 38mm;
    flex: 0 0 38mm;
    margin: 0;
    color: #525252;
    font-size: 10px;
    font-weight: 800;
}

.install-slip-info-grid strong,
.install-slip-location-grid strong {
    flex: 1;
    min-width: 0;
    color: #111827;
    font-size: 12.5px;
    font-weight: 700;
    line-height: 1.45;
    word-break: break-word;
}

.install-slip-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
}

.install-slip-table th,
.install-slip-table td {
    border: 1px solid #bdbdbd;
    padding: 2mm 1.8mm;
    vertical-align: top;
    color: #111827;
}

.install-slip-table th {
    background: #f5f5f5;
    font-size: 9.5px;
    font-weight: 900;
    text-align: center;
}

.install-slip-table tfoot td {
    font-weight: 900;
    background: #fafafa;
}

.install-slip-table .center {
    text-align: center;
}

.install-slip-table .right {
    text-align: right;
}

.install-slip-table .total {
    font-size: 11px;
}

.install-slip-note {
    margin-top: 5mm;
    border: 1px solid #d4d4d8;
    border-radius: 3mm;
    padding: 3mm 4mm;
}

.install-slip-note h2 {
    border-bottom: 0;
    padding-bottom: 0;
    margin-bottom: 2mm;
    font-size: 14px;
}

.install-slip-note-lines div {
    height: 7mm;
    border-bottom: 1px dashed #888;
}

.install-slip-signatures {
    margin-top: 5mm;
    border: 1px solid #d4d4d8;
    border-radius: 3mm;
    display: grid;
    grid-template-columns: 1fr 1fr;
    overflow: hidden;
}

.install-slip-signatures div {
    min-height: 31mm;
    padding: 4mm;
    text-align: center;
    box-sizing: border-box;
}

.install-slip-signatures div + div {
    border-left: 1px solid #d4d4d8;
}

.install-slip-signatures strong {
    display: block;
    font-size: 11.5px;
    color: #111827;
    margin-bottom: 6mm;
}

.install-slip-signatures p {
    width: 72%;
    height: 8mm;
    margin: 0 auto 2mm;
    border-bottom: 1.5px solid #111827;
}

.install-slip-signatures span,
.install-slip-signatures small {
    display: block;
    font-size: 10.5px;
    color: #111827;
    line-height: 1.6;
}

.install-slip-footer {
    margin-top: 4mm;
    padding-top: 2mm;
    border-top: 1px solid #d4d4d8;
    display: flex;
    justify-content: center;
    gap: 7mm;
    color: #525252;
    font-size: 9.5px;
}

@media print {
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    html,
    body {
        width: 210mm !important;
        height: 297mm !important;
        background: #ffffff !important;
    }

    body.app-body {
        overflow: visible !important;
        background: #ffffff !important;
    }

    .sidebar,
    .topbar,
    .install-slip-toolbar,
    .no-print {
        display: none !important;
    }

    .app-shell,
    .main,
    .content {
        display: block !important;
        width: 210mm !important;
        height: 297mm !important;
        min-height: 0 !important;
        overflow: hidden !important;
        padding: 0 !important;
        margin: 0 !important;
        background: #ffffff !important;
    }

    .install-slip-page {
        width: 210mm !important;
        max-width: 210mm !important;
        height: 297mm !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .install-slip-paper {
        width: 210mm !important;
        height: 297mm !important;
        min-height: 297mm !important;
        max-height: 297mm !important;
        box-shadow: none !important;
        border: 0 !important;
        padding: 9mm 10mm !important;
        page-break-inside: avoid !important;
    }

    @page {
        size: A4 portrait;
        margin: 0;
    }
}
</style>

<?php layout_footer(); ?>