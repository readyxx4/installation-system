<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('2');

function table_exists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare("\n        SELECT TABLE_NAME\n        FROM INFORMATION_SCHEMA.TABLES\n        WHERE TABLE_SCHEMA = DATABASE()\n          AND TABLE_NAME = ?\n        LIMIT 1\n    ");
    $stmt->bind_param('s', $table);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function column_exists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("\n        SELECT COLUMN_NAME\n        FROM INFORMATION_SCHEMA.COLUMNS\n        WHERE TABLE_SCHEMA = DATABASE()\n          AND TABLE_NAME = ?\n          AND COLUMN_NAME = ?\n        LIMIT 1\n    ");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();

    return $stmt->get_result()->num_rows > 0;
}

function prepare_setup_tables(mysqli $conn): void
{
    if (!table_exists($conn, 'install_detail')) {
        $conn->query("\n            CREATE TABLE install_detail (\n                detail_id INT AUTO_INCREMENT PRIMARY KEY,\n                setup_id CHAR(11) NOT NULL,\n                pro_id CHAR(10) NOT NULL,\n                install_qty INT NOT NULL DEFAULT 1,\n                install_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,\n                install_total DECIMAL(10,2) NOT NULL DEFAULT 0.00\n            )\n        ");
    }

    if (!column_exists($conn, 'install_detail', 'install_qty')) {
        $conn->query("ALTER TABLE install_detail ADD COLUMN install_qty INT NOT NULL DEFAULT 1");
    }

    if (!column_exists($conn, 'install_detail', 'install_price')) {
        $conn->query("ALTER TABLE install_detail ADD COLUMN install_price DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    }

    if (!column_exists($conn, 'install_detail', 'install_total')) {
        $conn->query("ALTER TABLE install_detail ADD COLUMN install_total DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    }

    if (table_exists($conn, 'setup')) {
        if (!column_exists($conn, 'setup', 'setup_address')) {
            $conn->query("ALTER TABLE setup ADD COLUMN setup_address TEXT NULL");
        }

        if (!column_exists($conn, 'setup', 'setup_note')) {
            $conn->query("ALTER TABLE setup ADD COLUMN setup_note TEXT NULL");
        }

        if (!column_exists($conn, 'setup', 'created_at')) {
            $conn->query("ALTER TABLE setup ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
    }
}

function icon_svg(string $name): string
{
    $icons = [
        'file' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h5"></path></svg>',
        'plus' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>',
        'user' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>',
    ];

    return $icons[$name] ?? '';
}

function setup_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'สร้างใบงานแล้ว',
        '1' => 'มอบหมายงานแล้ว',
        '2' => 'ช่างรับงานแล้ว',
        '3' => 'กำลังติดตั้ง',
        '4' => 'ติดตั้งเสร็จสิ้น',
        default => 'ไม่ทราบสถานะ',
    };
}

function setup_status_badge($status): string
{
    return match ((string) $status) {
        '0' => 'orange',
        '1' => 'blue',
        '2' => 'green',
        '3' => 'purple',
        '4' => 'green',
        default => 'orange',
    };
}

function setup_status_group($status): string
{
    return match ((string) $status) {
        '0' => 'created',
        '1' => 'assigned',
        '2' => 'accepted',
        '3' => 'installing',
        '4' => 'done',
        default => 'created',
    };
}

function technician_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'พร้อมรับงาน',
        '1' => 'ไม่พร้อมรับงาน',
        default => 'ไม่ทราบสถานะ',
    };
}

prepare_setup_tables($conn);

$setup_rows = [];
$status_counts = [
    'all' => 0,
    'created' => 0,
    'assigned' => 0,
    'accepted' => 0,
    'installing' => 0,
    'done' => 0,
];

try {
    $setup_result = $conn->query("\n        SELECT\n            s.setup_id,\n            s.user_id,\n            s.pro_id,\n            s.setup_status,\n            s.created_at,\n\n            u.user_name,\n            u.user_fullname,\n\n            p.pro_name,\n\n            COALESCE(detail_summary.item_count, 0) AS item_count,\n            COALESCE(detail_summary.setup_total, 0) AS setup_total,\n\n            a.assign_id,\n            a.assign_status,\n            a.tech_id,\n\n            t.tech_name,\n            t.tech_fullname,\n            t.tech_phone,\n            t.tech_email,\n            t.tech_status\n        FROM setup s\n        LEFT JOIN `user` u\n            ON s.user_id = u.user_id\n        LEFT JOIN product p\n            ON s.pro_id = p.pro_id\n        LEFT JOIN (\n            SELECT\n                setup_id,\n                COUNT(detail_id) AS item_count,\n                SUM(install_total) AS setup_total\n            FROM install_detail\n            GROUP BY setup_id\n        ) detail_summary\n            ON s.setup_id = detail_summary.setup_id\n        LEFT JOIN assignment a\n            ON a.setup_id = s.setup_id\n           AND a.assign_id = (\n                SELECT aa.assign_id\n                FROM assignment aa\n                WHERE aa.setup_id = s.setup_id\n                  AND aa.assign_status IN (1, 2, 5)\n                ORDER BY aa.assign_date DESC, aa.assign_id DESC\n                LIMIT 1\n           )\n        LEFT JOIN technicians t\n            ON TRIM(a.tech_id) = TRIM(t.tech_id)\n        ORDER BY s.created_at DESC, s.setup_id DESC\n        LIMIT 300\n    ");

    while ($row = $setup_result->fetch_assoc()) {
        $setup_rows[] = $row;
        $status_counts['all']++;

        $group = setup_status_group($row['setup_status'] ?? '0');
        if (isset($status_counts[$group])) {
            $status_counts[$group]++;
        }
    }
} catch (Throwable $e) {
    $setup_rows = [];
}

layout_header('รายการงานติดตั้ง', 'setups');
?>

<?= flash_message() ?>

<section class="cs-job-status-panel cs-setups-page-panel">
    <div class="cs-job-status-head">
        <div>
            <h2>รายการใบงานติดตั้ง</h2>
            <!-- <p>แยกใบงานที่สร้างไว้แล้ว ใบงานที่มอบหมาย และใบงานที่ช่างรับงานแล้ว</p> -->
        </div>

        <a class="cs-status-link" href="<?= h(app_system_url('finance/create_setup.php')) ?>">
            <?= icon_svg('plus') ?>
            สร้างใบงานติดตั้ง
        </a>
    </div>

    <div class="cs-status-tabs" aria-label="เลือกสถานะใบงาน">
        <button type="button" class="cs-status-tab active" data-filter="all">
            ทั้งหมด <b><?= h((string) $status_counts['all']) ?></b>
        </button>
        <button type="button" class="cs-status-tab" data-filter="created">
            สร้างใบงานแล้ว <b><?= h((string) $status_counts['created']) ?></b>
        </button>
        <button type="button" class="cs-status-tab" data-filter="assigned">
            มอบหมายแล้ว <b><?= h((string) $status_counts['assigned']) ?></b>
        </button>
        <button type="button" class="cs-status-tab" data-filter="accepted">
            ช่างรับงาน <b><?= h((string) $status_counts['accepted']) ?></b>
        </button>
        <button type="button" class="cs-status-tab" data-filter="installing">
            กำลังติดตั้ง <b><?= h((string) $status_counts['installing']) ?></b>
        </button>
        <button type="button" class="cs-status-tab" data-filter="done">
            เสร็จสิ้น <b><?= h((string) $status_counts['done']) ?></b>
        </button>
    </div>

    <div class="cs-table-wrap cs-job-status-table-wrap cs-setups-table-wrap">
        <table class="cs-data-table cs-job-status-table cs-setups-table-clean">
            <thead>
                <tr>
                    <th>รหัสใบงาน</th>
                    <th>ลูกค้า</th>
                    <th>สินค้าแรก</th>
                    <th>จำนวนรายการ</th>
                    <th>รวมค่าติดตั้ง</th>
                    <th>ช่าง</th>
                    <th>สถานะ</th>
                    <th>ใบติดตั้ง</th>
                </tr>
            </thead>

            <tbody id="setupStatusRows">
                <?php if (count($setup_rows) === 0): ?>
                    <tr>
                        <td colspan="8" class="cs-empty-cell">ยังไม่มีใบงานติดตั้ง</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($setup_rows as $row): ?>
                    <?php
                        $status_value = (string) ($row['setup_status'] ?? '0');
                        $filter_group = setup_status_group($status_value);

                        $customer_name = trim((string) ($row['user_fullname'] ?? ''));
                        if ($customer_name === '') {
                            $customer_name = trim((string) ($row['user_name'] ?? ''));
                        }

                        $tech_name = trim((string) ($row['tech_fullname'] ?? ''));
                        if ($tech_name === '') {
                            $tech_name = trim((string) ($row['tech_name'] ?? ''));
                        }
                    ?>
                    <tr data-status-group="<?= h($filter_group) ?>" data-status-value="<?= h($status_value) ?>">
                        <td>
                            <strong><?= h($row['setup_id']) ?></strong>
                        </td>

                        <td>
                            <strong><?= h($customer_name !== '' ? $customer_name : '-') ?></strong>
                        </td>

                        <td><?= h($row['pro_name'] ?? '-') ?></td>

                        <td><?= h((string) (int) ($row['item_count'] ?? 0)) ?> รายการ</td>

                        <td>
                            <b><?= h(number_format((float) ($row['setup_total'] ?? 0), 2)) ?> บาท</b>
                        </td>

                        <td>
                            <?php if (!empty($row['tech_id']) && $tech_name !== ''): ?>
                                <strong class="cs-tech-name-text"><?= h($tech_name) ?></strong>
                            <?php else: ?>
                                <span class="cs-muted-text">-</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="badge <?= h(setup_status_badge($status_value)) ?>">
                                <?= h(setup_status_name($status_value)) ?>
                            </span>
                        </td>

                        <td>
                            <a class="cs-table-action" href="<?= h(app_system_url('finance/setup_slip.php?id=' . urlencode($row['setup_id']))) ?>">
                                <?= icon_svg('file') ?>
                                เปิดใบ
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
document.querySelectorAll('.cs-status-tab').forEach(function (button) {
    button.addEventListener('click', function () {
        const filter = this.dataset.filter || 'all';

        document.querySelectorAll('.cs-status-tab').forEach(function (tab) {
            tab.classList.remove('active');
        });
        this.classList.add('active');

        document.querySelectorAll('#setupStatusRows tr[data-status-group]').forEach(function (row) {
            const group = row.dataset.statusGroup || 'all';
            row.style.display = (filter === 'all' || group === filter) ? '' : 'none';
        });
    });
});
</script>

<?php
layout_footer();