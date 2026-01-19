<?php
require_once '../config.php';

// 设置安全响应头
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// 限制CORS - 只允许特定来源
$allowed_origins = [
    'http://localhost',
    'https://slserver.com',
    'https://www.slserver.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: none");
}

header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token");
header("Access-Control-Max-Age: 86400");

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 验证CSRF令牌
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'CSRF令牌验证失败'
        ]);
        exit();
    }
}

try {
    // 输入验证和清理
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $limit = max(1, min($limit, 50)); // 限制在1-50之间
    
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $offset = max(0, $offset); // 确保非负
    
    $status = isset($_GET['status']) ? sanitize_input($_GET['status']) : 'published';
    $allowed_statuses = ['published', 'draft', 'archived'];
    if (!in_array($status, $allowed_statuses)) {
        $status = 'published';
    }

    // 获取公告列表
    $db = Database::getInstance()->getConnection();
    error_log("Debug: 正在执行公告查询，参数: status=$status, limit=$limit, offset=$offset");
    $stmt = $db->prepare("
        SELECT a.id, a.title, a.content, a.priority, a.author_id, a.created_at, a.updated_at, u.username as author_name 
        FROM announcements a 
        JOIN users u ON a.author_id = u.id 
        WHERE a.status = ? AND a.is_active = 1 
        ORDER BY a.priority DESC, a.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    
    $stmt->bindValue(1, $status);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 获取总数
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM announcements WHERE status = ? AND is_active = 1");
    $count_stmt->execute([$status]);
    $total = $count_stmt->fetch()['total'];

    // 格式化数据
    $formattedAnnouncements = [];
    foreach ($announcements as $announcement) {
        $formattedAnnouncements[] = [
            'id' => (int)$announcement['id'],
            'title' => htmlspecialchars($announcement['title'], ENT_QUOTES, 'UTF-8'),
            'content' => htmlspecialchars($announcement['content'], ENT_QUOTES, 'UTF-8'),
            'priority' => htmlspecialchars($announcement['priority'], ENT_QUOTES, 'UTF-8'),
            'author_name' => htmlspecialchars($announcement['author_name'], ENT_QUOTES, 'UTF-8'),
            'created_at' => date('Y-m-d H:i', strtotime($announcement['created_at'])),
            'updated_at' => date('Y-m-d H:i', strtotime($announcement['updated_at']))
        ];
    }

    // 记录安全日志
    log_security_event('announcements_viewed', null, ['limit' => $limit, 'offset' => $offset]);

    echo json_encode([
        'success' => true,
        'announcements' => $formattedAnnouncements,
        'count' => count($formattedAnnouncements),
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset
    ]);

} catch (PDOException $e) {
    error_log("获取公告错误: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '获取公告失败',
        'announcements' => [],
        'count' => 0,
        'total' => 0
    ]);
} catch (Exception $e) {
    error_log("获取公告错误: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '获取公告失败',
        'announcements' => [],
        'count' => 0,
        'total' => 0
    ]);
}
?>