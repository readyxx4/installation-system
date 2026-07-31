<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('2');

function table_exists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare("
        SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        LIMIT 1
    ");

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->num_rows > 0;
}

function column_exists(mysqli $conn, string $table, string $column): bool
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
    $result = $stmt->get_result();

    return $result->num_rows > 0;
}

function prepare_product_payment_table(mysqli $conn): void
{
    if (!table_exists($conn, 'product_payment')) {
        $conn->query("
            CREATE TABLE product_payment (
                paymentpro_id CHAR(11) PRIMARY KEY,
                paymentpro_date DATETIME NOT NULL,
                user_id CHAR(13) NOT NULL,
                setup_id CHAR(11) NOT NULL,
                paymentpro_status INT(1) NOT NULL DEFAULT 0
            )
        ");
    }

    if (!column_exists($conn, 'product_payment', 'paymentpro_id')) {
        $conn->query("
            ALTER TABLE product_payment
            ADD COLUMN paymentpro_id CHAR(11) PRIMARY KEY
        ");
    }

    if (!column_exists($conn, 'product_payment', 'paymentpro_date')) {
        $conn->query("
            ALTER TABLE product_payment
            ADD COLUMN paymentpro_date DATETIME NULL
        ");
    }

    if (!column_exists($conn, 'product_payment', 'user_id')) {
        $conn->query("
            ALTER TABLE product_payment
            ADD COLUMN user_id CHAR(13) NULL
        ");
    }

    if (!column_exists($conn, 'product_payment', 'setup_id')) {
        $conn->query("
            ALTER TABLE product_payment
            ADD COLUMN setup_id CHAR(11) NULL
        ");
    }

    if (!column_exists($conn, 'product_payment', 'paymentpro_status')) {
        $conn->query("
            ALTER TABLE product_payment
            ADD COLUMN paymentpro_status INT(1) NOT NULL DEFAULT 0
        ");
    }
}

function make_payment_id(mysqli $conn): string
{
    /*
      Data Dictionary กำหนด paymentpro_id เป็น CHAR(11)
      ใช้รูปแบบ:
      PAY-0000001
      PAY-0000002
    */

    $prefix = 'PAY-';

    for ($i = 1; $i <= 9999999; $i++) {
        $running_no = str_pad((string) $i, 7, '0', STR_PAD_LEFT);
        $paymentpro_id = $prefix . $running_no;

        $stmt = $conn->prepare("
            SELECT paymentpro_id
            FROM product_payment
            WHERE paymentpro_id = ?
            LIMIT 1
        ");

        $stmt->bind_param('s', $paymentpro_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return $paymentpro_id;
        }
    }

    throw new Exception('ไม่สามารถสร้างรหัสการจ่ายสินค้าใหม่ได้');
}

function payment_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'รอการจ่ายสินค้า',
        '1' => 'จ่ายสินค้าแล้ว',
        '2' => 'ยกเลิกการจ่ายสินค้า',
        default => 'ไม่ทราบสถานะ',
    };
}

function payment_status_badge($status): string
{
    return match ((string) $status) {
        '0' => 'orange',
        '1' => 'green',
        '2' => 'red',
        default => 'red',
    };
}

prepare_product_payment_table($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $setup_id = trim($_POST['setup_id'] ?? '');
    $paymentpro_date = trim($_POST['paymentpro_date'] ?? '');
    $paymentpro_status = trim($_POST['paymentpro_status'] ?? '');

    if ($setup_id === '' || $paymentpro_date === '' || $paymentpro_status === '') {
        redirect_to(app_system_url('finance/payment.php?status=error'));
    }

    if (!in_array($paymentpro_status, ['0', '1', '2'], true)) {
        redirect_to(app_system_url('finance/payment.php?status=error'));
    }

    $payment_date_sql = str_replace('T', ' ', $paymentpro_date);

    if (strlen($payment_date_sql) === 16) {
        $payment_date_sql .= ':00';
    }

    try {
        $stmt_setup = $conn->prepare("
            SELECT 
                s.setup_id,
                s.user_id,
                u.user_name,
                p.pro_name
            FROM setup s
            LEFT JOIN `user` u ON s.user_id = u.user_id
            LEFT JOIN product p ON s.pro_id = p.pro_id
            WHERE s.setup_id = ?
            LIMIT 1
        ");

        $stmt_setup->bind_param('s', $setup_id);
        $stmt_setup->execute();
        $setup_result = $stmt_setup->get_result();

        if ($setup_result->num_rows !== 1) {
            redirect_to(app_system_url('finance/payment.php?status=error'));
        }

        $setup = $setup_result->fetch_assoc();
        $user_id = $setup['user_id'];

        /*
          ถ้างานติดตั้งนี้เคยมีการบันทึกจ่ายสินค้าแล้ว
          ให้ update แทนการเพิ่มซ้ำ
        */
        $check_payment = $conn->prepare("
            SELECT paymentpro_id
            FROM product_payment
            WHERE setup_id = ?
            LIMIT 1
        ");

        $check_payment->bind_param('s', $setup_id);
        $check_payment->execute();
        $payment_result = $check_payment->get_result();

        if ($payment_result->num_rows > 0) {
            $old_payment = $payment_result->fetch_assoc();
            $paymentpro_id = $old_payment['paymentpro_id'];

            $stmt = $conn->prepare("
                UPDATE product_payment
                SET paymentpro_date = ?,
                    user_id = ?,
                    paymentpro_status = ?
                WHERE paymentpro_id = ?
            ");

            $status_int = (int) $paymentpro_status;

            $stmt->bind_param(
                'ssis',
                $payment_date_sql,
                $user_id,
                $status_int,
                $paymentpro_id
            );

            $stmt->execute();

            redirect_to(app_system_url('finance/payment.php?status=updated'));
        } else {
            $paymentpro_id = make_payment_id($conn);
            $status_int = (int) $paymentpro_status;

            $stmt = $conn->prepare("
                INSERT INTO product_payment
                (
                    paymentpro_id,
                    paymentpro_date,
                    user_id,
                    setup_id,
                    paymentpro_status
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                'ssssi',
                $paymentpro_id,
                $payment_date_sql,
                $user_id,
                $setup_id,
                $status_int
            );

            $stmt->execute();

            redirect_to(app_system_url('finance/payment.php?status=created'));
        }
    } catch (Throwable $e) {
        redirect_to(app_system_url('finance/payment.php?status=error'));
    }
}

$default_payment_date = date('Y-m-d\TH:i');

$setups = $conn->query("
    SELECT
        s.setup_id,
        s.setup_date,
        s.setup_status,
        s.user_id,

        u.user_name,
        u.user_phone,

        p.pro_name,

        pp.paymentpro_id,
        pp.paymentpro_status
    FROM setup s
    LEFT JOIN `user` u ON s.user_id = u.user_id
    LEFT JOIN product p ON s.pro_id = p.pro_id
    LEFT JOIN product_payment pp ON s.setup_id = pp.setup_id
    ORDER BY s.created_at DESC, s.setup_id DESC
");

$payments = $conn->query("
    SELECT
        pp.paymentpro_id,
        pp.paymentpro_date,
        pp.paymentpro_status,

        s.setup_id,
        s.setup_date,

        u.user_name,
        u.user_phone,

        p.pro_name
    FROM product_payment pp
    LEFT JOIN setup s ON pp.setup_id = s.setup_id
    LEFT JOIN `user` u ON pp.user_id = u.user_id
    LEFT JOIN product p ON s.pro_id = p.pro_id
    ORDER BY pp.paymentpro_date DESC, pp.paymentpro_id DESC
");

layout_header('บันทึกการจ่ายสินค้า', 'payment');
page_head('บันทึกการจ่ายสินค้า', 'Process : บันทึกการจ่ายสินค้า');
?>

<?= flash_message() ?>

<div class="role-hero">
  <div>
    <div class="hero-tag">Product Payment</div>
    <h2>บันทึกการจ่ายสินค้า</h2>
    <p>
      เลือกงานติดตั้งที่สร้างไว้ แล้วบันทึกสถานะการจ่ายสินค้า
      เช่น รอการจ่ายสินค้า จ่ายสินค้าแล้ว หรือยกเลิกการจ่ายสินค้า
    </p>
  </div>

  <div class="hero-visual">
    <div class="big-icon">📦</div>
    <strong>Product Payment</strong>
    <span>จัดการสถานะการจ่ายสินค้า</span>
  </div>
</div>

<div class="panel user-add-panel">
  <div class="panel-title user-add-title">ฟอร์มบันทึกการจ่ายสินค้า</div>

  <form class="admin-form user-add-form" method="POST" action="<?= h(app_system_url('finance/payment.php')) ?>">
    <div class="full">
      <label for="setup_id">เลือกงานติดตั้ง</label>
      <select id="setup_id" name="setup_id" required onchange="showSetupInfo()">
        <option value="" selected disabled>กรุณาเลือกงานติดตั้ง</option>

        <?php while ($setup = $setups->fetch_assoc()): ?>
          <option
            value="<?= h($setup['setup_id']) ?>"
            data-customer="<?= h($setup['user_name'] ?? '-') ?>"
            data-phone="<?= h($setup['user_phone'] ?? '-') ?>"
            data-product="<?= h($setup['pro_name'] ?? '-') ?>"
            data-payment-status="<?= h((string) ($setup['paymentpro_status'] ?? '')) ?>"
          >
            <?= h($setup['setup_id']) ?>
            |
            <?= h($setup['user_name'] ?? '-') ?>
            |
            <?= h($setup['pro_name'] ?? '-') ?>
            |
            <?= $setup['paymentpro_id'] ? 'เคยบันทึกแล้ว' : 'ยังไม่บันทึก' ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>

    <div>
      <label>ลูกค้า</label>
      <input type="text" id="customer_show" value="-" readonly>
    </div>

    <div>
      <label>เบอร์โทรศัพท์</label>
      <input type="text" id="phone_show" value="-" readonly>
    </div>

    <div class="full">
      <label>สินค้า</label>
      <input type="text" id="product_show" value="-" readonly>
    </div>

    <div>
      <label for="paymentpro_date">วันที่จ่ายสินค้า</label>
      <input
        type="datetime-local"
        id="paymentpro_date"
        name="paymentpro_date"
        value="<?= h($default_payment_date) ?>"
        required
      >
    </div>

    <div>
      <label for="paymentpro_status">สถานะการจ่ายสินค้า</label>
      <select id="paymentpro_status" name="paymentpro_status" required>
        <option value="0">0: รอการจ่ายสินค้า</option>
        <option value="1">1: จ่ายสินค้าแล้ว</option>
        <option value="2">2: ยกเลิกการจ่ายสินค้า</option>
      </select>
    </div>

    <div class="form-actions">
      <a class="btn-secondary" href="<?= h(app_system_url('finance/index.php')) ?>">
        กลับหน้าหลัก
      </a>

      <button class="btn" type="submit">
        บันทึกข้อมูล
      </button>
    </div>
  </form>
</div>

<div class="panel">
  <div class="panel-title">ประวัติการจ่ายสินค้า</div>

  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>รหัสการจ่ายสินค้า</th>
          <th>วันที่จ่ายสินค้า</th>
          <th>รหัสงานติดตั้ง</th>
          <th>ลูกค้า</th>
          <th>เบอร์โทร</th>
          <th>สินค้า</th>
          <th>สถานะ</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($payments->num_rows === 0): ?>
          <tr>
            <td colspan="7" class="empty-state">ยังไม่มีประวัติการจ่ายสินค้า</td>
          </tr>
        <?php endif; ?>

        <?php while ($payment = $payments->fetch_assoc()): ?>
          <tr>
            <td><?= h($payment['paymentpro_id']) ?></td>

            <td>
              <?= !empty($payment['paymentpro_date'])
                ? h(date('d/m/Y H:i', strtotime($payment['paymentpro_date'])))
                : '-'
              ?>
            </td>

            <td><?= h($payment['setup_id'] ?? '-') ?></td>
            <td><?= h($payment['user_name'] ?? '-') ?></td>
            <td><?= h($payment['user_phone'] ?? '-') ?></td>
            <td><?= h($payment['pro_name'] ?? '-') ?></td>

            <td>
              <span class="badge <?= h(payment_status_badge($payment['paymentpro_status'])) ?>">
                <?= h(payment_status_name($payment['paymentpro_status'])) ?>
              </span>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function showSetupInfo() {
  const setupSelect = document.getElementById('setup_id');
  const selected = setupSelect.options[setupSelect.selectedIndex];

  if (!selected) {
    return;
  }

  document.getElementById('customer_show').value = selected.dataset.customer || '-';
  document.getElementById('phone_show').value = selected.dataset.phone || '-';
  document.getElementById('product_show').value = selected.dataset.product || '-';

  const oldStatus = selected.dataset.paymentStatus;

  if (oldStatus !== '') {
    document.getElementById('paymentpro_status').value = oldStatus;
  }
}
</script>

<?php
layout_footer();