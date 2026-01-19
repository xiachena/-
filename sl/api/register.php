<?php
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

// 安全头
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// 限制CORS - 只允许同源请求
header("Access-Control-Allow-Origin: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST']);
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");
header("Access-Control-Max-Age: 3600");

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只允许POST请求']);
    exit();
}

// 检查是否为AJAX请求
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '非法请求']);
    exit();
}

try {
    // 获取POST数据
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => '无效的请求数据']);
        exit();
    }
    
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    // 输入验证
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'message' => '所有字段都是必填的']);
        exit();
    }

    // 验证用户名格式
    if (!preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $username)) {
        echo json_encode(['success' => false, 'message' => '用户名必须是3-20个字符，只能包含字母、数字、下划线和连字符']);
        exit();
    }

    // 验证邮箱格式
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        echo json_encode(['success' => false, 'message' => '请输入有效的邮箱地址']);
        exit();
    }

    // 验证密码强度
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => '密码长度至少为8个字符']);
        exit();
    }
    
    // 密码复杂度验证
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.])[A-Za-z\d@$!%*?&.]{8,}$/', $password)) {
        echo json_encode(['success' => false, 'message' => '密码必须包含大小写字母、数字和特殊字符']);
        exit();
    }

    // 验证密码匹配
    if ($password !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => '两次输入的密码不一致']);
        exit();
    }

    $db = Database::getInstance()->getConnection();
    
    // 检查注册频率限制
    $ip = get_client_ip();
    $stmt = $db->prepare("SELECT COUNT(*) as registrations FROM users WHERE ip = ? AND joined_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->execute([$ip]);
    $registrations = $stmt->fetch(PDO::FETCH_ASSOC)['registrations'];
    
    if ($registrations >= 3) {
        echo json_encode(['success' => false, 'message' => '注册过于频繁，请稍后再试']);
        exit();
    }

    // 检查用户名是否已存在
    $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $checkStmt->execute([$username]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '用户名已存在']);
        exit();
    }

    // 检查邮箱是否已存在
    $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '邮箱已被注册']);
        exit();
    }

    // 使用安全的密码哈希
    $hashedPassword = hash_password($password);
    
    // 插入新用户
    $stmt = $db->prepare("
        INSERT INTO users (username, email, password, ip, joined_at, status, rank, avatar)
        VALUES (?, ?, ?, ?, NOW(), 'active', 'user', 'default.png')
    ");
    
    $stmt->execute([$username, $email, $hashedPassword, $ip]);

    if ($stmt->rowCount() > 0) {
        $userId = $db->lastInsertId();
        
        // 记录安全事件
        log_security_event('user_registered', $userId, $ip);
        
        echo json_encode(['success' => true, 'message' => '注册成功！请登录']);
    } else {
        echo json_encode(['success' => false, 'message' => '注册失败，请稍后重试']);
    }

} catch (PDOException $e) {
    error_log("注册错误: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '注册失败，请稍后重试']);
} catch (Exception $e) {
    error_log("注册错误: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '注册失败，请稍后重试']);
}
?>