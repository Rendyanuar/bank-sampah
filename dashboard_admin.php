<?php
session_start();

// Mencegah browser menyimpan cache halaman ini
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

// Cek apakah user belum login atau bukan admin
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

$notif_sukses = "";
$notif_gagal = "";

// =========================================================
// LOGIKA RESET DATA (Dijalankan jika tombol reset diklik)
// =========================================================
if (isset($_POST['reset_data'])) {
    $hapus_setoran = mysqli_query($koneksi, "DELETE FROM transaksi_setoran");
    $hapus_tarik = mysqli_query($koneksi, "DELETE FROM transaksi_tarik");
    
    // Reset Auto Increment agar penomoran ID di database kembali mulai dari 1
    mysqli_query($koneksi, "ALTER TABLE transaksi_setoran AUTO_INCREMENT = 1");
    mysqli_query($koneksi, "ALTER TABLE transaksi_tarik AUTO_INCREMENT = 1");
    
    // HAPUS reset saldo dari validasi IF
    if ($hapus_setoran && $hapus_tarik) {
        $notif_sukses = "Sistem berhasil di-reset! Semua riwayat transaksi dihapus, namun Saldo Nasabah dan Admin tetap utuh/aman.";
    } else {
        $notif_gagal = "Gagal me-reset data sistem. Terjadi kesalahan pada database.";
    }
}

// =========================================================
// LOGIKA FILTER WAKTU (Untuk Tabel Rasio)
// =========================================================
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'semua';
$where_query = "";

if ($filter == 'bulan_ini') {
    $where_query = " AND MONTH(ts.tanggal) = MONTH(CURRENT_DATE()) AND YEAR(ts.tanggal) = YEAR(CURRENT_DATE())";
} elseif ($filter == 'tahun_ini') {
    $where_query = " AND YEAR(ts.tanggal) = YEAR(CURRENT_DATE())";
}

// =========================================================
// MENGAMBIL DATA ASLI DARI DATABASE UNTUK KARTU RINGKASAN
// =========================================================

// 1. Hitung Total Nasabah
$query_nasabah = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'nasabah'");
$data_nasabah = mysqli_fetch_assoc($query_nasabah);
$total_nasabah = $data_nasabah['total'];

// 2. Hitung Total Kategori Sampah
$query_kategori = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM kategori_sampah");
$data_kategori = mysqli_fetch_assoc($query_kategori);
$total_kategori = $data_kategori['total'];

// 3. Hitung Total Sampah Terkumpul (Hanya yang statusnya 'disetujui' atau 'selesai')
$query_berat = mysqli_query($koneksi, "SELECT SUM(berat) as total_berat FROM transaksi_setoran WHERE status = 'disetujui' OR status = 'selesai'");
$data_berat = mysqli_fetch_assoc($query_berat);
$total_berat = $data_berat['total_berat'] ? $data_berat['total_berat'] : 0; 

// 4. Hitung Total Saldo Beredar (Kas Admin)
$q_saldo_admin = mysqli_query($koneksi, "SELECT saldo FROM users WHERE role = 'admin' LIMIT 1");
$d_saldo_admin = mysqli_fetch_assoc($q_saldo_admin);
$saldo_admin = ($d_saldo_admin['saldo'] !== null) ? $d_saldo_admin['saldo'] : 0;

// =========================================================
// MENGHITUNG DATA PENDING UNTUK NOTIFIKASI SIDEBAR & KARTU
// =========================================================
$q_notif_setoran = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM transaksi_setoran WHERE status = 'pending'");
$notif_setoran = mysqli_fetch_assoc($q_notif_setoran)['total'];

$q_notif_tarik = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM transaksi_tarik WHERE status = 'pending'");
$notif_tarik = mysqli_fetch_assoc($q_notif_tarik)['total'];

// =========================================================
// LOGIKA TAMBAHAN: PAGINATION & RASIO
// =========================================================

// A. Logika Pagination (Maks 5 data per halaman)
$batas = 5;
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
$halaman_awal = ($halaman > 1) ? ($halaman * $batas) - $batas : 0;

$q_total_transaksi = mysqli_query($koneksi, "SELECT id FROM transaksi_setoran");
$jumlah_data = mysqli_num_rows($q_total_transaksi);
$total_halaman = ceil($jumlah_data / $batas);
if ($total_halaman == 0) $total_halaman = 1; // Mencegah 1/0 jika kosong

// B. Logika Rasio (Total berat semua sampah untuk persentase dengan filter waktu)
$query_total_rasio = mysqli_query($koneksi, "SELECT SUM(ts.berat) as total_berat_filter FROM transaksi_setoran ts WHERE (ts.status = 'disetujui' OR ts.status = 'selesai') $where_query");
$data_total_rasio = mysqli_fetch_assoc($query_total_rasio);
$total_berat_filter = $data_total_rasio['total_berat_filter'] ? $data_total_rasio['total_berat_filter'] : 0;
$total_berat_semua = ($total_berat_filter > 0) ? $total_berat_filter : 1; // Mencegah pembagian dengan 0

// C. Ambil Foto Profil Admin
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
    <title>Dashboard Admin - Bank Sampah Induk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar Styling */
        .sidebar { width: 85px; background-color: #2c3e50; color: white; display: flex; flex-direction: column; z-index: 1001;}
        .sidebar h2 { margin: 0; background-color: #1abc9c; font-size: 24px; cursor: default; white-space: nowrap; height: 60px; display: flex; align-items: center; justify-content: center; box-sizing: border-box;}
        .sidebar-logo-text { display: none; }
        .menu { flex: 1; padding-top: 10px; }
        .menu a { display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 12px 5px; color: #bdc3c7; text-decoration: none; transition: 0.3s; border-left: 4px solid transparent; cursor: pointer; position: relative;}
        .menu a:hover, .menu a.active { background-color: #34495e; color: white; border-left-color: #1abc9c; }
        .menu a i { font-size: 22px; margin-bottom: 4px; }
        .menu a span.menu-text { font-size: 10px; text-align: center; line-height: 1.2; font-weight: 600;}
        
        .notif-badge { position: absolute; top: 5px; right: 15px; background-color: #e74c3c; color: white; font-size: 10px; font-weight: bold; min-width: 15px; height: 15px; border-radius: 15px; display: flex; justify-content: center; align-items: center; padding: 0 3px; border: 2px solid #2c3e50; animation: popIn 0.3s ease-out;}
        .menu a:hover .notif-badge, .menu a.active .notif-badge { border-color: #34495e; }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; }
        
        /* Header */
        .header { background-color: #1abc9c; padding: 0 30px; height: 60px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 10; box-sizing: border-box;}
        .header h3 { margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 18px; }
        .mobile-menu-btn { display: none; font-size: 20px; color: white; cursor: pointer; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; }

        .content { padding: 30px; flex: 1; overflow-y: auto; overflow-x: hidden; width: 100%; box-sizing: border-box;}
        
        /* Dropdown Profil */
        .header-right { display: flex; align-items: center; }
        .profile-dropdown { position: relative; display: inline-block; }
        .profile-dropdown-toggle { display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 20px; transition: 0.3s; cursor: pointer; user-select: none; }
        .profile-dropdown-toggle:hover, .profile-dropdown-toggle:active { background-color: rgba(255,255,255,0.1); }
        .profile-dropdown-toggle img { width: 32px; height: 32px; border-radius: 50%; background: #ddd; border: 2px solid white; object-fit: cover; }
        .profile-dropdown-menu { display: none; position: absolute; right: 0; top: 120%; background-color: white; min-width: 200px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); border-radius: 10px; overflow: hidden; z-index: 100; border: 1px solid #eee; }
        .profile-dropdown-menu.show { display: block; animation: fadeIn 0.2s ease-in-out; }
        .profile-dropdown-menu a { color: #555; padding: 12px 20px; text-decoration: none; display: flex; align-items: center; font-size: 14px; transition: 0.2s; cursor: pointer; justify-content: flex-start; border: none; }
        .profile-dropdown-menu a:hover { background-color: #f1fcf9; color: #1abc9c; }
        .profile-dropdown-menu a i { margin-right: 12px; font-size: 16px; width: 20px; text-align: center; }
        .dropdown-divider { height: 1px; background-color: #eee; margin: 0; }
        
        /* Kartu Ringkasan */
        .dashboard-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; border-bottom: 4px solid #ccc; transition: 0.3s; text-decoration: none; color: inherit; cursor: pointer; position: relative;}
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .stat-card .icon { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white;}
        .stat-card .info h4 { margin: 0; color: #888; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;}
        .stat-card .info h2 { margin: 5px 0 0; color: #2c3e50; font-size: 24px;}
        
        .bg-blue { background: linear-gradient(135deg, #3498db, #2980b9); }
        .border-blue { border-color: #3498db; }
        .bg-orange { background: linear-gradient(135deg, #f39c12, #d35400); }
        .border-orange { border-color: #f39c12; }
        .bg-green { background: linear-gradient(135deg, #1abc9c, #16a085); }
        .border-green { border-color: #1abc9c; }
        .bg-purple { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .border-purple { border-color: #9b59b6; }

        /* Tabel & Card Tabel */
        .card-table { background-color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; width: 100%; max-width: 100%; box-sizing: border-box;}
        .card-table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;}
        .card-table-header h3 { margin: 0; color: #2c3e50; font-size: 18px;}
        
        .table-responsive { width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px; display: block; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 650px; white-space: nowrap;}
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        table th { background-color: #f8f9fa; color: #555; font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px;}
        table tr:hover { background-color: #f9fbfb; }
        
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; color: white;}
        .badge-pending { background-color: #f39c12; } 
        .badge-disetujui { background-color: #3498db; } 
        .badge-selesai { background-color: #2ecc71; } 
        .badge-ditolak { background-color: #e74c3c; } 

        .tools-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap;}
        
        .filter-select { padding: 8px 15px; border: 1px solid #ddd; border-radius: 5px; font-size: 13px; font-weight: bold; color: #333; background: #f8f9fa; outline: none; cursor: pointer;}
        .filter-select:focus { border-color: #3498db; }
        
        .btn-export { background-color: #27ae60; color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-size: 13px; font-weight: bold; transition: 0.2s; display: flex; align-items: center; gap: 5px; border: none; cursor: pointer;}
        .btn-export:hover { background-color: #219150; }
        
        .btn-reset { background-color: #e74c3c; color: white; padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; font-weight: bold; transition: 0.2s; display: flex; align-items: center; gap: 5px;}
        .btn-reset:hover { background-color: #c0392b; }
        
        .pagination { display: flex; justify-content: center; align-items: center; gap: 15px; margin-top: 20px; }
        .pagination a { padding: 6px 15px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 13px;}
        .pagination a:hover { background: #2980b9; }
        .pagination .info-halaman { font-weight: bold; color: #555; font-size: 13px; }
        
        .progress-bar-bg { background: #eee; border-radius: 10px; height: 8px; width: 100%; margin-top: 5px; overflow: hidden; }
        .progress-bar-fill { background: #1abc9c; height: 100%; }

        /* MODAL STANDAR */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 350px; border-radius: 15px; overflow: hidden; box-shadow: 0 15px 30px rgba(0,0,0,0.3); text-align: center; animation: popIn 0.3s ease-out; }
        .modal-header { background-color: #1abc9c; padding: 25px 20px; }
        .modal-header img { width: 80px; height: auto; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2)); }
        .modal-header-danger { background-color: #e74c3c; padding: 25px 20px; color: white;}
        .modal-header-danger i { font-size: 60px; text-shadow: 0 4px 6px rgba(0,0,0,0.2); }
        .modal-header-success { background-color: #2ecc71; padding: 25px 20px; color: white;}
        .modal-header-success i { font-size: 60px; }
        .modal-body { padding: 25px 30px 30px; background: #fff; }
        .modal-body h3 { margin: 0; color: #333; font-size: 20px; }
        .modal-body p { color: #666; margin-bottom: 25px; font-size: 14px; line-height: 1.5; margin-top: 10px;}
        .modal-buttons { display: flex; gap: 10px; justify-content: center; }
        .modal-buttons button { padding: 10px 20px; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; flex: 1; }
        .btn-cancel { background: #e0e0e0; color: #555; }
        .btn-cancel:hover { background: #d5d5d5; }
        .btn-confirm { background: #e74c3c; color: white; box-shadow: 0 4px 6px rgba(231,76,60,0.2);}
        .btn-confirm:hover { background: #c0392b; transform: translateY(-2px);}
        .btn-confirm-green { background: #27ae60; color: white; box-shadow: 0 4px 6px rgba(39, 174, 96,0.2);}
        
        @keyframes popIn { 0% { transform: scale(0.9); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

        /* RESPONSIVE MOBILE */
        @media screen and (max-width: 768px) {
            .mobile-menu-btn { display: block; }
            .header { padding: 0 20px; }
            .user-info span { color: rgba(255,255,255,0.8) !important; }
            .user-info b { color: white !important; }
            .profile-dropdown-toggle i { color: white !important; }
            .sidebar { position: fixed; top: 0; left: -260px; width: 260px; height: 100vh; transition: 0.3s; box-shadow: 5px 0 15px rgba(0,0,0,0.1); }
            .sidebar.active-mobile { left: 0; }
            .sidebar h2 { font-size: 18px; justify-content: flex-start; padding-left: 20px;}
            .sidebar-logo-text { display: inline; font-size: 18px; margin-left: 10px;}
            .menu { padding-top: 15px; }
            .menu a { flex-direction: row; justify-content: flex-start; padding: 15px 25px; }
            .menu a i { margin-right: 15px; margin-bottom: 0; font-size: 20px;}
            .menu a span.menu-text { font-size: 15px; font-weight: normal;}
            .content { padding: 15px; }
            .card-table { padding: 20px; width: 100%; max-width: calc(100vw - 30px); }
            .modal-box { width: 90%; }
            .card-table-header { flex-direction: column; align-items: flex-start; gap: 15px;}
            .btn-excel { width: 100%; justify-content: center;}
            .tools-group { width: 100%; display: flex; flex-direction: column; align-items: stretch; gap: 10px; }
            .filter-select { width: 100%; box-sizing: border-box; }
        }
    </style>
</head>
<body>

    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleMobileMenu()"></div>

    <div class="sidebar" id="sidebarMenu">
        <h2 title="Bank Sampah"><i class="fa fa-recycle"></i><span class="sidebar-logo-text">BANK SAMPAH</span></h2>
        <div class="menu">
            <a href="dashboard_admin.php" class="active">
                <i class="fa fa-home"></i><span class="menu-text">Beranda</span>
            </a>
            <a href="data_nasabah.php">
                <i class="fa fa-users"></i><span class="menu-text">Nasabah</span>
            </a>
            <a href="kategori_sampah.php">
                <i class="fa fa-trash"></i><span class="menu-text">Kategori</span>
            </a>
            <a href="transaksi_setoran.php">
                <i class="fa fa-exchange-alt"></i><span class="menu-text">Setoran</span>
                <?php if($notif_setoran > 0) echo "<span class='notif-badge'>$notif_setoran</span>"; ?>
            </a>
            <a href="transaksi_tarik.php">
                <i class="fa fa-hand-holding-usd"></i><span class="menu-text">Pencairan</span>
                <?php if($notif_tarik > 0) echo "<span class='notif-badge'>$notif_tarik</span>"; ?>
            </a>
            <a style="cursor: pointer;" onclick="showLogoutModal()">
                <i class="fa fa-sign-out-alt"></i><span class="menu-text">Keluar</span>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h3><i class="fa fa-bars mobile-menu-btn" onclick="toggleMobileMenu()"></i> Beranda Admin</h3>
            <div class="header-right">
                <div class="profile-dropdown">
                    <div class="profile-dropdown-toggle" onclick="toggleDropdown()">
                        <div class="user-info" style="text-align: right; margin-right: 5px;">
                            <span style="font-size: 12px; color: rgba(255,255,255,0.8); display: block; line-height: 1;">Administrator</span>
                            <b style="font-size: 14px; color: white;"><?php echo isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'Admin'; ?></b>
                        </div>
                        <img src="<?php echo $path_foto_header; ?>" alt="Avatar Admin">
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
            
            <div class="dashboard-cards">
                <a href="data_nasabah.php" class="stat-card border-blue">
                    <div class="icon bg-blue"><i class="fa fa-users"></i></div>
                    <div class="info">
                        <h4>Total Nasabah</h4>
                        <h2><?php echo $total_nasabah; ?> Orang</h2>
                    </div>
                </a>

                <a href="kategori_sampah.php" class="stat-card border-orange">
                    <div class="icon bg-orange"><i class="fa fa-boxes"></i></div>
                    <div class="info">
                        <h4>Kategori Sampah</h4>
                        <h2><?php echo $total_kategori; ?> Jenis</h2>
                    </div>
                </a>

                <a href="transaksi_setoran.php" class="stat-card border-green">
                    <div class="icon bg-green"><i class="fa fa-weight"></i></div>
                    <div class="info">
                        <h4>Sampah Terkumpul</h4>
                        <h2><?php echo $total_berat; ?> Kg</h2>
                    </div>
                    <?php if($notif_setoran > 0) echo "<span style='position:absolute; top:-5px; right:-5px; background:#e74c3c; color:white; width:25px; height:25px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:bold; border:3px solid white; box-shadow:0 3px 5px rgba(0,0,0,0.2); animation:popIn 0.3s;'>$notif_setoran</span>"; ?>
                </a>

               <a href="topup_admin.php" class="stat-card border-purple">
                    <div class="icon bg-purple"><i class="fa fa-vault"></i></div>
                    <div class="info">
                        <h4>Saldo Kas Admin</h4>
                        <p style="font-weight: bold; margin-top:5px; font-size: 16px; color:#2c3e50;">Rp <?php echo number_format($saldo_admin, 0, ',', '.'); ?></p>
                    </div>
                </a>
            </div>

            <!-- KOTAK GABUNGAN: RASIO & TRANSAKSI SETORAN TERBARU -->
            <div class="card-table">
                
                <!-- BAGIAN 1: RASIO SAMPAH TERKUMPUL -->
                <div class="card-table-header">
                    <h3><i class="fa fa-chart-pie"></i> Rasio Sampah Terkumpul</h3>
                    <div class="tools-group">
                        <form method="GET" action="" style="margin:0;">
                            <select name="filter" class="filter-select" onchange="this.form.submit()">
                                <option value="semua" <?php if($filter == 'semua') echo 'selected'; ?>>Semua Waktu</option>
                                <option value="bulan_ini" <?php if($filter == 'bulan_ini') echo 'selected'; ?>>Bulan Ini</option>
                                <option value="tahun_ini" <?php if($filter == 'tahun_ini') echo 'selected'; ?>>Tahun Ini</option>
                            </select>
                        </form>
                        <button onclick="showResetModal()" class="btn-reset" title="Hapus Semua Data Transaksi"><i class="fa fa-trash"></i> Reset Data</button>
                    </div>
                </div>
                <div class="table-responsive" style="margin-bottom: 30px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Kategori Sampah</th>
                                <th>Total Berat (Kg)</th>
                                <th>Rasio (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $q_rasio = mysqli_query($koneksi, "
                                SELECT k.nama_barang, SUM(ts.berat) as total_berat_per_jenis 
                                FROM transaksi_setoran ts
                                JOIN kategori_sampah k ON ts.id_kategori = k.id
                                WHERE (ts.status='selesai' OR ts.status='disetujui') $where_query
                                GROUP BY ts.id_kategori
                            ");
                            
                            if(mysqli_num_rows($q_rasio) > 0) {
                                while ($r = mysqli_fetch_assoc($q_rasio)) {
                                    $persen = round(($r['total_berat_per_jenis'] / $total_berat_semua) * 100, 1);
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($r['nama_barang']) . "</td>";
                                    echo "<td><b>" . $r['total_berat_per_jenis'] . " Kg</b></td>";
                                    echo "<td>{$persen}% <div class='progress-bar-bg'><div class='progress-bar-fill' style='width: {$persen}%;'></div></div></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='3' style='text-align:center; color:#95a5a6; padding:20px;'>Belum ada data setoran sampah pada rentang waktu yang dipilih.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <hr style="border: 0; border-top: 1px dashed #ccc; margin: 30px 0;">

                <!-- BAGIAN 2: TRANSAKSI SETORAN TERBARU -->
                <div class="card-table-header">
                    <h3><i class="fa fa-list"></i> Transaksi Setoran Terbaru</h3>
                    <button class="btn-export" style="background:#27ae60;" onclick="exportTableToExcel('tabelSetoran', 'Laporan_Setoran_Sampah')">
                        <i class="fa fa-file-excel"></i> Export ke Excel
                    </button>
                </div>
                <div class="table-responsive">
                    <table id="tabelSetoran">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Nama Nasabah</th>
                                <th>Jenis Sampah</th>
                                <th>Berat (Kg)</th>
                                <th>Total Rp</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $query_terbaru = "SELECT ts.*, u.nama_lengkap, k.nama_barang 
                                              FROM transaksi_setoran ts
                                              JOIN users u ON ts.username_nasabah = u.username
                                              JOIN kategori_sampah k ON ts.id_kategori = k.id
                                              ORDER BY ts.tanggal DESC LIMIT $halaman_awal, $batas";
                            $result_terbaru = mysqli_query($koneksi, $query_terbaru);
                            $nomor = $halaman_awal + 1;

                            if (mysqli_num_rows($result_terbaru) > 0) {
                                while ($row = mysqli_fetch_assoc($result_terbaru)) {
                                    echo "<tr>";
                                    echo "<td>" . $nomor++ . "</td>";
                                    echo "<td>" . date('d M Y', strtotime($row['tanggal'])) . "</td>";
                                    echo "<td><b>" . htmlspecialchars($row['nama_lengkap']) . "</b></td>";
                                    echo "<td>" . htmlspecialchars($row['nama_barang']) . "</td>";
                                    echo "<td>" . $row['berat'] . " Kg</td>";
                                    echo "<td style='color:#2e7d32; font-weight:bold;'>Rp " . number_format($row['total_harga'], 0, ',', '.') . "</td>";
                                    
                                    if ($row['status'] == 'selesai') {
                                        $badge_class = 'badge-selesai'; $status_text = 'Selesai';
                                    } elseif ($row['status'] == 'disetujui') {
                                        $badge_class = 'badge-disetujui'; $status_text = 'Disetujui';
                                    } elseif ($row['status'] == 'ditolak') {
                                        $badge_class = 'badge-ditolak'; $status_text = 'Ditolak';
                                    } else {
                                        $badge_class = 'badge-pending'; $status_text = 'Minta Persetujuan';
                                    }

                                    echo "<td><span class='badge $badge_class'>" . $status_text . "</span></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' style='text-align:center; padding:30px; color:#95a5a6; font-style: italic;'><i class='fa fa-inbox fa-2x' style='margin-bottom:10px; color:#bdc3c7;'></i><br>Belum ada data transaksi setoran sampah saat ini.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($jumlah_data > 0): ?>
                <div class="pagination">
                    <?php if($halaman > 1): ?>
                        <a href="?halaman=<?php echo $halaman - 1; ?>"><i class="fa fa-chevron-left"></i> Prev</a>
                    <?php endif; ?>
                    <span class="info-halaman">Halaman <?php echo $halaman; ?> / <?php echo $total_halaman; ?></span>
                    <?php if($halaman < $total_halaman): ?>
                        <a href="?halaman=<?php echo $halaman + 1; ?>">Next <i class="fa fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>

            <!-- LAPORAN PENGELUARAN DANA KAS DENGAN SEARCH (EXCEL) -->
            <div class="card-table">
                <div class="card-table-header">
                    <h3><i class="fa fa-file-invoice-dollar"></i> Laporan Pengeluaran Dana Kas</h3>
                    <div class="tools-group">
                        <input type="text" id="searchKas" onkeyup="searchTable()" placeholder="Cari nama nasabah..." class="filter-select" style="width: 200px;">
                        <button class="btn-export" onclick="exportTableToExcel('tabelPengeluaran', 'Laporan_Pengeluaran_Bank_Sampah')">
                            <i class="fa fa-file-excel"></i> Export
                        </button>
                    </div>
                </div>
                <p style="font-size: 13px; color: #7f8c8d; margin-top: -10px; margin-bottom: 20px;">
                    Tabel ini mencatat seluruh riwayat pencairan nasabah yang berstatus "Selesai" (Uang keluar dari Kas Admin).
                </p>
                <div class="table-responsive">
                    <table id="tabelPengeluaran">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal & Waktu</th>
                                <th>Nama Nasabah</th>
                                <th>Metode Penarikan</th>
                                <th>Rincian Tujuan</th>
                                <th>Nominal Keluar</th>
                            </tr>
                        </thead>
                        <tbody id="bodyTableKas">
                            <?php
                            $q_laporan = "SELECT t.*, u.nama_lengkap 
                                          FROM transaksi_tarik t 
                                          JOIN users u ON t.username_nasabah = u.username 
                                          WHERE t.status = 'selesai' ORDER BY t.tanggal DESC";
                            $r_laporan = mysqli_query($koneksi, $q_laporan);
                            $no_lap = 1;

                            if (mysqli_num_rows($r_laporan) > 0) {
                                while ($row_lap = mysqli_fetch_assoc($r_laporan)) {
                                    $tgl_lap = date('d M Y (H:i)', strtotime($row_lap['tanggal']));
                                    echo "<tr>";
                                    echo "<td>" . $no_lap++ . "</td>";
                                    echo "<td>" . $tgl_lap . "</td>";
                                    echo "<td class='nama-nasabah'><b>" . htmlspecialchars($row_lap['nama_lengkap']) . "</b></td>";
                                    echo "<td>" . htmlspecialchars($row_lap['metode']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row_lap['nomor_tujuan']) . "</td>";
                                    echo "<td style='color:#c0392b; font-weight:bold;'>Rp " . number_format($row_lap['nominal'], 0, ',', '.') . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' style='text-align:center; padding:20px; color:#7f8c8d;'>Belum ada riwayat pengeluaran kas.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- MODAL RESET DATA -->
    <div id="resetModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header-danger">
                <i class="fa fa-exclamation-triangle"></i>
            </div>
            <div class="modal-body">
                <h3 style="color:#c0392b;">Peringatan Reset!</h3>
                <p>Tindakan ini akan <b>MENGHAPUS SEMUA</b> riwayat setoran & penarikan. <br><br><i style="color:#e74c3c; font-size:12px;">Catatan: Saldo Nasabah dan Admin <b>TIDAK</b> akan terhapus.</i></p>
                <form method="POST" action="">
                    <div class="modal-buttons">
                        <button type="button" class="btn-cancel" onclick="closeResetModal()">Batal</button>
                        <button type="submit" name="reset_data" class="btn-confirm">Ya, Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL NOTIFIKASI -->
    <?php if (!empty($notif_sukses) || !empty($notif_gagal)) : ?>
    <div id="notifModal" class="modal-overlay" style="display: flex;">
        <div class="modal-box">
            <div class="<?php echo !empty($notif_sukses) ? 'modal-header-success' : 'modal-header-danger'; ?>">
                <i class="<?php echo !empty($notif_sukses) ? 'fa fa-check-circle' : 'fa fa-times-circle'; ?>"></i>
            </div>
            <div class="modal-body">
                <h3 style="color:<?php echo !empty($notif_sukses) ? '#27ae60' : '#c0392b'; ?>;"><?php echo !empty($notif_sukses) ? 'Sukses!' : 'Gagal!'; ?></h3>
                <p><?php echo !empty($notif_sukses) ? $notif_sukses : $notif_gagal; ?></p>
                <button class="<?php echo !empty($notif_sukses) ? 'btn-confirm-green' : 'btn-confirm'; ?>" style="width: 100%;" onclick="tutupNotif()">Tutup</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- MODAL LOGOUT -->
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
                    <button class="btn-confirm" onclick="prosesLogout()">Ya, Keluar</button>
                </div>
            </div>
        </div>
    </div>

<script>
    // --- FITUR PENCARIAN NAMA NASABAH ---
    function searchTable() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("searchKas");
        filter = input.value.toUpperCase();
        table = document.getElementById("tabelPengeluaran");
        tr = table.getElementsByTagName("tr");

        for (i = 1; i < tr.length; i++) {
            td = tr[i].getElementsByClassName("nama-nasabah")[0];
            if (td) {
                txtValue = td.textContent || td.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    }

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

    function showResetModal() { document.getElementById('resetModal').style.display = 'flex'; }
    function closeResetModal() { document.getElementById('resetModal').style.display = 'none'; }
    function showLogoutModal() { document.getElementById('customModal').style.display = 'flex'; }
    function closeLogoutModal() { document.getElementById('customModal').style.display = 'none'; }
    function prosesLogout() { window.location.href = 'logout.php'; }
    function tutupNotif() { document.getElementById('notifModal').style.display = 'none'; window.location.href = 'dashboard_admin.php'; }

    function exportTableToExcel(tableID, filename = ''){
        var downloadLink;
        var dataType = 'application/vnd.ms-excel';
        var tableSelect = document.getElementById(tableID);
        var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
        var date = new Date();
        var tglString = date.getFullYear() + "-" + (date.getMonth()+1) + "-" + date.getDate();
        filename = filename ? filename + '_' + tglString + '.xls' : 'Laporan_Kas.xls';
        
        downloadLink = document.createElement("a");
        document.body.appendChild(downloadLink);
        
        if(navigator.msSaveOrOpenBlob){
            var blob = new Blob(['\ufeff', tableHTML], { type: dataType });
            navigator.msSaveOrOpenBlob( blob, filename);
        }else{
            downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
            downloadLink.download = filename;
            downloadLink.click();
        }
    }
</script>
</body>
</html>