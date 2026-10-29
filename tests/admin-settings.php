<?php
// Run with: php tests/admin-settings.php (no WordPress installation required).
define('ABSPATH', __DIR__);
$options = [];
$user = 1;
$meta = [1 => ['wfebpg_last_template' => 'first.json'], 2 => ['wfebpg_last_template' => 'second.json']];
$authorized = true;
$valid_nonce = true;
$response = null;
function add_action($hook, $callback) {}
function get_current_user_id() { return $GLOBALS['user']; }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function add_option($key, $value, $deprecated = '', $autoload = false) {
    if (array_key_exists($key, $GLOBALS['options'])) return false;
    $GLOBALS['options'][$key] = $value;
    return true;
}
function update_option($key, $value, $autoload = null) { $GLOBALS['options'][$key] = $value; }
function metadata_exists($type, $user, $key) { return array_key_exists($key, $GLOBALS['meta'][$user] ?? []); }
function get_user_meta($user, $key, $single) { return $GLOBALS['meta'][$user][$key] ?? ''; }
function absint($value) { return abs((int) $value); }
function get_post_type($id) { return $id === 12 ? 'page' : 'attachment'; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function current_user_can($cap) { return $GLOBALS['authorized']; }
function check_ajax_referer($action, $field, $die) { return $GLOBALS['valid_nonce']; }
function wp_unslash($value) { return $value; }
function wp_send_json_error($data, $status) { $GLOBALS['response'] = ['success' => false, 'status' => $status]; }
function wp_send_json_success() { $GLOBALS['response'] = ['success' => true]; }
require __DIR__ . '/../includes/admin-settings.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

check(wfebpg_get_setting('wfebpg_last_template') === 'first.json', 'Legacy value did not migrate');
$user = 2;
check(wfebpg_get_setting('wfebpg_last_template') === 'first.json', 'Second administrator replaced shared value');
update_option('wfebpg_last_template', '');
check(wfebpg_get_setting('wfebpg_last_template') === '', 'Cleared preference was remigrated');
check(wfebpg_get_setting('wfebpg_overwrite', 0) === 0, 'Default was not persisted');
$meta[1]['wfebpg_overwrite'] = 1;
$user = 1;
check(wfebpg_get_setting('wfebpg_overwrite', 0) === 0, 'False setting was replaced by user metadata');

$_POST['settings'] = ['widgets_per_section' => 9, 'widgets_no_limit' => '1', 'parent' => 12,
    'repeat_fill_partial' => '1', 'flipbox_back_title' => '0', 'overwrite' => '0', 'image_pool_enabled' => '0', 'log_level' => 'warning', 'log_search' => '<b>error</b>', 'active_tab' => 'logs'];
wfebpg_ajax_save_settings();
check($response['success'], 'Valid settings did not save');
check(wfebpg_get_setting('wfebpg_repeat_fill_partial') === 1, 'Partial width not saved');
check(wfebpg_get_setting('wfebpg_flipbox_back_title',1) === 0, 'Original back-title preference not saved');
$user = 2;
check(wfebpg_get_setting('wfebpg_widgets_per_section') === 9, 'Number not shared');
check(wfebpg_get_setting('wfebpg_widgets_no_limit') === 1, 'No limit not shared');
check(wfebpg_get_setting('wfebpg_log_search') === 'error', 'Search not sanitized');
$_POST['settings'] = ['widgets_no_limit' => '0', 'log_search' => ''];
wfebpg_ajax_save_settings();
check(wfebpg_get_setting('wfebpg_widgets_no_limit') === 0, 'Unchecked value lost');
check(wfebpg_get_setting('wfebpg_log_search') === '', 'Empty value lost');
check(wfebpg_get_setting('wfebpg_widgets_per_section') === 9, 'Patch replaced unrelated number');
check(wfebpg_sanitize_settings(['widgets_per_section' => 999])['wfebpg_widgets_per_section'] === 100, 'Limit not clamped');
check(wfebpg_sanitize_settings(['parent' => 99])['wfebpg_parent'] === 0, 'Invalid parent accepted');
foreach ([['unexpected' => 'x'], ['active_tab' => 'bad'], ['log_level' => 'bad'], ['overwrite' => []]] as $invalid) {
    $_POST['settings'] = $invalid;
    wfebpg_ajax_save_settings();
    check(!$response['success'] && $response['status'] === 400, 'Invalid preference accepted');
}
$_POST['settings'] = ['overwrite' => '1'];
$before = $options;
$authorized = false;
wfebpg_ajax_save_settings();
check($response['status'] === 403 && $options === $before, 'Unauthorized settings saved');
$authorized = true;
$valid_nonce = false;
wfebpg_ajax_save_settings();
check($response['status'] === 403 && $options === $before, 'Invalid nonce accepted');
echo "Shared settings passed: migration, multiple administrators, off/empty values, patches, validation, authorization.\n";
