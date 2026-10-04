self.addEventListener(
    "install",
    function() {
        self.skipWaiting();
    }
);

self.addEventListener(
    "activate",
    function(event) {
        event.waitUntil(
            self.clients.claim()
        );
    }
);

self.addEventListener(
    "message",
    function(event) {
        if (!event.data) {
            return;
        }

        if (
            event.data.type ===
            "SHOW_TODO_NOTIFICATION"
        ) {
            const tag = "todo-" + event.data.id;

            event.waitUntil(
                self.registration
                    .getNotifications({ tag: tag })
                    .then(function(notifications) {
                        if (notifications.length) {
                            return;
                        }

                        return self.registration.showNotification(
                            "Pengingat Todo",
                            {
                                body: event.data.judul,
                                tag: tag,
                                renotify: false,
                                data: {
                                    id: event.data.id,
                                    url: self.registration.scope
                                }
                            }
                        );
                    })
                    .catch(function(error) {
                        console.error(error);

                        if (event.source) {
                            event.source.postMessage({
                                type: "TODO_NOTIFICATION_ERROR",
                                id: event.data.id
                            });
                        }
                    })
            );
        }

        if (
            event.data.type ===
            "CANCEL_TODO_NOTIFICATION"
        ) {
            const tag = "todo-" + event.data.id;

            event.waitUntil(
                self.registration
                    .getNotifications({ tag: tag })
                    .then(function(notifications) {
                        notifications.forEach(function(notification) {
                            notification.close();
                        });
                    })
                    .catch(function(error) {
                        console.error(error);

                        if (event.source) {
                            event.source.postMessage({
                                type: "TODO_NOTIFICATION_ERROR",
                                operation: "cancel",
                                id: event.data.id
                            });
                        }
                    })
            );
        }
    }
);

self.addEventListener(
    "notificationclick",
    function(event) {
        event.notification.close();

        event.waitUntil(
            self.clients
                .matchAll({
                    type: "window",
                    includeUncontrolled: true
                })
                .then(
                    function(clients) {
                        const targetUrl =
                            event.notification.data &&
                            event.notification.data.url
                                ? event.notification.data.url
                                : self.registration.scope;

                        for (
                            const client
                            of clients
                        ) {
                            if (
                                client.url.startsWith(
                                    self.registration.scope
                                ) &&
                                "focus" in
                                client
                            ) {
                                return client.focus();
                            }
                        }

                        if (
                            self.clients
                                .openWindow
                        ) {
                            return self.clients.openWindow(targetUrl);
                        }
                    }
                )
        );
    }
);