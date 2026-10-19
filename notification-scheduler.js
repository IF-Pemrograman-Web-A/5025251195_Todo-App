(function () {
    "use strict";

    const statusElement = document.getElementById("status-aplikasi");
    const permissionButton = document.getElementById("tombol-izin-notifikasi");
    const csrfToken = window.NOTIFICATION_CSRF_TOKEN || "";
    const apiUrl = new URL("notification_actions.php", document.baseURI).toString();
    const graceMs = 24 * 60 * 60 * 1000;
    const pollMs = 10000;
    const refreshMs = 30000;
    const todos = new Map();
    const processing = new Set();
    const handled = new Set();
    let registration = null;
    let recipientId = 0;
    let syncing = false;
    let lastSnapshotAt = 0;
    let statusChannel = null;
    try {
        if ("BroadcastChannel" in window) {
            statusChannel = new BroadcastChannel("iron-citadel-notification-status");
        }
    } catch (error) {
        console.warn("Koordinasi status antar tab tidak tersedia:", error);
    }

    if (statusChannel) {
        statusChannel.addEventListener("message", function(event) {
            const message = event.data || {};
            if (message.type !== "processed" || !message.key) return;
            handled.add(message.key);
            setStatus(message.message || "Status pengingat diperbarui di tab lain.");
        });
    }

    function setStatus(message) {
        if (statusElement) statusElement.textContent = message;
    }

    function logDiagnostic(message, detail) {
        if (detail) console.info("[pengingat] " + message, detail);
        else console.info("[pengingat] " + message);
    }

    function eventKey(todo) {
        return String(todo.id) + ":" + String(todo.waktuNotifikasi || "");
    }

    function broadcastProcessed(key, result, message) {
        if (statusChannel) {
            statusChannel.postMessage({ type: "processed", key: key, result: result, message: message });
        }
    }

    function notificationTag(todo) {
        return "todo-" + recipientId + "-" + todo.id + "-" + String(todo.waktuNotifikasi || "").replace(/[^0-9]/g, "");
    }

    function sendWorkerMessage(message, waitForReply) {
        if (!registration || !registration.active) {
            return Promise.reject(new Error("Service Worker belum aktif."));
        }
        if (!waitForReply) {
            try {
                registration.active.postMessage(message);
                return Promise.resolve({ type: "sent" });
            } catch (error) {
                return Promise.reject(error);
            }
        }

        return new Promise(function (resolve, reject) {
            const channel = new MessageChannel();
            const timeout = window.setTimeout(function () {
                channel.port1.close();
                reject(new Error("Service Worker tidak memberi konfirmasi."));
            }, 12000);

            channel.port1.onmessage = function (event) {
                window.clearTimeout(timeout);
                channel.port1.close();
                const response = event.data || {};
                if (response.type === "TODO_NOTIFICATION_SHOWN" || response.type === "TODO_NOTIFICATION_ALREADY_SHOWN") {
                    resolve(response);
                } else {
                    reject(new Error(response.message || "Service Worker gagal menampilkan notifikasi."));
                }
            };

            try {
                registration.active.postMessage(message, [channel.port2]);
            } catch (error) {
                window.clearTimeout(timeout);
                channel.port1.close();
                reject(error);
            }
        });
    }

    function cancelOldNotification(todo) {
        if (!registration || !registration.active) return;
        sendWorkerMessage({
            type: "CANCEL_TODO_NOTIFICATION",
            tag: notificationTag(todo)
        }, false).catch(function (error) {
            logDiagnostic("Pembatalan notifikasi lama gagal.", error);
        });
    }

    async function apiPost(payload) {
        const response = await fetch(apiUrl, {
            method: "POST",
            credentials: "same-origin",
            cache: "no-store",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(Object.assign({ csrfToken: csrfToken }, payload))
        });
        const result = await response.json();
        if (!response.ok || !result.ok) {
            throw new Error(result.message || "Server menolak pemrosesan pengingat.");
        }
        return result;
    }

    async function recordMissed(todo, detail) {
        const key = eventKey(todo);
        if (handled.has(key)) return;
        if (processing.has(key)) return;
        processing.add(key);
        try {
            const result = await apiPost({
                action: "missed",
                todoId: todo.id,
                scheduledFor: todo.waktuNotifikasi
            });
            if (result.result === "missed" || result.result === "triggered") {
                handled.add(key);
            }
            if (result.result === "missed") {
                setStatus(detail);
                broadcastProcessed(key, "missed", detail);
                logDiagnostic("Pengingat dicatat sebagai terlewat.", { todoId: todo.id, reason: result.detail });
            } else if (result.result === "triggered") {
                const message = "Notifikasi untuk \"" + todo.judul + "\" sudah berhasil dipanggil oleh browser.";
                setStatus(message);
                broadcastProcessed(key, "triggered", message);
            }
        } catch (error) {
            console.error("Gagal mencatat pengingat terlewat:", error);
            setStatus("Pengingat terlewat belum dapat dicatat. Periksa koneksi aplikasi.");
        } finally {
            processing.delete(key);
        }
    }

    async function processDueReminder(todo, now) {
        const dueMs = todo.waktuNotifikasiMs;
        if (todo.selesai || !Number.isFinite(dueMs) || dueMs > now) return;

        const lateness = now - dueMs;
        const key = eventKey(todo);
        if (handled.has(key)) return;
        if (lateness > graceMs) {
            await recordMissed(todo, "Pengingat untuk \"" + todo.judul + "\" terlewat saat aplikasi tidak aktif.");
            return;
        }

        if (!("Notification" in window)) {
            await recordMissed(todo, "Browser tidak mendukung notifikasi; pengingat tidak dapat ditampilkan.");
            return;
        }
        if (Notification.permission === "default") {
            setStatus("Waktu pengingat \"" + todo.judul + "\" tiba. Aktifkan izin notifikasi untuk memprosesnya.");
            return;
        }
        if (Notification.permission !== "granted") {
            setStatus("Izin notifikasi ditolak. Aktifkan izin situs di Edge, lalu tekan Aktifkan Notifikasi lagi.");
            return;
        }

        if (processing.has(key)) return;
        processing.add(key);
        try {
            const claim = await apiPost({
                action: "claim",
                todoId: todo.id,
                scheduledFor: todo.waktuNotifikasi,
                permissionGranted: true
            });
            if (claim.result !== "claimed") {
                if (claim.result === "missed" || claim.result === "triggered") {
                    handled.add(key);
                    const message = claim.result === "missed"
                        ? "Pengingat untuk \"" + todo.judul + "\" terlewat dan sudah dicatat."
                        : "Notifikasi untuk \"" + todo.judul + "\" sudah berhasil dipanggil oleh browser.";
                    setStatus(message);
                    broadcastProcessed(key, claim.result, message);
                }
                return;
            }

            try {
                if (!registration || !registration.active) throw new Error("Service Worker belum aktif.");
                await sendWorkerMessage({
                    type: "SHOW_TODO_NOTIFICATION",
                    id: todo.id,
                    recipientId: recipientId,
                    judul: todo.judul,
                    tag: notificationTag(todo),
                    url: new URL("index.php?view=" + (todo.tipe === "shared" ? "shared" : "personal") + "#todo-" + todo.id, document.baseURI).toString()
                }, true);

                const recorded = await apiPost({ action: "result", result: "triggered", todoId: todo.id, scheduledFor: todo.waktuNotifikasi });
                if (recorded.result !== "triggered") throw new Error("Hasil pemanggilan notifikasi tidak tercatat sebagai triggered.");
                handled.add(key);
                const message = "Notifikasi untuk \"" + todo.judul + "\" berhasil dipanggil oleh browser.";
                setStatus(message);
                broadcastProcessed(key, "triggered", message);
                logDiagnostic("showNotification berhasil dipanggil.", { todoId: todo.id, scheduledFor: todo.waktuNotifikasi });
            } catch (error) {
                console.error("Notifikasi gagal ditampilkan:", error);
                try {
                    const recorded = await apiPost({
                        action: "result",
                        result: "missed",
                        detail: "notification_api_failed",
                        todoId: todo.id,
                        scheduledFor: todo.waktuNotifikasi
                    });
                    if (recorded.result === "missed") handled.add(key);
                } catch (recordError) {
                    console.error("Gagal menyimpan hasil notifikasi:", recordError);
                }
                const message = "Notifikasi untuk \"" + todo.judul + "\" gagal ditampilkan dan dicatat.";
                setStatus(message);
                broadcastProcessed(key, "missed", message);
            }
        } catch (error) {
            console.error("Pengingat tidak dapat diklaim:", error);
            setStatus("Pengingat belum dapat diproses. Periksa koneksi dan konsol browser.");
        } finally {
            processing.delete(key);
        }
    }

    function updateTaskBadges(todo, now) {
        const row = document.getElementById("todo-" + todo.id);
        const isOverdue = !todo.selesai && Number.isFinite(todo.tenggatMs) && todo.tenggatMs <= now;
        if (row) row.dataset.overdue = isOverdue ? "true" : "false";

        const deadlineBadge = document.getElementById("todo-deadline-" + todo.id);
        if (deadlineBadge && isOverdue) {
            deadlineBadge.className = "badge badge--overdue";
            deadlineBadge.textContent = "Tenggat terlewat";
        }

        const reminderBadge = document.getElementById("todo-reminder-" + todo.id);
        if (reminderBadge && todo.waktuNotifikasiMs !== null) {
            if (todo.selesai) {
                reminderBadge.className = "badge badge--none";
                reminderBadge.textContent = "Pengingat nonaktif";
            } else if (todo.waktuNotifikasiMs <= now) {
                reminderBadge.className = "badge badge--overdue";
                reminderBadge.textContent = "Waktu pengingat lewat";
            }
        }
    }

    async function refreshSnapshot() {
        const response = await fetch(apiUrl + "?action=snapshot", {
            credentials: "same-origin",
            cache: "no-store"
        });
        const result = await response.json();
        if (!response.ok || !result.ok || !Array.isArray(result.todos)) {
            throw new Error(result.message || "Snapshot tugas tidak valid.");
        }

        recipientId = Number(result.recipientId) || 0;
        const previous = new Map(todos);
        todos.clear();
        result.todos.forEach(function(todo) {
            todos.set(Number(todo.id), todo);
        });

        previous.forEach(function(oldTodo, id) {
            const current = todos.get(id);
            if (!current || current.selesai || current.waktuNotifikasi !== oldTodo.waktuNotifikasi) {
                cancelOldNotification(oldTodo);
            }
        });
        lastSnapshotAt = Date.now();
        logDiagnostic("Snapshot pengingat dari database diperbarui.", { taskCount: todos.size });
    }

    async function reconcile(forceSnapshot) {
        if (syncing) return;
        syncing = true;
        try {
            if (forceSnapshot || Date.now() - lastSnapshotAt >= refreshMs || todos.size === 0) {
                await refreshSnapshot();
            }
            const now = Date.now();
            for (const todo of todos.values()) {
                updateTaskBadges(todo, now);
                await processDueReminder(todo, now);
            }
        } catch (error) {
            console.error("Pemeriksaan pengingat gagal:", error);
            setStatus("Pemeriksaan pengingat gagal. Periksa koneksi aplikasi dan konsol browser.");
        } finally {
            syncing = false;
        }
    }

    async function enableNotifications() {
        if (!("Notification" in window)) {
            setStatus("Browser ini tidak mendukung Notifications API.");
            return;
        }
        if (Notification.permission === "denied") {
            setStatus("Izin notifikasi ditolak. Ubah izin situs ini melalui pengaturan Edge, lalu muat ulang halaman.");
            return;
        }
        if (Notification.permission === "default") {
            try {
                const permission = await Notification.requestPermission();
                if (permission !== "granted") {
                    setStatus("Izin notifikasi belum diberikan. Pengingat belum dapat ditampilkan.");
                    return;
                }
            } catch (error) {
                console.error("Permintaan izin notifikasi gagal:", error);
                setStatus("Browser gagal meminta izin notifikasi.");
                return;
            }
        }
        if (Notification.permission !== "granted") {
            setStatus("Izin notifikasi belum aktif.");
            return;
        }

        try {
            if (!registration || !registration.active) {
                registration = await navigator.serviceWorker.ready;
            }
            const testTag = "iron-citadel-test-" + Date.now() + "-" + Math.random().toString(36).slice(2);
            await sendWorkerMessage({
                type: "SHOW_TODO_NOTIFICATION",
                id: 0,
                recipientId: recipientId,
                judul: "Notifikasi berhasil diaktifkan.",
                tag: testTag,
                url: window.location.href
            }, true);
            setStatus("Notifikasi uji berhasil ditampilkan. Pengingat diperiksa setiap 10 detik selama dashboard terbuka.");
            handled.clear();
            await reconcile(true);
        } catch (error) {
            console.error("Notifikasi uji gagal ditampilkan:", error);
            setStatus("Izin aktif, tetapi notifikasi uji gagal ditampilkan: " + error.message);
        }
    }
    async function initialize() {
        if (permissionButton) permissionButton.addEventListener("click", enableNotifications);

        if (!("serviceWorker" in navigator)) {
            setStatus("Browser tidak mendukung Service Worker; pengingat tidak dapat diproses.");
            return;
        }

        try {
            const workerUrl = new URL("service-worker.js", document.baseURI);
            const worker = await navigator.serviceWorker.register(workerUrl.href);
            registration = await navigator.serviceWorker.ready;
            logDiagnostic("Service Worker siap.", {
                scope: registration.scope,
                state: registration.active ? registration.active.state : worker.active ? worker.active.state : "pending"
            });
        } catch (error) {
            console.error("Service Worker gagal didaftarkan:", error);
            setStatus("Service Worker gagal didaftarkan. Pastikan aplikasi dibuka melalui localhost atau HTTPS.");
            return;
        }

        if (!("Notification" in window)) {
            setStatus("Browser tidak mendukung notifikasi. Riwayat tetap dapat dilihat di halaman Notifications.");
        } else if (Notification.permission === "default") {
            setStatus("Notifikasi browser belum diizinkan. Tekan Aktifkan Notifikasi sebelum waktu pengingat.");
        } else if (Notification.permission === "denied") {
            setStatus("Izin notifikasi ditolak. Aktifkan izin melalui pengaturan situs browser.");
        } else {
            setStatus("Pemeriksaan pengingat sedang berjalan.");
        }

        await reconcile(true);
        window.setInterval(function() { reconcile(false); }, pollMs);
        window.addEventListener("focus", function() { reconcile(true); });
        window.addEventListener("pageshow", function() { reconcile(true); });
        document.addEventListener("visibilitychange", function() {
            if (document.visibilityState === "visible") reconcile(true);
        });
    }

    initialize();
})();
