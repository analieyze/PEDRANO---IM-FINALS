<?php
declare(strict_types=1);

$search = trim((string)($_GET['search'] ?? ''));
$filterCat = (int)($_GET['filter_cat'] ?? 0);
$filterStatus = trim((string)($_GET['filter_status'] ?? ''));
$filterDateFrom = trim((string)($_GET['date_from'] ?? ''));
$filterDateTo = trim((string)($_GET['date_to'] ?? ''));

$sortColumn = $_GET['sort'] ?? 'donation_id';
$sortOrder = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$allowedSorts = [
    'donation_id' => 'd.donation_id',
    'item_name' => 'd.item_name',
    'category' => 'c.category_name',
    'quantity' => 'd.quantity',
    'status' => 'd.status',
    'date' => 'd.donation_date'
];
$orderBy = $allowedSorts[$sortColumn] ?? 'd.donation_id';

$whereSql = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $whereSql .= " AND (d.item_name LIKE ? OR dn.contact_person LIKE ? OR d.donation_id LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like]);
}
if ($filterCat > 0) {
    $whereSql .= " AND d.category_id = ?";
    $params[] = $filterCat;
}
if ($filterStatus !== '') {
    $whereSql .= " AND d.status = ?";
    $params[] = $filterStatus;
}
if ($filterDateFrom !== '') {
    $whereSql .= " AND d.donation_date >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo !== '') {
    $whereSql .= " AND d.donation_date <= ?";
    $params[] = $filterDateTo;
}

$countStmt = $conn->prepare("SELECT COUNT(*) FROM donations d LEFT JOIN donors dn ON dn.donor_id = d.donor_id $whereSql");
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();

$perPage = 10;
$totalPages = max(1, (int)ceil($totalFiltered / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;

$dataSql = "SELECT d.*, c.category_name, c.icon, c.color, dn.contact_person, dn.contact_number
            FROM donations d
            LEFT JOIN categories c ON c.category_id = d.category_id
            LEFT JOIN donors dn ON dn.donor_id = d.donor_id
            $whereSql
            ORDER BY $orderBy $sortOrder
            LIMIT $perPage OFFSET $offset";
$dataStmt = $conn->prepare($dataSql);
$dataStmt->execute($params);
$donationsList = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

$hasActiveFilters = ($search !== '' || $filterCat > 0 || $filterStatus !== '' || $filterDateFrom !== '' || $filterDateTo !== '');
$activeCatName = '';
if ($filterCat > 0) {
    $cstmt = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?");
    $cstmt->execute([$filterCat]);
    $activeCatName = (string)$cstmt->fetchColumn();
}
?>
<!-- View Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h2 class="h3 fw-bold m-0" style="color: var(--text-dark);">Relief Donation Records</h2>
        <div style="font-size: 14px; color: var(--text-muted);">Manage supply intake, warehouse verification, and evacuation releases.</div>
    </div>
    <button type="button" class="btn-plum-action" data-bs-toggle="modal" data-bs-target="#modalAddDonation">
        <i data-lucide="plus" style="width:16px;height:16px;stroke-width:2.5;"></i>
        <span>Record Donation</span>
    </button>
</div>

<!-- Pill Filter Card (Rose-Taupe) -->
<div class="card-smartech mb-4 p-4">
    <form method="get" action="index.php" id="donationsFilterForm">
        <input type="hidden" name="section" value="donations">
        <input type="hidden" name="sort" value="<?=h($sortColumn)?>">
        <input type="hidden" name="order" value="<?=h($sortOrder)?>">

        <div class="row g-3">
            <div class="col-lg-4 col-md-12">
                <label class="form-label" for="search_input">Search Keyword</label>
                <div class="position-relative">
                    <input type="text" class="form-control" id="search_input" name="search" placeholder="Type to filter item, donor..." value="<?=h($search)?>" autocomplete="off" style="padding-left: 42px;">
                    <i data-lucide="search" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-muted); pointer-events: none;"></i>
                </div>
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label" for="filter_cat">Category</label>
                <select class="form-control form-select" id="filter_cat" name="filter_cat" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php
                    $allCats = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($allCats as $ac):
                    ?>
                        <option value="<?=$ac['category_id']?>" <?=$filterCat === (int)$ac['category_id'] ? 'selected' : ''?>>
                            <?=h($ac['category_name'])?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label" for="filter_status">Status</label>
                <select class="form-control form-select" id="filter_status" name="filter_status" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?=$filterStatus === 'Pending' ? 'selected' : ''?>>Pending</option>
                    <option value="Received" <?=$filterStatus === 'Received' ? 'selected' : ''?>>Received</option>
                    <option value="In Stock" <?=$filterStatus === 'In Stock' ? 'selected' : ''?>>In Stock</option>
                    <option value="Released" <?=$filterStatus === 'Released' ? 'selected' : ''?>>Released</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label" for="date_from">From Date</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?=h($filterDateFrom)?>" onchange="this.form.submit()">
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label" for="date_to">To Date</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?=h($filterDateTo)?>" onchange="this.form.submit()">
            </div>
        </div>
    </form>

    <!-- Removable Chips -->
    <?php if ($hasActiveFilters): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top" style="border-color: rgba(62, 41, 56, 0.1) !important;">
            <span class="small fw-bold" style="color: var(--text-dark);">Active:</span>
            <?php if ($search !== ''): ?>
                <a href="?<?=h(http_build_query(array_merge($_GET, ['search' => '', 'page' => 1])))?>" class="chip-filter" title="Remove search filter">
                    Search: "<?=h($search)?>" <i data-lucide="x" style="width:12px;height:12px;stroke-width:2.5;"></i>
                </a>
            <?php endif; ?>
            <?php if ($filterCat > 0): ?>
                <a href="?<?=h(http_build_query(array_merge($_GET, ['filter_cat' => '', 'page' => 1])))?>" class="chip-filter" title="Remove category filter">
                    Category: <?=h($activeCatName)?> <i data-lucide="x" style="width:12px;height:12px;stroke-width:2.5;"></i>
                </a>
            <?php endif; ?>
            <?php if ($filterStatus !== ''): ?>
                <a href="?<?=h(http_build_query(array_merge($_GET, ['filter_status' => '', 'page' => 1])))?>" class="chip-filter" title="Remove status filter">
                    Status: <?=h($filterStatus)?> <i data-lucide="x" style="width:12px;height:12px;stroke-width:2.5;"></i>
                </a>
            <?php endif; ?>
            <?php if ($filterDateFrom !== '' || $filterDateTo !== ''): ?>
                <a href="?<?=h(http_build_query(array_merge($_GET, ['date_from' => '', 'date_to' => '', 'page' => 1])))?>" class="chip-filter" title="Remove date filter">
                    Date: <?=h($filterDateFrom ?: 'Start')?> &rarr; <?=h($filterDateTo ?: 'End')?> <i data-lucide="x" style="width:12px;height:12px;stroke-width:2.5;"></i>
                </a>
            <?php endif; ?>
            <a href="?section=donations" class="btn btn-link btn-sm text-danger text-decoration-none small p-0 ms-2 fw-bold">Reset All</a>
        </div>
    <?php endif; ?>
</div>

<!-- Table of Donations -->
<div class="table-responsive">
    <table class="table-smartech table-responsive-cards">
        <thead>
            <tr>
                <th>
                    <a href="?<?=http_build_query(array_merge($_GET, ['sort' => 'donation_id', 'order' => $sortColumn === 'donation_id' && $sortOrder === 'ASC' ? 'desc' : 'asc']))?>" class="text-decoration-none text-muted">
                        ID <?=$sortColumn === 'donation_id' ? ($sortOrder === 'ASC' ? '&uarr;' : '&darr;') : ''?>
                    </a>
                </th>
                <th>
                    <a href="?<?=http_build_query(array_merge($_GET, ['sort' => 'item_name', 'order' => $sortColumn === 'item_name' && $sortOrder === 'ASC' ? 'desc' : 'asc']))?>" class="text-decoration-none text-muted">
                        Item Name <?=$sortColumn === 'item_name' ? ($sortOrder === 'ASC' ? '&uarr;' : '&darr;') : ''?>
                    </a>
                </th>
                <th>Category</th>
                <th>
                    <a href="?<?=http_build_query(array_merge($_GET, ['sort' => 'quantity', 'order' => $sortColumn === 'quantity' && $sortOrder === 'ASC' ? 'desc' : 'asc']))?>" class="text-decoration-none text-muted">
                        Qty <?=$sortColumn === 'quantity' ? ($sortOrder === 'ASC' ? '&uarr;' : '&darr;') : ''?>
                    </a>
                </th>
                <th>Status</th>
                <th>Donor Contributor</th>
                <th>
                    <a href="?<?=http_build_query(array_merge($_GET, ['sort' => 'date', 'order' => $sortColumn === 'date' && $sortOrder === 'ASC' ? 'desc' : 'asc']))?>" class="text-decoration-none text-muted">
                        Date <?=$sortColumn === 'date' ? ($sortOrder === 'ASC' ? '&uarr;' : '&darr;') : ''?>
                    </a>
                </th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($donationsList)): ?>
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="mb-2"><i data-lucide="search-x" style="width:40px;height:40px;stroke-width:1.5;color:var(--text-muted);"></i></div>
                        <div class="fw-bold mb-1 fs-5">No donation records match your search</div>
                        <div class="small text-muted mb-3">Adjust your filters or record a new supply batch.</div>
                        <a href="?section=donations" class="btn btn-sm rounded-pill fw-bold px-4 py-2" style="background: var(--panel-plum); color: #fff;">Clear Filters</a>
                    </td>
                </tr>
            <?php else: foreach ($donationsList as $item):
                $st = $item['status'] ?? 'Received';
                $badgeClass = match($st) {
                    'Pending' => 'badge-status-pending',
                    'In Stock' => 'badge-status-stock',
                    'Released' => 'badge-status-released',
                    default => 'badge-status-received'
                };
                $stIcon = match($st) {
                    'Pending' => 'clock',
                    'In Stock' => 'package',
                    'Released' => 'arrow-up-right',
                    default => 'check-circle-2'
                };
            ?>
                <tr>
                    <td data-label="ID">
                        <span style="color: var(--panel-plum); font-weight: 800;">#DON-<?=str_pad((string)$item['donation_id'], 4, '0', STR_PAD_LEFT)?></span>
                    </td>
                    <td data-label="Item Name">
                        <span class="fw-bold"><?=h($item['item_name'])?></span>
                    </td>
                    <td data-label="Category">
                        <span class="badge rounded-pill" style="background: #EBE0DB; color: var(--text-dark); font-weight: 800; font-size: 12px; padding: 6px 12px;">
                            <?=h($item['category_name'] ?? 'General')?>
                        </span>
                    </td>
                    <td data-label="Quantity">
                        <span class="fw-bold fs-6"><?=number_format((int)$item['quantity'])?></span>
                    </td>
                    <td data-label="Status">
                        <div class="dropdown d-inline-block">
                            <button class="badge-status-pill <?=$badgeClass?> border-0 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Click to update status">
                                <i data-lucide="<?=$stIcon?>" style="width:13px;height:13px;stroke-width:2.5;"></i>
                                <span><?=h($st)?></span>
                            </button>
                            <ul class="dropdown-menu shadow border-0 p-2" style="background: var(--container-cream); border-radius: 16px; font-size: 13px;">
                                <li><h6 class="dropdown-header">Update Status</h6></li>
                                <li>
                                    <form method="post" action="index.php">
                                        <input type="hidden" name="action" value="donation_status_update">
                                        <input type="hidden" name="donation_id" value="<?=$item['donation_id']?>">
                                        <input type="hidden" name="status" value="Pending">
                                        <button type="submit" class="dropdown-item rounded-pill">Pending</button>
                                    </form>
                                </li>
                                <li>
                                    <form method="post" action="index.php">
                                        <input type="hidden" name="action" value="donation_status_update">
                                        <input type="hidden" name="donation_id" value="<?=$item['donation_id']?>">
                                        <input type="hidden" name="status" value="Received">
                                        <button type="submit" class="dropdown-item rounded-pill">Received</button>
                                    </form>
                                </li>
                                <li>
                                    <form method="post" action="index.php">
                                        <input type="hidden" name="action" value="donation_status_update">
                                        <input type="hidden" name="donation_id" value="<?=$item['donation_id']?>">
                                        <input type="hidden" name="status" value="In Stock">
                                        <button type="submit" class="dropdown-item rounded-pill">In Stock</button>
                                    </form>
                                </li>
                                <li>
                                    <form method="post" action="index.php">
                                        <input type="hidden" name="action" value="donation_status_update">
                                        <input type="hidden" name="donation_id" value="<?=$item['donation_id']?>">
                                        <input type="hidden" name="status" value="Released">
                                        <button type="submit" class="dropdown-item rounded-pill">Released to Field</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </td>
                    <td data-label="Donor">
                        <div class="fw-bold"><?=h($item['contact_person'] ?? 'Anonymous')?></div>
                    </td>
                    <td data-label="Date" class="small text-muted">
                        <?=date('M j, Y', strtotime($item['donation_date']))?>
                    </td>
                    <td data-label="Actions" class="text-end">
                        <div class="d-inline-flex gap-2">
                            <button type="button" class="btn btn-sm rounded-circle p-2"
                                    style="background: #EBE0DB; color: var(--text-dark); width: 36px; height: 36px; display: grid; place-items: center;"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditDonation"
                                    data-id="<?=$item['donation_id']?>"
                                    data-name="<?=h($item['item_name'])?>"
                                    data-qty="<?=$item['quantity']?>"
                                    data-cat="<?=$item['category_id']?>"
                                    data-donor="<?=$item['donor_id']?>"
                                    data-date="<?=$item['donation_date']?>"
                                    data-status="<?=h($item['status'])?>"
                                    title="Edit item">
                                <i data-lucide="edit-3" style="width:15px;height:15px;stroke-width:2;"></i>
                            </button>
                            <button type="button" class="btn btn-sm rounded-circle p-2 text-danger"
                                    style="background: #FEE2E2; width: 36px; height: 36px; display: grid; place-items: center;"
                                    onclick="confirmDeleteDonation(<?=$item['donation_id']?>, '<?=h(addslashes($item['item_name']))?>')"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalDeleteDonation"
                                    data-id="<?=$item['donation_id']?>"
                                    data-name="<?=h($item['item_name'])?>"
                                    title="Delete item">
                                <i data-lucide="trash-2" style="width:15px;height:15px;stroke-width:2;"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination Bar -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 mt-4 px-1">
    <small class="text-muted fw-bold">
        Showing <?=min($totalFiltered, $offset + 1)?>-<?=min($totalFiltered, $offset + $perPage)?> of <?=number_format($totalFiltered)?> records
    </small>
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Pagination">
            <ul class="pagination pagination-sm m-0 gap-1">
                <li class="page-item <?=$page <= 1 ? 'disabled' : ''?>">
                    <a class="page-link rounded-pill px-3" href="?<?=http_build_query(array_merge($_GET, ['page' => $page - 1]))?>">Prev</a>
                </li>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?=$p === $page ? 'active' : ''?>">
                        <a class="page-link rounded-circle text-center" style="width: 32px; height: 32px; line-height: 20px;" href="?<?=http_build_query(array_merge($_GET, ['page' => $p]))?>"><?=$p?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?=$page >= $totalPages ? 'disabled' : ''?>">
                    <a class="page-link rounded-pill px-3" href="?<?=http_build_query(array_merge($_GET, ['page' => $page + 1]))?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
