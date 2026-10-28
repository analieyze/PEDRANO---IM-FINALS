<?php
declare(strict_types=1);

$currentUsername = (string)($_SESSION['username'] ?? 'Sophia');
$unreadNotesCount = $unreadNotesCount ?? 0;
$recentNotifications = $recentNotifications ?? [];
$flash = $flash ?? '';
$flashType = $flashType ?? 'success';
?>
<!-- Top Bar with Smartech Heading & Controls -->
<header class="topbar-header">
    <h1 class="greeting-heading">HELLO, <?=strtoupper(h($currentUsername))?>!</h1>

    <div class="topbar-actions">
        <form method="get" action="index.php" class="pill-search m-0">
            <input type="hidden" name="section" value="donations">
            <i data-lucide="search" style="width:16px;height:16px;stroke-width:2;"></i>
            <input type="text" name="search" placeholder="Search supplies, donors..." value="<?=h($_GET['search'] ?? '')?>">
        </form>

        <!-- Fullscreen Toggle Button -->
        <button class="circle-action-btn" type="button" onclick="toggleFullScreen()" title="Toggle Fullscreen" aria-label="Toggle Fullscreen">
            <i data-lucide="maximize-2" id="fsIcon" style="width:18px;height:18px;stroke-width:2;"></i>
        </button>

        <!-- Notifications Dropdown Trigger -->
        <div class="dropdown">
            <button class="circle-action-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                <i data-lucide="bell" style="width:18px;height:18px;stroke-width:2;"></i>
                <?php if ($unreadNotesCount > 0): ?>
                    <span class="unread-dot"></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0" style="background: var(--container-cream); border-radius: 20px; width: 320px; overflow: hidden;">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold" style="font-size: 14px;">Notifications</span>
                    <?php if ($unreadNotesCount > 0): ?>
                        <a href="?section=notifications&action=notification_read_all" class="text-decoration-none small" style="color: var(--panel-plum); font-weight: 700;">Mark all read</a>
                    <?php endif; ?>
                </div>
                <div class="p-2" style="max-height: 250px; overflow-y: auto;">
                    <?php if (empty($recentNotifications)): ?>
                        <div class="text-center text-muted py-3 small">No notifications</div>
                    <?php else: foreach ($recentNotifications as $rn): ?>
                        <div class="p-2 rounded-3 mb-1" style="background: #ffffff; font-size: 12px;">
                            <div class="fw-bold" style="color: var(--text-dark);"><?=h($rn['message'])?></div>
                            <div class="text-muted" style="font-size: 10px;"><?=date('M j, Y', strtotime($rn['event_date']))?></div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
                <div class="p-2 text-center border-top">
                    <a href="?section=notifications" class="text-decoration-none small fw-bold" style="color: var(--panel-plum);">View All Notifications &rarr;</a>
                </div>
            </div>
        </div>

        <!-- Direct Mobile Logout Button -->
        <a href="?logout=1" class="circle-action-btn d-inline-flex d-md-none text-danger" title="Log out" aria-label="Log out" style="color: #dc2626;">
            <i data-lucide="log-out" style="width:18px;height:18px;stroke-width:2;"></i>
        </a>

        <!-- Kebab Menu -->
        <div class="dropdown">
            <button class="circle-action-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Quick Actions">
                <i data-lucide="more-vertical" style="width:18px;height:18px;stroke-width:2;"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" style="background: var(--container-cream); border-radius: 16px; min-width: 200px;">
                <li><a class="dropdown-item rounded-pill py-2" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAddDonation">Record Donation</a></li>
                <li><a class="dropdown-item rounded-pill py-2" href="javascript:void(0)" data-bs-toggle="modal" data-bs-target="#modalAddEvent">Schedule Distribution</a></li>
                <li><a class="dropdown-item rounded-pill py-2" href="?section=categories">Manage Categories</a></li>
                <li><a class="dropdown-item rounded-pill py-2" href="?section=activityLogs">Activity Logs</a></li>
                <li><a class="dropdown-item rounded-pill py-2" href="?section=profile">My Profile</a></li>
                <li><hr class="dropdown-divider my-1" style="border-color: rgba(155, 122, 148, 0.2);"></li>
                <li>
                    <a class="dropdown-item rounded-pill py-2 text-danger fw-bold d-flex align-items-center gap-2" href="?logout=1">
                        <i data-lucide="log-out" style="width:16px;height:16px;stroke-width:2;"></i>
                        <span>Log out</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>

<!-- Dismissible Floating Banner -->
<?php if ($flash): ?>
    <div class="alert alert-<?=$flashType?> alert-dismissible fade show rounded-pill px-4 shadow-sm mb-4 d-flex align-items-center gap-2" role="alert">
        <i data-lucide="info" style="width:18px;height:18px;stroke-width:2;"></i>
        <div class="flex-grow-1 fw-bold" style="font-size: 13px;"><?=h($flash)?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
