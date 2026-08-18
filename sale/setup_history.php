<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('2');

function history_icon_svg(string $name): string
{
    $icons = [
        'file' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h5"></path></svg>',
        'search' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.5-3.5"></path></svg>',
        'reset' => '<svg class="cs-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"></path><path d="M3 4v6h6"></path></svg>',
    ];

    return $icons[$name] ?? '';
}

function history_status_key($setup_status, $assign_status): string
{
    if ((string) $setup_status === '5' || (string) $assign_status === '4') {
        return 'canceled';
    }

    if ((string) $assign_status === '3') {
        return 'rejected';
    }

    if ((string) $setup_status === '4' || (string) $assign_status === '5') {
        return 'done';
    }

    if ((string) $setup_status === '3') {
        return 'installing';
    }

    if ((string) $setup_status === '2' || (string) $assign_status === '2') {
        return 'accepted';
    }

    return 'assigned';
}

function history_status_name(string $status_key): string
{
    return match ($status_key) {
        'assigned' => 'มอบหมายงานแล้ว',
        'accepted' => 'ช่างรับงานแล้ว',
        'installing' => 'กำลังติดตั้ง',
        'done' => 'เสร็จสิ้น',
        'canceled' => 'ยกเลิก',
        default => 'ไม่ทราบสถานะ',
    };
}

function history_status_class(string $status_key): string
{
    return match ($status_key) {
        'assigned' => 'status-assigned',
        'accepted' => 'status-accepted',
        'installing' => 'status-installing',
        'done' => 'status-done',
        'canceled' => 'status-canceled',
        default => 'status-created',
    };
}


$history_setup_rows = [];
$history_status_filters = [
    'all' => 'ทั้งหมด',
    'assigned' => 'มอบหมายแล้ว',
    'accepted' => 'ช่างรับงานแล้ว',
    'installing' => 'กำลังติดตั้ง',
    'done' => 'เสร็จสิ้น',
    'canceled' => 'ยกเลิก',
];
$history_status_counts = array_fill_keys(array_keys($history_status_filters), 0);

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
                ORDER BY aa.assign_date DESC, aa.assign_id DESC
                LIMIT 1
           )
        LEFT JOIN technicians t
            ON TRIM(a.tech_id) = TRIM(t.tech_id)
        WHERE s.setup_status <> 0
           OR a.assign_id IS NOT NULL
        ORDER BY s.created_at DESC, s.setup_id DESC
        LIMIT 300
    ");

    while ($row = $setup_result->fetch_assoc()) {
        $row['history_status_key'] = history_status_key($row['setup_status'] ?? '', $row['assign_status'] ?? '');
        $history_setup_rows[] = $row;
    }

    foreach ($history_setup_rows as $history_row) {
        $history_status_counts['all']++;
        $status_key = (string) ($history_row['history_status_key'] ?? 'assigned');

        if (array_key_exists($status_key, $history_status_counts)) {
            $history_status_counts[$status_key]++;
        }
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
            <p class="cs-page-subtitle">ใบงานที่เข้าสู่ขั้นตอนดำเนินการแล้ว จึงแสดงไว้สำหรับตรวจสอบข้อมูลเท่านั้น</p>
        </div>
    </div>

    <form class="toolbar setup-toolbar cs-sale-admin-toolbar cs-history-search-toolbar" action="javascript:void(0)">
        <input
            id="historySearchInput"
            type="search"
            data-history-search
            placeholder="ค้นหารหัสใบงาน ลูกค้า สินค้า หรือช่าง"
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
    <div class="cs-history-toolbar">
        <div class="cs-status-tabs cs-history-tabs" aria-label="กรองประวัติใบงาน">
            <?php foreach ($history_status_filters as $filter_key => $filter_label): ?>
                <button
                    type="button"
                    class="cs-status-tab <?= $filter_key === 'all' ? 'status-all active' : 'status-' . h($filter_key) ?>"
                    data-history-filter="<?= h($filter_key) ?>"
                >
                    <span><?= h($filter_label) ?></span>
                    <span class="status-count-badge"><?= h((string) ($history_status_counts[$filter_key] ?? 0)) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="cs-table-wrap cs-job-status-table-wrap cs-setups-table-wrap cs-history-table-wrap">
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

            <tbody>
                <?php if (count($history_setup_rows) === 0): ?>
                    <tr>
                        <td colspan="8" class="cs-empty-cell">ยังไม่มีประวัติใบงาน</td>
                    </tr>
                <?php endif; ?>

                <?php if (count($history_setup_rows) > 0): ?>
                    <tr data-history-search-empty style="display: none;">
                        <td colspan="8" class="cs-empty-cell">ไม่พบใบงานที่ตรงกับคำค้นหา</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($history_setup_rows as $row): ?>
                    <?php
                        $status_key = (string) ($row['history_status_key'] ?? 'assigned');
                        $customer_name = trim((string) ($row['user_name'] ?? ''));
                        $tech_name = trim((string) ($row['tech_name'] ?? ''));
                    ?>

                    <tr
                        data-history-status="<?= h($status_key) ?>"
                        data-history-search-text="<?= h(strtolower($row['setup_id'] . ' ' . $customer_name . ' ' . ($row['pro_name'] ?? '') . ' ' . $tech_name)) ?>"
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
                            <span class="setup-status-badge <?= h(history_status_class($status_key)) ?>">
                                <?= h(history_status_name($status_key)) ?>
                            </span>
                        </td>


                        <td>
                            <a
                                class="cs-table-action"
                                href="<?= h(app_system_url('sale/setup_slip.php?id=' . urlencode($row['setup_id']))) ?>"
                            >
                                <?= history_icon_svg('file') ?>
                                ใบติดตั้ง
                            </a>
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
