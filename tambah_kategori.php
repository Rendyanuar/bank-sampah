<?php
session_start();

// Mencegah browser menyimpan cache halaman
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

// Cek apakah user belum login atau BUKAN ADMIN
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

$pesan = "";
$tambah_sukses = false;

if (isset($_POST['submit_tambah'])) {
    $nama_barang = mysqli_real_escape_string($koneksi, $_POST['nama_barang']);
    $harga_barang = mysqli_real_escape_string($koneksi, $_POST['harga_barang']);
    $nama_file_gambar = "";

    // Proses Upload Gambar (Jika ada file yang dipilih)
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $nama_file_asli = $_FILES['gambar']['name'];
        $tmp_file = $_FILES['gambar']['tmp_name'];
        
        // Membersihkan nama file agar tidak ada spasi (diganti underscore) dan menambahkan angka unik
        $nama_file_bersih = str_replace(" ", "_", $nama_file_asli);
        $nama_file_gambar = time() . '_' . $nama_file_bersih;
        
        // Folder tujuan upload (pastikan folder ini ada)
        $path_upload = "assets/sampah/" . $nama_file_gambar;
        
        // Memindahkan file dari memori sementara ke folder assets/sampah/
        if (!move_uploaded_file($tmp_file, $path_upload)) {
            $pesan = "<div class='alert error'>Gagal mengunggah gambar. Pastikan folder assets/sampah/ sudah ada.</div>";
            $nama_file_gambar = ""; // Kosongkan nama file jika gagal upload
        }
    }

    // Hanya simpan ke database jika tidak ada pesan error dari upload gambar
    if (empty($pesan)) {
        $query_insert = "INSERT INTO kategori_sampah (nama_barang, harga_barang, gambar) 
                         VALUES ('$nama_barang', '$harga_barang', '$nama_file_gambar')";
        
        if (mysqli_query($koneksi, $query_insert)) {
            $tambah_sukses = true;
        } else {
            $pesan = "<div class='alert error'>Gagal menyimpan data ke database.</div>";
        }
    }
}

// Ambil Foto Profil Admin untuk Header
$q_foto = mysqli_query($koneksi, "SELECT foto_profil FROM users WHERE username = '".$_SESSION['username']."'");
$d_foto = mysqli_fetch_assoc($q_foto);
$path_foto_header = (!empty($d_foto['foto_profil']) && file_exists('assets/profil/' . $d_foto['foto_profil'])) 
                    ? 'assets/profil/' . $d_foto['foto_profil'] 
                    : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['nama']) . '&background=1abc9c&color=fff';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Tambah Kategori - Bank Sampah Induk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar Mini (Disetarakan 85px) */
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
        
        /* Main Content */
        .main-content { flex: 1; display: flex; flex-direction: column; position: relative;}
        
        /* HEADER WARNA HIJAU DAN DIKUNCI 60px */
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
        .header h3 { margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 18px;}
        
        /* Tombol Hamburger & Latar Gelap untuk HP */
        .mobile-menu-btn { display: none; font-size: 20px; color: white; cursor: pointer; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; }
        
        /* Dropdown Profil Admin */
        .header-right { display: flex; align-items: center; }
        .profile-dropdown { position: relative; display: inline-block; }
        .profile-dropdown-toggle { display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 20px; transition: 0.3s; cursor: pointer; user-select: none; }
        .profile-dropdown-toggle:hover, .profile-dropdown-toggle:active { background-color: rgba(255,255,255,0.1); }
        .profile-dropdown-toggle img { width: 32px; height: 32px; border-radius: 50%; background: #ddd; border: 2px solid white; object-fit: cover; }
        
        .user-info { font-size: 14px; color: white; display: flex; align-items: center; gap: 10px;}
        .user-info span { color: rgba(255,255,255,0.9); }
        .user-info b { color: white; }
        
        .profile-dropdown-menu { display: none; position: absolute; right: 0; top: 120%; background-color: white; min-width: 200px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); border-radius: 10px; overflow: hidden; z-index: 100; border: 1px solid #eee; }
        .profile-dropdown-menu.show { display: block; animation: fadeIn 0.2s ease-in-out; }
        
        .profile-dropdown-menu a { color: #555; padding: 12px 20px; text-decoration: none; display: flex; align-items: center; font-size: 14px; transition: 0.2s; cursor: pointer; }
        .profile-dropdown-menu a:hover { background-color: #f1fcf9; color: #1abc9c; }
        .profile-dropdown-menu a i { margin-right: 12px; font-size: 16px; width: 20px; text-align: center; }
        .dropdown-divider { height: 1px; background-color: #eee; margin: 0; }

        .content { padding: 30px; flex: 1; overflow-y: auto; display: flex; justify-content: center; align-items: flex-start; box-sizing: border-box;}
        
        /* Card Form */
        .card-form { background-color: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; max-width: 500px; border-top: 5px solid #1abc9c; box-sizing: border-box;}
        .card-form h2 { margin-top: 0; color: #2c3e50; font-size: 22px; text-align: center; margin-bottom: 25px;}
        
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; color: #444; font-size: 14px; font-weight: 700;}
        
        .input-wrapper { display: flex; border: 1px solid #ccc; border-radius: 8px; background-color: #fcfcfc; overflow: hidden; transition: 0.3s; }
        .input-wrapper:focus-within { border-color: #1abc9c; box-shadow: 0 0 8px rgba(26, 188, 156, 0.3); background-color: #fff;}
        .input-wrapper .icon { padding: 12px 15px; background-color: #f1f3f4; color: #1abc9c; border-right: 1px solid #ccc; display: flex; align-items: center; width: 20px; justify-content: center; font-weight: bold;}
        .input-wrapper input[type="text"], .input-wrapper input[type="number"] { flex: 1; padding: 12px 15px; border: none; background: transparent; outline: none; font-size: 14px; color: #333;}
        
        /* Spesial untuk File Upload */
        .input-wrapper input[type="file"] { flex: 1; padding: 9px 15px; border: none; background: transparent; outline: none; font-size: 14px; color: #555; cursor: pointer; }
        
        .button-group { display: flex; gap: 15px; margin-top: 30px; }
        .btn-back, .btn-submit { color: white; border: none; border-radius: 25px; cursor: pointer; text-decoration: none; transition: 0.3s; text-align: center; font-weight: bold;}
        .btn-back { background-color: #95a5a6; padding: 12px 25px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px rgba(149,165,166,0.2);}
        .btn-submit { background-color: #1abc9c; padding: 12px; flex: 1; letter-spacing: 1px; font-size: 15px; box-shadow: 0 4px 6px rgba(26,188,156,0.2);}
        .btn-back:hover { background-color: #7f8c8d; transform: translateY(-2px); }
        .btn-submit:hover { background-color: #16a085; transform: translateY(-2px); }

        .alert { padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500;}
        .alert.error { background-color: #fdeded; color: #e53935; border: 1px solid #ffcdd2; border-left: 5px solid #e53935; }

        /* Modal Global */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 360px; border-radius: 16px; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.4); text-align: center; animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        
        .modal-header-green { background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%); padding: 25px 20px; color: white; }
        .modal-header-red { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); padding: 25px 20px; color: white; }
        .modal-header-green img, .modal-header-red img { width: 80px; height: auto; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2)); }
        .modal-header-green i, .modal-header-red i { font-size: 60px; text-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        
        .modal-body { padding: 25px 30px 30px; background: #fff; }
        .modal-body h3 { margin: 0 0 10px; color: #333; font-size: 22px; font-weight: 800;}
        .modal-body p { color: #555; margin-bottom: 25px; font-size: 15px; line-height: 1.5; margin-top: 10px;}
        .modal-buttons { display: flex; gap: 15px; justify-content: center; }
        .modal-buttons button { padding: 12px 20px; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; flex: 1; }
        
        .btn-cancel { background: #e0e0e0; color: #555; }
        .btn-cancel:hover { background: #d5d5d5; }
        .btn-confirm-red { background: #e74c3c; color: white; box-shadow: 0 4px 6px rgba(231,76,60,0.2);}
        .btn-confirm-red:hover { background: #c0392b; transform: translateY(-2px);}
        .btn-confirm-green { background: #16a085; color: white; box-shadow: 0 4px 6px rgba(22,160,133,0.3); width: 100%;}
        .btn-confirm-green:hover { background: #12876f; transform: translateY(-2px);}
        
        @keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

        /* =======================================================
           RESPONSIVE MOBILE (HP)
           ======================================================= */
        @media screen and (max-width: 768px) {
            .mobile-menu-btn { display: block; }
            .header { padding: 0 20px; }
            .header h3 { font-size: 16px; }

            /* Sembunyikan kata "Hai, Admin" di HP */
            .user-info span { display: none; }
            .user-info b { display: none; }
            
            .sidebar {
                position: fixed;
                top: 0; left: -260px; width: 260px; height: 100vh;
                transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 5px 0 15px rgba(0,0,0,0.1);
            }
            .sidebar.active-mobile { left: 0; }
            
            .sidebar h2 { font-size: 18px; justify-content: flex-start; padding-left: 20px;}
            .sidebar-logo-text { display: inline; font-size: 18px; margin-left: 10px; font-family: 'Segoe UI', sans-serif;}
            
            .menu { padding-top: 15px; }
            .menu a { flex-direction: row; justify-content: flex-start; padding: 15px 25px; }
            .menu a i { margin-right: 15px; margin-bottom: 0; font-size: 20px;}
            .menu a span.menu-text { font-size: 15px; font-weight: normal;}

            .content { padding: 15px; display: block;}
            .card-form { padding: 25px 20px; margin: 0 auto; width: 100%; max-width: calc(100vw - 30px); }
            .card-form h2 { font-size: 20px; }
            
            /* Penyesuaian Modal Mungil di HP */
            .modal-box { width: 92%; max-width: 320px; }
            .modal-header-green, .modal-header-red { padding: 15px; }
            .modal-header-green i, .modal-header-red i { font-size: 40px; }
            .modal-header-red img { width: 60px; }
            .modal-body { padding: 20px; }
            .modal-body h3 { font-size: 18px; margin-bottom: 8px;}
            .modal-body p { font-size: 13px; margin-bottom: 20px; line-height: 1.4; }
            .modal-buttons { gap: 10px; }
            .modal-buttons button { padding: 10px; font-size: 13px; }
            
            /* Tombol Form di HP */
            .button-group { flex-direction: column-reverse; gap: 10px;}
        }
    </style>
</head>
<body>

    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleMobileMenu()"></div>

    <div class="sidebar" id="sidebarMenu">
        <h2 title="Bank Sampah"><i class="fa fa-recycle"></i><span class="sidebar-logo-text">BANK SAMPAH</span></h2>
        <div class="menu">
            <a href="dashboard_admin.php" title="Dashboard">
                <i class="fa fa-home"></i>
                <span class="menu-text">Beranda</span>
            </a>
            <a href="data_nasabah.php" title="Data Nasabah">
                <i class="fa fa-users"></i>
                <span class="menu-text">Nasabah</span>
            </a>
            <a href="kategori_sampah.php" class="active" title="Kategori Sampah">
                <i class="fa fa-trash"></i>
                <span class="menu-text">Kategori</span>
            </a>
            <a href="transaksi_setoran.php" title="Transaksi Setoran">
                <i class="fa fa-exchange-alt"></i>
                <span class="menu-text">Setoran</span>
            </a>
            <a href="transaksi_tarik.php" title="Pencairan Saldo">
                <i class="fa fa-hand-holding-usd"></i>
                <span class="menu-text">Pencairan</span>
            </a>
            <a style="cursor: pointer;" onclick="showLogoutModal()" title="Logout">
                <i class="fa fa-sign-out-alt"></i>
                <span class="menu-text">Keluar</span>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h3><i class="fa fa-bars mobile-menu-btn" onclick="toggleMobileMenu()"></i> Tambah Kategori</h3>
            
            <div class="header-right">
                <div class="profile-dropdown">
                    <div class="profile-dropdown-toggle" onclick="toggleDropdown()">
                        <div class="user-info">
                            <span style="font-size: 12px; display: block; line-height: 1;">Administrator</span>
                            <b><?php echo isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'Admin'; ?></b>
                        </div>
                        <img src="<?php echo $path_foto_header; ?>" alt="Avatar Admin">
                        <i class="fa fa-chevron-down" style="font-size: 12px; color: white;"></i>
                    </div>
                    
                    <div id="myDropdown" class="profile-dropdown-menu">
                        <a href="profil_admin.php"><i class="fa fa-user-edit"></i> Profil & Sandi</a>
                        <div class="dropdown-divider"></div>
                        <a onclick="showLogoutModal()" style="color: #e74c3c;"><i class="fa fa-sign-out-alt"></i> Keluar</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="content">
            <div class="card-form">
                <h2>Form Kategori Baru</h2>
                
                <?php echo $pesan; ?>
                
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="input-group">
                        <label>Nama Barang / Kategori</label>
                        <div class="input-wrapper">
                            <span class="icon"><i class="fa fa-box"></i></span>
                            <input type="text" name="nama_barang" placeholder="Masukkan nama barang baru" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Harga Barang (Per Kg)</label>
                        <div class="input-wrapper">
                            <span class="icon">Rp</span>
                            <input type="number" name="harga_barang" placeholder="Contoh: 1500" required min="0">
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Upload Gambar Barang (Opsional)</label>
                        <div class="input-wrapper">
                            <span class="icon"><i class="fa fa-image"></i></span>
                            <input type="file" name="gambar" accept="image/png, image/jpeg, image/jpg">
                        </div>
                        <span style="font-size: 12px; color: #888; display: block; margin-top: 5px;">* Format yang diizinkan: JPG, JPEG, PNG.</span>
                    </div>
                    
                    <div class="button-group">
                        <a href="kategori_sampah.php" class="btn-back"><i class="fa fa-arrow-left"></i> &nbsp;Batal</a>
                        <button type="submit" name="submit_tambah" class="btn-submit">TAMBAH DATA</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="logoutModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header-red">
                <img src="assets/logo-lebak.png" alt="Logo Instansi">
            </div>
            <div class="modal-body">
                <h3>Konfirmasi Logout</h3>
                <p>Apakah Anda yakin ingin keluar dari sistem Bank Sampah Induk?</p>
                <div class="modal-buttons">
                    <button class="btn-cancel" onclick="closeLogoutModal()">Batal</button>
                    <button class="btn-confirm-red" onclick="window.location.href='logout.php'">Ya, Keluar</button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($tambah_sukses) : ?>
    <div id="successModal" class="modal-overlay" style="display: flex;">
        <div class="modal-box">
            <div class="modal-header-green">
                <i class="fa fa-check-circle"></i>
            </div>
            <div class="modal-body">
                <h3>Berhasil Ditambahkan!</h3>
                <p>Kategori sampah baru telah masuk ke dalam daftar.</p>
                <button class="btn-confirm-green" onclick="window.location.href='kategori_sampah.php'">Lihat Daftar Kategori</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

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

    // FUNGSI DROPDOWN PROFIL
    function toggleDropdown() {
        document.getElementById("myDropdown").classList.toggle("show");
    }

    window.onclick = function(event) {
        if (!event.target.closest('.profile-dropdown')) {
            var dropdowns = document.getElementsByClassName("profile-dropdown-menu");
            for (var i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
    }

    // MODAL LOGOUT
    function showLogoutModal() { document.getElementById('logoutModal').style.display = 'flex'; }
    function closeLogoutModal() { document.getElementById('logoutModal').style.display = 'none'; }
</script>

</body>
</html>