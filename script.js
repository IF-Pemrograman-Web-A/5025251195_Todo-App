let todos = [
    {
        id: 1,
        judul: "Eksplorasi Tag Semantik HTML",
        keterangan: "Mempelajari penggunaan tag semantik pada HTML",
        selesai: true
    },
    {
        id: 2,
        judul: "Layout Dua Panel Flexbox",
        keterangan: "Membuat layout menggunakan Flexbox",
        selesai: false
    },
    {
        id: 3,
        judul: "Push Kode ke GitHub",
        keterangan: "Mengunggah project ke repository GitHub",
        selesai: false
    }
];


const taskList = document.querySelector(".task-list");

const inputJudul = document.getElementById("judul");

const inputKeterangan = document.getElementById("keterangan");

const tombolSimpan = document.getElementById("tombol-simpan");


const tombolMode = document.createElement("button");

tombolMode.textContent = "🌙 Dark Mode";

tombolMode.classList.add("mode-button");

document.body.insertBefore(
    tombolMode,
    document.querySelector(".main-container")
);


function tampilkanTodo() {

    taskList.innerHTML = "";


    todos.forEach(function(todo) {

        const li = document.createElement("li");

        li.classList.add("task-item");


        if (todo.selesai) {
            li.classList.add("done");
        }


        const isi = document.createElement("div");


        const judul = document.createElement("span");

        judul.textContent = todo.judul;


        const keterangan = document.createElement("small");

        keterangan.textContent = todo.keterangan;

        keterangan.classList.add("task-description");


        isi.appendChild(judul);

        isi.appendChild(keterangan);


        const bagianAksi = document.createElement("div");

        bagianAksi.classList.add("task-actions");


        const checkbox = document.createElement("input");

        checkbox.type = "checkbox";

        checkbox.checked = todo.selesai;

        checkbox.classList.add("task-checkbox");


        checkbox.addEventListener("change", function() {

            todo.selesai = checkbox.checked;

            tampilkanTodo();

        });


        const badge = document.createElement("span");

        badge.classList.add("badge");

        if (todo.selesai) {
            badge.textContent = "Selesai";
        } else {
            badge.textContent = "Proses";
        }


        const tombolEdit = document.createElement("button");

        tombolEdit.textContent = "Edit";

        tombolEdit.classList.add("edit-button");


        tombolEdit.addEventListener("click", function() {

            const judulBaru = prompt(
                "Edit judul tugas:",
                todo.judul
            );


            if (
                judulBaru !== null &&
                judulBaru.trim() !== ""
            ) {
                todo.judul = judulBaru;
            }


            const keteranganBaru = prompt(
                "Edit keterangan:",
                todo.keterangan
            );


            if (keteranganBaru !== null) {
                todo.keterangan = keteranganBaru;
            }


            tampilkanTodo();

        });


        const tombolHapus = document.createElement("button");

        tombolHapus.textContent = "Hapus";

        tombolHapus.classList.add("delete-button");


        tombolHapus.addEventListener("click", function() {

            const yakin = confirm(
                "Apakah kamu yakin ingin menghapus tugas ini?"
            );


            if (yakin) {

                todos = todos.filter(function(item) {

                    return item.id !== todo.id;

                });

                tampilkanTodo();
            }

        });


        bagianAksi.appendChild(checkbox);

        bagianAksi.appendChild(badge);

        bagianAksi.appendChild(tombolEdit);

        bagianAksi.appendChild(tombolHapus);


        li.appendChild(isi);

        li.appendChild(bagianAksi);


        taskList.appendChild(li);

    });

}


tombolSimpan.addEventListener("click", function() {

    const judul = inputJudul.value.trim();

    const keterangan = inputKeterangan.value.trim();


    if (judul === "") {

        alert("Judul tugas belum diisi!");

        return;
    }


    const todoBaru = {

        id: Date.now(),

        judul: judul,

        keterangan: keterangan,

        selesai: false

    };


    todos.push(todoBaru);


    tampilkanTodo();


    inputJudul.value = "";

    inputKeterangan.value = "";

});


tombolMode.addEventListener("click", function() {

    document.body.classList.toggle("dark-mode");


    if (document.body.classList.contains("dark-mode")) {

        tombolMode.textContent = "☀️ Light Mode";

    } else {

        tombolMode.textContent = "🌙 Dark Mode";

    }

});


tampilkanTodo();