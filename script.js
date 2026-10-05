let todos = [];
let database = null;
let databaseSiap = false;
let streamKamera = null;
let fotoSementara = null;
let serviceWorkerRegistration = null;
let notifikasiTimers = new Map();
const BATAS_UKURAN_FOTO = 5 * 1024 * 1024;

const taskListBelumSelesai = document.getElementById("todo-belum-selesai");
const taskListSudahSelesai = document.getElementById("todo-sudah-selesai");
const inputJudul = document.getElementById("judul");
const inputKeterangan = document.getElementById("keterangan");
const tombolSimpan = document.getElementById("tombol-simpan");
const tombolMode = document.getElementById("tombol-mode");
const tombolKamera = document.getElementById("tombol-kamera");
const tombolFile = document.getElementById("tombol-file");
const inputFile = document.getElementById("input-file");
const videoKamera = document.getElementById("preview-kamera");
const tombolJepret = document.getElementById("tombol-jepret");
const tombolStopKamera = document.getElementById("tombol-stop-kamera");
const canvasKamera = document.getElementById("canvas-kamera");
const previewFoto = document.getElementById("preview-foto");
const inputNotifikasi = document.getElementById("waktu-notifikasi");
const inputTenggat = document.getElementById("waktu-tenggat");
const inputDeskripsiFoto = document.getElementById("deskripsi-foto");
const statusAplikasi = document.getElementById("status-aplikasi");
const todoForm = document.getElementById("todo-form");

tombolSimpan.disabled = true;

function setStatus(pesan) {
    statusAplikasi.textContent = pesan;
}

if ("serviceWorker" in navigator) {
    navigator.serviceWorker.addEventListener(
        "message",
        function(event) {
            if (
                event.data &&
                event.data.type === "TODO_NOTIFICATION_ERROR"
            ) {
                setStatus(
                    event.data.operation === "cancel"
                        ? "Pengingat gagal dibatalkan oleh Service Worker."
                        : "Notifikasi gagal ditampilkan. Periksa izin dan dukungan browser."
                );
            }
        }
    );
}

function fokusElemen(id) {
    const elemen = document.getElementById(id);

    if (elemen) {
        requestAnimationFrame(function() {
            elemen.focus();
        });
    }
}

let requestDatabase = null;

try {
    requestDatabase = indexedDB.open("todoApp", 1);
} catch (error) {
    console.error(error);
    tombolSimpan.disabled = true;
    setStatus("Browser tidak dapat membuka penyimpanan Todo.");
}

if (requestDatabase) {
    requestDatabase.onupgradeneeded = function(event) {
        database = event.target.result;

        if (!database.objectStoreNames.contains("todos")) {
            database.createObjectStore("todos", {
                keyPath: "id"
            });
        }
    };

    requestDatabase.onsuccess = function(event) {
        database = event.target.result;
        database.onversionchange = function() {
            database.close();
            database = null;
            databaseSiap = false;
            tombolSimpan.disabled = true;
            setStatus("Database berubah di tab lain. Muat ulang aplikasi.");
        };
        databaseSiap = true;
        tombolSimpan.disabled = false;

        ambilTodos();
    };

    requestDatabase.onerror = function() {
        databaseSiap = false;
        tombolSimpan.disabled = true;

        setStatus(
            "Penyimpanan Todo tidak dapat digunakan."
        );
    };

    requestDatabase.onblocked = function() {
        setStatus(
            "Penyimpanan sedang digunakan tab lain. Tutup tab lain lalu muat ulang."
        );
    };
}

function ambilTodos() {
    if (!databaseSiap || !database) {
        return;
    }

    const transaksi = database.transaction(
        "todos",
        "readonly"
    );

    const store = transaksi.objectStore("todos");
    const request = store.getAll();

    request.onsuccess = function() {
        todos = request.result;
        tampilkanTodo();
        jadwalkanSemuaNotifikasi();
    };

    request.onerror = function() {
        setStatus(
            "Data Todo gagal dimuat."
        );
    };
}

function simpanTodo(todo) {
    return new Promise(function(resolve, reject) {
        if (!databaseSiap || !database) {
            reject(
                new Error(
                    "IndexedDB belum siap."
                )
            );

            return;
        }

        const transaksi = database.transaction(
            "todos",
            "readwrite"
        );

        const store = transaksi.objectStore("todos");
        store.put(todo);

        transaksi.oncomplete = function() {
            resolve();
        };

        transaksi.onerror = function() {
            reject(transaksi.error || new Error("Todo gagal disimpan."));
        };

        transaksi.onabort = function() {
            reject(transaksi.error || new Error("Penyimpanan Todo dibatalkan."));
        };
    });
}

function hapusTodo(id) {
    return new Promise(function(resolve, reject) {
        if (!databaseSiap || !database) {
            reject(new Error("IndexedDB belum siap."));

            return;
        }

        const transaksi = database.transaction(
            "todos",
            "readwrite"
        );

        const store = transaksi.objectStore("todos");
        store.delete(id);

        transaksi.oncomplete = function() {
            resolve();
        };

        transaksi.onerror = function() {
            reject(transaksi.error || new Error("Todo gagal dihapus."));
        };

        transaksi.onabort = function() {
            reject(transaksi.error || new Error("Penghapusan Todo dibatalkan."));
        };
    });
}

function updateModeButton() {
    if (
        document.body.classList.contains(
            "dark-mode"
        )
    ) {
        tombolMode.textContent = "Light Mode";
    } else {
        tombolMode.textContent = "Dark Mode";
    }

    tombolMode.setAttribute(
        "aria-pressed",
        String(
            document.body.classList.contains(
                "dark-mode"
            )
        )
    );
}

let modeTersimpan = null;

try {
    modeTersimpan =
        localStorage.getItem("todoMode");
} catch (error) {
    console.warn("Preferensi tema tidak dapat dibaca.", error);
}

if (modeTersimpan === "dark") {
    document.body.classList.add(
        "dark-mode"
    );
}

updateModeButton();

tombolMode.addEventListener(
    "click",
    function() {
        document.body.classList.toggle(
            "dark-mode"
        );

        const modeGelap =
            document.body.classList.contains(
                "dark-mode"
            );

        try {
            localStorage.setItem(
                "todoMode",
                modeGelap
                    ? "dark"
                    : "light"
            );
        } catch (error) {
            console.warn("Preferensi tema tidak dapat disimpan.", error);
        }

        updateModeButton();

        setStatus(
            modeGelap
                ? "Mode gelap diaktifkan."
                : "Mode terang diaktifkan."
        );
    }
);

tombolFile.addEventListener(
    "click",
    function() {
        inputFile.click();
    }
);

inputFile.addEventListener(
    "change",
    function() {
        const file =
            inputFile.files[0];

        if (!file) {
            return;
        }

        if (
            !file.type.startsWith(
                "image/"
            )
        ) {
            inputFile.value = "";

            setStatus(
                "File yang dipilih harus berupa gambar."
            );

            return;
        }

        if (file.size > BATAS_UKURAN_FOTO) {
            inputFile.value = "";
            setStatus("Ukuran foto maksimal 5 MB.");
            return;
        }

        inputDeskripsiFoto.required = true;
        tombolSimpan.disabled = true;

        const reader =
            new FileReader();

        reader.onload =
            function(event) {
                fotoSementara =
                    event.target.result;

                previewFoto.src =
                    fotoSementara;

                previewFoto.alt =
                    inputDeskripsiFoto.value.trim() ||
                    "Pratinjau foto tugas";

                previewFoto.hidden =
                    false;

                tombolSimpan.disabled =
                    !databaseSiap;

                hentikanKamera();

                videoKamera.hidden =
                    true;

                tombolJepret.hidden =
                    true;

                setStatus(
                    "Foto dari file berhasil dipilih."
                );
            };

        reader.onerror =
            function() {
                inputDeskripsiFoto.required =
                    Boolean(fotoSementara);
                inputFile.value = "";
                tombolSimpan.disabled =
                    !databaseSiap;

                setStatus(
                    "Foto gagal dibaca."
                );
            };

        reader.readAsDataURL(
            file
        );
    }
);

tombolKamera.addEventListener(
    "click",
    async function() {
        if (
            !navigator.mediaDevices ||
            !navigator.mediaDevices.getUserMedia
        ) {
            setStatus(
                "Browser tidak mendukung akses kamera."
            );

            return;
        }

        try {
            hentikanKamera();

            streamKamera =
                await navigator.mediaDevices.getUserMedia(
                    {
                        video: true
                    }
                );

            videoKamera.srcObject =
                streamKamera;

            videoKamera.hidden =
                false;

            tombolStopKamera.hidden =
                false;

            tombolJepret.hidden =
                false;

            setStatus(
                "Kamera aktif."
            );
        } catch (error) {
            console.error(error);

            hentikanKamera();

            setStatus(
                "Kamera tidak dapat digunakan. Periksa izin kamera."
            );
        }
    }
);

tombolStopKamera.addEventListener(
    "click",
    function() {
        hentikanKamera();
        tombolKamera.focus();
        setStatus("Kamera dihentikan.");
    }
);

tombolJepret.addEventListener(
    "click",
    function() {
        if (!streamKamera) {
            return;
        }

        if (
            videoKamera.videoWidth === 0 ||
            videoKamera.videoHeight === 0
        ) {
            setStatus(
                "Kamera belum siap."
            );

            hentikanKamera();
            return;
        }

        try {
            const lebar =
                videoKamera.videoWidth;

            const tinggi =
                videoKamera.videoHeight;

            const skala = Math.min(
                1,
                1600 / Math.max(lebar, tinggi)
            );

            canvasKamera.width =
                Math.round(lebar * skala);

            canvasKamera.height =
                Math.round(tinggi * skala);

            const context =
                canvasKamera.getContext(
                    "2d"
                );

            if (!context) {
                throw new Error("Canvas 2D tidak tersedia.");
            }

            context.drawImage(
                videoKamera,
                0,
                0,
                canvasKamera.width,
                canvasKamera.height
            );

            const fotoBaru =
                canvasKamera.toDataURL(
                    "image/jpeg",
                    0.8
                );

            if (!fotoBaru.startsWith("data:image/jpeg;base64,")) {
                throw new Error("Hasil capture kamera bukan JPEG yang valid.");
            }

            fotoSementara = fotoBaru;
            inputDeskripsiFoto.required = true;

            previewFoto.src =
                fotoSementara;

            previewFoto.alt =
                inputDeskripsiFoto.value.trim() ||
                "Pratinjau foto tugas";

            previewFoto.hidden =
                false;

            setStatus(
                "Foto berhasil diambil."
            );
        } catch (error) {
            console.error("Capture kamera gagal.", error);
            setStatus(
                "Foto gagal diambil atau diproses. Silakan coba lagi."
            );
        } finally {
            hentikanKamera();
        }
    }
);

inputDeskripsiFoto.addEventListener(
    "input",
    function() {
        if (!previewFoto.hidden) {
            previewFoto.alt =
                inputDeskripsiFoto.value.trim() ||
                "Pratinjau foto tugas";
        }
    }
);

function hentikanKamera() {
    if (streamKamera) {
        streamKamera
            .getTracks()
            .forEach(
                function(track) {
                    track.stop();
                }
            );
    }

    streamKamera = null;
    videoKamera.srcObject = null;
    videoKamera.hidden = true;
    tombolJepret.hidden = true;
    tombolStopKamera.hidden = true;
}

async function siapkanServiceWorker() {
    if (
        !("serviceWorker" in navigator)
    ) {
        return null;
    }

    try {
        await navigator.serviceWorker.register(
            "service-worker.js"
        );

        serviceWorkerRegistration =
            await navigator.serviceWorker.ready;

        return serviceWorkerRegistration;
    } catch (error) {
        console.error(error);

        setStatus(
            "Service Worker tidak dapat digunakan."
        );

        return null;
    }
}

async function mintaIzinNotifikasi() {
    if (
        !("Notification" in window)
    ) {
        setStatus(
            "Browser tidak mendukung notifikasi."
        );

        return false;
    }

    if (
        Notification.permission ===
        "granted"
    ) {
        return true;
    }

    if (
        Notification.permission ===
        "denied"
    ) {
        setStatus(
            "Izin notifikasi ditolak di browser."
        );

        return false;
    }

    let permission;

    try {
        permission =
            await Notification.requestPermission();
    } catch (error) {
        console.error(error);
        setStatus(
            "Izin notifikasi tidak dapat diminta."
        );
        return false;
    }

    if (
        permission !== "granted"
    ) {
        setStatus(
            "Izin notifikasi belum diberikan."
        );

        return false;
    }

    return true;
}

function batalkanTimerNotifikasi(id) {
    const timer =
        notifikasiTimers.get(id);

    if (timer) {
        clearTimeout(timer);

        notifikasiTimers.delete(id);
    }

}

function batalkanNotifikasi(id) {
    batalkanTimerNotifikasi(id);

    return kirimPesanServiceWorker({
        type: "CANCEL_TODO_NOTIFICATION",
        id: id
    });
}

function kirimPesanServiceWorker(pesan) {
    function kirim(registration) {
        if (!registration.active) {
            throw new Error("Service Worker belum aktif.");
        }

        registration.active.postMessage(pesan);
    }

    let pengiriman;

    if (serviceWorkerRegistration) {
        pengiriman = Promise.resolve().then(function() {
            kirim(serviceWorkerRegistration);
        });
    } else if ("serviceWorker" in navigator) {
        pengiriman = navigator.serviceWorker.getRegistration().then(
            function(registration) {
                if (!registration) {
                    throw new Error("Service Worker belum terdaftar.");
                }

                kirim(registration);
            }
        );
    } else {
        pengiriman = Promise.reject(
            new Error("Browser tidak mendukung Service Worker.")
        );
    }

    return pengiriman.catch(function(error) {
        console.error("Pesan Service Worker gagal dikirim.", error);
        setStatus(
            pesan.type === "CANCEL_TODO_NOTIFICATION"
                ? "Pengingat gagal dibatalkan oleh Service Worker."
                : "Notifikasi gagal dikirim ke Service Worker."
        );

        return false;
    });
}

async function kirimNotifikasiKeServiceWorker(
    todo
) {
    try {
        if (
            !serviceWorkerRegistration
        ) {
            serviceWorkerRegistration =
                await navigator.serviceWorker.ready;
        }

        if (!serviceWorkerRegistration.active) {
            setStatus("Service Worker belum aktif; notifikasi tidak terkirim.");
            return;
        }

        serviceWorkerRegistration.active.postMessage({
            type:
                "SHOW_TODO_NOTIFICATION",
            id:
                todo.id,
            judul:
                todo.judul
        });
    } catch (error) {
        console.error(error);
        setStatus("Notifikasi gagal dikirim oleh Service Worker.");
    }
}

async function jadwalkanNotifikasi(
    todo
) {
    batalkanTimerNotifikasi(
        todo.id
    );

    if (todo.selesai) {
        batalkanNotifikasi(todo.id);
        return;
    }

    const waktuTenggat = todo.tenggat
        ? new Date(todo.tenggat).getTime()
        : NaN;
    const waktuNotifikasi = todo.waktuNotifikasi
        ? new Date(todo.waktuNotifikasi).getTime()
        : NaN;
    let terakhirDiperiksa = Date.now();

    function periksaWaktuPengingat() {
        const sekarang = Date.now();
        notifikasiTimers.delete(todo.id);
        perbaruiBadgeTodo(todo);

        const tenggatBaruLewat =
            !todo.selesai &&
            Number.isFinite(waktuTenggat) &&
            waktuTenggat > terakhirDiperiksa &&
            waktuTenggat <= sekarang;

        const pengingatBaruTiba =
            Number.isFinite(waktuNotifikasi) &&
            waktuNotifikasi > terakhirDiperiksa &&
            waktuNotifikasi <= sekarang;

        const pengumuman = [];

        if (tenggatBaruLewat) {
            pengumuman.push(
                "Tenggat tugas " + todo.judul + " terlewat."
            );
        }

        if (pengingatBaruTiba) {
            pengumuman.push(
                "Waktu pengingat untuk " + todo.judul + " tiba."
            );
        }

        if (pengumuman.length) {
            setStatus(pengumuman.join(" "));
        }

        if (
            pengingatBaruTiba &&
            "Notification" in window &&
            Notification.permission === "granted"
        ) {
            kirimNotifikasiKeServiceWorker(todo);
        }

        terakhirDiperiksa = sekarang;

        const waktuBerikutnya = [
            waktuTenggat,
            waktuNotifikasi
        ].filter(function(waktu) {
            return Number.isFinite(waktu) && waktu > sekarang;
        }).sort(function(a, b) {
            return a - b;
        })[0];

        if (!waktuBerikutnya) {
            return;
        }

        const timer = setTimeout(
            periksaWaktuPengingat,
            Math.min(
                waktuBerikutnya - sekarang,
                2147483647
            )
        );

        notifikasiTimers.set(todo.id, timer);
    }

    const sekarang = Date.now();
    const waktuPertama = [
        waktuTenggat,
        waktuNotifikasi
    ].filter(function(waktu) {
        return Number.isFinite(waktu) && waktu > sekarang;
    }).sort(function(a, b) {
        return a - b;
    })[0];

    if (waktuPertama) {
        const timer = setTimeout(
            periksaWaktuPengingat,
            Math.min(
                waktuPertama - sekarang,
                2147483647
            )
        );

        notifikasiTimers.set(todo.id, timer);
    }
}

function jadwalkanSemuaNotifikasi() {
    const now = Date.now();
    const pengingatTerlewat = todos.filter(function(todo) {
        const waktu = todo.waktuNotifikasi
            ? new Date(todo.waktuNotifikasi).getTime()
            : NaN;

        return !todo.selesai && Number.isFinite(waktu) && waktu <= now;
    }).length;

    if (pengingatTerlewat) {
        setStatus(
            pengingatTerlewat +
            " waktu pengingat terlewat saat aplikasi tidak aktif; notifikasi tidak diputar ulang."
        );
    }

    todos.forEach(
        function(todo) {
            jadwalkanNotifikasi(
                todo
            );
        }
    );
}

function formatTanggal(waktu) {
    if (!waktu) {
        return "";
    }

    const tanggal =
        new Date(waktu);

    if (
        Number.isNaN(
            tanggal.getTime()
        )
    ) {
        return "";
    }

    return tanggal.toLocaleString(
        "id-ID",
        {
            dateStyle:
                "medium",
            timeStyle:
                "short"
        }
    );
}

function buatFieldEdit(
    parent,
    id,
    labelText,
    value,
    type
) {
    const group =
        document.createElement(
            "div"
        );

    group.classList.add(
        "editing-field"
    );

    const label =
        document.createElement(
            "label"
        );

    label.setAttribute(
        "for",
        id
    );

    label.textContent =
        labelText;

    let field;

    if (
        type === "textarea"
    ) {
        field =
            document.createElement(
                "textarea"
            );

        field.rows = 3;
    } else {
        field =
            document.createElement(
                "input"
            );

        field.type = type;
    }

    field.id = id;

    field.value =
        value || "";

    group.appendChild(
        label
    );

    group.appendChild(
        field
    );

    parent.appendChild(
        group
    );

    return field;
}

function modeEditTodo(
    li,
    todo
) {
    li.innerHTML = "";

    li.classList.add(
        "editing"
    );

    const container =
        document.createElement(
            "div"
        );

    container.classList.add(
        "editing-container"
    );

    const inputJudulEdit =
        buatFieldEdit(
            container,
            "edit-judul-" +
                todo.id,
            "Judul",
            todo.judul,
            "text"
        );

    const textareaEdit =
        buatFieldEdit(
            container,
            "edit-keterangan-" +
                todo.id,
            "Keterangan",
            todo.keterangan,
            "textarea"
        );

    const inputWaktuEdit =
        buatFieldEdit(
            container,
            "edit-notifikasi-" +
                todo.id,
            "Waktu Notifikasi",
            todo.waktuNotifikasi,
            "datetime-local"
        );

    const inputTenggatEdit =
        buatFieldEdit(
            container,
            "edit-tenggat-" + todo.id,
            "Tenggat Tugas",
            todo.tenggat,
            "datetime-local"
        );

    const inputDeskripsiFotoEdit =
        buatFieldEdit(
            container,
            "edit-alt-foto-" + todo.id,
            "Deskripsi Foto",
            todo.deskripsiFoto,
            "text"
        );

    inputDeskripsiFotoEdit.required =
        Boolean(todo.foto);

    if (todo.foto) {
        const foto =
            document.createElement(
                "img"
            );

        foto.src =
            todo.foto;

        foto.alt =
            todo.deskripsiFoto ||
            "Foto tugas " + todo.judul;

        container.appendChild(
            foto
        );
    }

    const groupFile =
        document.createElement(
            "div"
        );

    groupFile.classList.add(
        "editing-field"
    );

    const idFile =
        "edit-file-" +
        todo.id;

    const labelFile =
        document.createElement(
            "label"
        );

    labelFile.setAttribute(
        "for",
        idFile
    );

    labelFile.textContent =
        "Ganti Foto";

    const inputFoto =
        document.createElement(
            "input"
        );

    inputFoto.type =
        "file";

    inputFoto.id =
        idFile;

    inputFoto.accept =
        "image/*";

    groupFile.appendChild(
        labelFile
    );

    groupFile.appendChild(
        inputFoto
    );

    container.appendChild(
        groupFile
    );

    let fotoEditBaru =
        todo.foto || null;

    inputFoto.addEventListener(
        "change",
        function() {
            const file =
                inputFoto.files[0];

            if (!file) {
                return;
            }

            if (
                !file.type.startsWith(
                    "image/"
                )
            ) {
                inputFoto.value =
                    "";

                setStatus(
                    "File yang dipilih harus berupa gambar."
                );

                return;
            }

            if (file.size > BATAS_UKURAN_FOTO) {
                inputFoto.value = "";
                setStatus("Ukuran foto maksimal 5 MB.");
                return;
            }

            inputDeskripsiFotoEdit.required = true;
            tombolSimpanEdit.disabled = true;

            const reader =
                new FileReader();

            reader.onload =
                function(event) {
                    fotoEditBaru =
                        event.target.result;

                    tombolSimpanEdit.disabled =
                        false;

                    setStatus(
                        "Foto baru berhasil dipilih."
                    );
                };

            reader.onerror =
                function() {
                    inputDeskripsiFotoEdit.required =
                        Boolean(fotoEditBaru);
                    tombolSimpanEdit.disabled =
                        false;

                    setStatus("Foto gagal dibaca.");
                };

            reader.readAsDataURL(
                file
            );
        }
    );

    const tombolContainer =
        document.createElement(
            "div"
        );

    tombolContainer.classList.add(
        "editing-buttons"
    );

    const tombolSimpanEdit =
        document.createElement(
            "button"
        );

    tombolSimpanEdit.type =
        "button";

    tombolSimpanEdit.textContent =
        "Simpan";

    tombolSimpanEdit.id =
        "edit-simpan-" +
        todo.id;

    const tombolBatalEdit =
        document.createElement(
            "button"
        );

    tombolBatalEdit.type =
        "button";

    tombolBatalEdit.textContent =
        "Batal";

    tombolBatalEdit.id =
        "edit-batal-" +
        todo.id;

    tombolContainer.appendChild(
        tombolSimpanEdit
    );

    tombolContainer.appendChild(
        tombolBatalEdit
    );

    container.appendChild(
        tombolContainer
    );

    tombolSimpanEdit.addEventListener(
        "click",
        async function() {
            const judulBaru =
                inputJudulEdit.value.trim();

            if (!judulBaru) {
                setStatus(
                    "Judul tugas belum diisi."
                );

                inputJudulEdit.focus();

                return;
            }

            if (
                fotoEditBaru &&
                !inputDeskripsiFotoEdit.value.trim()
            ) {
                setStatus(
                    "Deskripsi foto perlu diisi agar gambar dapat diakses."
                );
                inputDeskripsiFotoEdit.focus();
                return;
            }

            const waktuNotifikasiBaru =
                inputWaktuEdit.value;

            if (waktuNotifikasiBaru) {
                const waktuBaru =
                    new Date(
                        waktuNotifikasiBaru
                    ).getTime();

                if (
                    Number.isNaN(waktuBaru) ||
                    waktuBaru <= Date.now()
                ) {
                    setStatus(
                        "Waktu notifikasi harus berada di masa depan."
                    );
                    inputWaktuEdit.focus();
                    return;
                }
            }

            let izinNotifikasiPromise = null;

            if (waktuNotifikasiBaru) {
                izinNotifikasiPromise =
                    mintaIzinNotifikasi().catch(
                        function(error) {
                            console.warn(
                                "Permintaan izin notifikasi gagal.",
                                error
                            );

                            return false;
                        }
                    );
            }

            const todoSebelumEdit = {
                ...todo
            };

            todo.judul =
                judulBaru;

            todo.keterangan =
                textareaEdit.value.trim();

            todo.waktuNotifikasi =
                waktuNotifikasiBaru;

            todo.tenggat =
                inputTenggatEdit.value;

            todo.foto =
                fotoEditBaru;

            todo.deskripsiFoto =
                inputDeskripsiFotoEdit.value.trim();

            try {
                await simpanTodo(
                    todo
                );

                batalkanNotifikasi(todo.id);

                tampilkanTodo();

                await jadwalkanNotifikasi(
                    todo
                );

                perbaruiBadgeTodo(todo);

                setStatus("Tugas berhasil diperbarui.");

                fokusElemen(
                    "todo-edit-" +
                        todo.id
                );

                if (izinNotifikasiPromise) {
                    izinNotifikasiPromise.then(
                        function(izinNotifikasi) {
                            if (!izinNotifikasi) {
                                setStatus(
                                    "Tugas diperbarui, tetapi izin notifikasi belum diberikan."
                                );
                            }
                        }
                    );
                }
            } catch (error) {
                console.error(error);

                Object.assign(
                    todo,
                    todoSebelumEdit
                );

                tampilkanTodo();

                fokusElemen(
                    "todo-edit-" +
                        todo.id
                );

                setStatus(
                    "Tugas gagal diperbarui."
                );
            }
        }
    );

    tombolBatalEdit.addEventListener(
        "click",
        function() {
            tampilkanTodo();

            fokusElemen(
                "todo-edit-" +
                    todo.id
            );

            setStatus(
                "Perubahan dibatalkan."
            );
        }
    );

    li.appendChild(
        container
    );

    inputJudulEdit.focus();
}

function statusTenggatTodo(todo) {
    if (!todo.tenggat) {
        return {
            teks: "Tanpa tenggat",
            kelas: "badge--none"
        };
    }

    const waktu = new Date(todo.tenggat).getTime();

    if (Number.isNaN(waktu)) {
        return {
            teks: "Tenggat tidak valid",
            kelas: "badge--invalid"
        };
    }

    if (todo.selesai) {
        return {
            teks: "Tenggat tercatat",
            kelas: "badge--deadline"
        };
    }

    if (waktu <= Date.now()) {
        return {
            teks: "Tenggat terlewat",
            kelas: "badge--overdue"
        };
    }

    return {
        teks: "Tenggat mendatang",
        kelas: "badge--deadline"
    };
}

function statusPengingatTodo(todo) {
    if (!todo.waktuNotifikasi) {
        return {
            teks: "Tanpa pengingat",
            kelas: "badge--none"
        };
    }

    if (todo.selesai) {
        return {
            teks: "Pengingat nonaktif",
            kelas: "badge--none"
        };
    }

    const waktu =
        new Date(todo.waktuNotifikasi).getTime();

    if (Number.isNaN(waktu)) {
        return {
            teks: "Pengingat tidak valid",
            kelas: "badge--invalid"
        };
    }

    if (waktu <= Date.now()) {
        return {
            teks: "Waktu pengingat lewat",
            kelas: "badge--overdue"
        };
    }

    if (!("Notification" in window)) {
        return {
            teks: "Notifikasi tidak didukung",
            kelas: "badge--none"
        };
    }

    if (Notification.permission === "denied") {
        return {
            teks: "Izin ditolak",
            kelas: "badge--none"
        };
    }

    if (Notification.permission !== "granted") {
        return {
            teks: "Izin diperlukan",
            kelas: "badge--none"
        };
    }

    return {
        teks: "Ada pengingat",
        kelas: "badge--reminder"
    };
}

function aturBadgeTodo(id, status) {
    const badge = document.getElementById(id);

    if (!badge) {
        return;
    }

    badge.className = "badge " + status.kelas;
    badge.textContent = status.teks;
}

function buatBadgeTodo(id, status) {
    const badge = document.createElement("span");

    badge.id = id;
    badge.className = "badge " + status.kelas;
    badge.textContent = status.teks;

    return badge;
}

function perbaruiBadgeTodo(todo) {
    aturBadgeTodo(
        "todo-deadline-" + todo.id,
        statusTenggatTodo(todo)
    );
    aturBadgeTodo(
        "todo-reminder-" + todo.id,
        statusPengingatTodo(todo)
    );
}

function buatAksiTodo(
    li,
    todo
) {
    const bagianAksi =
        document.createElement(
            "div"
        );

    bagianAksi.classList.add(
        "task-actions"
    );

    const checkbox =
        document.createElement(
            "input"
        );

    checkbox.type =
        "checkbox";

    checkbox.checked =
        todo.selesai;

    checkbox.classList.add(
        "task-checkbox"
    );

    checkbox.id =
        "todo-check-" +
        todo.id;

    checkbox.setAttribute(
        "aria-label",
        todo.selesai
            ? "Tandai " +
              todo.judul +
              " sebagai belum selesai"
            : "Tandai " +
              todo.judul +
              " sebagai selesai"
    );

    checkbox.addEventListener(
        "change",
        async function() {
            const statusBaru =
                checkbox.checked;

            todo.selesai =
                statusBaru;

            try {
                await simpanTodo(
                    todo
                );

                if (statusBaru) {
                    batalkanNotifikasi(todo.id);
                } else {
                    await jadwalkanNotifikasi(todo);
                }

                tampilkanTodo();

                fokusElemen(
                    "todo-check-" +
                        todo.id
                );

                setStatus(
                    statusBaru
                        ? "Tugas ditandai selesai."
                        : "Tugas ditandai belum selesai."
                );
            } catch (error) {
                console.error(error);

                todo.selesai =
                    !statusBaru;

                tampilkanTodo();

                fokusElemen(
                    "todo-check-" +
                        todo.id
                );

                setStatus(
                    "Perubahan status gagal disimpan."
                );
            }
        }
    );

    const badge = buatBadgeTodo(
        "todo-status-" + todo.id,
        todo.selesai
            ? {
                teks: "Selesai",
                kelas: "badge--done"
            }
            : {
                teks: "Proses",
                kelas: "badge--progress"
            }
    );

    const badgeTenggat = buatBadgeTodo(
        "todo-deadline-" + todo.id,
        statusTenggatTodo(todo)
    );

    const badgePengingat = buatBadgeTodo(
        "todo-reminder-" + todo.id,
        statusPengingatTodo(todo)
    );

    const tombolEdit =
        document.createElement(
            "button"
        );

    tombolEdit.type =
        "button";

    tombolEdit.textContent =
        "Edit";

    tombolEdit.classList.add(
        "edit-button"
    );

    tombolEdit.id =
        "todo-edit-" +
        todo.id;

    tombolEdit.setAttribute(
        "aria-label",
        "Edit tugas " +
        todo.judul
    );

    tombolEdit.addEventListener(
        "click",
        function() {
            modeEditTodo(
                li,
                todo
            );
        }
    );

    const tombolHapus =
        document.createElement(
            "button"
        );

    tombolHapus.type =
        "button";

    tombolHapus.textContent =
        "Hapus";

    tombolHapus.classList.add(
        "delete-button"
    );

    tombolHapus.id =
        "todo-delete-" +
        todo.id;

    tombolHapus.setAttribute(
        "aria-label",
        "Hapus tugas " +
        todo.judul
    );

    tombolHapus.addEventListener(
        "click",
        async function() {
            const yakin =
                window.confirm(
                    "Apakah kamu yakin ingin menghapus tugas ini?"
                );

            if (!yakin) {
                return;
            }

            const index =
                todos.findIndex(
                    function(item) {
                        return (
                            item.id ===
                            todo.id
                        );
                    }
                );

            const nextTodo =
                todos[index + 1] ||
                todos[index - 1];

            try {
                await hapusTodo(
                    todo.id
                );

                batalkanNotifikasi(
                    todo.id
                );

                todos =
                    todos.filter(
                        function(item) {
                            return (
                                item.id !==
                                todo.id
                            );
                        }
                    );

                tampilkanTodo();

                if (nextTodo) {
                    fokusElemen(
                        "todo-edit-" +
                            nextTodo.id
                    );
                } else {
                    inputJudul.focus();
                }

                setStatus(
                    "Tugas berhasil dihapus."
                );
            } catch (error) {
                console.error(error);

                setStatus(
                    "Tugas gagal dihapus."
                );
            }
        }
    );

    bagianAksi.appendChild(
        checkbox
    );

    bagianAksi.appendChild(
        badge
    );

    bagianAksi.appendChild(
        badgeTenggat
    );

    bagianAksi.appendChild(
        badgePengingat
    );

    bagianAksi.appendChild(
        tombolEdit
    );

    bagianAksi.appendChild(
        tombolHapus
    );

    return bagianAksi;
}

function tampilkanTodo() {
    taskListBelumSelesai.innerHTML = "";
    taskListSudahSelesai.innerHTML = "";

    todos.forEach(
        function(todo) {
            const li =
                document.createElement(
                    "li"
                );

            li.classList.add(
                "task-item"
            );

            if (todo.selesai) {
                li.classList.add(
                    "done"
                );
            }

            const isi =
                document.createElement(
                    "div"
                );

            const judul =
                document.createElement(
                    "span"
                );

            judul.textContent =
                todo.judul;

            isi.appendChild(
                judul
            );

            if (todo.keterangan) {
                const keterangan =
                    document.createElement(
                        "small"
                    );

                keterangan.classList.add(
                    "task-description"
                );

                keterangan.textContent =
                    todo.keterangan;

                isi.appendChild(
                    keterangan
                );
            }

            if (todo.foto) {
                const foto =
                    document.createElement(
                        "img"
                    );

                foto.src =
                    todo.foto;

                foto.alt =
                    todo.deskripsiFoto ||
                    "Foto tugas " + todo.judul;

                isi.appendChild(
                    foto
                );
            }

            if (todo.tenggat) {
                const tenggat =
                    document.createElement("small");

                tenggat.classList.add(
                    "task-description"
                );

                tenggat.textContent =
                    "Tenggat: " + formatTanggal(todo.tenggat);

                isi.appendChild(tenggat);
            }

            if (
                todo.waktuNotifikasi
            ) {
                const waktu =
                    document.createElement(
                        "small"
                    );

                waktu.classList.add(
                    "task-description"
                );

                waktu.textContent =
                    "Notifikasi: " +
                    formatTanggal(
                        todo.waktuNotifikasi
                    );

                isi.appendChild(
                    waktu
                );
            }

            const bagianAksi =
                buatAksiTodo(
                    li,
                    todo
                );

            li.appendChild(
                isi
            );

            li.appendChild(
                bagianAksi
            );

            const taskList = todo.selesai
                ? taskListSudahSelesai
                : taskListBelumSelesai;

            taskList.appendChild(
                li
            );
        }
    );
}

todoForm.addEventListener(
    "submit",
    async function(event) {
        event.preventDefault();

        if (!databaseSiap) {
            setStatus(
                "Penyimpanan belum siap."
            );

            return;
        }

        const judul =
            inputJudul.value.trim();

        const keterangan =
            inputKeterangan.value.trim();

        const waktuNotifikasi =
            inputNotifikasi.value;

        const tenggat =
            inputTenggat.value;

        const deskripsiFoto =
            inputDeskripsiFoto.value.trim();

        if (!judul) {
            setStatus(
                "Judul tugas belum diisi."
            );

            inputJudul.focus();

            return;
        }

        if (waktuNotifikasi) {
            const waktu =
                new Date(
                    waktuNotifikasi
                ).getTime();

            if (
                Number.isNaN(waktu) ||
                waktu <= Date.now()
            ) {
                setStatus(
                    "Waktu notifikasi harus berada di masa depan."
                );

                inputNotifikasi.focus();

                return;
            }
        }

        const idBaru =
            Date.now();

        const todoBaru = {
            id: idBaru,
            judul: judul,
            keterangan: keterangan,
            selesai: false,
            foto: fotoSementara,
            deskripsiFoto: deskripsiFoto,
            tenggat: tenggat,
            waktuNotifikasi:
                waktuNotifikasi
        };

        let izinNotifikasiPromise = null;

        if (waktuNotifikasi) {
            izinNotifikasiPromise =
                mintaIzinNotifikasi().catch(
                    function(error) {
                        console.warn(
                            "Permintaan izin notifikasi gagal.",
                            error
                        );

                        return false;
                    }
                );
        }

        try {
            await simpanTodo(
                todoBaru
            );

            todos.push(
                todoBaru
            );

            tampilkanTodo();

            await jadwalkanNotifikasi(
                todoBaru
            );

            perbaruiBadgeTodo(todoBaru);

            todoForm.reset();
            inputDeskripsiFoto.required = false;

            fotoSementara =
                null;

            previewFoto.src =
                "";

            previewFoto.hidden =
                true;

            videoKamera.hidden =
                true;

            tombolJepret.hidden =
                true;

            inputFile.value =
                "";

            hentikanKamera();

            const izinNotifikasi =
                izinNotifikasiPromise
                    ? await izinNotifikasiPromise
                    : true;

            setStatus(
                todoBaru.waktuNotifikasi &&
                !izinNotifikasi
                    ? "Tugas berhasil disimpan. Izin notifikasi belum diberikan."
                    : "Tugas berhasil disimpan."
            );

            fokusElemen(
                "todo-edit-" +
                    idBaru
            );
        } catch (error) {
            console.error(
                "Gagal menyimpan Todo ke IndexedDB.",
                error
            );

            setStatus(
                "Tugas gagal disimpan."
            );
        }
    }
);

window.addEventListener(
    "beforeunload",
    function() {
        hentikanKamera();

        notifikasiTimers.forEach(
            function(timer) {
                clearTimeout(timer);
            }
        );

        notifikasiTimers.clear();
    }
);

window.addEventListener(
    "load",
    async function() {
        await siapkanServiceWorker();

        inputJudul.focus();
    }
);