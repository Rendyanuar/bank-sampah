<?php
// Mulai session
session_start();

// Hancurkan semua data session yang tersimpan
session_unset();
session_destroy();

// Arahkan kembali ke halaman login
header("Location: login.php");
exit;
?>