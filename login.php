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
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = $_POST['password'];

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
            margin: 0; 
            box-sizing: border-box;
            background-color: #f4f7f6;
        }

        /* LAYOUT BAGI DUA (SPLIT SCREEN) UNTUK DESKTOP */
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

        /* BAGIAN KANAN - FORM LOGIN */
        .right-side {
            flex: 1;
            max-width: 500px;
            background-color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
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

        .login-box h2 { color: #238b45; margin-top: 0; margin-bottom: 5px; font-size: 28px; font-weight: bold;}
        .login-box p.subtitle { color: #666; font-size: 15px; margin-bottom: 35px; margin-top: 0; }
        
        .alert-error { background-color: rgba(255, 235, 238, 0.9); color: #c62828; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; text-align: left; border: 1px solid #ffcdd2; }
        
        .input-group { margin-bottom: 20px; text-align: left; }
        .input-group label { display: block; margin-bottom: 8px; color: #333; font-size: 15px; font-weight: bold; }
        
        .input-wrapper { 
            display: flex; 
            border: 1px solid #dcdcdc; 
            border-radius: 8px; 
            background-color: #fdfdfd; 
            overflow: hidden;
            transition: 0.3s;
        }
        .input-wrapper:focus-within { border-color: #1abc9c; box-shadow: 0 0 0 3px rgba(26, 188, 156, 0.1); background-color: #ffffff;}
        .input-wrapper .icon { padding: 14px 15px; color: #888; display: flex; align-items: center; justify-content: center; }
        .input-wrapper input { flex: 1; padding: 14px 15px 14px 0; border: none; background: transparent; outline: none; font-size: 15px; color: #333; width: 100%;}
        
        input::-ms-reveal, input::-ms-clear { display: none; }
        
        .input-wrapper .icon-toggle {
            padding: 14px 15px;
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

        .btn-login { width: 100%; padding: 15px; background-color: #1abc9c; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: 0.3s; margin-bottom: 25px; box-shadow: 0 4px 6px rgba(26,188,156,0.2);}
        .btn-login:hover { background-color: #16a085; transform: translateY(-2px); box-shadow: 0 6px 12px rgba(26,188,156,0.3);}
        
        .link-registrasi { font-size: 15px; color: #555; }
        .link-registrasi a { color: #1abc9c; text-decoration: none; font-weight: bold; }

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
                padding: 0; /* Nolkan padding agar header hijau mentok atas */
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
            
            /* Pembungkus form isian */
            .form-container-mobile {
                padding: 0 25px 30px 25px;
                max-width: 450px;
                margin: 0 auto;
            }
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
                    <h2>Selamat Datang</h2>
                    <p class="subtitle">Silakan masuk menggunakan akun Anda</p>
                    
                    <?php if (!empty($error)) : ?>
                        <div class="alert-error"><i class="fa fa-exclamation-triangle"></i> <?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form action="" method="POST">
                        <div class="input-group">
                            <label>Nomor Anggota / Telepon</label>
                            <div class="input-wrapper">
                                <span class="icon"><i class="fa fa-id-card"></i></span>
                                <input type="text" name="username" placeholder="Masukkan No Anggota / Telepon" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="input-group" style="margin-bottom: 8px;">
                            <label>Password</label>
                            <div class="input-wrapper">
                                <span class="icon"><i class="fa fa-lock"></i></span>
                                <input type="password" name="password" id="input-password" placeholder="Masukkan Password" required>
                                
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
                            <a href="https://wa.me/6287772666425?text=Halo%20Admin,%20saya%20lupa%20password%20akun%20Bank%20Sampah%20saya.%20Mohon%20bantuannya." target="_blank" style="color: #1abc9c; font-size: 14px; text-decoration: none; font-weight: bold; transition: 0.2s;">Lupa Password?</a>
                        </div>
                        
                        <button type="submit" name="submit_login" class="btn-login">MASUK</button>
                    </form>
                    
                    <div class="link-registrasi">
                        Belum punya akun? <a href="registrasi.php">Daftar Nasabah Baru</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
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