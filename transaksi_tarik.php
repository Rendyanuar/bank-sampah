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
$notif_sukses = "";
$notif_gagal = "";

// Ambil Foto Profil Admin untuk Header Dropdown
$q_foto = mysqli_query($koneksi, "SELECT foto_profil FROM users WHERE username = '".$_SESSION['username']."'");
$d_foto = mysqli_fetch_assoc($q_foto);
$path_foto_header = (!empty($d_foto['foto_profil']) && file_exists('assets/profil/' . $d_foto['foto_profil'])) 
                    ? 'assets/profil/' . $d_foto['foto_profil'] 
                    : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['nama']) . '&background=1abc9c&color=fff';

// Ambil Saldo Admin Saat Ini (Untuk Pengecekan di Modal Verifikasi)
$q_saldo_admin = mysqli_query($koneksi, "SELECT saldo FROM users WHERE role = 'admin' LIMIT 1");
$d_saldo_admin = mysqli_fetch_assoc($q_saldo_admin);
$saldo_admin_saat_ini = ($d_saldo_admin['saldo'] !== null) ? $d_saldo_admin['saldo'] : 0;

// PROSES JIKA ADMIN MENEKAN TOMBOL SETUJUI / TOLAK / SELESAI
if (isset($_GET['aksi']) && isset($_GET['id'])) {
    $id_transaksi = mysqli_real_escape_string($koneksi, $_GET['id']);
    $aksi = $_GET['aksi'];

    // Ambil data penarikan untuk aksi tolak (mengembalikan saldo)
    $q_trans = mysqli_query($koneksi, "SELECT * FROM transaksi_tarik WHERE id = '$id_transaksi'");
    $d_trans = mysqli_fetch_assoc($q_trans);

    if ($d_trans) {
        $nominal = $d_trans['nominal'];
        $user_nasabah = $d_trans['username_nasabah'];

        if ($aksi == 'setujui') {
            // Mengubah status jadi disetujui (Admin bersiap transfer)
            $query_status = "UPDATE transaksi_tarik SET status = 'disetujui' WHERE id = '$id_transaksi'";
            if (mysqli_query($koneksi, $query_status)) {
                $notif_sukses = "Penarikan disetujui! Silakan lakukan transfer ke rekening nasabah, lalu klik 'Selesaikan Transfer'.";
            }
        } elseif ($aksi == 'tolak') {
            // JIKA DITOLAK: Saldo yang tadi dipotong harus dikembalikan ke nasabah!
            if ($d_trans['status'] == 'pending') {
                mysqli_query($koneksi, "UPDATE users SET saldo = saldo + $nominal WHERE username = '$user_nasabah'");
                mysqli_query($koneksi, "UPDATE transaksi_tarik SET status = 'ditolak' WHERE id = '$id_transaksi'");
                $notif_sukses = "Penarikan ditolak. Saldo sebesar Rp " . number_format($nominal, 0, ',', '.') . " telah dikembalikan ke nasabah.";
            }
        } elseif ($aksi == 'selesai') {
            // TAHAP FINAL: Admin sudah mentransfer uang
            if (strtolower(trim($d_trans['status'])) == 'disetujui') {
                // Pengecekan kembali apakah saldo admin cukup
                if ($saldo_admin_saat_ini >= $nominal) {
                    mysqli_query($koneksi, "UPDATE transaksi_tarik SET status = 'selesai' WHERE id = '$id_transaksi'");
                    $notif_sukses = "Transaksi Selesai! Uang telah berhasil dicairkan ke nasabah.";
                } else {
                    $notif_gagal = "Gagal memproses! Saldo kas Admin tidak mencukupi untuk transfer ini.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pencairan Saldo - Bank Sampah Induk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar Mini (Disetarakan 85px) */
        .sidebar { width: 85px; background-color: #2c3e50; color: white; display: flex; flex-direction: column; z-index: 1001; }
        
        /* Tinggi logo dikunci presisi 60px */
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
        .menu a { display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 12px 5px; color: #bdc3c7; text-decoration: none; transition: 0.3s; border-left: 4px solid transparent; cursor: pointer; position: relative;}
        .menu a:hover, .menu a.active { background-color: #34495e; color: white; border-left-color: #1abc9c; }
        .menu a i { font-size: 22px; margin-bottom: 4px; }
        .menu a span.menu-text { font-size: 10px; text-align: center; line-height: 1.2; font-weight: 600;}
        
        .main-content { flex: 1; display: flex; flex-direction: column; position: relative;}
        
        /* HEADER WARNA HIJAU 60px */
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
        
        /* Tombol Hamburger & Latar Gelap HP */
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

        .content { padding: 30px; flex: 1; overflow-y: auto; overflow-x: hidden; width: 100%; box-sizing: border-box;}
        
        .card { background-color: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; max-width: 100%; box-sizing: border-box; overflow: hidden;}
        .card-header { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;}
        .card-header h2 { margin: 0; color: #1abc9c; font-size: 22px;}
        
        /* BUNGKUS SCROLL TABEL */
        .table-responsive { 
            width: 100%; 
            max-width: 100%;
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch; 
            display: block; 
        }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; min-width: 900px; white-space: nowrap;}
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; vertical-align: middle; }
        table th { background-color: #f8f9fa; color: #333; font-weight: 600; text-transform: uppercase; font-size: 13px; letter-spacing: 0.5px;}
        table tr:hover { background-color: #f1fcf9; transition: 0.2s;}
        
        /* Badges untuk Status */
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; color: white !important; display: inline-block; text-align: center; }
        .badge-pending { background-color: #f39c12 !important; }    
        .badge-disetujui { background-color: #3498db !important; }  
        .badge-selesai { background-color: #2ecc71 !important; }    
        .badge-ditolak { background-color: #e74c3c !important; }    

        /* Tombol Aksi - Lebar diset otomatis ke panjang teks (max-content) */
        .action-buttons-stack { display: flex; flex-direction: column; gap: 8px; width: max-content; min-width: 130px; margin: 0 auto; }
        .btn-approve, .btn-reject, .btn-selesai { width: 100%; box-sizing: border-box; white-space: nowrap; padding: 8px 12px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; font-size: 13px; font-weight: bold; transition: 0.2s; display: block; text-align: center; color: white; }
        .btn-approve { background-color: #2ecc71; }
        .btn-approve:hover { background-color: #27ae60; }
        .btn-reject { background-color: #e74c3c; }
        .btn-reject:hover { background-color: #c0392b; }
        .btn-selesai { background-color: #3498db; }
        .btn-selesai:hover { background-color: #2980b9; }

        /* Detail Rekening Box */
        .rek-box { background: #f8f9fa; border: 1px dashed #bdc3c7; padding: 8px 12px; border-radius: 6px; font-size: 13px; display: inline-block;}
        .rek-box strong { color: #2c3e50; font-size: 14px;}

        /* Modal Global */
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

        /* STRUK / INFO BOX DI MODAL VERIFIKASI */
        .info-box { background: #f8f9fa; border: 1px solid #eee; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: left; }
        .info-box p { margin: 0 0 8px 0; font-size: 14px; color: #7f8c8d; }
        .info-box p:last-child { margin-bottom: 0; }
        .info-box strong { font-size: 16px; display: block; margin-top: 3px; }

        /* =======================================================
           RESPONSIVE MOBILE (HP)
           ======================================================= */
        @media screen and (max-width: 768px) {
            .mobile-menu-btn { display: block; }
            .header { padding: 0 20px; }
            .header h3 { font-size: 16px; }

            /* Sembunyikan tulisan admin */
            .user-info span, .user-info b { display: none; }

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

            .content { padding: 15px; display: block; }
            .card { padding: 20px; margin-bottom: 20px; border-radius: 12px; width: 100%; max-width: calc(100vw - 30px); }
            .card-header h2 { font-size: 18px; }
            
            .table-responsive { overflow-x: auto !important; margin-top: 10px; border: 1px solid #f1f1f1; }

            /* Penyesuaian Modal Mungil di HP */
            .modal-box-small { max-width: 280px !important; border-radius: 12px; }
            .modal-box-small .modal-header-green, .modal-box-small .modal-header-red, .modal-box-small .modal-header-blue { padding: 15px; }
            .modal-box-small .modal-header-green i, .modal-box-small .modal-header-red i, .modal-box-small .modal-header-blue i { font-size: 40px; }
            .modal-box-small .modal-header-red img { width: 60px; }
            .modal-box-small .modal-body { padding: 15px 20px 20px 20px; }
            .modal-box-small .modal-body h3 { font-size: 18px; margin-bottom: 8px;}
            .modal-box-small .modal-body p { font-size: 13px; margin-bottom: 15px; line-height: 1.4; }
            
            /* Struk Khusus HP agar muat di kotak kecil */
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
                <i class="fa fa-home"></i>
                <span class="menu-text">Beranda</span>
            </a>
            <a href="data_nasabah.php" title="Data Nasabah">
                <i class="fa fa-users"></i>
                <span class="menu-text">Nasabah</span>
            </a>
            <a href="kategori_sampah.php" title="Kategori Sampah">
                <i class="fa fa-trash"></i>
                <span class="menu-text">Kategori</span>
            </a>
            <a href="transaksi_setoran.php" title="Transaksi Setoran">
                <i class="fa fa-exchange-alt"></i>
                <span class="menu-text">Setoran</span>
            </a>
            <a href="transaksi_tarik.php" class="active" title="Pencairan Saldo">
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
            <h3><i class="fa fa-bars mobile-menu-btn" onclick="toggleMobileMenu()"></i> Pencairan Saldo</h3>
            
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
            <div style="background-color: #e8f6f3; padding: 15px 25px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #bde6de; display: flex; flex-wrap: wrap; gap: 20px; align-items: center; font-size: 13px; color: #2c3e50; box-shadow: 0 4px 10px rgba(0,0,0,0.03); width: 100%; box-sizing: border-box;">
                <strong style="font-size: 14px; color: #16a085;"><i class="fa fa-info-circle"></i> Arti Warna Status:</strong>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 14px; height: 14px; background-color: #f39c12; border-radius: 4px;"></span> 
                    <span><b>Menunggu</b> (Perlu Persetujuan)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 14px; height: 14px; background-color: #3498db; border-radius: 4px;"></span> 
                    <span><b>Disetujui</b> (Lakukan Transfer)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 14px; height: 14px; background-color: #2ecc71; border-radius: 4px;"></span> 
                    <span><b>Selesai</b> (Uang Telah Dikirim)</span>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2>Daftar Permintaan Pencairan</h2>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="white-space: nowrap;">No</th>
                                <th style="white-space: nowrap;">Tanggal Request</th>
                                <th style="white-space: nowrap;">Nama Nasabah</th>
                                <th style="white-space: nowrap;">Nominal Tarik</th>
                                <th style="white-space: nowrap;">Tujuan Transfer</th>
                                <th style="white-space: nowrap; text-align: center;">Status</th>
                                <th style="text-align: center; white-space: nowrap;">Tindakan Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $query = "SELECT tt.*, u.nama_lengkap 
                                      FROM transaksi_tarik tt
                                      JOIN users u ON tt.username_nasabah = u.username
                                      ORDER BY tt.tanggal DESC";
                            
                            $result = mysqli_query($koneksi, $query);
                            $no = 1;

                            if (mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    echo "<tr>";
                                    echo "<td>" . $no++ . "</td>";
                                    echo "<td style='white-space: nowrap;'>" . date('d M Y, H:i', strtotime($row['tanggal'])) . " WIB</td>";
                                    echo "<td style='white-space: nowrap;'><b>" . htmlspecialchars($row['nama_lengkap']) . "</b></td>";
                                    echo "<td style='font-weight:bold; color:#e74c3c; white-space: nowrap;'>Rp " . number_format($row['nominal'], 0, ',', '.') . "</td>";
                                    
                                    echo "<td>
                                            <div class='rek-box'>
                                                <i class='fa fa-wallet' style='color:#3498db;'></i> " . htmlspecialchars($row['metode']) . "<br>
                                                <strong style='font-size: 15px;'>" . htmlspecialchars($row['nomor_tujuan']) . "</strong>
                                            </div>
                                          </td>";
                                    
                                    $status_db = strtolower(trim($row['status']));
                                    
                                    if ($status_db == 'selesai') {
                                        $badge_class = 'badge-selesai'; $status_text = 'Selesai';
                                    } elseif ($status_db == 'disetujui') {
                                        $badge_class = 'badge-disetujui'; $status_text = 'Disetujui';
                                    } elseif ($status_db == 'ditolak') {
                                        $badge_class = 'badge-ditolak'; $status_text = 'Ditolak';
                                    } else {
                                        $badge_class = 'badge-pending'; $status_text = 'Menunggu';
                                    }
                                    
                                    echo "<td style='text-align: center; white-space: nowrap;'><span class='badge $badge_class'>" . $status_text . "</span></td>";
                                    
                                    echo "<td style='text-align: center; vertical-align: middle;'>";
                                    
                                    if ($status_db == 'pending' || $status_db == 'menunggu') {
                                        echo "<div class='action-buttons-stack'>";
                                        echo "<a href='transaksi_tarik.php?aksi=setujui&id=" . $row['id'] . "' class='btn-approve'><i class='fa fa-check'></i> Setujui</a>";
                                        echo "<a href='transaksi_tarik.php?aksi=tolak&id=" . $row['id'] . "' class='btn-reject' onclick='return confirm(\"Tolak penarikan ini? Saldo akan dikembalikan ke nasabah.\")'><i class='fa fa-times'></i> Tolak</a>";
                                        echo "</div>";
                                    } elseif ($status_db == 'disetujui') {
                                        echo "<div class='action-buttons-stack'>";
                                        echo "<a href='javascript:void(0);' onclick='showVerifikasiModal(" . $row['id'] . ", " . $row['nominal'] . ", \"" . htmlspecialchars(addslashes($row['metode'])) . "\", \"" . htmlspecialchars(addslashes($row['nomor_tujuan'])) . "\", \"" . htmlspecialchars(addslashes($row['nama_lengkap'])) . "\")' class='btn-selesai'><i class='fa fa-paper-plane'></i> Selesaikan Transfer</a>";
                                        echo "</div>";
                                    } else {
                                        echo "<span style='color:#95a5a6; font-size:12px; font-style:italic;'><i class='fa fa-lock'></i> Selesai</span>";
                                    }
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' style='text-align:center; padding:30px; color:#7f8c8d;'>Belum ada permintaan penarikan uang dari nasabah.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
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

    <div id="verifikasiModal" class="modal-overlay">
        <div class="modal-box modal-box-small">
            <div class="modal-header-blue">
                <i class="fa fa-paper-plane"></i>
            </div>
            <div class="modal-body">
                <h3>Verifikasi Transfer</h3>
                <p style="margin-bottom: 15px;">Pastikan Anda telah transfer ke rekening berikut sebelum menyelesaikan transaksi.</p>
                
                <div class="info-box">
                    <p>Nama: <strong id="strukNama" style="color: #333;">-</strong></p>
                    <p>Tujuan: <strong id="strukTujuan" style="color: #3498db;">-</strong></p>
                    <hr style="border: 0; border-top: 1px dashed #ccc; margin: 10px 0;">
                    <p>Nominal: <strong id="strukNominal" style="color: #e74c3c;">Rp 0</strong></p>
                    <p style="margin-top: 10px; font-size: 11px;">Saldo Anda Saat Ini:<br> <b style="color: #2ecc71;">Rp <?php echo number_format($saldo_admin_saat_ini, 0, ',', '.'); ?></b></p>
                </div>

                <p id="pesanWarning" style="color: #e74c3c; font-weight: bold; font-size: 13px; display: none; margin-top:-10px; margin-bottom:15px;">
                    <i class="fa fa-exclamation-triangle"></i> Saldo Kas Anda Kurang!
                </p>

                <div class="modal-buttons">
                    <button class="btn-cancel" onclick="closeVerifikasiModal()">Batal</button>
                    <button class="btn-confirm-blue" id="btnSelesaikan" onclick="executeSelesai()">Selesaikan</button>
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
                <h3><?php echo !empty($notif_sukses) ? 'Berhasil!' : 'Gagal!'; ?></h3>
                <p><?php echo !empty($notif_sukses) ? $notif_sukses : $notif_gagal; ?></p>
                <button class="<?php echo !empty($notif_sukses) ? 'btn-confirm-green' : 'btn-confirm-red'; ?>" style="width: 100%;" onclick="tutupNotif()">Tutup</button>
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

    // --- FUNGSI DROPDOWN PROFIL ---
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

    // FUNGSI LOGOUT & NOTIFIKASI
    function showLogoutModal() { document.getElementById('logoutModal').style.display = 'flex'; }
    function closeLogoutModal() { document.getElementById('logoutModal').style.display = 'none'; }
    
    function tutupNotif() {
        document.getElementById('notifModal').style.display = 'none';
        var clean_url = window.location.pathname;
        setTimeout(function() { window.location.href = clean_url; }, 100);
    }

    // --- FUNGSI MODAL VERIFIKASI SELESAI (CEK SALDO ADMIN) ---
    let idTransaksiAktif = "";
    let saldoAdminJS = <?php echo $saldo_admin_saat_ini; ?>;

    function showVerifikasiModal(id, nominal, metode, tujuan, nama) {
        idTransaksiAktif = 'transaksi_tarik.php?aksi=selesai&id=' + id;
        
        // Format uang (Rupiah) di JS
        let formatter = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 });
        
        // Masukkan data ke dalam Struk UI
        document.getElementById('strukNama').innerText = nama;
        document.getElementById('strukTujuan').innerText = metode + " (" + tujuan + ")";
        document.getElementById('strukNominal').innerText = formatter.format(nominal);
        
        let btnSelesaikan = document.getElementById('btnSelesaikan');
        let pesanWarning = document.getElementById('pesanWarning');
        
        // Cek jika saldo admin kurang dari yang ditarik
        if (saldoAdminJS < nominal) {
            btnSelesaikan.style.opacity = '0.5';
            btnSelesaikan.style.cursor = 'not-allowed';
            btnSelesaikan.disabled = true; // MATIKAN TOMBOL
            pesanWarning.style.display = 'block'; // MUNCULKAN TEKS PERINGATAN
        } else {
            btnSelesaikan.style.opacity = '1';
            btnSelesaikan.style.cursor = 'pointer';
            btnSelesaikan.disabled = false; // HIDUPKAN TOMBOL
            pesanWarning.style.display = 'none'; // SEMBUNYIKAN PERINGATAN
        }

        document.getElementById('verifikasiModal').style.display = 'flex';
    }

    function closeVerifikasiModal() {
        document.getElementById('verifikasiModal').style.display = 'none';
    }

    function executeSelesai() {
        window.location.href = idTransaksiAktif;
    }
</script>

</body>
</html>