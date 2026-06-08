<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

if (!isset($_SESSION['login']) || $_SESSION['role'] != 'nasabah') {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

$username_aktif = $_SESSION['username'];
$notif_sukses = "";
$notif_gagal = "";

// AMBIL DATA TERBARU DULU SEBELUM UPDATE
$q_user = mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username_aktif' AND role = 'nasabah'");
$data_user = mysqli_fetch_assoc($q_user);

// PROSES UPDATE PROFIL & FOTO
if (isset($_POST['update_profil'])) {
    $nama_baru = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $telepon_baru = mysqli_real_escape_string($koneksi, $_POST['nomor_telepon']);
    
    $foto_lama = $data_user['foto_profil'];
    $nama_file_baru = $foto_lama; 
    
    if ($_FILES['foto_profil']['name'] != "") {
        $ekstensi_diperbolehkan = array('png', 'jpg', 'jpeg');
        $nama_foto = $_FILES['foto_profil']['name'];
        $x = explode('.', $nama_foto);
        $ekstensi = strtolower(end($x));
        $ukuran = $_FILES['foto_profil']['size'];
        $file_tmp = $_FILES['foto_profil']['tmp_name'];

        if (in_array($ekstensi, $ekstensi_diperbolehkan) === true) {
            if ($ukuran < 2048000) { 
                $nama_file_baru = $username_aktif . '_' . time() . '.' . $ekstensi;
                move_uploaded_file($file_tmp, 'assets/profil/' . $nama_file_baru);
                
                if (!empty($foto_lama) && file_exists('assets/profil/' . $foto_lama)) {
                    unlink('assets/profil/' . $foto_lama);
                }
            } else {
                $notif_gagal = "Ukuran gambar terlalu besar! (Maksimal 2 MB)";
            }
        } else {
            $notif_gagal = "Ekstensi file tidak valid! (Hanya JPG, JPEG, PNG)";
        }
    }

    if (empty($notif_gagal)) {
        $query_update = "UPDATE users SET 
                         nama_lengkap = '$nama_baru', 
                         nomor_telepon = '$telepon_baru', 
                         foto_profil = '$nama_file_baru'
                         WHERE username = '$username_aktif' AND role = 'nasabah'";

        if (mysqli_query($koneksi, $query_update)) {
            $_SESSION['nama'] = $nama_baru; 
            $notif_sukses = "Data profil Anda berhasil diperbarui!";
            
            $q_user = mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username_aktif' AND role = 'nasabah'");
            $data_user = mysqli_fetch_assoc($q_user);
        } else {
            $notif_gagal = "Gagal memperbarui profil. Silakan coba lagi.";
        }
    }
}

$path_foto = (!empty($data_user['foto_profil']) && file_exists('assets/profil/' . $data_user['foto_profil'])) 
             ? 'assets/profil/' . $data_user['foto_profil'] 
             : 'https://ui-avatars.com/api/?name=' . urlencode($data_user['nama_lengkap']) . '&background=1abc9c&color=fff&size=128';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Profil Saya - Bank Sampah Induk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }
        
        .sidebar { width: 85px; background-color: #2c3e50; color: white; display: flex; flex-direction: column; z-index: 1001;}
        
        .sidebar h2 { 
            margin: 0; 
            background-color: #1abc9c; 
            font-size: 24px; 
            cursor: default; 
            white-space: nowrap;
            height: 60px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            box-sizing: border-box;
        }
        
        .sidebar-logo-text { display: none; }
        .menu { flex: 1; padding-top: 10px; }
        .menu a { display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 12px 5px; color: #bdc3c7; text-decoration: none; transition: 0.3s; border-left: 4px solid transparent; cursor: pointer;}
        .menu a:hover, .menu a.active { background-color: #34495e; color: white; border-left-color: #1abc9c; }
        .menu a i { font-size: 22px; margin-bottom: 4px; }
        .menu a span.menu-text { font-size: 10px; text-align: center; line-height: 1.2; font-weight: 600;}

        .main-content { flex: 1; display: flex; flex-direction: column; position: relative;}
        
        .header { 
            background-color: #1abc9c; 
            padding: 0 30px; 
            height: 60px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
            z-index: 10;
            box-sizing: border-box;
        }
        .header h3 { margin: 0; color: white; font-size: 16px; display: flex; align-items: center; gap: 10px;}
        
        .mobile-menu-btn { display: none; font-size: 20px; color: white; cursor: pointer;}
        
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; }

        .content { padding: 30px; flex: 1; overflow-y: auto; display: flex; justify-content: center; }
        
        .card { background-color: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; max-width: 600px; box-sizing: border-box;}
        .card-header { text-align: center; margin-bottom: 30px;}
        .card-header img { width: 100px; height: 100px; border-radius: 50%; border: 4px solid #1abc9c; object-fit: cover; margin-bottom: 15px; background: #eee;}
        .card-header h2 { margin: 0; color: #2c3e50; }
        .card-header p { color: #7f8c8d; margin-top: 5px; font-size: 14px;}

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; color: #555; margin-bottom: 8px; font-weight: bold; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box; background-color: #f9fbfb; transition: 0.3s;}
        .form-group input[type="file"] { background-color: white; padding: 9px; cursor: pointer;}
        .form-group input:focus, .form-group textarea:focus { border-color: #1abc9c; outline: none; background-color: #fff;}
        
        /* PERBAIKAN: Disamakan dengan input lain, hanya background agak abu & teks jelas */
        .form-group input[readonly] { 
            background-color: #ecf0f1; 
            color: #333 !important; 
            cursor: not-allowed; 
            border: 1px solid #ddd;
            font-weight: 600;
        }
        
        .button-group { display: flex; margin-top: 25px; }
        .btn-simpan { background-color: #1abc9c; color: white; border: none; padding: 14px 20px; border-radius: 8px; font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.2s; width: 100%; display: flex; justify-content: center; align-items: center; gap: 8px;}
        .btn-simpan:hover { background-color: #16a085; transform: translateY(-2px);}
        
        .alert-success { background-color: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border: 1px solid #c3e6cb;}
        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border: 1px solid #f5c6cb;}

        /* MODAL GLOBAL */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 350px; border-radius: 15px; overflow: hidden; box-shadow: 0 15px 30px rgba(0,0,0,0.3); text-align: center; animation: popIn 0.3s ease-out; }
        .modal-header-red { background-color: #e74c3c; padding: 25px 20px; }
        .modal-header-red img { width: 80px; height: auto; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2)); }
        .modal-body { padding: 25px 30px 30px; background: #fff; }
        .modal-body h3 { margin: 0 0 10px; color: #333; font-size: 20px; }
        .modal-body p { color: #666; margin-bottom: 25px; font-size: 14px; line-height: 1.5; }
        .modal-buttons { display: flex; gap: 15px; justify-content: center; }
        .modal-buttons button { padding: 10px 20px; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; flex: 1; }
        .btn-cancel { background: #e0e0e0; color: #555; }
        .btn-cancel:hover { background: #d5d5d5; }
        .btn-confirm { background: #c0392b; color: white; box-shadow: 0 4px 6px rgba(231,76,60,0.2);}
        .btn-confirm:hover { background: #a53125; transform: translateY(-2px);}
        @keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

        /* =======================================================
           RESPONSIVE MOBILE (HP)
           ======================================================= */
        @media screen and (max-width: 768px) {
            .mobile-menu-btn { display: block; }
            .header { padding: 0 20px; }

            .sidebar {
                position: fixed;
                top: 0;
                left: -260px; 
                width: 260px;
                height: 100vh;
                transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 5px 0 15px rgba(0,0,0,0.1);
            }
            .sidebar.active-mobile { left: 0; }
            
            .sidebar h2 { font-size: 18px; justify-content: flex-start; padding-left: 20px; }
            .sidebar-logo-text { display: inline; font-size: 18px; margin-left: 10px; font-family: 'Segoe UI', sans-serif;}
            
            .menu { padding-top: 15px; }
            .menu a { flex-direction: row; justify-content: flex-start; padding: 15px 25px; }
            .menu a i { margin-right: 15px; margin-bottom: 0; font-size: 20px;}
            .menu a span.menu-text { font-size: 15px; font-weight: normal;}

            .content { padding: 15px; display: block; }
            .card { padding: 25px 20px; margin: 0 auto 30px; }
            .card-header img { width: 80px; height: 80px; }
            .card-header h2 { font-size: 20px; }
            
            .btn-simpan { width: 100%; }
        }
    </style>
</head>
<body>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleMobileMenu()"></div>

    <div class="sidebar" id="sidebarMenu">
        <h2 title="Bank Sampah"><i class="fa fa-recycle"></i><span class="sidebar-logo-text">BANK SAMPAH</span></h2>
        <div class="menu">
            <a href="dashboard_nasabah.php">
                <i class="fa fa-home"></i>
                <span class="menu-text">Beranda</span>
            </a>
            <a href="setor_sampah.php">
                <i class="fa fa-leaf"></i>
                <span class="menu-text">Setor Sampah</span>
            </a>
            <a href="riwayat_transaksi.php">
                <i class="fa fa-history"></i>
                <span class="menu-text">Riwayat Transaksi</span>
            </a>
            <a href="profil_nasabah.php" class="active">
                <i class="fa fa-user"></i>
                <span class="menu-text">Profil Saya</span>
            </a>
            <a onclick="showLogoutModal()" style="color: #e74c3c; cursor: pointer; border-top: 1px solid rgba(255,255,255,0.1); margin-top:10px;">
                <i class="fa fa-sign-out-alt"></i>
                <span class="menu-text">Keluar</span>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h3>
                <i class="fa fa-bars mobile-menu-btn" onclick="toggleMobileMenu()"></i>
                <a href="dashboard_nasabah.php" style="color:white; text-decoration:none;"> Kembali ke Beranda</a>
            </h3>
        </div>
        
        <div class="content">
            <div class="card">
                <div class="card-header">
                    <img src="<?php echo $path_foto; ?>" alt="Avatar Profil">
                    <h2><?php echo htmlspecialchars($data_user['nama_lengkap']); ?></h2>
                    <p>Kelola data informasi pribadi & foto Anda di sini.</p>
                </div>

                <?php if(!empty($notif_sukses)): ?>
                    <div class="alert-success"><i class="fa fa-check-circle"></i> <?php echo $notif_sukses; ?></div>
                <?php endif; ?>
                
                <?php if(!empty($notif_gagal)): ?>
                    <div class="alert-danger"><i class="fa fa-exclamation-triangle"></i> <?php echo $notif_gagal; ?></div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Foto Profil (Maks 2MB, JPG/PNG)</label>
                        <input type="file" name="foto_profil" accept="image/png, image/jpeg, image/jpg">
                    </div>
                    
                    <div class="form-group">
                        <label>Nomor Anggota (Tidak dapat diubah)</label>
                        <!-- KEMBALI NORMAL SEPERTI KOTAK LAINNYA -->
                        <input type="text" value="<?php echo htmlspecialchars($data_user['username']); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Lengkap / Instansi</label>
                        <input type="text" name="nama_lengkap" value="<?php echo htmlspecialchars($data_user['nama_lengkap']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Nomor Telepon / WhatsApp</label>
                        <input type="text" name="nomor_telepon" value="<?php echo htmlspecialchars($data_user['nomor_telepon']); ?>" placeholder="Contoh: 081234567890">
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" name="update_profil" class="btn-simpan"><i class="fa fa-save"></i> Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="customModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header-red">
                <img src="assets/logo-lebak.png" alt="Logo Instansi">
            </div>
            <div class="modal-body">
                <h3>Konfirmasi Logout</h3>
                <p>Apakah Anda yakin ingin keluar dari sistem Bank Sampah Induk?</p>
                <div class="modal-buttons">
                    <button class="btn-cancel" onclick="closeLogoutModal()">Batal</button>
                    <button class="btn-confirm" onclick="prosesLogout()">Ya, Keluar</button>
                </div>
            </div>
        </div>
    </div>

<script>
    function toggleMobileMenu() {
        const sidebar = document.getElementById('sidebarMenu');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar.classList.contains('active-mobile')) {
            sidebar.classList.remove('active-mobile');
            overlay.style.display = 'none';
        } else {
            sidebar.classList.add('active-mobile');
            overlay.style.display = 'block';
        }
    }
    function showLogoutModal() { document.getElementById('customModal').style.display = 'flex'; }
    function closeLogoutModal() { document.getElementById('customModal').style.display = 'none'; }
    function prosesLogout() { window.location.href = 'logout.php'; }
</script>

</body>
</html>