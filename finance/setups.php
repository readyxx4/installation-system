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

    return $stmt->get_result()->num_rows > 0;
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

    return $stmt->get_result()->num_rows > 0;
}

function prepare_setup_tables(mysqli $conn): void
{
    if (!table_exists($conn, 'install_detail')) {
        $conn->query("
            CREATE TABLE install_detail (
                detail_id INT AUTO_INCREMENT PRIMARY KEY,
                setup_id CHAR(11) NOT NULL,
                pro_id CHAR(10) NOT NULL,
                install_qty INT NOT NULL DEFAULT 1,
                install_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                install_total DECIMAL(10,2) NOT NULL DEFAULT 0.00
            )
        ");
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
    ];

    return $icons[$name] ?? '';
}

function setup_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'สร้างใบงานแล้ว',
        '1' => 'มอบหมายแล้ว',
        '2' => 'ช่างรับงาน',
        '3' => 'กำลังติดตั้ง',
        '4' => 'เสร็จสิ้น',
        default => 'ไม่ทราบสถานะ',
    };
}

function setup_status_class($status): string
{
    return match ((string) $status) {
        '0' => 'status-created',
        '1' => 'status-assigned',
        '2' => 'status-accepted',
        '3' => 'status-installing',
        '4' => 'status-done',
        default => 'status-created',
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
    $setup_result = $conn->query("
        SELECT
            s.setup_id,
            s.user_id,
            s.pro_id,
            s.setup_status,
            s.created_at,

            u.user_name,

            p.pro_name,

            COALESCE(detail_summary.item_count, 0) AS item_count,
            COALESCE(detail_summary.setup_total, 0) AS setup_total,

            a.assign_id,
            a.assign_status,
            a.tech_id,

            t.tech_name,
            t.tech_phone,
            t.tech_email,
            t.tech_status
        FROM setup s
        LEFT JOIN `user` u
            ON s.user_id = u.user_id
        LEFT JOIN product p
            ON s.pro_id = p.pro_id
        LEFT JOIN (
            SELECT
                setup_id,
                COUNT(detail_id) AS item_count,
                SUM(install_total) AS setup_total
            FROM install_detail
            GROUP BY setup_id
        ) detail_summary
            ON s.setup_id = detail_summary.setup_id
        LEFT JOIN assignment a
            ON a.setup_id = s.setup_id
           AND a.assign_id = (
                SELECT aa.assign_id
                FROM assignment aa
                WHERE aa.setup_id = s.setup_id
                  AND aa.assign_status IN (1, 2, 5)
                ORDER BY aa.assign_date DESC, aa.assign_id DESC
                LIMIT 1
           )
        LEFT JOIN technicians t
            ON TRIM(a.tech_id) = TRIM(t.tech_id)
        ORDER BY s.created_at DESC, s.setup_id DESC
        LIMIT 300
    ");

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
        </div>

        <a class="cs-status-link" href="<?= h(app_system_url('finance/create_setup.php')) ?>">
            <?= icon_svg('plus') ?>
            สร้างใบงานติดตั้ง
        </a>
    </div>

    <div class="cs-status-tabs" aria-label="เลือกสถานะใบงาน">
        <button type="button" class="cs-status-tab active status-all" data-filter="all">
            ทั้งหมด <b><?= h((string) $status_counts['all']) ?></b>
        </button>

        <button type="button" class="cs-status-tab status-created" data-filter="created">
            สร้างใบงานแล้ว <b><?= h((string) $status_counts['created']) ?></b>
        </button>

        <button type="button" class="cs-status-tab status-assigned" data-filter="assigned">
            มอบหมายแล้ว <b><?= h((string) $status_counts['assigned']) ?></b>
        </button>

        <button type="button" class="cs-status-tab status-accepted" data-filter="accepted">
            ช่างรับงาน <b><?= h((string) $status_counts['accepted']) ?></b>
        </button>

        <button type="button" class="cs-status-tab status-installing" data-filter="installing">
            กำลังติดตั้ง <b><?= h((string) $status_counts['installing']) ?></b>
        </button>

        <button type="button" class="cs-status-tab status-done" data-filter="done">
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
                        $customer_name = trim((string) ($row['user_name'] ?? ''));
                        $tech_name = trim((string) ($row['tech_name'] ?? ''));
                    ?>

                    <tr
                        data-status-group="<?= h($filter_group) ?>"
                        data-status-value="<?= h($status_value) ?>"
                    >
                        <td><strong><?= h($row['setup_id']) ?></strong></td>

                        <td>
                            <strong><?= h($customer_name !== '' ? $customer_name : '-') ?></strong>
                        </td>

                        <td><?= h($row['pro_name'] ?? '-') ?></td>

                        <td>
                            <?= h((string) (int) ($row['item_count'] ?? 0)) ?> รายการ
                        </td>

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
                            <span class="setup-status-badge <?= h(setup_status_class($status_value)) ?>">
                                <?= h(setup_status_name($status_value)) ?>
                            </span>
                        </td>

                        <td>
                            <a
                                class="cs-table-action"
                                href="<?= h(app_system_url('finance/setup_slip.php?id=' . urlencode($row['setup_id']))) ?>"
                            >
                                <?= icon_svg('file') ?>
                                ใบติดตั้ง
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