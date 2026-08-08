<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('3');

function count_table(mysqli $conn, string $table): int
{
    try {
        $result = $conn->query("SELECT COUNT(*) AS total FROM `$table`");
        $row = $result->fetch_assoc();
        return (int) ($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function count_users_by_role(mysqli $conn, array $roles): int
{
    if (empty($roles)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $types = str_repeat('i', count($roles));

    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS total
            FROM `user`
            WHERE user_role IN ($placeholders)
        ");

        $stmt->bind_param($types, ...$roles);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return (int) ($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function safe_result(mysqli $conn, string $sql)
{
    try {
        return $conn->query($sql);
    } catch (Throwable $e) {
        return false;
    }
}

function tech_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'พร้อมรับงาน',
        '1' => 'ไม่พร้อมรับงาน',
        default => 'ไม่ทราบสถานะ',
    };
}

function tech_status_badge($status): string
{
    return match ((string) $status) {
        '0' => 'green',
        '1' => 'red',
        default => 'orange',
    };
}

$total_system_users = count_users_by_role($conn, [1, 2, 3]);
$total_customers = count_users_by_role($conn, [0]);
$total_technicians = count_table($conn, 'technicians');
$total_product_types = count_table($conn, 'product_type');
$total_products = count_table($conn, 'product');

$recent_technicians = safe_result($conn, "
    SELECT
        tech_id,
        tech_name,
        tech_phone,
        tech_status
    FROM technicians
    ORDER BY tech_id DESC
    LIMIT 5
");

$recent_users = safe_result($conn, "
    SELECT
        user_id,
        user_name,
        user_phone,
        user_email,
        user_role
    FROM `user`
    WHERE user_role IN (1, 2, 3)
    ORDER BY user_id DESC
    LIMIT 5
");

$recent_products = safe_result($conn, "
    SELECT
        p.pro_id,
        p.pro_name,
        p.pro_price,
        p.pro_price_install,
        pt.protype_name
    FROM product p
    LEFT JOIN product_type pt
        ON p.protype_id = pt.protype_id
    ORDER BY p.pro_id DESC
    LIMIT 5
");

$recent_customers = safe_result($conn, "
    SELECT
        user_id,
        user_name,
        user_phone,
        user_email
    FROM `user`
    WHERE user_role = 0
    ORDER BY user_id DESC
    LIMIT 5
");

layout_header('หน้าหลักผู้ดูแลระบบ', 'dashboard');
?>

<link
    rel="stylesheet"
    href="<?= h(app_asset_url('admin/assets/css/dashboard.css')) ?>?v=<?= h(asset_version('admin/assets/css/dashboard.css')) ?>"
>

<div class="admin-dashboard-v2">

    <div class="admin-dashboard-top">
        <div>
            <h1>ภาพรวมผู้ดูแลระบบ</h1>
            <p>สรุปข้อมูลสำคัญ สถานะช่าง และรายการล่าสุดที่ควรตรวจสอบ</p>
        </div>

        <div class="admin-dashboard-actions">
            <div class="admin-date-pill">
                <i class="fa-regular fa-calendar"></i>
                <?= h(date('d/m/Y')) ?>
            </div>
        </div>
    </div>

    <div class="admin-summary-grid">

        <a class="admin-summary-card" href="<?= h(app_system_url('admin/users.php')) ?>">
            <div>
                <span>ผู้ใช้ระบบ</span>
                <strong><?= h((string) $total_system_users) ?></strong>
                <small>หัวหน้าช่าง / พนักงานขาย / ผู้ดูแลระบบ</small>
            </div>

            <div class="summary-icon">
                <i class="fa-solid fa-users"></i>
            </div>
        </a>

        <a class="admin-summary-card" href="<?= h(app_system_url('admin/customers.php')) ?>">
            <div>
                <span>ลูกค้า</span>
                <strong><?= h((string) $total_customers) ?></strong>
                <small>ข้อมูลลูกค้าที่ใช้บริการ</small>
            </div>

            <div class="summary-icon">
                <i class="fa-solid fa-user-group"></i>
            </div>
        </a>

        <a class="admin-summary-card" href="<?= h(app_system_url('admin/technicians.php')) ?>">
            <div>
                <span>ช่างติดตั้ง</span>
                <strong><?= h((string) $total_technicians) ?></strong>
                <small>ทีมช่างในระบบ</small>
            </div>

            <div class="summary-icon">
                <i class="fa-solid fa-screwdriver-wrench"></i>
            </div>
        </a>

        <a class="admin-summary-card" href="<?= h(app_system_url('admin/product_types.php')) ?>">
            <div>
                <span>ประเภทสินค้า</span>
                <strong><?= h((string) $total_product_types) ?></strong>
                <small>หมวดหมู่สินค้า</small>
            </div>

            <div class="summary-icon">
                <i class="fa-solid fa-table-cells-large"></i>
            </div>
        </a>

        <a class="admin-summary-card" href="<?= h(app_system_url('admin/products.php')) ?>">
            <div>
                <span>สินค้า</span>
                <strong><?= h((string) $total_products) ?></strong>
                <small>รายการสินค้าและค่าติดตั้ง</small>
            </div>

            <div class="summary-icon">
                <i class="fa-solid fa-box"></i>
            </div>
        </a>

    </div>

    <div class="admin-dashboard-info-grid">

        <div class="admin-widget">
            <div class="admin-widget-head">
                <div>
                    <h2>สถานะช่าง</h2>
                    <p>ข้อมูลช่างล่าสุด</p>
                </div>

                <a class="admin-widget-link" href="<?= h(app_system_url('admin/technicians.php')) ?>">
                    ดูทั้งหมด
                </a>
            </div>

            <div class="admin-mini-list">

                <?php if (!$recent_technicians || $recent_technicians->num_rows === 0): ?>

                    <div class="admin-empty-mini">
                        ยังไม่มีข้อมูลช่าง
                    </div>

                <?php else: ?>

                    <?php while ($tech = $recent_technicians->fetch_assoc()): ?>

                        <div class="admin-mini-item">
                            <div class="admin-mini-main">
                                <strong><?= h($tech['tech_name']) ?></strong>
                                <span><?= h($tech['tech_phone']) ?></span>
                            </div>

                            <span class="badge <?= h(tech_status_badge($tech['tech_status'])) ?>">
                                <?= h(tech_status_name($tech['tech_status'])) ?>
                            </span>
                        </div>

                    <?php endwhile; ?>

                <?php endif; ?>

            </div>
        </div>

        <div class="admin-widget">
            <div class="admin-widget-head">
                <div>
                    <h2>ข้อมูลผู้ใช้ล่าสุด</h2>
                    <p>บัญชีผู้ใช้ระบบที่เพิ่มล่าสุด</p>
                </div>

                <a class="admin-widget-link" href="<?= h(app_system_url('admin/users.php')) ?>">
                    ดูทั้งหมด
                </a>
            </div>

            <div class="admin-mini-list">

                <?php if (!$recent_users || $recent_users->num_rows === 0): ?>

                    <div class="admin-empty-mini">
                        ยังไม่มีข้อมูลผู้ใช้
                    </div>

                <?php else: ?>

                    <?php while ($user = $recent_users->fetch_assoc()): ?>

                        <div class="admin-mini-item">
                            <div class="admin-mini-main">
                                <strong><?= h($user['user_name']) ?></strong>
                                <span><?= h($user['user_phone']) ?></span>
                            </div>

                            <span class="admin-user-role">
                                <?= h(role_name($user['user_role'])) ?>
                            </span>
                        </div>

                    <?php endwhile; ?>

                <?php endif; ?>

            </div>
        </div>

    </div>

    <div class="admin-bottom-grid">

        <div class="admin-widget">
            <div class="admin-widget-head">
                <div>
                    <h2>สินค้าล่าสุด</h2>
                    <p>รายการสินค้าที่อยู่ในระบบ</p>
                </div>

                <a class="admin-widget-link" href="<?= h(app_system_url('admin/products.php')) ?>">
                    ดูทั้งหมด
                </a>
            </div>

            <div class="admin-mini-list">

                <?php if (!$recent_products || $recent_products->num_rows === 0): ?>

                    <div class="admin-empty-mini">
                        ยังไม่มีสินค้า
                    </div>

                <?php else: ?>

                    <?php while ($product = $recent_products->fetch_assoc()): ?>

                        <div class="admin-mini-item">
                            <div class="admin-mini-main">
                                <strong><?= h($product['pro_name']) ?></strong>
                                <span><?= h($product['protype_name'] ?: '-') ?></span>
                            </div>

                            <b>
                                <?= h(number_format((float) $product['pro_price_install'], 2)) ?> บาท
                            </b>
                        </div>

                    <?php endwhile; ?>

                <?php endif; ?>

            </div>
        </div>

        <div class="admin-widget">
            <div class="admin-widget-head">
                <div>
                    <h2>ลูกค้าล่าสุด</h2>
                    <p>ข้อมูลลูกค้าที่เพิ่มล่าสุด</p>
                </div>

                <a class="admin-widget-link" href="<?= h(app_system_url('admin/customers.php')) ?>">
                    ดูทั้งหมด
                </a>
            </div>

            <div class="admin-mini-list">

                <?php if (!$recent_customers || $recent_customers->num_rows === 0): ?>

                    <div class="admin-empty-mini">
                        ยังไม่มีลูกค้า
                    </div>

                <?php else: ?>

                    <?php while ($customer = $recent_customers->fetch_assoc()): ?>

                        <div class="admin-mini-item">
                            <div class="admin-mini-main">
                                <strong><?= h($customer['user_name']) ?></strong>
                                <span><?= h($customer['user_phone']) ?></span>
                            </div>

                            <small>
                                <?= h($customer['user_id']) ?>
                            </small>
                        </div>

                    <?php endwhile; ?>

                <?php endif; ?>

            </div>
        </div>

    </div>

</div>

<?php layout_footer(); ?>