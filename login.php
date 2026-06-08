<?php
session_start();
include 'koneksi.php';

// PENJAGA KEAMANAN: Jika sudah login, blokir akses ke halaman login dan lempar ke dashboard
if (isset($_SESSION['login'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: dashboard_admin.php");
    } else if ($_SESSION['role'] == 'nasabah') {
        header("Location: dashboard_nasabah.php");
    }
    exit;
}

$error = "";

if (isset($_POST['submit_login'])) {
    // Menangkap inputan (Nomor Anggota ATAU Nomor Telepon)
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = $_POST['password'];

    // Cek ke database: Cocokkan dengan kolom username ATAU kolom nomor_telepon
    $query = "SELECT * FROM users WHERE username = '$username' OR nomor_telepon = '$username'";
    $result = mysqli_query($koneksi, $query);

    if (mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        
        if ($password === $row['password']) {
            $_SESSION['login'] = true;
            $_SESSION['username'] = $row['username'];
            $_SESSION['nama'] = $row['nama_lengkap'];
            $_SESSION['role'] = $row['role']; 

            if ($row['role'] == 'admin') {
                header("Location: dashboard_admin.php");
            } else if ($row['role'] == 'nasabah') {
                header("Location: dashboard_nasabah.php");
            }
            exit;
        } else {
            $error = "Password yang Anda masukkan salah!";
        }
    } else {
        $error = "Nomor Anggota atau Nomor Telepon tidak ditemukan!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - Bank Sampah Induk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            /* Trik Cache Busting otomatis di bagian URL */
            background-image: url('assets/bg-lingkungan.jpg?v=<?php echo time(); ?>'); 
            background-size: cover; 
            background-position: center; 
            background-attachment: fixed; 
            background-repeat: no-repeat; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            margin: 0; 
            box-sizing: border-box;
        }
        body::before { content: ""; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.3); z-index: 1; }
        
        /* EFEK GLASSMORPHISM (KACA TRANSPARAN) */
        .container { 
            position: relative; 
            z-index: 2; 
            background-color: rgba(255, 255, 255, 0.85); /* Putih transparan */
            backdrop-filter: blur(12px); /* Efek blur kaca */
            -webkit-backdrop-filter: blur(12px); /* Dukungan untuk Safari */
            border: 1px solid rgba(255, 255, 255, 0.5); /* Garis tepi tipis ala kaca */
            padding: 40px 30px; 
            border-radius: 16px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.2); 
            width: 100%; 
            max-width: 380px; 
            text-align: center; 
            box-sizing: border-box; 
        }
        
        .logo-instansi { width: 80px; height: auto; margin-bottom: 10px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); }
        .container h2 { color: #2e7d32; margin-top: 0; margin-bottom: 5px; font-size: 22px; text-shadow: 0 1px 2px rgba(255,255,255,0.8); }
        .container p.subtitle { color: #444; font-size: 13px; margin-bottom: 25px; margin-top: 0; font-weight: 600;}
        
        .alert-error { background-color: rgba(255, 235, 238, 0.9); color: #c62828; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; text-align: left; border: 1px solid #ffcdd2; }
        
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; margin-bottom: 8px; color: #222; font-size: 14px; font-weight: bold; }
        
        /* Desain Input Wrapper */
        .input-wrapper { 
            display: flex; 
            border: 1px solid rgba(200, 200, 200, 0.8); 
            border-radius: 8px; 
            background-color: rgba(255, 255, 255, 0.9); /* Sedikit lebih solid agar teks ketikan jelas */
            overflow: hidden;
            transition: 0.3s;
        }
        .input-wrapper:focus-within { border-color: #1abc9c; box-shadow: 0 0 8px rgba(26, 188, 156, 0.3); background-color: #ffffff;}
        .input-wrapper .icon { padding: 12px 15px; background-color: rgba(241, 241, 241, 0.8); color: #555; border-right: 1px solid rgba(200, 200, 200, 0.5); display: flex; align-items: center; width: 20px; justify-content: center; }
        .input-wrapper input { flex: 1; padding: 12px 15px; border: none; background: transparent; outline: none; font-size: 14px; color: #333; width: 100%;}
        
        /* Menghilangkan mata bawaan browser agar tidak ganda */
        input::-ms-reveal,
        input::-ms-clear { display: none; }
        
        /* Style untuk ikon mata (Fitur Hold) */
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
        .input-wrapper .icon-toggle:active { color: #16a085; }

        .btn-login { width: 100%; padding: 14px; background-color: #1abc9c; color: white; border: none; border-radius: 25px; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.3s; margin-bottom: 15px; box-shadow: 0 4px 6px rgba(26,188,156,0.2);}
        .btn-login:hover { background-color: #16a085; transform: translateY(-2px); box-shadow: 0 6px 12px rgba(26,188,156,0.3);}
        
        .link-registrasi { font-size: 14px; color: #333; font-weight: 500;}
        .link-registrasi a { color: #16a085; text-decoration: none; font-weight: bold; }

        /* =======================================================
           RESPONSIVE MOBILE (HP) - DIPERBAIKI AGAR LEBIH RAMPING
           ======================================================= */
        @media screen and (max-width: 768px) {
            body { padding: 15px; }
            .container { 
                padding: 30px 20px; 
                max-width: 320px; /* Ukuran dipersempit agar tidak kebesaran di HP */
            }
            .logo-instansi { width: 70px; }
            .container h2 { font-size: 20px; }
            .input-wrapper .icon { padding: 10px 12px; }
            .input-wrapper input { padding: 10px 12px; font-size: 13px; }
            .btn-login { padding: 12px; font-size: 15px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="assets/logo-lebak.png" alt="Logo Instansi" class="logo-instansi">
        
        <h2>BANK SAMPAH INDUK</h2>
        <p class="subtitle">Dinas Lingkungan Hidup Kab. Lebak</p>
        
        <?php if (!empty($error)) : ?>
            <div class="alert-error"><i class="fa fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <form action="" method="POST">
            <div class="input-group">
                <label>Nomor Anggota / Telepon</label>
                <div class="input-wrapper">
                    <span class="icon"><i class="fa fa-id-card"></i></span>
                    <input type="text" name="username" placeholder="Nomor Anggota / Telepon" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                </div>
            </div>
            
            <div class="input-group" style="margin-bottom: 8px;">
                <label>Password</label>
                <div class="input-wrapper">
                    <span class="icon"><i class="fa fa-lock"></i></span>
                    <input type="password" name="password" id="input-password" placeholder="Masukkan Password Anda" required>
                    
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

            <div style="text-align: right; margin-bottom: 25px;">
                <a href="https://wa.me/6281234567890?text=Halo%20Admin,%20saya%20lupa%20password%20akun%20Bank%20Sampah%20saya.%20Mohon%20bantuannya." target="_blank" style="color: #16a085; font-size: 13px; text-decoration: none; font-weight: bold; transition: 0.2s;">Lupa Password?</a>
            </div>
            
            <button type="submit" name="submit_login" class="btn-login">MASUK</button>
        </form>
        
        <div class="link-registrasi">
            Belum punya akun? <a href="registrasi.php">Daftar Nasabah Baru</a>
        </div>
    </div>

<script>
    // Fungsi untuk menampilkan password (saat ditahan/hold)
    function showPassword(inputId, iconId) {
        document.getElementById(inputId).type = "text";
        document.getElementById(iconId).classList.replace("fa-eye", "fa-eye-slash");
    }

    // Fungsi untuk menyembunyikan password (saat dilepas)
    function hidePassword(inputId, iconId) {
        document.getElementById(inputId).type = "password";
        document.getElementById(iconId).classList.replace("fa-eye-slash", "fa-eye");
    }
</script>

</body>
</html>