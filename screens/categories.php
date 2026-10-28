<?php
declare(strict_types=1);

$catList = $conn->query(
    "SELECT c.*, COUNT(d.donation_id) AS items_count, COALESCE(SUM(d.quantity), 0) AS units_sum
     FROM categories c
     LEFT JOIN donations d ON d.category_id = c.category_id
     GROUP BY c.category_id
     ORDER BY c.category_id ASC"
)->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 fw-bold m-0" style="color: var(--text-dark);">Relief Supply Categories</h2>
        <div style="font-size: 14px; color: var(--text-muted);">Organized supplies by food, water, medical, apparel, and sanitation kits.</div>
    </div>
    <button type="button" class="btn-plum-action" data-bs-toggle="modal" data-bs-target="#modalAddCategory">
        <i data-lucide="plus" style="width:16px;height:16px;stroke-width:2.5;"></i>
        <span>Add Category</span>
    </button>
</div>

<div class="row g-4">
    <?php foreach ($catList as $c): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card-smartech h-100">
                <div>
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width: 48px; height: 48px; background: #ffffff; border-radius: 16px; display: grid; place-items: center; color: var(--panel-plum); box-shadow: 0 2px 6px rgba(0,0,0,0.05);">
                                <i data-lucide="<?=h($c['icon'] ?: 'package')?>" style="width:24px;height:24px;stroke-width:2;"></i>
                            </div>
                            <div>
                                <h3 class="h5 fw-bold m-0" style="color: var(--text-dark);"><?=h($c['category_name'])?></h3>
                                <small class="text-muted">#CAT-<?=$c['category_id']?></small>
                            </div>
                        </div>
                        <span class="badge rounded-pill" style="background: var(--panel-plum); color: #fff; font-size: 12px; padding: 6px 12px;">
                            <?=number_format((int)$c['units_sum'])?> units
                        </span>
                    </div>
                    <p style="font-size: 14px; color: var(--text-muted); min-height: 42px;"><?=h($c['description'] ?: 'General classification')?></p>
                </div>
                <div class="d-flex justify-content-between align-items-center pt-3 border-top" style="border-color: rgba(62, 41, 56, 0.1) !important;">
                    <span class="small fw-bold text-muted"><?=number_format((int)$c['items_count'])?> entries recorded</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm rounded-pill px-3 fw-bold" style="background: #ffffff; color: var(--text-dark);"
                                data-bs-toggle="modal"
                                data-bs-target="#modalEditCategory"
                                data-id="<?=$c['category_id']?>"
                                data-name="<?=h($c['category_name'])?>"
                                data-desc="<?=h($c['description'])?>"
                                data-icon="<?=h($c['icon'])?>"
                                data-color="<?=h($c['color'])?>">
                            Edit
                        </button>
                        <button type="button" class="btn btn-sm rounded-pill px-3 fw-bold text-danger" style="background: #FEE2E2;"
                                data-bs-toggle="modal"
                                data-bs-target="#modalDeleteCategory"
                                data-id="<?=$c['category_id']?>"
                                data-name="<?=h($c['category_name'])?>">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
