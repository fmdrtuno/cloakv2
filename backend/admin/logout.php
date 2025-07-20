<?php
session_start();

// Hủy bỏ tất cả các biến phiên
$_SESSION = array();

// Hủy bỏ phiên
session_destroy();

// Chuyển hướng đến trang đăng nhập
header("location: login.php");
exit;
?>
