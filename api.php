<?php
/**
 * NIXSHOOTS CMS - API Endpoint
 * Version: 4.0.0
 * testing
 * JSON API for frontend communication
 */

define('NIX_CMS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';
$db = db_connect();

switch ($action) {
    case 'get_settings':
        $settings = [];
        $keys = ['mark', 'handle', 'tagline', 'loc', 'email', 'wa', 'statement', 'about', 'avail', 'accent'];
        foreach ($keys as $key) {
            $settings[$key] = get_setting($key, '');
        }
        echo json_encode(['success' => true, 'settings' => $settings]);
        break;
        
    case 'get_collections':
        $collections = get_collections($_GET['include_hidden'] === '1');
        echo json_encode(['success' => true, 'collections' => $collections]);
        break;
        
    case 'get_images':
        $collectionId = $_GET['collection_id'] ?? '';
        if ($collectionId) {
            $images = get_collection_images($collectionId);
            echo json_encode(['success' => true, 'images' => $images]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No collection ID provided']);
        }
        break;
        
    case 'get_page':
        $slug = $_GET['slug'] ?? '';
        if ($slug) {
            $page = get_page($slug);
            echo json_encode(['success' => true, 'page' => $page]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No slug provided']);
        }
        break;
        
    case 'version':
        echo json_encode([
            'version' => DB_VERSION,
            'cache_version' => CACHE_VERSION,
            'timestamp' => time()
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
