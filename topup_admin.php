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

$pesan_sukses = "";
$pesan_error = "";

// ==================================================================
// AUTO CREATE TABLE JIKA BELUM ADA DI DATABASE (MENCEGAH ERROR)
// ==================================================================
$cek_tabel = mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS riwayat_kas_admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    jenis_transaksi VARCHAR(50), 
    nominal INT(11),
    keterangan TEXT
)");

// ==================================================================
// 1. PROSES JIKA TOMBOL "ISI SALDO KAS" DITEKAN
// ==================================================================
if (isset($_POST['topup_saldo'])) {
    $jumlah_tambah = mysqli_real_escape_string($koneksi, $_POST['jumlah_tambah']);

    if ($jumlah_tambah > 0) {
        $query = "UPDATE users SET saldo = COALESCE(saldo, 0) + $jumlah_tambah WHERE role = 'admin'";
        if (mysqli_query($koneksi, $query)) {
            // Catat ke Riwayat Kas Admin
            mysqli_query($koneksi, "INSERT INTO riwayat_kas_admin (jenis_transaksi, nominal, keterangan) VALUES ('Masuk', '$jumlah_tambah', 'Top Up / Penambahan Kas Admin')");
            $pesan_sukses = "Berhasil! Saldo kas ditambahkan sebesar Rp " . number_format($jumlah_tambah, 0, ',', '.') . ".";
        } else {
            $pesan_error = "Terjadi kesalahan pada database saat menambah kas.";
        }
    } else {
        $pesan_error = "Gagal! Jumlah yang dimasukkan tidak valid.";
    }
}

// ==================================================================
// 2. PROSES JIKA TOMBOL "KURANGI SALDO KAS" DITEKAN
// ==================================================================
if (isset($_POST['kurangi_saldo'])) {
    $jumlah_kurang = mysqli_real_escape_string($koneksi, $_POST['jumlah_kurang']);
    $keterangan_kurang = mysqli_real_escape_string($koneksi, $_POST['keterangan_kurang']); // Opsional untuk alasan

    if ($jumlah_kurang > 0) {
        $q_cek_sementara = mysqli_query($koneksi, "SELECT saldo FROM users WHERE role = 'admin' LIMIT 1");
        $d_sementara = mysqli_fetch_assoc($q_cek_sementara);
        $saldo_sementara = $d_sementara['saldo'] !== null ? $d_sementara['saldo'] : 0;

        if ($saldo_sementara >= $jumlah_kurang) {
            $query = "UPDATE users SET saldo = saldo - $jumlah_kurang WHERE role = 'admin'";
            if (mysqli_query($koneksi, $query)) {
                // Catat ke Riwayat Kas Admin
                $ket = !empty($keterangan_kurang) ? $keterangan_kurang : 'Penarikan Kas Admin';
                mysqli_query($koneksi, "INSERT INTO riwayat_kas_admin (jenis_transaksi, nominal, keterangan) VALUES ('Keluar', '$jumlah_kurang', '$ket')");
                $pesan_sukses = "Berhasil! Saldo kas dikurangi sebesar Rp " . number_format($jumlah_kurang, 0, ',', '.') . ".";
            } else {
                $pesan_error = "Terjadi kesalahan pada database saat mengurangi kas.";
            }
        } else {
            $pesan_error = "Gagal! Saldo kas tidak mencukupi untuk dikurangi.";
        }
    } else {
        $pesan_error = "Gagal! Jumlah yang dimasukkan tidak valid.";
    }
}

// ==================================================================
// 3. PROSES JIKA TOMBOL "RESET DATA" DITEKAN (FITUR BARU)
// ==================================================================
if (isset($_POST['reset_kas'])) {
    $hapus = mysqli_query($koneksi, "DELETE FROM riwayat_kas_admin");
    mysqli_query($koneksi, "ALTER TABLE riwayat_kas_admin AUTO_INCREMENT = 1"); // Reset ID kembali ke 1
    if ($hapus) {
        $pesan_sukses = "Berhasil! Seluruh data riwayat arus kas telah dihapus.";
    } else {
        $pesan_error = "Gagal menghapus data riwayat kas.";
    }
}

// =========================================================
// MENGHITUNG DATA PENDING UNTUK NOTIFIKASI SIDEBAR
// =========================================================
$q_notif_setoran = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM transaksi_setoran WHERE status = 'pending'");
$notif_setoran = mysqli_fetch_assoc($q_notif_setoran)['total'];

$q_notif_tarik = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM transaksi_tarik WHERE status = 'pending'");
$notif_tarik = mysqli_fetch_assoc($q_notif_tarik)['total'];

// ==================================================================
// AMBIL DATA SALDO ADMIN & FOTO PROFIL
// ==================================================================
$q_cek = mysqli_query($koneksi, "SELECT saldo, foto_profil FROM users WHERE role = 'admin' LIMIT 1");
$data_admin = mysqli_fetch_assoc($q_cek);
$saldo_saat_ini = $data_admin['saldo'] !== null ? $data_admin['saldo'] : 0;

$path_foto_header = (!empty($data_admin['foto_profil']) && file_exists('assets/profil/' . $data_admin['foto_profil'])) 
                    ? 'assets/profil/' . $data_admin['foto_profil'] 
                    : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['nama']) . '&background=1abc9c&color=fff';

// =========================================================
// LOGIKA PAGINATION (HALAMAN) UNTUK RIWAYAT KAS
// =========================================================
$batas = 10; // Menampilkan 10 data per halaman
$halaman = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
$halaman_awal = ($halaman > 1) ? ($halaman * $batas) - $batas : 0;

$q_total_riwayat = mysqli_query($koneksi, "SELECT id FROM riwayat_kas_admin");
$jumlah_data_riwayat = mysqli_num_rows($q_total_riwayat);
$total_halaman = ceil($jumlah_data_riwayat / $batas);
if ($total_halaman == 0) $total_halaman = 1; // Mencegah 1/0
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kelola Kas - Bank Sampah Induk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 85px; background-color: #2c3e50; color: white; display: flex; flex-direction: column; z-index: 1001;}
        .sidebar h2 { margin: 0; background-color: #1abc9c; font-size: 24px; cursor: default; white-space: nowrap; height: 60px; display: flex; align-items: center; justify-content: center;}
        .sidebar-logo-text { display: none; }
        .menu { flex: 1; padding-top: 10px; }
        .menu a { display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 12px 5px; color: #bdc3c7; text-decoration: none; transition: 0.3s; position: relative;}
        .menu a:hover, .menu a.active { background-color: #34495e; color: white; border-left: 4px solid #1abc9c; }
        .menu a i { font-size: 22px; margin-bottom: 4px; }
        .menu a span.menu-text { font-size: 10px; text-align: center; font-weight: 600;}
        .notif-badge { position: absolute; top: 5px; right: 15px; background-color: #e74c3c; color: white; font-size: 10px; font-weight: bold; min-width: 15px; height: 15px; border-radius: 15px; display: flex; justify-content: center; align-items: center; padding: 0 3px; border: 2px solid #2c3e50;}

        .main-content { flex: 1; display: flex; flex-direction: column; position: relative;}
        .header { background-color: #1abc9c; padding: 0 30px; height: 60px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 10;}
        .header h3 { margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 18px;}
        .mobile-menu-btn { display: none; font-size: 20px; color: white; cursor: pointer; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; }
        
        .header-right { display: flex; align-items: center; }
        .profile-dropdown { position: relative; display: inline-block; }
        .profile-dropdown-toggle { display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 20px; transition: 0.3s; cursor: pointer; user-select: none; }
        .profile-dropdown-toggle:hover { background-color: rgba(255,255,255,0.1); }
        .profile-dropdown-toggle img { width: 32px; height: 32px; border-radius: 50%; background: #ddd; border: 2px solid white; object-fit: cover; }
        .user-info { font-size: 14px; color: white; display: flex; align-items: center; gap: 10px;}
        .user-info span { color: rgba(255,255,255,0.9); }
        .profile-dropdown-menu { display: none; position: absolute; right: 0; top: 120%; background-color: white; min-width: 200px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); border-radius: 10px; overflow: hidden; z-index: 100; border: 1px solid #eee; }
        .profile-dropdown-menu.show { display: block; animation: fadeIn 0.2s ease-in-out; }
        .profile-dropdown-menu a { color: #555; padding: 12px 20px; text-decoration: none; display: flex; align-items: center; font-size: 14px; transition: 0.2s; cursor: pointer; border:none; }
        .profile-dropdown-menu a:hover { background-color: #f1fcf9; color: #1abc9c; }
        .profile-dropdown-menu a i { margin-right: 12px; width: 20px; text-align: center; }

        .content { padding: 30px; flex: 1; overflow-y: auto; overflow-x: hidden; width: 100%; box-sizing: border-box; }
        
        .kas-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-bottom: 30px; }
        .card { background-color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); box-sizing: border-box; }
        
        .saldo-box { background: linear-gradient(135deg, #2c3e50, #34495e); color: white; padding: 25px 20px; border-radius: 12px; text-align: center; height: 100%; box-sizing: border-box; display: flex; flex-direction: column; justify-content: center;}
        .saldo-box i { font-size: 40px; color: #1abc9c; margin-bottom: 15px;}
        .saldo-box h3 { margin: 0; font-size: 15px; font-weight: normal; color: #bdc3c7; }
        .saldo-box h2 { margin: 10px 0 0 0; font-size: 30px; color: white;}

        .kas-forms { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .kas-form-group h4 { margin: 0 0 12px 0; font-size: 15px; color: #333; }
        .kas-form-group input { width: 100%; padding: 12px 15px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-size: 14px; background: #f9fbfb;}
        .kas-form-group input:focus { border-color: #1abc9c; outline: none; background: white;}
        .btn-kas { width: 100%; padding: 12px; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;}
        .btn-kas-green { background: #2ecc71; }
        .btn-kas-green:hover { background: #27ae60; transform: translateY(-2px);}
        .btn-kas-red { background: #e74c3c; }
        .btn-kas-red:hover { background: #c0392b; transform: translateY(-2px);}

        /* PERBAIKAN STRUKTUR CSS TAMBAHAN (HAPUS & PAGINATION) */
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; flex-wrap: wrap; gap:10px;}
        .card-header h2 { margin: 0; color: #1abc9c; font-size: 20px;}
        .tools-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap;}
        
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px;}
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .btn-export { background-color: #27ae60; color: white; padding: 8px 15px; border-radius: 5px; border:none; cursor:pointer; font-size: 13px; font-weight: bold; display: flex; align-items: center; gap: 5px;}
        .btn-export:hover { background-color: #219150; }
        .btn-reset { background-color: #e74c3c; color: white; padding: 8px 15px; border-radius: 5px; border:none; cursor:pointer; font-size: 13px; font-weight: bold; display: flex; align-items: center; gap: 5px;}
        .btn-reset:hover { background-color: #c0392b; }

        .table-responsive { width: 100%; overflow-x: auto; display: block; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; min-width: 700px; white-space: nowrap;}
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        table th { background-color: #f8f9fa; color: #333; font-weight: 600; text-transform: uppercase; font-size: 13px; letter-spacing: 0.5px;}
        table tr:hover { background-color: #f1fcf9; transition: 0.2s;}
        
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; color: white;}
        .badge-masuk { background-color: #2ecc71; }
        .badge-keluar { background-color: #e74c3c; }

        /* CSS PAGINATION SAMA SEPERTI HALAMAN LAIN */
        .pagination { display: flex; justify-content: center; align-items: center; gap: 15px; margin-top: 20px; }
        .pagination a { padding: 6px 15px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 13px;}
        .pagination a:hover { background: #2980b9; }
        .pagination .info-halaman { font-weight: bold; color: #555; font-size: 13px; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 320px; border-radius: 16px; overflow: hidden; text-align: center; animation: popIn 0.3s ease-out; }
        .modal-header-red { background: #e74c3c; padding: 25px 20px; }
        .modal-header-red img { width: 80px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2)); }
        .modal-body { padding: 25px 30px 30px; background: #fff; }
        .modal-body h3 { margin: 0 0 10px; color: #333; font-size: 20px; font-weight: 800;}
        .modal-body p { color: #555; margin-bottom: 25px; font-size: 14px;}
        .modal-buttons { display: flex; gap: 10px; justify-content: center; width: 100%;}
        .modal-buttons button { padding: 10px 20px; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer; flex: 1; }
        .btn-cancel { background: #e0e0e0; color: #555; }
        .btn-confirm-red { background: #e74c3c; color: white; }

        @keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

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
            .menu a i { margin-right: 15px; font-size: 20px;}
            .menu a span.menu-text { font-size: 15px; font-weight: normal;}
            
            .content { padding: 15px; }
            .kas-grid { grid-template-columns: 1fr; gap: 15px; }
            .kas-forms { grid-template-columns: 1fr; gap: 15px;}
            .card { padding: 20px; width: 100%; max-width: calc(100vw - 30px); }
            .table-responsive { overflow-x: auto !important; margin-top: 10px; border: 1px solid #f1f1f1; }
            .modal-box { width: 90%; }
        }
    </style>
</head>
<body>

    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleMobileMenu()"></div>

    <div class="sidebar" id="sidebarMenu">
        <h2 title="Bank Sampah"><i class="fa fa-recycle"></i><span class="sidebar-logo-text">BANK SAMPAH</span></h2>
        <div class="menu">
            <a href="dashboard_admin.php">
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
            <a href="topup_admin.php" class="active">
                <i class="fa fa-vault"></i><span class="menu-text">Kas Admin</span>
            </a>
            <a style="cursor: pointer;" onclick="showLogoutModal()">
                <i class="fa fa-sign-out-alt"></i><span class="menu-text">Keluar</span>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h3><i class="fa fa-bars mobile-menu-btn" onclick="toggleMobileMenu()"></i> Kelola Kas Admin</h3>
            <div class="header-right">
                <div class="profile-dropdown">
                    <div class="profile-dropdown-toggle" onclick="toggleDropdown()">
                        <div class="user-info" style="text-align: right; margin-right: 5px;">
                            <span style="font-size: 12px; display: block; line-height: 1;">Administrator</span>
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
            <?php if(!empty($pesan_sukses)): ?>
                <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?php echo $pesan_sukses; ?></div>
            <?php endif; ?>
            <?php if(!empty($pesan_error)): ?>
                <div class="alert alert-error"><i class="fa fa-exclamation-triangle"></i> <?php echo $pesan_error; ?></div>
            <?php endif; ?>

            <div class="kas-grid">
                <div class="saldo-box">
                    <i class="fa fa-vault"></i>
                    <h3>Total Saldo Kas Beredar</h3>
                    <h2>Rp <?php echo number_format($saldo_saat_ini, 0, ',', '.'); ?></h2>
                </div>

                <div class="card" style="padding: 20px;">
                    <div class="kas-forms">
                        <div class="kas-form-group">
                            <h4><i class="fa fa-plus-circle" style="color: #2ecc71;"></i> Top Up Kas</h4>
                            <form method="POST">
                                <input type="number" name="jumlah_tambah" min="1" required placeholder="Contoh: 500000">
                                <button type="submit" name="topup_saldo" class="btn-kas btn-kas-green">Isi Saldo Kas</button>
                            </form>
                        </div>
                        
                        <div class="kas-form-group">
                            <h4><i class="fa fa-minus-circle" style="color: #e74c3c;"></i> Kurangi Kas</h4>
                            <form method="POST">
                                <input type="number" name="jumlah_kurang" min="1" required placeholder="Contoh: 100000">
                                <input type="text" name="keterangan_kurang" placeholder="Keterangan (Opsional)">
                                <button type="submit" name="kurangi_saldo" class="btn-kas btn-kas-red">Kurangi Saldo Kas</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fa fa-history"></i> Riwayat Arus Kas Admin</h2>
                    <div class="tools-group">
                        <button class="btn-reset" onclick="showResetModal()">
                            <i class="fa fa-trash"></i> Reset Data
                        </button>
                        <button class="btn-export" onclick="exportTableToExcel('tabelRiwayatKas', 'Riwayat_Kas_Admin')">
                            <i class="fa fa-file-excel"></i> Export ke Excel
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table id="tabelRiwayatKas">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal & Waktu</th>
                                <th>Jenis Transaksi</th>
                                <th>Nominal</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // UPDATE: Menambahkan LIMIT $halaman_awal, $batas untuk PAGINATION
                            $query_riwayat = "SELECT * FROM riwayat_kas_admin ORDER BY tanggal DESC LIMIT $halaman_awal, $batas";
                            $result_riwayat = mysqli_query($koneksi, $query_riwayat);
                            $no_riwayat = $halaman_awal + 1; // Update nomor urut otomatis

                            if (mysqli_num_rows($result_riwayat) > 0) {
                                while ($row_r = mysqli_fetch_assoc($result_riwayat)) {
                                    $tgl_r = date('d M Y (H:i)', strtotime($row_r['tanggal']));
                                    $jenis_r = $row_r['jenis_transaksi'];
                                    $nominal_r = "Rp " . number_format($row_r['nominal'], 0, ',', '.');
                                    $ket_r = htmlspecialchars($row_r['keterangan']);
                                    
                                    if ($jenis_r == 'Masuk') {
                                        $badge_r = "<span class='badge badge-masuk'><i class='fa fa-arrow-down'></i> Masuk</span>";
                                        $warna_uang = "#27ae60"; // Hijau
                                    } else {
                                        $badge_r = "<span class='badge badge-keluar'><i class='fa fa-arrow-up'></i> Keluar</span>";
                                        $warna_uang = "#e74c3c"; // Merah
                                    }

                                    echo "<tr>";
                                    echo "<td>" . $no_riwayat++ . "</td>";
                                    echo "<td>" . $tgl_r . "</td>";
                                    echo "<td>" . $badge_r . "</td>";
                                    echo "<td style='color:$warna_uang; font-weight:bold;'>" . $nominal_r . "</td>";
                                    echo "<td>" . $ket_r . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5' style='text-align:center; padding:20px; color:#7f8c8d;'>Belum ada riwayat transaksi kas.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($jumlah_data_riwayat > 0): ?>
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
        </div>
    </div>

    <div id="resetModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header-red" style="padding: 25px;">
                <i class="fa fa-exclamation-triangle" style="font-size: 50px; color: white;"></i>
            </div>
            <div class="modal-body">
                <h3>Hapus Riwayat Kas?</h3>
                <p>Tindakan ini akan <b>menghapus seluruh tabel riwayat arus kas.</b> (Saldo Kas Admin tidak akan terpengaruh/berubah).</p>
                <form method="POST" action="">
                    <div class="modal-buttons">
                        <button type="button" class="btn-cancel" onclick="document.getElementById('resetModal').style.display='none'">Batal</button>
                        <button type="submit" name="reset_kas" class="btn-confirm-red">Ya, Hapus Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="logoutModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header-red">
                <img src="assets/logo-lebak.png" alt="Logo">
            </div>
            <div class="modal-body">
                <h3>Konfirmasi Logout</h3>
                <p>Apakah Anda yakin ingin keluar dari sistem?</p>
                <div class="modal-buttons">
                    <button class="btn-cancel" onclick="document.getElementById('logoutModal').style.display='none'">Batal</button>
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
    
    // FUNGSI MEMANGGIL MODAL RESET
    function showResetModal() { document.getElementById('resetModal').style.display = 'flex'; }

    // FUNGSI SAKTI EXPORT KE EXCEL
    function exportTableToExcel(tableID, filename = ''){
        var downloadLink;
        var dataType = 'application/vnd.ms-excel';
        var tableSelect = document.getElementById(tableID);
        var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
        
        var date = new Date();
        var tglString = date.getFullYear() + "-" + (date.getMonth()+1) + "-" + date.getDate();
        filename = filename ? filename + '_' + tglString + '.xls' : 'Riwayat_Kas.xls';
        
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

    // Bersihkan URL saat load agar notif tidak ke-refresh
    if ( window.history.replaceState ) {
        window.history.replaceState( null, null, window.location.href );
    }
</script>

</body>
</html>