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
        $pesan = "<div class='alert-error'><i class='fa fa-exclamation-triangle'></i> Silakan klik tombol 'Buat Nomor Anggota Baru' terlebih dahulu!</div>";
    } 
    // Validasi Password
    else if ($password !== $konfirmasi) {
        $pesan = "<div class='alert-error'><i class='fa fa-exclamation-triangle'></i> Password dan Konfirmasi Password tidak cocok!</div>";
    } 
    else {
        // CEK GANDA: Username ATAU Nomor Telepon
        $cek_hp_query = ($nomor_telepon != "" && $nomor_telepon != "-") ? " OR nomor_telepon = '$nomor_telepon'" : "";
        
        $cek_ganda = mysqli_query($koneksi, "SELECT username, nomor_telepon FROM users WHERE username = '$username' $cek_hp_query");
        
        if (mysqli_num_rows($cek_ganda) > 0) {
            $data_ganda = mysqli_fetch_assoc($cek_ganda);
            
            if ($data_ganda['nomor_telepon'] == $nomor_telepon && $nomor_telepon != "-") {
                $pesan = "<div class='alert-error'><i class='fa fa-exclamation-triangle'></i> Nomor telepon <b>$nomor_telepon</b> sudah terdaftar! Silakan gunakan nomor lain.</div>";
            } else {
                $pesan = "<div class='alert-error'><i class='fa fa-exclamation-triangle'></i> Nomor Anggota ini baru saja diambil orang lain. Silakan buat nomor baru.</div>";
            }
        } else {
            // Simpan ke database
            $query = "INSERT INTO users (username, password, nama_lengkap, nomor_telepon, role) 
                      VALUES ('$username', '$password', '$nama_lengkap', '$nomor_telepon', '$role')";
            
            if (mysqli_query($koneksi, $query)) {
                $registrasi_sukses = true;
                $_POST = array(); // Kosongkan form
            } else {
                $pesan = "<div class='alert-error'><i class='fa fa-exclamation-triangle'></i> Gagal menyimpan data ke database.</div>";
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
            margin: 0; 
            box-sizing: border-box;
            background-color: #f4f7f6; /* Sama dengan login */
        }

        /* LAYOUT BAGI DUA (SPLIT SCREEN) UNTUK DESKTOP - SAMA PERSIS LOGIN */
        .split-layout {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* BAGIAN KIRI - BRANDING & POHON */
        .left-side {
            flex: 1.2;
            background-image: url('assets/bg-lingkungan.jpg?v=<?php echo time(); ?>'); 
            background-size: cover;
            background-position: center;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            overflow: hidden;
        }
        .left-side::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, rgba(26, 188, 156, 0.85), rgba(46, 125, 50, 0.9));
            z-index: 1;
        }
        .left-content {
            position: relative;
            z-index: 2;
            padding: 40px;
        }
        
        .tree-illustration {
            font-size: 80px;
            color: #ffffff;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 10px rgba(0,0,0,0.3));
        }

        .left-content h1 {
            font-size: 46px;
            margin: 10px 0;
            font-weight: 800;
            letter-spacing: 2px;
            text-shadow: 0 4px 8px rgba(0,0,0,0.3);
            line-height: 1.2;
        }
        .left-content p {
            font-size: 18px;
            margin-top: 5px;
            font-weight: 500;
            opacity: 0.9;
            letter-spacing: 1px;
        }

        /* BAGIAN KANAN - FORM REGISTRASI */
        .right-side {
            flex: 1;
            max-width: 500px;
            background-color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px; 
            box-shadow: -10px 0 30px rgba(0,0,0,0.1);
            z-index: 2;
        }
        .login-box { 
            width: 100%; 
            max-width: 380px; 
            text-align: center; 
        }
        
        /* PENGATURAN LOGO DAN HEADER */
        .mobile-header-logo { text-align: center; margin-bottom: 15px; }
        .logo-wrapper { display: inline-flex; align-items: center; justify-content: center; }
        .logo-instansi { width: 70px; height: auto; }
        .form-container-mobile { width: 100%; box-sizing: border-box; }

        .login-box h2 { color: #238b45; margin-top: 0; margin-bottom: 5px; font-size: 24px; font-weight: bold;}
        .login-box p.subtitle { color: #666; font-size: 14px; margin-bottom: 20px; margin-top: 0; }
        
        .alert-error { background-color: rgba(255, 235, 238, 0.9); color: #c62828; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; text-align: left; border: 1px solid #ffcdd2; }
        
        /* SPASI UNTUK SETIAP INPUT DIPERKETAT AGAR ANTI-SCROLL */
        .input-group { margin-bottom: 12px; text-align: left; }
        .input-group label { display: block; margin-bottom: 5px; color: #333; font-size: 13px; font-weight: bold; }
        
        .input-wrapper { 
            display: flex; 
            border: 1px solid #dcdcdc; 
            border-radius: 8px; 
            background-color: #fdfdfd; 
            overflow: hidden;
            transition: 0.3s;
        }
        .input-wrapper:focus-within { border-color: #1abc9c; box-shadow: 0 0 0 3px rgba(26, 188, 156, 0.1); background-color: #ffffff;}
        .input-wrapper .icon { padding: 10px 15px; color: #888; display: flex; align-items: center; justify-content: center; }
        .input-wrapper input { flex: 1; padding: 10px 15px 10px 0; border: none; background: transparent; outline: none; font-size: 13px; color: #333; width: 100%;}
        
        input::-ms-reveal, input::-ms-clear { display: none; }
        
        .input-wrapper .icon-toggle {
            padding: 10px 15px;
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
        .wrapper-readonly { background-color: #f1f4f8 !important; border-color: #d1d9e6 !important; }
        .wrapper-readonly input { font-weight: bold; color: #16a085 !important; letter-spacing: 1px; cursor: not-allowed; }
        
        .center-action { display: flex; align-items: center; justify-content: space-between; margin-top: 6px; }

        .btn-generate {
            background-color: #3498db; color: white; border: none; padding: 6px 12px;
            border-radius: 20px; font-size: 11px; font-weight: bold; cursor: pointer;
            display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; box-shadow: 0 2px 4px rgba(52,152,219,0.3);
        }
        .btn-generate:hover { background-color: #2980b9; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(52,152,219,0.4);}
        
        .note-salin { font-size: 11px; color: #e74c3c; font-weight: 700; text-align: right; display: none; animation: fadeIn 0.5s; margin-left: 5px;}
        .input-note { font-size: 11px; color: #7f8c8d; margin-top: 4px; font-weight: 600; line-height: 1.2; }

        .button-group { display: flex; gap: 10px; margin-top: 20px; }
        .btn-back, .btn-submit { color: white; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; transition: 0.3s; text-align: center; font-size: 14px; font-weight: bold;}
        .btn-back { background-color: #95a5a6; padding: 12px 20px; display: flex; align-items: center; justify-content: center;}
        .btn-submit { background-color: #1abc9c; padding: 12px; flex: 1; box-shadow: 0 4px 6px rgba(26,188,156,0.2);}
        .btn-back:hover { background-color: #7f8c8d; transform: translateY(-2px);}
        .btn-submit:hover { background-color: #16a085; transform: translateY(-2px); box-shadow: 0 6px 12px rgba(26,188,156,0.3);}

        /* CUSTOM TOAST NOTIFICATION */
        .toast-overlay-box {
            visibility: hidden; min-width: 250px; background-color: #2c3e50; color: #fff; text-align: center;
            border-radius: 10px; padding: 16px 20px; position: fixed; z-index: 10000; left: 50%; bottom: 30px;
            transform: translateX(-50%); box-shadow: 0 5px 15px rgba(0,0,0,0.3); font-size: 14px;
            display: flex; align-items: center; gap: 10px; pointer-events: none;
        }
        .toast-overlay-box.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 4.5s; }
        .toast-overlay-box i { font-size: 24px; color: #2ecc71; }
        .toast-overlay-box div { text-align: left; line-height: 1.4; }

        @keyframes fadein { from { bottom: 0; opacity: 0; } to { bottom: 30px; opacity: 1; } }
        @keyframes fadeout { from { bottom: 30px; opacity: 1; } to { bottom: 0; opacity: 0; } }

        /* STYLING CUSTOM POP-UP SUKSES */
        .modal-success-overlay {
            display: flex; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7); z-index: 9999; justify-content: center; align-items: center;
        }
        .modal-success-box {
            background: #fff; width: 400px; border-radius: 16px; overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4); text-align: center; animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .modal-success-header { background: linear-gradient(135deg, #a8e063 0%, #56ab2f 100%); padding: 35px 20px 25px; color: white; }
        .modal-success-header i { font-size: 65px; margin-bottom: 15px; text-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .modal-success-header h3 { margin: 0; font-size: 24px; font-weight: 800;}
        .modal-success-body { padding: 30px; }
        .modal-success-body p { color: #555; font-size: 15px; margin-bottom: 20px; line-height: 1.6; }
        
        .btn-modal-login {
            background: #2e7d32; color: white; border: none; padding: 14px 30px; border-radius: 30px;
            font-size: 15px; font-weight: bold; cursor: pointer; transition: 0.3s; width: 100%; box-shadow: 0 4px 10px rgba(46, 125, 50, 0.3);
        }
        .btn-modal-login:hover { background: #1b5e20; transform: translateY(-3px); box-shadow: 0 6px 15px rgba(46, 125, 50, 0.4); }

        @keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
        @keyframes fadeIn { 0% { opacity: 0; } 100% { opacity: 1; } }

        /* =======================================================
           RESPONSIVE MOBILE (HP) - HEADER HIJAU & NO SCROLL
           ======================================================= */
        @media screen and (max-width: 850px) {
            .split-layout { 
                display: flex; 
                flex-direction: column;
            }
            .left-side { 
                display: none; 
            }
            .right-side { 
                max-width: 100%; 
                min-height: 100vh;
                min-height: 100dvh; 
                padding: 0; 
                box-shadow: none;
                align-items: flex-start;
            }
            .login-box {
                max-width: 100%; 
                width: 100%;
            }
            
            /* HEADER HIJAU DIBELAKANG LOGO KHUSUS HP */
            .mobile-header-logo {
                background: linear-gradient(135deg, #1abc9c, #2e7d32);
                padding: 35px 20px;
                border-radius: 0 0 35px 35px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.15);
                margin-bottom: 30px;
                width: 100%;
                box-sizing: border-box;
            }
            .logo-wrapper {
                background-color: white;
                width: 90px;
                height: 90px;
                border-radius: 50%;
                box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            }
            .logo-instansi { 
                width: 60px; 
            }
            
            .form-container-mobile {
                padding: 0 25px 30px 25px;
                max-width: 450px;
                margin: 0 auto;
            }

            .modal-success-box { width: 90%; max-width: 340px; }
            .modal-success-header i { font-size: 50px; }
            .modal-success-header h3 { font-size: 18px; }
            .modal-success-body { padding: 20px; }
            .toast-overlay-box { min-width: 80%; bottom: 20px; }
        }
    </style>
</head>
<body>

    <div class="split-layout">
        <div class="left-side">
            <div class="left-content">
                <i class="fa-solid fa-tree tree-illustration"></i>
                <h1>BANK SAMPAH <br> INDUK</h1>
                <p>Dinas Lingkungan Hidup Kab. Lebak</p>
            </div>
        </div>

        <div class="right-side">
            <div class="login-box">
                <div class="mobile-header-logo">
                    <div class="logo-wrapper">
                        <img src="assets/logo-lebak.png" alt="Logo Instansi" class="logo-instansi">
                    </div>
                </div>
                
                <div class="form-container-mobile">
                    <h2>Form Registrasi</h2>
                    <p class="subtitle">Lengkapi data diri Anda di bawah ini</p>
                    
                    <?php if (!empty($pesan)) echo $pesan; ?>
                    
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
                                <i class="fa fa-info-circle" style="color: #3498db;"></i> Note: Beri strip (-) jika tidak memiliki Nomor Telepon.
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
                                    <i class="fa fa-exclamation-circle"></i> Salin dan simpan Nomor Anggota ini.
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
                        
                        <div class="input-group" style="margin-bottom: 5px;">
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
                            <a href="login.php" class="btn-back" title="Kembali ke Login"><i class="fa fa-arrow-left"></i></a>
                            <button type="submit" name="submit_registrasi" class="btn-submit">DAFTAR SEKARANG</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
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
    window.history.pushState("registrasi", null, window.location.href);
    window.onpopstate = function(event) { window.location.href = "login.php"; };

    function showToast(pesanUtama, pesanSub) {
        var toast = document.getElementById("customToast");
        var msg = document.getElementById("toastMessage");
        
        msg.innerHTML = "<b>" + pesanUtama + "</b><br><span style='font-size:12px; color:#bdc3c7;'>" + pesanSub + "</span>";
        toast.className = "toast-overlay-box show";
        setTimeout(function(){ toast.className = "toast-overlay-box"; }, 5000);
    }

    function buatNomorAnggota() {
        var inputNomor = document.getElementById('nomor_anggota');
        var note = document.getElementById('note-salin');
        
        inputNomor.value = "<?php echo $next_nomor; ?>";
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