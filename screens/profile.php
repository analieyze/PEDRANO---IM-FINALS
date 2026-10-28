<?php
declare(strict_types=1);

$currentUserId = (int)$_SESSION['user_id'];
$usrStmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$usrStmt->execute([$currentUserId]);
$userData = $usrStmt->fetch(PDO::FETCH_ASSOC);
$currentUsername = (string)($_SESSION['username'] ?? 'Sophia');
?>
<div class="mb-4">
    <h2 class="h3 fw-bold m-0" style="color: var(--text-dark);">Staff Profile & Security</h2>
    <div style="font-size: 14px; color: var(--text-muted);">Manage your user credentials, disaster contact email, and password.</div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card-smartech">
            <h3 class="h5 fw-bold mb-3" style="color: var(--text-dark);">Profile Information</h3>
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="profile_update">

                <div class="mb-3">
                    <label class="form-label" for="p_user">Username</label>
                    <input class="form-control" type="text" id="p_user" name="username" value="<?=h($userData['username'] ?? '')?>" required>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="p_email">Work Email</label>
                    <input class="form-control" type="email" id="p_email" name="email" value="<?=h($userData['email'] ?? '')?>" required>
                </div>

                <button type="submit" class="btn-plum-action">
                    Save Profile Changes
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-smartech">
            <h3 class="h5 fw-bold mb-3" style="color: var(--text-dark);">Change Password</h3>
            <form method="post" action="index.php">
                <input type="hidden" name="action" value="password_change">

                <div class="mb-3">
                    <label class="form-label" for="cur_pass">Current Password</label>
                    <input class="form-control" type="password" id="cur_pass" name="current_password" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="new_pass">New Password (min 6 characters)</label>
                    <input class="form-control" type="password" id="new_pass" name="new_password" minlength="6" required>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="conf_pass">Confirm New Password</label>
                    <input class="form-control" type="password" id="conf_pass" name="confirm_password" minlength="6" required>
                </div>

                <button type="submit" class="btn-plum-action">
                    Update Password
                </button>
            </form>
        </div>
    </div>

    <!-- Account Session & Sign Out Card (Accessible on All Devices & Mobile) -->
    <div class="col-12">
        <div class="card-smartech d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3">
            <div>
                <h3 class="h6 fw-bold m-0 text-danger">Sign Out of Session</h3>
                <div class="small text-muted">Currently active session for <strong><?=h($userData['username'] ?? $currentUsername)?></strong> (<?=h($userData['email'] ?? '')?>).</div>
            </div>
            <a href="?logout=1" class="btn btn-outline-danger rounded-pill px-4 py-2 fw-bold d-inline-flex align-items-center gap-2">
                <i data-lucide="log-out" style="width:16px;height:16px;stroke-width:2;"></i>
                <span>Log out</span>
            </a>
        </div>
    </div>
</div>
