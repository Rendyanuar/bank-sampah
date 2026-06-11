<?php
session_start();

// Mencegah browser menyimpan cache halaman ini
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

include 'koneksi.php';
$pesan = "";
$registrasi_sukses = false; 

// ================================================================
// LOGIKA PERSIAPAN NOMOR ANGGOTA OTOMATIS SAAT HALAMAN DIBUKA
// ================================================================
$query_last = mysqli_query($koneksi, "SELECT username FROM users WHERE role = 'nasabah' ORDER BY id DESC LIMIT 1");
$next_nomor = "";

if (mysqli_num_rows($query_last) > 0) {
    $data_last = mysqli_fetch_assoc($query_last);
    $last_nomor = $data_last['username'];
    
    // Pecah nomor terakhir
    $pecah = explode(",", $last_nomor);
    if (count($pecah) > 1) {
        $prefix = $pecah[0]; // Bagian "100117"
        $urut = (int)$pecah[1]; // Bagian "00001" jadi angka 1
        $urut++; // Tambah urutannya
        $next_nomor = $prefix . "," . str_pad($urut, 5, "0", STR_PAD_LEFT);
    } else {
        $next_nomor = "100117,00001"; 
    }
} else {
    // Jika belum ada nasabah sama sekali
    $next_nomor = "100117,00001";
}

// ================================================================
// PROSES PENYIMPANAN DATA SAAT TOMBOL DAFTAR DITEKAN
// ================================================================
if (isset($_POST['submit_registrasi'])) {
    $nama_lengkap  = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $username      = mysqli_real_escape_string($koneksi, $_POST['username']); 
    $nomor_telepon = mysqli_real_escape_string($koneksi, $_POST['nomor_telepon']); 
    $password      = $_POST['password'];
    $konfirmasi    = $_POST['konfirmasi_password'];
    $role          = "nasabah"; 

    // Validasi apakah tombol generate sudah ditekan
    if (empty($username)) {
        $pesan = "<div class='alert error'>Silakan klik tombol 'Buat Nomor Anggota Baru' terlebih dahulu!</div>";
    } 
    // Validasi Password
    else if ($password !== $konfirmasi) {
        $pesan = "<div class='alert error'>Password dan Konfirmasi Password tidak cocok!</div>";
    } 
    else {
        // CEK GANDA: Username ATAU Nomor Telepon
        // Filter nomor telepon khusus yang tidak kosong atau bukan tanda strip "-"
        $cek_hp_query = ($nomor_telepon != "" && $nomor_telepon != "-") ? " OR nomor_telepon = '$nomor_telepon'" : "";
        
        $cek_ganda = mysqli_query($koneksi, "SELECT username, nomor_telepon FROM users WHERE username = '$username' $cek_hp_query");
        
        if (mysqli_num_rows($cek_ganda) > 0) {
            $data_ganda = mysqli_fetch_assoc($cek_ganda);
            
            if ($data_ganda['nomor_telepon'] == $nomor_telepon && $nomor_telepon != "-") {
                $pesan = "<div class='alert error'><i class='fa fa-exclamation-triangle'></i> Nomor telepon <b>$nomor_telepon</b> sudah terdaftar! Silakan gunakan nomor lain.</div>";
            } else {
                $pesan = "<div class='alert error'><i class='fa fa-exclamation-triangle'></i> Nomor Anggota ini baru saja diambil orang lain. Silakan buat nomor baru.</div>";
            }
        } else {
            // Simpan ke database
            $query = "INSERT INTO users (username, password, nama_lengkap, nomor_telepon, role) 
                      VALUES ('$username', '$password', '$nama_lengkap', '$nomor_telepon', '$role')";
            
            if (mysqli_query($koneksi, $query)) {
                $registrasi_sukses = true;
                $_POST = array(); // Kosongkan form
            } else {
                $pesan = "<div class='alert error'>Gagal menyimpan data ke database.</div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Registrasi - Bank Sampah Induk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-image: url('assets/bg-lingkungan.jpg?v=<?php echo time(); ?>'); 
            background-size: cover;          
            background-position: center;     
            background-attachment: fixed;    
            background-repeat: no-repeat;
            display: flex; 
            flex-direction: column; 
            align-items: center;
            justify-content: center; 
            padding: 40px 20px; 
            margin: 0; 
            min-height: 100vh;
            box-sizing: border-box;
        }

        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 1;
            pointer-events: none;
        }

        /* EFEK GLASSMORPHISM KOTAK UTAMA */
        .container { 
            position: relative;
            z-index: 10;
            background-color: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.5); 
            padding: 45px 40px; 
            border-radius: 16px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.2); 
            width: 100%; 
            max-width: 550px; 
            border-top: 6px solid #2e7d32; 
            box-sizing: border-box;
        }

        .title-wrapper { text-align: center; margin-bottom: 30px; }
        .title-wrapper h2 { margin: 0; color: #2e7d32; letter-spacing: 1.5px; font-weight: 800; font-size: 24px; text-shadow: 0 1px 2px rgba(255,255,255,0.8);}
        .title-line { height: 4px; background-color: #1abc9c; width: 60px; margin: 10px auto 0; border-radius: 2px;}

        /* SPASI UNTUK SETIAP INPUT */
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; color: #222; font-size: 14px; font-weight: 700;}
        
        .input-wrapper { 
            display: flex; 
            border: 1px solid rgba(200, 200, 200, 0.8); 
            border-radius: 8px; 
            background-color: rgba(255, 255, 255, 0.9); 
            overflow: hidden;
            transition: 0.3s;
        }
        .input-wrapper:focus-within { border-color: #1abc9c; box-shadow: 0 0 8px rgba(26, 188, 156, 0.3); background-color: #ffffff;}
        .input-wrapper .icon { padding: 12px 15px; background-color: rgba(241, 241, 241, 0.8); color: #2e7d32; border-right: 1px solid rgba(200, 200, 200, 0.5); display: flex; align-items: center; width: 20px; justify-content: center; }
        .input-wrapper input { flex: 1; padding: 12px 15px; border: none; background: transparent; outline: none; font-size: 14px; color: #333; width: 100%;}
        
        input::-ms-reveal,
        input::-ms-clear { display: none; }
        
        .input-wrapper .icon-toggle {
            padding: 12px 15px;
            color: #888;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            transition: 0.2s;
            user-select: none;
        }
        .input-wrapper .icon-toggle:hover { color: #1abc9c; }

        /* KOTAK NOMOR ANGGOTA KHUSUS (Readonly) */
        .wrapper-readonly { background-color: #e9ecef !important; border-color: #ccc !important; }
        .wrapper-readonly input { font-weight: bold; color: #16a085 !important; font-size: 16px; letter-spacing: 1px; cursor: not-allowed; text-align: center;}
        
        /* PEMBUNGKUS AREA TENGAH UNTUK TOMBOL DAN NOTE */
        .center-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 12px;
        }

        .btn-generate {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px; 
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
            box-shadow: 0 4px 6px rgba(52,152,219,0.3);
        }
        .btn-generate:hover { background-color: #2980b9; transform: translateY(-2px); box-shadow: 0 6px 10px rgba(52,152,219,0.4);}
        
        .note-salin {
            font-size: 12px;
            color: #e74c3c;
            margin-top: 10px;
            font-weight: 700;
            text-align: center;
            display: none; 
            animation: fadeIn 0.5s;
        }
        
        /* CATATAN KECIL DI BAWAH INPUT */
        .input-note {
            font-size: 11px;
            color: #7f8c8d;
            margin-top: 6px;
            font-weight: 600;
            line-height: 1.4;
        }

        .button-group { display: flex; gap: 15px; margin-top: 35px; }
        .btn-back, .btn-submit { background-color: #1abc9c; color: white; border: none; border-radius: 25px; cursor: pointer; text-decoration: none; transition: 0.3s; text-align: center; box-shadow: 0 4px 6px rgba(26,188,156,0.2);}
        .btn-back { padding: 12px 25px; display: flex; align-items: center; justify-content: center;}
        .btn-submit { padding: 12px; flex: 1; font-weight: bold; letter-spacing: 1px; font-size: 15px;}
        .btn-back:hover, .btn-submit:hover { background-color: #16a085; transform: translateY(-2px); box-shadow: 0 6px 12px rgba(26,188,156,0.3);}
        
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500;}
        .alert.error { background-color: rgba(253, 237, 237, 0.9); color: #e53935; border: 1px solid #ffcdd2; border-left: 5px solid #e53935; }

        /* KOTAK INFO LINGKUNGAN */
        .info-box {
            position: relative;
            z-index: 10;
            margin-top: 25px;
            background: linear-gradient(135deg, rgba(26, 188, 156, 0.85) 0%, rgba(46, 125, 50, 0.85) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 550px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            box-sizing: border-box;
            animation: fadeInUp 0.8s ease-out;
        }
        .info-box i { font-size: 30px; color: #dcedc8; }
        .info-box p { margin: 0; font-size: 14px; font-weight: 500; line-height: 1.5; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }

        /* CUSTOM TOAST NOTIFICATION */
        .toast-overlay-box {
            visibility: hidden;
            min-width: 250px;
            background-color: #2c3e50;
            color: #fff;
            text-align: center;
            border-radius: 10px;
            padding: 16px 20px;
            position: fixed;
            z-index: 10000;
            left: 50%;
            bottom: 30px;
            transform: translateX(-50%);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            pointer-events: none;
        }
        .toast-overlay-box.show {
            visibility: visible;
            animation: fadein 0.5s, fadeout 0.5s 4.5s;
        }
        .toast-overlay-box i { font-size: 24px; color: #2ecc71; }
        .toast-overlay-box div { text-align: left; line-height: 1.4; }

        @keyframes fadein { from { bottom: 0; opacity: 0; } to { bottom: 30px; opacity: 1; } }
        @keyframes fadeout { from { bottom: 30px; opacity: 1; } to { bottom: 0; opacity: 0; } }

        /* STYLING CUSTOM POP-UP SUKSES */
        .modal-success-overlay {
            display: flex; 
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7); 
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .modal-success-box {
            background: #fff;
            width: 400px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            text-align: center;
            animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .modal-success-header {
            background: linear-gradient(135deg, #a8e063 0%, #56ab2f 100%);
            padding: 35px 20px 25px;
            color: white;
        }
        .modal-success-header i { font-size: 65px; margin-bottom: 15px; text-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .modal-success-header h3 { margin: 0; font-size: 24px; font-weight: 800;}
        .modal-success-body { padding: 30px; }
        .modal-success-body p { color: #555; font-size: 15px; margin-bottom: 20px; line-height: 1.6; }
        
        .btn-modal-login {
            background: #2e7d32;
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 30px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            width: 100%;
            box-shadow: 0 4px 10px rgba(46, 125, 50, 0.3);
        }
        .btn-modal-login:hover { background: #1b5e20; transform: translateY(-3px); box-shadow: 0 6px 15px rgba(46, 125, 50, 0.4); }

        @keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
        @keyframes fadeIn { 0% { opacity: 0; } 100% { opacity: 1; } }
        @keyframes fadeInUp { 0% { transform: translateY(20px); opacity: 0; } 100% { transform: translateY(0); opacity: 1; } }

        @media screen and (max-width: 768px) {
            body { padding: 20px 15px; }
            .container { padding: 30px 20px; max-width: 340px; z-index: 10; }
            .title-wrapper h2 { font-size: 20px; }
            .info-box { flex-direction: column; text-align: center; gap: 10px; padding: 15px; max-width: 340px; }
            .info-box i { font-size: 26px; }
            .info-box p { font-size: 13px; }
            .input-wrapper .icon { padding: 10px 12px; }
            .input-wrapper input { padding: 10px 12px; font-size: 13px; }
            .modal-success-box { width: 90%; max-width: 340px; }
            .modal-success-header i { font-size: 50px; }
            .modal-success-header h3 { font-size: 18px; }
            .modal-success-body { padding: 20px; }
            .button-group { margin-top: 25px; gap: 10px;}
            .btn-back, .btn-submit { padding: 12px; font-size: 14px;}
            .toast-overlay-box { min-width: 80%; bottom: 20px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="title-wrapper">
        <h2>FORM REGISTRASI</h2>
        <div class="title-line"></div>
    </div>
    
    <?php echo $pesan; ?>
    
    <form action="" method="POST">
        <div class="input-group">
            <label>Nama Lengkap / Instansi</label>
            <div class="input-wrapper">
                <span class="icon"><i class="fa fa-user-edit"></i></span>
                <input type="text" name="nama_lengkap" placeholder="Masukkan Nama Lengkap Anda" 
                       value="<?php echo isset($_POST['nama_lengkap']) ? htmlspecialchars($_POST['nama_lengkap']) : ''; ?>" required>
            </div>
        </div>

        <div class="input-group">
            <label>Nomor Telepon / WhatsApp</label>
            <div class="input-wrapper">
                <span class="icon"><i class="fa fa-phone"></i></span>
                <input type="text" name="nomor_telepon" placeholder="Contoh: 081234567890" 
                       value="<?php echo isset($_POST['nomor_telepon']) ? htmlspecialchars($_POST['nomor_telepon']) : ''; ?>" required>
            </div>
            <div class="input-note">
                <i class="fa fa-info-circle" style="color: #3498db;"></i> Note: Beri tanda strip (-) jika Anda tidak memiliki Nomor Telepon.
            </div>
        </div>

        <div class="input-group">
            <label>Nomor Anggota (Otomatis)</label>
            <div class="input-wrapper wrapper-readonly">
                <span class="icon"><i class="fa fa-id-card"></i></span>
                <input type="text" name="username" id="nomor_anggota" placeholder="Klik tombol di bawah ini" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" readonly required>
            </div>
            
            <div class="center-action">
                <button type="button" class="btn-generate" onclick="buatNomorAnggota()">
                    <i class="fa fa-cog"></i> Buat Nomor Anggota Baru
                </button>
                <div class="note-salin" id="note-salin">
                    <i class="fa fa-exclamation-circle"></i> Note: Salin dan simpan Nomor Anggota ini untuk Login.
                </div>
            </div>
        </div>
        
        <div class="input-group">
            <label>Password</label>
            <div class="input-wrapper">
                <span class="icon"><i class="fa fa-lock"></i></span>
                <input type="password" name="password" id="input-password" placeholder="Buat Password Anda" required>
                
                <span class="icon-toggle" 
                      onmousedown="showPassword('input-password', 'icon-pw')" 
                      onmouseup="hidePassword('input-password', 'icon-pw')"
                      onmouseleave="hidePassword('input-password', 'icon-pw')"
                      ontouchstart="showPassword('input-password', 'icon-pw')"
                      ontouchend="hidePassword('input-password', 'icon-pw')">
                    <i class="fa fa-eye" id="icon-pw"></i>
                </span>
            </div>
        </div>
        
        <div class="input-group">
            <label>Konfirmasi Password</label>
            <div class="input-wrapper">
                <span class="icon"><i class="fa fa-lock"></i></span>
                <input type="password" name="konfirmasi_password" id="input-konfirmasi" placeholder="Ketik Ulang Password Anda" required>
                
                <span class="icon-toggle" 
                      onmousedown="showPassword('input-konfirmasi', 'icon-konfirm')" 
                      onmouseup="hidePassword('input-konfirmasi', 'icon-konfirm')"
                      onmouseleave="hidePassword('input-konfirmasi', 'icon-konfirm')"
                      ontouchstart="showPassword('input-konfirmasi', 'icon-konfirm')"
                      ontouchend="hidePassword('input-konfirmasi', 'icon-konfirm')">
                    <i class="fa fa-eye" id="icon-konfirm"></i>
                </span>
            </div>
        </div>
        
        <div class="button-group">
            <a href="login.php" class="btn-back"><i class="fa fa-arrow-left"></i></a>
            <button type="submit" name="submit_registrasi" class="btn-submit">DAFTAR SEKARANG</button>
        </div>
    </form>
</div>

<div class="info-box">
    <i class="fa fa-leaf"></i>
    <p><b>Mari Bersama Menjaga Bumi Kita!</b><br>
    Setiap sampah yang Anda tabung hari ini adalah langkah kecil untuk lingkungan hidup yang lebih hijau esok hari.</p>
</div>

<div id="customToast" class="toast-overlay-box">
    <i class="fa fa-check-circle"></i>
    <div id="toastMessage">Pesan akan muncul di sini</div>
</div>

<?php if ($registrasi_sukses) : ?>
<div class="modal-success-overlay">
    <div class="modal-success-box">
        <div class="modal-success-header">
            <i class="fa fa-check-circle"></i>
            <h3>Pendaftaran Berhasil!</h3>
        </div>
        <div class="modal-success-body">
            <p>Akun Anda telah berhasil didaftarkan di sistem <b>Bank Sampah Induk</b>. Silakan gunakan Nomor Anggota atau Nomor Telepon untuk masuk ke dasbor Anda.</p>
            <button class="btn-modal-login" onclick="window.location.href='login.php'">
                Lanjut ke Menu Login <i class="fa fa-arrow-right" style="margin-left: 5px;"></i>
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // PENGENDALI TOMBOL BACK BROWSER
    window.history.pushState("registrasi", null, window.location.href);
    window.onpopstate = function(event) {
        window.location.href = "login.php";
    };

    // FUNGSI MENAMPILKAN TOAST (PENGGANTI ALERT)
    function showToast(pesanUtama, pesanSub) {
        var toast = document.getElementById("customToast");
        var msg = document.getElementById("toastMessage");
        
        msg.innerHTML = "<b>" + pesanUtama + "</b><br><span style='font-size:12px; color:#bdc3c7;'>" + pesanSub + "</span>";
        toast.className = "toast-overlay-box show";
        
        // Hilangkan otomatis setelah 5 detik
        setTimeout(function(){ toast.className = "toast-overlay-box"; }, 5000);
    }

    // FITUR GENERATE NOMOR ANGGOTA 
    function buatNomorAnggota() {
        var inputNomor = document.getElementById('nomor_anggota');
        var note = document.getElementById('note-salin');
        
        // Memasukkan variabel PHP yang sudah dikalkulasi ke dalam input
        inputNomor.value = "<?php echo $next_nomor; ?>";
        
        // Memunculkan tulisan note merah
        note.style.display = "block";
        
        var pesanUtama = "Nomor Anggota berhasil dibuat!";
        var pesanSub = "Tersalin ke papan klip. Silakan isi sandi dan daftar.";
        
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(inputNomor.value).then(function() {
                showToast(pesanUtama, pesanSub);
            }).catch(function() {
                showToast("Nomor dibuat: " + inputNomor.value, "Silakan salin manual jika diperlukan.");
            });
        } else {
            inputNomor.select();
            inputNomor.setSelectionRange(0, 99999); 
            try {
                document.execCommand("copy");
                showToast(pesanUtama, pesanSub);
            } catch (err) {
                showToast("Nomor dibuat: " + inputNomor.value, "Silakan salin manual jika diperlukan.");
            }
            window.getSelection().removeAllRanges();
        }
    }

    // FITUR MATA PASSWORD
    function showPassword(inputId, iconId) {
        document.getElementById(inputId).type = "text";
        document.getElementById(iconId).classList.replace("fa-eye", "fa-eye-slash");
    }

    function hidePassword(inputId, iconId) {
        document.getElementById(inputId).type = "password";
        document.getElementById(iconId).classList.replace("fa-eye-slash", "fa-eye");
    }
</script>

</body>
</html>