<?php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'sl_server_db');
define('DB_USER', 'root');
define('DB_PASS', 'inscode');

// 网站配置
define('SITE_NAME', 'SL铸币服务器');
define('SITE_URL', 'http://localhost');
define('SERVER_IP', 'play.slserver.com');
define('SERVER_VERSION', '1.20.1');

// 安全配置
define('SESSION_LIFETIME', 3600); // 会话有效期 1小时
define('CSRF_TOKEN_NAME', 'csrf_token');
define('MAX_LOGIN_ATTEMPTS', 5); // 最大登录尝试次数
define('LOCKOUT_TIME', 900); // 锁定时间 15分钟

// 邮件配置
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');

// 文件上传配置
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// 错误报告配置
define('DEBUG_MODE', false); // 生产环境设为false
define('LOG_ERRORS', true);
define('ERROR_LOG_FILE', 'logs/error.log');

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 开启会话
session_start();

// 错误处理
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// 安全头配置
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Referrer-Policy: strict-origin-when-cross-origin');

// 自动加载类
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// 数据库连接类
class Database {
    private static $instance = null;
    private $conn;
    
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch(PDOException $e) {
            error_log("数据库连接失败: " . $e->getMessage());
            die("数据库连接失败，请稍后再试");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    // 防止克隆
    private function __clone() {}
    
    // 防止反序列化
    public function __wakeup() {}
}

// 安全函数
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function generate_csrf_token() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verify_csrf_token($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function generate_secure_token() {
    return bin2hex(random_bytes(32));
}

// 密码哈希
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// 日志函数
function log_error($message) {
    if (LOG_ERRORS) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;
        file_put_contents(ERROR_LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

function log_security_event($event, $user_id = null, $ip = null) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("INSERT INTO security_logs (event_type, user_id, ip_address, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$event, $user_id, $ip ?? $_SERVER['REMOTE_ADDR']]);
}

// IP地址获取函数
function get_client_ip() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// 响应式图片处理
function resize_image($source, $destination, $max_width, $max_height, $quality = 85) {
    $info = getimagesize($source);
    if ($info === false) {
        return false;
    }
    
    $width = $info[0];
    $height = $info[1];
    $type = $info[2];
    
    // 计算新尺寸
    $ratio = $width / $height;
    if ($width > $max_width || $height > $max_height) {
        if ($width / $max_width > $height / $max_height) {
            $new_width = $max_width;
            $new_height = $max_width / $ratio;
        } else {
            $new_height = $max_height;
            $new_width = $max_height * $ratio;
        }
    } else {
        $new_width = $width;
        $new_height = $height;
    }
    
    // 创建图像
    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($source);
            break;
        case IMAGETYPE_WEBP:
            $image = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    // 创建新图像
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // 保持透明度
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($new_image, imagecolorallocatealpha($new_image, 0, 0, 0, 127));
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
    }
    
    // 重采样
    imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // 保存图像
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($new_image, $destination, $quality);
            break;
        case IMAGETYPE_PNG:
            imagepng($new_image, $destination, round($quality / 10));
            break;
        case IMAGETYPE_GIF:
            imagegif($new_image, $destination);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($new_image, $destination, $quality);
            break;
    }
    
    // 清理
    imagedestroy($image);
    imagedestroy($new_image);
    
    return true;
}

// 分页函数
function get_pagination($total_items, $current_page, $items_per_page = 10, $page_links = 5) {
    $total_pages = ceil($total_items / $items_per_page);
    $start_page = max(1, $current_page - floor($page_links / 2));
    $end_page = min($total_pages, $start_page + $page_links - 1);
    $start_page = max(1, $end_page - $page_links + 1);
    
    return [
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'start_page' => $start_page,
        'end_page' => $end_page,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages,
        'previous_page' => $current_page - 1,
        'next_page' => $current_page + 1
    ];
}

// 缓存函数
function get_cache($key) {
    $cache_file = 'cache/' . md5($key) . '.cache';
    if (file_exists($cache_file) && filemtime($cache_file) > time() - 3600) { // 1小时缓存
        return unserialize(file_get_contents($cache_file));
    }
    return false;
}

function set_cache($key, $data, $expire = 3600) {
    $cache_file = 'cache/' . md5($key) . '.cache';
    file_put_contents($cache_file, serialize($data));
}

function clear_cache($key = null) {
    if ($key) {
        $cache_file = 'cache/' . md5($key) . '.cache';
        if (file_exists($cache_file)) {
            unlink($cache_file);
        }
    } else {
        // 清除所有缓存
        $files = glob('cache/*.cache');
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}

// 创建必要的目录
$directories = ['logs', 'cache', 'uploads', 'uploads/avatars'];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0750, true);
        // 创建.htaccess文件防止直接访问
        file_put_contents($dir . '/.htaccess', "Deny from all\n");
    }
}

// 设置上传目录权限
if (file_exists('uploads')) {
    chmod('uploads', 0750);
}

// 禁用目录列表
if (!file_exists('.htaccess')) {
    $htaccess_content = "Options -Indexes\n";
    $htaccess_content .= "RewriteEngine On\n";
    $htaccess_content .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
    $htaccess_content .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
    $htaccess_content .= "RewriteRule ^(.*)$ index.php [QSA,L]\n";
    file_put_contents('.htaccess', $htaccess_content);
}
?>