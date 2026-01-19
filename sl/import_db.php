<?php
// 数据库连接配置
$servername = "localhost";
$username = "root";
$password = "";

// 创建数据库连接
$conn = new mysqli($servername, $username, $password);

// 检查连接
if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

// 读取SQL文件内容
$sql = file_get_contents("C:\\xampp\\htdocs\\sl\\database.sql");

// 执行SQL文件
if ($conn->multi_query($sql) === TRUE) {
    echo "数据库导入成功！";
} else {
    echo "错误: " . $conn->error;
}

// 关闭连接
$conn->close();
?>