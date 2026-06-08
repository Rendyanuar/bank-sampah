<?php
session_start();
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin') {
    exit;
}
include 'koneksi.php';

// Format Output ke Excel
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Data_Transaksi_Bank_Sampah.xls");
?>

<table border="1">
    <tr>
        <th>No</th>
        <th>Tanggal</th>
        <th>Nama Nasabah</th>
        <th>Jenis Sampah</th>
        <th>Berat (Kg)</th>
        <th>Total Harga (Rp)</th>
        <th>Status</th>
    </tr>
    <?php
    // PERBAIKAN: Mengubah u.nama menjadi u.nama_lengkap
    $query = "SELECT ts.*, u.nama_lengkap, k.nama_barang 
              FROM transaksi_setoran ts
              JOIN users u ON ts.username_nasabah = u.username
              JOIN kategori_sampah k ON ts.id_kategori = k.id
              ORDER BY ts.tanggal DESC";
    
    $result = mysqli_query($koneksi, $query);
    $no = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . $row['tanggal'] . "</td>";
        // PERBAIKAN: Mengubah $row['nama'] menjadi $row['nama_lengkap']
        echo "<td>" . htmlspecialchars($row['nama_lengkap']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_barang']) . "</td>";
        echo "<td>" . $row['berat'] . "</td>";
        echo "<td>" . $row['total_harga'] . "</td>";
        echo "<td>" . ucfirst($row['status']) . "</td>";
        echo "</tr>";
    }
    ?>
</table>