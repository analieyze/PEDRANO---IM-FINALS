<?php
declare(strict_types=1);

$currentUsername = (string)($_SESSION['username'] ?? 'Sophia');
$section = $section ?? 'dashboard';
?>
<!-- Left Sidebar Panel -->
<aside class="sidebar-panel">
    <div class="brand-section">
        <div class="brand-logo-icon">
            <i data-lucide="package-search" style="width:22px;height:22px;stroke-width:2;"></i>
        </div>
        <div class="brand-text">ReliefFlow</div>
    </div>

    <div class="profile-badge">
        <div class="profile-avatar-circle">
            <?=strtoupper(substr($currentUsername, 0, 1))?>
        </div>
        <div>
            <div class="profile-name"><?=h($currentUsername)?></div>
            <div class="profile-role">Field Coordinator</div>
        </div>
    </div>

    <nav class="nav-vertical">
        <a href="?section=dashboard" class="nav-item-pill <?=($section === 'dashboard') ? 'active' : ''?>">
            <i data-lucide="layout-grid" style="width:18px;height:18px;stroke-width:2;"></i>
            <span>Dashboard</span>
        </a>
        <a href="?section=donations" class="nav-item-pill <?=($section === 'donations') ? 'active' : ''?>">
            <i data-lucide="package" style="width:18px;height:18px;stroke-width:2;"></i>
            <span>Donations</span>
        </a>
        <a href="?section=categories" class="nav-item-pill <?=($section === 'categories') ? 'active' : ''?>">
            <i data-lucide="tags" style="width:18px;height:18px;stroke-width:2;"></i>
            <span>Categories</span>
        </a>
        <a href="?section=donors" class="nav-item-pill <?=($section === 'donors') ? 'active' : ''?>">
            <i data-lucide="handshake" style="width:18px;height:18px;stroke-width:2;"></i>
            <span>Donors</span>
        </a>
        <a href="?section=notifications" class="nav-item-pill <?=($section === 'notifications') ? 'active' : ''?>">
            <i data-lucide="bell" style="width:18px;height:18px;stroke-width:2;"></i>
            <span>Notifications</span>
        </a>
        <a href="?section=activityLogs" class="nav-item-pill <?=($section === 'activityLogs' || $section === 'activitylogs') ? 'active' : ''?>">
            <i data-lucide="clipboard-list" style="width:18px;height:18px;stroke-width:2;"></i>
            <span>Activity Logs</span>
        </a>
        <a href="?section=profile" class="nav-item-pill <?=($section === 'profile') ? 'active' : ''?>">
            <i data-lucide="user" style="width:18px;height:18px;stroke-width:2;"></i>
            <span>Profile</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="?logout=1" class="logout-link">
            <i data-lucide="log-out" style="width:18px;height:18px;stroke-width:2;"></i>
            <span>Log out</span>
        </a>
    </div>
</aside>
