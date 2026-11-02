const CACHE_NAME = "iron-citadel-shell-v14";
const SHELL_FILES = [
    "./offline.html",
    "./manifest.webmanifest",
    "./index.html",
    "./style.css",
    "./dashboard.css",
    "./dashboard.js",
    "./notification-scheduler.js",
    "./script.js",
    "./pwa.js",
    "./pwa-offline.js",
    "./icons/icon-192.png",
    "./icons/icon-512.png"
];

self.addEventListener("install", function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function(cache) {
                return cache.addAll(SHELL_FILES);
            })
            .then(function() {
                return self.skipWaiting();
            })
    );
});

self.addEventListener("activate", function(event) {
    event.waitUntil(
        caches.keys()
            .then(function(names) {
                return Promise.all(names.map(function(name) {
                    if (name.startsWith("iron-citadel-shell-") && name !== CACHE_NAME) {
                        return caches.delete(name);
                    }
                    return Promise.resolve(false);
                }));
            })
            .then(function() {
                return self.clients.claim();
            })
    );
});

self.addEventListener("fetch", function(event) {
    const request = event.request;
    if (request.method !== "GET") return;

    const requestUrl = new URL(request.url);
    if (requestUrl.origin !== self.location.origin) return;

    if (request.mode === "navigate") {
        event.respondWith(
            fetch(request).catch(function() {
                return caches.match("./offline.html");
            })
        );
        return;
    }

    const isShellAsset = SHELL_FILES.some(function(path) {
        return requestUrl.pathname === new URL(path, self.registration.scope).pathname;
    });
    if (!isShellAsset) return;

    event.respondWith(
        caches.match(request).then(function(cachedResponse) {
            return cachedResponse || fetch(request).then(function(response) {
                if (response.ok && response.type === "basic") {
                    const copy = response.clone();
                    caches.open(CACHE_NAME).then(function(cache) {
                        return cache.put(request, copy);
                    }).catch(function(error) {
                        console.error("[pwa] Gagal menyimpan aset ke cache:", error);
                    });
                }
                return response;
            });
        })
    );
});

self.addEventListener("message", function(event) {
    const data = event.data || {};

    if (data.type === "SHOW_TODO_NOTIFICATION") {
        const reply = event.ports && event.ports[0];
        const tag = String(data.tag || ("todo-" + data.recipientId + "-" + data.id));
        event.waitUntil(
            self.registration.getNotifications({ tag: tag }).then(function(existing) {
                if (existing.length) {
                    if (reply) reply.postMessage({ type: "TODO_NOTIFICATION_ALREADY_SHOWN" });
                    return;
                }
                return self.registration.showNotification("Pengingat Tugas", {
                    body: String(data.judul || "Ada tugas yang perlu diperiksa."),
                    tag: tag,
                    renotify: false,
                    data: {
                        id: Number(data.id),
                        url: String(data.url || self.registration.scope)
                    }
                }).then(function() {
                    if (reply) reply.postMessage({ type: "TODO_NOTIFICATION_SHOWN" });
                });
            }).catch(function(error) {
                console.error("Service Worker gagal menampilkan notifikasi:", error);
                if (reply) {
                    reply.postMessage({
                        type: "TODO_NOTIFICATION_ERROR",
                        message: "Browser menolak pemanggilan notifikasi."
                    });
                }
                if (event.source) {
                    event.source.postMessage({ type: "TODO_NOTIFICATION_ERROR", id: data.id });
                }
            })
        );
        return;
    }

    if (data.type === "CANCEL_TODO_NOTIFICATION") {
        const tag = String(data.tag || "");
        event.waitUntil(
            self.registration.getNotifications({ tag: tag }).then(function(notifications) {
                notifications.forEach(function(notification) { notification.close(); });
            }).catch(function(error) {
                console.error("Service Worker gagal membersihkan notifikasi:", error);
            })
        );
    }
});

self.addEventListener("notificationclick", function(event) {
    event.notification.close();
    const targetUrl = new URL(
        event.notification.data && event.notification.data.url
            ? event.notification.data.url
            : self.registration.scope,
        self.registration.scope
    ).toString();

    event.waitUntil(self.clients.matchAll({ type: "window", includeUncontrolled: true }).then(async function(clients) {
        for (const client of clients) {
            if (client.url.startsWith(self.registration.scope)) {
                const target = client.navigate ? await client.navigate(targetUrl) : client;
                return target.focus();
            }
        }
        if (self.clients.openWindow) return self.clients.openWindow(targetUrl);
    }));
});
