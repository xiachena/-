<?php
/**
 * SL铸币服务器 - 首页重定向
 * 将请求重定向到index.html
 */

// 包含配置文件
require_once 'config.php';

// 重定向到index.html
header('Location: index.html');
exit;
?>