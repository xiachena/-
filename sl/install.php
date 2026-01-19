<?php
/**
 * SL铸币服务器网站安装脚本
 * 运行此脚本来自动设置数据库和配置文件
 */

header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$errors = [];
$messages = [];

// 检查PHP版本
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die('错误：需要PHP 7.4或更高版本。当前版本：' . PHP_VERSION);
}

// 检查必需扩展
$required_extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
$missing_extensions = [];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    die('错误：缺少必需的PHP扩展：' . implode(', ', $missing_extensions));
}

function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function test_database_connection($host, $dbname, $user, $pass) {
    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return ['success' => true, 'connection' => $pdo];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function create_config_file($config_data) {
    $config_content = "<?php\n";
    $config_content .= "/**\n";
    $config_content .= " * SL铸币服务器配置文件\n";
    $config_content .= " * 自动生成于 " . date('Y-m-d H:i:s') . "\n";
    $config_content .= " */\n\n";
    
    $config_content .= "// 数据库配置\n";
    $config_content .= "define('DB_HOST', '" . addslashes($config_data['db_host']) . "');\n";
    $config_content .= "define('DB_NAME', '" . addslashes($config_data['db_name']) . "');\n";
    $config_content .= "define('DB_USER', '" . addslashes($config_data['db_user']) . "');\n";
    $config_content .= "define('DB_PASS', '" . addslashes($config_data['db_pass']) . "');\n\n";
    
    $config_content .= "// 网站配置\n";
    $config_content .= "define('SITE_NAME', '" . addslashes($config_data['site_name']) . "');\n";
    $config_content .= "define('SITE_URL', '" . addslashes($config_data['site_url']) . "');\n";
    $config_content .= "define('SERVER_IP', '" . addslashes($config_data['server_ip']) . "');\n";
    $config_content .= "define('SERVER_PORT', '" . addslashes($config_data['server_port']) . "');\n\n";
    
    $config_content .= "// 安全配置\n";
    $config_content .= "define('SECRET_KEY', '" . bin2hex(random_bytes(32)) . "');\n";
    $config_content .= "define('SESSION_LIFETIME', 3600);\n";
    $config_content .= "define('PASSWORD_MIN_LENGTH', 8);\n";
    $config_content .= "define('MAX_LOGIN_ATTEMPTS', 5);\n";
    $config_content .= "define('LOCKOUT_TIME', 900); // 15分钟\n\n";
    
    $config_content .= "// 邮件配置\n";
    $config_content .= "define('MAIL_HOST', '" . addslashes($config_data['mail_host']) . "');\n";
    $config_content .= "define('MAIL_PORT', '" . addslashes($config_data['mail_port']) . "');\n";
    $config_content .= "define('MAIL_USER', '" . addslashes($config_data['mail_user']) . "');\n";
    $config_content .= "define('MAIL_PASS', '" . addslashes($config_data['mail_pass']) . "');\n";
    $config_content .= "define('MAIL_FROM', '" . addslashes($config_data['mail_from']) . "');\n\n";
    
    $config_content .= "// 其他配置\n";
    $config_content .= "define('DEBUG_MODE', " . ($config_data['debug_mode'] ? 'true' : 'false') . ");\n";
    $config_content .= "define('MAINTENANCE_MODE', false);\n";
    $config_content .= "define('ALLOW_REGISTRATION', " . ($config_data['allow_registration'] ? 'true' : 'false') . ");\n";
    $config_content .= "define('REQUIRE_EMAIL_VERIFICATION', " . ($config_data['require_email_verification'] ? 'true' : 'false') . ");\n\n";
    
    $config_content .= "// 时区设置\n";
    $config_content .= "date_default_timezone_set('Asia/Shanghai');\n\n";
    
    $config_content .= "// 错误处理\n";
    $config_content .= "if (DEBUG_MODE) {\n";
    $config_content .= "    error_reporting(E_ALL);\n";
    $config_content .= "    ini_set('display_errors', 1);\n";
    $config_content .= "} else {\n";
    $config_content .= "    error_reporting(0);\n";
    $config_content .= "    ini_set('display_errors', 0);\n";
    $config_content .= "}\n\n";
    
    $config_content .= "// 数据库连接函数\n";
    $config_content .= "function getDbConnection() {\n";
    $config_content .= "    try {\n";
    $config_content .= "        \\$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';\n";
    $config_content .= "        \\$options = [\n";
    $config_content .= "            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,\n";
    $config_content .= "            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n";
    $config_content .= "            PDO::ATTR_EMULATE_PREPARES => false,\n";
    $config_content .= "        ];\n";
    $config_content .= "        return new PDO(\\$dsn, DB_USER, DB_PASS, \\$options);\n";
    $config_content .= "    } catch (PDOException \\$e) {\n";
    $config_content .= "        error_log('数据库连接失败: ' . \\$e->getMessage());\n";
    $config_content .= "        return null;\n";
    $config_content .= "    }\n";
    $config_content .= "}\n\n";
    
    $config_content .= "// 安全函数\n";
    $config_content .= "function sanitize_input(\\$data) {\n";
    $config_content .= "    return htmlspecialchars(strip_tags(trim(\\$data)), ENT_QUOTES, 'UTF-8');\n";
    $config_content .= "}\n\n";
    
    $config_content .= "function generate_csrf_token() {\n";
    $config_content .= "    if (!isset(\\$_SESSION['csrf_token'])) {\n";
    $config_content .= "        \\$_SESSION['csrf_token'] = bin2hex(random_bytes(32));\n";
    $config_content .= "    }\n";
    $config_content .= "    return \\$_SESSION['csrf_token'];\n";
    $config_content .= "}\n\n";
    
    $config_content .= "function verify_csrf_token(\\$token) {\n";
    $config_content .= "    return isset(\\$_SESSION['csrf_token']) && hash_equals(\\$_SESSION['csrf_token'], \\$token);\n";
    $config_content .= "}\n\n";
    
    $config_content .= "// 响应函数\n";
    $config_content .= "function send_response(\\$success, \\$message, \\$data = null, \\$code = 200) {\n";
    $config_content .= "    http_response_code(\\$code);\n";
    $config_content .= "    header('Content-Type: application/json; charset=utf-8');\n";
    $config_content .= "    echo json_encode([\n";
    $config_content .= "        'success' => \\$success,\n";
    $config_content .= "        'message' => \\$message,\n";
    $config_content .= "        'data' => \\$data,\n";
    $config_content .= "        'timestamp' => time()\n";
    $config_content .= "    ]);\n";
    $config_content .= "    exit;\n";
    $config_content .= "}\n\n";
    
    $config_content .= "// 日志函数\n";
    $config_content .= "function log_message(\\$message, \\$level = 'info') {\n";
    $config_content .= "    \\$log_dir = __DIR__ . '/logs';\n";
    $config_content .= "    if (!is_dir(\\$log_dir)) {\n";
    $config_content .= "        mkdir(\\$log_dir, 0755, true);\n";
    $config_content .= "    }\n";
    $config_content .= "    \\$log_file = \\$log_dir . '/' . date('Y-m-d') . '.log';\n";
    $config_content .= "    \\$message = date('Y-m-d H:i:s') . ' [' . strtoupper(\\$level) . '] ' . \\$message . PHP_EOL;\n";
    $config_content .= "    error_log(\\$message, 3, \\$log_file);\n";
    $config_content .= "}\n\n";
    
    $config_content .= "// 启动会话\n";
    $config_content .= "if (session_status() === PHP_SESSION_NONE) {\n";
    $config_content .= "    session_start([\n";
    $config_content .= "        'cookie_lifetime' => SESSION_LIFETIME,\n";
    $config_content .= "        'cookie_secure' => isset(\\$_SERVER['HTTPS']),\n";
    $config_content .= "        'cookie_httponly' => true,\n";
    $config_content .= "        'cookie_samesite' => 'Lax'\n";
    $config_content .= "    ]);\n";
    $config_content .= "}\n";
    
    return file_put_contents('config.php', $config_content);
}

function create_database_tables($pdo) {
    try {
        // 读取SQL文件
        $sql_file = file_get_contents('database.sql');
        if (!$sql_file) {
            return ['success' => false, 'error' => '无法读取database.sql文件'];
        }
        
        // 执行SQL语句
        $pdo->exec($sql_file);
        
        // 插入默认数据
        $default_data = [
            // 默认管理员账户
            "INSERT INTO users (username, email, password, role, is_active, created_at) VALUES 
             ('admin', 'admin@example.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'owner', 1, NOW())",
            
            // 默认网站设置
            "INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES 
             ('site_name', 'SL铸币服务器', NOW()),
             ('server_ip', 'play.slserver.com', NOW()),
             ('server_port', '25565', NOW()),
             ('maintenance_mode', '0', NOW()),
             ('allow_registration', '1', NOW()),
             ('require_email_verification', '0', NOW())",
            
            // 默认公告
            "INSERT INTO announcements (title, content, priority, author_id, created_at) VALUES 
             ('欢迎来到SL铸币服务器！', '这是您的第一个公告。请编辑此公告以提供重要信息给您的玩家。', 'high', 1, NOW())",
            
            // 默认规则
            "INSERT INTO server_rules (category, title, description, severity, punishment, created_at) VALUES 
             ('general', '尊重其他玩家', '请尊重所有玩家，禁止辱骂、骚扰或歧视行为。', 'moderate', '警告或临时封禁', NOW()),
             ('general', '禁止作弊', '使用任何作弊工具、外挂或利用漏洞都是严格禁止的。', 'severe', '永久封禁', NOW()),
             ('gameplay', '禁止恶意破坏', '故意破坏其他玩家的建筑或财产是不允许的。', 'severe', '赔偿损失并封禁', NOW())",
            
            // 默认插件
            "INSERT INTO server_plugins (name, description, version, author, is_active, config_url, created_at) VALUES 
             ('EssentialsX', '基础插件，提供基本的服务器功能', '2.19.0', 'EssentialsX Team', 1, 'https://essentialsx.net/', NOW()),
             ('WorldEdit', '世界编辑工具', '7.2.10', 'EngineHub', 1, 'https://enginehub.org/worldedit/', NOW()),
             ('Vault', '经济插件前置', '1.7.3', 'Sleakes', 1, 'https://github.com/MilkBowl/Vault', NOW())",
            
            // 默认管理人员
            "INSERT INTO staff_members (username, role, bio, avatar, discord, email, is_active, created_at) VALUES 
             ('Admin', 'owner', '服务器创始人，负责整体管理和技术支持', 'https://via.placeholder.com/100', 'admin#1234', 'admin@slserver.com', 1, NOW())"
        ];
        
        foreach ($default_data as $sql) {
            $pdo->exec($sql);
        }
        
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            // 数据库配置
            $db_host = sanitize_input($_POST['db_host'] ?? 'localhost');
            $db_name = sanitize_input($_POST['db_name'] ?? 'sl_server_db');
            $db_user = sanitize_input($_POST['db_user'] ?? '');
            $db_pass = sanitize_input($_POST['db_pass'] ?? '');
            
            if (empty($db_user)) {
                $errors[] = '数据库用户名不能为空';
            }
            
            if (empty($errors)) {
                // 测试数据库连接
                $test_result = test_database_connection($db_host, $db_name, $db_user, $db_pass);
                if (!$test_result['success']) {
                    $errors[] = '数据库连接失败: ' . $test_result['error'];
                } else {
                    $_SESSION['install_data'] = [
                        'db_host' => $db_host,
                        'db_name' => $db_name,
                        'db_user' => $db_user,
                        'db_pass' => $db_pass
                    ];
                    header('Location: install.php?step=3');
                    exit;
                }
            }
            break;
            
        case 3:
            // 网站配置
            $site_name = sanitize_input($_POST['site_name'] ?? 'SL铸币服务器');
            $site_url = sanitize_input($_POST['site_url'] ?? 'http://localhost');
            $server_ip = sanitize_input($_POST['server_ip'] ?? 'play.slserver.com');
            $server_port = sanitize_input($_POST['server_port'] ?? '25565');
            
            $_SESSION['install_data'] = array_merge($_SESSION['install_data'] ?? [], [
                'site_name' => $site_name,
                'site_url' => $site_url,
                'server_ip' => $server_ip,
                'server_port' => $server_port
            ]);
            
            header('Location: install.php?step=4');
            exit;
            break;
            
        case 4:
            // 邮件配置
            $mail_host = sanitize_input($_POST['mail_host'] ?? 'smtp.gmail.com');
            $mail_port = sanitize_input($_POST['mail_port'] ?? '587');
            $mail_user = sanitize_input($_POST['mail_user'] ?? '');
            $mail_pass = sanitize_input($_POST['mail_pass'] ?? '');
            $mail_from = sanitize_input($_POST['mail_from'] ?? 'noreply@slserver.com');
            
            $_SESSION['install_data'] = array_merge($_SESSION['install_data'] ?? [], [
                'mail_host' => $mail_host,
                'mail_port' => $mail_port,
                'mail_user' => $mail_user,
                'mail_pass' => $mail_pass,
                'mail_from' => $mail_from
            ]);
            
            header('Location: install.php?step=5');
            exit;
            break;
            
        case 5:
            // 高级配置
            $debug_mode = isset($_POST['debug_mode']);
            $allow_registration = isset($_POST['allow_registration']);
            $require_email_verification = isset($_POST['require_email_verification']);
            
            $config_data = array_merge($_SESSION['install_data'] ?? [], [
                'debug_mode' => $debug_mode,
                'allow_registration' => $allow_registration,
                'require_email_verification' => $require_email_verification
            ]);
            
            // 创建配置文件
            if (create_config_file($config_data)) {
                // 创建数据库表
                try {
                    $pdo = new PDO(
                        "mysql:host={$config_data['db_host']};dbname={$config_data['db_name']};charset=utf8mb4",
                        $config_data['db_user'],
                        $config_data['db_pass'],
                        [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                        ]
                    );
                    
                    $result = create_database_tables($pdo);
                    if ($result['success']) {
                        $messages[] = '安装成功！';
                        $messages[] = '管理员密码: ' $result['admin_password'];
                        $messages[] = '请立即删除install.php文件以确保安全。';
                        $step = 6;
                    } else {
                        $errors[] = '创建数据库表失败: ' . $result['error'];
                    }
                } catch (PDOException $e) {
                    $errors[] = '数据库操作失败: ' . $e->getMessage();
                }
            } else {
                $errors[] = '创建配置文件失败';
            }
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SL铸币服务器 - 安装向导</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .install-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        
        .install-header {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .install-header h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .progress-bar {
            background: rgba(255,255,255,0.3);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 1rem;
        }
        
        .progress-fill {
            background: white;
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .install-body {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #FFD700;
        }
        
        .btn {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 215, 0, 0.3);
        }
        
        .error {
            background: #fee;
            color: #c33;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .success {
            background: #efe;
            color: #3c3;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        
        .step {
            flex: 1;
            text-align: center;
            padding: 0.5rem;
            background: #f0f0f0;
            border-radius: 8px;
            margin: 0 0.25rem;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .step.active {
            background: linear-gradient(135deg, #FFD700, #FFA500);
            color: white;
        }
        
        .step.completed {
            background: #4CAF50;
            color: white;
        }
        
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #FFD700;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0 8px 8px 0;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1>SL铸币服务器安装向导</h1>
            <p>步骤 <?php echo $step; ?> / 6</p>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo ($step / 6) * 100; ?>%"></div>
            </div>
        </div>
        
        <div class="install-body">
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($messages)): ?>
                <div class="success">
                    <?php foreach ($messages as $message): ?>
                        <p><?php echo $message; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">1. 欢迎</div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">2. 数据库</div>
                <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3. 网站</div>
                <div class="step <?php echo $step >= 4 ? 'active' : ''; ?>">4. 邮件</div>
                <div class="step <?php echo $step >= 5 ? 'active' : ''; ?>">5. 高级</div>
                <div class="step <?php echo $step >= 6 ? 'active' : ''; ?>">6. 完成</div>
            </div>
            
            <?php if ($step === 1): ?>
                <div class="info-box">
                    <h3>欢迎使用SL铸币服务器安装向导！</h3>
                    <p>此向导将帮助您完成网站的安装和配置。请确保您已准备好以下信息：</p>
                    <ul style="margin-top: 1rem; margin-left: 1.5rem;">
                        <li>MySQL数据库信息（主机、数据库名、用户名、密码）</li>
                        <li>网站基本信息（名称、URL）</li>
                        <li>服务器信息（IP地址、端口）</li>
                        <li>邮件服务器信息（可选）</li>
                    </ul>
                </div>
                
                <form method="get">
                    <input type="hidden" name="step" value="2">
                    <button type="submit" class="btn">开始安装</button>
                </form>
                
            <?php elseif ($step === 2): ?>
                <h3>数据库配置</h3>
                <form method="post">
                    <div class="form-group">
                        <label class="form-label">数据库主机</label>
                        <input type="text" name="db_host" class="form-input" value="localhost" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">数据库名称</label>
                        <input type="text" name="db_name" class="form-input" value="sl_server_db" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">数据库用户名</label>
                        <input type="text" name="db_user" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">数据库密码</label>
                        <input type="password" name="db_pass" class="form-input" required>
                    </div>
                    
                    <button type="submit" class="btn">下一步</button>
                </form>
                
            <?php elseif ($step === 3): ?>
                <h3>网站配置</h3>
                <form method="post">
                    <div class="form-group">
                        <label class="form-label">网站名称</label>
                        <input type="text" name="site_name" class="form-input" value="SL铸币服务器" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">网站URL</label>
                        <input type="url" name="site_url" class="form-input" value="http://localhost" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">服务器IP地址</label>
                        <input type="text" name="server_ip" class="form-input" value="play.slserver.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">服务器端口</label>
                        <input type="number" name="server_port" class="form-input" value="25565" required>
                    </div>
                    
                    <button type="submit" class="btn">下一步</button>
                </form>
                
            <?php elseif ($step === 4): ?>
                <h3>邮件配置（可选）</h3>
                <div class="info-box">
                    <p>邮件配置用于用户注册验证、密码重置等功能。如果您暂时不需要这些功能，可以跳过此步骤。</p>
                </div>
                
                <form method="post">
                    <div class="form-group">
                        <label class="form-label">SMTP主机</label>
                        <input type="text" name="mail_host" class="form-input" value="smtp.gmail.com">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">SMTP端口</label>
                        <input type="number" name="mail_port" class="form-input" value="587">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">SMTP用户名</label>
                        <input type="text" name="mail_user" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">SMTP密码</label>
                        <input type="password" name="mail_pass" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">发件人邮箱</label>
                        <input type="email" name="mail_from" class="form-input" value="noreply@slserver.com">
                    </div>
                    
                    <button type="submit" class="btn">下一步</button>
                </form>
                
            <?php elseif ($step === 5): ?>
                <h3>高级配置</h3>
                <form method="post">
                    <div class="checkbox-group">
                        <input type="checkbox" id="debug_mode" name="debug_mode">
                        <label for="debug_mode">启用调试模式</label>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="allow_registration" name="allow_registration" checked>
                        <label for="allow_registration">允许用户注册</label>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="require_email_verification" name="require_email_verification">
                        <label for="require_email_verification">需要邮箱验证</label>
                    </div>
                    
                    <div class="info-box">
                        <p><strong>调试模式：</strong>启用后会显示详细的错误信息，仅建议在开发环境中使用。</p>
                        <p><strong>用户注册：</strong>允许新用户注册账户。</p>
                        <p><strong>邮箱验证：</strong>要求用户验证邮箱地址后才能登录。</p>
                    </div>
                    
                    <button type="submit" class="btn">完成安装</button>
                </form>
                
            <?php elseif ($step === 6): ?>
                <h3>安装完成！</h3>
                <div class="success">
                    <p>🎉 恭喜！SL铸币服务器网站已成功安装。</p>
                </div>
                
                <div class="info-box">
                    <h4>重要提醒：</h4>
                    <ol style="margin-top: 1rem; margin-left: 1.5rem;">
                        <li><strong>删除安装文件：</strong>请立即删除install.php文件以确保安全。</li>
                        <li><strong>管理员账户：</strong>默认管理员用户名：admin</li>
                        <li><strong>管理员密码：</strong>请查看上方成功消息中的随机密码</li>
                        <li><strong>安全建议：</strong>请立即修改管理员密码。</li>
                        <li><strong>配置文件：</strong>config.php文件已创建，包含所有配置信息。</li>
                    </ol>
                </div>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="index.html" class="btn" style="display: inline-block; text-decoration: none;">访问网站</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>