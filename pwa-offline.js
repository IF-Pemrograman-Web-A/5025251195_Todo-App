(function () {
    "use strict";

    const databaseName = "ironCitadelPwa";
    const databaseVersion = 1;
    let databaseRequest;
    const offlineApp = document.getElementById("offline-task-list");
    const statusElement = document.getElementById("offline-status");
    const syncButton = document.getElementById("offline-sync-button");
    let database = null;
    let currentUserId = 0;
    let currentView = "personal";
    let editTask = null;
    let rendering = false;
    let renderAgain = false;
    let objectUrls = [];

    try {
        databaseRequest = indexedDB.open(databaseName, databaseVersion);
    } catch (error) {
        setStatus("Penyimpanan offline tidak dapat dibuka: " + error.message);
    }

    if (!databaseRequest) return;

    databaseRequest.onupgradeneeded = function (event) {
        const db = event.target.result;
        if (!db.objectStoreNames.contains("snapshots")) db.createObjectStore("snapshots", { keyPath: "key" });
        if (!db.objectStoreNames.contains("outbox")) db.createObjectStore("outbox", { keyPath: "sequence", autoIncrement: true });
        if (!db.objectStoreNames.contains("images")) db.createObjectStore("images", { keyPath: "key" });
    };

    databaseRequest.onerror = function () {
        setStatus("Penyimpanan offline tidak dapat dibuka. Periksa ruang penyimpanan browser.");
    };

    databaseRequest.onsuccess = function (event) {
        database = event.target.result;
        database.onversionchange = function () {
            database.close();
            database = null;
            setStatus("Penyimpanan offline berubah. Muat ulang aplikasi.");
        };
        initialize();
    };

    function setStatus(message) {
        if (statusElement) statusElement.textContent = message;
        const appStatus = document.getElementById("status-aplikasi");
        if (appStatus) appStatus.textContent = message;
        const offlineStatus = document.getElementById("pwa-offline-status");
        if (offlineStatus) offlineStatus.textContent = message;
    }

    function transactionRequest(storeName, mode, action) {
        return new Promise(function (resolve, reject) {
            if (!database) {
                reject(new Error("Penyimpanan offline belum siap."));
                return;
            }
            const transaction = database.transaction(storeName, mode);
            let request;
            let result;
            try {
                request = action(transaction.objectStore(storeName));
            } catch (error) {
                reject(error);
                return;
            }
            request.onsuccess = function () { result = request.result; };
            request.onerror = function () { reject(request.error || new Error("Permintaan IndexedDB gagal.")); };
            transaction.oncomplete = function () { resolve(result); };
            transaction.onerror = function () { reject(transaction.error || new Error("Transaksi IndexedDB gagal.")); };
            transaction.onabort = function () { reject(transaction.error || new Error("Transaksi IndexedDB dibatalkan.")); };
        });
    }

    function snapshotKey(userId, view) {
        return String(userId) + ":" + view;
    }

    function readSnapshot(userId, view) {
        return transactionRequest("snapshots", "readonly", function (store) {
            return store.get(snapshotKey(userId, view));
        });
    }

    function writeSnapshot(userId, view, csrfToken, todos) {
        return new Promise(function (resolve, reject) {
            if (!database) {
                reject(new Error("Penyimpanan offline belum siap."));
                return;
            }
            const transaction = database.transaction(["snapshots", "outbox"], "readwrite");
            const snapshotStore = transaction.objectStore("snapshots");
            const operationsRequest = transaction.objectStore("outbox").getAll();
            let result;
            operationsRequest.onsuccess = function () {
                const pendingStatuses = operationsRequest.result
                    .filter(function (operation) {
                        return operation.userId === Number(userId) && operation.action === "set_status";
                    })
                    .sort(function (left, right) { return left.sequence - right.sequence; });
                const mergedTodos = todos.map(function (todo) {
                    let merged = todo;
                    pendingStatuses.forEach(function (operation) {
                        if (Number(operation.todo && operation.todo.id) === Number(todo.id)) {
                            merged = Object.assign({}, merged, {
                                selesai: Number(operation.todo.selesai),
                                _pending: true
                            });
                        }
                    });
                    return merged;
                });
                result = snapshotStore.put({
                    key: snapshotKey(userId, view),
                    userId: Number(userId),
                    view: view,
                    csrfToken: csrfToken,
                    todos: mergedTodos,
                    savedAt: Date.now()
                });
            };
            operationsRequest.onerror = function () {
                reject(operationsRequest.error || new Error("Antrean offline gagal dibaca."));
            };
            transaction.oncomplete = function () { resolve(result && result.result); };
            transaction.onerror = function () { reject(transaction.error || new Error("Snapshot IndexedDB gagal disimpan.")); };
            transaction.onabort = function () { reject(transaction.error || new Error("Penyimpanan snapshot dibatalkan.")); };
        });
    }

    function getAllQueue() {
        return transactionRequest("outbox", "readonly", function (store) {
            return store.getAll();
        });
    }

    function queueOperation(operation) {
        return transactionRequest("outbox", "readwrite", function (store) {
            return store.add(operation);
        });
    }

    function removeOperation(sequence) {
        return transactionRequest("outbox", "readwrite", function (store) {
            return store.delete(sequence);
        });
    }

    function replaceOperation(operation) {
        return transactionRequest("outbox", "readwrite", function (store) {
            return store.put(operation);
        });
    }

    function putImage(userId, path, blob) {
        return transactionRequest("images", "readwrite", function (store) {
            return store.put({ key: String(userId) + ":" + path, blob: blob });
        });
    }

    function getImage(userId, path) {
        return transactionRequest("images", "readonly", function (store) {
            return store.get(String(userId) + ":" + path);
        });
    }

    function newOperationId() {
        if (window.crypto && typeof window.crypto.randomUUID === "function") return window.crypto.randomUUID();
        return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (character) {
            const random = Math.random() * 16 | 0;
            return (character === "x" ? random : (random & 3 | 8)).toString(16);
        });
    }

    function currentUrlView() {
        const query = new URLSearchParams(window.location.search);
        return query.get("view") === "shared" ? "shared" : "personal";
    }

    function escapeHtml(value) {
        return String(value == null ? "" : value).replace(/[&<>"']/g, function (character) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[character];
        });
    }

    function toDateInput(value) {
        if (!value) return "";
        return String(value).replace(" ", "T").slice(0, 16);
    }

    function fromForm(form) {
        const data = new FormData(form);
        return {
            judul: String(data.get("judul") || "").trim(),
            keterangan: String(data.get("keterangan") || "").trim(),
            tipe: data.get("tipe") === "shared" ? "shared" : "personal",
            selesai: String(data.get("selesai") || "0"),
            tenggat: String(data.get("tenggat") || ""),
            waktu_notifikasi: String(data.get("waktu_notifikasi") || ""),
            deskripsi_foto: String(data.get("deskripsi_foto") || "").trim()
        };
    }

    function fileAsDataUrl(file) {
        if (!file) return Promise.resolve("");
        if (!["image/jpeg", "image/png", "image/gif", "image/webp"].includes(file.type) || file.size > 5 * 1024 * 1024) {
            return Promise.reject(new Error("Foto harus berupa gambar dengan ukuran maksimal 5 MB."));
        }
        return new Promise(function (resolve, reject) {
            const reader = new FileReader();
            reader.onload = function () { resolve(String(reader.result || "")); };
            reader.onerror = function () { reject(new Error("Foto gagal dibaca.")); };
            reader.readAsDataURL(file);
        });
    }

    function validateTask(task, photo, validateReminder) {
        if (!task.judul) throw new Error("Judul tugas wajib diisi.");
        if (photo && !task.deskripsi_foto) throw new Error("Deskripsi foto wajib diisi jika tugas memiliki foto.");
        if (validateReminder !== false && task.waktu_notifikasi) {
            const reminder = new Date(task.waktu_notifikasi);
            if (!Number.isFinite(reminder.getTime()) || reminder <= new Date()) {
                throw new Error("Waktu notifikasi harus berada di masa depan.");
            }
        }
    }

    async function readEffectiveTodos(userId, view) {
        const snapshots = await Promise.all([
            readSnapshot(userId, "personal"),
            readSnapshot(userId, "shared")
        ]);
        const snapshot = snapshots[view === "shared" ? 1 : 0];
        const tasksById = new Map();
        snapshots.forEach(function (entry) {
            if (!entry) return;
            entry.todos.forEach(function (todo) {
                tasksById.set(String(todo.id), Object.assign({}, todo));
            });
        });
        const operations = (await getAllQueue())
            .filter(function (operation) { return operation.userId === Number(userId); })
            .sort(function (left, right) { return left.sequence - right.sequence; });

        operations.forEach(function (operation) {
            const data = operation.todo || {};
            const taskId = String(data.id || operation.localId);
            if (operation.action === "create") {
                tasksById.set(String(operation.localId), Object.assign({}, data, { id: operation.localId, _pending: true }));
            } else if (operation.action === "update" && tasksById.has(taskId)) {
                const task = Object.assign({}, tasksById.get(taskId), data.changes || {}, { _pending: true });
                if (data.foto_base64) task.foto_base64 = data.foto_base64;
                tasksById.set(taskId, task);
            } else if (operation.action === "set_status" && tasksById.has(taskId)) {
                const task = Object.assign({}, tasksById.get(taskId), { selesai: Number(data.selesai), _pending: true });
                tasksById.set(taskId, task);
            } else if (operation.action === "delete") {
                tasksById.delete(taskId);
            }
        });
        const tasks = Array.from(tasksById.values()).filter(function (todo) {
            return (todo.tipe === "shared" ? "shared" : "personal") === view;
        });
        return { snapshot: snapshot, tasks: tasks, operations: operations };
    }

    async function saveFromDashboard(data) {
        if (!data || !data.userId || !Array.isArray(data.todos)) return;
        currentUserId = Number(data.userId);
        currentView = data.view === "shared" ? "shared" : "personal";
        await writeSnapshot(currentUserId, currentView, data.csrfToken, data.todos);
        cachePhotos(currentUserId, data.todos);
        if (navigator.onLine) {
            await Promise.all(["personal", "shared"].map(async function (view) {
                try {
                    await fetchSnapshot(currentUserId, view);
                } catch (error) {
                    console.warn("[pwa] Snapshot offline belum diperbarui:", error);
                }
            }));
            await syncQueue();
        }
    }

    async function cachePhotos(userId, todos) {
        if (!navigator.onLine) return;
        for (const todo of todos) {
            if (!todo.foto) continue;
            try {
                const cached = await getImage(userId, todo.foto);
                if (cached) continue;
                const response = await fetch(todo.foto, { credentials: "same-origin", cache: "no-store" });
                if (response.ok) await putImage(userId, todo.foto, await response.blob());
            } catch (error) {
                console.warn("[pwa] Foto tidak dapat disimpan untuk offline:", error);
            }
        }
    }

    async function fetchSnapshot(userId, view) {
        const response = await fetch("pwa_sync.php?view=" + encodeURIComponent(view), {
            credentials: "same-origin",
            cache: "no-store"
        });
        const result = await response.json();
        if (!response.ok || !result.ok || Number(result.userId) !== Number(userId)) {
            throw new Error(result.message || "Snapshot tugas tidak dapat dimuat.");
        }
        await writeSnapshot(userId, view, result.csrfToken, result.todos);
        cachePhotos(userId, result.todos);
        return result;
    }

    async function syncQueue() {
        if (!database || !navigator.onLine || !currentUserId) return;
        const operations = (await getAllQueue())
            .filter(function (operation) { return operation.userId === Number(currentUserId); })
            .sort(function (left, right) { return left.sequence - right.sequence; });
        if (!operations.length) {
            updateQueueCount(0);
            return;
        }
        updateQueueCount(operations.length);

        for (const operation of operations) {
            const snapshot = await readSnapshot(operation.userId, operation.view);
            if (!snapshot || !snapshot.csrfToken) {
                setStatus("Perubahan tersimpan, tetapi token sinkronisasi belum tersedia. Buka dashboard online sekali.");
                break;
            }
            try {
                const response = await fetch("pwa_sync.php", {
                    method: "POST",
                    credentials: "same-origin",
                    cache: "no-store",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        csrfToken: snapshot.csrfToken,
                        operationId: operation.operationId,
                        action: operation.action,
                        todo: operation.todo
                    })
                });
                const result = await response.json();
                if (!response.ok || !result.ok) {
                    throw new Error(result.message || "Server menolak sinkronisasi.");
                }
                if (operation.action === "create" && result.todo) {
                    await replaceLocalId(operation, result.todo);
                } else if (operation.action === "delete") {
                    await removeSnapshotTodo(operation.userId, Number(result.deletedId));
                } else if (result.todo) {
                    await upsertSnapshotTodo(operation.userId, result.todo);
                }
                await removeOperation(operation.sequence);
                setStatus("Perubahan offline berhasil disinkronkan.");
            } catch (error) {
                console.error("[pwa] Sinkronisasi operasi gagal:", error);
                setStatus("Sinkronisasi tertunda: " + error.message + " Perubahan tetap tersimpan di perangkat.");
                break;
            }
        }

        const remaining = (await getAllQueue()).filter(function (operation) {
            return operation.userId === Number(currentUserId);
        });
        updateQueueCount(remaining.length);
        if (!remaining.length && navigator.onLine) {
            await Promise.all(["personal", "shared"].map(function (view) {
                return fetchSnapshot(currentUserId, view).catch(function (error) {
                    console.warn("[pwa] Snapshot terbaru belum dimuat:", error);
                });
            }));
            if (offlineApp) await renderOfflineTasks();
        }
    }

    async function upsertSnapshotTodo(userId, todo, localId) {
        const targetView = todo.tipe === "shared" ? "shared" : "personal";
        for (const view of ["personal", "shared"]) {
            const snapshot = await readSnapshot(userId, view);
            if (!snapshot) continue;
            const todos = snapshot.todos.filter(function (item) {
                return Number(item.id) !== Number(todo.id) && (localId == null || String(item.id) !== String(localId));
            });
            if (view === targetView) todos.push(todo);
            await writeSnapshot(userId, view, snapshot.csrfToken, todos);
        }
    }

    async function removeSnapshotTodo(userId, todoId) {
        for (const view of ["personal", "shared"]) {
            const snapshot = await readSnapshot(userId, view);
            if (!snapshot) continue;
            await writeSnapshot(userId, view, snapshot.csrfToken, snapshot.todos.filter(function (todo) {
                return Number(todo.id) !== Number(todoId);
            }));
        }
    }

    async function replaceLocalId(operation, todo) {
        await upsertSnapshotTodo(operation.userId, todo, operation.localId);
        const queue = await getAllQueue();
        for (const queued of queue) {
            if (queued.userId !== operation.userId || queued.sequence === operation.sequence) continue;
            if (queued.todo && String(queued.todo.id) === String(operation.localId)) {
                queued.todo.id = todo.id;
                await replaceOperation(queued);
            }
        }
    }

    async function enqueue(userId, view, action, todo, localId) {
        await queueOperation({
            userId: Number(userId),
            view: view,
            operationId: newOperationId(),
            action: action,
            todo: todo,
            localId: localId || null,
            createdAt: Date.now()
        });
        const count = (await getAllQueue()).filter(function (operation) {
            return operation.userId === Number(userId);
        }).length;
        updateQueueCount(count);
        return count;
    }

    async function saveSnapshotStatus(userId, view, todoId, status) {
        const operation = {
            userId: Number(userId),
            view: view,
            operationId: newOperationId(),
            action: "set_status",
            todo: { id: Number(todoId), selesai: String(status) },
            localId: null,
            createdAt: Date.now()
        };
        return new Promise(function (resolve, reject) {
            if (!database) {
                reject(new Error("Penyimpanan offline belum siap."));
                return;
            }
            const transaction = database.transaction(["snapshots", "outbox"], "readwrite");
            const snapshotsStore = transaction.objectStore("snapshots");
            const outboxStore = transaction.objectStore("outbox");
            const requests = ["personal", "shared"].map(function (snapshotView) {
                return snapshotsStore.get(snapshotKey(userId, snapshotView));
            });
            const queueRequest = outboxStore.getAll();
            let readyCount = 0;
            let foundTask = false;
            let result;

            function saveUpdatedSnapshots() {
                readyCount++;
                if (readyCount !== requests.length + 1) return;
                requests.forEach(function (request) {
                    const snapshot = request.result;
                    if (!snapshot || snapshot.userId !== Number(userId)) return;
                    let changed = false;
                    snapshot.todos = snapshot.todos.map(function (todo) {
                        if (Number(todo.id) !== Number(todoId)) return todo;
                        changed = true;
                        foundTask = true;
                        return Object.assign({}, todo, { selesai: Number(status), _pending: true });
                    });
                    if (changed) {
                        snapshot.savedAt = Date.now();
                        snapshotsStore.put(snapshot);
                    }
                });
                if (!foundTask) {
                    const createOperation = queueRequest.result.find(function (queued) {
                        return queued.userId === Number(userId)
                            && queued.action === "create"
                            && String(queued.localId) === String(todoId);
                    });
                    if (createOperation) {
                        createOperation.todo.selesai = String(status);
                        result = outboxStore.put(createOperation);
                        foundTask = true;
                    }
                }
                if (!foundTask) {
                    transaction.abort();
                    reject(new Error("Tugas tidak ditemukan pada snapshot lokal; perubahan tidak dimasukkan ke antrean."));
                    return;
                }
                if (result) {
                    result.onerror = function () {
                        reject(result.error || new Error("Perubahan status gagal disimpan."));
                    };
                } else {
                    result = outboxStore.add(operation);
                    result.onerror = function () {
                        reject(result.error || new Error("Perubahan status gagal dimasukkan ke antrean."));
                    };
                }
            }

            requests.forEach(function (request) {
                request.onsuccess = saveUpdatedSnapshots;
                request.onerror = function () {
                    reject(request.error || new Error("Snapshot lokal gagal dibaca."));
                };
            });
            queueRequest.onsuccess = saveUpdatedSnapshots;
            queueRequest.onerror = function () {
                reject(queueRequest.error || new Error("Antrean offline gagal dibaca."));
            };
            transaction.oncomplete = function () {
                getAllQueue().then(function (queue) {
                    updateQueueCount(queue.filter(function (item) {
                        return item.userId === Number(userId);
                    }).length);
                    resolve(result && result.result);
                }).catch(reject);
            };
            transaction.onerror = function () {
                reject(transaction.error || new Error("Status dan antrean gagal disimpan bersama."));
            };
            transaction.onabort = function () {
                reject(transaction.error || new Error("Penyimpanan status dibatalkan."));
            };
        });
    }

    function updateDashboardTaskStatus(form, status) {
        const row = form.closest(".task-item");
        if (!row) return;
        const completed = Number(status) === 1;
        row.classList.toggle("done", completed);
        row.dataset.status = completed ? "completed" : "pending";
        row.dataset.overdue = completed ? "false" : (row.dataset.deadlinePassed || "false");

        const button = form.querySelector(".task-toggle");
        if (button) {
            button.setAttribute("aria-label", completed ? "Tandai belum selesai" : "Tandai selesai");
            const icon = button.querySelector("span");
            if (icon) icon.textContent = completed ? "\u2713" : "";
        }

        const badge = document.getElementById("todo-status-" + row.dataset.todoId);
        if (badge) {
            badge.classList.toggle("badge--done", completed);
            badge.classList.toggle("badge--progress", !completed);
            badge.textContent = completed ? "Selesai" : "Proses";
        }

        const searchInput = document.getElementById("task-search");
        if (searchInput) searchInput.dispatchEvent(new Event("input", { bubbles: true }));
    }

    function updateQueueCount(count) {
        const element = document.getElementById("offline-queue-count");
        if (element) element.textContent = count + " perubahan tertunda";
        if (syncButton) syncButton.disabled = !navigator.onLine || count === 0;
    }

    async function saveOfflineCreate(form, userId, view) {
        const task = fromForm(form);
        const file = form.elements.namedItem("foto")?.files?.[0];
        task.foto_base64 = form.elements.namedItem("foto_base64")
            ? String(form.elements.namedItem("foto_base64").value || "")
            : "";
        if (!task.foto_base64 && file) task.foto_base64 = await fileAsDataUrl(file);
        validateTask(task, Boolean(task.foto_base64));
        const localId = "local-" + newOperationId();
        task.id = localId;
        task.user_id = Number(userId);
        task.is_owner = true;
        await enqueue(userId, task.tipe === "shared" ? "shared" : view, "create", task, localId);
        setStatus("Tugas disimpan offline. Perubahan akan dikirim saat koneksi kembali.");
    }

    async function saveOfflineUpdate(form, userId, view) {
        const data = fromForm(form);
        const todoId = Number(form.elements.namedItem("todo_id").value);
        const current = (await readEffectiveTodos(userId, view)).tasks.find(function (todo) {
            return Number(todo.id) === todoId;
        });
        if (!current) throw new Error("Data tugas tidak tersedia di penyimpanan offline.");
        const changes = {};
        ["judul", "keterangan", "tipe", "selesai"].forEach(function (field) {
            if (String(current[field] == null ? "" : current[field]) !== String(data[field])) changes[field] = data[field];
        });
        ["tenggat", "waktu_notifikasi"].forEach(function (field) {
            if (toDateInput(current[field]) !== data[field]) changes[field] = data[field];
        });
        if (String(current.deskripsi_foto || "") !== data.deskripsi_foto) changes.deskripsi_foto = data.deskripsi_foto;
        let photo = String(form.elements.namedItem("foto_base64")?.value || "");
        const file = form.elements.namedItem("foto")?.files?.[0];
        if (!photo && file) photo = await fileAsDataUrl(file);
        validateTask(Object.assign({}, current, changes), Boolean(photo), Object.prototype.hasOwnProperty.call(changes, "waktu_notifikasi"));
        const task = { id: todoId, changes: changes };
        if (photo) task.foto_base64 = photo;
        await enqueue(userId, view, "update", task);
        setStatus("Perubahan tugas disimpan offline.");
    }

    async function interceptDashboardSubmit(event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        const action = form.elements.namedItem("action")?.value;
        if (navigator.onLine && action !== "toggle") return;
        const view = form.elements.namedItem("return_view")?.value === "shared" ? "shared" : "personal";
        if (form.id !== "todo-form" && !form.matches("[data-edit-form]") && action !== "toggle" && action !== "delete") return;
        event.preventDefault();
        event.stopImmediatePropagation();
        if (form.dataset.offlineSubmitting === "true") return;
        if (!database || !currentUserId) {
            setStatus("Penyimpanan offline belum siap. Tunggu sebentar lalu coba lagi.");
            return;
        }
        if (action === "toggle") {
            const button = form.querySelector(".task-toggle");
            form.dataset.offlineSubmitting = "true";
            if (button) button.disabled = true;
            try {
                const id = Number(form.elements.namedItem("todo_id").value);
                const current = (await readEffectiveTodos(currentUserId, view)).tasks.find(function (todo) {
                    return Number(todo.id) === id;
                });
                if (!current) throw new Error("Data tugas tidak tersedia di penyimpanan offline.");
                const row = form.closest(".task-item");
                if (!row) throw new Error("Baris tugas tidak ditemukan pada halaman.");
                const status = row.dataset.status === "completed" ? "0" : "1";
                await saveSnapshotStatus(currentUserId, view, id, status);
                updateDashboardTaskStatus(form, status);
                setStatus(navigator.onLine
                    ? "Status tugas tersimpan. Menyinkronkan ke server..."
                    : "Status tugas tersimpan secara lokal dan menunggu koneksi untuk disinkronkan.");
                if (navigator.onLine) await syncQueue();
            } catch (error) {
                console.error("[pwa] Perubahan status offline gagal disimpan:", error);
                setStatus("Status tugas belum tersimpan: " + error.message);
            } finally {
                delete form.dataset.offlineSubmitting;
                if (button) button.disabled = false;
            }
            return;
        }
        try {
            if (form.id === "todo-form") {
                await saveOfflineCreate(form, currentUserId, view);
            } else if (form.matches("[data-edit-form]")) {
                await saveOfflineUpdate(form, currentUserId, view);
            } else if (action === "delete") {
                await enqueue(currentUserId, view, "delete", { id: Number(form.elements.namedItem("todo_id").value) });
            }
            const nextView = form.id === "todo-form" && form.elements.namedItem("tipe")?.value === "shared"
                ? "shared"
                : view;
            window.location.href = new URL("offline.html?view=" + nextView, document.baseURI).href;
        } catch (error) {
            console.error("[pwa] Perubahan offline gagal disimpan:", error);
            setStatus(error.message);
        }
    }

    async function renderOfflineTasks() {
        if (!offlineApp) return;
        if (rendering) {
            renderAgain = true;
            return;
        }
        rendering = true;
        try {
            objectUrls.forEach(function (url) { URL.revokeObjectURL(url); });
            objectUrls = [];
            if (!currentUserId) {
                offlineApp.innerHTML = "";
                const emptyState = document.getElementById("offline-empty-state");
                if (emptyState) emptyState.hidden = false;
                updateQueueCount(0);
                setStatus("Tidak ada akun tersimpan. Login online sekali agar workspace dapat digunakan offline.");
                return;
            }
            const result = await readEffectiveTodos(currentUserId, currentView);
            const accountLabel = document.getElementById("offline-account-label");
            if (accountLabel) accountLabel.textContent = "Akun demo lokal " + currentUserId;
            const title = document.getElementById("offline-view-title");
            if (title) title.textContent = currentView === "shared" ? "Tugas Shared" : "Tugas Personal";
            document.querySelectorAll("[data-offline-view]").forEach(function (button) {
                button.classList.toggle("active", button.dataset.offlineView === currentView);
                button.setAttribute("aria-pressed", button.dataset.offlineView === currentView ? "true" : "false");
            });
            const empty = document.getElementById("offline-empty-state");
            if (empty) empty.hidden = result.tasks.length > 0;
            const allOperations = (await getAllQueue()).filter(function (operation) {
                return operation.userId === Number(currentUserId);
            });
            updateQueueCount(allOperations.length);
            if (!result.snapshot && result.tasks.length === 0) {
                setStatus("Belum ada data tersimpan untuk workspace ini. Buka dashboard saat online terlebih dahulu.");
            }
            const cards = [];
            for (const task of result.tasks) {
                const done = Number(task.selesai) === 1;
                const image = task.foto_base64 || (task.foto ? await offlineImageUrl(currentUserId, task.foto) : "");
                cards.push(
                    '<article class="offline-task' + (done ? ' is-done' : '') + '">' +
                    '<div class="offline-task-copy"><h3>' + escapeHtml(task.judul) + '</h3>' +
                    (task.keterangan ? '<p>' + escapeHtml(task.keterangan) + '</p>' : '') +
                    '<small>' + (task.tipe === "shared" ? "Shared" : "Personal") +
                    (task.tenggat ? " · Tenggat " + escapeHtml(task.tenggat) : "") +
                    (task._pending ? " · Belum disinkronkan" : "") + '</small>' +
                    (image ? '<img class="offline-task-photo" src="' + escapeHtml(image) + '" alt="' + escapeHtml(task.deskripsi_foto || task.judul) + '">' : '') +
                    '</div><div class="offline-task-actions">' +
                    '<button type="button" data-action="toggle" data-id="' + escapeHtml(task.id) + '">' + (done ? "Tandai proses" : "Tandai selesai") + '</button>' +
                    (task.is_owner ? '<button type="button" data-action="edit" data-id="' + escapeHtml(task.id) + '">Edit</button>' +
                    '<button type="button" data-action="delete" data-id="' + escapeHtml(task.id) + '">Hapus</button>' : '') +
                    '</div></article>'
                );
            }
            offlineApp.innerHTML = cards.join("");
            if (navigator.onLine) {
                setStatus(allOperations.length
                    ? "Online · " + allOperations.length + " perubahan menunggu sinkronisasi."
                    : "Online · data tersimpan dan sudah sinkron.");
            } else {
                setStatus("Offline · perubahan disimpan di perangkat ini.");
            }
        } catch (error) {
            console.error("[pwa] Data offline gagal ditampilkan:", error);
            setStatus("Data offline gagal dibaca: " + error.message);
        } finally {
            rendering = false;
            if (renderAgain) {
                renderAgain = false;
                renderOfflineTasks();
            }
        }
    }

    async function offlineImageUrl(userId, path) {
        const entry = await getImage(userId, path);
        if (!entry || !entry.blob) return "";
        const url = URL.createObjectURL(entry.blob);
        objectUrls.push(url);
        return url;
    }

    function setOfflineView(view) {
        currentView = view === "shared" ? "shared" : "personal";
        const categoryInput = document.querySelector("#offline-create-form [name='tipe']");
        if (categoryInput) categoryInput.value = currentView;
        const url = new URL(window.location.href);
        url.searchParams.set("view", currentView);
        history.replaceState(null, "", url);
        renderOfflineTasks();
    }

    async function submitOfflineCreate(event) {
        event.preventDefault();
        const form = event.currentTarget;
        if (!form.reportValidity()) return;
        try {
            const task = fromForm(form);
            const file = form.elements.namedItem("foto").files[0];
            task.foto_base64 = await fileAsDataUrl(file);
            validateTask(task, Boolean(task.foto_base64));
            const localId = "local-" + newOperationId();
            task.id = localId;
            task.user_id = currentUserId;
            task.is_owner = true;
            await enqueue(currentUserId, task.tipe === "shared" ? "shared" : currentView, "create", task, localId);
            form.reset();
            currentView = task.tipe === "shared" ? "shared" : "personal";
            await renderOfflineTasks();
            setStatus("Tugas disimpan offline. Perubahan akan dikirim saat koneksi kembali.");
        } catch (error) {
            console.error("[pwa] Tugas offline gagal disimpan:", error);
            setStatus(error.message);
        }
    }

    async function openOfflineEdit(todoId) {
        const result = await readEffectiveTodos(currentUserId, currentView);
        editTask = result.tasks.find(function (todo) { return String(todo.id) === String(todoId); });
        if (!editTask) {
            setStatus("Tugas tidak ditemukan di penyimpanan offline.");
            return;
        }
        const form = document.getElementById("offline-edit-form");
        form.elements.namedItem("id").value = editTask.id;
        form.elements.namedItem("judul").value = editTask.judul || "";
        form.elements.namedItem("keterangan").value = editTask.keterangan || "";
        form.elements.namedItem("tipe").value = editTask.tipe || "personal";
        form.elements.namedItem("selesai").value = String(editTask.selesai || 0);
        form.elements.namedItem("tenggat").value = toDateInput(editTask.tenggat);
        form.elements.namedItem("waktu_notifikasi").value = toDateInput(editTask.waktu_notifikasi);
        form.elements.namedItem("deskripsi_foto").value = editTask.deskripsi_foto || "";
        document.getElementById("offline-edit-dialog").showModal();
    }

    async function submitOfflineEdit(event) {
        event.preventDefault();
        const form = event.currentTarget;
        if (!form.reportValidity() || !editTask) return;
        try {
            const values = fromForm(form);
            const changes = {};
            ["judul", "keterangan", "tipe", "selesai", "deskripsi_foto"].forEach(function (field) {
                if (String(editTask[field] == null ? "" : editTask[field]) !== String(values[field])) changes[field] = values[field];
            });
            ["tenggat", "waktu_notifikasi"].forEach(function (field) {
                if (toDateInput(editTask[field]) !== values[field]) changes[field] = values[field];
            });
            const file = form.elements.namedItem("foto").files[0];
            const photo = await fileAsDataUrl(file);
            validateTask(Object.assign({}, editTask, changes), Boolean(photo), Object.prototype.hasOwnProperty.call(changes, "waktu_notifikasi"));
            const todo = { id: editTask.id, changes: changes };
            if (photo) todo.foto_base64 = photo;
            await enqueue(currentUserId, currentView, "update", todo);
            document.getElementById("offline-edit-dialog").close();
            editTask = null;
            await renderOfflineTasks();
            setStatus("Perubahan tugas disimpan offline.");
        } catch (error) {
            console.error("[pwa] Edit offline gagal disimpan:", error);
            setStatus(error.message);
        }
    }

    async function handleOfflineTaskAction(event) {
        const button = event.target.closest("[data-action]");
        if (!button) return;
        const id = button.dataset.id;
        const result = await readEffectiveTodos(currentUserId, currentView);
        const task = result.tasks.find(function (todo) { return String(todo.id) === String(id); });
        if (!task) return;
        try {
            if (button.dataset.action === "toggle") {
                await saveSnapshotStatus(
                    currentUserId,
                    currentView,
                    task.id,
                    Number(task.selesai) === 1 ? "0" : "1"
                );
            } else if (button.dataset.action === "edit") {
                await openOfflineEdit(id);
                return;
            } else if (button.dataset.action === "delete") {
                if (!window.confirm("Hapus tugas ini?")) return;
                await enqueue(currentUserId, currentView, "delete", { id: task.id });
            }
            await renderOfflineTasks();
            if (navigator.onLine) await syncQueue();
        } catch (error) {
            console.error("[pwa] Aksi offline gagal:", error);
            setStatus("Aksi belum tersimpan: " + error.message);
        }
    }

    async function initialize() {
        const dashboardData = window.PWA_OFFLINE_DATA;
        if (dashboardData && dashboardData.userId) {
            currentUserId = Number(dashboardData.userId);
            currentView = dashboardData.view === "shared" ? "shared" : "personal";
            document.addEventListener("submit", interceptDashboardSubmit, true);
        }
        try {
            if (dashboardData && dashboardData.userId) await saveFromDashboard(dashboardData);
        } catch (error) {
            console.error("[pwa] Snapshot dashboard gagal disimpan:", error);
            setStatus("Data belum dapat disiapkan untuk offline: " + error.message);
        }

        try {
            if (!currentUserId) {
                currentUserId = Number(localStorage.getItem("ironCitadelPwaUserId")) || 0;
            }
        } catch (error) {
            console.error("[pwa] Akun offline tidak dapat dibaca:", error);
        }
        currentView = offlineApp ? currentUrlView() : (dashboardData && dashboardData.view === "shared" ? "shared" : "personal");

        if (offlineApp) {
            if (!currentUserId) {
                setStatus("Tidak ada akun tersimpan. Login online sekali agar workspace dapat digunakan offline.");
            }
            document.querySelectorAll("[data-offline-view]").forEach(function (button) {
                button.addEventListener("click", function () { setOfflineView(button.dataset.offlineView); });
            });
            offlineApp.addEventListener("click", handleOfflineTaskAction);
            document.getElementById("offline-create-form").addEventListener("submit", submitOfflineCreate);
            document.getElementById("offline-edit-form").addEventListener("submit", submitOfflineEdit);
            document.getElementById("offline-edit-cancel").addEventListener("click", function () {
                document.getElementById("offline-edit-dialog").close();
            });
            const themeButton = document.getElementById("offline-theme-button");
            try {
                if (localStorage.getItem("todoMode") === "dark") document.body.classList.add("dark-mode");
            } catch (error) {
                console.warn("[pwa] Preferensi tema tidak dapat dibaca:", error);
            }
            if (themeButton) {
                themeButton.addEventListener("click", function () {
                    document.body.classList.toggle("dark-mode");
                    try {
                        localStorage.setItem("todoMode", document.body.classList.contains("dark-mode") ? "dark" : "light");
                    } catch (error) {
                        console.warn("[pwa] Preferensi tema tidak dapat disimpan:", error);
                    }
                    themeButton.textContent = document.body.classList.contains("dark-mode") ? "Light Mode" : "Dark Mode";
                });
                themeButton.textContent = document.body.classList.contains("dark-mode") ? "Light Mode" : "Dark Mode";
            }
            if (syncButton) syncButton.addEventListener("click", syncQueue);
            await renderOfflineTasks();
            const createCategory = document.querySelector("#offline-create-form [name='tipe']");
            if (createCategory) createCategory.value = currentView;
        }

        window.addEventListener("online", async function () {
            if (!currentUserId) return;
            await syncQueue();
            if (offlineApp) await renderOfflineTasks();
        });
        window.addEventListener("offline", function () {
            if (offlineApp) setStatus("Offline · perubahan akan disimpan di perangkat ini.");
        });

        if (currentUserId) {
            const operations = await getAllQueue();
            updateQueueCount(operations.filter(function (operation) {
                return operation.userId === Number(currentUserId);
            }).length);
            if (navigator.onLine && !dashboardData) await syncQueue();
        }
    }
}());
