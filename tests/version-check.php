<?php
// Run: php tests/version-check.php (no WordPress or network access required).
define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
$cache = false;
$requests = 0;
$response = ['code'=>200,'body'=>"<?php\n/**\n * Version: r2.2.6\n */"];
function get_transient($key) { return $GLOBALS['cache']; }
function set_transient($key, $value, $ttl) { $GLOBALS['cache']=$value; $GLOBALS['ttl']=$ttl; }
function wp_remote_get($url, $args) {
    $GLOBALS['requests']++;
    if ($url !== 'https://raw.githubusercontent.com/ArdsGold/w2e-wf/main/wolf-forge-elementor-page-generator.php') throw new RuntimeException('Wrong repository');
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
check($ttl === 6 * HOUR_IN_SECONDS, 'Wrong success cache duration');
check(strpos(render('r2.2.6'), 'Up to date') !== false, 'Equal versions not recognized');
check(strpos(render('r2.2.10'), 'ahead of GitHub') !== false, 'Numeric comparison failed');
check(strpos(render('r2.2.10'), '<a ') === false, 'Newer installation shows update link');
check($requests === 1, 'Cached version triggered more requests');
foreach (['network error',['code'=>404,'body'=>'Not found'],['code'=>200,'body'=>'<script>bad version</script>']] as $failure) {
    $response = $failure;
    $cache = false;
    $html = render('r2.2.5');
    check(strpos($html,'Version <strong>r2.2.5</strong>') !== false, 'Installed version missing on failure');
    check(strpos($html,'unavailable') !== false && strpos($html,'<a ') === false, 'Failure shown as available update');
    check($ttl === 15 * MINUTE_IN_SECONDS, 'Wrong failure cache duration');
    $before = $requests;
    render('r2.2.5');
    check($requests === $before, 'Failure was not cached');
}
echo "GitHub version checks passed: newer/equal/older versions, numeric comparison, update link, caching, and failures.\n";
