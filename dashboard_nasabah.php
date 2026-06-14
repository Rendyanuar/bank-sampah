<?php
session_start();

// Mencegah browser menyimpan cache halaman ini
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

// Cek apakah user belum login atau bukan nasabah
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'nasabah') {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

// Ambil username dari session (Ini adalah Nomor Anggotanya)
$username_aktif = $_SESSION['username'];

// 1. Ambil Data Nasabah (Nama, Saldo Terbaru, Foto Profil)
$q_user = mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username_aktif' AND role = 'nasabah'");
$data_user = mysqli_fetch_assoc($q_user);
$saldo_nasabah = $data_user['saldo'] ? $data_user['saldo'] : 0;
$nama_nasabah = $data_user['nama_lengkap'];

$notif_sukses = "";
$notif_gagal = "";

// 2. PROSES PENARIKAN TUNAI
if (isset($_POST['tarik_tunai'])) {
    $nominal_tarik = (int)$_POST['nominal'];
    $metode = mysqli_real_escape_string($koneksi, $_POST['metode']);
    $metode_lainnya = isset($_POST['metode_lainnya']) ? mysqli_real_escape_string($koneksi, $_POST['metode_lainnya']) : '';
    $nomor_tujuan = mysqli_real_escape_string($koneksi, $_POST['nomor_tujuan']);

    // Tentukan metode final: Jika pilih "Lainnya", gunakan teks yang diketik manual
    $metode_final = ($metode === 'Lainnya') ? $metode_lainnya : $metode;

    // Cek apakah nominal valid dan saldo mencukupi
    if ($nominal_tarik < 10000) {
        $notif_gagal = "Minimal penarikan adalah Rp 10.000.";
    } elseif ($nominal_tarik > $saldo_nasabah) {
        $notif_gagal = "Saldo Anda tidak mencukupi untuk melakukan penarikan ini.";
    } elseif (empty($metode_final)) {
        $notif_gagal = "Mohon sebutkan nama Bank / E-Wallet tujuan Anda.";
    } else {
        // Paksa ambil waktu Jakarta dari PHP
        date_default_timezone_set('Asia/Jakarta');
        $waktu_sekarang = date('Y-m-d H:i:s');
        
        // Masukkan data ke tabel riwayat penarikan (beserta kolom tanggal)
        $q_insert = "INSERT INTO transaksi_tarik (username_nasabah, nominal, metode, nomor_tujuan, status, tanggal) 
                     VALUES ('$username_aktif', '$nominal_tarik', '$metode_final', '$nomor_tujuan', 'pending', '$waktu_sekarang')";
        
        if (mysqli_query($koneksi, $q_insert)) {
            // Potong saldo nasabah sementara (agar tidak double request)
            mysqli_query($koneksi, "UPDATE users SET saldo = saldo - $nominal_tarik WHERE username = '$username_aktif'");
            $saldo_nasabah -= $nominal_tarik; // Update tampilan saldo di layar
            
            $notif_sukses = "Permintaan penarikan berhasil dikirim! Silakan tunggu Admin melakukan transfer ke rekening/e-wallet Anda.";
        } else {
            $notif_gagal = "Terjadi kesalahan sistem, coba lagi nanti.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard Nasabah - Bank Sampah Induk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar Styling (Mini Sidebar dengan Teks) */
        .sidebar { width: 85px; background-color: #2c3e50; color: white; display: flex; flex-direction: column; z-index: 1001; }
        
        .sidebar h2 { 
            margin: 0; 
            background-color: #1abc9c; 
            font-size: 24px; 
            cursor: default; 
            white-space: nowrap;
            height: 60px; /* Tinggi dikunci presisi */
            display: flex; 
            align-items: center; 
            justify-content: center; 
            box-sizing: border-box;
        }
        
        .sidebar-logo-text { display: none; } /* Teks logo disembunyikan di PC */
        .menu { flex: 1; padding-top: 10px; }
        .menu a { display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 12px 5px; color: #bdc3c7; text-decoration: none; transition: 0.3s; border-left: 4px solid transparent; cursor: pointer;}
        .menu a:hover, .menu a.active { background-color: #34495e; color: white; border-left-color: #1abc9c; }
        .menu a i { font-size: 22px; margin-bottom: 4px; }
        .menu a span.menu-text { font-size: 10px; text-align: center; line-height: 1.2; font-weight: 600;}
        
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative;}
        
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
        .header h3 { margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 18px;}
        
        /* Hamburger Button & Overlay */
        .mobile-menu-btn { display: none; font-size: 20px; color: white; cursor: pointer; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; }

        /* HEADER KANAN (User Info + Saldo Button) */
        .header-right { display: flex; align-items: center; gap: 20px; }
        .btn-saldo-header { background: white; color: #16a085; padding: 8px 15px; border-radius: 20px; text-decoration: none; font-size: 14px; font-weight: bold; border: none; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1); white-space: nowrap;}
        .btn-saldo-header:hover { transform: translateY(-2px); box-shadow: 0 6px 10px rgba(0,0,0,0.15); }
        .btn-saldo-header i { margin-right: 5px; }

        /* DROPDOWN PROFIL NASABAH */
        .profile-dropdown { position: relative; display: inline-block; }
        .profile-dropdown-toggle { display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 20px; transition: 0.3s; cursor: pointer; user-select: none; }
        .profile-dropdown-toggle:hover, .profile-dropdown-toggle:active { background-color: rgba(255,255,255,0.1); }
        .profile-dropdown-toggle img { width: 32px; height: 32px; border-radius: 50%; background: #ddd; border: 2px solid white; object-fit: cover; }
        
        .profile-dropdown-menu { display: none; position: absolute; right: 0; top: 120%; background-color: white; min-width: 200px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); border-radius: 10px; overflow: hidden; z-index: 100; border: 1px solid #eee; }
        .profile-dropdown-menu.show { display: block; animation: fadeIn 0.2s ease-in-out; }
        .profile-dropdown-menu a { color: #555; padding: 12px 20px; text-decoration: none; display: flex; align-items: center; font-size: 14px; transition: 0.2s; cursor: pointer; }
        .profile-dropdown-menu a:hover { background-color: #f1fcf9; color: #1abc9c; }
        .profile-dropdown-menu a i { margin-right: 12px; font-size: 16px; width: 20px; text-align: center; }
        .dropdown-divider { height: 1px; background-color: #eee; margin: 0; }

        .content { padding: 30px; flex: 1; overflow-y: auto; overflow-x: hidden; width: 100%; box-sizing: border-box;}
        .card { background-color: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 30px; width: 100%; max-width: 100%; box-sizing: border-box; overflow: hidden;}
        .card h2 { margin-top: 0; color: #1abc9c; }
        .card p { color: #666; margin-bottom: 0;}
        
        /* TABEL RIWAYAT TRANSAKSI */
        .table-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 15px;}
        .table-header h3 { margin: 0; color: #2c3e50; font-size: 18px; }
        
        .table-responsive { width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px; display: block;}
        table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 600px; white-space: nowrap;}
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        table th { background-color: #f8f9fa; color: #555; font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px;}
        table tr:hover { background-color: #f9fbfb; }
        
        /* BADGE STATUS YANG DIPERBARUI */
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; color: white;}
        .badge-pending { background-color: #f39c12; }
        .badge-disetujui { background-color: #3498db; }
        .badge-selesai { background-color: #2ecc71; }
        .badge-ditolak { background-color: #e74c3c; }
        .badge-dibatalkan { background-color: #95a5a6; } /* Warna abu-abu untuk Dibatalkan */

        /* MODAL GLOBAL */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 380px; border-radius: 16px; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.4); text-align: center; animation: popIn 0.3s ease-out; }
        
        /* KELAS KHUSUS MODAL MUNGIL (LOGOUT & NOTIF) */
        .modal-box-small { width: 320px !important; }

        .modal-header-green { background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%); padding: 25px 20px; color: white; }
        .modal-header-red { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); padding: 25px 20px; color: white; }
        .modal-header-green i, .modal-header-red i { font-size: 60px; text-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .modal-header-red img { width: 80px; height: auto; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2)); }
        
        .modal-body { padding: 25px 30px 30px; background: #fff; }
        .modal-body h3 { margin: 0 0 10px; color: #333; font-size: 20px; font-weight: 800;}
        .modal-body p { color: #666; margin-bottom: 25px; font-size: 14px; line-height: 1.5; }
        .modal-buttons { display: flex; gap: 15px; justify-content: center; width: 100%;}
        .btn-confirm-green { background: #16a085; color: white; padding: 12px 20px; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; flex: 1;}
        .btn-confirm-green:hover { background: #12876f; }
        .btn-cancel { background: #e0e0e0; color: #555; padding: 12px 20px; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; flex: 1;}
        .btn-cancel:hover { background: #d5d5d5; }

        /* DESAIN STRUK DIGITAL */
        .struk-container { background: #f8f9fa; border: 1px dashed #bdc3c7; border-radius: 10px; padding: 20px; margin-bottom: 20px; text-align: left; }
        .struk-container p { margin: 5px 0; font-size: 13px; color: #555; border-bottom: 1px dotted #ccc; padding-bottom: 5px; }
        .struk-container p span { float: right; font-weight: bold; color: #2c3e50; }
        .struk-container .total-struk { text-align: center; font-size: 24px; color: #1abc9c; font-weight: bold; margin-top: 15px; border: none; }
        
        .form-group { text-align: left; margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; color: #555; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group select { width: 100%; padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #f9fbfb; transition: 0.3s;}
        .form-group input:focus, .form-group select:focus { border-color: #1abc9c; outline: none; background: white;}

        @keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* =======================================================
           RESPONSIVE MOBILE (HP)
           ======================================================= */
        @media screen and (max-width: 768px) {
            .mobile-menu-btn { display: block; }
            .header { padding: 0 20px; }
            .header h3 { font-size: 16px; }
            .header-right { gap: 10px; }
            .btn-saldo-header { padding: 6px 12px; font-size: 12px; }
            .profile-dropdown-toggle img { width: 28px; height: 28px; }
            .user-info b { font-size: 12px !important; }
            .user-info span { display: none !important; }

            .sidebar {
                position: fixed;
                top: 0;
                left: -260px; /* Menyembunyikan sidebar di kiri */
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

            .content { padding: 15px; }
            .card { padding: 20px; margin-bottom: 20px; width: 100%; max-width: calc(100vw - 30px); }
            .card h2 { font-size: 18px; }
            
            .profile-dropdown-menu { right: -10px; min-width: 180px;}

            /* UKURAN MODAL UMUM DI HP (TARIK TUNAI & STRUK TETAP LEGA) */
            .modal-box { width: 92%; max-width: 380px; }

            /* UKURAN MODAL SUPER MUNGIL KHUSUS LOGOUT & NOTIFIKASI */
            .modal-box-small { max-width: 280px !important; border-radius: 12px; }
            .modal-box-small .modal-header-green, .modal-box-small .modal-header-red, .modal-box-small .modal-header-blue { padding: 15px; }
            .modal-box-small .modal-header-green i, .modal-box-small .modal-header-red i, .modal-box-small .modal-header-blue i { font-size: 40px; }
            .modal-box-small .modal-header-red img { width: 60px; }
            .modal-box-small .modal-body { padding: 15px 20px 20px 20px; }
            .modal-box-small .modal-body h3 { font-size: 18px; margin-bottom: 8px;}
            .modal-box-small .modal-body p { font-size: 13px; margin-bottom: 15px; line-height: 1.4; }
            .modal-box-small .info-box { padding: 12px; margin-bottom: 15px; }
            .modal-box-small .info-box p { font-size: 12px; margin-bottom: 5px; }
            .modal-box-small .info-box strong { font-size: 14px; }
            .modal-box-small .modal-buttons { gap: 8px; }
            .modal-box-small .modal-buttons button { padding: 10px; font-size: 12px; }
        }
    </style>
</head>
<body>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleMobileMenu()"></div>

    <div class="sidebar" id="sidebarMenu">
        <h2 title="Bank Sampah"><i class="fa fa-recycle"></i><span class="sidebar-logo-text">BANK SAMPAH</span></h2>
        <div class="menu">
            <a href="dashboard_nasabah.php" class="active">
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
            <a href="profil_nasabah.php">
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
                Beranda
            </h3>
            <div class="header-right">
                
                <div class="profile-dropdown">
                    <div class="profile-dropdown-toggle" onclick="toggleDropdown()">
                        <div class="user-info" style="text-align: right; margin-right: 5px;">
                            <span style="font-size: 12px; color: rgba(255,255,255,0.8); display: block; line-height: 1;">Nasabah</span>
                            <b style="font-size: 14px; color: white;"><?php echo htmlspecialchars($nama_nasabah); ?></b>
                        </div>
                        <?php 
                        $path_foto_header = (!empty($data_user['foto_profil']) && file_exists('assets/profil/' . $data_user['foto_profil'])) 
                                            ? 'assets/profil/' . $data_user['foto_profil'] 
                                            : 'https://ui-avatars.com/api/?name=' . urlencode($nama_nasabah) . '&background=1abc9c&color=fff';
                        ?>
                        <img src="<?php echo $path_foto_header; ?>" alt="Avatar">
                        <i class="fa fa-chevron-down" style="font-size: 12px; color: white; margin-left: 5px;"></i>
                    </div>
                    <div id="myDropdown" class="profile-dropdown-menu">
                        <a href="profil_nasabah.php"><i class="fa fa-user-circle"></i> Profil Saya</a>
                        <a href="ubah_password.php"><i class="fa fa-key"></i> Ubah Password</a>
                        <a href="https://wa.me/6287772666425" target="_blank"><i class="fa fa-headset"></i> Bantuan (Admin)</a>
                        <div class="dropdown-divider"></div>
                        <a onclick="showLogoutModal()" style="color: #e74c3c;"><i class="fa fa-sign-out-alt"></i> Keluar</a>
                    </div>
                </div>

                <button class="btn-saldo-header" onclick="showStrukModal()">
                    <i class="fa fa-wallet"></i> Rp <?php echo number_format($saldo_nasabah, 0, ',', '.'); ?>
                </button>
            </div>
        </div>
        
        <div class="content">
            <div class="card">
                <h2>Selamat Datang, <?php echo htmlspecialchars($nama_nasabah); ?>!</h2>
                <p>Mari mulai mengumpulkan sampah dan ubah menjadi tabungan yang bermanfaat bagi lingkungan dan diri sendiri.</p>
                <p style="margin-top:10px; font-size:13px; color:#e67e22;"><i class="fa fa-lightbulb"></i> <b>Tips:</b> Klik tombol saldo di pojok kanan atas untuk melihat struk digital dan menarik tunai uang Anda.</p>
            </div>

            <div style="background-color: #e8f6f3; padding: 15px 25px; border-radius: 12px; margin-bottom: 30px; border: 1px solid #bde6de; display: flex; flex-wrap: wrap; gap: 20px; align-items: center; font-size: 13px; color: #2c3e50; box-shadow: 0 4px 10px rgba(0,0,0,0.03); width: 100%; box-sizing: border-box;">
                <strong style="font-size: 14px; color: #16a085;"><i class="fa fa-info-circle"></i> Arti Warna Status:</strong>
                
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 14px; height: 14px; background-color: #f39c12; border-radius: 4px;"></span> 
                    <span><b>Menunggu</b> (Diperiksa Admin)</span>
                </div>
                
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 14px; height: 14px; background-color: #3498db; border-radius: 4px;"></span> 
                    <span><b>Disetujui</b> (Silakan datang ke tempat)</span>
                </div>
                
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 14px; height: 14px; background-color: #2ecc71; border-radius: 4px;"></span> 
                    <span><b>Selesai</b> (Saldo Masuk)</span>
                </div>
                
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 14px; height: 14px; background-color: #e74c3c; border-radius: 4px;"></span> 
                    <span><b>Ditolak</b> (Data Tidak Valid)</span>
                </div>
                
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 14px; height: 14px; background-color: #95a5a6; border-radius: 4px;"></span> 
                    <span><b>Dibatalkan</b> (Dibatalkan Nasabah)</span>
                </div>
            </div>

            <div class="card" style="padding: 25px;">
                <div class="table-header">
                    <h3><i class="fa fa-history"></i> Riwayat Setoran Terakhir Anda</h3>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Kategori Sampah</th>
                                <th>Berat</th>
                                <th>Total Dana</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $query_history = "SELECT ts.*, k.nama_barang 
                                              FROM transaksi_setoran ts
                                              JOIN kategori_sampah k ON ts.id_kategori = k.id
                                              WHERE ts.username_nasabah = '$username_aktif'
                                              ORDER BY ts.tanggal DESC LIMIT 10";
                            $result_history = mysqli_query($koneksi, $query_history);
                            $no = 1;

                            if (mysqli_num_rows($result_history) > 0) {
                                while ($row = mysqli_fetch_assoc($result_history)) {
                                    echo "<tr>";
                                    echo "<td>" . $no++ . "</td>";
                                    echo "<td>" . date('d M Y', strtotime($row['tanggal'])) . "</td>";
                                    echo "<td><b>" . htmlspecialchars($row['nama_barang']) . "</b></td>";
                                    echo "<td>" . $row['berat'] . " Kg</td>";
                                    echo "<td style='color:#2e7d32; font-weight:bold;'>Rp " . number_format($row['total_harga'], 0, ',', '.') . "</td>";
                                    
                                    // LOGIKA STATUS YANG DIPERBARUI
                                    $status_db = strtolower(trim($row['status']));
                                    if ($status_db == 'selesai') {
                                        $badge_class = 'badge-selesai'; $text = 'Selesai';
                                    } elseif ($status_db == 'disetujui') {
                                        $badge_class = 'badge-disetujui'; $text = 'Disetujui';
                                    } elseif ($status_db == 'ditolak') {
                                        $badge_class = 'badge-ditolak'; $text = 'Ditolak';
                                    } elseif ($status_db == 'dibatalkan') {
                                        $badge_class = 'badge-dibatalkan'; $text = 'Dibatalkan';
                                    } else {
                                        $badge_class = 'badge-pending'; $text = 'Menunggu';
                                    }
                                    echo "<td><span class='badge $badge_class'>" . $text . "</span></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' style='text-align:center; padding:20px; color:#95a5a6;'>Anda belum memiliki riwayat setoran.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div> 
            </div>
        </div>
    </div>

    <div id="strukModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header-green">
                <i class="fa fa-receipt"></i>
            </div>
            <div class="modal-body">
                <h3 style="text-align:center;">Informasi Tabungan</h3>
                <div class="struk-container">
                    <p>Nama Nasabah: <span><?php echo htmlspecialchars($nama_nasabah); ?></span></p>
                    <p>Nomor Anggota: <span><?php echo htmlspecialchars($username_aktif); ?></span></p>
                    <p style="border-bottom:none;">Saldo Tersedia:</p>
                    <p class="total-struk">Rp <?php echo number_format($saldo_nasabah, 0, ',', '.'); ?></p>
                </div>
                <div class="modal-buttons">
                    <button class="btn-cancel" onclick="closeStrukModal()">Tutup</button>
                    <button class="btn-confirm-green" onclick="bukaModalTarik()">Tarik Tunai</button>
                </div>
            </div>
        </div>
    </div>

    <div id="tarikModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header-green" style="padding: 15px 20px; background: linear-gradient(135deg, #3498db, #2980b9);">
                <h3 style="margin:0; color:white; text-align:center;"><i class="fa fa-money-bill-wave"></i> Form Penarikan</h3>
            </div>
            <div class="modal-body" style="padding-top: 20px;">
                <form method="POST" action="" onsubmit="return validasiTarik()">
                    <div class="form-group">
                        <label>Pilih Metode Transfer</label>
                        <select name="metode" id="pilih_metode" onchange="ubahLabelTujuan()" required>
                            <option value="">-- Pilih E-Wallet / Bank --</option>
                            <option value="DANA">DANA</option>
                            <option value="OVO">OVO</option>
                            <option value="GoPay">GoPay</option>
                            <option value="ShopeePay">ShopeePay</option>
                            <option value="Bank BRI">Bank BRI</option>
                            <option value="Bank BCA">Bank BCA</option>
                            <option value="Bank Mandiri">Bank Mandiri</option>
                            <option value="Lainnya" style="font-weight:bold; color:#16a085;">Lainnya (Ketik Manual)...</option>
                        </select>
                    </div>

                    <div class="form-group" id="box_metode_lainnya" style="display: none; animation: fadeIn 0.3s;">
                        <label>Nama Bank / E-Wallet Lainnya</label>
                        <input type="text" name="metode_lainnya" id="input_metode_lainnya" placeholder="(Contoh: Bank BJB / LinkAja)">
                    </div>

                    <div class="form-group">
                        <label id="label_tujuan">Nomor Rekening / HP (E-Wallet)</label>
                        <input type="text" name="nomor_tujuan" id="input_tujuan" placeholder="Contoh: 081234567890" required>
                    </div>
                    <div class="form-group">
                        <label>Nominal Penarikan (Rp)</label>
                        <input type="number" id="input_nominal" name="nominal" placeholder="Minimal 10000" required>
                    </div>
                    <p style="font-size:12px; color:#e74c3c; text-align:left; margin-bottom:15px; margin-top:-5px;">
                        <i>*Uang akan dikirim manual oleh Admin.</i>
                    </p>
                    <div class="modal-buttons">
                        <button type="button" class="btn-cancel" onclick="kembaliKeStruk()">Batal</button>
                        <button type="submit" name="tarik_tunai" class="btn-confirm-green" style="background:#3498db;">Kirim Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="notifModal" class="modal-overlay" style="display: <?php echo (!empty($notif_sukses) || !empty($notif_gagal)) ? 'flex' : 'none'; ?>;">
        <div class="modal-box modal-box-small">
            <div id="notifHeader" class="<?php echo !empty($notif_sukses) ? 'modal-header-green' : 'modal-header-red'; ?>">
                <i id="notifIcon" class="<?php echo !empty($notif_sukses) ? 'fa fa-check-circle' : 'fa fa-times-circle'; ?>"></i>
            </div>
            <div class="modal-body" style="text-align:center;">
                <h3 id="notifTitle"><?php echo !empty($notif_sukses) ? 'Berhasil!' : (!empty($notif_gagal) ? 'Gagal!' : 'Peringatan!'); ?></h3>
                <p id="notifMessage"><?php echo !empty($notif_sukses) ? $notif_sukses : $notif_gagal; ?></p>
                <button id="notifBtn" class="btn-confirm-green" style="width: 100%; <?php echo !empty($notif_gagal) ? 'background:#e74c3c;' : ''; ?>" onclick="tutupNotif()">Tutup</button>
            </div>
        </div>
    </div>

    <div id="customModal" class="modal-overlay">
        <div class="modal-box modal-box-small">
            <div class="modal-header-green" style="background: #e74c3c;">
                <img src="assets/logo-lebak.png" alt="Logo Instansi">
            </div>
            <div class="modal-body" style="text-align:center;">
                <h3>Konfirmasi Logout</h3>
                <p>Apakah Anda yakin ingin keluar dari sistem Bank Sampah Induk?</p>
                <div class="modal-buttons">
                    <button class="btn-cancel" onclick="closeLogoutModal()">Batal</button>
                    <button class="btn-confirm-green" style="background: #c0392b;" onclick="prosesLogout()">Keluar</button>
                </div>
            </div>
        </div>
    </div>

<script>
    // --- FUNGSI LIVE IF UNTUK METODE TRANSFER ---
    function ubahLabelTujuan() {
        var select = document.getElementById("pilih_metode");
        var label = document.getElementById("label_tujuan");
        var input = document.getElementById("input_tujuan");
        
        var boxLainnya = document.getElementById("box_metode_lainnya");
        var inputLainnya = document.getElementById("input_metode_lainnya");
        
        var nilai = select.value;

        // Logika untuk menampilkan form "Lainnya"
        if (nilai === "Lainnya") {
            boxLainnya.style.display = "block";
            inputLainnya.required = true;
            
            label.innerText = "Nomor Rekening / HP Tujuan";
            input.placeholder = "Contoh: 081234567890";
        } else {
            boxLainnya.style.display = "none";
            inputLainnya.required = false;
            inputLainnya.value = ""; // Kosongkan isian jika opsi diganti

            if (nilai === "") {
                label.innerText = "Nomor Rekening / HP (E-Wallet)";
                input.placeholder = "Contoh: 081234567890";
            } else if (nilai.includes("Bank")) {
                label.innerText = "Nomor Rekening " + nilai;
                input.placeholder = "Masukkan Nomor Rekening " + nilai;
            } else {
                label.innerText = "Nomor E-Wallet " + nilai;
                input.placeholder = "Masukkan Nomor HP " + nilai;
            }
        }
    }

    // --- FITUR TOGGLE HAMBURGER MENU (MOBILE) ---
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

    // --- FUNGSI KLIK / TAP DROPDOWN PROFIL ---
    function toggleDropdown() {
        document.getElementById("myDropdown").classList.toggle("show");
    }

    // Menutup dropdown jika user mengklik area lain
    window.onclick = function(event) {
        if (!event.target.closest('.profile-dropdown')) {
            var dropdowns = document.getElementsByClassName("profile-dropdown-menu");
            for (var i = 0; i < dropdowns.length; i++) {
                if (dropdowns[i].classList.contains('show')) {
                    dropdowns[i].classList.remove('show');
                }
            }
        }
    }

    // --- LOGIKA MODAL POP-UP DAN VALIDASI PENARIKAN (UPDATE) ---
    function showStrukModal() { document.getElementById('strukModal').style.display = 'flex'; }
    function closeStrukModal() { document.getElementById('strukModal').style.display = 'none'; }
    
    // Cek saldo dulu sebelum membuka form penarikan
    function bukaModalTarik() {
        var saldo = <?php echo $saldo_nasabah; ?>;
        if (saldo < 10000) {
            closeStrukModal();
            var pesan = saldo <= 0 ? "Maaf, saldo tabungan Anda Rp 0. Anda belum bisa melakukan penarikan tunai." : "Saldo Anda belum mencapai batas minimal penarikan (Rp 10.000).";
            tampilkanNotifGagalJS(pesan);
            return;
        }
        document.getElementById('strukModal').style.display = 'none';
        document.getElementById('tarikModal').style.display = 'flex';
    }

    function kembaliKeStruk() {
        document.getElementById('tarikModal').style.display = 'none';
        document.getElementById('strukModal').style.display = 'flex';
    }

    // Validasi nominal saat tombol Kirim Request ditekan
    function validasiTarik() {
        var nominal = parseInt(document.getElementById('input_nominal').value);
        var saldo = <?php echo $saldo_nasabah; ?>;
        
        if (isNaN(nominal) || nominal < 10000) {
            tampilkanNotifGagalJS("Minimal penarikan tunai adalah Rp 10.000.");
            return false;
        }
        if (nominal > saldo) {
            tampilkanNotifGagalJS("Maaf, saldo Anda kurang. Saldo maksimal yang dapat Anda tarik saat ini adalah Rp " + saldo.toLocaleString('id-ID') + ".");
            return false;
        }
        return true;
    }

    // Fungsi trigger Notifikasi Merah Custom
    function tampilkanNotifGagalJS(pesan) {
        document.getElementById('tarikModal').style.display = 'none';
        document.getElementById('strukModal').style.display = 'none';
        
        document.getElementById('notifHeader').className = 'modal-header-red';
        document.getElementById('notifIcon').className = 'fa fa-times-circle';
        document.getElementById('notifTitle').innerText = 'Gagal!';
        document.getElementById('notifMessage').innerText = pesan;
        document.getElementById('notifBtn').style.background = '#e74c3c';
        
        document.getElementById('notifModal').style.display = 'flex';
    }

    function tutupNotif() {
        document.getElementById('notifModal').style.display = 'none';
        window.location.href = window.location.pathname; // Hapus parameter POST/GET agar tidak refresh berulang
    }

    function showLogoutModal() { document.getElementById('customModal').style.display = 'flex'; }
    function closeLogoutModal() { document.getElementById('customModal').style.display = 'none'; }
    function prosesLogout() { window.location.href = 'logout.php'; }
    
    // Mencegah form tersubmit ulang saat halaman direfresh
    if ( window.history.replaceState ) {
        window.history.replaceState( null, null, window.location.href );
    }
</script>

</body>
</html>