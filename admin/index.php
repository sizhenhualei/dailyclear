<?php
/**
 * /admin 入口：转发到根目录的后台 admin.php
 * （客户界面不显示后台入口，老师用 域名/admin 进后台）
 */

$self = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '/admin/index.php';
$root = rtrim(str_replace('\\', '/', dirname(dirname($self))), '/');

header('Location: ' . $root . '/admin.php', true, 302);
header('Content-Type: text/plain; charset=utf-8');
echo "后台在 " . $root . "/admin.php";
