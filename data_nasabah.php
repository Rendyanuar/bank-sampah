<?php
session_start();

// Mencegah browser menyimpan cache halaman ini
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

// PASTIKAN TABEL RIWAYAT KAS ADA AGAR TIDAK ERROR
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS riwayat_kas_admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    jenis_transaksi VARCHAR(50), 
    nominal INT(11),
    keterangan TEXT
)");

$hapus_sukses = false;
$tarik_sukses = false;
$tambah_sukses = false;
$pesan_error = "";
$nama_transaksi = "";
$nominal_transaksi = 0;

// ==================================================================
// FITUR HAPUS DATA NASABAH
// ==================================================================
if (isset($_GET['hapus'])) {
    $username_hapus = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    mysqli_query($koneksi, "DELETE FROM transaksi_setoran WHERE username_nasabah = '$username_hapus'");
    mysqli_query($koneksi, "DELETE FROM transaksi_tarik WHERE username_nasabah = '$username_hapus'");
    
    if (mysqli_query($koneksi, "DELETE FROM users WHERE username = '$username_hapus' AND role = 'nasabah'")) {
        $hapus_sukses = true;
    } else {
        $pesan_error = "Gagal menghapus data nasabah.";
    }
}

// ==================================================================
// FITUR TARIK SALDO MANUAL (OTOMATIS POTONG KAS ADMIN)
// ==================================================================
if (isset($_POST['tarik_saldo_admin'])) {
    $uname_nasabah = mysqli_real_escape_string($koneksi, $_POST['uname_nasabah']);
    $nominal = (int)$_POST['nominal_tarik'];

    $cek_s = mysqli_query($koneksi, "SELECT saldo, nama_lengkap FROM users WHERE username = '$uname_nasabah'");
    $data_s = mysqli_fetch_assoc($cek_s);
    $saldo_sekarang = (int)$data_s['saldo'];

    // CEK SALDO KAS ADMIN SAAT INI
    $cek_admin = mysqli_query($koneksi, "SELECT saldo FROM users WHERE role = 'admin' LIMIT 1");
    $saldo_admin_sekarang = (int)mysqli_fetch_assoc($cek_admin)['saldo'];

    if ($nominal > $saldo_sekarang) {
        $pesan_error = "Gagal! Nominal penarikan (Rp " . number_format($nominal, 0, ',', '.') . ") melebihi sisa saldo nasabah (Rp " . number_format($saldo_sekarang, 0, ',', '.') . ").";
    } elseif ($nominal > $saldo_admin_sekarang) {
        $pesan_error = "Gagal! Saldo Kas Admin tidak cukup untuk pencairan ini (Sisa Kas: Rp " . number_format($saldo_admin_sekarang, 0, ',', '.') . "). Silakan Top Up Kas Admin.";
    } elseif ($nominal < 1000) {
        $pesan_error = "Gagal! Minimal penarikan adalah Rp 1.000.";
    } else {
        $q_tarik = "INSERT INTO transaksi_tarik (username_nasabah, nominal, metode, nomor_tujuan, status) 
                    VALUES ('$uname_nasabah', '$nominal', 'Tunai', 'Penarikan di Kantor', 'selesai')";
        
        if (mysqli_query($koneksi, $q_tarik)) {
            // 1. Potong Saldo Nasabah
            mysqli_query($koneksi, "UPDATE users SET saldo = saldo - $nominal WHERE username = '$uname_nasabah'");
            // 2. Potong Saldo Kas Admin
            mysqli_query($koneksi, "UPDATE users SET saldo = saldo - $nominal WHERE role = 'admin'");
            // 3. Catat di Riwayat Kas Admin
            $ket_kas = "Pencairan Tunai Nasabah: " . $data_s['nama_lengkap'];
            mysqli_query($koneksi, "INSERT INTO riwayat_kas_admin (jenis_transaksi, nominal, keterangan) VALUES ('Keluar', '$nominal', '$ket_kas')");

            $tarik_sukses = true;
            $nama_transaksi = $data_s['nama_lengkap'];
            $nominal_transaksi = $nominal;
        } else {
            $pesan_error = "Gagal memproses transaksi.";
        }
    }
}

// ==================================================================
// FITUR TAMBAH SALDO MANUAL
// ==================================================================
if (isset($_POST['tambah_saldo_admin'])) {
    $uname_nasabah = mysqli_real_escape_string($koneksi, $_POST['uname_nasabah_tambah']);
    $nominal = (int)$_POST['nominal_tambah'];

    if ($nominal < 1000) {
        $pesan_error = "Minimal penambahan saldo adalah Rp 1.000.";
    } else {
        if (mysqli_query($koneksi, "UPDATE users SET saldo = saldo + $nominal WHERE username = '$uname_nasabah'")) {
            $cek_s = mysqli_query($koneksi, "SELECT nama_lengkap FROM users WHERE username = '$uname_nasabah'");
            $data_s = mysqli_fetch_assoc($cek_s);
            $tambah_sukses = true;
            $nama_transaksi = $data_s['nama_lengkap'];
            $nominal_transaksi = $nominal;
        } else {
            $pesan_error = "Gagal menambahkan saldo.";
        }
    }
}

// =========================================================
// AMBIL DATA NOTIFIKASI & PROFIL
// =========================================================
$q_notif_setoran = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM transaksi_setoran WHERE status = 'pending'");
$notif_setoran = mysqli_fetch_assoc($q_notif_setoran)['total'];

$q_notif_tarik = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM transaksi_tarik WHERE status = 'pending'");
$notif_tarik = mysqli_fetch_assoc($q_notif_tarik)['total'];

$q_foto = mysqli_query($koneksi, "SELECT foto_profil FROM users WHERE username = '".$_SESSION['username']."'");
$d_foto = mysqli_fetch_assoc($q_foto);
$path_foto_header = (!empty($d_foto['foto_profil']) && file_exists('assets/profil/' . $d_foto['foto_profil'])) 
                    ? 'assets/profil/' . $d_foto['foto_profil'] : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['nama']) . '&background=1abc9c&color=fff';

// ==================================================================
// KONFIGURASI PAGINASI (HALAMAN)
// ==================================================================
$data_per_halaman = 10; // Jumlah data yang ditampilkan per halaman
$halaman_aktif = (isset($_GET['halaman']) && is_numeric($_GET['halaman'])) ? (int)$_GET['halaman'] : 1;
$awal_data = ($halaman_aktif - 1) * $data_per_halaman;

// Hitung total data nasabah keseluruhan
$query_total = "SELECT COUNT(*) AS total_data FROM users WHERE role = 'nasabah'";
$hasil_total = mysqli_query($koneksi, $query_total);
$row_total = mysqli_fetch_assoc($hasil_total);
$total_data = $row_total['total_data'];

// Hitung jumlah halaman yang dibutuhkan
$jumlah_halaman = ceil($total_data / $data_per_halaman);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Nasabah - Bank Sampah Induk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar Styling SAMA PERSIS DENGAN DASHBOARD */
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
        
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative;}
        .header { background-color: #1abc9c; padding: 0 30px; height: 60px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 10; box-sizing: border-box;}
        .header h3 { margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 18px;}
        .mobile-menu-btn { display: none; font-size: 20px; color: white; cursor: pointer; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; }
        
        .header-right { display: flex; align-items: center; }
        .profile-dropdown { position: relative; display: inline-block; }
        .profile-dropdown-toggle { display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 20px; transition: 0.3s; cursor: pointer; user-select: none; }
        .profile-dropdown-toggle:hover, .profile-dropdown-toggle:active { background-color: rgba(255,255,255,0.1); }
        .profile-dropdown-toggle img { width: 32px; height: 32px; border-radius: 50%; background: #ddd; border: 2px solid white; object-fit: cover; }
        .user-info { font-size: 14px; color: white; display: flex; align-items: center; gap: 10px;}
        .user-info span { color: rgba(255,255,255,0.9); }
        .profile-dropdown-menu { display: none; position: absolute; right: 0; top: 120%; background-color: white; min-width: 200px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); border-radius: 10px; overflow: hidden; z-index: 100; border: 1px solid #eee; }
        .profile-dropdown-menu.show { display: block; animation: fadeIn 0.2s ease-in-out; }
        .profile-dropdown-menu a { color: #555; padding: 12px 20px; text-decoration: none; display: flex; align-items: center; font-size: 14px; transition: 0.2s; cursor: pointer; justify-content: flex-start; border: none; }
        .profile-dropdown-menu a:hover { background-color: #f1fcf9; color: #1abc9c; }
        .profile-dropdown-menu a i { margin-right: 12px; font-size: 16px; width: 20px; text-align: center; }
        .dropdown-divider { height: 1px; background-color: #eee; margin: 0; }

        .content { padding: 30px; flex: 1; overflow-y: auto; overflow-x: hidden; width: 100%; box-sizing: border-box; }
        .card { background-color: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; box-sizing: border-box; overflow: hidden;}
        
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; flex-wrap: wrap; gap: 15px;}
        .card-header h2 { margin: 0; color: #1abc9c; font-size: 22px;}
        
        /* SEARCH BOX STYLING */
        .search-box { 
            padding: 10px 15px; 
            border: 1px solid #ddd; 
            border-radius: 6px; 
            font-size: 13px; 
            width: 100%; 
            max-width: 300px; 
            box-sizing: border-box; 
            outline: none; 
            transition: 0.3s; 
        }
        .search-box:focus { border-color: #1abc9c; box-shadow: 0 0 5px rgba(26,188,156,0.2); }

        .alert-error { background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border: 1px solid #f5c6cb; }

        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; display: block; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; min-width: 850px; white-space: nowrap;}
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        table th { background-color: #f8f9fa; color: #333; font-weight: 600; text-transform: uppercase; font-size: 13px;}
        table tr:hover { background-color: #f1fcf9; transition: 0.2s;}
        
        .btn-action { padding: 8px 12px; border: none; border-radius: 6px; color: white; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; margin-right: 5px; transition: 0.2s;}
        .btn-edit { background-color: #9b59b6; }
        .btn-tambah { background-color: #3498db; }
        .btn-tarik { background-color: #27ae60; }
        .btn-delete { background-color: #e74c3c; }
        .btn-edit:hover, .btn-tambah:hover, .btn-tarik:hover, .btn-delete:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.2);}

        /* STYLING PAGINASI */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .pagination-info {
            font-size: 13px;
            color: #7f8c8d;
        }
        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            gap: 5px;
        }
        .pagination a {
            color: #34495e;
            padding: 8px 14px;
            text-decoration: none;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
            background-color: white;
        }
        .pagination a.active {
            background-color: #1abc9c;
            color: white;
            border-color: #1abc9c;
        }
        .pagination a:hover:not(.active) {
            background-color: #f1fcf9;
            color: #1abc9c;
            border-color: #1abc9c;
        }
        .pagination a.disabled {
            color: #ccc;
            pointer-events: none;
            background-color: #f9f9f9;
        }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 400px; border-radius: 16px; overflow: hidden; text-align: center; animation: popIn 0.3s ease-out; }
        .modal-box-small { width: 320px !important;}
        .modal-header-green { background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%); padding: 20px; color: white; }
        .modal-header-blue { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 20px; color: white; }
        .modal-header-red { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); padding: 20px; color: white; }
        .modal-header-orange { background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); padding: 20px; color: white; }

        .modal-body { padding: 25px 30px 30px; background: #fff; }
        .form-group-modal { text-align: left; margin-bottom: 15px; }
        .form-group-modal label { display: block; font-size: 13px; color: #555; margin-bottom: 5px; font-weight: bold; }
        .form-group-modal input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; box-sizing: border-box; background: #f9fbfb;}
        .info-saldo-modal { background: #f1fcf9; border: 1px dashed #1abc9c; padding: 10px; border-radius: 8px; margin-bottom: 20px; font-size: 16px; font-weight: bold; color: #16a085;}
        
        .modal-buttons { display: flex; gap: 10px; justify-content: center; width: 100%; margin-top:20px;}
        .modal-buttons button { padding: 12px 20px; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer; flex: 1; transition: 0.2s;}
        .btn-cancel { background: #e0e0e0; color: #555; }
        .btn-cancel:hover { background: #d5d5d5; }
        .btn-confirm-red { background: #e74c3c; color: white; }
        .btn-confirm-green { background: #1abc9c; color: white; }
        .btn-confirm-blue { background: #3498db; color: white; }

        @keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

        @media screen and (max-width: 768px) {
            .mobile-menu-btn { display: block; }
            .header { padding: 0 20px; }
            .user-info span { color: rgba(255,255,255,0.8) !important; }
            .user-info b { color: white !important; }
            .profile-dropdown-toggle i { color: white !important; }
            .sidebar { position: fixed; top: 0; left: -260px; width: 260px; height: 100vh; transition: 0.3s; box-shadow: 5px 0 15px rgba(0,0,0,0.1);}
            .sidebar.active-mobile { left: 0; }
            .sidebar h2 { font-size: 18px; justify-content: flex-start; padding-left: 20px;}
            .sidebar-logo-text { display: inline; margin-left: 10px;}
            .menu { padding-top: 15px; }
            .menu a { flex-direction: row; justify-content: flex-start; padding: 15px 25px; border-left: none;}
            .menu a i { margin-right: 15px; font-size: 20px;}
            .menu a span.menu-text { font-size: 15px; font-weight: normal;}
            .content { padding: 15px; }
            .card { padding: 20px; width: 100%; max-width: calc(100vw - 30px); }
            .search-box { max-width: 100%; } /* Lebar full di HP */
            .table-responsive { overflow-x: auto !important; margin-top: 10px; border: 1px solid #f1f1f1; }
            .modal-box { width: 92%; max-width: 380px; }
            .pagination-container { flex-direction: column; align-items: center; }
            .pagination { flex-wrap: wrap; justify-content: center;}
        }
    </style>
</head>
<body>

    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleMobileMenu()"></div>

    <div class="sidebar" id="sidebarMenu">
        <h2 title="Bank Sampah"><i class="fa fa-recycle"></i><span class="sidebar-logo-text">BANK SAMPAH</span></h2>
        <div class="menu">
            <a href="dashboard_admin.php"><i class="fa fa-home"></i><span class="menu-text">Beranda</span></a>
            <a href="data_nasabah.php" class="active"><i class="fa fa-users"></i><span class="menu-text">Nasabah</span></a>
            <a href="kategori_sampah.php"><i class="fa fa-trash"></i><span class="menu-text">Kategori</span></a>
            <a href="transaksi_setoran.php"><i class="fa fa-exchange-alt"></i><span class="menu-text">Setoran</span>
                <?php if($notif_setoran > 0) echo "<span class='notif-badge'>$notif_setoran</span>"; ?>
            </a>
            <a href="transaksi_tarik.php"><i class="fa fa-hand-holding-usd"></i><span class="menu-text">Pencairan</span>
                <?php if($notif_tarik > 0) echo "<span class='notif-badge'>$notif_tarik</span>"; ?>
            </a>
            <a style="cursor: pointer;" onclick="showLogoutModal()"><i class="fa fa-sign-out-alt"></i><span class="menu-text">Keluar</span></a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h3><i class="fa fa-bars mobile-menu-btn" onclick="toggleMobileMenu()"></i> Kelola Data Nasabah</h3>
            <div class="header-right">
                <div class="profile-dropdown">
                    <div class="profile-dropdown-toggle" onclick="toggleDropdown()">
                        <div class="user-info" style="text-align: right; margin-right: 5px;">
                            <span style="font-size: 12px; display: block; line-height: 1;">Administrator</span>
                            <b style="font-size: 14px;"><?php echo isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'Admin'; ?></b>
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
            <div class="card">
                <div class="card-header">
                    <h2>Daftar Nasabah Terdaftar</h2>
                    <input type="text" id="searchNasabah" class="search-box" onkeyup="searchTable()" placeholder="Cari Nama atau No. Anggota...">
                </div>

                <?php if(!empty($pesan_error)): ?>
                    <div class="alert-error"><i class="fa fa-exclamation-triangle"></i> <?php echo $pesan_error; ?></div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table id="tabelNasabah">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nomor Anggota</th>
                                <th>Nama Lengkap</th>
                                <th>No. Telepon</th>
                                <th>Saldo Tersedia</th>
                                <th style="text-align:center;">Aksi Pengelolaan</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyNasabah">
                            <?php
                            // DIURUTKAN BERDASARKAN NOMOR ANGGOTA TERAWAL (ASCENDING) BESERTA LIMIT HALAMAN
                            $query = "SELECT * FROM users WHERE role = 'nasabah' ORDER BY username ASC LIMIT $awal_data, $data_per_halaman";
                            $result = mysqli_query($koneksi, $query);
                            $no = $awal_data + 1; // Penomoran urut sesuai halaman

                            if (mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    $saldo_tampil = $row['saldo'] ? $row['saldo'] : 0;
                                    $uname_js = htmlspecialchars($row['username'], ENT_QUOTES);
                                    $nama_js = htmlspecialchars($row['nama_lengkap'], ENT_QUOTES);
                                    
                                    echo "<tr class='row-nasabah'>";
                                    echo "<td>" . $no++ . "</td>";
                                    echo "<td class='kolom-nomor'><b>" . htmlspecialchars($row['username']) . "</b></td>";
                                    echo "<td class='kolom-nama'>" . htmlspecialchars($row['nama_lengkap']) . "</td>";
                                    echo "<td>" . (!empty($row['nomor_telepon']) ? htmlspecialchars($row['nomor_telepon']) : "-") . "</td>";
                                    echo "<td style='color:#27ae60; font-weight:bold;'>Rp " . number_format($saldo_tampil, 0, ',', '.') . "</td>";
                                    echo "<td style='text-align:center;'>
                                            <a href='edit_nasabah.php?username=" . urlencode($row['username']) . "' class='btn-action btn-edit' title='Edit Profil'><i class='fa fa-user-edit'></i></a>
                                            <a href='javascript:void(0);' onclick='showTambahModal(\"{$uname_js}\", \"{$nama_js}\")' class='btn-action btn-tambah' title='Tambah Saldo Manual'><i class='fa fa-plus-circle'></i></a>
                                            <a href='javascript:void(0);' onclick='showTarikModal(\"{$uname_js}\", \"{$nama_js}\", {$saldo_tampil})' class='btn-action btn-tarik' title='Tarik Saldo Manual'><i class='fa fa-money-bill-wave'></i></a>
                                            <a href='javascript:void(0);' onclick='showDeleteModal(\"{$uname_js}\")' class='btn-action btn-delete' title='Hapus Nasabah'><i class='fa fa-trash'></i></a>
                                          </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr id='rowKosong'><td colspan='6' style='text-align:center; padding:20px; color:#7f8c8d;'>Belum ada data nasabah.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <?php if($total_data > 0): ?>
                <div class="pagination-container" id="wadahPaginasi">
                    <div class="pagination-info">
                        Menampilkan <?php echo ($awal_data + 1); ?> - <?php echo min(($awal_data + $data_per_halaman), $total_data); ?> dari <b><?php echo $total_data; ?></b> nasabah
                    </div>
                    <ul class="pagination">
                        <?php if($halaman_aktif > 1): ?>
                            <li><a href="?halaman=<?php echo $halaman_aktif - 1; ?>">&laquo; Seb</a></li>
                        <?php else: ?>
                            <li><a class="disabled">&laquo; Seb</a></li>
                        <?php endif; ?>

                        <?php 
                        for($i = 1; $i <= $jumlah_halaman; $i++): 
                            if ($i == $halaman_aktif):
                        ?>
                            <li><a href="?halaman=<?php echo $i; ?>" class="active"><?php echo $i; ?></a></li>
                        <?php else: ?>
                            <li><a href="?halaman=<?php echo $i; ?>"><?php echo $i; ?></a></li>
                        <?php 
                            endif;
                        endfor; 
                        ?>

                        <?php if($halaman_aktif < $jumlah_halaman): ?>
                            <li><a href="?halaman=<?php echo $halaman_aktif + 1; ?>">Lanjut &raquo;</a></li>
                        <?php else: ?>
                            <li><a class="disabled">Lanjut &raquo;</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>

    <div id="warningModal" class="modal-overlay" style="z-index: 10001;">
        <div class="modal-box modal-box-small">
            <div class="modal-header-orange"><i class="fa fa-exclamation-circle" style="font-size: 50px;"></i></div>
            <div class="modal-body">
                <h3 id="warningTitle" style="text-align:center;">Saldo Kosong!</h3>
                <p id="warningText" style="text-align:center;">Nasabah tidak memiliki saldo.</p>
                <button class="btn-confirm-red" style="background:#e67e22; width:100%; border:none; padding:12px; border-radius:25px; color:white; font-weight:bold;" onclick="document.getElementById('warningModal').style.display='none'">Mengerti</button>
            </div>
        </div>
    </div>

    <div id="tambahModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header-blue">
                <i class="fa fa-hand-holding-usd" style="font-size: 40px; margin-bottom: 5px;"></i>
                <h3 style="margin: 0; font-size: 20px;">Tambah Saldo</h3>
            </div>
            <div class="modal-body">
                <div class="info-saldo-modal" style="border-color:#3498db; background:#f0f8ff; color:#2980b9; text-align:center;">
                    <span style="font-size: 12px; color: #7f8c8d; font-weight: normal; display: block;">Nasabah:</span>
                    <span id="display_nama_tambah" style="font-size: 18px;"></span>
                </div>
                <form action="data_nasabah.php" method="POST">
                    <input type="hidden" name="uname_nasabah_tambah" id="input_uname_tambah">
                    <div class="form-group-modal">
                        <label>Nominal Ditambahkan (Rp)</label>
                        <input type="number" name="nominal_tambah" required min="1000" placeholder="Minimal Rp 1.000">
                    </div>
                    <div class="modal-buttons">
                        <button type="button" class="btn-cancel" onclick="closeTambahModal()">Batal</button>
                        <button type="submit" name="tambah_saldo_admin" class="btn-confirm-blue" style="background:#3498db;">Tambahkan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="tarikModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header-green">
                <i class="fa fa-wallet" style="font-size: 40px; margin-bottom: 5px;"></i>
                <h3 style="margin: 0; font-size: 20px;">Tarik Tunai</h3>
            </div>
            <div class="modal-body">
                <div class="info-saldo-modal" style="text-align:center;">
                    <span style="font-size: 12px; color: #7f8c8d; font-weight: normal; display: block;">Saldo Nasabah:</span>
                    <span id="display_nama_nasabah"></span><br>
                    <span id="display_saldo_max" style="font-size: 22px;"></span>
                </div>
                
                <form action="data_nasabah.php" method="POST" onsubmit="return validasiTarik(event)" novalidate>
                    <input type="hidden" name="uname_nasabah" id="input_uname_nasabah">
                    <div class="form-group-modal">
                        <label>Nominal yang Ditarik (Rp)</label>
                        <input type="number" name="nominal_tarik" id="input_nominal_tarik" required min="1000" placeholder="Misal: 50000">
                    </div>
                    <div class="modal-buttons">
                        <button type="button" class="btn-cancel" onclick="closeTarikModal()">Batal</button>
                        <button type="submit" name="tarik_saldo_admin" class="btn-confirm-green" style="background:#27ae60;">Proses Tarik</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="logoutModal" class="modal-overlay">
        <div class="modal-box modal-box-small">
            <div class="modal-header-red" style="padding:25px 20px;"><img src="assets/logo-lebak.png" alt="Logo"></div>
            <div class="modal-body" style="text-align:center;">
                <h3>Konfirmasi Logout</h3>
                <p>Apakah Anda yakin ingin keluar?</p>
                <div class="modal-buttons">
                    <button class="btn-cancel" onclick="document.getElementById('logoutModal').style.display='none'">Batal</button>
                    <button class="btn-confirm-red" style="background:#e74c3c;" onclick="window.location.href='logout.php'">Keluar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="deleteModal" class="modal-overlay">
        <div class="modal-box modal-box-small">
            <div class="modal-header-red"><i class="fa fa-exclamation-triangle" style="font-size:40px;"></i></div>
            <div class="modal-box-small">
                <div class="modal-body" style="text-align:center;">
                    <h3>Hapus Data?</h3>
                    <p>Apakah Anda yakin ingin menghapus data nasabah ini?</p>
                    <div class="modal-buttons">
                        <button class="btn-cancel" onclick="closeDeleteModal()">Batal</button>
                        <button class="btn-confirm-red" style="background:#e74c3c;" onclick="executeDelete()">Hapus</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($hapus_sukses || $tarik_sukses || $tambah_sukses) : ?>
    <div id="successModal" class="modal-overlay" style="display: flex;">
        <div class="modal-box modal-box-small">
            <div class="modal-header-green" style="<?php if($tambah_sukses) echo 'background: linear-gradient(135deg, #3498db, #2980b9);'; ?> padding:25px 20px;">
                <i class="fa fa-check-circle" style="font-size:50px;"></i>
            </div>
            <div class="modal-body" style="text-align:center;">
                <h3>Berhasil!</h3>
                <p style="font-size:13px; line-height:1.4;">
                    <?php 
                    if ($hapus_sukses) echo "Data nasabah sukses dihapus.";
                    if ($tarik_sukses) echo "Berhasil menarik Rp " . number_format($nominal_transaksi,0,',','.') . " untuk " . htmlspecialchars($nama_transaksi) . ".<br><b style='color:#e74c3c;'>(Saldo Kas Admin Otomatis Terpotong)</b>";
                    if ($tambah_sukses) echo "Berhasil menambah Rp " . number_format($nominal_transaksi,0,',','.') . " ke tabungan " . htmlspecialchars($nama_transaksi) . ".";
                    ?>
                </p>
                <button class="btn-confirm-green" style="width:100%; <?php if($tambah_sukses) echo 'background:#3498db;'; ?>" onclick="closeSuccessModal()">Tutup</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <table style="display:none;" id="tabelDataPenuh">
        <?php
        // Kueri tersembunyi untuk meload SEMUA data agar bisa dicari tanpa reload
        $q_full = "SELECT * FROM users WHERE role = 'nasabah' ORDER BY username ASC";
        $r_full = mysqli_query($koneksi, $q_full);
        $no_full = 1;
        while ($rf = mysqli_fetch_assoc($r_full)) {
            $s_tampil = $rf['saldo'] ? $rf['saldo'] : 0;
            $u_js = htmlspecialchars($rf['username'], ENT_QUOTES);
            $n_js = htmlspecialchars($rf['nama_lengkap'], ENT_QUOTES);
            
            echo "<tr class='row-full'>";
            echo "<td>" . $no_full++ . "</td>";
            echo "<td class='kolom-nomor-full'><b>" . htmlspecialchars($rf['username']) . "</b></td>";
            echo "<td class='kolom-nama-full'>" . htmlspecialchars($rf['nama_lengkap']) . "</td>";
            echo "<td>" . (!empty($rf['nomor_telepon']) ? htmlspecialchars($rf['nomor_telepon']) : "-") . "</td>";
            echo "<td style='color:#27ae60; font-weight:bold;'>Rp " . number_format($s_tampil, 0, ',', '.') . "</td>";
            echo "<td style='text-align:center;'>
                    <a href='edit_nasabah.php?username=" . urlencode($rf['username']) . "' class='btn-action btn-edit' title='Edit Profil'><i class='fa fa-user-edit'></i></a>
                    <a href='javascript:void(0);' onclick='showTambahModal(\"{$u_js}\", \"{$n_js}\")' class='btn-action btn-tambah'><i class='fa fa-plus-circle'></i></a>
                    <a href='javascript:void(0);' onclick='showTarikModal(\"{$u_js}\", \"{$n_js}\", {$s_tampil})' class='btn-action btn-tarik'><i class='fa fa-money-bill-wave'></i></a>
                    <a href='javascript:void(0);' onclick='showDeleteModal(\"{$u_js}\")' class='btn-action btn-delete'><i class='fa fa-trash'></i></a>
                  </td>";
            echo "</tr>";
        }
        ?>
    </table>

<script>
    // FITUR LIVE SEARCH (DIADAPTASI UNTUK PAGINASI)
    function searchTable() {
        var input, filter, tbodyAsli, wadahPaginasi, tabelPenuh, barisPenuh, tdNo, tdNama, i, txtNo, txtNama, countMatch;
        input = document.getElementById("searchNasabah");
        filter = input.value.toUpperCase();
        
        tbodyAsli = document.getElementById("tbodyNasabah");
        wadahPaginasi = document.getElementById("wadahPaginasi");
        
        tabelPenuh = document.getElementById("tabelDataPenuh");
        barisPenuh = tabelPenuh.getElementsByClassName("row-full");

        // Jika kotak pencarian kosong, kembalikan ke mode paginasi (halaman aktif)
        if (filter.trim() === "") {
            window.location.reload(); 
            return;
        }

        // Jika ada ketikan, sembunyikan paginasi dan ganti isi tbody dengan seluruh data yang cocok
        if(wadahPaginasi) wadahPaginasi.style.display = "none";
        tbodyAsli.innerHTML = ""; 
        countMatch = 0;

        for (i = 0; i < barisPenuh.length; i++) {
            tdNo = barisPenuh[i].getElementsByClassName("kolom-nomor-full")[0];
            tdNama = barisPenuh[i].getElementsByClassName("kolom-nama-full")[0];
            
            if (tdNo || tdNama) {
                txtNo = tdNo.textContent || tdNo.innerText;
                txtNama = tdNama.textContent || tdNama.innerText;
                
                if (txtNo.toUpperCase().indexOf(filter) > -1 || txtNama.toUpperCase().indexOf(filter) > -1) {
                    // Klon baris dari tabel tersembunyi ke tabel utama yang dilihat user
                    tbodyAsli.appendChild(barisPenuh[i].cloneNode(true));
                    countMatch++;
                }
            }       
        }
        
        if (countMatch === 0) {
            tbodyAsli.innerHTML = "<tr><td colspan='6' style='text-align:center; padding:20px; color:#e74c3c; font-weight:bold;'>Data tidak ditemukan.</td></tr>";
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
    function showLogoutModal() { document.getElementById('logoutModal').style.display = 'flex'; }
    function closeLogoutModal() { document.getElementById('logoutModal').style.display = 'none'; }
    
    function showTambahModal(username, namaLengkap) {
        document.getElementById('input_uname_tambah').value = username;
        document.getElementById('display_nama_tambah').innerText = namaLengkap;
        document.getElementById('tambahModal').style.display = 'flex';
    }
    function closeTambahModal() { document.getElementById('tambahModal').style.display = 'none'; }
    
    function showTarikModal(username, namaLengkap, saldoMax) {
        if (saldoMax <= 0) {
            document.getElementById('warningTitle').innerText = "Saldo Kosong!";
            document.getElementById('warningText').innerHTML = "Nasabah <b>" + namaLengkap + "</b> tidak memiliki saldo (Rp 0).";
            document.getElementById('warningModal').style.display = 'flex';
            return;
        }
        document.getElementById('input_uname_nasabah').value = username;
        document.getElementById('display_nama_nasabah').innerText = namaLengkap;
        let rupiahFormat = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(saldoMax);
        document.getElementById('display_saldo_max').innerText = rupiahFormat;
        document.getElementById('input_nominal_tarik').max = saldoMax;
        document.getElementById('input_nominal_tarik').value = ""; 
        document.getElementById('tarikModal').style.display = 'flex';
    }
    function closeTarikModal() { document.getElementById('tarikModal').style.display = 'none'; }
    
    function validasiTarik(e) {
        let inputNominal = parseInt(document.getElementById('input_nominal_tarik').value);
        let maxSaldo = parseInt(document.getElementById('input_nominal_tarik').max);
        
        if (inputNominal > maxSaldo) {
            e.preventDefault(); 
            
            let rupiahMax = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(maxSaldo);
            let rupiahReq = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(inputNominal);
            
            document.getElementById('tarikModal').style.display = 'none';
            document.getElementById('warningTitle').innerText = "Saldo Tidak Cukup!";
            document.getElementById('warningText').innerHTML = "Gagal memproses <b>" + rupiahReq + "</b>.<br>Sisa saldo nasabah hanya <b>" + rupiahMax + "</b>.";
            document.getElementById('warningModal').style.display = 'flex';
            return false;
        }
        return true;
    }

    let deleteTargetUrl = "";
    function showDeleteModal(username) {
        deleteTargetUrl = 'data_nasabah.php?hapus=' + encodeURIComponent(username);
        document.getElementById('deleteModal').style.display = 'flex';
    }
    function closeDeleteModal() { document.getElementById('deleteModal').style.display = 'none'; }
    function executeDelete() { window.location.href = deleteTargetUrl; }
    function closeSuccessModal() {
        document.getElementById('successModal').style.display = 'none';
        window.history.pushState({}, document.title, window.location.pathname);
    }
</script>
</body>
</html>