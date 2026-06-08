<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

if (!isset($_GET['username']) || empty($_GET['username'])) {
    header("Location: data_nasabah.php");
    exit;
}

$target_username = mysqli_real_escape_string($koneksi, $_GET['username']);
$pesan_sukses = "";
$pesan_error = "";

// ==================================================================
// PROSES UPDATE PROFIL NASABAH SAJA
// ==================================================================
if (isset($_POST['update_profil'])) {
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $telepon = mysqli_real_escape_string($koneksi, $_POST['nomor_telepon']);
    $password = mysqli_real_escape_string($koneksi, $_POST['password']);

    if (!empty($password)) {
        $q_upd = "UPDATE users SET nama_lengkap='$nama', nomor_telepon='$telepon', password='$password' WHERE username='$target_username' AND role='nasabah'";
    } else {
        $q_upd = "UPDATE users SET nama_lengkap='$nama', nomor_telepon='$telepon' WHERE username='$target_username' AND role='nasabah'";
    }
    
    if (mysqli_query($koneksi, $q_upd)) {
        $pesan_sukses = "Data profil nasabah berhasil diperbarui!";
    } else {
        $pesan_error = "Gagal memperbarui profil: " . mysqli_error($koneksi);
    }
}

// AMBIL DATA NASABAH TERBARU
$q_nasabah = mysqli_query($koneksi, "SELECT * FROM users WHERE username='$target_username' AND role='nasabah'");
if(mysqli_num_rows($q_nasabah) == 0){
    header("Location: data_nasabah.php"); 
    exit;
}
$data_nasabah = mysqli_fetch_assoc($q_nasabah);

// Foto Profil Admin
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
    <title>Edit Nasabah - Bank Sampah Induk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }
        
        .sidebar { width: 85px; background-color: #2c3e50; color: white; display: flex; flex-direction: column; z-index: 1001;}
        .sidebar h2 { margin: 0; background-color: #1abc9c; font-size: 24px; cursor: default; white-space: nowrap; height: 60px; display: flex; align-items: center; justify-content: center;}
        .sidebar-logo-text { display: none; }
        .menu { flex: 1; padding-top: 10px; }
        .menu a { display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 12px 5px; color: #bdc3c7; text-decoration: none; transition: 0.3s; border-left: 4px solid transparent;}
        .menu a:hover, .menu a.active { background-color: #34495e; color: white; border-left-color: #1abc9c; }
        .menu a i { font-size: 22px; margin-bottom: 4px; }
        .menu a span.menu-text { font-size: 10px; text-align: center; font-weight: 600;}

        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden;}
        
        .header { background-color: #1abc9c; padding: 0 30px; height: 60px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 10;}
        .header h3 { margin: 0; color: white; font-size: 18px; display: flex; align-items: center; gap: 10px;}
        .header h3 a { color: white; text-decoration: none; display: flex; align-items: center; gap: 8px;}
        
        .mobile-menu-btn { display: none; font-size: 20px; color: white; cursor: pointer;}
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; }
        
        .header-right { display: flex; align-items: center; }
        .profile-dropdown { position: relative; display: inline-block; }
        .profile-dropdown-toggle { display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 20px; transition: 0.3s; cursor: pointer;}
        .profile-dropdown-toggle img { width: 32px; height: 32px; border-radius: 50%; border: 2px solid white; object-fit: cover; }
        .user-info { font-size: 14px; color: white; display: flex; align-items: center; gap: 10px;}
        .user-info span { color: rgba(255,255,255,0.9); }
        .profile-dropdown-menu { display: none; position: absolute; right: 0; top: 120%; background-color: white; min-width: 200px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); border-radius: 10px; overflow: hidden; z-index: 100; border: 1px solid #eee; }
        .profile-dropdown-menu.show { display: block; }
        .profile-dropdown-menu a { color: #555; padding: 12px 20px; text-decoration: none; display: flex; align-items: center; font-size: 14px; transition: 0.2s;}
        .profile-dropdown-menu a:hover { background-color: #f1fcf9; color: #1abc9c; }
        .profile-dropdown-menu a i { margin-right: 12px; width: 20px; text-align: center; }

        .content { padding: 30px; flex: 1; overflow-y: auto; display: flex; justify-content: center; align-items: flex-start;}

        .card-form { background-color: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; max-width: 550px;}
        .card-form h3 { margin-top: 0; margin-bottom: 25px; color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 15px; font-size: 20px;}
        
        .form-group { margin-bottom: 20px; text-align: left;}
        .form-group label { display: block; font-size: 13px; color: #555; margin-bottom: 8px; font-weight: bold; }
        .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box; background-color: #f9fbfb;}
        .form-group input:focus { border-color: #1abc9c; outline: none; background-color: #fff;}
        .form-group input[readonly] { background-color: #ecf0f1; color: #333; font-weight: bold; border-color: #ddd; cursor: not-allowed;}
        
        .btn-simpan { background-color: #1abc9c; color: white; border: none; padding: 14px 20px; border-radius: 8px; font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%;}
        .btn-simpan:hover { background-color: #16a085; transform: translateY(-2px);}

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 25px; font-size: 14px; font-weight: 500;}
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}

        /* MODAL LOGOUT */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 320px; border-radius: 15px; overflow: hidden; box-shadow: 0 15px 30px rgba(0,0,0,0.3); text-align: center; animation: popIn 0.3s ease-out; }
        .modal-header-red { background: #e74c3c; padding: 25px 20px; }
        .modal-header-red img { width: 80px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2)); }
        .modal-body { padding: 25px; background: #fff; }
        .modal-buttons { display: flex; gap: 10px; justify-content: center; margin-top: 20px;}
        .modal-buttons button { padding: 10px 15px; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer; flex: 1;}
        .btn-cancel { background: #e0e0e0; color: #555; }
        .btn-confirm-red { background: #e74c3c; color: white; }

        @keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

        @media screen and (max-width: 768px) {
            .mobile-menu-btn { display: block; }
            .header { padding: 0 20px; }
            .user-info span, .user-info b { display: none; }
            .sidebar { position: fixed; top: 0; left: -260px; width: 260px; height: 100vh; transition: 0.3s; box-shadow: 5px 0 15px rgba(0,0,0,0.1); }
            .sidebar.active-mobile { left: 0; }
            .sidebar h2 { justify-content: flex-start; padding-left: 20px; font-size: 18px;}
            .sidebar-logo-text { display: inline; margin-left: 10px;}
            .menu a { flex-direction: row; justify-content: flex-start; padding: 15px 25px; }
            .menu a i { margin-right: 15px; margin-bottom: 0; font-size: 20px;}
            .menu a span.menu-text { font-size: 15px; font-weight: normal;}
            .content { padding: 15px; display: block;}
            .card-form { padding: 25px; max-width: 100%;}
        }
    </style>
</head>
<body>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleMobileMenu()"></div>

    <div class="sidebar" id="sidebarMenu">
        <h2 title="Bank Sampah"><i class="fa fa-recycle"></i><span class="sidebar-logo-text">BANK SAMPAH</span></h2>
        <div class="menu">
            <a href="dashboard_admin.php"><i class="fa fa-home"></i><span class="menu-text">Beranda</span></a>
            <a href="data_nasabah.php" class="active"><i class="fa fa-users"></i><span class="menu-text">Nasabah</span></a>
            <a href="kategori_sampah.php"><i class="fa fa-trash"></i><span class="menu-text">Kategori</span></a>
            <a href="transaksi_setoran.php"><i class="fa fa-exchange-alt"></i><span class="menu-text">Setoran</span></a>
            <a href="transaksi_tarik.php"><i class="fa fa-hand-holding-usd"></i><span class="menu-text">Pencairan</span></a>
            <a style="cursor:pointer;" onclick="showLogoutModal()"><i class="fa fa-sign-out-alt"></i><span class="menu-text">Keluar</span></a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h3>
                <i class="fa fa-bars mobile-menu-btn" onclick="toggleMobileMenu()"></i>
                <a href="data_nasabah.php"><i class="fa fa-arrow-left"></i> Kembali</a>
            </h3>
            
            <div class="header-right">
                <div class="profile-dropdown">
                    <div class="profile-dropdown-toggle" onclick="toggleDropdown()">
                        <div class="user-info" style="text-align: right; margin-right: 5px;">
                            <span style="font-size: 12px; display: block; line-height: 1;">Administrator</span>
                            <b style="font-size: 14px;"><?php echo isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'Admin'; ?></b>
                        </div>
                        <img src="<?php echo $path_foto_header; ?>" alt="Avatar">
                        <i class="fa fa-chevron-down" style="font-size: 12px; color: white; margin-left: 5px;"></i>
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
                <h3><i class="fa fa-user-edit"></i> Edit Profil Nasabah</h3>
                
                <?php if(!empty($pesan_sukses)): ?>
                    <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?php echo $pesan_sukses; ?></div>
                <?php endif; ?>
                <?php if(!empty($pesan_error)): ?>
                    <div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?php echo $pesan_error; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label>Nomor Anggota / Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($data_nasabah['username']); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Lengkap / Instansi</label>
                        <input type="text" name="nama_lengkap" value="<?php echo htmlspecialchars($data_nasabah['nama_lengkap']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Nomor Telepon / WhatsApp</label>
                        <input type="text" name="nomor_telepon" value="<?php echo htmlspecialchars($data_nasabah['nomor_telepon']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Reset Password (Opsional)</label>
                        <input type="text" name="password" placeholder="Isi jika ingin mengganti sandi nasabah ini">
                        <span style="font-size:11px; color:#e74c3c; margin-top:5px; display:block;">*Biarkan kosong jika tidak ingin mengubah password.</span>
                    </div>
                    
                    <button type="submit" name="update_profil" class="btn-simpan">
                        <i class="fa fa-save"></i> Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="logoutModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header-red">
                <img src="assets/logo-lebak.png" alt="Logo">
            </div>
            <div class="modal-body" style="text-align:center;">
                <h3 style="margin:0 0 10px; color:#333;">Konfirmasi Logout</h3>
                <p style="color:#555; margin-bottom:20px;">Apakah Anda yakin ingin keluar dari sistem?</p>
                <div class="modal-buttons">
                    <button class="btn-cancel" onclick="closeLogoutModal()">Batal</button>
                    <button class="btn-confirm-red" onclick="window.location.href='logout.php'">Ya, Keluar</button>
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
    function toggleDropdown() { document.getElementById("myDropdown").classList.toggle("show"); }
    window.onclick = function(event) {
        if (!event.target.closest('.profile-dropdown')) {
            var dropdowns = document.getElementsByClassName("profile-dropdown-menu");
            for (var i = 0; i < dropdowns.length; i++) {
                if (dropdowns[i].classList.contains('show')) dropdowns[i].classList.remove('show');
            }
        }
    }
    function showLogoutModal() { document.getElementById('logoutModal').style.display = 'flex'; }
    function closeLogoutModal() { document.getElementById('logoutModal').style.display = 'none'; }
</script>

</body>
</html>