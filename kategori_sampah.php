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
$hapus_sukses = false;

// Fitur Hapus Data Kategori Sampah
if (isset($_GET['hapus'])) {
    $id_hapus = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    if (mysqli_query($koneksi, "DELETE FROM kategori_sampah WHERE id = '$id_hapus'")) {
        $hapus_sukses = true;
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
    <title>Kategori Sampah - Bank Sampah Induk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar Styling (Mini Sidebar 85px) */
        .sidebar { width: 85px; background-color: #2c3e50; color: white; display: flex; flex-direction: column; z-index: 1001; }
        
        /* Kunci tinggi logo 60px agar presisi dengan header */
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

        .content { padding: 30px; flex: 1; overflow-y: auto; overflow-x: hidden; width: 100%; box-sizing: border-box;}
        
        .card { background-color: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; max-width: 100%; box-sizing: border-box; overflow: hidden;}
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; flex-wrap: wrap; gap: 10px;}
        .card-header h2 { margin: 0; color: #1abc9c; font-size: 22px;}
        
        .btn-tambah { background-color: #1abc9c; color: white; border: none; padding: 10px 20px; border-radius: 25px; font-weight: bold; cursor: pointer; text-decoration: none; transition: 0.3s; display: inline-block;}
        .btn-tambah:hover { background-color: #16a085; transform: translateY(-2px);}

        /* STYLING TABEL DENGAN SCROLL KETAT */
        .table-responsive { 
            width: 100%; 
            max-width: 100%;
            overflow-x: auto; 
            -webkit-overflow-scrolling: touch; 
            display: block; 
        }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px; min-width: 650px; white-space: nowrap;}
        table th, table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; vertical-align: middle; }
        table th { background-color: #f8f9fa; color: #333; font-weight: 600; text-transform: uppercase; font-size: 13px; letter-spacing: 0.5px;}
        table tr:hover { background-color: #f1fcf9; transition: 0.2s;}
        
        .harga { font-weight: bold; color: #2e7d32; }
        .satuan { color: #888; font-size: 12px; font-weight: normal;}

        .img-sampah { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 2px solid #ddd; background-color: #f8f9fa; }
        .img-placeholder { width: 60px; height: 60px; border-radius: 8px; background-color: #ecf0f1; display: flex; align-items: center; justify-content: center; color: #bdc3c7; font-size: 24px; border: 2px dashed #bdc3c7; }

        .btn-action { padding: 6px 12px; border: none; border-radius: 4px; color: white; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; margin-right: 5px; transition: 0.2s;}
        .btn-edit { background-color: #3498db; }
        .btn-edit:hover { background-color: #2980b9; }
        .btn-delete { background-color: #e74c3c; }
        .btn-delete:hover { background-color: #c0392b; }

        /* MODAL KOTAK (GLOBAL) */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-box { background: #fff; width: 360px; border-radius: 16px; overflow: hidden; box-shadow: 0 15px 40px rgba(0,0,0,0.4); text-align: center; animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        .modal-header-green { background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%); padding: 25px 20px; color: white; }
        .modal-header-green img { width: 80px; height: auto; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2)); }
        .modal-header-green i { font-size: 60px; text-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .modal-header-red { background: linear-gradient(135deg, #ff7675 0%, #d63031 100%); padding: 25px 20px; color: white; }
        .modal-header-red i { font-size: 60px; text-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .modal-body { padding: 25px 30px 30px; background: #fff; }
        .modal-body h3 { margin: 0 0 10px; color: #333; font-size: 22px; font-weight: 800;}
        .modal-body p { color: #555; margin-bottom: 25px; font-size: 15px; line-height: 1.5; }
        .modal-buttons { display: flex; gap: 15px; justify-content: center; }
        .modal-buttons button { padding: 12px 20px; border: none; border-radius: 25px; font-weight: bold; font-size: 14px; cursor: pointer; transition: 0.2s; flex: 1; }
        .btn-cancel { background: #e0e0e0; color: #555; }
        .btn-cancel:hover { background: #d5d5d5; }
        .btn-confirm-red { background: #d63031; color: white; box-shadow: 0 4px 6px rgba(214,48,49,0.3);}
        .btn-confirm-red:hover { background: #b33939; transform: translateY(-2px);}
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
            
            /* Sembunyikan tulisan "Administrator" di HP biar rapi */
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
            
            .card { 
                padding: 20px; 
                margin-bottom: 20px; 
                border-radius: 12px;
                width: 100%;
                max-width: calc(100vw - 30px); 
            }
            .card-header h2 { font-size: 18px; }
            .btn-tambah { padding: 8px 15px; font-size: 13px; }
            
            .table-responsive { 
                overflow-x: auto !important; 
                margin-top: 10px;
                border: 1px solid #f1f1f1;
            }

            /* Penyesuaian Modal Mungil di HP */
            .modal-box { width: 92%; max-width: 320px; }
            .modal-header-green, .modal-header-red { padding: 15px; }
            .modal-header-green i, .modal-header-red i { font-size: 40px; }
            .modal-header-green img, .modal-header-red img { width: 60px; }
            .modal-body { padding: 20px; }
            .modal-body h3 { font-size: 18px; margin-bottom: 8px;}
            .modal-body p { font-size: 13px; margin-bottom: 20px; line-height: 1.4; }
            .modal-buttons { gap: 10px; }
            .modal-buttons button { padding: 10px; font-size: 13px; }
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
            <h3><i class="fa fa-bars mobile-menu-btn" onclick="toggleMobileMenu()"></i> Kelola Kategori Sampah</h3>
            
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
            <div class="card">
                <div class="card-header">
                    <h2>Daftar Harga Barang (Per Kg)</h2>
                    <a href="tambah_kategori.php" class="btn-tambah"><i class="fa fa-plus"></i> Tambah Kategori</a>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Barang</th>
                                <th>Harga Barang</th>
                                <th>Aksi</th>
                                <th style="text-align: center;">Gambar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Mengambil data dari database
                            $query = "SELECT * FROM kategori_sampah ORDER BY id ASC";
                            $result = mysqli_query($koneksi, $query);
                            $no = 1;

                            if (mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    echo "<tr>";
                                    echo "<td>" . $no++ . "</td>";
                                    echo "<td><b>" . htmlspecialchars($row['nama_barang']) . "</b></td>";
                                    echo "<td class='harga'>Rp " . number_format($row['harga_barang'], 0, ',', '.') . " <span class='satuan'>/ kg</span></td>";
                                    echo "<td>
                                            <a href='edit_kategori.php?id=" . $row['id'] . "' class='btn-action btn-edit' title='Edit Harga'><i class='fa fa-edit'></i></a>
                                            <a href='javascript:void(0);' onclick='showDeleteModal(" . $row['id'] . ")' class='btn-action btn-delete' title='Hapus Kategori'><i class='fa fa-trash'></i></a>
                                          </td>";
                                    
                                    // Kolom Gambar
                                    echo "<td style='text-align: center;'>";
                                    $path_gambar = "assets/sampah/" . $row['gambar'];
                                    if (!empty($row['gambar']) && file_exists($path_gambar)) {
                                        echo "<img src='$path_gambar' class='img-sampah' alt='" . htmlspecialchars($row['nama_barang']) . "'>";
                                    } else {
                                        echo "<div class='img-placeholder' title='Gambar Belum Tersedia'><i class='fa fa-image'></i></div>";
                                    }
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5' style='text-align:center; padding:20px; color:#7f8c8d;'>Belum ada kategori sampah.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="logoutModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header-green" style="background: #e74c3c;">
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

    <div id="deleteModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header-red">
                <i class="fa fa-exclamation-triangle"></i>
            </div>
            <div class="modal-body">
                <h3>Hapus Kategori?</h3>
                <p>Apakah Anda yakin ingin menghapus kategori sampah ini?</p>
                <div class="modal-buttons">
                    <button class="btn-cancel" onclick="closeDeleteModal()">Batal</button>
                    <button class="btn-confirm-red" onclick="executeDelete()">Hapus</button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($hapus_sukses) : ?>
    <div id="successModal" class="modal-overlay" style="display: flex;">
        <div class="modal-box">
            <div class="modal-header-green">
                <i class="fa fa-check-circle"></i>
            </div>
            <div class="modal-body">
                <h3>Berhasil!</h3>
                <p>Kategori sampah telah dihapus.</p>
                <button class="btn-confirm-green" onclick="closeSuccessModal()">Tutup</button>
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

    // --- FUNGSI MODAL ---
    function showLogoutModal() { document.getElementById('logoutModal').style.display = 'flex'; }
    function closeLogoutModal() { document.getElementById('logoutModal').style.display = 'none'; }

    let deleteTargetId = ""; 
    function showDeleteModal(id) {
        deleteTargetId = 'kategori_sampah.php?hapus=' + id;
        document.getElementById('deleteModal').style.display = 'flex';
    }
    function closeDeleteModal() { document.getElementById('deleteModal').style.display = 'none'; }
    function executeDelete() { window.location.href = deleteTargetId; }

    function closeSuccessModal() {
        document.getElementById('successModal').style.display = 'none';
        window.history.pushState({}, document.title, "kategori_sampah.php");
    }
</script>

</body>
</html>