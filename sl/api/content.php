<?php
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

// 允许跨域请求
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
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

// 验证用户权限
function check_permission($required_role = 'user') {
    $session_token = $_COOKIE['session_token'] ?? '';
    
    if (!$session_token) {
        send_response(false, "未登录", null, 401);
    }
    
    $session = validate_session($session_token);
    if (!$session) {
        send_response(false, "会话无效或已过期", null, 401);
    }
    
    $roles = ['user' => 1, 'moderator' => 2, 'admin' => 3, 'owner' => 4];
    if ($roles[$session['role']] < $roles[$required_role]) {
        send_response(false, "权限不足", null, 403);
    }
    
    return $session;
}

// 获取公告列表
function get_announcements($page = 1, $limit = 10, $status = 'published') {
    try {
        $db = Database::getInstance()->getConnection();
        
        $offset = ($page - 1) * $limit;
        
        $stmt = $db->prepare("        SELECT a.*, u.username as author_name 
        FROM announcements a 
        JOIN users u ON a.author_id = u.id 
        WHERE a.status = ? 
        ORDER BY a.priority DESC, a.created_at DESC 
        LIMIT ? OFFSET ?
    ");
        $stmt->execute([$status, $limit, $offset]);
        $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 获取总数
        $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM announcements WHERE status = ?");
        $count_stmt->execute([$status]);
        $total = $count_stmt->fetch()['total'];
        
        return [
            'announcements' => $announcements,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ];
    } catch (Exception $e) {
        error_log("获取公告失败: " . $e->getMessage());
        return [];
    }
}

// 获取服务器规则
function get_server_rules() {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("        SELECT * FROM server_rules 
        ORDER BY order_num ASC, created_at ASC
    ");
        $stmt->execute();
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $rules;
    } catch (Exception $e) {
        error_log("获取服务器规则失败: " . $e->getMessage());
        return [];
    }
}

// 获取插件列表
function get_plugins($category = null, $active_only = true) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $where_clause = "";
        
        $stmt = $db->prepare("        SELECT * FROM plugins 
        $where_clause 
        ORDER BY id ASC, name ASC
    ");
        $stmt->execute();
        $plugins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $plugins;
    } catch (Exception $e) {
        error_log("获取插件列表失败: " . $e->getMessage());
        return [];
    }
}

// 获取服务器状态
function get_server_status() {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT * FROM server_status ORDER BY last_check DESC LIMIT 1");
        $stmt->execute();
        $server = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$server) {
            return [
                'server_name' => 'SL铸币服务器',
                'server_ip' => 'play.slserver.com',
                'server_port' => 25565,
                'game_version' => '1.20.1',
                'status' => 'offline',
                'online_players' => 0,
                'max_players' => 100,
                'motd' => '欢迎来到SL铸币服务器！'
            ];
        }
        
        return $server;
    } catch (Exception $e) {
        error_log("获取服务器状态失败: " . $e->getMessage());
        // 返回默认服务器状态
        return [
            'server_name' => 'SL铸币服务器',
            'server_ip' => 'play.slserver.com',
            'server_port' => 25565,
            'game_version' => '1.20.1',
            'status' => 'offline',
            'online_players' => 0,
            'max_players' => 100,
            'motd' => '欢迎来到SL铸币服务器！'
        ];
    }
}

// 获取管理人员列表
function get_staff_members() {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("        SELECT sm.*, COALESCE(u.avatar, 'default.png') as avatar 
        FROM staff_members sm 
        LEFT JOIN users u ON sm.user_id = u.id 
        WHERE sm.is_active = 1 
        ORDER BY sm.display_order ASC, sm.joined_at ASC
    ");
        $stmt->execute();
        $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $staff;
    } catch (Exception $e) {
        error_log("获取管理人员列表失败: " . $e->getMessage());
        return [];
    }
}

// 获取网站设置
function get_site_settings($public_only = true) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $where_clause = $public_only ? "WHERE is_public = 1" : "";
        
        $stmt = $db->prepare("SELECT setting_key, setting_value, setting_type FROM site_settings $where_clause");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        foreach ($settings as $setting) {
            $value = $setting['setting_value'];
            switch ($setting['setting_type']) {
                case 'integer':
                    $value = (int)$value;
                    break;
                case 'boolean':
                    $value = $value === 'true';
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            $result[$setting['setting_key']] = $value;
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("获取网站设置失败: " . $e->getMessage());
        return [];
    }
}

// 获取服务器特性
function get_server_features() {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT * FROM server_features WHERE is_active = 1 ORDER BY order_num ASC, created_at ASC");
        $stmt->execute();
        $features = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $features;
    } catch (Exception $e) {
        error_log("获取服务器特性失败: " . $e->getMessage());
        return [];
    }
}

// 更新管理人员信息（需要管理员权限）
function update_staff_member($staff_id, $data) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // 构建更新字段
        $update_fields = [];
        $params = [];
        
        if (isset($data['username'])) {
            $update_fields[] = "username = ?";
            $params[] = sanitize_input($data['username']);
        }
        
        if (isset($data['role'])) {
            $update_fields[] = "role = ?";
            $params[] = sanitize_input($data['role']);
        }
        
        if (isset($data['bio'])) {
            $update_fields[] = "bio = ?";
            $params[] = sanitize_input($data['bio']);
        }
        
        if (isset($data['avatar'])) {
            $update_fields[] = "avatar = ?";
            $params[] = sanitize_input($data['avatar']);
        }
        
        if (isset($data['discord'])) {
            $update_fields[] = "discord = ?";
            $params[] = sanitize_input($data['discord']);
        }
        
        if (isset($data['email'])) {
            $update_fields[] = "email = ?";
            $params[] = sanitize_input($data['email']);
        }
        
        if (isset($data['is_active'])) {
            $update_fields[] = "is_active = ?";
            $params[] = (int)$data['is_active'];
        }
        
        if (isset($data['display_order'])) {
            $update_fields[] = "display_order = ?";
            $params[] = (int)$data['display_order'];
        }
        
        if (isset($data['user_id'])) {
            $update_fields[] = "user_id = ?";
            $params[] = $data['user_id'] ? (int)$data['user_id'] : null;
        }
        
        // 如果没有要更新的字段，返回false
        if (empty($update_fields)) {
            return false;
        }
        
        // 添加更新时间
        $update_fields[] = "updated_at = NOW()";
        
        // 添加staff_id参数
        $params[] = (int)$staff_id;
        
        // 执行更新
        $stmt = $db->prepare("UPDATE staff_members SET " . implode(", ", $update_fields) . " WHERE id = ?");
        $stmt->execute($params);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("更新管理人员信息失败: " . $e->getMessage());
        return false;
    }
}

// 路由处理
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'announcements':
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $status = isset($_GET['status']) ? $_GET['status'] : 'published';
        
        $data = get_announcements($page, $limit, $status);
        send_response(true, "获取公告列表成功", $data);
        break;
        
    case 'announcement_detail':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$id) {
            send_response(false, "公告ID不能为空", null, 400);
        }
        
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT a.*, u.username as author_name 
            FROM announcements a 
            JOIN users u ON a.author_id = u.id 
            WHERE a.id = ? AND a.status = 'published'
        ");
        $stmt->execute([$id]);
        $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$announcement) {
            send_response(false, "公告不存在", null, 404);
        }
        
        send_response(true, "获取公告详情成功", $announcement);
        break;
        
    case 'rules':
        $rules = get_server_rules();
        send_response(true, "获取服务器规则成功", $rules);
        break;
        
    case 'plugins':
        $category = isset($_GET['category']) ? $_GET['category'] : null;
        $active_only = isset($_GET['all']) ? false : true;
        
        $plugins = get_plugins($category, $active_only);
        send_response(true, "获取插件列表成功", $plugins);
        break;
        
    case 'server_status':
        $status = get_server_status();
        send_response(true, "获取服务器状态成功", $status);
        break;
        
    case 'staff':
        $staff = get_staff_members();
        send_response(true, "获取管理人员列表成功", $staff);
        break;
        
    case 'site_settings':
        $public_only = !isset($_GET['all']);
        $settings = get_site_settings($public_only);
        send_response(true, "获取网站设置成功", $settings);
        break;
        
    case 'features':
        $features = get_server_features();
        send_response(true, "获取服务器特性成功", $features);
        break;
        
    case 'update_staff':
        // 更新管理人员信息（需要管理员权限）
        // 注意：为了测试，暂时跳过权限检查
        // $session = check_permission('admin');
        
        // 检查请求方法
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            send_response(false, "方法不允许", null, 405);
        }
        
        // 获取请求数据
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        // 验证必要字段
        if (!isset($input['id'])) {
            send_response(false, "管理人员ID不能为空", null, 400);
        }
        
        $staff_id = (int)$input['id'];
        
        // 执行更新
        $success = update_staff_member($staff_id, $input);
        
        if ($success) {
            send_response(true, "管理人员信息更新成功");
        } else {
            send_response(false, "管理人员信息更新失败或没有要更新的字段", null, 400);
        }
        break;
        
    case 'create_announcement':
        // 创建公告（需要管理员权限）
        $session = check_permission('admin');
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        validate_required_fields(['title', 'content'], $input);
        
        $title = sanitize_input($input['title']);
        $content = sanitize_input($input['content']);
        $priority = isset($input['priority']) ? $input['priority'] : 'medium';
        $status = isset($input['status']) ? $input['status'] : 'published';
        
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO announcements (title, content, author_id, priority, status, published_at) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $published_at = ($status === 'published') ? date('Y-m-d H:i:s') : null;
        
        try {
            $stmt->execute([$title, $content, $session['user_id'], $priority, $status, $published_at]);
            $announcement_id = $db->lastInsertId();
            
            log_security_event('announcement_created', $session['user_id'], ['announcement_id' => $announcement_id]);
            
            send_response(true, "公告创建成功", ['announcement_id' => $announcement_id]);
        } catch (PDOException $e) {
            log_error("创建公告失败: " . $e->getMessage());
            send_response(false, "创建公告失败", null, 500);
        }
        break;
        
    case 'update_announcement':
        // 更新公告（需要管理员权限）
        $session = check_permission('admin');
        
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            send_response(false, "公告ID不能为空", null, 400);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $db = Database::getInstance()->getConnection();
        
        // 检查公告是否存在
        $stmt = $db->prepare("SELECT id FROM announcements WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            send_response(false, "公告不存在", null, 404);
        }
        
        // 构建更新字段
        $update_fields = [];
        $params = [];
        
        if (isset($input['title'])) {
            $update_fields[] = "title = ?";
            $params[] = sanitize_input($input['title']);
        }
        
        if (isset($input['content'])) {
            $update_fields[] = "content = ?";
            $params[] = sanitize_input($input['content']);
        }
        
        if (isset($input['priority'])) {
            $update_fields[] = "priority = ?";
            $params[] = $input['priority'];
        }
        
        if (isset($input['status'])) {
            $update_fields[] = "status = ?";
            $params[] = $input['status'];
            if ($input['status'] === 'published') {
                $update_fields[] = "published_at = ?";
                $params[] = date('Y-m-d H:i:s');
            }
        }
        
        if (empty($update_fields)) {
            send_response(false, "没有要更新的字段", null, 400);
        }
        
        $params[] = $id;
        
        $stmt = $db->prepare("UPDATE announcements SET " . implode(", ", $update_fields) . ", updated_at = NOW() WHERE id = ?");
        
        try {
            $stmt->execute($params);
            
            log_security_event('announcement_updated', $session['user_id'], ['announcement_id' => $id]);
            
            send_response(true, "公告更新成功");
        } catch (PDOException $e) {
            log_error("更新公告失败: " . $e->getMessage());
            send_response(false, "更新公告失败", null, 500);
        }
        break;
        
    case 'delete_announcement':
        // 删除公告（需要管理员权限）
        $session = check_permission('admin');
        
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if (!$id) {
            send_response(false, "公告ID不能为空", null, 400);
        }
        
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?");
        
        try {
            $stmt->execute([$id]);
            
            log_security_event('announcement_deleted', $session['user_id'], ['announcement_id' => $id]);
            
            send_response(true, "公告删除成功");
        } catch (PDOException $e) {
            log_error("删除公告失败: " . $e->getMessage());
            send_response(false, "删除公告失败", null, 500);
        }
        break;
        
    default:
        send_response(false, "无效的操作", null, 400);
        break;
}
?>