<?php
declare(strict_types=1);

if (empty($mCats) && isset($conn)) {
    $mCats = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);
}
if (empty($mDonors) && isset($conn)) {
    $mDonors = $conn->query("SELECT donor_id, contact_person FROM donors ORDER BY contact_person")->fetchAll(PDO::FETCH_ASSOC);
}
$allEvents = $allEvents ?? [];
?>
<!-- ============================================================
     GLOBAL MODALS (Events, Donations, Categories, Donors)
============================================================ -->

<!-- Modal: Schedule Distribution Event -->
<div class="modal fade" id="modalAddEvent" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0; box-shadow: var(--shadow-soft);">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="event_create">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="color: var(--text-dark);">Schedule Relief Distribution</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label" for="ev_title">Event Title / Mission</label>
                        <input class="form-control" type="text" id="ev_title" name="title" placeholder="e.g. Barangay San Jose Relief Mission" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="ev_date">Date</label>
                            <input class="form-control" type="date" id="ev_date" name="event_date" value="<?=date('Y-m-d')?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="ev_time">Time</label>
                            <input class="form-control" type="time" id="ev_time" name="event_time" value="09:00" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="ev_loc">Evacuation Site / Location</label>
                        <input class="form-control" type="text" id="ev_loc" name="location" placeholder="e.g. North Covered Gym / Sector 4 Depot" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" for="ev_stat">Status</label>
                        <select class="form-control" id="ev_stat" name="status">
                            <option value="Scheduled" selected>Scheduled</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-plum-action">Save to Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: View Event Details -->
<div class="modal fade" id="modalViewEvent" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0; box-shadow: var(--shadow-soft);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="vEvTitle" style="color: var(--text-dark);">Event Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <div class="p-3 rounded-4 mb-3" style="background: #ffffff;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge rounded-pill" id="vEvStatusBadge" style="background: var(--panel-plum); color: #fff;">Status</span>
                        <small class="text-muted" id="vEvDateTime"></small>
                    </div>
                    <div class="fw-bold mb-1" id="vEvLocation" style="color: var(--text-dark);"></div>
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <form method="post" action="index.php" id="formCompleteEvent" class="d-inline">
                        <input type="hidden" name="action" value="event_status_update">
                        <input type="hidden" name="event_id" id="vEvIdComplete">
                        <input type="hidden" name="status" value="Completed">
                        <button type="submit" class="btn btn-sm rounded-pill px-3 fw-bold" style="background: #DCFCE7; color: #166534;">Mark Completed</button>
                    </form>

                    <form method="post" action="index.php" id="formDeleteEvent" class="d-inline">
                        <input type="hidden" name="action" value="event_delete">
                        <input type="hidden" name="event_id" id="vEvIdDelete">
                        <button type="submit" class="btn btn-sm rounded-pill px-3 fw-bold text-danger" style="background: #FEE2E2;">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: All Relief Events List -->
<div class="modal fade" id="modalAllEvents" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0; box-shadow: var(--shadow-soft);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" style="color: var(--text-dark);">All Relief Missions & Events</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3" style="max-height: 480px; overflow-y: auto;">
                <?php if (empty($allEvents)): ?>
                    <div class="text-center py-4 text-muted">No events currently scheduled.</div>
                <?php else: foreach ($allEvents as $ev): ?>
                    <div class="pill-list-row mb-2">
                        <div class="pill-row-avatar">
                            <i data-lucide="calendar" style="width:16px;height:16px;stroke-width:2;"></i>
                        </div>
                        <div class="pill-row-info">
                            <div class="pill-row-name"><?=h($ev['title'])?></div>
                            <div class="pill-row-desc"><?=date('D, d M Y', strtotime($ev['event_date']))?> &bull; <?=h($ev['event_time'])?> &bull; <?=h($ev['location'])?></div>
                        </div>
                        <span class="badge rounded-pill me-2" style="background: <?=($ev['status'] === 'Completed') ? '#DCFCE7' : 'var(--panel-plum)'?>; color: <?=($ev['status'] === 'Completed') ? '#166534' : '#ffffff'?>;">
                            <?=h($ev['status'])?>
                        </span>
                        <form method="post" action="index.php" class="d-inline">
                            <input type="hidden" name="action" value="event_delete">
                            <input type="hidden" name="event_id" value="<?=$ev['event_id']?>">
                            <button type="submit" class="btn btn-sm text-danger border-0 bg-transparent" title="Delete event">
                                <i data-lucide="trash-2" style="width:16px;height:16px;stroke-width:2;"></i>
                            </button>
                        </form>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn-plum-action" data-bs-toggle="modal" data-bs-target="#modalAddEvent">+ Schedule New Event</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Donor Quick Details -->
<div class="modal fade" id="modalDonorQuick" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0; box-shadow: var(--shadow-soft);">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="dqName" style="color: var(--text-dark);">Donor Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <div class="p-3 rounded-4 mb-3" style="background: #ffffff;">
                    <div class="small text-muted mb-1">Phone Contact</div>
                    <div class="fw-bold mb-2" id="dqPhone"></div>
                    <div class="small text-muted mb-1">Depot / Station Address</div>
                    <div class="fw-bold" id="dqAddr"></div>
                </div>
                <div class="text-end">
                    <a href="#" id="dqTelBtn" class="btn-plum-action text-decoration-none">
                        <i data-lucide="phone" style="width:16px;height:16px;stroke-width:2;"></i>
                        <span>Call Contributor</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Donation -->
<div class="modal fade" id="modalAddDonation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0; box-shadow: var(--shadow-soft);">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="donation_create">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="color: var(--text-dark);">Record Relief Supply</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label" for="ad_item">Supply / Item Name</label>
                        <input class="form-control" type="text" id="ad_item" name="item_name" placeholder="e.g. Bottled Drinking Water 500ml" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="ad_cat">Category</label>
                            <select class="form-control" id="ad_cat" name="category_id" required>
                                <?php foreach ($mCats as $mc): ?>
                                    <option value="<?=$mc['category_id']?>"><?=h($mc['category_name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="ad_status">Status</label>
                            <select class="form-control" id="ad_status" name="status" required>
                                <option value="Received" selected>Received</option>
                                <option value="In Stock">In Stock</option>
                                <option value="Pending">Pending</option>
                                <option value="Released">Released</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="ad_donor">Donor Contributor</label>
                        <select class="form-control" id="ad_donor" name="donor_id" required>
                            <?php foreach ($mDonors as $md): ?>
                                <option value="<?=$md['donor_id']?>"><?=h($md['contact_person'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="ad_qty">Quantity (Units / Packs)</label>
                            <input class="form-control" type="number" id="ad_qty" name="quantity" value="10" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="ad_date">Date Received</label>
                            <input class="form-control" type="date" id="ad_date" name="donation_date" value="<?=date('Y-m-d')?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-plum-action">Save Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Donation -->
<div class="modal fade" id="modalEditDonation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0; box-shadow: var(--shadow-soft);">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="donation_edit">
                <input type="hidden" name="donation_id" id="edit_don_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="color: var(--text-dark);">Edit Relief Supply</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label" for="edit_don_name">Item Name</label>
                        <input class="form-control" type="text" id="edit_don_name" name="item_name" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="edit_don_cat">Category</label>
                            <select class="form-control" id="edit_don_cat" name="category_id" required>
                                <?php foreach ($mCats as $mc): ?>
                                    <option value="<?=$mc['category_id']?>"><?=h($mc['category_name'])?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="edit_don_status">Status</label>
                            <select class="form-control" id="edit_don_status" name="status" required>
                                <option value="Pending">Pending</option>
                                <option value="Received">Received</option>
                                <option value="In Stock">In Stock</option>
                                <option value="Released">Released</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="edit_don_donor">Donor Contributor</label>
                        <select class="form-control" id="edit_don_donor" name="donor_id" required>
                            <?php foreach ($mDonors as $md): ?>
                                <option value="<?=$md['donor_id']?>"><?=h($md['contact_person'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="edit_don_qty">Quantity</label>
                            <input class="form-control" type="number" id="edit_don_qty" name="quantity" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="edit_don_date">Date</label>
                            <input class="form-control" type="date" id="edit_don_date" name="donation_date" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-plum-action">Update Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Donation -->
<div class="modal fade" id="modalDeleteDonation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0; box-shadow: var(--shadow-soft);">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="donation_delete">
                <input type="hidden" name="donation_id" id="del_don_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="mb-1">Are you sure you want to delete this donation record?</p>
                    <div class="fw-bold fs-5 text-dark" id="del_don_name"></div>
                    <div class="alert alert-warning py-2 small rounded-pill px-3 mt-3 mb-0">
                        This action will update stock ledger balances.
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4">Delete Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Category -->
<div class="modal fade" id="modalAddCategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0;">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="category_create">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Add Supply Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label" for="ac_name">Category Name</label>
                        <input class="form-control" type="text" id="ac_name" name="category_name" placeholder="e.g. Baby Care Products" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="ac_icon">Line Icon</label>
                            <select class="form-control" id="ac_icon" name="icon">
                                <option value="utensils">Food (Utensils)</option>
                                <option value="droplets">Water (Droplets)</option>
                                <option value="pill">Medicine (Pill)</option>
                                <option value="shirt">Clothing (Shirt)</option>
                                <option value="sparkles">Hygiene (Sparkles)</option>
                                <option value="package" selected>General (Package)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="ac_col">Color Swatch</label>
                            <input class="form-control p-1" type="color" id="ac_col" name="color" value="#0284c7">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ac_desc">Description</label>
                        <textarea class="form-control" id="ac_desc" name="description" rows="2" style="border-radius: 16px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-plum-action">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Category -->
<div class="modal fade" id="modalEditCategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0;">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="category_edit">
                <input type="hidden" name="category_id" id="ec_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label" for="ec_name">Category Name</label>
                        <input class="form-control" type="text" id="ec_name" name="category_name" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="ec_icon">Line Icon</label>
                            <input class="form-control" type="text" id="ec_icon" name="icon" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="ec_color">Color</label>
                            <input class="form-control p-1" type="color" id="ec_color" name="color" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="ec_desc">Description</label>
                        <textarea class="form-control" id="ec_desc" name="description" rows="2" style="border-radius: 16px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-plum-action">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Category -->
<div class="modal fade" id="modalDeleteCategory" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0;">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="category_delete">
                <input type="hidden" name="category_id" id="del_cat_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger">Delete Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p>Delete category <strong id="del_cat_name"></strong>?</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Donor -->
<div class="modal fade" id="modalAddDonor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0;">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="donor_create">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Register Donor Partner</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label" for="adn_person">Contact Person / Organization</label>
                        <input class="form-control" type="text" id="adn_person" name="contact_person" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="adn_phone">Phone Contact</label>
                        <input class="form-control" type="text" id="adn_phone" name="contact_number" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="adn_addr">Address / Depot Location</label>
                        <input class="form-control" type="text" id="adn_addr" name="address" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-plum-action">Save Contributor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Donor -->
<div class="modal fade" id="modalEditDonor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0;">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="donor_edit">
                <input type="hidden" name="donor_id" id="edn_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Edit Donor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label class="form-label" for="edn_person">Contact Person</label>
                        <input class="form-control" type="text" id="edn_person" name="contact_person" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edn_phone">Contact Phone</label>
                        <input class="form-control" type="text" id="edn_phone" name="contact_number" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edn_addr">Address</label>
                        <input class="form-control" type="text" id="edn_addr" name="address" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-plum-action">Update Contributor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete Donor -->
<div class="modal fade" id="modalDeleteDonor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--container-cream); border-radius: 24px; border: 0;">
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="donor_delete">
                <input type="hidden" name="donor_id" id="del_dn_id">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger">Delete Donor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p>Remove donor <strong id="del_dn_name"></strong>?</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>
