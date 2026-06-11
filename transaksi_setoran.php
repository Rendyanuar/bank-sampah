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

// Paksa PHP menggunakan waktu Indonesia agar fungsi date() di tabel akurat
date_default_timezone_set('Asia/Jakarta');

$notif_sukses = "";
$notif_gagal = "";

// Ambil Foto Profil Admin untuk Header Dropdown
$q_foto = mysqli_query($koneksi, "SELECT foto_profil FROM users WHERE username = '".$_SESSION['username']."'");
$d_foto = mysqli_fetch_assoc($q_foto);
$path_foto_header = (!empty($d_foto['foto_profil']) && file_exists('assets/profil/' . $d_foto['foto_profil'])) 
                    ? 'assets/profil/' . $d_foto['foto_profil'] 
                    : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['nama']) . '&background=1abc9c&color=fff';

// Ambil Saldo Admin Saat Ini (Untuk dimunculkan di Modal Verifikasi Ulang)
$q_saldo_admin = mysqli_query($koneksi, "SELECT saldo FROM users WHERE role = 'admin' LIMIT 1");
$d_saldo_admin = mysqli_fetch_assoc($q_saldo_admin);
$saldo_admin_saat_ini = ($d_saldo_admin['saldo'] !== null) ? $d_saldo_admin['saldo'] : 0;

// ==================================================================
// 1. PROSES EDIT BERAT (KG) - OTOMATIS HITUNG ULANG (ANTI-ERROR)
// ==================================================================
if (isset($_POST['proses_edit_berat'])) {
    $id_trans = mysqli_real_escape_string($koneksi, $_POST['id_transaksi']);
    
    // Konversi koma ke titik agar aman dibaca sistem desimal database
    $berat_baru_raw = str_replace(',', '.', $_POST['berat_baru']);
    $berat_baru = (float)$berat_baru_raw;

    if ($berat_baru <= 0) {
        $notif_gagal = "Gagal! Berat (Kg) harus lebih besar dari 0.";
    } else {
        // Ambil data transaksi lama
        $q_ts = mysqli_query($koneksi, "SELECT * FROM transaksi_setoran WHERE id = '$id_trans'");
        if ($q_ts && mysqli_num_rows($q_ts) > 0) {
            $d_old = mysqli_fetch_assoc($q_ts);
            $id_kat = $d_old['id_kategori'];
            $status_sekarang = $d_old['status'];
            $total_lama = (int)$d_old['total_harga'];
            $uname_nasabah = $d_old['username_nasabah'];

            // Ambil harga dari kategori sampah (Otomatis melacak variasi nama kolom harga)
            $q_kat = mysqli_query($koneksi, "SELECT * FROM kategori_sampah WHERE id = '$id_kat'");
            $d_kat = mysqli_fetch_assoc($q_kat);
            
            $harga_per_kg = 0;
            if(isset($d_kat['harga_barang'])) { 
            $harga_per_kg = $d_kat['harga_barang']; }
            
            if ($harga_per_kg > 0) {
                // Kalkulasi nominal baru dan selisihnya
                $total_baru = $berat_baru * $harga_per_kg;
                $selisih = $total_baru - $total_lama;

                $q_upd = "UPDATE transaksi_setoran SET berat = '$berat_baru', total_harga = '$total_baru' WHERE id = '$id_trans'";
                
                if (mysqli_query($koneksi, $q_upd)) {
                    // Sinkronisasi saldo hanya berjalan jika status transaksi sudah 'selesai'
                    if ($status_sekarang == 'selesai') {
                        mysqli_query($koneksi, "UPDATE users SET saldo = saldo + $selisih WHERE username = '$uname_nasabah' AND role='nasabah'");
                        mysqli_query($koneksi, "UPDATE users SET saldo = saldo - $selisih WHERE role = 'admin'");
                        $saldo_admin_saat_ini -= $selisih; 
                    }
                    $notif_sukses = "Sukses! Timbangan diubah ke $berat_baru Kg. Harga otomatis disesuaikan menjadi Rp " . number_format($total_baru, 0, ',', '.');
                } else {
                    $notif_gagal = "Gagal memperbarui database: " . mysqli_error($koneksi);
                }
            } else {
                $notif_gagal = "Gagal! Tidak dapat menemukan harga dasar di database Kategori Sampah.";
            }
        } else {
            $notif_gagal = "Gagal! Data transaksi setoran tidak ditemukan.";
        }
    }
}

// ==================================================================
// 2. PROSES JIKA ADMIN MENEKAN TOMBOL SETUJUI / TOLAK / SELESAI
// ==================================================================
if (isset($_GET['aksi']) && isset($_GET['id'])) {
    $id_transaksi = mysqli_real_escape_string($koneksi, $_GET['id']);
    $aksi = $_GET['aksi'];

    if ($aksi == 'setujui') {
        $query_status = "UPDATE transaksi_setoran SET status = 'disetujui' WHERE id = '$id_transaksi'";
        if (mysqli_query($koneksi, $query_status)) {
            $notif_sukses = "Setoran berhasil disetujui! Lanjutkan ke tahap 'Selesai' untuk memotong saldo kas dan menyelesaikan transaksi.";
        }
    } elseif ($aksi == 'tolak') {
        $query_status = "UPDATE transaksi_setoran SET status = 'ditolak' WHERE id = '$id_transaksi'";
        if (mysqli_query($koneksi, $query_status)) {
            $notif_sukses = "Setoran nasabah telah ditolak.";
        }
    } elseif ($aksi == 'selesai') {
        $q_trans = mysqli_query($koneksi, "SELECT total_harga, username_nasabah, status FROM transaksi_setoran WHERE id = '$id_transaksi'");
        $d_trans = mysqli_fetch_assoc($q_trans);
        
        if ($d_trans && $d_trans['status'] == 'disetujui') {
            $total_harga = $d_trans['total_harga'];
            $user_nasabah = $d_trans['username_nasabah'];
            
            if ($saldo_admin_saat_ini >= $total_harga) {
                mysqli_query($koneksi, "UPDATE users SET saldo = saldo - $total_harga WHERE role = 'admin'");
                mysqli_query($koneksi, "UPDATE users SET saldo = COALESCE(saldo, 0) + $total_harga WHERE username = '$user_nasabah' AND role = 'nasabah'");
                mysqli_query($koneksi, "UPDATE transaksi_setoran SET status = 'selesai' WHERE id = '$id_transaksi'");
                
                $notif_sukses = "Transaksi Selesai! Saldo kas Admin berhasil dipotong dan otomatis ditransfer ke saldo Nasabah.";
                $saldo_admin_saat_ini -= $total_harga; 
            } else {
                $notif_gagal = "Gagal memproses! Saldo kas Admin tidak mencukupi untuk membayar setoran ini.";
            }
        }
    }
}

// SINKRONISASI HITUNG ANGKA MERAH BADGE NOTIFIKASI SIDEBAR
$q_notif_setoran = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM transaksi_setoran WHERE status = 'pending'");
$notif_setoran = mysqli_fetch_assoc($q_notif_setoran)['total'];

$q_notif_tarik = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM transaksi_tarik WHERE status = 'pending'");
$notif_tarik = mysqli_fetch_assoc($q_notif_tarik)['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Transaksi Setoran - Bank Sampah Induk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar Styling (Sesuai Dashboard Admin) */
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
        
        /* Header Styling */
        .header { background-color: #1abc9c; padding: 0 30px; height: 60px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 10; box-sizing: border-box;}
        .header h3 { margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 18px; }
        .mobile-menu-btn { display: none; font-size: 20px; color: white; cursor: pointer; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; }
        
        /* Dropdown Profil */
        .header-right { display: flex; align-items: center; }
        .profile-dropdown { position: relative; display: inline-block; }
        .profile-dropdown-toggle { display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 20px; transition: 0.3s; cursor: pointer; user-select: none; }
        .profile-dropdown-toggle:hover, .profile-dropdown-toggle:active { background-color: rgba(255,255,255,0.1); }
        .profile-dropdown-toggle img { width: 32px; height: 32px; border-radius: 50%; background: #ddd; border: 2px solid white; object-fit: cover; }
        .user-info { font-size: 14px; color: white; display: flex; align-items: center; gap: 10px;}
        .user-info span { color: rgba(255,255,255,0.9); display: block; line-height: 1; }
        .user-info b { color: white; }
        
        .profile-dropdown-menu { display: none; position: absolute; right: 0; top: 120%; background-color: white; min-width: 200px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); border-radius: 10px; overflow: hidden; z-index: 100; border: 1px solid #eee; }
        .profile-dropdown-menu.show { display: block; animation: fadeIn 0.2s ease-in-out; }
        .profile-dropdown-menu a { color: #555; padding: 12px 20px; text-decoration: none; display: flex; align-items: center; font-size: 14px; transition: 0.2s; cursor: pointer; }
        .profile-dropdown-menu a:hover { background-color: #f1fcf9; color: #1abc9c; }
        .profile-dropdown-menu a i { margin-right: 12px; font-size: 16px; width: 20px; text-align: center; }
        .dropdown-divider { height: 1px; background-color: #eee; margin: 0; }

        .content { padding: 30px; flex: 1; overflow-y: auto; overflow-x: hidden; width: 100%; box-sizing: border-box;}
        
        .card { background-color: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; max-width: 100%; box-sizing: border-box; overflow: hidden;}
        .card-header { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;}
        .card-header h2 { margin: 0; color: #1abc9c; font-size: 22px;}
        
        /* Tabel responsive */
        .table-responsive { width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; display: block; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; min-width: 900px; white-space: nowrap;}
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; vertical-align: middle; }
        table th { background-color: #f8f9fa; color: #333; font-weight: 600; text-transform: uppercase; font-size: 13px; letter-spacing: 0.5px;}
        table tr:hover { background-color: #f1fcf9; transition: 0.2s;}

        .table-bukti { width: 55px; height: 55px; border-radius: 8px; object-fit: cover; border: 2px solid #bdc3c7; cursor: pointer; transition: 0.2s; }
        .table-bukti:hover { border-color: #1abc9c; opacity: 0.8; transform: scale(1.05); }
        
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; color: white; display: inline-block; text-align: center; }
        .badge-pending { background-color: #f39c12; }    
        .badge-disetujui { background-color: #3498db; }  
        .badge-selesai { background-color: #2ecc71; }    
        .badge-ditolak { background-color: #e74c3c; }    
        .badge-dibatalkan { background-color: #95a5a6; }

        /* Tombol Layout */
        .btn-approve { background-color: #2ecc71; color: white; padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 13px; font-weight: bold; transition: 0.2s; display: block; text-align: center; width: 100%; box-sizing: border-box;}
        .btn-approve:hover { background-color: #27ae60; }
        .btn-reject { background-color: #e74c3c; color: white; padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 13px; font-weight: bold; transition: 0.2s; display: block; text-align: center; width: 100%; box-sizing: border-box;}
        .btn-reject:hover { background-color: #c0392b; }
        .btn-selesai { background-color: #3498db; color: white; padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 13px; font-weight: bold; transition: 0.2s; display: block; text-align: center; width: 100%; box-sizing: border-box;}
        .btn-selesai:hover { background-color: #2980b9; }
        .btn-edit-berat { background-color: #f39c12; color: white; padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 13px; font-weight: bold; transition: 0.2s; display: block; text-align: center; width: 100%; box-sizing: border-box; }
        .btn-edit-berat:hover { background-color: #e67e22; }

        .action-buttons-stack { display: flex; flex-direction: column; gap: 8px; width: 100px; margin: 0 auto; }

        /* Modal Structure */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 380px; border-radius: 16px; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.4); text-align: center; animation: popIn 0.3s ease-out; }
        
        /* KELAS KHUSUS MODAL MUNGIL (VERIFIKASI, NOTIF, LOGOUT) */
        .modal-box-small { width: 320px !important; margin: 0 auto;}

        .modal-header-green { background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%); padding: 25px 20px; color: white; }
        .modal-header-blue { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 25px 20px; color: white; }
        .modal-header-red { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); padding: 25px 20px; color: white; }
        .modal-header-green img, .modal-header-blue img, .modal-header-red img { width: 80px; height: auto; }
        .modal-header-green i, .modal-header-blue i, .modal-header-red i { font-size: 60px; text-shadow: 0 4px 10px rgba(0,0,0,0.2);}
        .modal-body { padding: 25px 30px 30px; background: #fff; }
        .modal-body h3 { margin: 0 0 10px; color: #333; font-size: 22px; font-weight: 800;}
        .modal-body p { color: #555; margin-bottom: 25px; font-size: 15px; line-height: 1.5; }
        
        .modal-buttons { display: flex; gap: 10px; justify-content: center; width: 100%;}
        .btn-confirm-green { background: #16a085; color: white; padding: 12px 20px; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; flex: 1;}
        .btn-confirm-green:hover { background: #12876f; }
        .btn-confirm-blue { background: #3498db; color: white; padding: 12px 20px; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; flex: 1;}
        .btn-confirm-blue:hover { background: #2980b9; }
        .btn-confirm-red { background: #e74c3c; color: white; padding: 12px 20px; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; flex: 1;}
        .btn-confirm-red:hover { background: #c0392b; }
        .btn-cancel { background: #ecf0f1; color: #7f8c8d; padding: 12px 20px; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; flex: 1;}
        .btn-cancel:hover { background: #bdc3c7; }

        @keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

        .info-box { background: #f8f9fa; border: 1px solid #eee; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: left; }
        .info-box p { margin: 0 0 8px 0; font-size: 14px; color: #7f8c8d; }
        .info-box p:last-child { margin-bottom: 0; }
        .info-box strong { font-size: 18px; display: block; margin-top: 3px; }

        /* Lightbox CSS */
        #lightboxModal { background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(5px); z-index: 10000;}
        .lightbox-content { position: relative; max-width: 90%; max-height: 90vh; display: flex; flex-direction: column; align-items: center; justify-content: center; animation: popIn 0.3s ease-out;}
        .lightbox-content img { max-width: 100%; max-height: 75vh; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); object-fit: contain; background: white; padding: 10px;}
        .btn-kembali-foto { margin-top: 20px; background: #e74c3c; color: white; border: none; padding: 12px 30px; border-radius: 25px; font-size: 16px; font-weight: bold; cursor: pointer; box-shadow: 0 4px 10px rgba(231,76,60,0.3); transition: 0.2s; }
        .btn-kembali-foto:hover { background: #c0392b; transform: translateY(-2px); }

        /* RESPONSIVE MOBILE */
        @media screen and (max-width: 768px) {
            .mobile-menu-btn { display: block; }
            .header { padding: 0 20px; }
            .user-info span, .user-info b { display: none; }
            .sidebar { position: fixed; top: 0; left: -260px; width: 260px; height: 100vh; transition: 0.3s; box-shadow: 5px 0 15px rgba(0,0,0,0.1); }
            .sidebar.active-mobile { left: 0; }
            .sidebar h2 { font-size: 18px; justify-content: flex-start; padding-left: 20px;}
            .sidebar-logo-text { display: inline; font-size: 18px; margin-left: 10px;}
            .menu { padding-top: 15px; }
            .menu a { flex-direction: row; justify-content: flex-start; padding: 15px 25px; }
            .menu a i { margin-right: 15px; margin-bottom: 0; font-size: 20px;}
            .menu a span.menu-text { font-size: 15px; font-weight: normal;}
            .content { padding: 15px; display: block; }
            .card { padding: 20px; width: 100%; max-width: calc(100vw - 30px); }
            .table-responsive { overflow-x: auto !important; margin-top: 10px; border: 1px solid #f1f1f1; }
            .modal-box { width: 92%; max-width: 380px; }
            
            /* Penyesuaian Modal Mungil di HP */
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

    <div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleMobileMenu()"></div>

    <div class="sidebar" id="sidebarMenu">
        <h2 title="Bank Sampah"><i class="fa fa-recycle"></i><span class="sidebar-logo-text">BANK SAMPAH</span></h2>
        <div class="menu">
            <a href="dashboard_admin.php" title="Dashboard">
                <i class="fa fa-home"></i><span class="menu-text">Beranda</span>
            </a>
            <a href="data_nasabah.php" title="Data Nasabah">
                <i class="fa fa-users"></i><span class="menu-text">Nasabah</span>
            </a>
            <a href="kategori_sampah.php" title="Kategori Sampah">
                <i class="fa fa-trash"></i><span class="menu-text">Kategori</span>
            </a>
            <a href="transaksi_setoran.php" class="active" title="Transaksi Setoran">
                <i class="fa fa-exchange-alt"></i><span class="menu-text">Setoran</span>
                <?php if($notif_setoran > 0) echo "<span class='notif-badge'>$notif_setoran</span>"; ?>
            </a>
            <a href="transaksi_tarik.php" title="Pencairan Saldo">
                <i class="fa fa-hand-holding-usd"></i><span class="menu-text">Pencairan</span>
                <?php if($notif_tarik > 0) echo "<span class='notif-badge'>$notif_tarik</span>"; ?>
            </a>
            <a style="cursor: pointer;" onclick="showLogoutModal()" title="Logout">
                <i class="fa fa-sign-out-alt"></i><span class="menu-text">Keluar</span>
            </a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h3><i class="fa fa-bars mobile-menu-btn" onclick="toggleMobileMenu()"></i> Persetujuan Transaksi Setoran</h3>
            
            <div class="header-right">
                <div class="profile-dropdown">
                    <div class="profile-dropdown-toggle" onclick="toggleDropdown()">
                        <div class="user-info" style="text-align: right; margin-right: 5px;">
                            <span style="font-size: 12px; display: block; line-height: 1;">Administrator</span>
                            <b><?php echo isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'Admin'; ?></b>
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
                    <h2>Permintaan Persetujuan Setoran Sampah</h2>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="white-space: nowrap;">No</th>
                                <th style="white-space: nowrap;">Tanggal Pengajuan</th>
                                <th style="white-space: nowrap;">Nama Nasabah</th>
                                <th style="white-space: nowrap;">Jenis Sampah</th>
                                <th style="white-space: nowrap;">Berat</th>
                                <th style="white-space: nowrap; text-align: center;">Bukti Foto</th>
                                <th style="white-space: nowrap;">Total Dana</th>
                                <th style="white-space: nowrap; text-align: center;">Status</th>
                                <th style="text-align: center; white-space: nowrap;">Tindakan Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $query = "SELECT ts.*, u.nama_lengkap, k.nama_barang 
                                      FROM transaksi_setoran ts
                                      JOIN users u ON ts.username_nasabah = u.username
                                      JOIN kategori_sampah k ON ts.id_kategori = k.id
                                      ORDER BY ts.tanggal DESC";
                            
                            $result = mysqli_query($koneksi, $query);
                            $no = 1;

                            if (mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    echo "<tr>";
                                    echo "<td>" . $no++ . "</td>";
                                    echo "<td style='white-space: nowrap;'>" . date('d M Y, H:i', strtotime($row['tanggal'])) . " WIB</td>";
                                    echo "<td style='white-space: nowrap;'><b>" . htmlspecialchars($row['nama_lengkap']) . "</b></td>";
                                    echo "<td style='white-space: nowrap;'>" . htmlspecialchars($row['nama_barang']) . "</td>";
                                    echo "<td style='white-space: nowrap; font-weight:bold; color:#f39c12;'>" . $row['berat'] . " Kg</td>";
                                    
                                    // BUKTI FOTO
                                    echo "<td style='text-align: center;'>";
                                    if (!empty($row['gambar_sampah']) && file_exists('assets/setoran/' . $row['gambar_sampah'])) {
                                        echo "<img src='assets/setoran/" . $row['gambar_sampah'] . "' class='table-bukti' alt='Bukti Sampah' onclick=\"bukaFotoLayarPenuh('assets/setoran/" . $row['gambar_sampah'] . "')\" title='Klik untuk melihat'> ";
                                    } else {
                                        echo "<span style='font-size:11px; color:#bdc3c7; font-style:italic;'>Tidak ada foto</span>";
                                    }
                                    echo "</td>";

                                    echo "<td style='font-weight:bold; color:#2e7d32; white-space: nowrap;'>Rp " . number_format($row['total_harga'], 0, ',', '.') . "</td>";
                                    
                                    // LOGIKA STATUS
                                    $status_sekarang = strtolower($row['status']);
                                    if ($status_sekarang == 'selesai') {
                                        $badge_class = 'badge-selesai'; $status_text = 'Selesai';
                                    } elseif ($status_sekarang == 'disetujui') {
                                        $badge_class = 'badge-disetujui'; $status_text = 'Disetujui';
                                    } elseif ($status_sekarang == 'ditolak') {
                                        $badge_class = 'badge-ditolak'; $status_text = 'Ditolak Admin';
                                    } elseif ($status_sekarang == 'dibatalkan') {
                                        $badge_class = 'badge-dibatalkan'; $status_text = 'Dibatalkan Nasabah';
                                    } else {
                                        $badge_class = 'badge-pending'; $status_text = 'Menunggu';
                                    }
                                    
                                    echo "<td style='text-align: center; white-space: nowrap;'><span class='badge $badge_class'>" . $status_text . "</span></td>";
                                    
                                    // TOMBOL AKSI ADMIN
                                    $id_js = $row['id'];
                                    $nama_js = htmlspecialchars($row['nama_lengkap'], ENT_QUOTES);
                                    $barang_js = htmlspecialchars($row['nama_barang'], ENT_QUOTES);
                                    $berat_raw = $row['berat'];

                                    echo "<td style='text-align: center; vertical-align: middle;'>";
                                    echo "<div class='action-buttons-stack'>";
                                    
                                    if ($status_sekarang == 'pending') {
                                        echo "<a href='transaksi_setoran.php?aksi=setujui&id=$id_js' class='btn-approve'><i class='fa fa-check'></i> Setujui</a>";
                                        echo "<a href='transaksi_setoran.php?aksi=tolak&id=$id_js' class='btn-reject'><i class='fa fa-times'></i> Tolak</a>";
                                    } elseif ($status_sekarang == 'disetujui') {
                                        echo "<button type='button' onclick='showVerifikasiModal($id_js, " . $row['total_harga'] . ")' class='btn-selesai'><i class='fa fa-flag-checkered'></i> Selesai</button>";
                                    } else {
                                        echo "<span style='color:#95a5a6; font-size:12px; font-style:italic;'><i class='fa fa-lock'></i> Terkunci</span>";
                                    }

                                    // Tombol Edit Kg (Bisa ditekan kapan saja kecuali sudah Ditolak / Dibatalkan / Selesai)
                                    if ($status_sekarang != 'ditolak' && $status_sekarang != 'dibatalkan' && $status_sekarang != 'selesai') {
                                        echo "<button type='button' onclick='showEditBeratModal($id_js, \"$nama_js\", \"$barang_js\", \"$berat_raw\")' class='btn-edit-berat'><i class='fa fa-weight-hanging'></i> Edit Kg</button>";
                                    }
                                    
                                    echo "</div>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='9' style='text-align:center; padding:30px; color:#7f8c8d;'>Belum ada permintaan pengajuan transaksi setoran sampah.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="editBeratModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header-green" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); padding: 20px; color: white;">
                <i class="fa fa-weight-hanging" style="font-size: 50px; text-shadow: 0 4px 10px rgba(0,0,0,0.2);"></i>
            </div>
            <div class="modal-body">
                <h3 style="margin-bottom: 20px; text-align:center;">Sesuaikan Timbangan</h3>
                <div class="info-box">
                    <p>Nasabah: <strong style="color: #333;" id="eb_nama"></strong></p>
                    <p>Kategori: <strong style="color: #333;" id="eb_barang"></strong></p>
                    <p>Berat Sebelumnya: <strong style="color: #e67e22;" id="eb_berat_lama"></strong></p>
                </div>
                <form action="transaksi_setoran.php" method="POST">
                    <input type="hidden" name="id_transaksi" id="eb_id">
                    <div style="text-align: left; margin-bottom: 15px;">
                        <label style="display: block; font-size: 13px; color: #555; margin-bottom: 5px; font-weight: bold;">Berat Aktual / Riil (Kg)</label>
                        <input type="number" step="0.01" name="berat_baru" id="eb_input_berat" required placeholder="Contoh: 4.5" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 15px; box-sizing: border-box; background: #f9fbfb;">
                    </div>
                    <p style="font-size:11px; color:#e74c3c; line-height:1.4; text-align:left; margin-bottom:15px; font-weight:bold;">
                        *Sistem akan otomatis menghitung ulang Harga Total dan Saldo sesuai dengan harga pasaran saat ini.
                    </p>
                    <div class="modal-buttons">
                        <button type="button" class="btn-cancel" onclick="closeEditBeratModal()">Batal</button>
                        <button type="submit" name="proses_edit_berat" class="btn-confirm-green" style="background:#e67e22; box-shadow: 0 4px 6px rgba(230,126,34,0.2);">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="lightboxModal" class="modal-overlay">
        <div class="lightbox-content">
            <img id="imgLayarPenuh" src="" alt="Bukti Setoran Sampah">
            <button class="btn-kembali-foto" onclick="tutupFotoLayarPenuh()"><i class="fa fa-arrow-left"></i> Kembali</button>
        </div>
    </div>

    <div id="verifikasiModal" class="modal-overlay">
        <div class="modal-box modal-box-small">
            <div class="modal-header-blue">
                <i class="fa fa-info-circle"></i>
            </div>
            <div class="modal-body">
                <h3>Verifikasi Ulang</h3>
                <p style="margin-bottom: 15px;">Pastikan rincian ini benar sebelum memotong saldo kas Anda.</p>
                
                <div class="info-box">
                    <p>Total Harga Setoran: <strong style="color: #333;" id="textTotalHarga">Rp 0</strong></p>
                    <p>Saldo Kas Admin Saat Ini: <strong style="color: #2ecc71;">Rp <?php echo number_format($saldo_admin_saat_ini, 0, ',', '.'); ?></strong></p>
                </div>

                <p id="pesanWarning" style="color: #e74c3c; font-weight: bold; font-size: 13px; display: none; margin-top:-10px; margin-bottom:15px;">
                    <i class="fa fa-exclamation-triangle"></i> Saldo Anda tidak mencukupi!
                </p>

                <div class="modal-buttons">
                    <button class="btn-cancel" onclick="closeVerifikasiModal()">Batal</button>
                    <button class="btn-confirm-blue" id="btnSelesaikan" onclick="executeSelesai()">Selesaikan & Potong</button>
                </div>
            </div>
        </div>
    </div>

    <div id="logoutModal" class="modal-overlay">
        <div class="modal-box modal-box-small">
            <div class="modal-header-red" style="background: #e74c3c;">
                <img src="assets/logo-lebak.png" alt="Logo Instansi">
            </div>
            <div class="modal-body">
                <h3>Konfirmasi Logout</h3>
                <p>Apakah Anda yakin ingin keluar dari sistem?</p>
                <div class="modal-buttons">
                    <button class="btn-cancel" onclick="closeLogoutModal()">Batal</button>
                    <button class="btn-confirm-red" onclick="window.location.href='logout.php'">Ya, Keluar</button>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($notif_sukses) || !empty($notif_gagal)) : ?>
    <div id="notifModal" class="modal-overlay" style="display: flex;">
        <div class="modal-box modal-box-small">
            <div class="<?php echo !empty($notif_sukses) ? 'modal-header-green' : 'modal-header-red'; ?>">
                <i class="<?php echo !empty($notif_sukses) ? 'fa fa-check-circle' : 'fa fa-times-circle'; ?>"></i>
            </div>
            <div class="modal-body">
                <h3 style="color:<?php echo !empty($notif_sukses) ? '#27ae60' : '#c0392b'; ?>;"><?php echo !empty($notif_sukses) ? 'Berhasil!' : 'Gagal!'; ?></h3>
                <p><?php echo !empty($notif_sukses) ? $notif_sukses : $notif_gagal; ?></p>
                <button class="<?php echo !empty($notif_sukses) ? 'btn-confirm-green' : 'btn-confirm-blue'; ?>" style="width: 100%; <?php echo !empty($notif_gagal) ? 'background:#e74c3c;' : ''; ?>" onclick="tutupNotif()">Tutup</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

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

    function bukaFotoLayarPenuh(urlGambar) {
        document.getElementById('imgLayarPenuh').src = urlGambar;
        document.getElementById('lightboxModal').style.display = 'flex';
    }
    function tutupFotoLayarPenuh() {
        document.getElementById('lightboxModal').style.display = 'none';
        document.getElementById('imgLayarPenuh').src = '';
    }

    function showEditBeratModal(id, nama, barang, beratLama) {
        document.getElementById('eb_id').value = id;
        document.getElementById('eb_nama').innerText = nama;
        document.getElementById('eb_barang').innerText = barang;
        document.getElementById('eb_berat_lama').innerText = beratLama + " Kg";
        document.getElementById('eb_input_berat').value = beratLama;
        document.getElementById('editBeratModal').style.display = 'flex';
    }
    function closeEditBeratModal() {
        document.getElementById('editBeratModal').style.display = 'none';
    }

    let idTransaksiAktif = "";
    let saldoAdminJS = <?php echo $saldo_admin_saat_ini; ?>;

    function showVerifikasiModal(id, totalHarga) {
        idTransaksiAktif = 'transaksi_setoran.php?aksi=selesai&id=' + id;
        
        let formatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });
        document.getElementById('textTotalHarga').innerText = formatter.format(totalHarga);
        
        let btnSelesaikan = document.getElementById('btnSelesaikan');
        let pesanWarning = document.getElementById('pesanWarning');
        
        if (saldoAdminJS < totalHarga) {
            btnSelesaikan.style.opacity = '0.5';
            btnSelesaikan.style.cursor = 'not-allowed';
            btnSelesaikan.disabled = true;
            pesanWarning.style.display = 'block';
        } else {
            btnSelesaikan.style.opacity = '1';
            btnSelesaikan.style.cursor = 'pointer';
            btnSelesaikan.disabled = false;
            pesanWarning.style.display = 'none';
        }
        document.getElementById('verifikasiModal').style.display = 'flex';
    }
    function closeVerifikasiModal() { document.getElementById('verifikasiModal').style.display = 'none'; }
    function executeSelesai() { window.location.href = idTransaksiAktif; }

    function showLogoutModal() { document.getElementById('logoutModal').style.display = 'flex'; }
    function closeLogoutModal() { document.getElementById('logoutModal').style.display = 'none'; }
    
    function tutupNotif() {
        document.getElementById('notifModal').style.display = 'none';
        var clean_url = window.location.pathname;
        setTimeout(function() { window.location.href = clean_url; }, 100);
    }

    if ( window.history.replaceState ) {
        window.history.replaceState( null, null, window.location.href );
    }
</script>
</body>
</html>