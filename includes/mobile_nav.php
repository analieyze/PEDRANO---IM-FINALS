<?php
declare(strict_types=1);

$section = $section ?? 'dashboard';
?>
<!-- Mobile Bottom Tabs (< 768px) -->
<nav class="mobile-bottom-tabs" aria-label="Mobile Navigation">
    <a href="?section=dashboard" class="mobile-tab-btn <?=$section === 'dashboard' ? 'active' : ''?>">
        <i data-lucide="layout-grid" style="width:20px;height:20px;stroke-width:2;"></i>
        <span>Dashboard</span>
    </a>
    <a href="?section=donations" class="mobile-tab-btn <?=$section === 'donations' ? 'active' : ''?>">
        <i data-lucide="package" style="width:20px;height:20px;stroke-width:2;"></i>
        <span>Donations</span>
    </a>
    <a href="javascript:void(0)" class="mobile-tab-btn" data-bs-toggle="modal" data-bs-target="#modalAddDonation" style="color: var(--panel-plum);">
        <span class="badge rounded-circle p-2" style="background: var(--panel-plum); color: #fff; margin-top: -14px;">
            <i data-lucide="plus" style="width:22px;height:22px;stroke-width:2.5;"></i>
        </span>
    </a>
    <a href="?section=categories" class="mobile-tab-btn <?=$section === 'categories' ? 'active' : ''?>">
        <i data-lucide="tags" style="width:20px;height:20px;stroke-width:2;"></i>
        <span>Categories</span>
    </a>
    <a href="?section=profile" class="mobile-tab-btn <?=$section === 'profile' ? 'active' : ''?>">
        <i data-lucide="user" style="width:20px;height:20px;stroke-width:2;"></i>
        <span>Profile</span>
    </a>
</nav>
