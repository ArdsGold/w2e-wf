<?php
// Run: php tests/version-check.php (no WordPress or network access required).
define('ABSPATH', __DIR__);
$urls = [];
$requests = 0;
$response = ['code'=>200,'body'=>"<?php\n/**\n * Version: r2.2.6\n */"];
function get_transient($key) { throw new RuntimeException('Stale version cache read'); }
function set_transient($key, $value, $ttl) { throw new RuntimeException('Version result cached'); }
function wp_generate_uuid4() { return 'request-' . ($GLOBALS['requests'] + 1); }
function wp_remote_get($url, $args) {
    $GLOBALS['requests']++;
    if (strtok($url, '?') !== 'https://raw.githubusercontent.com/ArdsGold/w2e-wf/main/wolf-forge-elementor-page-generator.php') throw new RuntimeException('Wrong repository');
    if (in_array($url, $GLOBALS['urls'], true)) throw new RuntimeException('Reused upstream cache key');
    $GLOBALS['urls'][] = $url;
    if (($args['headers']['Cache-Control'] ?? '') !== 'no-cache') throw new RuntimeException('Missing refresh header');
    if ($args['timeout'] > 3 || $args['limit_response_size'] > 16384) throw new RuntimeException('Unbounded request');
    return $GLOBALS['response'];
}
function is_wp_error($response) { return $response === 'network error'; }
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_url($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
require __DIR__.'/../includes/version-check.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function render($installed) { ob_start(); wfebpg_render_version_status($installed); return ob_get_clean(); }

$html = render('r2.2.5');
check(strpos($html,'Update available: r2.2.6') !== false && strpos($html,'href="https://github.com/ArdsGold/w2e-wf"') !== false, 'Missing update link');
check(strpos(render('r2.2.6'), 'Up to date') !== false, 'Equal versions not recognized');
check(strpos(render('r2.2.10'), 'ahead of GitHub') !== false, 'Numeric comparison failed');
check(strpos(render('r2.2.10'), '<a ') === false, 'Newer installation shows update link');
check($requests === 4, 'Dashboard loads did not each check GitHub');
$response = ['code'=>200,'body'=>"<?php\n/**\n * Version: r2.2.7\n */"];
check(strpos(render('r2.2.7'),'Up to date') !== false, 'Initial version did not match');
$response['body'] = str_replace('r2.2.7','r2.2.8',$response['body']);
check(strpos(render('r2.2.7'),'Update available: r2.2.8') !== false, 'Refresh did not detect a new GitHub version');
foreach (['network error',['code'=>404,'body'=>'Not found'],['code'=>200,'body'=>'<script>bad version</script>']] as $failure) {
    $response = $failure;
    $html = render('r2.2.5');
    check(strpos($html,'Version <strong>r2.2.5</strong>') !== false, 'Installed version missing on failure');
    check(strpos($html,'unavailable') !== false && strpos($html,'<a ') === false && strpos($html,'Up to date') === false, 'Failure shown as a successful check');
    $before = $requests;
    render('r2.2.5');
    check($requests === $before + 1, 'Failed check did not retry on refresh');
}
echo "GitHub version checks passed: newer/equal/older versions, numeric comparison, update link, fresh checks on every load, release detection, and failure retries.\n";
