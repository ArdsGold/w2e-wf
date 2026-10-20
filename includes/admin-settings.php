<?php
if (!defined('ABSPATH')) exit;

/** Seed shared preferences from the first administrator's existing settings. */
function wfebpg_get_setting($key, $default = '') {
    $value = get_option($key, null);
    if ($value !== null) return $value;
    $value = metadata_exists('user', get_current_user_id(), $key)
        ? get_user_meta(get_current_user_id(), $key, true) : $default;
    add_option($key, $value, '', false);
    return get_option($key, $value);
}

/** Only dashboard preferences can be changed by the autosave endpoint. */
function wfebpg_sanitize_settings($settings) {
    if (!is_array($settings)) throw new InvalidArgumentException('Invalid settings.');
    $clean = [];
    foreach ($settings as $key => $value) {
        if (!is_scalar($value)) throw new InvalidArgumentException('Invalid setting value.');
        switch ($key) {
            case 'widgets_per_section':
                $value = max(1, min(100, absint($value)));
                break;
            case 'widgets_no_limit':
            case 'overwrite':
            case 'image_pool_enabled':
                $value = in_array($value, [true, 1, '1'], true) ? 1 : 0;
                break;
            case 'parent':
                $value = absint($value);
                if ($value && get_post_type($value) !== 'page') $value = 0;
                break;
            case 'active_tab':
                if (!in_array($value, ['generation', 'image-replacement', 'new-pages', 'validator', 'logs'], true)) {
                    throw new InvalidArgumentException('Invalid tab.');
                }
                break;
            case 'log_level':
                if (!in_array($value, ['', 'success', 'info', 'warning', 'error'], true)) {
                    throw new InvalidArgumentException('Invalid log level.');
                }
                break;
            case 'log_search':
                $value = substr(sanitize_text_field($value), 0, 200);
                break;
            default:
                throw new InvalidArgumentException('Unknown setting.');
        }
        $clean['wfebpg_' . $key] = $value;
    }
    return $clean;
}

function wfebpg_ajax_save_settings() {
    if (!current_user_can('manage_options') || !check_ajax_referer('wfebpg_save_settings', 'nonce', false)) {
        wp_send_json_error(['message' => 'Unauthorized.'], 403);
        return;
    }
    try {
        $settings = wfebpg_sanitize_settings(wp_unslash($_POST['settings'] ?? []));
    } catch (InvalidArgumentException $e) {
        wp_send_json_error(['message' => $e->getMessage()], 400);
        return;
    }
    foreach ($settings as $key => $value) update_option($key, $value, false);
    wp_send_json_success();
}
add_action('wp_ajax_wfebpg_save_settings', 'wfebpg_ajax_save_settings');
