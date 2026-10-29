<?php
// Run: php tests/queue-worker.php (no WordPress or real pages required).
define('ABSPATH', __DIR__);
define('OBJECT', 'OBJECT');
$options = [];
$seen = [];
$errors = [];
$authorized = true;
$nonce_valid = true;
$lose_lock = false;
$queue_cache = null;
function add_action($hook, $callback) {}
function get_option($key, $default = false) {
    if ($key === 'wfebpg_queue') {
        if ($GLOBALS['queue_cache'] === null) $GLOBALS['queue_cache'] = $GLOBALS['options'][$key] ?? $default;
        return $GLOBALS['queue_cache'];
    }
    return $GLOBALS['options'][$key] ?? $default;
}
function update_option($key, $value, $autoload = null) {
    $GLOBALS['options'][$key] = $value;
    if ($key === 'wfebpg_queue') $GLOBALS['queue_cache'] = $value;
}
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function add_option($key, $value, $deprecated = '', $autoload = false) {
    if ($GLOBALS['lose_lock'] || isset($GLOBALS['options'][$key])) return false;
    $GLOBALS['options'][$key] = $value;
    return true;
}
function absint($value) { return abs((int)$value); }
function sanitize_title($value) { return $value; }
function get_page_by_path($slug, $output, $type) { return null; }
function wp_insert_post($post, $error) { return 123; }
function is_wp_error($value) { return false; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_slash($value) { return $value; }
function update_post_meta($id, $key, $value) {}
function clean_post_cache($id) {}
function wp_cache_delete($id, $group) { if ($id === 'wfebpg_queue' && $group === 'options') $GLOBALS['queue_cache'] = null; }
function current_time($format) { return '2026-10-30 00:00:00'; }
function wp_doing_ajax() { return true; }
function current_user_can($capability) { return $GLOBALS['authorized']; }
function check_ajax_referer($action, $field, $die) { return $GLOBALS['nonce_valid']; }
function wp_send_json_error($data, $status) { $GLOBALS['response']=['success'=>false,'status'=>$status]; }
function wp_send_json_success($data) { $GLOBALS['response']=['success'=>true,'data'=>$data]; }
class WFEBPG_Logger {
    public static function log($message, $level = 'info') { if ($level === 'error') $GLOBALS['errors'][]=$message; }
}
class WFEBPG_DOCX_Reader {
    public static function read($file, $fallback) {
        $GLOBALS['seen'][] = $file;
        if ($file === 'enqueue') {
            $queue = get_option('wfebpg_queue', []);
            $queue[] = job('added-during-generation');
            update_option('wfebpg_queue', $queue);
        }
        if ($file === 'fail') throw new RuntimeException('Expected failed job');
        if ($file === 'cancel-during-job') {
            // Another PHP request clears the queue while this worker still caches it.
            $cached = $GLOBALS['queue_cache'];
            wfebpg_cancel_queue();
            check(isset($GLOBALS['options'][WFEBPG_Generator::QUEUE_LOCK_OPTION]), 'Cancellation released an active worker lock');
            $GLOBALS['queue_cache'] = $cached;
        }
        return ['items'=>[],'repeatables'=>[]];
    }
}
require __DIR__.'/../includes/class-template.php';
require __DIR__.'/../includes/class-generator.php';
require __DIR__.'/../admin/admin-page.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function job($name) { return ['docx'=>$name,'page_title'=>$name,'template'=>$GLOBALS['template']]; }
$template = tempnam(sys_get_temp_dir(), 'wfebpg-queue-');
try {
    file_put_contents($template, '{"content":[]}');
    $names = ['enqueue','second','fail','fourth','fifth','sixth','seventh'];
    update_option('wfebpg_queue', array_map('job', $names));
    $total = 0;
    $failed = 0;
    for ($i=0; $i<3; $i++) {
        $result = WFEBPG_Generator::process_queue();
        check(!$result['busy'] && $result['processed'] <= 3, 'Batch/lock limit failed');
        check(!isset($options[WFEBPG_Generator::QUEUE_LOCK_OPTION]), 'Worker lock was not released');
        $total += $result['processed'];
        $failed += $result['failed'];
    }
    check($total === 8 && $failed === 1 && $result['remaining'] === 0, 'Queue did not drain or failure was not counted');
    check($seen === array_merge($names,['added-during-generation']), 'Jobs lost, duplicated, or reordered during enqueue');
    check(count($errors) === 1, 'Failed job was not logged');
    update_option(WFEBPG_Generator::QUEUE_LOCK_OPTION, time());
    $result = WFEBPG_Generator::process_queue();
    check($result['busy'] && $result['processed'] === 0, 'Active worker was ignored');
    update_option(WFEBPG_Generator::QUEUE_LOCK_OPTION, time()-121);
    check(!WFEBPG_Generator::process_queue()['busy'], 'Stale lock was not recovered');
    $lose_lock = true;
    check(WFEBPG_Generator::process_queue()['busy'], 'Lost lock race was ignored');
    $lose_lock = false;
    update_option('wfebpg_queue', [job('endpoint-job')]);
    $before = $seen;
    $authorized = false;
    wfebpg_process_queue_now();
    check($response['status'] === 403 && $seen === $before, 'Unauthorized worker ran');
    $authorized = true;
    $nonce_valid = false;
    wfebpg_process_queue_now();
    check($response['status'] === 403 && $seen === $before, 'Invalid nonce accepted');
    $nonce_valid = true;
    wfebpg_process_queue_now();
    check($response['success'] && $response['data']['processed'] === 1 && $response['data']['remaining'] === 0, 'AJAX worker did not return progress');
    update_option('wfebpg_queue', [job('waiting-one'),job('waiting-two')]);
    $authorized = false;
    wfebpg_cancel_queue();
    check($response['status'] === 403 && count(get_option('wfebpg_queue')) === 2, 'Unauthorized cancellation accepted');
    $authorized = true;
    $nonce_valid = false;
    wfebpg_cancel_queue();
    check($response['status'] === 403 && count(get_option('wfebpg_queue')) === 2, 'Invalid cancellation nonce accepted');
    $nonce_valid = true;
    wfebpg_cancel_queue();
    check($response['success'] && $response['data']['cancelled'] === 2 && get_option('wfebpg_queue') === [], 'Waiting jobs were not cancelled');
    wfebpg_cancel_queue();
    check($response['data']['cancelled'] === 0, 'Empty cancellation failed');
    update_option('wfebpg_queue', [job('cancel-during-job'),job('must-not-run'),job('must-not-run-either')]);
    $before = count($seen);
    $result = WFEBPG_Generator::process_queue();
    check($result['processed'] === 1 && $result['remaining'] === 0 && count($seen) === $before+1, 'Worker resumed cancelled jobs from stale cache');
    check($response['data']['cancelled'] === 2, 'Cancellation included the running job');
} finally {
    unlink($template);
}
echo "Queue worker passed: batches, added jobs, failures, locks, authorization, cancellation, and stale-cache protection.\n";
