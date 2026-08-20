<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('2');

$currentSaleId = trim((string) ($_SESSION['user_id'] ?? ''));

if ($currentSaleId === '') {
    redirect_to(app_system_url('login.php'));
}

function history_icon_svg(string $name): string
{
    $icons = [
        'file' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h5"></path></svg>',
        'search' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>',
        'reset' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"></path><path d="M3 4v6h6"></path></svg>',
    ];

    return $icons[$name] ?? '';
}

$history_setup_rows = [];

try {
    $setup_stmt = $conn->prepare("
        SELECT
            s.setup_id,
            s.user_id,
            s.created_at,

            u.user_name,

            COALESCE(detail_summary.item_count, 0) AS item_count,
            COALESCE(detail_summary.setup_total, 0) AS setup_total,

            a.assign_id,
            a.assign_status
        FROM setup s
        LEFT JOIN `user` u
            ON s.user_id = u.user_id
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
                ORDER BY aa.assign_date DESC, aa.assign_id DESC
                LIMIT 1
           )
        WHERE s.sale_id = ?
          AND (
                s.setup_status = 5
                OR (
                    a.assign_id IS NOT NULL
                    AND COALESCE(a.assign_status, 0) <> 0
                )
          )
        ORDER BY s.created_at DESC, s.setup_id DESC
        LIMIT 300
    ");

    $setup_stmt->bind_param('s', $currentSaleId);
    $setup_stmt->execute();
    $setup_result = $setup_stmt->get_result();

    while ($row = $setup_result->fetch_assoc()) {
        $history_setup_rows[] = $row;
    }
} catch (Throwable $e) {
    $history_setup_rows = [];
}

layout_header('ประวัติใบงาน', 'setup_history');
?>

<link
  rel="stylesheet"
  href="<?= h(app_asset_url('sale/assets/css/setups.css')) ?>?v=<?= h(asset_version('sale/assets/css/setups.css')) ?>"
>

<?= flash_message() ?>

<section class="cs-job-status-panel cs-setups-page-panel">
    <div class="cs-job-status-head">
        <div>
            <h2>ประวัติใบงาน</h2>
            <p class="cs-page-subtitle">ใบงานที่หัวหน้าช่างมอบหมายแล้ว แสดงไว้สำหรับตรวจสอบย้อนหลัง</p>
        </div>
    </div>

    <form class="toolbar setup-toolbar cs-sale-admin-toolbar cs-history-search-toolbar" action="javascript:void(0)">
        <input
            id="historySearchInput"
            type="search"
            data-history-search
            placeholder="ค้นหารหัสใบงาน หรือลูกค้า"
            autocomplete="off"
        >

        <button class="btn btn-search" type="submit" data-history-search-submit>
            <?= history_icon_svg('search') ?>
            <span>ค้นหา</span>
        </button>

        <button class="btn btn-reset" type="button" data-history-search-reset>
            <?= history_icon_svg('reset') ?>
            <span>ล้างค้นหา</span>
        </button>
    </form>

    <div class="cs-table-wrap cs-job-status-table-wrap cs-setups-table-wrap cs-history-table-wrap">
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

            <tbody>
                <?php if (count($history_setup_rows) === 0): ?>
                    <tr>
                        <td colspan="6" class="cs-empty-cell">ยังไม่มีประวัติใบงาน</td>
                    </tr>
                <?php endif; ?>

                <?php if (count($history_setup_rows) > 0): ?>
                    <tr data-history-search-empty style="display: none;">
                        <td colspan="6" class="cs-empty-cell">ไม่พบใบงานที่ตรงกับคำค้นหา</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($history_setup_rows as $row): ?>
                    <?php
                        $customer_name = trim((string) ($row['user_name'] ?? ''));
                        $created_at = trim((string) ($row['created_at'] ?? ''));
                        $created_text = '-';

                        if ($created_at !== '') {
                            $created_timestamp = strtotime($created_at);
                            $created_text = $created_timestamp
                                ? date('d/m/Y H:i', $created_timestamp)
                                : $created_at;
                        }
                    ?>

                    <tr
                        data-history-search-text="<?= h(strtolower(
                            (string) $row['setup_id'] . ' ' .
                            $customer_name
                        )) ?>"
                    >
                        <td class="setup-col-id">
                            <strong><?= h($row['setup_id']) ?></strong>
                        </td>

                        <td class="setup-col-created">
                            <?= h($created_text) ?>
                        </td>

                        <td class="setup-col-customer">
                            <strong><?= h($customer_name !== '' ? $customer_name : '-') ?></strong>
                        </td>

                        <td class="setup-col-items">
                            <span class="setup-item-count">
                                <?= h((string) (int) ($row['item_count'] ?? 0)) ?> รายการ
                            </span>
                        </td>

                        <td class="setup-col-total">
                            <strong><?= h(number_format((float) ($row['setup_total'] ?? 0), 2)) ?> บาท</strong>
                        </td>

                        <td class="setup-col-actions">
                            <div class="cs-row-actions cs-history-row-actions">
                                <a
                                    class="btn btn-small cs-history-view-btn"
                                    href="<?= h(app_system_url('sale/setup_detail.php?id=' . urlencode((string) $row['setup_id']))) ?>"
                                >
                                    <?= history_icon_svg('file') ?>
                                    <span>ดูรายละเอียด</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script
  src="<?= h(app_asset_url('sale/assets/js/setups.js')) ?>?v=<?= h(asset_version('sale/assets/js/setups.js')) ?>"
  defer
></script>

<?php
layout_footer();