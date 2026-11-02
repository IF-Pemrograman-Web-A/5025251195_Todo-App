(function () {
    "use strict";

    const installButton = document.querySelector("[data-pwa-install]");
    const status = document.getElementById("pwa-install-status");
    let installPrompt = null;
    let appInstalled = false;
    let reloadingForWorkerUpdate = false;

    function setInstallStatus(message) {
        if (status) status.textContent = message;
    }

    const userId = document.body && document.body.dataset.userId;
    if (userId) {
        try {
            localStorage.setItem("ironCitadelPwaUserId", userId);
        } catch (error) {
            console.error("[pwa] Akun offline tidak dapat disimpan:", error);
        }
    }

    document.querySelectorAll("[data-pwa-logout]").forEach(function (link) {
        link.addEventListener("click", function () {
            try {
                localStorage.removeItem("ironCitadelPwaUserId");
            } catch (error) {
                console.error("[pwa] Akun offline tidak dapat dihapus:", error);
            }
        });
    });

    function showInstallInstructions() {
        setInstallStatus("Belum ada prompt instalasi dari browser. Untuk memasang di Microsoft Edge: 1) klik menu titik tiga, 2) pilih Apps, 3) pilih Install this site as an app.");
    }

    function showInstalledMessage(isStandalone) {
        setInstallStatus(isStandalone
            ? "The Iron Citadel sedang berjalan dalam mode aplikasi terinstal. Buka aplikasi ini melalui Desktop atau Start Menu."
            : "The Iron Citadel sudah terpasang di perangkat ini. Kamu dapat membuka aplikasi melalui Desktop, Start Menu, atau menu Apps di Microsoft Edge.");
    }

    if ("serviceWorker" in navigator) {
        if (navigator.serviceWorker.controller) {
            navigator.serviceWorker.addEventListener("controllerchange", function () {
                if (reloadingForWorkerUpdate) return;
                reloadingForWorkerUpdate = true;
                window.location.reload();
            });
        }

        const workerUrl = new URL("./service-worker.js", document.baseURI);
        navigator.serviceWorker.register(workerUrl.href)
            .then(function () {
                return navigator.serviceWorker.ready;
            })
            .then(function (registration) {
                console.info("[pwa] Service Worker aktif:", registration.scope);
            })
            .catch(function (error) {
                console.error("[pwa] Service Worker gagal didaftarkan:", error);
                setInstallStatus("Dukungan offline belum aktif. Muat ulang atau periksa koneksi.");
            });
    } else {
        setInstallStatus("Browser ini belum mendukung Service Worker.");
    }

    function isStandalone() {
        return window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;
    }

    window.addEventListener("beforeinstallprompt", function (event) {
        event.preventDefault();
        installPrompt = event;
        setInstallStatus("Aplikasi siap dipasang.");
    });

    window.addEventListener("appinstalled", function () {
        appInstalled = true;
        installPrompt = null;
        showInstalledMessage(isStandalone());
    });

    if (installButton) {
        installButton.addEventListener("click", async function () {
            if (appInstalled || isStandalone()) {
                showInstalledMessage(isStandalone());
                return;
            }

            if (!installPrompt) {
                showInstallInstructions();
                return;
            }

            const prompt = installPrompt;
            installPrompt = null;
            installButton.disabled = true;

            try {
                await prompt.prompt();
                const choice = await prompt.userChoice;
                if (choice.outcome === "accepted") {
                    setInstallStatus("Aplikasi sedang dipasang.");
                } else {
                    setInstallStatus("Instalasi dibatalkan. Kamu dapat mencoba lagi atau memasang melalui menu titik tiga > Apps > Install this site as an app.");
                }
            } catch (error) {
                console.error("[pwa] Prompt instalasi gagal:", error);
                showInstallInstructions();
            } finally {
                installButton.disabled = false;
            }
        });
    }

    if (isStandalone()) {
        showInstalledMessage(true);
    }
}());
