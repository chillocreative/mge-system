<?php
/**
 * MGE-PMS One-Time Production Setup
 * Access: https://app.mge-eng.com/setup.php?token=mge2026setup
 * DELETE this file after setup is complete.
 */

define('SETUP_TOKEN', 'mge2026setup');
define('BASE_PATH', dirname(__DIR__));

if (($_GET['token'] ?? '') !== SETUP_TOKEN) {
    http_response_code(403);
    die('<h2>403 Forbidden</h2><p>Provide ?token= to access setup.</p>');
}

$action = $_POST['action'] ?? '';
$messages = [];

// ── Helper ────────────────────────────────────────────────────────────────────
function php_bin(): string {
    foreach (['php8.3', 'php8.2', 'php'] as $bin) {
        if (@shell_exec("which $bin 2>/dev/null")) return $bin;
    }
    return 'php';
}

function artisan(string $cmd): string {
    $php = php_bin();
    $base = BASE_PATH;
    return shell_exec("cd $base && $php artisan $cmd 2>&1");
}

function env_path(): string { return BASE_PATH . '/.env'; }

// ── Actions ───────────────────────────────────────────────────────────────────
if ($action === 'create_env') {
    $example = BASE_PATH . '/.env.production.example';
    if (!file_exists(env_path()) && file_exists($example)) {
        copy($example, env_path());
        $messages[] = ['ok', '.env created from .env.production.example'];
    } elseif (file_exists(env_path())) {
        $messages[] = ['warn', '.env already exists — not overwritten'];
    } else {
        $messages[] = ['err', '.env.production.example not found'];
    }
}

if ($action === 'set_db') {
    $db   = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';
    if ($db && $user && file_exists(env_path())) {
        $env = file_get_contents(env_path());
        $env = preg_replace('/^DB_DATABASE=.*/m', "DB_DATABASE=$db", $env);
        $env = preg_replace('/^DB_USERNAME=.*/m', "DB_USERNAME=$user", $env);
        $env = preg_replace('/^DB_PASSWORD=.*/m', "DB_PASSWORD=$pass", $env);
        file_put_contents(env_path(), $env);
        $messages[] = ['ok', 'DB credentials written to .env'];
    } else {
        $messages[] = ['err', 'Missing DB fields or .env not found'];
    }
}

if ($action === 'run_setup') {
    $out = artisan('config:clear');   $messages[] = ['ok', 'config:clear — ' . trim($out)];
    $out = artisan('key:generate --force'); $messages[] = ['ok', 'key:generate — ' . trim($out)];
    $out = artisan('migrate --force'); $messages[] = ['ok', 'migrate — ' . trim($out ?: '(no output)')];
    $out = artisan('storage:link --force'); $messages[] = ['ok', 'storage:link — ' . trim($out ?: '(no output)')];
    $out = artisan('optimize');        $messages[] = ['ok', 'optimize — ' . trim($out)];
}

if ($action === 'delete_self') {
    unlink(__FILE__);
    die('<h2>setup.php deleted. App is live.</h2><p><a href="/">Go to app</a></p>');
}

// ── Diagnostics ───────────────────────────────────────────────────────────────
$checks = [
    'PHP Version'       => PHP_VERSION . (version_compare(PHP_VERSION, '8.3.0', '>=') ? ' ✓' : ' ✗ (need 8.3+)'),
    '.env exists'       => file_exists(env_path()) ? '✓ Yes' : '✗ Missing',
    'APP_KEY set'       => (function() {
        if (!file_exists(env_path())) return '✗ .env missing';
        preg_match('/^APP_KEY=(.+)/m', file_get_contents(env_path()), $m);
        return ($m[1] ?? '') ? '✓ ' . substr($m[1], 0, 20) . '...' : '✗ Empty';
    })(),
    'storage writable'  => is_writable(BASE_PATH . '/storage') ? '✓ Yes' : '✗ Not writable',
    'bootstrap/cache'   => is_writable(BASE_PATH . '/bootstrap/cache') ? '✓ Yes' : '✗ Not writable',
    'vendor exists'     => is_dir(BASE_PATH . '/vendor') ? '✓ Yes' : '✗ Missing',
    'DB connection'     => (function() {
        if (!file_exists(BASE_PATH . '/.env')) return '— .env missing';
        preg_match_all('/^(DB_HOST|DB_DATABASE|DB_USERNAME|DB_PASSWORD)=(.*)$/m',
            file_get_contents(BASE_PATH . '/.env'), $m, PREG_SET_ORDER);
        $cfg = [];
        foreach ($m as $row) $cfg[$row[1]] = $row[2];
        try {
            $dsn = 'mysql:host=' . ($cfg['DB_HOST'] ?? '127.0.0.1') . ';dbname=' . ($cfg['DB_DATABASE'] ?? '');
            new PDO($dsn, $cfg['DB_USERNAME'] ?? '', $cfg['DB_PASSWORD'] ?? '');
            return '✓ Connected';
        } catch (\Exception $e) {
            return '✗ ' . $e->getMessage();
        }
    })(),
];

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MGE-PMS Setup</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#0f172a;color:#f1f5f9;padding:2rem}
.card{background:#1e293b;border-radius:12px;padding:1.5rem;margin-bottom:1.5rem;border:1px solid #334155}
h1{font-size:1.4rem;font-weight:700;margin-bottom:.3rem}
h2{font-size:1rem;font-weight:600;color:#94a3b8;margin-bottom:1rem;text-transform:uppercase;letter-spacing:.05em}
table{width:100%;border-collapse:collapse}
td{padding:.5rem .75rem;border-bottom:1px solid #334155;font-size:.875rem}
td:first-child{color:#94a3b8;width:180px}
.ok{color:#4ade80}.warn{color:#facc15}.err{color:#f87171}
.msg{padding:.6rem 1rem;border-radius:8px;margin-bottom:.5rem;font-size:.85rem}
.msg.ok{background:#14532d;border:1px solid #16a34a}
.msg.warn{background:#713f12;border:1px solid #ca8a04}
.msg.err{background:#7f1d1d;border:1px solid #dc2626}
form{display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;margin-top:.75rem}
input{background:#0f172a;border:1px solid #475569;border-radius:6px;color:#f1f5f9;padding:.5rem .75rem;font-size:.875rem;width:100%}
label{font-size:.75rem;color:#94a3b8;display:block;margin-bottom:.3rem}
.field{flex:1;min-width:140px}
button{background:#facc15;color:#0f172a;border:none;border-radius:6px;padding:.5rem 1.25rem;font-weight:700;cursor:pointer;font-size:.875rem;white-space:nowrap}
button.danger{background:#ef4444;color:#fff}
.tag{display:inline-block;background:#facc15;color:#0f172a;border-radius:4px;padding:.1rem .5rem;font-size:.7rem;font-weight:700;margin-left:.5rem}
</style>
</head>
<body>
<div style="max-width:860px;margin:0 auto">

<div style="display:flex;align-items:center;gap:1rem;margin-bottom:2rem">
  <svg width="40" height="40" viewBox="0 0 40 40" fill="none"><rect width="40" height="40" rx="8" fill="rgba(255,255,255,0.08)"/>
  <path d="M10 30V12L20 22L30 12V30" stroke="#f1f5f9" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
  <line x1="8" y1="35" x2="32" y2="35" stroke="#facc15" stroke-width="2.5" stroke-linecap="round"/></svg>
  <div><h1>MGE-PMS Setup <span class="tag">PRODUCTION</span></h1>
  <p style="font-size:.8rem;color:#64748b">Delete this file once setup is complete</p></div>
</div>

<?php foreach ($messages as [$type, $text]): ?>
<div class="msg <?= $type ?>"><?= htmlspecialchars($text) ?></div>
<?php endforeach; ?>

<!-- Diagnostics -->
<div class="card">
<h2>System Diagnostics</h2>
<table>
<?php foreach ($checks as $k => $v): ?>
<tr><td><?= $k ?></td><td class="<?= str_starts_with($v,'✓')?'ok':(str_starts_with($v,'✗')?'err':'warn') ?>"><?= htmlspecialchars($v) ?></td></tr>
<?php endforeach; ?>
</table>
</div>

<!-- Step 1 -->
<div class="card">
<h2>Step 1 — Create .env</h2>
<p style="font-size:.85rem;color:#94a3b8;margin-bottom:.75rem">Copies <code>.env.production.example</code> → <code>.env</code> (skipped if .env already exists)</p>
<form method="post"><input type="hidden" name="action" value="create_env">
<button type="submit">Create .env</button></form>
</div>

<!-- Step 2 -->
<div class="card">
<h2>Step 2 — Set Database Credentials</h2>
<form method="post">
<input type="hidden" name="action" value="set_db">
<div class="field"><label>DB Name (cPanel format: user_dbname)</label><input name="db_name" placeholder="cpanelusername_mge_pms"></div>
<div class="field"><label>DB Username (cPanel format: user_dbuser)</label><input name="db_user" placeholder="cpanelusername_mgeuser"></div>
<div class="field"><label>DB Password</label><input name="db_pass" type="password" placeholder="••••••••"></div>
<button type="submit">Save DB Config</button>
</form>
</div>

<!-- Step 3 -->
<div class="card">
<h2>Step 3 — Run Setup (migrate, key:generate, optimize)</h2>
<p style="font-size:.85rem;color:#94a3b8;margin-bottom:.75rem">Runs: <code>key:generate</code> → <code>migrate --force</code> → <code>storage:link</code> → <code>optimize</code></p>
<form method="post"><input type="hidden" name="action" value="run_setup">
<button type="submit">Run Setup</button></form>
</div>

<!-- Step 4 -->
<div class="card">
<h2>Step 4 — Delete This File</h2>
<p style="font-size:.85rem;color:#ef4444;margin-bottom:.75rem">⚠ Do this immediately after setup is done.</p>
<form method="post"><input type="hidden" name="action" value="delete_self">
<button class="danger" type="submit" onclick="return confirm('Delete setup.php permanently?')">Delete setup.php</button></form>
</div>

</div>
</body>
</html>
