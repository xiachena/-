<?php
/**
 * SL铸币服务器 - 安全错误处理页面
 * 处理所有HTTP错误并提供安全的错误信息
 */

// 包含配置文件
require_once 'config.php';

// 设置内容类型头
header('Content-Type: text/html; charset=utf-8');

// 覆盖某些配置头
header('Referrer-Policy: strict-origin-when-cross-origin'); // 确保使用正确的引用策略

// 获取错误代码
$error_code = isset($_GET['code']) ? intval($_GET['code']) : 500;

// 定义错误信息
$error_messages = [
    400 => [
        'title' => '错误请求',
        'message' => '服务器无法理解您的请求，请检查请求格式是否正确。',
        'description' => 'HTTP 400 - 错误的请求'
    ],
    401 => [
        'title' => '未授权',
        'message' => '您需要登录才能访问此资源。',
        'description' => 'HTTP 401 - 未授权访问'
    ],
    403 => [
        'title' => '禁止访问',
        'message' => '您没有权限访问此资源。',
        'description' => 'HTTP 403 - 禁止访问'
    ],
    404 => [
        'title' => '页面未找到',
        'message' => '您请求的页面不存在或已被移动。',
        'description' => 'HTTP 404 - 页面未找到'
    ],
    405 => [
        'title' => '方法不允许',
        'message' => '请求的方法不被允许。',
        'description' => 'HTTP 405 - 方法不允许'
    ],
    429 => [
        'title' => '请求过于频繁',
        'message' => '您的请求过于频繁，请稍后再试。',
        'description' => 'HTTP 429 - 请求限制'
    ],
    500 => [
        'title' => '服务器内部错误',
        'message' => '服务器遇到了意外错误，请稍后再试。',
        'description' => 'HTTP 500 - 内部服务器错误'
    ],
    503 => [
        'title' => '服务不可用',
        'message' => '服务器暂时无法处理您的请求，请稍后再试。',
        'description' => 'HTTP 503 - 服务不可用'
    ]
];

// 获取错误信息
$error_info = isset($error_messages[$error_code]) ? $error_messages[$error_code] : $error_messages[500];

// 设置HTTP状态码
http_response_code($error_code);

// 记录错误（如果可能）
if (function_exists('log_message')) {
    log_message("HTTP {$error_code} 错误: " . $_SERVER['REQUEST_URI'], 'error');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($error_info['title']); ?> - SL铸币服务器</title>
    <meta name="description" content="<?php echo htmlspecialchars($error_info['description']); ?>">
    
    <!-- 安全资源加载 -->
    <link href="https://cdn.bootcdn.net/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: 195 100% 50%;
            --secondary: 280 100% 50%;
            --background: 220 40% 5%;
            --foreground: 220 20% 95%;
            --card: 220 35% 10%;
            --border: 195 100% 30% / 0.3;
            --gradient-primary: linear-gradient(135deg, hsl(var(--primary)), hsl(var(--secondary)));
            --shadow-neon: 0 0 20px hsl(var(--primary) / 0.5), 0 0 40px hsl(var(--secondary) / 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Exo 2', sans-serif;
            background: linear-gradient(180deg, hsl(220 40% 3%), hsl(220 40% 8%));
            color: hsl(var(--foreground));
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            background: hsl(var(--card));
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 3rem;
            max-width: 600px;
            width: 100%;
            text-align: center;
            box-shadow: var(--shadow-neon);
            position: relative;
            overflow: hidden;
        }

        .error-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-primary);
        }

        .error-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .error-code {
            font-family: 'Orbitron', monospace;
            font-size: 6rem;
            font-weight: 900;
            margin-bottom: 1rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: var(--shadow-neon);
        }

        .error-title {
            font-family: 'Orbitron', monospace;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: hsl(var(--primary));
        }

        .error-message {
            font-size: 1.1rem;
            color: hsl(var(--foreground) / 0.8);
            margin-bottom: 2rem;
        }

        .error-description {
            font-size: 0.9rem;
            color: hsl(var(--foreground) / 0.6);
            margin-bottom: 2rem;
        }

        .error-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 4px 15px hsl(var(--primary) / 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px hsl(var(--primary) / 0.4);
        }

        .btn-outline {
            background: transparent;
            color: hsl(var(--primary));
            border: 2px solid hsl(var(--primary));
        }

        .btn-outline:hover {
            background: hsl(var(--primary));
            color: white;
        }

        .error-details {
            margin-top: 2rem;
            padding: 1rem;
            background: hsl(var(--background));
            border-radius: 8px;
            font-size: 0.8rem;
            color: hsl(var(--foreground) / 0.5);
            font-family: 'Courier New', monospace;
        }

        @media (max-width: 768px) {
            .error-container {
                padding: 2rem;
            }
            
            .error-code {
                font-size: 4rem;
            }
            
            .error-title {
                font-size: 1.5rem;
            }
            
            .error-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* 动画效果 */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-container {
            animation: fadeIn 0.6s ease-out;
        }

        /* 加载动画 */
        .loading-dots {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid hsl(var(--foreground) / 0.3);
            border-radius: 50%;
            border-top-color: hsl(var(--primary));
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        
        <div class="error-code"><?php echo $error_code; ?></div>
        
        <h1 class="error-title"><?php echo htmlspecialchars($error_info['title']); ?></h1>
        
        <p class="error-message"><?php echo htmlspecialchars($error_info['message']); ?></p>
        
        <p class="error-description"><?php echo htmlspecialchars($error_info['description']); ?></p>
        
        <div class="error-actions">
            <a href="javascript:history.back()" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i>
                返回上页
            </a>
            
            <a href="/" class="btn btn-primary">
                <i class="fas fa-home"></i>
                返回首页
            </a>
            
            <button onclick="location.reload()" class="btn btn-outline">
                <i class="fas fa-sync-alt"></i>
                刷新页面
            </button>
        </div>
        
        <?php if (DEBUG_MODE && !empty($_SERVER['REQUEST_URI'])): ?>
        <div class="error-details">
            <strong>调试信息：</strong><br>
            请求URI: <?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?><br>
            请求方法: <?php echo htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'GET'); ?><br>
            用户代理: <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'); ?><br>
<?php
            if (function_exists('get_client_ip')) {
                echo '客户端IP: ' . htmlspecialchars(get_client_ip()) . '<br>';
            }
            if (function_exists('log_error')) {
                log_error("HTTP {$error_code} 错误详情 - URI: " . $_SERVER['REQUEST_URI'] . ", IP: " . (function_exists('get_client_ip') ? get_client_ip() : 'Unknown'));
            }
?>
            时间: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // 自动重试机制
        let retryCount = 0;
        const maxRetries = 3;
        
        function autoRetry() {
            if (retryCount < maxRetries) {
                retryCount++;
                setTimeout(() => {
                    location.reload();
                }, 5000); // 5秒后自动重试
            }
        }

        // 对于5xx错误，尝试自动重试
        <?php if ($error_code >= 500): ?>
        autoRetry();
        <?php endif; ?>

        // 键盘快捷键
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.close(); // 尝试关闭窗口（可能被浏览器阻止）
            } else if (e.key === 'r' && e.ctrlKey) {
                e.preventDefault();
                location.reload();
            } else if (e.key === 'h' && e.ctrlKey) {
                e.preventDefault();
                window.location.href = '/';
            }
        });

        // 防止点击劫持
        if (self !== top) {
            top.location = self.location;
        }
    </script>
</body>
</html>
<?php
// 确保没有输出其他内容
exit;
?>