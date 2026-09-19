<?php
require_once __DIR__ . '/../check_login.php';
require_once __DIR__ . '/../db.php';

require_login('2');

$currentSaleId = trim((string) ($_SESSION['user_id'] ?? ''));
$setupId = trim((string) ($_GET['id'] ?? ''));

if ($currentSaleId === '') {
    redirect_to(app_system_url('login.php'));
}

if ($setupId === '') {
    redirect_to(app_system_url('sale/setup_history.php'));
}

function sale_detail_column_exists(mysqli $conn, string $table, string $column): bool
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

function sale_detail_date(?string $value, bool $withTime = false): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '--';
    }

    $timestamp = strtotime($value);

    if (!$timestamp) {
        return $value;
    }

    return $withTime
        ? date('d/m/Y H:i', $timestamp)
        : date('d/m/Y', $timestamp);
}

function sale_detail_time(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '--';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('H:i', $timestamp) : $value;
}

$installDateSelect = sale_detail_column_exists($conn, 'assignment', 'assign_install_date')
    ? 'a.assign_install_date'
    : 'NULL AS assign_install_date';

$installTimeSelect = sale_detail_column_exists($conn, 'assignment', 'assign_install_time')
    ? 'a.assign_install_time'
    : 'NULL AS assign_install_time';

$setupStmt = $conn->prepare("
    SELECT
        s.setup_id,
        s.customer_id AS user_id,
        s.sale_id,
        s.setup_date,
        s.setup_location,
        s.setup_address,
        s.setup_note,
        s.setup_status,
        s.created_at,

        c.customer_name AS user_name,
        c.customer_phone AS user_phone,
        c.customer_email AS user_email,
        c.customer_address AS user_address,

        a.assign_id,
        a.assign_by,
        a.assign_date,
        a.assign_status,
        {$installDateSelect},
        {$installTimeSelect},

        t.tech_id,
        COALESCE(NULLIF(TRIM(t.tech_fullname), ''), NULLIF(TRIM(t.tech_name), ''), t.tech_id) AS technician_name,
        t.tech_phone AS technician_phone,
        COALESCE(NULLIF(TRIM(assigner.user_name), ''), a.assign_by) AS assigner_name
    FROM setup s
    LEFT JOIN customers c
        ON s.customer_id = c.customer_id
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
    LEFT JOIN `user` assigner
        ON a.assign_by = assigner.user_id
    WHERE s.setup_id = ?
      AND s.sale_id = ?
    LIMIT 1
");

$setupStmt->bind_param('ss', $setupId, $currentSaleId);
$setupStmt->execute();
$setup = $setupStmt->get_result()->fetch_assoc();

if (!$setup) {
    redirect_to(app_system_url('sale/setup_history.php?status=notfound'));
}

$installationResult = null;
$installationResultImages = [];
$installationResultStmt = $conn->prepare('SELECT result_id, result_image FROM installation_result WHERE setup_id = ? LIMIT 1');
$installationResultStmt->bind_param('s', $setupId);
$installationResultStmt->execute();
$installationResult = $installationResultStmt->get_result()->fetch_assoc() ?: null;

if ($installationResult && !empty($installationResult['result_image'])) {
    $resultPayload = json_decode((string) $installationResult['result_image'], true);
    $resultPaths = is_array($resultPayload) && array_key_exists('photos', $resultPayload)
        ? $resultPayload['photos']
        : (is_array($resultPayload) ? $resultPayload : $installationResult['result_image']);

    $flattenResultPaths = static function ($value) use (&$flattenResultPaths): array {
        if (is_string($value)) {
            return [trim($value)];
        }

        if (!is_array($value)) {
            return [];
        }

        $paths = [];
        foreach ($value as $child) {
            $paths = array_merge($paths, $flattenResultPaths($child));
        }

        return $paths;
    };

    foreach ($flattenResultPaths($resultPaths) as $resultPath) {
        if ($resultPath !== '') {
            $installationResultImages[] = app_public_url($resultPath);
        }
    }
}

$items = [];

$detailStmt = $conn->prepare("
    SELECT
        d.pro_id,
        COALESCE(p.pro_name, d.pro_id) AS pro_name,
        COALESCE(d.install_qty, 1) AS install_qty,
        COALESCE(p.pro_price_install, 0) AS install_price,
        COALESCE(d.install_qty, 1) * COALESCE(p.pro_price_install, 0) AS install_total
    FROM install_detail d
    LEFT JOIN product p
        ON d.pro_id = p.pro_id
    WHERE d.setup_id = ?
    ORDER BY d.detail_id ASC
");

$detailStmt->bind_param('s', $setupId);
$detailStmt->execute();
$detailResult = $detailStmt->get_result();

while ($item = $detailResult->fetch_assoc()) {
    $items[] = $item;
}

$itemCount = count($items);
$totalAmount = 0.0;

foreach ($items as $item) {
    $totalAmount += (float) ($item['install_total'] ?? 0);
}

/*
 * Sale เห็นเฉพาะสถานะภาพรวม
 * ไม่แสดงสถานะย่อยของ workflow หัวหน้าช่าง
 */
$setupStatus = (int) ($setup['setup_status'] ?? 0);
$hasAssignment = !empty($setup['assign_id']);

if ($setupStatus === 5) {
    $saleStatusText = 'ยกเลิก';
    $saleStatusClass = 'cancelled';
} elseif ($setupStatus === 4) {
    $saleStatusText = 'เสร็จสิ้น';
    $saleStatusClass = 'done';
} elseif ($hasAssignment) {
    $saleStatusText = 'อยู่ระหว่างดำเนินการ';
    $saleStatusClass = 'progress';
} else {
    $saleStatusText = 'รอมอบหมายงาน';
    $saleStatusClass = 'waiting';
}

$assignmentId = '--';
$assignmentDate = '--';
$assignerName = '--';
$technicianName = '--';
$installDate = '--';
$installTime = '--';

if ($hasAssignment) {
    $assignmentId = trim((string) ($setup['assign_id'] ?? ''));
    if ($assignmentId === '') {
        $assignmentId = '--';
    }

    $assignmentDate = sale_detail_date(
        $setup['assign_date'] ?? null,
        true
    );

    $assignerName = trim((string) ($setup['assigner_name'] ?? ''));
    if ($assignerName === '') {
        $assignerName = '--';
    }

    $technicianName = trim((string) ($setup['technician_name'] ?? ''));
    if ($technicianName === '') {
        $technicianName = '--';
    }

    $installDate = sale_detail_date(
        $setup['assign_install_date'] ?? null
    );

    $installTime = sale_detail_time(
        $setup['assign_install_time'] ?? null
    );
}

layout_header('สรุปใบงาน', 'setup_history', 'ตรวจสอบรายละเอียดใบงานติดตั้ง');
?>

<link
  rel="stylesheet"
  href="<?= h(app_asset_url('sale/assets/css/setup_detail.css')) ?>?v=<?= h(asset_version('sale/assets/css/setup_detail.css')) ?>"
>

<section class="sale-detail-page">
    <!-- ส่วนหัวสรุปใบงาน -->
    <header class="sale-detail-page-header">
        <div class="sale-detail-summary-head">
            <div class="sale-detail-header-copy">
                <a
                    class="sale-detail-back"
                    href="<?= h(app_system_url('sale/setup_history.php')) ?>"
                >
                    <span class="sale-detail-back-icon" aria-hidden="true">←</span>
                    <span>กลับไปยังประวัติใบงาน</span>
                </a>

                <div class="sale-detail-summary-title-row">
                    <div class="sale-detail-summary-title">
                        <p class="sale-detail-setup-code">สรุปใบงาน</p>
                        <p class="sale-detail-content-subtitle">
                            รหัสใบงาน <strong><?= h($setup['setup_id']) ?></strong>
                            · ตรวจสอบรายละเอียดใบงานติดตั้ง
                        </p>
                    </div>
                </div>
            </div>

            <div class="sale-detail-header-actions">
                <span class="sale-detail-status <?= h($saleStatusClass) ?>">
                    <strong><?= h($saleStatusText) ?></strong>
                </span>
            </div>
        </div>
    </header>

    <div class="sale-detail-layout">
        <main class="sale-detail-main">
    <section class="sale-detail-customer-box customer-info">
        <header class="customer-info__header">
            <h2>ข้อมูลลูกค้า</h2>
        </header>

        <div class="sale-detail-customer-main customer-info__body">
            <div class="sale-detail-customer-icon customer-info__avatar" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M20 21a8 8 0 0 0-16 0"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>

            <div class="sale-detail-customer-content customer-info__content">
                <span class="customer-info__eyebrow">ลูกค้า</span>
                <h3><?= h($setup['user_name'] ?? '--') ?></h3>

                <div class="sale-detail-customer-contact customer-info__meta">
                    <span>
                        <svg class="ref-icon-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 9h16"></path><path d="M4 15h16"></path><path d="M10 3 8 21"></path><path d="M16 3 14 21"></path></svg>
                        <?= h($setup['user_id'] ?? '--') ?>
                    </span>
                    <span>
                        <svg class="ref-icon-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.32 1.77.59 2.61a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.47-1.16a2 2 0 0 1 2.11-.45c.84.27 1.71.47 2.61.59A2 2 0 0 1 22 16.92z"></path></svg>
                        <?= h($setup['user_phone'] ?? '--') ?>
                    </span>
                    <span>
                        <svg class="ref-icon-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 7l9 6 9-6"></path></svg>
                        <?= h($setup['user_email'] ?? '--') ?>
                    </span>
                </div>

                <div class="sale-detail-customer-address customer-info__address">
                    <span>
                        <svg class="ref-icon-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                        <span><?= h($setup['user_address'] ?? '--') ?></span>
                    </span>
                </div>
            </div>

            <div class="sale-detail-customer-id customer-info__id">
                <span>รหัสลูกค้า</span>
                <strong><?= h($setup['user_id'] ?? '--') ?></strong>
            </div>
        </div>
    </section>

    <section class="sale-detail-work-box">
        <header class="sale-detail-card-head">
            <div>
                <h2>รายการสินค้า</h2>
                <p>
                    <?= h((string) $itemCount) ?> รายการ ·
                    <?= h((string) array_sum(array_map(static fn (array $item): int => (int) ($item['install_qty'] ?? 0), $items))) ?> ชิ้น
                </p>
            </div>
        </header>

        <div class="sale-detail-product-table">
            <div class="sale-detail-product-head">
                <span>สินค้า</span>
                <span>จำนวน</span>
                <span>ค่าติดตั้ง/หน่วย</span>
                <span>รวม</span>
            </div>

            <?php if ($itemCount === 0): ?>
                <div class="sale-detail-empty">ไม่พบรายการสินค้า</div>
            <?php endif; ?>

            <?php foreach ($items as $item): ?>
                <div class="sale-detail-product-row">
                    <div class="sale-detail-product-main">
                        <strong><?= h($item['pro_name'] ?? '--') ?></strong>
                    </div>

                    <span><?= h((string) ($item['install_qty'] ?? 0)) ?> ชิ้น</span>

                    <span>
                        <?= h(number_format((float) ($item['install_price'] ?? 0), 2)) ?> บาท
                    </span>

                    <strong class="sale-detail-product-total">
                        <?= h(number_format((float) ($item['install_total'] ?? 0), 2)) ?> บาท
                    </strong>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="sale-detail-work-bottom">
            <div class="sale-detail-note">
                <span>หมายเหตุ</span>
                <strong>
                    <?= h(
                        trim((string) ($setup['setup_note'] ?? '')) !== ''
                            ? $setup['setup_note']
                            : '--'
                    ) ?>
                </strong>
            </div>

            <div class="sale-detail-total-row">
                <span>รวมค่าติดตั้ง</span>
                <strong><?= h(number_format($totalAmount, 2)) ?> บาท</strong>
            </div>
        </div>
    </section>

    <section class="sale-detail-installation-result" id="installation-result" aria-labelledby="installation-result-title">
        <div class="sale-detail-installation-result-content">
            <div class="sale-detail-installation-result-heading">
                <h2 id="installation-result-title">ผลการติดตั้ง</h2>
                <p>รูปงานติดตั้งที่ช่างบันทึกไว้</p>
            </div>

            <span class="sale-detail-installation-result-count">
                <?= h((string) count($installationResultImages)) ?> รูป
            </span>

            <?php if ($installationResultImages): ?>
                <div class="sale-detail-installation-result-gallery-block">
                    <span class="sale-detail-installation-result-label">รูปงานติดตั้ง</span>
                    <div class="sale-detail-installation-result-gallery">
                        <?php foreach (array_slice($installationResultImages, 0, 3) as $photoIndex => $installationResultImage): ?>
                            <a
                                class="sale-detail-installation-result-image installation-result-image-link"
                                href="<?= h($installationResultImage) ?>"
                                data-installation-result-image
                                data-gallery-index="<?= h((string) $photoIndex) ?>"
                                data-image-src="<?= h($installationResultImage) ?>"
                                data-image-alt="รูปงานติดตั้งที่ <?= h((string) ($photoIndex + 1)) ?>"
                            >
                                <img
                                    class="installation-result-image"
                                    src="<?= h($installationResultImage) ?>"
                                    alt="รูปงานติดตั้งที่ <?= h((string) ($photoIndex + 1)) ?>"
                                    loading="lazy"
                                >
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="sale-detail-installation-result-empty">
                    <strong>ยังไม่มีรูปงานติดตั้งที่ช่างบันทึกไว้</strong>
                </div>
            <?php endif; ?>
        </div>
    </section>
        </main>

        <!-- การ์ดข้อมูลการดำเนินงาน -->
        <aside class="sale-detail-side">
            <section class="sale-detail-process-card">
                <div class="sale-detail-process-head">
                    <div>
                        <h2>การดำเนินการ</h2>
                        <p>ข้อมูลการมอบหมายงาน</p>
                    </div>
                </div>

                <div class="sale-detail-process-row">
                    <div>
                        <span>รหัสงานมอบหมาย</span>
                        <strong><?= h($assignmentId) ?></strong>
                    </div>

                    <div>
                        <span>วันที่มอบหมาย</span>
                        <strong><?= h($assignmentDate) ?></strong>
                    </div>

                    <div>
                        <span>ผู้มอบหมายงาน</span>
                        <strong><?= h($assignerName) ?></strong>
                    </div>

                    <div>
                        <span>ช่างที่ได้รับมอบหมาย</span>
                        <strong><?= h($technicianName) ?></strong>
                    </div>

                    <div>
                        <span>วันที่ติดตั้ง</span>
                        <strong><?= h($installDate) ?></strong>
                    </div>

                    <div>
                        <span>เวลา</span>
                        <strong><?= h($installTime) ?></strong>
                    </div>
                </div>

                <div class="sale-detail-process-total">
                    <span>ค่าติดตั้ง</span>
                    <strong><?= h(number_format($totalAmount, 2)) ?> บาท</strong>
                </div>

                <div class="sale-detail-process-actions">
                    <?php if ($installationResult): ?>
                        <a class="sale-detail-slip-btn sale-detail-result-btn" href="#installation-result">
                            ดูผลการติดตั้ง
                        </a>
                    <?php else: ?>
                        <button class="sale-detail-slip-btn sale-detail-result-btn" type="button" disabled>
                            ดูผลการติดตั้ง
                        </button>
                    <?php endif; ?>

                    <a
                        class="sale-detail-slip-btn"
                        href="<?= h(app_system_url('sale/setup_slip.php?id=' . urlencode($setupId))) ?>"
                    >
                        ดูใบติดตั้ง
                    </a>
                </div>
            </section>
        </aside>
    </div>

    <?php if ($installationResultImages): ?>
        <dialog class="installation-result-modal" data-installation-result-modal aria-labelledby="installation-result-modal-title">
            <div class="installation-result-modal-header">
                <h2 id="installation-result-modal-title">รูปงานติดตั้งทั้งหมด</h2>
                <button class="installation-result-modal-close" type="button" data-installation-result-close aria-label="ปิดรูปภาพ">×</button>
            </div>

            <div class="installation-result-modal-stage" data-installation-result-stage>
                <button class="installation-result-modal-nav is-prev" type="button" data-installation-result-prev aria-label="ดูรูปก่อนหน้า">‹</button>
                <img class="installation-result-modal-image" data-installation-result-modal-image alt="">
                <button class="installation-result-modal-nav is-next" type="button" data-installation-result-next aria-label="ดูรูปถัดไป">›</button>
            </div>

            <div class="installation-result-modal-grid">
                <?php foreach ($installationResultImages as $photoIndex => $installationResultImage): ?>
                    <button
                        class="installation-result-modal-thumb"
                        type="button"
                        data-installation-result-thumb
                        data-gallery-index="<?= h((string) $photoIndex) ?>"
                        data-image-src="<?= h($installationResultImage) ?>"
                        data-image-alt="รูปงานติดตั้งที่ <?= h((string) ($photoIndex + 1)) ?>"
                        aria-label="เปิดรูปงานติดตั้งที่ <?= h((string) ($photoIndex + 1)) ?>"
                    >
                        <img src="<?= h($installationResultImage) ?>" alt="" loading="lazy">
                    </button>
                <?php endforeach; ?>
            </div>
        </dialog>
    <?php endif; ?>
</section>

<?php if ($installationResultImages): ?>
    <script>
    (() => {
        const modal = document.querySelector('[data-installation-result-modal]');
        const closeButton = document.querySelector('[data-installation-result-close]');
        const modalStage = document.querySelector('[data-installation-result-stage]');
        const modalImage = document.querySelector('[data-installation-result-modal-image]');
        const previousButton = document.querySelector('[data-installation-result-prev]');
        const nextButton = document.querySelector('[data-installation-result-next]');
        const imageLinks = document.querySelectorAll('[data-installation-result-image]');
        const thumbnailButtons = document.querySelectorAll('[data-installation-result-thumb]');

        if (!modal || !closeButton || !modalStage || !modalImage || !previousButton || !nextButton || !thumbnailButtons.length) {
            return;
        }

        let currentIndex = 0;
        const galleryItems = Array.from(thumbnailButtons);

        const renderImage = (index) => {
            const itemCount = galleryItems.length;
            if (!itemCount) {
                return;
            }

            currentIndex = (index + itemCount) % itemCount;
            const item = galleryItems[currentIndex];
            const imageSrc = item.dataset.imageSrc || '';
            if (!imageSrc) {
                return;
            }

            modalImage.src = imageSrc;
            modalImage.alt = item.dataset.imageAlt || 'รูปงานติดตั้ง';
            thumbnailButtons.forEach((thumbnail, thumbnailIndex) => {
                const isActive = thumbnailIndex === currentIndex;
                thumbnail.classList.toggle('is-active', isActive);
                if (isActive) {
                    thumbnail.setAttribute('aria-current', 'true');
                    thumbnail.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                } else {
                    thumbnail.removeAttribute('aria-current');
                }
            });

            const hasMultipleImages = itemCount > 1;
            previousButton.hidden = !hasMultipleImages;
            nextButton.hidden = !hasMultipleImages;
        };

        imageLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                renderImage(Number(link.dataset.galleryIndex || 0));
                modal.showModal();
                closeButton.focus();
            });
        });

        thumbnailButtons.forEach((thumbnail) => {
            thumbnail.addEventListener('click', () => {
                renderImage(Number(thumbnail.dataset.galleryIndex || 0));
            });
        });

        previousButton.addEventListener('click', () => renderImage(currentIndex - 1));
        nextButton.addEventListener('click', () => renderImage(currentIndex + 1));
        closeButton.addEventListener('click', () => modal.close());
        modalStage.addEventListener('click', (event) => {
            if (event.target === modalStage) {
                modal.close();
            }
        });
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.close();
            }
        });
        modal.addEventListener('close', () => {
            modalImage.removeAttribute('src');
            modalImage.alt = '';
            thumbnailButtons.forEach((thumbnail) => {
                thumbnail.classList.remove('is-active');
                thumbnail.removeAttribute('aria-current');
            });
            currentIndex = 0;
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.open) {
                event.preventDefault();
                modal.close();
            }
        });
    })();
    </script>
<?php endif; ?>

<?php
layout_footer();
