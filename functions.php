<?php
/**
 * NIXSHOOTS CMS - Core Functions
 * Version: 4.0.0
 * testing note
 * Image processing, caching, and utility functions
 */

if (!defined('NIX_CMS')) {
    die('Direct access not allowed');
}

/**
 * Generate unique ID
 */
function uid($len = 12) {
    return bin2hex(random_bytes($len / 2));
}

/**
 * Escape HTML entities
 */
function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Get cache key with version busting
 */
function cache_key($key) {
    return $key . '_v' . str_replace('.', '_', CACHE_VERSION);
}

/**
 * Get cached data
 */
function cache_get($key, $ttl = null) {
    $ttl = $ttl ?? CACHE_TTL;
    $file = CACHE_DIR . '/' . md5(cache_key($key)) . '.cache';
    
    if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
        return unserialize(file_get_contents($file));
    }
    
    return false;
}

/**
 * Set cache data
 */
function cache_set($key, $data, $ttl = null) {
    $file = CACHE_DIR . '/' . md5(cache_key($key)) . '.cache';
    file_put_contents($file, serialize($data));
}

/**
 * Clear all caches (use when publishing)
 */
function cache_clear() {
    $files = glob(CACHE_DIR . '/*.cache');
    foreach ($files as $file) {
        unlink($file);
    }
}

/**
 * Generate responsive image srcset
 */
function img_srcset($src, $width, $height) {
    if (!ENABLE_SRCSET || empty($src)) {
        return $src;
    }
    
    // If it's a local file path
    if (strpos($src, STORAGE_URL) === 0 || strpos($src, '/storage/') === 0) {
        $sizes = [400, 800, 1200, 1600];
        $srcset = [];
        
        foreach ($sizes as $w) {
            if ($w <= $width) {
                $h = round($height * ($w / $width));
                $srcset[] = img_resize_url($src, $w, $h) . " {$w}w";
            }
        }
        
        return implode(', ', $srcset) ?: $src;
    }
    
    return $src;
}

/**
 * Generate resized image URL
 */
function img_resize_url($src, $width, $height) {
    $base = basename($src, '.' . pathinfo($src, PATHINFO_EXTENSION));
    $ext = ENABLE_WEBP ? 'webp' : 'jpg';
    return sprintf('%s/%s_%dx%d.%s', STORAGE_URL, $base, $width, $height, $ext);
}

/**
 * Process and save uploaded image
 * Returns array with original and WebP paths
 */
function process_image($tmpPath, $filename) {
    $result = [
        'success' => false,
        'path' => null,
        'webp_path' => null,
        'width' => 0,
        'height' => 0,
        'error' => null
    ];
    
    try {
        // Get image info
        $info = getimagesize($tmpPath);
        if (!$info) {
            $result['error'] = 'Invalid image';
            return $result;
        }
        
        $origWidth = $info[0];
        $origHeight = $info[1];
        
        // Calculate scale
        $scale = min(1, IMG_MAX_WIDTH / $origWidth, IMG_MAX_HEIGHT / $origHeight);
        $newWidth = round($origWidth * $scale);
        $newHeight = round($origHeight * $scale);
        
        // Load image
        $image = imagecreatefromstring(file_get_contents($tmpPath));
        if (!$image) {
            $result['error'] = 'Failed to load image';
            return $result;
        }
        
        // Resize if needed
        if ($scale < 1) {
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            imagedestroy($image);
            $image = $resized;
        }
        
        // Generate unique filename
        $uniqueId = uid(8);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo($filename, PATHINFO_FILENAME));
        $baseName = substr($safeName, 0, 50) . '_' . $uniqueId;
        
        // Save JPEG
        $jpegPath = STORAGE_DIR . '/' . $baseName . '.jpg';
        imagejpeg($image, $jpegPath, IMG_QUALITY);
        
        $result['path'] = STORAGE_URL . '/' . $baseName . '.jpg';
        $result['width'] = $newWidth;
        $result['height'] = $newHeight;
        
        // Save WebP if enabled
        if (ENABLE_WEBP) {
            $webpPath = STORAGE_DIR . '/' . $baseName . '.webp';
            imagewebp($image, $webpPath, WEBP_QUALITY);
            $result['webp_path'] = STORAGE_URL . '/' . $baseName . '.webp';
        }
        
        imagedestroy($image);
        $result['success'] = true;
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

/**
 * Generate picture tag with WebP fallback
 */
function picture_tag($src, $webpSrc = null, $alt = '', $class = '', $lazy = true) {
    $loading = $lazy ? 'loading="lazy"' : '';
    $decoding = $lazy ? 'decoding="async"' : '';
    
    if ($webpSrc && ENABLE_WEBP) {
        return sprintf(
            '<picture><source srcset="%s" type="image/webp"><img src="%s" alt="%s" class="%s" %s %s></picture>',
            esc($webpSrc), esc($src), esc($alt), esc($class), $loading, $decoding
        );
    }
    
    return sprintf(
        '<img src="%s" alt="%s" class="%s" %s %s>',
        esc($src), esc($alt), esc($class), $loading, $decoding
    );
}

/**
 * Generate blur-up placeholder (inline SVG)
 */
function blur_placeholder($width, $height, $color = '#1b1712') {
    $svg = sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d"><rect fill="%s" width="%d" height="%d"/></svg>',
        $width, $height, $width, $height, $color, $width, $height
    );
    return 'data:image/svg+xml,' . urlencode($svg);
}

/**
 * Database connection (SQLite)
 */
function db_connect() {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $db;
    } catch (PDOException $e) {
        error_log('DB Connection failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Initialize database schema
 */
function db_init() {
    $db = db_connect();
    if (!$db) return false;
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS collections (
            id TEXT PRIMARY KEY,
            title TEXT,
            code TEXT,
            year TEXT,
            blurb TEXT,
            hidden INTEGER DEFAULT 0,
            cover TEXT,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS images (
            id TEXT PRIMARY KEY,
            collection_id TEXT,
            src TEXT,
            webp_src TEXT,
            caption TEXT,
            width INTEGER,
            height INTEGER,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS pages (
            slug TEXT PRIMARY KEY,
            title TEXT,
            content TEXT,
            meta_title TEXT,
            meta_description TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Insert default pages if not exist
    $pages = [
        ['about', 'About', '', 'About - NIXSHOOTS', 'Photography by NIXSHOOTS'],
        ['footer', 'Footer Info', '', 'NIXSHOOTS Photography', 'Professional photography services']
    ];
    
    foreach ($pages as $page) {
        $stmt = $db->prepare("INSERT OR IGNORE INTO pages (slug, title, content, meta_title, meta_description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute($page);
    }
    
    return true;
}

/**
 * Get setting from database
 */
function get_setting($key, $default = '') {
    static $cache = [];
    
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    
    $cached = cache_get('setting_' . $key);
    if ($cached !== false) {
        $cache[$key] = $cached;
        return $cached;
    }
    
    $db = db_connect();
    if (!$db) {
        $cache[$key] = $default;
        return $default;
    }
    
    $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetchColumn();
    
    $value = $result !== false ? $result : $default;
    $cache[$key] = $value;
    cache_set('setting_' . $key, $value);
    
    return $value;
}

/**
 * Update setting in database
 */
function set_setting($key, $value) {
    $db = db_connect();
    if (!$db) return false;
    
    $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
    $result = $stmt->execute([$key, $value]);
    
    // Clear cache
    unset_cache('setting_' . $key);
    
    return $result;
}

/**
 * Get page content
 */
function get_page($slug) {
    $cached = cache_get('page_' . $slug);
    if ($cached !== false) {
        return $cached;
    }
    
    $db = db_connect();
    if (!$db) return null;
    
    $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ?");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();
    
    if ($page) {
        cache_set('page_' . $slug, $page);
    }
    
    return $page;
}

/**
 * Update page content
 */
function set_page($slug, $data) {
    $db = db_connect();
    if (!$db) return false;
    
    $stmt = $db->prepare("
        INSERT OR REPLACE INTO pages (slug, title, content, meta_title, meta_description, updated_at) 
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    
    $result = $stmt->execute([
        $slug,
        $data['title'] ?? '',
        $data['content'] ?? '',
        $data['meta_title'] ?? '',
        $data['meta_description'] ?? ''
    ]);
    
    // Clear cache
    unset_cache('page_' . $slug);
    
    return $result;
}

/**
 * Get all collections
 */
function get_collections($includeHidden = false) {
    $cached = cache_get('collections_' . ($includeHidden ? 'all' : 'public'));
    if ($cached !== false) {
        return $cached;
    }
    
    $db = db_connect();
    if (!$db) return [];
    
    $where = $includeHidden ? '' : 'WHERE hidden = 0';
    $stmt = $db->query("SELECT * FROM collections $where ORDER BY sort_order, created_at DESC");
    $collections = $stmt->fetchAll();
    
    cache_set('collections_' . ($includeHidden ? 'all' : 'public'), $collections);
    
    return $collections;
}

/**
 * Get images for a collection
 */
function get_collection_images($collectionId) {
    $cached = cache_get('images_' . $collectionId);
    if ($cached !== false) {
        return $cached;
    }
    
    $db = db_connect();
    if (!$db) return [];
    
    $stmt = $db->prepare("SELECT * FROM images WHERE collection_id = ? ORDER BY sort_order");
    $stmt->execute([$collectionId]);
    $images = $stmt->fetchAll();
    
    cache_set('images_' . $collectionId, $images);
    
    return $images;
}

/**
 * Delete cache item
 */
function unset_cache($key) {
    $file = CACHE_DIR . '/' . md5(cache_key($key)) . '.cache';
    if (file_exists($file)) {
        unlink($file);
    }
}

/**
 * Publish changes (clear all caches)
 */
function publish_changes() {
    cache_clear();
    
    // Regenerate static assets if needed
    generate_version_file();
    
    return true;
}

/**
 * Generate version.json for cache busting
 */
function generate_version_file() {
    $versionData = [
        'version' => DB_VERSION,
        'cache_version' => CACHE_VERSION,
        'timestamp' => time(),
        'build' => date('Y-m-d-H-i-s')
    ];
    
    file_put_contents(__DIR__ . '/version.json', json_encode($versionData, JSON_PRETTY_PRINT));
}

/**
 * Check if user is authenticated
 */
function is_authenticated() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['nix_authenticated']) && $_SESSION['nix_authenticated'] === true;
}

/**
 * Verify passcode
 */
function verify_passcode($input) {
    $storedHash = get_setting('pass_hash');
    
    if (empty($storedHash)) {
        // No password set, allow access
        return true;
    }
    
    return password_verify($input, $storedHash);
}

/**
 * Hash passcode
 */
function hash_passcode($passcode) {
    return password_hash($passcode, PASSWORD_DEFAULT);
}

/**
 * Start session safely
 */
function start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Log activity
 */
function log_activity($action, $details = '') {
    $logFile = __DIR__ . '/data/activity.log';
    $entry = sprintf(
        "[%s] %s - %s\n",
        date('Y-m-d H:i:s'),
        $action,
        $details
    );
    file_put_contents($logFile, $entry, FILE_APPEND);
}

/**
 * Get client IP
 */
function get_client_ip() {
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = explode(',', $_SERVER[$key])[0];
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Rate limiting helper
 */
function rate_limit($key, $limit = 10, $window = 60) {
    $file = CACHE_DIR . '/rate_' . md5($key) . '.txt';
    $now = time();
    
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && ($now - $data['reset']) < $window) {
            if ($data['count'] >= $limit) {
                return false;
            }
            $data['count']++;
        } else {
            $data = ['count' => 1, 'reset' => $now];
        }
    } else {
        $data = ['count' => 1, 'reset' => $now];
    }
    
    file_put_contents($file, json_encode($data));
    return true;
}

// Initialize version file on load
generate_version_file();
