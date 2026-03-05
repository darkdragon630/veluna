<?php
define('BASE_URL', ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['SCRIPT_NAME']),'/').'/' );
// Target management dipindahkan ke Dashboard
// Redirect ke dashboard#targets
header('Location: ' . BASE_URL . 'dashboard.php#targets');
exit;
