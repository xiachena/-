<?php
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

// 允许跨域请求
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Max-Age: 86400");

// 响应函数
function send_response($success, $message, $data = null, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// 验证必填字段
function validate_required_fields($fields, $data) {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            send_response(false, "字段 '$field' 不能为空", null, 400);
        }
    }
}

// 验证邮箱格式
function validate_email($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_response(false, "邮箱格式不正确", null, 400);
    }
}

// 验证用户名格式
function validate_username($username) {
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        send_response(false, "用户名必须是3-20个字符，只能包含字母、数字和下划线", null, 400);
    }
}

// 验证密码强度
function validate_password($password) {
    if (strlen($password) < 8) {
        send_response(false, "密码长度至少为8个字符", null, 400);
    }
    if (!preg_match('/[A-Z]/', $password)) {
        send_response(false, "密码必须包含至少一个大写字母", null, 400);
    }
    if (!preg_match('/[a-z]/', $password)) {
        send_response(false, "密码必须包含至少一个小写字母", null, 400);
    }
    if (!preg_match('/[0-9]/', $password)) {
        send_response(false, "密码必须包含至少一个数字", null, 400);
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        send_response(false, "密码必须包含至少一个特殊字符", null, 400);
    }
}

// 检查用户是否被锁定（暂时禁用，等待数据库字段添加）
function check_user_lockout($username, $db) {
    // 功能暂时禁用，用户表中缺少lockout_until字段
    return;
}

// 更新登录尝试次数（暂时禁用，等待数据库字段添加）
function update_login_attempts($username, $db, $reset = false) {
    // 功能暂时禁用，用户表中缺少login_attempts字段
    return;
}

// 创建用户会话
function create_user_session($user_id, $db) {
    $session_token = generate_secure_token();
    $expires_at = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
    $ip_address = get_client_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $db->prepare("INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $session_token, $ip_address, $user_agent, $expires_at]);
    
    return $session_token;
}

// 验证用户会话
function validate_session($session_token) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT us.*, u.username, u.email, u.role, u.status FROM user_sessions us JOIN users u ON us.user_id = u.id WHERE us.session_token = ? AND us.expires_at > NOW()");
    $stmt->execute([$session_token]);
    $session = $stmt->fetch();
    
    if ($session) {
        // 更新会话过期时间
        $expires_at = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        $stmt = $db->prepare("UPDATE user_sessions SET expires_at = ? WHERE session_token = ?");
        $stmt->execute([$expires_at, $session_token]);
        
        return $session;
    }
    
    return false;
}

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// 路由处理
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        // 注册功能
        validate_required_fields(['username', 'email', 'password', 'confirm_password'], $input);
        
        $username = sanitize_input($input['username']);
        $email = sanitize_input($input['email']);
        $password = $input['password'];
        $confirm_password = $input['confirm_password'];
        
        // 验证输入
        validate_username($username);
        validate_email($email);
        validate_password($password);
        
        if ($password !== $confirm_password) {
            send_response(false, "两次输入的密码不一致", null, 400);
        }
        
        $db = Database::getInstance()->getConnection();
        
        // 检查用户名和邮箱是否已存在
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            send_response(false, "用户名或邮箱已存在", null, 409);
        }
        
        // 创建用户
        $hashed_password = hash_password($password);
        $stmt = $db->prepare("INSERT INTO users (username, email, password, ip, joined_at, status) VALUES (?, ?, ?, ?, NOW(), 'active')");
        
        try {
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $stmt->execute([$username, $email, $hashed_password, $ip_address]);
            $user_id = $db->lastInsertId();
            
            // 创建玩家统计记录
            $stmt = $db->prepare("INSERT INTO player_stats (user_id) VALUES (?)");
            $stmt->execute([$user_id]);
            
            // 记录安全事件
            log_security_event('user_registered', $user_id);
            
            send_response(true, "注册成功！请登录", ['user_id' => $user_id]);
        } catch (PDOException $e) {
            log_error("注册失败: " . $e->getMessage());
            send_response(false, "注册失败，请稍后再试", null, 500);
        }
        break;
        
    case 'login':
        // 登录功能
        validate_required_fields(['username', 'password'], $input);
        
        $username = sanitize_input($input['username']);
        $password = $input['password'];
        $remember = isset($input['remember']) && $input['remember'];
        
        $db = Database::getInstance()->getConnection();
        
        // 检查用户是否被锁定
        check_user_lockout($username, $db);
        
        // 获取用户信息
        $stmt = $db->prepare("SELECT id, username, email, password, role, status, login_attempts FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            update_login_attempts($username, $db);
            send_response(false, "用户名或密码错误", null, 401);
        }
        
        // 检查用户状态
        if ($user['status'] === 'banned') {
            send_response(false, "您的账户已被封禁", null, 403);
        }
        
        if ($user['status'] === 'suspended') {
            send_response(false, "您的账户已被暂停", null, 403);
        }
        
        // 验证密码
        if (!verify_password($password, $user['password'])) {
            update_login_attempts($username, $db);
            send_response(false, "用户名或密码错误", null, 401);
        }
        
        // 重置登录尝试次数
        update_login_attempts($username, $db, true);
        
        // 更新最后登录时间
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        // 创建会话
        $session_token = create_user_session($user['id'], $db);
        
        // 记录安全事件
        log_security_event('user_login', $user['id']);
        
        // 设置会话cookie
        $cookie_lifetime = $remember ? time() + (30 * 24 * 60 * 60) : 0; // 30天或会话
        setcookie('session_token', $session_token, $cookie_lifetime, '/', '', true, true);
        
        send_response(true, "登录成功", [
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'session_token' => $session_token
        ]);
        break;
        
    case 'logout':
        // 登出功能
        $session_token = $_COOKIE['session_token'] ?? '';
        
        if ($session_token) {
            $db = Database::getInstance()->getConnection();
            
            // 删除会话
            $stmt = $db->prepare("DELETE FROM user_sessions WHERE session_token = ?");
            $stmt->execute([$session_token]);
            
            // 删除cookie
            setcookie('session_token', '', time() - 3600, '/', '', true, true);
            
            // 记录安全事件
            log_security_event('user_logout');
        }
        
        send_response(true, "登出成功");
        break;
        
    case 'check_session':
        // 检查会话状态
        $session_token = $_COOKIE['session_token'] ?? '';
        
        if ($session_token) {
            $session = validate_session($session_token);
            if ($session) {
                send_response(true, "会话有效", [
                    'user' => [
                        'id' => $session['user_id'],
                        'username' => $session['username'],
                        'email' => $session['email'],
                        'role' => $session['role']
                    ]
                ]);
            }
        }
        
        send_response(false, "会话无效或已过期", null, 401);
        break;
        
    case 'get_user_info':
        // 获取用户信息
        $session_token = $_COOKIE['session_token'] ?? '';
        
        if (!$session_token) {
            send_response(false, "未登录", null, 401);
        }
        
        $session = validate_session($session_token);
        if (!$session) {
            send_response(false, "会话无效或已过期", null, 401);
        }
        
        $db = Database::getInstance()->getConnection();
        
        // 获取用户详细信息
        $stmt = $db->prepare("SELECT u.*, ps.total_play_time, ps.money FROM users u LEFT JOIN player_stats ps ON u.id = ps.user_id WHERE u.id = ?");
        $stmt->execute([$session['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            send_response(true, "获取用户信息成功", [
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['rank'],
                    'status' => $user['status'],
                    'last_login' => $user['last_login'],
                    'joined_at' => $user['joined_at'],
                    'total_play_time' => $user['total_play_time'] ?? 0,
                    'money' => $user['money'] ?? 0
                ]
            ]);
        }
        
        send_response(false, "用户不存在", null, 404);
        break;
        
    default:
        send_response(false, "无效的操作", null, 400);
        break;
}
?>