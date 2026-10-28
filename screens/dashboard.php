<?php
declare(strict_types=1);

$lowStockItems = $lowStockItems ?? [];
$recentDonors = $recentDonors ?? [];
$upcomingEvents = $upcomingEvents ?? [];
$batchDonations = $batchDonations ?? [];
$stockLevelPct = $stockLevelPct ?? 68;
$itemsReleasedPct = $itemsReleasedPct ?? 24;
$itemsReceivedPct = $itemsReceivedPct ?? 92;
$netInStockUnits = $netInStockUnits ?? 0;
$totalReleasedUnits = $totalReleasedUnits ?? 0;
$grandTotalHandled = $grandTotalHandled ?? 0;
?>
<!-- Low-Stock Alert Strip (Shown only when low stock items exist) -->
<?php if (!empty($lowStockItems)): ?>
    <div class="mb-4 p-3 rounded-4 d-flex align-items-center justify-content-between shadow-sm" style="background: #FEF3C7; border: 1px solid #FDE68A; color: #92400E;">
        <div class="d-flex align-items-center gap-2">
            <i data-lucide="alert-triangle" style="width:20px;height:20px;stroke-width:2;"></i>
            <span class="fw-bold" style="font-size: 13px;">
                Critical Supply Alert: <?=h($lowStockItems[0]['item_name'])?> has only <?=h($lowStockItems[0]['quantity'])?> units remaining.
            </span>
        </div>
        <a href="?section=donations&filter_status=Pending" class="btn btn-sm rounded-pill fw-bold" style="background: #92400E; color: #ffffff; font-size: 12px; padding: 6px 16px;">Restock</a>
    </div>
<?php endif; ?>

<!-- Main Dashboard Grid (2 Zones: Left Content & Right Plum Panel) -->
<div class="row g-4">
    <!-- Left Zone (Widgets) -->
    <div class="col-xl-8 col-lg-7">
        <!-- Row 1: Recent Donors & Upcoming Events Cards -->
        <div class="row g-4 mb-4">
            <!-- Card 1: Recent Donors -->
            <div class="col-md-6">
                <div class="card-smartech h-100">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="card-smartech-title m-0">Recent Donors</h2>
                            <button type="button" class="btn btn-sm rounded-pill px-3 py-1 fw-bold" style="background: var(--pill-row); font-size: 12px;" data-bs-toggle="modal" data-bs-target="#modalAddDonor">+ New</button>
                        </div>
                        <?php if (empty($recentDonors)): ?>
                            <div class="text-muted small text-center py-4">No donors recorded</div>
                        <?php else: foreach ($recentDonors as $rd): ?>
                            <div class="pill-list-row" onclick="viewDonorDetails('<?=h($rd['contact_person'])?>', '<?=h($rd['contact_number'])?>', '<?=h($rd['address'])?>')">
                                <div class="pill-row-avatar">
                                    <?=strtoupper(substr($rd['contact_person'], 0, 1))?>
                                </div>
                                <div class="pill-row-info">
                                    <div class="pill-row-name"><?=h($rd['contact_person'])?></div>
                                    <div class="pill-row-desc"><?=h($rd['latest_item'])?></div>
                                </div>
                                <div class="pill-row-action" title="View details">
                                    <i data-lucide="message-square" style="width:13px;height:13px;stroke-width:2;"></i>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <a href="?section=donors" class="see-more-link">See more &rsaquo;</a>
                </div>
            </div>

            <!-- Card 2: Upcoming Events (Interactive & Working) -->
            <div class="col-md-6">
                <div class="card-smartech h-100">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2 class="card-smartech-title m-0">Upcoming Events</h2>
                            <button type="button" class="btn btn-sm rounded-pill px-3 py-1 fw-bold" style="background: var(--pill-row); font-size: 12px;" data-bs-toggle="modal" data-bs-target="#modalAddEvent">+ Schedule</button>
                        </div>
                        <div class="upcoming-events-list" style="max-height: 280px; overflow-y: auto; padding-right: 4px;">
                        <?php if (empty($upcomingEvents)): ?>
                            <div class="text-muted small text-center py-4">No scheduled events</div>
                        <?php else: foreach ($upcomingEvents as $ue): ?>
                            <div class="pill-list-row" onclick="openEventModal(<?=$ue['event_id']?>, '<?=h($ue['title'])?>', '<?=$ue['event_date']?>', '<?=h($ue['event_time'])?>', '<?=h($ue['location'])?>', '<?=h($ue['status'])?>')">
                                <div class="pill-row-avatar" style="background: var(--text-dark);">
                                    <i data-lucide="calendar" style="width:15px;height:15px;stroke-width:2;"></i>
                                </div>
                                <div class="pill-row-info">
                                    <div class="pill-row-name"><?=h($ue['title'])?></div>
                                    <div class="pill-row-desc"><?=date('d M Y', strtotime($ue['event_date']))?> &bull; <?=h($ue['event_time'])?> &bull; <?=h($ue['location'])?></div>
                                </div>
                                <div class="pill-row-action" title="Event details">
                                    <i data-lucide="arrow-up-right" style="width:13px;height:13px;stroke-width:2;"></i>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                        </div>
                    </div>
                    <button type="button" class="see-more-link" data-bs-toggle="modal" data-bs-target="#modalAllEvents">See more &rsaquo;</button>
                </div>
            </div>
        </div>

        <!-- Row 2: Distribution Schedule (Interactive Month Calendar + Dynamic Date Filter) -->
        <div class="card-smartech mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="card-smartech-title m-0">Distribution Schedule</h2>
                <button type="button" class="btn btn-sm rounded-pill px-3 py-1 fw-bold" style="background: var(--pill-row); font-size: 12px;" data-bs-toggle="modal" data-bs-target="#modalAddEvent">+ Add Distribution</button>
            </div>
            <div class="schedule-layout">
                <!-- Interactive Calendar Box -->
                <div class="mini-calendar-box">
                    <div class="mini-calendar-header d-flex justify-content-between align-items-center px-1">
                        <button type="button" class="cal-nav-btn" onclick="prevCalMonth()" aria-label="Previous Month">&lsaquo;</button>
                        <span id="calMonthTitle" class="fw-bold">Month Year</span>
                        <button type="button" class="cal-nav-btn" onclick="nextCalMonth()" aria-label="Next Month">&rsaquo;</button>
                    </div>
                    <div class="mini-calendar-grid mb-2">
                        <div class="calendar-day-header">Mon</div>
                        <div class="calendar-day-header">Tue</div>
                        <div class="calendar-day-header">Wed</div>
                        <div class="calendar-day-header">Thu</div>
                        <div class="calendar-day-header">Fri</div>
                        <div class="calendar-day-header">Sat</div>
                        <div class="calendar-day-header">Sun</div>
                    </div>
                    <div class="mini-calendar-grid" id="calDaysGrid">
                        <!-- Populated dynamically by renderCalendar() -->
                    </div>
                </div>

                <!-- Dynamic Schedule Items for Selected Date -->
                <div class="schedule-items-list" id="scheduleListContainer">
                    <!-- Populated dynamically based on calendar selection -->
                </div>
            </div>
        </div>

        <!-- Row 3: Recent Donations (adapted from "My projects") -->
        <div class="card-smartech">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="card-smartech-title m-0">Recent Donations</h2>
                <a href="?section=donations" class="see-more-link m-0">All records &rsaquo;</a>
            </div>
            <div class="batch-grid">
                <?php if (empty($batchDonations)): ?>
                    <div class="text-muted small py-3">No recent supply batches</div>
                <?php else: foreach ($batchDonations as $b): ?>
                    <a href="?section=donations&search=<?=urlencode($b['item_name'])?>" class="batch-card">
                        <div class="batch-icon-sq">
                            <i data-lucide="<?=h($b['icon'] ?: 'package')?>" style="width:22px;height:22px;stroke-width:2;"></i>
                        </div>
                        <div>
                            <div class="batch-label"><?=h($b['item_name'])?></div>
                            <div class="batch-meta"><?=number_format((int)$b['quantity'])?> units &bull; <?=h($b['status'])?></div>
                        </div>
                    </a>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: The Iconic Darker Plum Statistics Panel (#9B7A94) -->
    <div class="col-xl-4 col-lg-5">
        <div class="panel-plum-stats h-100">
            <div>
                <div class="fw-bold mb-3 text-center" style="font-size: 15px; color: #ffffff;">Disaster Response Metrics</div>

                <!-- Ring Card 1: Stock Level -->
                <div class="plum-stat-card">
                    <div class="plum-stat-label">Stock Level</div>
                    <?php
                    $c1 = 2 * 3.14159 * 34; // radius 34 -> circum ~213.6
                    $offset1 = $c1 * (1 - ($stockLevelPct / 100));
                    ?>
                    <svg class="ring-chart-svg" viewBox="0 0 82 82">
                        <circle class="ring-chart-bg" cx="41" cy="41" r="34"></circle>
                        <circle class="ring-chart-stroke" cx="41" cy="41" r="34" stroke="#E57388"
                                stroke-dasharray="<?=$c1?>" stroke-dashoffset="<?=$offset1?>"></circle>
                        <text class="ring-chart-val" x="41" y="41"><?=$stockLevelPct?>%</text>
                    </svg>
                    <div class="small mt-2" style="color: #E8D8E5; font-size: 11px;"><?=number_format($netInStockUnits)?> units verified in depot</div>
                </div>

                <!-- Ring Card 2: Items Released -->
                <div class="plum-stat-card">
                    <div class="plum-stat-label">Items Released</div>
                    <?php
                    $offset2 = $c1 * (1 - ($itemsReleasedPct / 100));
                    ?>
                    <svg class="ring-chart-svg" viewBox="0 0 82 82">
                        <circle class="ring-chart-bg" cx="41" cy="41" r="34"></circle>
                        <circle class="ring-chart-stroke" cx="41" cy="41" r="34" stroke="#5FBDB0"
                                stroke-dasharray="<?=$c1?>" stroke-dashoffset="<?=$offset2?>"></circle>
                        <text class="ring-chart-val" x="41" y="41"><?=$itemsReleasedPct?>%</text>
                    </svg>
                    <div class="small mt-2" style="color: #E8D8E5; font-size: 11px;"><?=number_format($totalReleasedUnits)?> units dispatched to evacuees</div>
                </div>

                <!-- Ring Card 3: Items Received -->
                <div class="plum-stat-card mb-0">
                    <div class="plum-stat-label">Items Received</div>
                    <?php
                    $offset3 = $c1 * (1 - ($itemsReceivedPct / 100));
                    ?>
                    <svg class="ring-chart-svg" viewBox="0 0 82 82">
                        <circle class="ring-chart-bg" cx="41" cy="41" r="34"></circle>
                        <circle class="ring-chart-stroke" cx="41" cy="41" r="34" stroke="#E8B86D"
                                stroke-dasharray="<?=$c1?>" stroke-dashoffset="<?=$offset3?>"></circle>
                        <text class="ring-chart-val" x="41" y="41"><?=$itemsReceivedPct?>%</text>
                    </svg>
                    <div class="small mt-2" style="color: #E8D8E5; font-size: 11px;"><?=number_format($grandTotalHandled)?> total units logged</div>
                </div>
            </div>

            <div class="text-center pt-3">
                <a href="?section=donations" class="plum-see-more">
                    <span>Manage All Inventory</span>
                    <i data-lucide="arrow-right" style="width:14px;height:14px;stroke-width:2.5;"></i>
                </a>
            </div>
        </div>
    </div>
</div>
