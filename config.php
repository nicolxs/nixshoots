<?php
/**
 * NIXSHOOTS CMS - Configuration
 * Version: 4.0.0
 * 
 * Database and environment configuration
 */

// Prevent direct access to config
if (!defined('NIX_CMS')) {
    die('Direct access not allowed');
}

// Environment detection (Vercel vs Local)
$isVercel = getenv('VERCEL') === '1';
$isProduction = getenv('APP_ENV') === 'production';

// Database settings (SQLite for simplicity, works on Vercel)
define('DB_PATH', __DIR__ . '/data/nixshoots.db');
define('DB_VERSION', '4.0.0');

// Cache settings
define('CACHE_DIR', __DIR__ . '/cache');
define('CACHE_VERSION', DB_VERSION); // Bump this to invalidate all caches
define('CACHE_TTL', 3600); // 1 hour for dynamic content

// Image settings
define('IMG_MAX_WIDTH', 1600);
define('IMG_MAX_HEIGHT', 2000);
define('IMG_QUALITY', 82);
define('THUMB_SIZE', 400);
define('WEBP_QUALITY', 75);

// Storage settings
define('STORAGE_DIR', __DIR__ . '/storage/frames');
define('STORAGE_URL', $isVercel ? '/storage/frames' : '/storage/frames');

// Security
define('SESSION_LIFETIME', 86400); // 24 hours
define('PASS_MIN_LENGTH', 4);

// Feature flags
define('ENABLE_WEBP', true);
define('ENABLE_AVIF', false); // AVIF support coming soon
define('ENABLE_LAZY_LOAD', true);
define('ENABLE_SRCSET', true);
define('ENABLE_CDN', false); // Set to true when using CDN

// CDN Configuration (optional)
define('CDN_URL', ''); // e.g., 'https://cdn.nixshoots.com'

// Analytics (optional)
define('ANALYTICS_ID', getenv('ANALYTICS_ID') ?: '');

// Contact settings (defaults, can be overridden in CMS)
define('DEFAULT_EMAIL', 'hello@nixshoots.com');
define('DEFAULT_HANDLE', '@nixshoots');

// Initialize directories
$dirs = [CACHE_DIR, STORAGE_DIR, __DIR__ . '/data'];
foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Error reporting (disabled in production)
if (!$isProduction && !$isVercel) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('UTC');

// Memory limits for image processing
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '300');
