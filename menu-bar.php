<?php
$current_page = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'notifications.php'
    ? 'notifications'
    : ((($_GET['view'] ?? 'personal') === 'shared') ? 'shared' : 'personal');
$demoSwitchAccounts = [];
$canQuickSwitch = isDemoSwitchUsername($_SESSION['username'] ?? '');
if ($canQuickSwitch) {
    $demoSwitchAccounts = getDemoSwitchAccounts($pdo);
    $demoSwitchIds = array_map('intval', array_column($demoSwitchAccounts, 'id'));
    $canQuickSwitch = count($demoSwitchAccounts) > 1
        && in_array((int) ($_SESSION['user_id'] ?? 0), $demoSwitchIds, true);
}
if ($canQuickSwitch && empty($_SESSION['account_switch_csrf'])) {
    $_SESSION['account_switch_csrf'] = bin2hex(random_bytes(32));
}
?>
<nav class="sidebar" aria-label="Main navigation">
    <div class="sidebar-header">
        <a class="brand-lockup" href="index.php?view=personal" aria-label="The Iron Citadel home">
            <span class="brand-mark" aria-hidden="true">IC</span>
            <span class="brand-copy">
                <strong class="brand-name">The Iron Citadel</strong>
                <span class="brand-caption">Task workspace</span>
            </span>
        </a>
        <?php if (isset($_SESSION['nama_lengkap'])): ?>
            <p class="sidebar-user" title="Signed in user">
                <?= htmlspecialchars($_SESSION['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>
    </div>

    <ul class="sidebar-menu">
        <li>
            <a href="index.php?view=personal"
               class="sidebar-link <?= $current_page === 'personal' ? 'active' : '' ?>"
               aria-current="<?= $current_page === 'personal' ? 'page' : 'false' ?>">
                <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                </svg>
                <span>Personal</span>
            </a>
        </li>
        <li>
            <a href="index.php?view=shared"
               class="sidebar-link <?= $current_page === 'shared' ? 'active' : '' ?>"
               aria-current="<?= $current_page === 'shared' ? 'page' : 'false' ?>">
                <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>Shared</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <?php if ($canQuickSwitch): ?>
            <details class="account-switcher" id="account-switcher">
                <summary class="account-switch-toggle">
                    <span class="account-switch-icon" aria-hidden="true">&#8644;</span>
                    <span>Quick Switch Account</span>
                </summary>
                <div class="account-switch-panel">
                    <p class="account-switch-title">Quick Switch Account</p>
                    <div class="account-switch-current">
                        <span>Current Account</span>
                        <strong><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                        <small>@<?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                    <form method="POST" action="switch-account.php" id="quick-switch-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['account_switch_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                        <label for="quick-switch-account">Pilih akun demo</label>
                        <select id="quick-switch-account" name="account_username" data-current-username="<?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                            <?php foreach ($demoSwitchAccounts as $account): ?>
                                <option value="<?= htmlspecialchars($account['username'], ENT_QUOTES, 'UTF-8') ?>" <?= $account['username'] === ($_SESSION['username'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars($account['nama_lengkap'], ENT_QUOTES, 'UTF-8') ?><?= $account['username'] === ($_SESSION['username'] ?? '') ? ' — Current Account' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="account-switch-submit">Switch Account</button>
                    </form>
                    <p class="account-switch-note">Akses demonstrasi khusus untuk akun demo.</p>
                </div>
            </details>
        <?php endif; ?>
        <a href="logout.php" class="sidebar-link sidebar-logout">
            <svg class="sidebar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>Logout</span>
        </a>
    </div>
</nav>
<script>
(function () {
    var switcher = document.getElementById('account-switcher');
    var switchForm = document.getElementById('quick-switch-form');
    if ('BroadcastChannel' in window) {
        var accountChannel = new BroadcastChannel('iron-citadel-account-switch');
        accountChannel.addEventListener('message', function (event) {
            if (event.data && event.data.type === 'account-switched') window.location.reload();
        });
        if (document.body && document.body.dataset.accountSwitched === 'true') {
            accountChannel.postMessage({ type: 'account-switched' });
        }
    }
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) window.location.reload();
    });    document.addEventListener('click', function (event) {
        if (switcher && switcher.open && !switcher.contains(event.target)) switcher.open = false;
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && switcher && switcher.open) {
            switcher.open = false;
            var summary = switcher.querySelector('summary');
            if (summary) summary.focus();
        }
    });
    if (switchForm) {
        switchForm.addEventListener('submit', function (event) {
            if (typeof window.prepareAccountSwitch === 'function' && !window.prepareAccountSwitch()) {
                event.preventDefault();
                return;
            }
            var select = switchForm.querySelector('select[name="account_username"]');
            if (!select || select.value === select.dataset.currentUsername) {
                event.preventDefault();
                return;
            }
            var accountName = select.options[select.selectedIndex].text.replace(' — Current Account', '');
            if (!window.confirm('Beralih ke akun ' + accountName + '?')) {
                event.preventDefault();
                return;
            }
            var button = switchForm.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.textContent = 'Switching...';
            }
            if (switcher) switcher.open = false;
        });
    }
})();
</script>