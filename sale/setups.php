<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('2');

$currentSaleId = trim((string) ($_SESSION['user_id'] ?? ''));

if ($currentSaleId === '') {
    redirect_to(app_system_url('login.php'));
}

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
        'search' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>',
        'reset' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"></path><path d="M3 4v6h6"></path></svg>',
        'edit' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path></svg>',
        'trash' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 14H6L5 6"></path></svg>',
    ];

    return $icons[$name] ?? '';
}

function setup_status_name($status): string
{
    return match ((string) $status) {
        '0' => 'สร้างใบงานแล้ว',
        '1' => 'มอบหมายงานแล้ว',
        '2' => 'รับงาน',
        '3' => 'กำลังติดตั้ง',
        '4' => 'เสร็จสิ้น',
        '5' => 'ยกเลิกแล้ว',
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
        '5' => 'status-canceled',
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
        '5' => 'canceled',
        default => 'created',
    };
}

prepare_setup_tables($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_setup') {
    $cancelSetupId = trim($_POST['setup_id'] ?? '');

    if ($cancelSetupId === '') {
        redirect_to(app_system_url('sale/setups.php?status=error'));
    }

    try {
        $conn->begin_transaction();

        $lockStmt = $conn->prepare("
            SELECT
                s.setup_id,
                s.setup_status,
                a.assign_id
            FROM setup s
            LEFT JOIN assignment a
              ON a.setup_id = s.setup_id
             AND a.assign_id = (
                SELECT a2.assign_id
                FROM assignment a2
                WHERE a2.setup_id = s.setup_id
                ORDER BY a2.assign_date DESC, a2.assign_id DESC
                LIMIT 1
             )
            WHERE s.setup_id = ?
              AND s.sale_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $lockStmt->bind_param('ss', $cancelSetupId, $currentSaleId);
        $lockStmt->execute();
        $cancelSetup = $lockStmt->get_result()->fetch_assoc();

        if (!$cancelSetup || (string) ($cancelSetup['setup_status'] ?? '') !== '0' || !empty($cancelSetup['assign_id'])) {
            $conn->rollback();
            redirect_to(app_system_url('sale/setups.php?status=assignment_locked'));
        }

        $cancelStmt = $conn->prepare("
            UPDATE setup
            SET setup_status = 5
            WHERE setup_id = ?
              AND sale_id = ?
              AND setup_status = 0
        ");
        $cancelStmt->bind_param('ss', $cancelSetupId, $currentSaleId);
        $cancelStmt->execute();

        $conn->commit();
        redirect_to(app_system_url('sale/setup_history.php?status=cancelled'));
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
        }

        redirect_to(app_system_url('sale/setups.php?status=error'));
    }
}
$pending_setup_rows = [];

try {
    $setup_stmt = $conn->prepare("
        SELECT
            s.setup_id,
            s.customer_id AS user_id,
            s.pro_id,
            s.setup_status,
            s.created_at,

            c.customer_name AS user_name,

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
        LEFT JOIN customers c
            ON s.customer_id = c.customer_id
        LEFT JOIN product p
            ON s.pro_id = p.pro_id
        LEFT JOIN (
            SELECT
                d.setup_id,
                COUNT(d.detail_id) AS item_count,
                SUM(COALESCE(d.install_qty, 1) * COALESCE(p2.pro_price_install, 0)) AS setup_total
            FROM install_detail d
            LEFT JOIN product p2 ON d.pro_id = p2.pro_id
            GROUP BY d.setup_id
        ) detail_summary
            ON s.setup_id = detail_summary.setup_id
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
        WHERE s.sale_id = ?
        ORDER BY s.created_at DESC, s.setup_id DESC
        LIMIT 300
    ");

    $setup_stmt->bind_param('s', $currentSaleId);
    $setup_stmt->execute();
    $setup_result = $setup_stmt->get_result();

    while ($row = $setup_result->fetch_assoc()) {
        $is_pending_sale_work = (string) ($row['setup_status'] ?? '0') === '0'
            && empty($row['assign_id']);

        if ($is_pending_sale_work) {
            $pending_setup_rows[] = $row;
        }
    }
} catch (Throwable $e) {
    $pending_setup_rows = [];
}

function render_sale_setup_rows(array $rows, string $empty_message): void
{
    if (count($rows) === 0): ?>
        <tr>
            <td colspan="9" class="cs-empty-cell"><?= h($empty_message) ?></td>
        </tr>
        <?php return;
    endif;

    foreach ($rows as $row):
        $status_value = (string) ($row['setup_status'] ?? '0');
        $filter_group = setup_status_group($status_value);
        $customer_name = trim((string) ($row['user_name'] ?? ''));
        $tech_name = trim((string) ($row['tech_name'] ?? ''));
        ?>

        <tr data-status-group="<?= h($filter_group) ?>" data-status-value="<?= h($status_value) ?>" data-setup-search-text="<?= h(strtolower(
                $row['setup_id'] . ' ' .
                $customer_name
            )) ?>">
            <!-- รหัสใบงาน -->
            <td class="setup-col-id">
                <strong><?= h($row['setup_id']) ?></strong>
            </td>

            <!-- เวลาที่สร้างใบติดตั้ง -->
            <td class="setup-col-created">
                <?php
                $createdAt = trim((string) ($row['created_at'] ?? ''));

                if ($createdAt !== '') {
                    $createdTimestamp = strtotime($createdAt);

                    echo h(
                        $createdTimestamp
                        ? date('d/m/Y H:i', $createdTimestamp)
                        : $createdAt
                    );
                } else {
                    echo '-';
                }
                ?>
            </td>

            <!-- ลูกค้า -->
            <td class="setup-col-customer">
                <strong>
                    <?= h($customer_name !== '' ? $customer_name : '-') ?>
                </strong>
            </td>

            <!-- จำนวนสินค้า -->
            <td class="setup-col-items">
                <span class="setup-item-count">
                    <?= h((string) (int) ($row['item_count'] ?? 0)) ?>
                    รายการ
                </span>
            </td>

            <!-- รวมค่าติดตั้ง -->
            <td class="setup-col-total">
                <strong>
                    <?= h(number_format((float) ($row['setup_total'] ?? 0), 2)) ?>
                    บาท
                </strong>
            </td>

            <!-- จัดการ -->
            <td class="setup-col-actions">
                <div class="cs-row-actions">

                    <a href="<?= h(
                        app_system_url(
                            'sale/setup_slip.php?id=' .
                            urlencode((string) $row['setup_id'])
                        )
                    ) ?>" class="btn btn-small">
                        <?= icon_svg('file') ?>
                        ดู
                    </a>

                    <a href="<?= h(
                        app_system_url(
                            'sale/edit_setup.php?id=' .
                            urlencode((string) $row['setup_id'])
                        )
                    ) ?>" class="btn btn-small btn-edit">
                        <?= icon_svg('edit') ?>
                        แก้ไข
                    </a>

                    <button
                        type="button"
                        class="btn btn-small btn-delete"
                        data-open-cancel-modal
                        data-setup-id="<?= h($row['setup_id']) ?>"
                    >
                        <?= icon_svg('trash') ?>
                        ยกเลิก
                    </button>

                </div>
            </td>
        </tr>
    <?php endforeach;
}

layout_header('รายการงานติดตั้ง', 'setups');
?>

<link rel="stylesheet"
    href="<?= h(app_asset_url('sale/assets/css/setups.css')) ?>?v=<?= h(asset_version('sale/assets/css/setups.css')) ?>">

<?= flash_message() ?>

<section class="cs-job-status-panel cs-setups-page-panel">
    <div class="cs-job-status-head">
        <div>
            <h2>รายการใบงานติดตั้ง</h2>
            <p class="cs-page-subtitle">ใบงานที่รอดำเนินการ</p>
        </div>
    </div>

    <form class="toolbar setup-toolbar cs-list-toolbar cs-sale-admin-toolbar" action="javascript:void(0)">
        <input id="setupSearchInput" type="search" data-setup-search placeholder="ค้นหารหัสใบงาน ลูกค้า สินค้า หรือช่าง"
            autocomplete="off">

        <button class="btn btn-search" type="submit" data-setup-search-submit>
            <?= icon_svg('search') ?>
            <span>ค้นหา</span>
        </button>

        <button class="btn btn-reset" type="button" data-setup-search-reset>
            <?= icon_svg('reset') ?>
            <span>ล้างค้นหา</span>
        </button>

        <a class="btn btn-add cs-setup-add-link" href="<?= h(app_system_url('sale/create_setup.php')) ?>">
            <?= icon_svg('plus') ?>
            <span>สร้างใบงานติดตั้ง</span>
        </a>
    </form>
    <div class="cs-table-wrap cs-job-status-table-wrap cs-setups-table-wrap cs-pending-table-wrap">
        <table class="cs-data-table cs-job-status-table cs-setups-table-clean">
            <thead>
                <tr>
                    <th>รหัสใบงาน</th>
                    <th>วันที่-เวลา</th>
                    <th>ลูกค้า</th>
                    <th>จำนวนสินค้า</th>
                    <th>ค่าติดตั้ง</th>
                    <th>จัดการ</th>
                </tr>
            </thead>

            <tbody id="setupStatusRows">
                <?php render_sale_setup_rows($pending_setup_rows, 'ยังไม่มีใบงานที่รอดำเนินการ'); ?>
                <?php if (count($pending_setup_rows) > 0): ?>
                    <tr data-setup-search-empty style="display: none;">
                        <td colspan="6" class="cs-empty-cell">ไม่พบใบงานที่ตรงกับคำค้นหา</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</section>


<div class="cs-cancel-modal" id="cancelSetupModal" hidden>
    <div class="cs-cancel-modal-backdrop" data-close-cancel-modal></div>

    <div
        class="cs-cancel-modal-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="cancelSetupModalTitle"
    >
        <div class="cs-cancel-modal-icon" aria-hidden="true">
            <?= icon_svg('trash') ?>
        </div>

        <div class="cs-cancel-modal-content">
            <h3 id="cancelSetupModalTitle">ยืนยันการยกเลิกใบงาน</h3>

            <p>
                คุณต้องการยกเลิกใบงาน
                <strong id="cancelSetupCode">-</strong>
                ใช่หรือไม่?
            </p>

            <div class="cs-cancel-modal-note">
                ใบงานที่ยกเลิกจะถูกเก็บไว้ในประวัติ และไม่สามารถดำเนินการต่อจากหน้ารายการได้
            </div>
        </div>

        <form method="POST" class="cs-cancel-modal-actions" id="cancelSetupForm">
            <input type="hidden" name="action" value="cancel_setup">
            <input type="hidden" name="setup_id" id="cancelSetupId" value="">

            <button type="button" class="btn cs-cancel-modal-close" data-close-cancel-modal>
                ปิด
            </button>

            <button type="submit" class="btn cs-cancel-modal-confirm">
                <?= icon_svg('trash') ?>
                ยืนยันยกเลิก
            </button>
        </form>
    </div>
</div>

<script>
(() => {
    'use strict';

    const modal = document.getElementById('cancelSetupModal');
    const setupIdInput = document.getElementById('cancelSetupId');
    const setupCode = document.getElementById('cancelSetupCode');

    if (!modal || !setupIdInput || !setupCode) return;

    const openModal = (setupId) => {
        setupIdInput.value = setupId;
        setupCode.textContent = setupId || '-';
        modal.hidden = false;
        document.body.classList.add('cs-modal-open');
    };

    const closeModal = () => {
        modal.hidden = true;
        setupIdInput.value = '';
        setupCode.textContent = '-';
        document.body.classList.remove('cs-modal-open');
    };

    document.querySelectorAll('[data-open-cancel-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            openModal(button.dataset.setupId || '');
        });
    });

    modal.querySelectorAll('[data-close-cancel-modal]').forEach((element) => {
        element.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
})();
</script>


<script
    src="<?= h(app_asset_url('sale/assets/js/setups.js')) ?>?v=<?= h(asset_version('sale/assets/js/setups.js')) ?>"></script>

<?php
layout_footer();
