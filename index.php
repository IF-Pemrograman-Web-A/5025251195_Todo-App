<?php
date_default_timezone_set('Asia/Jakarta');
require_once 'config.php';
require_once 'auth.php';
requireLogin();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$accountSwitched = !empty($_SESSION['account_switched']);
unset($_SESSION['account_switched']);
$editRestore = $_SESSION['edit_restore'] ?? null;
$editFormError = $_SESSION['edit_form_error'] ?? '';
unset($_SESSION['edit_restore'], $_SESSION['edit_form_error']);

$user = getCurrentUser($pdo);
$view = $_GET['view'] ?? 'personal';
$displayName = $_SESSION['nama_lengkap'] ?? 'Pengguna';
if (empty($_SESSION['notification_csrf'])) {
    $_SESSION['notification_csrf'] = bin2hex(random_bytes(32));
}
$notificationCsrf = $_SESSION['notification_csrf'];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Iron Citadel - Task Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
</head>

<body data-account-switched="<?= $accountSwitched ? 'true' : 'false' ?>">
    <div class="app-layout">
        <?php include 'menu-bar.php'; ?>

        <div class="main-container">
            <header class="topbar">
                <div class="topbar-branding">
                    <p class="eyebrow">THE IRON CITADEL</p>
                    <h1>Task management workspace</h1>
                </div>
                <div class="topbar-actions">
                    <div class="topbar-user">
                        <span class="user-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($displayName, 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                        <span><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <button type="button" id="tombol-mode" class="mode-button" aria-label="Ubah mode tampilan">Dark Mode</button>
                </div>
            </header>

            <p id="status-aplikasi" role="status" aria-live="polite" aria-atomic="true"></p>
            <div class="notification-controls">
                <button type="button" id="tombol-izin-notifikasi">Aktifkan Notifikasi</button>
                <a href="notifications.php">Riwayat Notifications</a>
            </div>

            <div class="workspace-grid">
                <?php include 'content.php'; ?>
                <?php include 'form-todo.php'; ?>
            </div>
        </div>
    </div>

    <footer class="app-footer">
        <p>&copy; 2026 Pemrograman Web A</p>
    </footer>
    <script>
    window.NOTIFICATION_CSRF_TOKEN = <?= json_encode($notificationCsrf) ?>;
    </script>

    <script>
    // ============================================
    // 1. STATUS APLIKASI & DARK MODE
    // ============================================
    const statusAplikasi = document.getElementById('status-aplikasi');
    const tombolMode = document.getElementById('tombol-mode');

    function setStatus(pesan) {
        if (statusAplikasi) {
            statusAplikasi.textContent = pesan;
        }
    }

    function updateModeButton() {
        if (document.body.classList.contains('dark-mode')) {
            tombolMode.textContent = 'Light Mode';
        } else {
            tombolMode.textContent = 'Dark Mode';
        }
    }

    let modeTersimpan = null;
    try {
        modeTersimpan = localStorage.getItem('todoMode');
    } catch (e) {}

    if (modeTersimpan === 'dark') {
        document.body.classList.add('dark-mode');
    }
    updateModeButton();

    tombolMode.addEventListener('click', function() {
        document.body.classList.toggle('dark-mode');
        const isDark = document.body.classList.contains('dark-mode');
        try {
            localStorage.setItem('todoMode', isDark ? 'dark' : 'light');
        } catch (e) {}
        updateModeButton();
        setStatus(isDark ? 'Mode gelap diaktifkan.' : 'Mode terang diaktifkan.');
    });

    // ============================================
    // 2. KAMERA & MEDIA CAPTURE API
    // ============================================
    let streamKamera = null;
    const BATAS_UKURAN_FOTO = 5 * 1024 * 1024;

    const tombolKamera = document.getElementById('tombol-kamera');
    const tombolFile = document.getElementById('tombol-file');
    const inputFile = document.getElementById('input-file');
    const videoKamera = document.getElementById('preview-kamera');
    const tombolJepret = document.getElementById('tombol-jepret');
    const tombolStopKamera = document.getElementById('tombol-stop-kamera');
    const canvasKamera = document.getElementById('canvas-kamera');
    const previewFoto = document.getElementById('preview-foto');
    const tombolHapusFoto = document.getElementById('tombol-hapus-foto');
    const inputFotoBase64 = document.getElementById('foto-base64');
    const inputDeskripsiFoto = document.getElementById('deskripsi-foto');
    const inputJudul = document.getElementById('judul');
    const inputTenggat = document.getElementById('waktu-tenggat');
    const todoForm = document.getElementById('todo-form');

    function hentikanKamera() {
        if (streamKamera) {
            streamKamera.getTracks().forEach(function(track) {
                track.stop();
            });
        }
        streamKamera = null;
        if (videoKamera) {
            videoKamera.srcObject = null;
            videoKamera.hidden = true;
        }
        if (tombolJepret) tombolJepret.hidden = true;
        if (tombolStopKamera) tombolStopKamera.hidden = true;
    }

    if (tombolKamera) {
        tombolKamera.addEventListener('click', async function() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                setStatus('Browser tidak mendukung akses kamera.');
                return;
            }
            try {
                hentikanKamera();
                streamKamera = await navigator.mediaDevices.getUserMedia({ video: true });
                videoKamera.srcObject = streamKamera;
                videoKamera.hidden = false;
                tombolStopKamera.hidden = false;
                tombolJepret.hidden = false;
                setStatus('Kamera aktif.');
            } catch (error) {
                console.error(error);
                hentikanKamera();
                setStatus('Kamera tidak dapat digunakan. Periksa izin kamera browser.');
            }
        });
    }

    if (tombolStopKamera) {
        tombolStopKamera.addEventListener('click', function() {
            hentikanKamera();
            tombolKamera.focus();
            setStatus('Kamera dihentikan.');
        });
    }

    if (tombolJepret) {
        tombolJepret.addEventListener('click', function() {
            if (!streamKamera || videoKamera.videoWidth === 0 || videoKamera.videoHeight === 0) {
                setStatus('Kamera belum siap.');
                hentikanKamera();
                return;
            }
            try {
                const lebar = videoKamera.videoWidth;
                const tinggi = videoKamera.videoHeight;
                const skala = Math.min(1, 1600 / Math.max(lebar, tinggi));
                canvasKamera.width = Math.round(lebar * skala);
                canvasKamera.height = Math.round(tinggi * skala);
                const context = canvasKamera.getContext('2d');
                context.drawImage(videoKamera, 0, 0, canvasKamera.width, canvasKamera.height);

                const dataUrl = canvasKamera.toDataURL('image/jpeg', 0.85);
                if (!dataUrl.startsWith('data:image/jpeg;base64,')) {
                    throw new Error('Capture bukan JPEG yang valid');
                }

                inputFotoBase64.value = dataUrl;
                if (inputFile) inputFile.value = '';

                previewFoto.src = dataUrl;
                previewFoto.alt = inputDeskripsiFoto.value.trim() || 'Pratinjau foto tugas';
                previewFoto.hidden = false;
                if (tombolHapusFoto) tombolHapusFoto.style.display = 'block';

                inputDeskripsiFoto.required = true;
                setStatus('Foto berhasil diambil dari kamera.');
            } catch (error) {
                console.error('Capture kamera gagal:', error);
                setStatus('Foto gagal diambil. Silakan coba lagi.');
            } finally {
                hentikanKamera();
            }
        });
    }

    if (tombolFile && inputFile) {
        tombolFile.addEventListener('click', function() {
            inputFile.click();
        });

        inputFile.addEventListener('change', function() {
            const file = inputFile.files[0];
            if (!file) return;

            if (!file.type.startsWith('image/')) {
                inputFile.value = '';
                setStatus('File yang dipilih harus berupa gambar.');
                return;
            }
            if (file.size > BATAS_UKURAN_FOTO) {
                inputFile.value = '';
                setStatus('Ukuran foto maksimal 5 MB.');
                return;
            }

            inputFotoBase64.value = ''; // Menggunakan upload file standar
            inputDeskripsiFoto.required = true;

            const reader = new FileReader();
            reader.onload = function(event) {
                previewFoto.src = event.target.result;
                previewFoto.alt = inputDeskripsiFoto.value.trim() || 'Pratinjau foto tugas';
                previewFoto.hidden = false;
                if (tombolHapusFoto) tombolHapusFoto.style.display = 'block';
                hentikanKamera();
                setStatus('Foto dari file berhasil dipilih.');
            };
            reader.onerror = function() {
                setStatus('Foto gagal dibaca.');
            };
            reader.readAsDataURL(file);
        });
    }

    if (tombolHapusFoto) {
        tombolHapusFoto.addEventListener('click', function() {
            if (inputFile) inputFile.value = '';
            if (inputFotoBase64) inputFotoBase64.value = '';
            if (previewFoto) {
                previewFoto.src = '';
                previewFoto.hidden = true;
            }
            tombolHapusFoto.style.display = 'none';
            inputDeskripsiFoto.required = false;
            setStatus('Foto dibatalkan.');
        });
    }

    if (inputDeskripsiFoto && previewFoto) {
        inputDeskripsiFoto.addEventListener('input', function() {
            if (!previewFoto.hidden) {
                previewFoto.alt = inputDeskripsiFoto.value.trim() || 'Pratinjau foto tugas';
            }
        });
    }

    // Validate form fields; permission requests happen only from the explicit button.
    if (todoForm) {
        todoForm.addEventListener('submit', function(event) {
            const judul = inputJudul ? inputJudul.value.trim() : '';
            if (!judul) {
                event.preventDefault();
                setStatus('Judul tugas belum diisi.');
                if (inputJudul) inputJudul.focus();
                return;
            }

            const hasPhoto = (inputFile && inputFile.files.length > 0) || (inputFotoBase64 && inputFotoBase64.value);
            if (hasPhoto && (!inputDeskripsiFoto || !inputDeskripsiFoto.value.trim())) {
                event.preventDefault();
                setStatus('Deskripsi foto wajib diisi untuk teks aksesibilitas gambar.');
                if (inputDeskripsiFoto) inputDeskripsiFoto.focus();
                return;
            }

        });
    }

    // Centered task edit modal behavior. The existing POST form and fields remain unchanged.
    let activeEditModal = null;
    let activeEditTrigger = null;

    function editFormState(form) {
        return JSON.stringify(Array.from(form.elements).filter(function(field) {
            return field.name && field.type !== 'submit' && field.type !== 'button';
        }).map(function(field) {
            if (field.type === 'file') return [field.name, Array.from(field.files || []).map(function(file) { return file.name; })];
            return [field.name, field.value];
        }));
    }

    function modalFocusableElements(modal) {
        return Array.from(modal.querySelectorAll('button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'))
            .filter(function(element) { return element.getClientRects().length > 0; });
    }

    function closeEditModal() {
        if (!activeEditModal) return true;
        const modal = activeEditModal;
        const form = modal.querySelector('[data-edit-form]');
        if (form && form.dataset.saving === 'true') return false;
        const isDirty = form && form.dataset.initialState !== undefined && editFormState(form) !== form.dataset.initialState;
        if (isDirty && !window.confirm('Perubahan belum disimpan. Buang perubahan dan tutup Edit Task?')) return false;
        if (isDirty && form) form.reset();
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('edit-modal-open');
        activeEditModal = null;
        const trigger = activeEditTrigger;
        activeEditTrigger = null;
        if (trigger && document.contains(trigger)) trigger.focus();
        return true;
    }

    function openEditModal(todoId, trigger) {
        const modal = document.getElementById('edit-form-' + todoId);
        if (!modal) return;
        if (activeEditModal && activeEditModal !== modal && !closeEditModal()) return;
        document.querySelectorAll('.account-switcher[open]').forEach(function(switcher) { switcher.open = false; });
        const form = modal.querySelector('[data-edit-form]');
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('edit-modal-open');
        activeEditModal = modal;
        activeEditTrigger = trigger || document.querySelector('.edit-button[aria-controls="edit-form-' + todoId + '"]');
        if (form) form.dataset.initialState = editFormState(form);
        const firstField = modal.querySelector('input:not([type="hidden"]), select, textarea, button');
        if (firstField) firstField.focus();
    }

    function toggleEdit(todoId) {
        const modal = document.getElementById('edit-form-' + todoId);
        if (!modal) return;
        if (activeEditModal === modal || !modal.hidden) closeEditModal();
        else openEditModal(todoId, document.activeElement);
    }

    document.querySelectorAll('[data-edit-modal]:not([hidden])').forEach(function(modal) {
        activeEditModal = modal;
        activeEditTrigger = null;
        document.body.classList.add('edit-modal-open');
        const form = modal.querySelector('[data-edit-form]');
        if (form) form.dataset.initialState = editFormState(form);
        const firstField = modal.querySelector('input:not([type="hidden"]), select, textarea, button');
        if (firstField) firstField.focus();
    });

    document.querySelectorAll('[data-edit-form]').forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (form.dataset.saving === 'true') {
                event.preventDefault();
                return;
            }
            form.dataset.saving = 'true';
            form.querySelectorAll('button[type="submit"]').forEach(function(button) {
                button.disabled = true;
                button.textContent = 'Menyimpan...';
            });
        });
    });

    document.addEventListener('click', function(event) {
        if (activeEditModal && event.target === activeEditModal) closeEditModal();
    });

    document.addEventListener('keydown', function(event) {
        if (!activeEditModal) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeEditModal();
            return;
        }
        if (event.key !== 'Tab') return;
        const focusable = modalFocusableElements(activeEditModal);
        if (!focusable.length) {
            event.preventDefault();
            activeEditModal.querySelector('[role="dialog"]').focus();
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || !activeEditModal.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !activeEditModal.contains(document.activeElement))) {
            event.preventDefault();
            first.focus();
        }
    });

    window.prepareAccountSwitch = function() {
        if (!activeEditModal) return true;
        return closeEditModal();
    };
    // Auto-hide flash messages after 4 seconds
    document.querySelectorAll('.flash-success, .flash-error').forEach(function(el) {
        setTimeout(function() {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(function() { el.remove(); }, 500);
        }, 4000);
    });

    window.addEventListener('beforeunload', function() {
        hentikanKamera();
    });
    </script>

    <script src="notification-scheduler.js" defer></script>
    <script src="dashboard.js" defer></script>
</body>

</html>
