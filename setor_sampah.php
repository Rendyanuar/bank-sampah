<?php
session_start();

// Mencegah browser menyimpan cache
header("Cache-Control: no-cache, no-store, must-revalidate"); 
header("Pragma: no-cache"); 
header("Expires: 0"); 

// Cek session
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'nasabah') {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

$username_aktif = $_SESSION['username'];
$notif_sukses = "";
$notif_gagal = "";

// PROSES SUBMIT SETORAN SAMPAH
if (isset($_POST['submit_setoran'])) {
    $id_kategori = (int)$_POST['id_kategori'];
    $berat = (float)$_POST['berat'];
    
    // Validasi berat
    if ($berat <= 0) {
        $notif_gagal = "Berat sampah harus lebih dari 0 Kg.";
    } elseif ($id_kategori == 0) {
        $notif_gagal = "Kategori sampah tidak valid.";
    } else {
        // Ambil harga per Kg dari kategori yang dipilih
        $q_harga = mysqli_query($koneksi, "SELECT harga_barang FROM kategori_sampah WHERE id = $id_kategori");
        $d_harga = mysqli_fetch_assoc($q_harga);
        
        if ($d_harga) {
            $total_harga = $berat * $d_harga['harga_barang'];
            $nama_file_gambar = NULL;

            // Logika Upload Foto Bukti Sampah
            if (!empty($_FILES['gambar_sampah']['name'])) {
                $ekstensi_diperbolehkan = array('png', 'jpg', 'jpeg');
                $nama_foto = $_FILES['gambar_sampah']['name'];
                $x = explode('.', $nama_foto);
                $ekstensi = strtolower(end($x));
                $ukuran = $_FILES['gambar_sampah']['size'];
                $file_tmp = $_FILES['gambar_sampah']['tmp_name'];

                if (in_array($ekstensi, $ekstensi_diperbolehkan) === true) {
                    if ($ukuran < 2048000) { // Maksimal 2MB
                        $nama_file_gambar = 'sampah_' . $username_aktif . '_' . time() . '.' . $ekstensi;
                        move_uploaded_file($file_tmp, 'assets/setoran/' . $nama_file_gambar);
                    } else {
                        $notif_gagal = "Ukuran gambar terlalu besar! (Maksimal 2 MB)";
                    }
                } else {
                    $notif_gagal = "Ekstensi file tidak valid! (Hanya JPG, JPEG, PNG)";
                }
            } else {
                $notif_gagal = "Anda wajib mengunggah foto bukti sampah!";
            }

            // Jika tidak ada error upload gambar, masukkan data ke database
            if (empty($notif_gagal) && $nama_file_gambar !== NULL) {
                date_default_timezone_set('Asia/Jakarta');
                $waktu_sekarang = date('Y-m-d H:i:s');
                $q_insert = "INSERT INTO transaksi_setoran (username_nasabah, id_kategori, berat, total_harga, status, gambar_sampah, tanggal) 
                             VALUES ('$username_aktif', $id_kategori, $berat, $total_harga, 'pending', '$nama_file_gambar', '$waktu_sekarang')";
                
                if (mysqli_query($koneksi, $q_insert)) {
                    $notif_sukses = "Formulir setoran berhasil dikirim! Silakan tunggu Admin memverifikasi sampah Anda.";
                } else {
                    $notif_gagal = "Terjadi kesalahan pada sistem database. Silakan coba lagi.";
                }
            }
        } else {
            $notif_gagal = "Kategori sampah tidak ditemukan.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Setor Sampah - Bank Sampah Induk</title>
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
        .header h3 { margin: 0; color: white; font-size: 18px; display: flex; align-items: center; gap: 10px;}
        
        /* Tombol Hamburger */
        .mobile-menu-btn { display: none; font-size: 24px; color: white; cursor: pointer;}
        
        /* Latar Gelap Saat Sidebar Terbuka di HP */
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; }

        .content { padding: 30px; flex: 1; overflow-y: auto; display: flex; flex-direction: column; align-items: center; }
        
        /* Card Instructions (Tata Cara) */
        .instruction-card { background: linear-gradient(135deg, #1abc9c, #16a085); color: white; padding: 25px 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(26, 188, 156, 0.2); width: 100%; max-width: 700px; margin-bottom: 25px; box-sizing: border-box; }
        .instruction-card h3 { margin-top: 0; margin-bottom: 15px; font-size: 20px; border-bottom: 1px solid rgba(255,255,255,0.3); padding-bottom: 10px; display: flex; align-items: center; gap: 10px;}
        .steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; }
        .step-item { background: rgba(255, 255, 255, 0.1); padding: 15px; border-radius: 8px; text-align: center; }
        .step-item i { font-size: 24px; margin-bottom: 10px; }
        .step-item p { margin: 0; font-size: 13px; line-height: 1.4; }

        /* Card Form Setoran */
        .card { background-color: white; padding: 35px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; max-width: 700px; box-sizing: border-box;}
        .card-header { margin-bottom: 25px; border-bottom: 2px solid #eee; padding-bottom: 15px;}
        .card-header h2 { margin: 0; color: #2c3e50; display: flex; align-items: center; gap: 10px; font-size: 22px;}
        .card-header p { margin: 5px 0 0; color: #7f8c8d; font-size: 14px;}

        /* Form Styling Umum */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; color: #555; margin-bottom: 8px; font-weight: bold; }
        .form-group input { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box; background-color: #f9fbfb; transition: 0.3s;}
        .form-group input:focus { border-color: #1abc9c; outline: none; background-color: #fff;}
        
        /* ---------------------------------------------------
           CUSTOM SELECT DROPDOWN (FITUR SCROLL DALAM KOTAK)
           --------------------------------------------------- */
        .custom-select-wrapper { position: relative; user-select: none; flex: 1;}
        .custom-select-trigger {
            width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px;
            font-size: 14px; background-color: #f9fbfb; cursor: pointer; color: #333;
            display: flex; justify-content: space-between; align-items: center; transition: 0.3s; box-sizing: border-box;
        }
        .custom-select-trigger:hover { border-color: #1abc9c; background-color: #fff; }
        .custom-select-options {
            position: absolute; top: 100%; left: 0; right: 0;
            background: #fff; border: 1px solid #1abc9c; border-radius: 8px;
            margin-top: 5px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            z-index: 999; display: none;
            max-height: 220px; overflow-y: auto; 
        }
        .custom-select-options.open { display: block; }
        .custom-option {
            padding: 12px 15px; font-size: 14px; color: #333; border-bottom: 1px solid #eee; cursor: pointer; transition: 0.2s;
        }
        .custom-option:last-child { border-bottom: none; }
        .custom-option:hover, .custom-option.selected { background-color: #e2f7f2; color: #16a085; font-weight: bold; border-left: 3px solid #1abc9c;}
        
        .custom-select-options::-webkit-scrollbar { width: 6px; }
        .custom-select-options::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 8px;}
        .custom-select-options::-webkit-scrollbar-thumb { background: #1abc9c; border-radius: 8px;}
        .custom-select-options::-webkit-scrollbar-thumb:hover { background: #16a085; }

        /* TOMBOL ATAS-BAWAH & BERAT */
        .btn-updown { background-color: #2c3e50; color: white; border: none; border-radius: 6px; width: 40px; height: 21px; cursor: pointer; display: flex; justify-content: center; align-items: center; transition: 0.2s; }
        .btn-updown:active { background-color: #1abc9c; transform: scale(0.95); }
        .btn-updown i { font-size: 14px; }
        
        .btn-berat { background-color: #3498db; color: white; border: none; border-radius: 8px; width: 45px; height: 45px; cursor: pointer; font-size: 16px; display: flex; justify-content: center; align-items: center; flex-shrink: 0; transition: 0.2s;}
        .btn-berat:active { background-color: #2980b9; transform: scale(0.95); }

        /* Area Upload Gambar */
        .upload-area { border: 2px dashed #1abc9c; border-radius: 8px; padding: 20px; text-align: center; background-color: #f1fcf9; position: relative; transition: 0.3s; }
        .upload-area:hover { background-color: #e2f7f2; }
        .upload-area i { font-size: 40px; color: #1abc9c; margin-bottom: 10px; }
        .upload-area input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .upload-area p { margin: 0; color: #16a085; font-weight: bold; font-size: 14px; }
        .upload-area span { display: block; font-size: 12px; color: #7f8c8d; margin-top: 5px; font-weight: normal; }
        
        /* Preview Image & Action Buttons */
        #preview-box { display: none; margin-top: 15px; text-align: center; background: #fdfdfd; border: 1px solid #eee; border-radius: 8px; padding: 15px; }
        #preview-img { max-width: 100%; max-height: 200px; border-radius: 8px; border: 2px solid #ddd; object-fit: contain; }
        .preview-actions { display: flex; justify-content: center; gap: 10px; margin-top: 15px; }
        .btn-hapus-img { background: #e74c3c; color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: bold; font-size: 13px; cursor: pointer; transition: 0.2s;}
        .btn-hapus-img:hover { background: #c0392b;}
        .btn-ubah-img { background: #3498db; color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: bold; font-size: 13px; cursor: pointer; transition: 0.2s;}
        .btn-ubah-img:hover { background: #2980b9;}

        .btn-submit { background-color: #1abc9c; color: white; border: none; padding: 14px 20px; border-radius: 8px; font-weight: bold; font-size: 15px; cursor: pointer; transition: 0.2s; width: 100%; margin-top: 10px; display: flex; justify-content: center; align-items: center; gap: 8px;}
        .btn-submit:hover { background-color: #16a085; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(26, 188, 156, 0.3);}
        
        .alert-success { background-color: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border: 1px solid #c3e6cb;}
        .alert-danger { background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border: 1px solid #f5c6cb;}

        /* STYLING UNTUK MODAL GLOBAL */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 380px; border-radius: 15px; overflow: hidden; box-shadow: 0 15px 30px rgba(0,0,0,0.3); text-align: center; animation: popIn 0.3s ease-out; }
        .modal-header-red { background-color: #e74c3c; padding: 25px 20px; }
        .modal-header-green { background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%); padding: 25px 20px; color: white;}
        .modal-header-blue { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); padding: 25px 20px; color: white;}
        .modal-header-red img { width: 80px; height: auto; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2)); }
        .modal-header-green i, .modal-header-blue i { font-size: 50px; text-shadow: 0 4px 6px rgba(0,0,0,0.2); }
        .modal-body { padding: 25px 30px 30px; background: #fff; }
        .modal-body h3 { margin: 0 0 10px; color: #333; font-size: 20px; }
        .modal-body p { color: #666; margin-bottom: 20px; font-size: 14px; line-height: 1.5; }
        
        .info-box { background: #f8f9fa; border: 1px dashed #bdc3c7; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: left; }
        .info-box p { margin: 0 0 8px 0; font-size: 13px; color: #555; border-bottom: 1px dotted #ccc; padding-bottom: 5px;}
        .info-box p span { float: right; font-weight: bold; color: #2c3e50; }
        .info-box .total-struk { text-align: center; font-size: 20px; color: #2e7d32; font-weight: bold; margin-top: 15px; border: none; padding:0;}

        .modal-buttons { display: flex; gap: 15px; justify-content: center; }
        .modal-buttons button { padding: 12px 20px; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; flex: 1; }
        .btn-cancel { background: #e0e0e0; color: #555; }
        .btn-cancel:hover { background: #d5d5d5; }
        .btn-confirm { background: #c0392b; color: white; box-shadow: 0 4px 6px rgba(231,76,60,0.2);}
        .btn-confirm:hover { background: #a53125; transform: translateY(-2px);}
        .btn-confirm-blue-m { background: #3498db; color: white; box-shadow: 0 4px 6px rgba(52, 152, 219, 0.2);}
        .btn-confirm-blue-m:hover { background: #2980b9; transform: translateY(-2px);}
        @keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

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
            
            .sidebar h2 { font-size: 18px; justify-content: flex-start; padding-left: 20px; }
            .sidebar-logo-text { display: inline; font-size: 18px; margin-left: 10px; font-family: 'Segoe UI', sans-serif;}
            
            .menu { padding-top: 15px; }
            .menu a { flex-direction: row; justify-content: flex-start; padding: 15px 25px; }
            .menu a i { margin-right: 15px; margin-bottom: 0; font-size: 20px;}
            .menu a span.menu-text { font-size: 15px; font-weight: normal;}

            .content { padding: 15px; display: block; }
            
            .instruction-card, .card { padding: 20px; margin-bottom: 20px; border-radius: 12px; }
            .instruction-card h3, .card-header h2 { font-size: 18px; }
            .step-item { padding: 10px; }
            .step-item i { font-size: 20px; margin-bottom: 5px; }
            .step-item p { font-size: 12px; }

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
            <a href="setor_sampah.php" class="active">
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
                Setor Sampah
            </h3>
        </div>
        
        <div class="content">

            <div class="instruction-card">
                <h3><i class="fa fa-info-circle"></i> Tata Cara Penyetoran Sampah</h3>
                <div class="steps">
                    <div class="step-item">
                        <i class="fa fa-list-alt"></i>
                        <p><b>Pilih Kategori</b><br>Pilih jenis sampah yang sesuai dengan barang Anda.</p>
                    </div>
                    <div class="step-item">
                        <i class="fa fa-weight"></i>
                        <p><b>Isi Berat (Kg)</b><br>Timbang dan masukkan berat sampah Anda.</p>
                    </div>
                    <div class="step-item">
                        <i class="fa fa-camera"></i>
                        <p><b>Unggah Foto</b><br>Lampirkan foto sampah sebagai bukti validasi.</p>
                    </div>
                    <div class="step-item">
                        <i class="fa fa-clock"></i>
                        <p><b>Tunggu Admin</b><br>Admin akan mengecek dan memproses saldo Anda.</p>
                    </div>
                </div>
            </div>
            
            <div style="background-color: #e8f6f3; padding: 15px 25px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #bde6de; display: flex; flex-wrap: wrap; gap: 20px; align-items: center; font-size: 13px; color: #2c3e50; box-shadow: 0 4px 10px rgba(0,0,0,0.03); width: 100%; max-width: 700px; box-sizing: border-box;">
                <strong style="font-size: 14px; color: #16a085;"><i class="fa fa-info-circle"></i> Arti Warna Status:</strong>
                
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 14px; height: 14px; background-color: #f39c12; border-radius: 4px;"></span> 
                    <span><b>Menunggu</b> (Diperiksa Admin)</span>
                </div>
                
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 14px; height: 14px; background-color: #3498db; border-radius: 4px;"></span> 
                    <span><b>Disetujui</b> (Proses disetujui Silahkan datang ke tempat)</span>
                </div>
                
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 14px; height: 14px; background-color: #2ecc71; border-radius: 4px;"></span> 
                    <span><b>Selesai</b> (Saldo Masuk)</span>
                </div>
                
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="width: 14px; height: 14px; background-color: #e74c3c; border-radius: 4px;"></span> 
                    <span><b>Ditolak</b> (Data Tidak Valid)</span>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fa fa-clipboard-list"></i> Formulir Setor Sampah</h2>
                    <p>Lengkapi data di bawah ini untuk mengajukan setoran sampah Anda.</p>
                </div>

                <?php if(!empty($notif_sukses)): ?>
                    <div class="alert-success"><i class="fa fa-check-circle"></i> <?php echo $notif_sukses; ?></div>
                <?php endif; ?>
                
                <?php if(!empty($notif_gagal)): ?>
                    <div class="alert-danger"><i class="fa fa-exclamation-triangle"></i> <?php echo $notif_gagal; ?></div>
                <?php endif; ?>

                <form id="form-setoran" method="POST" action="" enctype="multipart/form-data" onsubmit="return tampilkanKonfirmasi(event)">
                    
                    <div class="form-group">
                        <label>Pilih Kategori / Jenis Sampah</label>
                        <div style="display: flex; gap: 10px; align-items: stretch;">
                            
                            <div class="custom-select-wrapper" id="customSelectWrapper">
                                <input type="hidden" name="id_kategori" id="hidden_kategori">
                                
                                <div class="custom-select-trigger" onclick="toggleCustomSelect()">
                                    <span id="trigger-text" style="color: #888;">-- Pilih Jenis Sampah --</span>
                                    <i class="fa fa-chevron-down"></i>
                                </div>
                                
                                <div class="custom-select-options" id="customOptionsList">
                                    <?php 
                                    $q_kat = mysqli_query($koneksi, "SELECT * FROM kategori_sampah ORDER BY nama_barang ASC");
                                    $loop_idx = 0;
                                    while ($d_kat = mysqli_fetch_assoc($q_kat)) {
                                        $nama = htmlspecialchars($d_kat['nama_barang']);
                                        $harga = $d_kat['harga_barang'];
                                        $teks_full = $nama . " - (Rp " . number_format($harga, 0, ',', '.') . " / Kg)";
                                        
                                        echo "<div class='custom-option' 
                                                data-id='" . $d_kat['id'] . "' 
                                                data-harga='" . $harga . "' 
                                                data-nama='" . addslashes($nama) . "' 
                                                onclick='pilihOpsi(" . $loop_idx . ")'>" . $teks_full . "</div>";
                                        $loop_idx++;
                                    }
                                    ?>
                                </div>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 5px; justify-content: center;">
                                <button type="button" class="btn-updown" onclick="geserKategori('up')"><i class="fa fa-chevron-up"></i></button>
                                <button type="button" class="btn-updown" onclick="geserKategori('down')"><i class="fa fa-chevron-down"></i></button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Estimasi Berat Sampah (Kilogram)</label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <button type="button" class="btn-berat" onclick="ubahBerat(-0.5)"><i class="fa fa-minus"></i></button>
                            
                            <input type="number" step="0.1" name="berat" id="input_berat" placeholder="0.0" style="text-align: center; flex: 1;" required>
                            
                            <button type="button" class="btn-berat" onclick="ubahBerat(0.5)"><i class="fa fa-plus"></i></button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Foto Bukti Sampah</label>
                        
                        <div class="upload-area" id="box-upload-area">
                            <i class="fa fa-cloud-upload-alt"></i>
                            <p>Klik atau seret foto sampah ke area ini</p>
                            <span>Format: JPG, JPEG, PNG (Maks 2MB)</span>
                            <input type="file" name="gambar_sampah" accept="image/png, image/jpeg, image/jpg" id="input-gambar" required>
                        </div>
                        
                        <div id="preview-box">
                            <p style="font-size: 13px; color: #555; margin-bottom: 10px;"><b>Preview Foto Bukti:</b></p>
                            <img id="preview-img" src="" alt="Preview">
                            
                            <div class="preview-actions">
                                <button type="button" class="btn-hapus-img" onclick="hapusGambar()"><i class="fa fa-trash"></i> Hapus</button>
                                <button type="button" class="btn-ubah-img" onclick="document.getElementById('input-gambar').click()"><i class="fa fa-edit"></i> Ubah Foto</button>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit"><i class="fa fa-paper-plane"></i> Kirim Pengajuan Setoran</button>
                </form>
            </div>
            
        </div>
    </div>

    <div id="konfirmasiSetoranModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header-blue">
                <i class="fa fa-question-circle"></i>
            </div>
            <div class="modal-body">
                <h3>Konfirmasi Setoran</h3>
                <p style="margin-bottom: 10px;">Periksa kembali rincian setoran Anda sebelum dikirim ke Admin.</p>
                
                <div class="info-box">
                    <p>Jenis Sampah: <span id="konf-kategori">-</span></p>
                    <p>Berat Estimasi: <span id="konf-berat">0 Kg</span></p>
                    <p style="border-bottom:none; margin-top:10px;">Estimasi Pendapatan:</p>
                    <p class="total-struk" id="konf-total">Rp 0</p>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="tutupKonfirmasi()">Batal</button>
                    <button type="button" class="btn-confirm-blue-m" onclick="prosesKirimSetoran()"><i class="fa fa-check"></i> Ya, Kirim</button>
                </div>
            </div>
        </div>
    </div>

    <div id="customModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header-red">
                <img src="assets/logo-lebak.png" alt="Logo Instansi">
            </div>
            <div class="modal-body">
                <h3>Konfirmasi Logout</h3>
                <p>Apakah Anda yakin ingin keluar dari sistem Bank Sampah Induk?</p>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeLogoutModal()">Batal</button>
                    <button type="button" class="btn-confirm" onclick="window.location.href='logout.php'">Ya, Keluar</button>
                </div>
            </div>
        </div>
    </div>

<script>
    // --- FITUR HAMBURGER MENU ---
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

    // --- LOGIKA CUSTOM SCROLL DROPDOWN ---
    let dataKategori = [];
    let indexPilihan = -1;
    let hargaTerpilih = 0;
    let namaTerpilih = "";

    window.onload = function() {
        let opsiElemen = document.querySelectorAll('.custom-option');
        opsiElemen.forEach((el, index) => {
            dataKategori.push({
                id: el.getAttribute('data-id'),
                harga: parseFloat(el.getAttribute('data-harga')),
                nama: el.getAttribute('data-nama'),
                teksFull: el.innerText,
                elemenHTML: el
            });
        });
    }

    function toggleCustomSelect() {
        document.getElementById('customOptionsList').classList.toggle('open');
    }

    function pilihOpsi(index) {
        if (index < 0 || index >= dataKategori.length) return;
        
        let opsi = dataKategori[index];
        
        document.getElementById('hidden_kategori').value = opsi.id;
        let trigger = document.getElementById('trigger-text');
        trigger.innerText = opsi.teksFull;
        trigger.style.color = "#333";
        trigger.style.fontWeight = "bold";
        
        hargaTerpilih = opsi.harga;
        namaTerpilih = opsi.nama;
        indexPilihan = index;

        document.getElementById('customOptionsList').classList.remove('open');
        
        document.querySelectorAll('.custom-option').forEach(el => el.classList.remove('selected'));
        opsi.elemenHTML.classList.add('selected');
    }

    document.addEventListener('click', function(event) {
        let isClickInside = document.getElementById('customSelectWrapper').contains(event.target);
        if (!isClickInside) {
            document.getElementById('customOptionsList').classList.remove('open');
        }
    });

    // --- FITUR GANTI KATEGORI (TOMBOL ATAS BAWAH) ---
    function geserKategori(arah) {
        if (dataKategori.length === 0) return;

        if (arah === 'up' && indexPilihan > 0) {
            pilihOpsi(indexPilihan - 1);
        } else if (arah === 'down' && indexPilihan < dataKategori.length - 1) {
            pilihOpsi(indexPilihan + 1);
        } else if (arah === 'down' && indexPilihan === -1) {
            pilihOpsi(0);
        }
    }

    // --- FITUR TAMBAH / KURANG BERAT ---
    function ubahBerat(nilai) {
        var input = document.getElementById('input_berat');
        var current = parseFloat(input.value) || 0;
        var hasil = current + nilai;
        if (hasil < 0) hasil = 0;
        input.value = hasil.toFixed(1);
    }

    // --- LOGIKA MODAL KONFIRMASI SETORAN ---
    let formTelahDikonfirmasi = false;

    function tampilkanKonfirmasi(e) {
        if(formTelahDikonfirmasi) return true; 
        e.preventDefault(); 
        
        let idKategori = document.getElementById('hidden_kategori').value;
        if (idKategori === "") {
            alert("Silakan pilih kategori sampah terlebih dahulu!");
            return false;
        }

        let berat = parseFloat(document.getElementById('input_berat').value) || 0;
        if (berat <= 0) {
            alert("Berat sampah harus lebih dari 0 Kg!");
            return false;
        }

        let totalEstimasi = hargaTerpilih * berat;
        let formatRupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(totalEstimasi);
        
        document.getElementById('konf-kategori').innerText = namaTerpilih;
        document.getElementById('konf-berat').innerText = berat + ' Kg';
        document.getElementById('konf-total').innerText = formatRupiah;
        
        document.getElementById('konfirmasiSetoranModal').style.display = 'flex';
    }

    function tutupKonfirmasi() {
        document.getElementById('konfirmasiSetoranModal').style.display = 'none';
        formTelahDikonfirmasi = false;
    }

    function prosesKirimSetoran() {
        formTelahDikonfirmasi = true;
        document.getElementById('konfirmasiSetoranModal').style.display = 'none';
        
        let form = document.getElementById('form-setoran');
        let hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'submit_setoran';
        hiddenInput.value = '1';
        form.appendChild(hiddenInput);
        
        form.submit();
    }

    // --- LOGOUT ---
    function showLogoutModal() { document.getElementById('customModal').style.display = 'flex'; }
    function closeLogoutModal() { document.getElementById('customModal').style.display = 'none'; }

    // --- PREVIEW GAMBAR ---
    const inputGambar = document.getElementById('input-gambar');
    const previewBox = document.getElementById('preview-box');
    const previewImg = document.getElementById('preview-img');
    const uploadAreaBox = document.getElementById('box-upload-area');

    inputGambar.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.addEventListener('load', function() {
                previewImg.setAttribute('src', this.result);
                previewBox.style.display = 'block';
                uploadAreaBox.style.display = 'none'; 
            });
            reader.readAsDataURL(file);
        } else {
            hapusGambar();
        }
    });

    function hapusGambar() {
        inputGambar.value = ""; 
        previewImg.setAttribute('src', '');
        previewBox.style.display = 'none'; 
        uploadAreaBox.style.display = 'block'; 
    }
</script>

</body>
</html>