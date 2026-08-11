<?php
/**
 * NIXSHOOTS CMS - Admin Interface
 * Version: 4.0.0
 * 
 * Secure admin panel for content management
 */

define('NIX_CMS', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Initialize database
db_init();
start_session();

// Handle authentication
$action = $_GET['action'] ?? 'login';
$message = '';
$messageType = '';

// Logout
if ($action === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Login attempt
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $passcode = $_POST['passcode'] ?? '';
    
    if (rate_limit('login_' . get_client_ip(), 5, 300)) {
        if (verify_passcode($passcode)) {
            $_SESSION['nix_authenticated'] = true;
            $_SESSION['nix_user'] = 'admin';
            log_activity('LOGIN', 'Successful login');
            header('Location: admin.php?action=dashboard');
            exit;
        } else {
            log_activity('LOGIN_FAILED', 'Invalid passcode attempt');
            $message = 'Invalid passcode';
            $messageType = 'error';
        }
    } else {
        $message = 'Too many attempts. Please try again later.';
        $messageType = 'error';
    }
}

// Check authentication for protected actions
$protectedActions = ['dashboard', 'save', 'upload', 'delete', 'publish', 'settings', 'pages'];
if (in_array($action, $protectedActions) && !is_authenticated()) {
    $action = 'login';
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_authenticated()) {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($action) {
        case 'save':
            // Save settings
            if (isset($input['settings'])) {
                foreach ($input['settings'] as $key => $value) {
                    if ($key === 'passcode' && !empty($value)) {
                        set_setting('pass_hash', hash_passcode($value));
                    } elseif ($key !== 'passcode') {
                        set_setting($key, $value);
                    }
                }
                publish_changes();
                echo json_encode(['success' => true, 'message' => 'Settings saved']);
            }
            break;
            
        case 'upload':
            // Handle file upload
            if (isset($_FILES['image'])) {
                $file = $_FILES['image'];
                $result = process_image($file['tmp_name'], $file['name']);
                
                if ($result['success']) {
                    // Save to database
                    $db = db_connect();
                    $collectionId = $input['collection_id'] ?? null;
                    
                    if ($collectionId) {
                        $stmt = $db->prepare("
                            INSERT INTO images (id, collection_id, src, webp_src, caption, width, height)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $imageId = uid();
                        $stmt->execute([
                            $imageId,
                            $collectionId,
                            $result['path'],
                            $result['webp_path'],
                            $input['caption'] ?? '',
                            $result['width'],
                            $result['height']
                        ]);
                        
                        publish_changes();
                        echo json_encode([
                            'success' => true,
                            'image' => [
                                'id' => $imageId,
                                'src' => $result['path'],
                                'webp_src' => $result['webp_path'],
                                'width' => $result['width'],
                                'height' => $result['height']
                            ]
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'No collection specified']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => $result['error']]);
                }
            }
            break;
            
        case 'delete':
            // Delete image
            if (isset($input['image_id'])) {
                $db = db_connect();
                $stmt = $db->prepare("DELETE FROM images WHERE id = ?");
                $stmt->execute([$input['image_id']]);
                publish_changes();
                echo json_encode(['success' => true]);
            }
            break;
            
        case 'publish':
            // Publish changes
            publish_changes();
            log_activity('PUBLISH', 'Site published');
            echo json_encode(['success' => true, 'message' => 'Published successfully']);
            break;
            
        case 'update_page':
            // Update page content
            if (isset($input['slug']) && isset($input['content'])) {
                $pageData = [
                    'title' => $input['title'] ?? '',
                    'content' => $input['content'],
                    'meta_title' => $input['meta_title'] ?? '',
                    'meta_description' => $input['meta_description'] ?? ''
                ];
                
                if (set_page($input['slug'], $pageData)) {
                    publish_changes();
                    echo json_encode(['success' => true, 'message' => 'Page updated']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update page']);
                }
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    
    exit;
}

// Get current settings for dashboard
$settings = [];
if (is_authenticated()) {
    $settingKeys = ['mark', 'handle', 'tagline', 'loc', 'email', 'wa', 'statement', 'about', 'avail', 'accent'];
    foreach ($settingKeys as $key) {
        $settings[$key] = get_setting($key, '');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NIXSHOOTS CMS v<?= DB_VERSION ?> — Admin</title>
<style>
:root{--bg:#0f0e0c;--bg2:#161411;--tx:#f1ece2;--mut:#9a938a;--red:#ff3b30;--yel:#ffcf24;--line:#2b2823;--green:#00c86f}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--tx);font-family:'Space Grotesk',sans-serif;font-size:14px;line-height:1.6}
.wrap{max-width:1400px;margin:0 auto;padding:24px}
header{background:var(--bg2);border-bottom:1px solid var(--line);padding:16px 24px;display:flex;justify-content:space-between;align-items:center}
.brand{font-family:'Anton',sans-serif;font-size:20px;text-transform:uppercase}
.brand em{font-style:normal;color:var(--red)}
nav{display:flex;gap:12px}
nav a,.btn{background:none;border:1px solid var(--line);color:var(--mut);padding:8px 16px;font-size:11px;letter-spacing:.14em;text-transform:uppercase;cursor:pointer;transition:all .2s;text-decoration:none}
nav a:hover,.btn:hover{border-color:var(--red);color:var(--red)}
.btn.primary{background:var(--red);border-color:var(--red);color:#fff}
.btn.primary:hover{background:#ff574d}
.btn.success{background:var(--green);border-color:var(--green);color:#000}
.version{font-size:10px;color:var(--mut);margin-left:12px}
.login-wrap{max-width:400px;margin:100px auto;padding:40px;background:var(--bg2);border:1px solid var(--line)}
.login-wrap h1{font-family:'Anton';font-size:32px;text-transform:uppercase;margin-bottom:24px}
.form-group{margin-bottom:20px}
.form-group label{display:block;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--mut);margin-bottom:8px}
.form-group input,.form-group textarea,.form-group select{width:100%;background:var(--bg);border:1px solid var(--line);color:var(--tx);padding:12px;font-size:14px;font-family:inherit}
.form-group input:focus,.form-group textarea:focus,.form-group select:focus{outline:none;border-color:var(--red)}
.form-group textarea{min-height:120px;resize:vertical}
.message{padding:12px;margin-bottom:20px;border:1px solid}
.message.error{background:rgba(255,59,48,.1);border-color:var(--red);color:var(--red)}
.message.success{background:rgba(0,200,111,.1);border-color:var(--green);color:var(--green)}
.dashboard{display:grid;grid-template-columns:280px 1fr;gap:24px;margin-top:24px}
.sidebar{background:var(--bg2);border:1px solid var(--line);padding:20px;height:fit-content}
.sidebar h3{font-family:'Anton';font-size:16px;text-transform:uppercase;margin-bottom:16px;color:var(--red)}
.sidebar ul{list-style:none}
.sidebar li{margin-bottom:8px}
.sidebar a{display:block;padding:8px;border-radius:4px;transition:all .2s}
.sidebar a:hover,.sidebar a.active{background:var(--bg);color:var(--red)}
.panel{background:var(--bg2);border:1px solid var(--line);padding:24px;margin-bottom:24px}
.panel h2{font-family:'Anton';font-size:24px;text-transform:uppercase;margin-bottom:20px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px}
.card{background:var(--bg);border:1px solid var(--line);padding:16px;border-radius:4px}
.card img{width:100%;aspect-ratio:4/5;object-fit:cover;margin-bottom:12px}
.card h4{font-size:13px;margin-bottom:4px}
.card p{font-size:11px;color:var(--mut)}
.dropzone{border:2px dashed var(--line);padding:40px;text-align:center;transition:all .2s}
.dropzone:hover,.dropzone.over{border-color:var(--red);background:rgba(255,59,48,.05)}
.swatch{width:32px;height:32px;border-radius:50%;border:2px solid transparent;cursor:pointer;transition:all .2s}
.swatch.on{border-color:var(--tx);transform:scale(1.1)}
.accent-row{display:flex;gap:8px;margin-top:12px}
@media(max-width:900px){.dashboard{grid-template-columns:1fr}}
</style>
</head>
<body>

<?php if ($action === 'login'): ?>
<div class="wrap">
    <div class="login-wrap">
        <h1>NIXSHOOTS <em>CMS</em></h1>
        <p style="color:var(--mut);margin-bottom:24px;font-size:12px;">Version <?= DB_VERSION ?></p>
        
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>"><?= esc($message) ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label for="passcode">Passcode</label>
                <input type="password" id="passcode" name="passcode" required autofocus>
            </div>
            <button type="submit" class="btn primary" style="width:100%">Sign In</button>
        </form>
    </div>
</div>

<?php else: ?>

<header>
    <div class="brand">NIXSHOOTS <em>CMS</em><span class="version">v<?= DB_VERSION ?></span></div>
    <nav>
        <a href="/" target="_blank">View Site</a>
        <a href="?action=logout">Logout</a>
    </nav>
</header>

<div class="wrap">
    <div class="dashboard">
        <aside class="sidebar">
            <h3>Navigation</h3>
            <ul>
                <li><a href="?action=dashboard" class="<?= $action === 'dashboard' ? 'active' : '' ?>">Dashboard</a></li>
                <li><a href="?action=collections" class="<?= $action === 'collections' ? 'active' : '' ?>">Collections</a></li>
                <li><a href="?action=images" class="<?= $action === 'images' ? 'active' : '' ?>">Images</a></li>
                <li><a href="?action=pages" class="<?= $action === 'pages' ? 'active' : '' ?>">Pages</a></li>
                <li><a href="?action=settings" class="<?= $action === 'settings' ? 'active' : '' ?>">Settings</a></li>
            </ul>
            
            <h3 style="margin-top:24px">Actions</h3>
            <button class="btn success" id="pubBtn" style="width:100%;margin-top:8px">Publish Changes</button>
            <button class="btn" id="clearCacheBtn" style="width:100%;margin-top:8px">Clear Cache</button>
        </aside>
        
        <main>
            <?php if ($action === 'dashboard'): ?>
            <div class="panel">
                <h2>Dashboard</h2>
                <div class="grid">
                    <div class="card">
                        <h4>Collections</h4>
                        <p><?= count(get_collections(true)) ?> total</p>
                    </div>
                    <div class="card">
                        <h4>Version</h4>
                        <p><?= DB_VERSION ?></p>
                    </div>
                    <div class="card">
                        <h4>Cache</h4>
                        <p>Last cleared: <?= date('M j, Y g:i A') ?></p>
                    </div>
                    <div class="card">
                        <h4>Status</h4>
                        <p style="color:var(--green)">● Online</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($action === 'settings'): ?>
            <div class="panel">
                <h2>Site Settings</h2>
                <form id="settingsForm">
                    <div class="grid" style="grid-template-columns:1fr 1fr">
                        <div class="form-group">
                            <label>Brand Name</label>
                            <input type="text" name="mark" value="<?= esc($settings['mark'] ?: 'NIXSHOOTS') ?>">
                        </div>
                        <div class="form-group">
                            <label>Handle</label>
                            <input type="text" name="handle" value="<?= esc($settings['handle'] ?: '@nixshoots') ?>">
                        </div>
                        <div class="form-group">
                            <label>Tagline</label>
                            <input type="text" name="tagline" value="<?= esc($settings['tagline'] ?: '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="loc" value="<?= esc($settings['loc'] ?: '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= esc($settings['email'] ?: '') ?>">
                        </div>
                        <div class="form-group">
                            <label>WhatsApp</label>
                            <input type="text" name="wa" value="<?= esc($settings['wa'] ?: '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Artist Statement</label>
                        <textarea name="statement" rows="4"><?= esc($settings['statement'] ?: '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>About Text</label>
                        <textarea name="about" rows="6"><?= esc($settings['about'] ?: '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Availability Status</label>
                        <input type="text" name="avail" value="<?= esc($settings['avail'] ?: 'Available for bookings') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Accent Color</label>
                        <div class="accent-row">
                            <?php 
                            $colors = ['#ff3b30', '#ff6a00', '#00c86f', '#8b5cf6', '#ffd21f'];
                            foreach ($colors as $color):
                                $active = ($settings['accent'] ?? '#ff3b30') === $color ? 'on' : '';
                            ?>
                            <div class="swatch <?= $active ?>" data-color="<?= $color ?>" style="background:<?= $color ?>"></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Change Passcode</label>
                        <input type="password" name="passcode" placeholder="Leave blank to keep current">
                    </div>
                    
                    <button type="submit" class="btn primary">Save Settings</button>
                </form>
            </div>
            <?php endif; ?>
            
            <?php if ($action === 'pages'): ?>
            <div class="panel">
                <h2>Edit Pages</h2>
                
                <div style="margin-bottom:32px">
                    <h3 style="font-family:'Anton';font-size:18px;text-transform:uppercase;margin-bottom:16px">About Page</h3>
                    <form class="pageForm" data-slug="about">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" value="About">
                        </div>
                        <div class="form-group">
                            <label>Content (HTML allowed)</label>
                            <textarea name="content" rows="10" placeholder="Enter about page content..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Meta Title</label>
                            <input type="text" name="meta_title" value="About - NIXSHOOTS">
                        </div>
                        <div class="form-group">
                            <label>Meta Description</label>
                            <textarea name="meta_description" rows="2">Photography by NIXSHOOTS</textarea>
                        </div>
                        <button type="submit" class="btn primary">Save About Page</button>
                    </form>
                </div>
                
                <div>
                    <h3 style="font-family:'Anton';font-size:18px;text-transform:uppercase;margin-bottom:16px">Footer Content</h3>
                    <form class="pageForm" data-slug="footer">
                        <div class="form-group">
                            <label>Footer Text (HTML allowed)</label>
                            <textarea name="content" rows="4" placeholder="Enter footer content..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Copyright Notice</label>
                            <input type="text" name="meta_title" placeholder="© 2024 NIXSHOOTS">
                        </div>
                        <button type="submit" class="btn primary">Save Footer</button>
                    </form>
                </div>
            </div>
            
            <script>
            // Load page content
            document.querySelectorAll('.pageForm').forEach(form => {
                const slug = form.dataset.slug;
                fetch('api.php?action=get_page&slug=' + slug)
                    .then(r => r.json())
                    .then(data => {
                        if (data.page) {
                            form.querySelector('[name="content"]').value = data.page.content || '';
                            form.querySelector('[name="title"]') && (form.querySelector('[name="title"]').value = data.page.title || '');
                            form.querySelector('[name="meta_title"]') && (form.querySelector('[name="meta_title"]').value = data.page.meta_title || '');
                            form.querySelector('[name="meta_description"]') && (form.querySelector('[name="meta_description"]').value = data.page.meta_description || '');
                        }
                    });
                
                form.addEventListener('submit', e => {
                    e.preventDefault();
                    const formData = new FormData(form);
                    const data = Object.fromEntries(formData.entries());
                    data.slug = slug;
                    
                    fetch('admin.php?action=update_page', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(data)
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            alert('Page saved! Don\'t forget to publish.');
                        } else {
                            alert('Error: ' + res.message);
                        }
                    });
                });
            });
            </script>
            <?php endif; ?>
            
        </main>
    </div>
</div>

<script>
// Accent color selection
document.querySelectorAll('.swatch').forEach(swatch => {
    swatch.addEventListener('click', () => {
        document.querySelectorAll('.swatch').forEach(s => s.classList.remove('on'));
        swatch.classList.add('on');
        document.querySelector('[name="accent"]')?.remove();
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'accent';
        input.value = swatch.dataset.color;
        document.getElementById('settingsForm').appendChild(input);
    });
});

// Save settings
document.getElementById('settingsForm')?.addEventListener('submit', e => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const settings = Object.fromEntries(formData.entries());
    
    fetch('admin.php?action=save', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({settings})
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert('Settings saved and published!');
        } else {
            alert('Error: ' + res.message);
        }
    });
});

// Publish button
document.getElementById('pubBtn')?.addEventListener('click', () => {
    fetch('admin.php?action=publish', {method: 'POST'})
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                alert('Site published successfully! Cache cleared.');
            }
        });
});

// Clear cache
document.getElementById('clearCacheBtn')?.addEventListener('click', () => {
    if (confirm('Clear all cached data?')) {
        fetch('admin.php?action=publish', {method: 'POST'})
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert('Cache cleared!');
                }
            });
    }
});
</script>

<?php endif; ?>

</body>
</html>
