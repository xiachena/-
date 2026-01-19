<?php
/**
 * SL铸币服务器 - 配置测试
 * 用于验证服务器配置和错误处理
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 测试基本配置
echo "<h1>SL铸币服务器 - 配置测试</h1>";

echo "<h2>PHP配置检查</h2>";
echo "<p>PHP版本: " . phpversion() . "</p>";
echo "<p>当前时间: " . date('Y-m-d H:i:s') . "</p>";

echo "<h2>文件系统检查</h2>";
$test_dirs = ['logs', 'cache', 'uploads'];
foreach ($test_dirs as $dir) {
    if (file_exists($dir) && is_dir($dir)) {
        echo "<p>✅ $dir 目录存在</p>";
        if (is_writable($dir)) {
            echo "<p>✅ $dir 目录可写</p>";
        } else {
            echo "<p>❌ $dir 目录不可写</p>";
        }
    } else {
        echo "<p>❌ $dir 目录不存在</p>";
    }
}

echo "<h2>配置文件检查</h2>";
if (file_exists('config.php')) {
    echo "<p>✅ config.php 文件存在</p>";
    try {
        require_once 'config.php';
        echo "<p>✅ config.php 加载成功</p>";
        echo "<p>站点名称: " . SITE_NAME . "</p>";
        echo "<p>调试模式: " . (DEBUG_MODE ? '开启' : '关闭') . "</p>";
    } catch (Exception $e) {
        echo "<p>❌ config.php 加载失败: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>❌ config.php 文件不存在</p>";
}

echo "<h2>错误处理测试</h2>";
// 测试错误处理
try {
    // 故意触发一个警告
    $undefined = [];
    echo $undefined['non_existent_key'];
    echo "<p>✅ 错误处理正常工作</p>";
} catch (Exception $e) {
    echo "<p>✅ 异常捕获正常: " . $e->getMessage() . "</p>";
}

echo "<h2>数据库连接测试</h2>";
if (class_exists('Database')) {
    try {
        $db = Database::getInstance();
        echo "<p>✅ 数据库连接成功</p>";
    } catch (Exception $e) {
        echo "<p>❌ 数据库连接失败: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>❌ Database 类未找到</p>";
}

echo "<h2>会话测试</h2>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p>✅ 会话已启动</p>";
    $_SESSION['test'] = 'working';
    echo "<p>✅ 会话写入测试: " . $_SESSION['test'] . "</p>";
} else {
    echo "<p>❌ 会话未启动</p>";
}

echo "<hr>";
echo "<p><strong>测试完成！</strong> 如果看到此页面，说明服务器配置基本正常。</p>";
echo "<p><a href='index.html'>返回首页</a> | <a href='error.php?code=404'>测试404错误页面</a> | <a href='error.php?code=500'>测试500错误页面</a></p>";
?>