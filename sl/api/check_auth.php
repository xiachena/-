<?php
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

// 安全头设置
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 只允许GET请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只允许GET请求']);
    exit();
}

try {
    // 验证CSRF令牌
    $csrf_token = $_GET['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF令牌无效']);
        exit();
    }

    // 检查用户是否已登录
    if (isset($_SESSION['user_id'])) {
        $user_id = (int)$_SESSION['user_id'];
        
        // 验证用户ID
        if ($user_id <= 0) {
            echo json_encode([
                'logged_in' => false,
                'user' => null
            ]);
            exit();
        }
        
        // 获取用户信息
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, username, email, role, created_at, status FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // 检查用户状态
            if ($user['status'] !== 'active') {
                echo json_encode([
                    'logged_in' => false,
                    'user' => null
                ]);
                exit();
            }
            
            echo json_encode([
                'success' => true,
                'logged_in' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'created_at' => $user['created_at']
                ]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'logged_in' => false,
                'user' => null
            ]);
        }
    } else {
        echo json_encode([
            'success' => true,
            'logged_in' => false,
            'user' => null
        ]);
    }

} catch (PDOException $e) {
    error_log("检查认证状态错误: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'logged_in' => false,
        'user' => null,
        'error' => '检查失败'
    ]);
} catch (Exception $e) {
    error_log("检查认证状态错误: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'logged_in' => false,
        'user' => null,
        'error' => '检查失败'
    ]);
}
?>