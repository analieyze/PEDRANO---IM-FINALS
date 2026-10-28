<?php
declare(strict_types=1);

$filterAct = trim((string)($_GET['filter_action'] ?? ''));
$logSql = "SELECT al.*, u.username, d.item_name
           FROM activitylogs al
           LEFT JOIN users u ON u.user_id = al.user_id
           LEFT JOIN donations d ON d.donation_id = al.donation_id";
$logParams = [];
if ($filterAct !== '') {
    if ($filterAct === 'STATUS_CHANGE') {
        $logSql .= " WHERE al.action LIKE 'STATUS_CHANGE%'";
    } else {
        $logSql .= " WHERE al.action = ?";
        $logParams[] = $filterAct;
    }
}
$logSql .= " ORDER BY al.log_id DESC LIMIT 50";
try {
    $lstmt = $conn->prepare($logSql);
    $lstmt->execute($logParams);
    $logsList = $lstmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $logsList = [];
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 fw-bold m-0" style="color: var(--text-dark);">Activity Logs</h2>
        <div style="font-size: 14px; color: var(--text-muted);">Audited timeline tracking volunteer operations and inventory changes.</div>
    </div>
    <form method="get" action="index.php" class="d-flex gap-2">
        <input type="hidden" name="section" value="activityLogs">
        <select name="filter_action" class="form-control" onchange="this.form.submit()" style="border-radius: var(--radius-pill); font-size: 13px;">
            <option value="">All Actions</option>
            <option value="CREATED_DONATION" <?=$filterAct === 'CREATED_DONATION' ? 'selected' : ''?>>Donations Created</option>
            <option value="UPDATED_DONATION" <?=$filterAct === 'UPDATED_DONATION' ? 'selected' : ''?>>Donations Updated</option>
            <option value="STATUS_CHANGE" <?=$filterAct === 'STATUS_CHANGE' ? 'selected' : ''?>>Status Updates</option>
            <option value="DELETED_DONATION" <?=$filterAct === 'DELETED_DONATION' ? 'selected' : ''?>>Donations Deleted</option>
            <option value="SCHEDULED_EVENT" <?=$filterAct === 'SCHEDULED_EVENT' ? 'selected' : ''?>>Events Scheduled</option>
            <option value="USER_LOGIN" <?=$filterAct === 'USER_LOGIN' ? 'selected' : ''?>>Staff Logins</option>
        </select>
    </form>
</div>

<div class="table-responsive">
    <table class="table-smartech">
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>Staff Member</th>
                <th>Action</th>
                <th>Target Supply</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logsList)): ?>
                <tr><td colspan="5" class="text-center text-muted py-5">No audit logs found.</td></tr>
            <?php else: foreach ($logsList as $lg): ?>
                <?php
                $act = (string)($lg['action'] ?? '');
                $actLabel = $act;
                $actBadgeStyle = 'background: rgba(155, 122, 148, 0.15); color: var(--panel-plum);';
                if (strpos($act, 'STATUS_CHANGE_TO_') === 0) {
                    $newSt = str_replace('STATUS_CHANGE_TO_', '', $act);
                    $actLabel = 'Status: ' . $newSt;
                    $actBadgeStyle = 'background: #E0F2FE; color: #0369A1;';
                } elseif ($act === 'CREATED_DONATION') {
                    $actLabel = 'Donation Created';
                    $actBadgeStyle = 'background: #DCFCE7; color: #166534;';
                } elseif ($act === 'UPDATED_DONATION') {
                    $actLabel = 'Donation Updated';
                    $actBadgeStyle = 'background: #FEF3C7; color: #92400E;';
                } elseif ($act === 'DELETED_DONATION') {
                    $actLabel = 'Donation Deleted';
                    $actBadgeStyle = 'background: #FEE2E2; color: #991B1B;';
                } elseif ($act === 'USER_LOGIN') {
                    $actLabel = 'Staff Login';
                    $actBadgeStyle = 'background: #F3E8FF; color: #6B21A8;';
                } elseif ($act === 'SCHEDULED_EVENT') {
                    $actLabel = 'Event Scheduled';
                    $actBadgeStyle = 'background: #EDE9FE; color: #5B21B6;';
                }

                // Determine Target Supply column content
                $target = '—';
                if (!empty($lg['item_name'])) {
                    $target = $lg['item_name'];
                } elseif (!empty($lg['donation_id'])) {
                    $target = '#DON-' . $lg['donation_id'];
                } elseif ($act === 'USER_LOGIN') {
                    $target = 'Login Session';
                } elseif (strpos($act, 'CATEGORY') !== false) {
                    $target = 'Category Setting';
                } elseif (strpos($act, 'DONOR') !== false) {
                    $target = 'Donor Contributor';
                } elseif (strpos($act, 'EVENT') !== false) {
                    $target = 'Relief Event';
                } elseif (strpos($act, 'PROFILE') !== false || strpos($act, 'PASSWORD') !== false) {
                    $target = 'User Account';
                }
                ?>
                <tr>
                    <td class="small text-muted"><?=date('M j, Y, g:i A', strtotime($lg['timestamp']))?></td>
                    <td>
                        <span class="badge rounded-pill" style="background: #EBE0DB; color: var(--text-dark); font-weight: 800;">
                            <?=h($lg['username'] ?? 'Staff')?>
                        </span>
                    </td>
                    <td>
                        <span class="badge rounded-pill" style="<?=$actBadgeStyle?> font-weight: 800;">
                            <?=h($actLabel)?>
                        </span>
                    </td>
                    <td><?=h($target)?></td>
                    <td><span class="badge-status-pill badge-status-received"><i data-lucide="check" style="width:12px;height:12px;stroke-width:2.5;"></i> Success</span></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
