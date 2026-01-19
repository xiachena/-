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
    $password = $input['password'] ?? '';

    // 输入验证
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => '用户名和密码不能为空']);
        exit();
    }
    
    // 用户名格式验证
    if (!preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $username)) {
        echo json_encode(['success' => false, 'message' => '用户名格式不正确']);
        exit();
    }
    
    // 密码基本验证
    if (empty($password)) {
        echo json_encode(['success' => false, 'message' => '密码不能为空']);
        exit();
    }

    $db = Database::getInstance()->getConnection();
    
    // 检查登录尝试次数 - 暂时禁用，因为login_attempts表不存在
    $ip = get_client_ip();

    // 准备SQL语句 - 使用密码哈希验证
    $stmt = $db->prepare("SELECT id, username, email, password, rank, avatar, joined_at FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && verify_password($password, $user['password'])) {
        // 更新最后登录时间
        $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([$user['id']]);
        
        // 记录安全事件 - 暂时禁用，因为相关表不存在
        // log_security_event('login_success', $user['id'], $ip);
        
        // 生成简单的会话令牌
        $token = generate_secure_token();
        
        echo json_encode([
            'success' => true,
            'message' => '登录成功',
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['rank'], // 注意：数据库中字段名为rank，不是role
                'avatar' => $user['avatar'],
                'created_at' => $user['joined_at'] // 注意：数据库中字段名为joined_at，不是created_at
            ]
        ]);
    } else {
        // 记录安全事件 - 暂时禁用，因为相关表不存在
        // log_security_event('login_failed', null, $ip);
        
        echo json_encode(['success' => false, 'message' => '用户名或密码错误']);
    }

} catch (PDOException $e) {
    error_log("登录错误: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '登录失败，请稍后重试']);
} catch (Exception $e) {
    error_log("登录错误: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '登录失败，请稍后重试']);
}
?>