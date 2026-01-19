<?php
require_once 'config.php';

// 设置页面标题
$page_title = '公告列表 - ' . SITE_NAME;

// 获取当前页码
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 10;

try {
    // 获取公告总数
    $db = Database::getInstance()->getConnection();
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM announcements WHERE status = 'published' AND is_active = 1");
    $count_stmt->execute();
    $total_announcements = $count_stmt->fetch()['total'];
    
    // 计算总页数
    $total_pages = max(1, ceil($total_announcements / $items_per_page));
    $current_page = min($current_page, $total_pages);
    
    // 计算偏移量
    $offset = ($current_page - 1) * $items_per_page;
    
    // 获取公告列表
    $stmt = $db->prepare("
        SELECT a.id, a.title, a.content, a.priority, a.author_id, a.created_at, a.updated_at, u.username as author_name 
        FROM announcements a 
        JOIN users u ON a.author_id = u.id 
        WHERE a.status = 'published' AND a.is_active = 1 
        ORDER BY a.priority DESC, a.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    
    $stmt->bindParam(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("获取公告列表错误: " . $e->getMessage());
    $announcements = [];
    $total_announcements = 0;
    $total_pages = 1;
    $current_page = 1;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars(SITE_NAME); ?> - 查看最新公告和游戏更新信息">
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .announcement-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .announcement-item:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .announcement-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #fff;
            margin: 0;
        }
        
        .announcement-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 1rem;
        }
        
        .announcement-content {
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
        }
        
        .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .priority-high {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
        }
        
        .priority-medium {
            background: linear-gradient(135deg, #feca57, #ff9ff3);
            color: white;
        }
        
        .priority-low {
            background: linear-gradient(135deg, #48dbfb, #0abde3);
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .pagination a {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .pagination .current {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: transparent;
        }
        
        .no-announcements {
            text-align: center;
            padding: 3rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            margin-bottom: 1rem;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: white;
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
    <nav class="navbar">
        <div class="container">
            <div class="navbar-content">
                <div class="logo">SL铸币服务器</div>
                <ul class="nav-links">
                    <li><a href="index.html">首页</a></li>
                    <li><a href="index.html#announcements">公告</a></li>
                    <li><a href="index.html#rules">服务器规则</a></li>
                    <li><a href="index.html#plugins">插件列表</a></li>
                    <li><a href="index.html#staff">管理人员</a></li>
                    <li><a href="index.html#contact">联系我们</a></li>
                </ul>
                <div class="nav-auth">
                    <a href="index.html" class="btn btn-outline">返回首页</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- 主内容 -->
    <main class="main-content">
        <div class="container">
            <div class="card">
                <div style="margin-bottom: 2rem;">
                    <a href="index.html" class="back-link">
                        <i class="fas fa-arrow-left"></i> 返回首页
                    </a>
                    <h1><i class="fas fa-bullhorn"></i> 服务器公告</h1>
                    <p style="color: rgba(255, 255, 255, 0.7);">查看最新的服务器更新、维护通知和重要消息</p>
                </div>

                <?php if (empty($announcements)): ?>
                    <div class="no-announcements">
                        <i class="fas fa-bell-slash" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>暂无公告</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <?php
                        $priority_class = '';
                        $priority_text = '';
                        switch ($announcement['priority']) {
                            case 'high':
                                $priority_class = 'priority-high';
                                $priority_text = '重要';
                                break;
                            case 'medium':
                                $priority_class = 'priority-medium';
                                $priority_text = '一般';
                                break;
                            case 'low':
                                $priority_class = 'priority-low';
                                $priority_text = '通知';
                                break;
                            default:
                                $priority_class = 'priority-low';
                                $priority_text = '通知';
                        }
                        ?>
                        <div class="announcement-item">
                            <div class="announcement-header">
                                <h3 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                <span class="priority-badge <?php echo $priority_class; ?>"><?php echo $priority_text; ?></span>
                            </div>
                            <div class="announcement-meta">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($announcement['author_name']); ?></span>
                                <span><i class="fas fa-calendar"></i> <?php echo date('Y年m月d日 H:i', strtotime($announcement['created_at'])); ?></span>
                                <?php if ($announcement['updated_at'] !== $announcement['created_at']): ?>
                                    <span><i class="fas fa-edit"></i> 更新于 <?php echo date('Y年m月d日 H:i', strtotime($announcement['updated_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="announcement-content">
                                <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($current_page > 1): ?>
                                <a href="?page=<?php echo $current_page - 1; ?>"><i class="fas fa-chevron-left"></i> 上一页</a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                <?php if ($i == $current_page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?php echo $current_page + 1; ?>">下一页 <i class="fas fa-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // 平滑滚动到顶部
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        // 页面加载完成后滚动到顶部
        window.addEventListener('load', function() {
            scrollToTop();
        });
    </script>
</body>
</html>