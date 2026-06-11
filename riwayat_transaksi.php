<?php
session_start();

// Mencegah browser menyimpan cache
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

// Cek session nasabah
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'nasabah') {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

// Paksa PHP menggunakan waktu Indonesia agar fungsi date() di tabel akurat
date_default_timezone_set('Asia/Jakarta');

$username_aktif = $_SESSION['username'];
$notif_sukses = "";
$notif_gagal = "";

// ==================================================================
// LOGIKA BATALKAN SETORAN SAMPAH
// ==================================================================
if (isset($_POST['batalkan_setoran'])) {
    $id_setoran = mysqli_real_escape_string($koneksi, $_POST['id_setoran']);
    
    // Verifikasi apakah transaksi ini milik user aktif & statusnya masih pending
    $q_cek = mysqli_query($koneksi, "SELECT status FROM transaksi_setoran WHERE id = '$id_setoran' AND username_nasabah = '$username_aktif'");
    
    if (mysqli_num_rows($q_cek) > 0) {
        $d_setoran = mysqli_fetch_assoc($q_cek);
        if ($d_setoran['status'] == 'pending') {
            
            // Ubah status transaksi menjadi dibatalkan
            mysqli_query($koneksi, "UPDATE transaksi_setoran SET status = 'dibatalkan' WHERE id = '$id_setoran'");
            
            $notif_sukses = "Transaksi setoran sampah berhasil dibatalkan.";
        } else {
            $notif_gagal = "Gagal membatalkan! Transaksi ini mungkin sudah diproses oleh Admin.";
        }
    } else {
        $notif_gagal = "Data transaksi tidak ditemukan.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Riwayat Transaksi - Bank Sampah Induk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar Mini */
        .sidebar { width: 85px; background-color: #2c3e50; color: white; display: flex; flex-direction: column; z-index: 1001;}
        
        /* DIPERBAIKI: Kunci tinggi logo agar persis sejajar dengan header kanan */
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
        .menu a { display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 12px 5px; color: #bdc3c7; text-decoration: none; transition: 0.3s; border-left: 4px solid transparent; cursor: pointer;}
        .menu a:hover, .menu a.active { background-color: #34495e; color: white; border-left-color: #1abc9c; }
        .menu a i { font-size: 22px; margin-bottom: 4px; }
        .menu a span.menu-text { font-size: 10px; text-align: center; line-height: 1.2; font-weight: 600;}
        
        .main-content { flex: 1; display: flex; flex-direction: column; position: relative;}
        
        /* DIPERBAIKI: Kunci tinggi header agar persis sejajar dengan logo kiri */
        .header { 
            background-color: #1abc9c; 
            padding: 0 30px; 
            height: 60px; /* Tinggi dikunci */
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); 
            z-index: 10;
            box-sizing: border-box;
        }
        .header h3 { margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 18px;}
        
        /* Tombol Hamburger & Latar Gelap */
        .mobile-menu-btn { display: none; font-size: 20px; color: white; cursor: pointer;}
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; }

        /* KUNCI AREA KONTEN AGAR TIDAK MELEBAR */
        .content { 
            padding: 30px; 
            flex: 1; 
            overflow-y: auto; 
            overflow-x: hidden; 
            width: 100%;
            box-sizing: border-box;
        }
        
        .card { 
            background-color: white; 
            padding: 25px 30px; 
            border-radius: 12px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
            margin-bottom: 30px; 
            box-sizing: border-box;
            width: 100%;
            max-width: 100%;
            overflow: hidden; 
        }
        
        .card-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 15px;}
        .card-header .icon-box { width: 45px; height: 45px; border-radius: 10px; display: flex; justify-content: center; align-items: center; font-size: 20px; color: white; flex-shrink: 0;}
        .icon-green { background: linear-gradient(135deg, #1abc9c, #16a085); }
        .icon-blue { background: linear-gradient(135deg, #3498db, #2980b9); }
        .card-header h2 { margin: 0; color: #2c3e50; font-size: 20px;}
        .card-header p { margin: 5px 0 0 0; color: #7f8c8d; font-size: 13px;}

        /* STYLING TABEL DENGAN SCROLL KETAT */
        .table-responsive { 
            width: 100%; 
            max-width: 100%;
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch; 
            display: block; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 14px; 
            min-width: 650px; 
            white-space: nowrap; 
        }
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; vertical-align: middle;}
        table th { background-color: #f8f9fa; color: #555; font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px;}
        table tr:hover { background-color: #f9fbfb; }
        
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; color: white; display: inline-block;}
        .badge-pending { background-color: #f39c12; }
        .badge-disetujui { background-color: #3498db; }
        .badge-selesai { background-color: #2ecc71; }
        .badge-ditolak { background-color: #e74c3c; }
        .badge-dibatalkan { background-color: #95a5a6; }

        /* TOMBOL BATAL MUNGIL DI TABEL */
        .btn-batal-sm { background-color: #e74c3c; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 11px; cursor: pointer; transition: 0.2s; font-weight: bold;}
        .btn-batal-sm:hover { background-color: #c0392b; transform: translateY(-1px); }

        /* Modal Global */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 350px; border-radius: 15px; overflow: hidden; box-shadow: 0 15px 30px rgba(0,0,0,0.3); text-align: center; animation: popIn 0.3s ease-out; }
        .modal-header-red { background: #e74c3c; padding: 25px 20px; color: white;}
        .modal-header-red img { width: 80px; height: auto; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2)); }
        .modal-header-green { background: #2ecc71; padding: 25px 20px; color: white;}
        .modal-body { padding: 25px 30px 30px; background: #fff; }
        .modal-body h3 { margin: 0 0 10px; color: #333; font-size: 20px; }
        .modal-body p { color: #666; margin-bottom: 25px; font-size: 14px; line-height: 1.5; }
        .modal-buttons { display: flex; gap: 15px; justify-content: center; }
        .modal-buttons button { padding: 10px 20px; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; flex: 1; }
        .btn-cancel { background: #e0e0e0; color: #555; }
        .btn-cancel:hover { background: #d5d5d5; }
        .btn-confirm { background: #c0392b; color: white; box-shadow: 0 4px 6px rgba(231,76,60,0.2);}
        .btn-confirm:hover { background: #a53125; transform: translateY(-2px);}
        .btn-confirm-green { background: #27ae60; color: white; box-shadow: 0 4px 6px rgba(39, 174, 96,0.2);}
        .btn-confirm-green:hover { background: #2ecc71; transform: translateY(-2px);}
        
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
            .sidebar-logo-text { display: inline; font-size: 18px; margin-left: 10px; font-family: 'Segoe UI', sans-serif;}
            
            .menu { padding-top: 15px; }
            .menu a { flex-direction: row; justify-content: flex-start; padding: 15px 25px; }
            .menu a i { margin-right: 15px; margin-bottom: 0; font-size: 20px;}
            .menu a span.menu-text { font-size: 15px; font-weight: normal;}

            .content { padding: 15px; display: block; }
            .card { 
                padding: 20px; 
                margin-bottom: 20px; 
                border-radius: 12px;
                width: 100%;
                max-width: calc(100vw - 30px); 
            }
            .card-header h2 { font-size: 18px; }
            .card-header p { font-size: 12px; line-height: 1.4;}
            .card-header .icon-box { width: 35px; height: 35px; font-size: 16px; }
            
            .table-responsive { 
                overflow-x: auto !important; 
                margin-top: 10px;
                border: 1px solid #f1f1f1;
            }
            
            .modal-box { width: 92%; max-width: 380px; }
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
            <a href="riwayat_transaksi.php" class="active">
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
                Riwayat Transaksi
            </h3>
        </div>
        
        <div class="content">
            
            <!-- TABEL 1: RIWAYAT SETORAN SAMPAH -->
            <div class="card">
                <div class="card-header">
                    <div class="icon-box icon-green"><i class="fa fa-box-open"></i></div>
                    <div>
                        <h2>Riwayat Setoran Sampah</h2>
                        <p>Daftar seluruh transaksi sampah yang pernah Anda setorkan ke Bank Sampah.</p>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal & Waktu</th>
                                <th>Kategori Sampah</th>
                                <th>Berat</th>
                                <th>Total Dana</th>
                                <th>Status</th>
                                <th style="text-align:center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $query_setoran = "SELECT ts.*, k.nama_barang 
                                              FROM transaksi_setoran ts
                                              JOIN kategori_sampah k ON ts.id_kategori = k.id
                                              WHERE ts.username_nasabah = '$username_aktif'
                                              ORDER BY ts.tanggal DESC";
                            $result_setoran = mysqli_query($koneksi, $query_setoran);
                            $no = 1;

                            if (mysqli_num_rows($result_setoran) > 0) {
                                while ($row = mysqli_fetch_assoc($result_setoran)) {
                                    echo "<tr>";
                                    echo "<td>" . $no++ . "</td>";
                                    echo "<td>" . date('d M Y, H:i', strtotime($row['tanggal'])) . "</td>";
                                    echo "<td><b>" . htmlspecialchars($row['nama_barang']) . "</b></td>";
                                    echo "<td>" . $row['berat'] . " Kg</td>";
                                    echo "<td style='color:#2e7d32; font-weight:bold;'>Rp " . number_format($row['total_harga'], 0, ',', '.') . "</td>";
                                    
                                    if ($row['status'] == 'selesai') {
                                        $badge_class = 'badge-selesai'; $text = 'Selesai';
                                    } elseif ($row['status'] == 'disetujui') {
                                        $badge_class = 'badge-disetujui'; $text = 'Disetujui';
                                    } elseif ($row['status'] == 'ditolak') {
                                        $badge_class = 'badge-ditolak'; $text = 'Ditolak';
                                    } elseif ($row['status'] == 'dibatalkan') {
                                        $badge_class = 'badge-dibatalkan'; $text = 'Dibatalkan';
                                    } else {
                                        $badge_class = 'badge-pending'; $text = 'Menunggu';
                                    }
                                    echo "<td><span class='badge $badge_class'>" . $text . "</span></td>";
                                    
                                    // TOMBOL BATAL KHUSUS STATUS PENDING
                                    echo "<td style='text-align:center;'>";
                                    if ($row['status'] == 'pending') {
                                        $id_setoran_js = $row['id'];
                                        echo "<button class='btn-batal-sm' onclick='showBatalSetoranModal($id_setoran_js)' title='Batalkan Setoran'><i class='fa fa-times'></i> Batal</button>";
                                    } else {
                                        echo "<span style='color:#bdc3c7; font-size:12px;'>-</span>";
                                    }
                                    echo "</td>";
                                    
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' style='text-align:center; padding:30px; color:#95a5a6;'>Belum ada riwayat setoran sampah.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TABEL 2: RIWAYAT PENARIKAN TUNAI -->
            <div class="card">
                <div class="card-header">
                    <div class="icon-box icon-blue"><i class="fa fa-money-bill-transfer"></i></div>
                    <div>
                        <h2>Riwayat Tarik Tunai</h2>
                        <p>Pantau status permintaan penarikan saldo ke Rekening / E-Wallet Anda.</p>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal Request</th>
                                <th>Metode</th>
                                <th>Nomor Tujuan</th>
                                <th>Nominal Ditarik</th>
                                <th>Status Penarikan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $query_tarik = "SELECT * FROM transaksi_tarik 
                                            WHERE username_nasabah = '$username_aktif' 
                                            ORDER BY tanggal DESC";
                            $result_tarik = mysqli_query($koneksi, $query_tarik);
                            $no2 = 1;

                            if (mysqli_num_rows($result_tarik) > 0) {
                                while ($row2 = mysqli_fetch_assoc($result_tarik)) {
                                    echo "<tr>";
                                    echo "<td>" . $no2++ . "</td>";
                                    echo "<td>" . date('d M Y, H:i', strtotime($row2['tanggal'])) . "</td>";
                                    echo "<td><span style='background:#ecf0f1; padding:3px 8px; border-radius:4px; font-weight:bold; font-size:12px; color:#2c3e50;'>" . htmlspecialchars($row2['metode']) . "</span></td>";
                                    echo "<td>" . htmlspecialchars($row2['nomor_tujuan']) . "</td>";
                                    echo "<td style='color:#e74c3c; font-weight:bold;'>Rp " . number_format($row2['nominal'], 0, ',', '.') . "</td>";
                                    
                                    if ($row2['status'] == 'selesai') {
                                        $badge_class = 'badge-selesai'; $text = 'Berhasil Ditransfer';
                                    } elseif ($row2['status'] == 'ditolak') {
                                        $badge_class = 'badge-ditolak'; $text = 'Gagal/Ditolak';
                                    } elseif ($row2['status'] == 'dibatalkan') {
                                        $badge_class = 'badge-dibatalkan'; $text = 'Dibatalkan';
                                    } else {
                                        $badge_class = 'badge-pending'; $text = 'Menunggu Admin';
                                    }
                                    
                                    echo "<td><span class='badge $badge_class'>" . $text . "</span></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='6' style='text-align:center; padding:30px; color:#95a5a6;'>Belum ada riwayat penarikan uang.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- MODAL BATALKAN SETORAN -->
    <div id="batalSetoranModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header-red">
                <i class="fa fa-trash-alt" style="font-size: 50px;"></i>
            </div>
            <div class="modal-body">
                <h3>Batalkan Setoran?</h3>
                <p>Apakah Anda yakin ingin membatalkan transaksi setoran sampah ini?</p>
                <form method="POST" action="">
                    <input type="hidden" name="id_setoran" id="input_id_setoran">
                    <div class="modal-buttons">
                        <button type="button" class="btn-cancel" onclick="document.getElementById('batalSetoranModal').style.display='none'">Tutup</button>
                        <button type="submit" name="batalkan_setoran" class="btn-confirm">Ya, Batalkan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL NOTIFIKASI -->
    <?php if (!empty($notif_sukses) || !empty($notif_gagal)) : ?>
    <div id="notifModal" class="modal-overlay" style="display: flex;">
        <div class="modal-box">
            <div class="<?php echo !empty($notif_sukses) ? 'modal-header-green' : 'modal-header-red'; ?>">
                <i class="<?php echo !empty($notif_sukses) ? 'fa fa-check-circle' : 'fa fa-times-circle'; ?>" style="font-size: 50px;"></i>
            </div>
            <div class="modal-body">
                <h3><?php echo !empty($notif_sukses) ? 'Berhasil!' : 'Gagal!'; ?></h3>
                <p><?php echo !empty($notif_sukses) ? $notif_sukses : $notif_gagal; ?></p>
                <div class="modal-buttons">
                    <button class="<?php echo !empty($notif_sukses) ? 'btn-confirm-green' : 'btn-confirm'; ?>" style="width: 100%;" onclick="tutupNotif()">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Logout -->
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

    // FUNGSI MEMANGGIL MODAL BATAL SETORAN
    function showBatalSetoranModal(id) {
        document.getElementById('input_id_setoran').value = id;
        document.getElementById('batalSetoranModal').style.display = 'flex';
    }

    // FUNGSI MODAL NOTIFIKASI
    function tutupNotif() {
        document.getElementById('notifModal').style.display = 'none';
        window.location.href = window.location.pathname; // Bersihkan URL dari submit form
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