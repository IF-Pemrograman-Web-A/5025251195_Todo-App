const inputTugas = document.getElementById('inputTugas');
const btnTambah = document.getElementById('btnTambah');
const daftarTugas = document.getElementById('daftarTugas');

btnTambah.addEventListener('click', function() {
    if (inputTugas.value == '') {
        alert('Tugas tidak boleh kosong!');
        return;
    }

    let li = document.createElement('li');
    
    let span = document.createElement('span');
    span.innerText = inputTugas.value;
    span.className = 'teks-tugas';
    
    span.addEventListener('click', function() {
        span.classList.toggle('selesai');
    });

    let btnHapus = document.createElement('button');
    btnHapus.innerText = 'X';
    btnHapus.className = 'btn-hapus';
    
    btnHapus.addEventListener('click', function() {
        li.remove();
    });

    li.appendChild(span);
    li.appendChild(btnHapus);

    daftarTugas.appendChild(li);

    inputTugas.value = '';
});