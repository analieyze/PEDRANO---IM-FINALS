<?php
declare(strict_types=1);

$allNotes = $conn->query("SELECT * FROM notifications ORDER BY notification_id DESC")->fetchAll(PDO::FETCH_ASSOC);
$unreadNotesCount = $unreadNotesCount ?? 0;
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 fw-bold m-0" style="color: var(--text-dark);">System Notifications</h2>
        <div style="font-size: 14px; color: var(--text-muted);">Event reminders, dispatch orders, and supply movements.</div>
    </div>
    <?php if ($unreadNotesCount > 0): ?>
        <a href="?section=notifications&action=notification_read_all" class="btn-plum-action">
            <i data-lucide="check-check" style="width:16px;height:16px;stroke-width:2;"></i>
            <span>Mark All Read</span>
        </a>
    <?php endif; ?>
</div>

<div class="card-smartech p-0 overflow-hidden">
    <table class="table-smartech mb-0">
        <thead>
            <tr>
                <th>Date</th>
                <th>Message</th>
                <th>Target Supply</th>
                <th>Status</th>
                <th class="text-end">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($allNotes)): ?>
                <tr><td colspan="5" class="text-center text-muted py-5">No notifications present.</td></tr>
            <?php else: foreach ($allNotes as $n): ?>
                <tr>
                    <td class="small text-muted"><?=date('M j, Y', strtotime($n['event_date']))?></td>
                    <td class="fw-bold"><?=h($n['message'])?></td>
                    <td>
                        <?php if ($n['donation_id']): ?>
                            <a href="?section=donations&search=<?=urlencode((string)$n['donation_id'])?>" class="text-decoration-none fw-bold" style="color: var(--panel-plum);">
                                #DON-<?=str_pad((string)$n['donation_id'], 4, '0', STR_PAD_LEFT)?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">General</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$n['is_read']): ?>
                            <span class="badge rounded-pill" style="background: var(--panel-plum); color: #fff; font-size: 12px;">Unread</span>
                        <?php else: ?>
                            <span class="badge rounded-pill" style="background: #EBE0DB; color: var(--text-muted); font-size: 12px;">Read</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if (!$n['is_read']): ?>
                            <form method="post" action="index.php" class="d-inline">
                                <input type="hidden" name="action" value="notification_read">
                                <input type="hidden" name="notification_id" value="<?=$n['notification_id']?>">
                                <button type="submit" class="btn btn-sm rounded-pill fw-bold px-3 py-1" style="background: #ffffff; color: var(--text-dark);">Mark Read</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
