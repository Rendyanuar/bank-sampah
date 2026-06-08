<?php
session_start();

// Mencegah browser menyimpan cache
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

// Cek apakah user belum login atau bukan admin
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

$username_aktif = $_SESSION['username'];
$notif_sukses = "";
$notif_gagal = "";

// =========================================================
// PROSES UPDATE DATA PROFIL & PASSWORD
// =========================================================
if (isset($_POST['update_profil'])) {
    $nama_baru = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $pass_baru = mysqli_real_escape_string($koneksi, $_POST['password']);
    
    // Logika Upload Foto Baru (Jika Admin Memilih Foto)
    $foto_profil = $_POST['foto_lama']; // Default pakai foto lama

    if (!empty($_FILES['foto']['name'])) {
        $ekstensi_diperbolehkan = array('png', 'jpg', 'jpeg');
        $nama_foto = $_FILES['foto']['name'];
        $x = explode('.', $nama_foto);
        $ekstensi = strtolower(end($x));
        $ukuran = $_FILES['foto']['size'];
        $file_tmp = $_FILES['foto']['tmp_name'];

        if (in_array($ekstensi, $ekstensi_diperbolehkan) === true) {
            if ($ukuran < 2048000) { // Maksimal 2MB
                $nama_file_baru = 'admin_' . time() . '_' . $nama_foto;
                move_uploaded_file($file_tmp, 'assets/profil/' . $nama_file_baru);
                $foto_profil = $nama_file_baru;
            } else {
                $notif_gagal = "Ukuran gambar terlalu besar! (Maksimal 2 MB)";
            }
        } else {
            $notif_gagal = "Ekstensi file tidak valid! (Hanya JPG, JPEG, PNG)";
        }
    }

    // Jika tidak ada error ukuran/ekstensi foto, lakukan update ke database
    if (empty($notif_gagal)) {
        $query_update = "UPDATE users SET nama_lengkap = '$nama_baru', password = '$pass_baru', foto_profil = '$foto_profil' WHERE username = '$username_aktif'";
        
        if (mysqli_query($koneksi, $query_update)) {
            $_SESSION['nama'] = $nama_baru; // Update session nama agar langsung berubah di pojok kanan atas
            $notif_sukses = "Profil dan Password berhasil diperbarui!";
        } else {
            $notif_gagal = "Gagal memperbarui data profil.";
        }
    }
}

// =========================================================
// AMBIL DATA ADMIN SAAT INI UNTUK DITAMPILKAN DI FORM
// =========================================================
$q_data = mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username_aktif'");
$data_admin = mysqli_fetch_assoc($q_data);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Profil Admin - Bank Sampah Induk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar Mini */
        .sidebar { width: 85px; background-color: #2c3e50; color: white; display: flex; flex-direction: column; z-index: 1001; }
        
        /* Tinggi logo dikunci presisi dengan header */
        .sidebar h2 { 
            margin: 0; 
            background-color: #1abc9c; 
            font-size: 24px; 
            cursor: default; 
            white-space: nowrap;
            height: 60px; /* Tinggi dikunci */
            display: flex; 
            align-items: center; 
            justify-content: center; 
            box-sizing: border-box;
        }
        
        .sidebar-logo-text { display: none; }
        .menu { flex: 1; padding-top: 10px; }
        .menu a { display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 12px 5px; color: #bdc3c7; text-decoration: none; transition: 0.3s; border-left: 4px solid transparent; cursor: pointer; position: relative;}
        .menu a:hover, .menu a.active { background-color: #34495e; color: white; border-left-color: #1abc9c; }
        .menu a i { font-size: 22px; margin-bottom: 4px; }
        .menu a span.menu-text { font-size: 10px; text-align: center; line-height: 1.2; font-weight: 600;}
        
        .main-content { flex: 1; display: flex; flex-direction: column; position: relative;}
        
        /* HEADER WARNA HIJAU */
        .header { 
            background-color: #1abc9c; 
            padding: 0 30px; 
            height: 60px; /* Tinggi dikunci presisi */
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
            z-index: 10;
            box-sizing: border-box;
        }
        .header h3 { margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 18px; }
        
        /* Hamburger Button & Overlay untuk HP */
        .mobile-menu-btn { display: none; font-size: 20px; color: white; cursor: pointer; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; }

        .user-info { font-size: 14px; color: white; display: flex; align-items: center; gap: 10px;}
        .user-info span { color: rgba(255,255,255,0.9); }
        .user-info img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 2px solid white;}
        
        .content { padding: 30px; flex: 1; overflow-y: auto; display: flex; justify-content: center; align-items: flex-start; box-sizing: border-box;}
        
        /* Tampilan Form Profil */
        .profile-card { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.05); width: 100%; max-width: 500px; text-align: center; box-sizing: border-box;}
        .profile-card h2 { margin-top: 0; color: #2c3e50; margin-bottom: 5px;}
        .profile-card p { color: #7f8c8d; font-size: 14px; margin-bottom: 30px;}
        
        .photo-upload { position: relative; width: 130px; height: 130px; margin: 0 auto 25px; }
        .photo-upload img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid #f4f7f6; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .photo-upload label { position: absolute; bottom: 0; right: 0; background: #1abc9c; color: white; width: 35px; height: 35px; border-radius: 50%; display: flex; justify-content: center; align-items: center; cursor: pointer; border: 3px solid white; transition: 0.3s;}
        .photo-upload label:hover { background: #16a085; transform: scale(1.1);}
        .photo-upload input[type="file"] { display: none; }
        
        .form-group { text-align: left; margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; color: #555; font-weight: 600; margin-bottom: 8px; }
        .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; box-sizing: border-box; background: #f9fbfb; transition: 0.3s;}
        .form-group input:focus { border-color: #1abc9c; outline: none; background: white;}
        .form-group input[readonly] { background: #eee; cursor: not-allowed; color: #777;}
        
        .btn-submit { background-color: #3498db; color: white; border: none; padding: 14px; border-radius: 8px; font-weight: bold; font-size: 15px; cursor: pointer; width: 100%; transition: 0.2s; margin-top: 10px; display: flex; justify-content: center; align-items: center; gap: 8px;}
        .btn-submit:hover { background-color: #2980b9; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);}

        /* Notifikasi Alert */
        .alert-success { background-color: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 25px; font-size: 14px; border: 1px solid #c3e6cb; text-align: left;}
        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 25px; font-size: 14px; border: 1px solid #f5c6cb; text-align: left;}

        /* Modal Logout Umum */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 380px; border-radius: 15px; overflow: hidden; box-shadow: 0 15px 30px rgba(0,0,0,0.3); text-align: center; animation: popIn 0.3s ease-out; }
        .modal-header { background-color: #e74c3c; padding: 25px 20px; }
        .modal-header img { width: 80px; height: auto; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2)); }
        .modal-body { padding: 25px 30px 30px; background: #fff; }
        .modal-body h3 { margin: 0; color: #333; font-size: 20px; }
        .modal-body p { color: #666; margin-bottom: 25px; font-size: 14px; line-height: 1.5; margin-top: 10px;}
        .modal-buttons { display: flex; gap: 15px; justify-content: center; }
        .modal-buttons button { padding: 10px 20px; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; flex: 1; }
        .btn-cancel { background: #e0e0e0; color: #555; }
        .btn-cancel:hover { background: #d5d5d5; }
        .btn-confirm { background: #e74c3c; color: white; box-shadow: 0 4px 6px rgba(231,76,60,0.2);}
        .btn-confirm:hover { background: #c0392b; transform: translateY(-2px);}
        
        @keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

        /* =======================================================
           RESPONSIVE MOBILE (HP)
           ======================================================= */
        @media screen and (max-width: 768px) {
            .mobile-menu-btn { display: block; }
            .header { padding: 0 20px; }
            .header h3 { font-size: 16px; }

            .sidebar {
                position: fixed;
                top: 0; left: -260px; width: 260px; height: 100vh;
                transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 5px 0 15px rgba(0,0,0,0.1);
            }
            .sidebar.active-mobile { left: 0; }
            
            .sidebar h2 { font-size: 18px; justify-content: flex-start; padding-left: 20px;}
            .sidebar-logo-text { display: inline; font-size: 18px; margin-left: 10px;}
            
            .menu { padding-top: 15px; }
            .menu a { flex-direction: row; justify-content: flex-start; padding: 15px 25px; }
            .menu a i { margin-right: 15px; margin-bottom: 0; font-size: 20px;}
            .menu a span.menu-text { font-size: 15px; font-weight: normal;}

            .content { padding: 15px; display: block; }
            .profile-card { padding: 25px 20px; margin: 0 auto; width: 100%; max-width: calc(100vw - 30px); }
            
            .user-info span { display: none; } /* Sembunyikan tulisan "Hai, Admin" di HP biar tidak kepanjangan */
            
            /* Penyesuaian Modal Mungil di HP */
            .modal-box { width: 92%; max-width: 320px; }
            .modal-header { padding: 15px; }
            .modal-header img { width: 60px; }
            .modal-body { padding: 20px; }
            .modal-body h3 { font-size: 18px; }
            .modal-body p { font-size: 13px; margin-bottom: 20px; }
            .modal-buttons { gap: 10px; }
            .modal-buttons button { padding: 10px; font-size: 13px; }
        }
    </style>
</head>
<body>

    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleMobileMenu()"></div>

    <div class="sidebar" id="sidebarMenu">
        <h2 title="Bank Sampah"><i class="fa fa-recycle"></i><span class="sidebar-logo-text">BANK SAMPAH</span></h2>
        <div class="menu">
            <a href="dashboard_admin.php">
                <i class="fa fa-home"></i>
                <span class="menu-text">Beranda</span>
            </a>
            <a href="data_nasabah.php">
                <i class="fa fa-users"></i>
                <span class="menu-text">Nasabah</span>
            </a>
            <a href="kategori_sampah.php">
                <i class="fa fa-trash"></i>
                <span class="menu-text">Kategori</span>
            </a>
            <a href="transaksi_setoran.php">
                <i class="fa fa-exchange-alt"></i>
                <span class="menu-text">Setoran</span>
            </a>
            <a href="transaksi_tarik.php">
                <i class="fa fa-hand-holding-usd"></i>
                <span class="menu-text">Pencairan</span>
            </a>
            <a href="profil_admin.php" class="active">
                <i class="fa fa-user-edit"></i>
                <span class="menu-text">Profil Admin</span>
            </a>
            <a style="cursor: pointer;" onclick="showLogoutModal()">
                <i class="fa fa-sign-out-alt"></i>
                <span class="menu-text">Keluar</span>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h3><i class="fa fa-bars mobile-menu-btn" onclick="toggleMobileMenu()"></i> Pengaturan Profil Admin</h3>
            <div class="user-info">
                <span>Hai, <b><?php echo htmlspecialchars($_SESSION['nama']); ?></b></span>
                <?php 
                // Tampilkan foto admin di header jika ada
                $path_foto_header = (!empty($data_admin['foto_profil']) && file_exists('assets/profil/' . $data_admin['foto_profil'])) 
                                    ? 'assets/profil/' . $data_admin['foto_profil'] 
                                    : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['nama']) . '&background=1abc9c&color=fff';
                ?>
                <img src="<?php echo $path_foto_header; ?>" alt="Avatar">
            </div>
        </div>
        
        <div class="content">
            <div class="profile-card">
                <h2><i class="fa fa-user-shield"></i> Data Administrator</h2>
                <p>Perbarui informasi akun dan kata sandi Anda di sini.</p>

                <?php if(!empty($notif_sukses)): ?>
                    <div class="alert-success"><i class="fa fa-check-circle"></i> <?php echo $notif_sukses; ?></div>
                <?php endif; ?>
                
                <?php if(!empty($notif_gagal)): ?>
                    <div class="alert-danger"><i class="fa fa-exclamation-triangle"></i> <?php echo $notif_gagal; ?></div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
                    
                    <div class="photo-upload">
                        <img id="preview-img" src="<?php echo $path_foto_header; ?>" alt="Foto Profil">
                        <label for="input-foto" title="Ganti Foto Profil"><i class="fa fa-camera"></i></label>
                        <input type="file" id="input-foto" name="foto" accept="image/png, image/jpeg, image/jpg" onchange="previewFile(this)">
                        <input type="hidden" name="foto_lama" value="<?php echo htmlspecialchars($data_admin['foto_profil']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Username (Tidak bisa diubah)</label>
                        <input type="text" value="<?php echo htmlspecialchars($data_admin['username']); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label>Nama Lengkap Admin</label>
                        <input type="text" name="nama" value="<?php echo htmlspecialchars($data_admin['nama_lengkap']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Kata Sandi (Password)</label>
                        <input type="text" name="password" value="<?php echo htmlspecialchars($data_admin['password']); ?>" required>
                    </div>

                    <button type="submit" name="update_profil" class="btn-submit"><i class="fa fa-save"></i> Simpan Perubahan</button>
                </form>
            </div>
        </div>
    </div>

    <div id="customModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <img src="assets/logo-lebak.png" alt="Logo Instansi">
            </div>
            <div class="modal-body">
                <h3>Konfirmasi Logout</h3>
                <p>Apakah Anda yakin ingin keluar dari sistem Bank Sampah Induk?</p>
                <div class="modal-buttons">
                    <button class="btn-cancel" onclick="closeLogoutModal()">Batal</button>
                    <button class="btn-confirm" onclick="window.location.href='logout.php'">Ya, Keluar</button>
                </div>
            </div>
        </div>
    </div>

<script>
    // --- FITUR HAMBURGER MENU (MOBILE) ---
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

    // FUNGSI PREVIEW FOTO LANSUNG BERUBAH SAAT DIPILIH
    function previewFile(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) { 
                document.getElementById('preview-img').src = e.target.result; 
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // FUNGSI LOGOUT
    function showLogoutModal() { document.getElementById('customModal').style.display = 'flex'; }
    function closeLogoutModal() { document.getElementById('customModal').style.display = 'none'; }
</script>

</body>
</html>