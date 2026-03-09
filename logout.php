<?php
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/' );
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
// Hapus session dari DB dan destroy
if (session_status() === PHP_SESSION_NONE) session_start();
session_destroy();
header('Location: ' . BASE_URL . 'index.php');
exit;
