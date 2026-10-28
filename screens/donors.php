<?php
declare(strict_types=1);

$searchDonor = trim((string)($_GET['search_donor'] ?? ''));
$donorSql = "SELECT dn.*, COUNT(d.donation_id) AS donation_count, COALESCE(SUM(d.quantity), 0) AS total_units_given
             FROM donors dn
             LEFT JOIN donations d ON d.donor_id = dn.donor_id";
$donorParams = [];
if ($searchDonor !== '') {
    $donorSql .= " WHERE dn.contact_person LIKE ? OR dn.contact_number LIKE ? OR dn.address LIKE ?";
    $term = '%' . $searchDonor . '%';
    $donorParams = [$term, $term, $term];
}
$donorSql .= " GROUP BY dn.donor_id ORDER BY dn.donor_id DESC";
$dstmt = $conn->prepare($donorSql);
$dstmt->execute($donorParams);
$donorsList = $dstmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 fw-bold m-0" style="color: var(--text-dark);">Relief Donors & Partners</h2>
        <div style="font-size: 14px; color: var(--text-muted);">Partner organizations, individual donors, and field sponsors.</div>
    </div>
    <button type="button" class="btn-plum-action" data-bs-toggle="modal" data-bs-target="#modalAddDonor">
        <i data-lucide="plus" style="width:16px;height:16px;stroke-width:2.5;"></i>
        <span>Register Donor</span>
    </button>
</div>

<div class="table-responsive">
    <table class="table-smartech table-responsive-cards">
        <thead>
            <tr>
                <th>ID</th>
                <th>Contact Person / Organization</th>
                <th>Phone Contact</th>
                <th>Depot / Address</th>
                <th>Contributions</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($donorsList)): ?>
                <tr><td colspan="6" class="text-center text-muted py-5">No donors found.</td></tr>
            <?php else: foreach ($donorsList as $donor): ?>
                <tr>
                    <td data-label="ID"><span style="color: var(--panel-plum); font-weight: 800;">#DNR-<?=$donor['donor_id']?></span></td>
                    <td data-label="Contact Person"><span class="fw-bold"><?=h($donor['contact_person'])?></span></td>
                    <td data-label="Phone">
                        <a href="tel:<?=h($donor['contact_number'])?>" class="text-decoration-none fw-bold" style="color: var(--panel-plum);">
                            <?=h($donor['contact_number'])?>
                        </a>
                    </td>
                    <td data-label="Address" class="small text-muted"><?=h($donor['address'])?></td>
                    <td data-label="Contributions">
                        <span class="badge rounded-pill" style="background: #EBE0DB; color: var(--text-dark); font-weight: 800; font-size: 12px; padding: 6px 12px;">
                            <?=$donor['donation_count']?> records &bull; <?=number_format((int)$donor['total_units_given'])?> units
                        </span>
                    </td>
                    <td data-label="Actions" class="text-end">
                        <div class="d-inline-flex gap-2">
                            <button type="button" class="btn btn-sm rounded-circle p-2"
                                    style="background: #EBE0DB; color: var(--text-dark); width: 36px; height: 36px; display: grid; place-items: center;"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditDonor"
                                    data-id="<?=$donor['donor_id']?>"
                                    data-person="<?=h($donor['contact_person'])?>"
                                    data-phone="<?=h($donor['contact_number'])?>"
                                    data-address="<?=h($donor['address'])?>">
                                <i data-lucide="edit-3" style="width:15px;height:15px;stroke-width:2;"></i>
                            </button>
                            <button type="button" class="btn btn-sm rounded-circle p-2 text-danger"
                                    style="background: #FEE2E2; width: 36px; height: 36px; display: grid; place-items: center;"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalDeleteDonor"
                                    data-id="<?=$donor['donor_id']?>"
                                    data-person="<?=h($donor['contact_person'])?>">
                                <i data-lucide="trash-2" style="width:15px;height:15px;stroke-width:2;"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
