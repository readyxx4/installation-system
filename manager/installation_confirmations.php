<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('1');

date_default_timezone_set('Asia/Bangkok');

$keyword = trim((string) ($_GET['q'] ?? ''));

function manager_confirmation_date(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '--';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : '--';
}

function manager_confirmation_time(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('H:i', $timestamp) : '';
}

function manager_confirmation_time_range(?string $start, ?string $end): string
{
    $startText = manager_confirmation_time($start);
    $endText = manager_confirmation_time($end);

    if ($startText === '') {
        return '--';
    }

    return $endText !== ''
        ? $startText . ' - ' . $endText . ' น.'
        : $startText . ' น.';
}

$sql = <<<'SQL'
    SELECT
        s.setup_id,
        c.customer_name,
        a.assign_id,
        a.assign_install_date,
        a.assign_install_time,
        a.assign_install_end_time,
        COALESCE(NULLIF(TRIM(t.tech_name), ''), NULLIF(TRIM(t.tech_fullname), ''), a.tech_id, '--') AS technician_name
    FROM setup s
    INNER JOIN assignment a
        ON a.setup_id = s.setup_id
       AND a.assign_id = (
            SELECT latest_a.assign_id
            FROM assignment latest_a
            WHERE latest_a.setup_id = s.setup_id
            ORDER BY latest_a.assign_date DESC, latest_a.assign_id DESC
            LIMIT 1
       )
    LEFT JOIN customers c
        ON c.customer_id = s.customer_id
    LEFT JOIN technicians t
        ON TRIM(t.tech_id) = TRIM(a.tech_id)
    WHERE s.setup_status = 3
      AND (a.assign_status IS NULL OR a.assign_status NOT IN (4, 5))
      AND EXISTS (
          SELECT 1
          FROM installation_result ir
          WHERE ir.setup_id = s.setup_id
      )
SQL;

if ($keyword !== '') {
    $sql .= <<<'SQL'
      AND (
          s.setup_id LIKE ?
          OR a.assign_id LIKE ?
          OR c.customer_name LIKE ?
          OR t.tech_name LIKE ?
          OR t.tech_fullname LIKE ?
      )
SQL;
}

$sql .= <<<'SQL'
    ORDER BY a.assign_install_date ASC, a.assign_install_time ASC, a.assign_date DESC, a.assign_id DESC
SQL;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $rows = [];
} elseif ($keyword !== '') {
    $like = '%' . $keyword . '%';
    $stmt->bind_param('sssss', $like, $like, $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
} else {
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

layout_header('ยืนยันผลการติดตั้ง', 'installation_confirmations', 'ตรวจสอบงานที่ช่างบันทึกผลการติดตั้งแล้ว และยืนยันปิดงาน');
?>

<link rel="stylesheet"
    href="<?= h(app_asset_url('manager/assets/css/installation_confirmations.css')) ?>?v=<?= h(asset_version('manager/assets/css/installation_confirmations.css')) ?>">

<main class="manager-confirmations-page">
    <?= flash_message() ?>

    <form class="manager-confirmations-toolbar" method="get"
        action="<?= h(app_system_url('manager/installation_confirmations.php')) ?>">
        <label class="manager-confirmations-search">
            <span class="sr-only">ค้นหางานรอยืนยัน</span>
            <svg class="manager-confirmations-search-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20L16.65 16.65"></path></svg>
            <input type="search" name="q" value="<?= h($keyword) ?>"
                placeholder="ค้นหารหัสมอบหมาย ลูกค้า หรือช่าง">
        </label>
        <button type="submit" class="manager-confirmations-search-button">
            <svg class="manager-confirmations-search-button-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M20 20L16.65 16.65"></path></svg>
            <span>ค้นหา</span>
        </button>
        <a class="manager-confirmations-reset" href="<?= h(app_system_url('manager/installation_confirmations.php')) ?>">
            <svg class="manager-confirmations-reset-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"></path><path d="M3 4v6h6"></path></svg>
            <span>ล้างค้นหา</span>
        </a>
    </form>

    <section class="manager-confirmations-panel" aria-label="รายการงานรอยืนยัน">
        <div class="manager-confirmations-table-wrap">
            <table class="manager-confirmations-table">
                <thead>
                    <tr>
                        <th>รหัสมอบหมายงาน</th>
                        <th>ลูกค้า</th>
                        <th>ช่างติดตั้ง</th>
                        <th>วันที่ติดตั้ง</th>
                        <th>เวลา</th>
                        <th class="manager-confirmations-status-heading">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="6" class="manager-confirmations-empty">
                                <?= $keyword !== '' ? 'ไม่พบงานรอยืนยันที่ตรงกับคำค้นหา' : 'ยังไม่มีงานที่รอหัวหน้าช่างยืนยัน' ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($rows as $row): ?>
                        <tr
                            class="manager-confirmations-row"
                            tabindex="0"
                            role="link"
                            data-detail-url="<?= h(app_system_url('manager/assignment_detail.php?id=' . urlencode((string) ($row['setup_id'] ?? '')))) ?>"
                            onclick="window.location.assign(this.dataset.detailUrl)"
                            onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location.assign(this.dataset.detailUrl); }"
                        >
                            <td>
                                <strong><?= h($row['assign_id'] ?? '--') ?></strong>
                            </td>
                            <td><?= h($row['customer_name'] ?? '--') ?></td>
                            <td><?= h($row['technician_name'] ?? '--') ?></td>
                            <td><?= h(manager_confirmation_date($row['assign_install_date'] ?? null)) ?></td>
                            <td><?= h(manager_confirmation_time_range($row['assign_install_time'] ?? null, $row['assign_install_end_time'] ?? null)) ?></td>
                            <td class="manager-confirmations-status-cell">
                                <a
                                    class="manager-confirmations-search-button"
                                    href="<?= h(app_system_url('manager/assignment_detail.php?id=' . urlencode((string) ($row['setup_id'] ?? '')))) ?>"
                                    onclick="event.stopPropagation()"
                                >
                                    ตรวจสอบผล
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php layout_footer(); ?>
