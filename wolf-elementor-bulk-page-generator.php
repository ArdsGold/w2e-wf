<?php
/**
 * Plugin Name: Wolf Forge Elementor Bulk Page Generator
 * Description: Bulk-generate Elementor pages from DOCX files and Elementor JSON templates. Supports generic/unique mapping, remembered JSON templates, yellow-heading repeatable sections, parent pages, phone links, queue processing, template validation, Media Library image pools, randomized repeatable images, previews, logs, and rollback.
 * Version: 1.12.5
 * Author: Wolf Forge
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */
if (!defined('ABSPATH')) exit;

define('WFEBPG_VERSION','1.12.5');
define('WFEBPG_DIR',plugin_dir_path(__FILE__));
define('WFEBPG_URL',plugin_dir_url(__FILE__));

require_once WFEBPG_DIR.'includes/class-docx-reader.php';
require_once WFEBPG_DIR.'includes/class-template.php';
require_once WFEBPG_DIR.'includes/class-generator.php';
require_once WFEBPG_DIR.'includes/class-phone-linker.php';
require_once WFEBPG_DIR.'includes/class-logger.php';
require_once WFEBPG_DIR.'admin/admin-page.php';

register_activation_hook(__FILE__, function(){
    if (!wp_next_scheduled('wfebpg_process_queue')) wp_schedule_event(time()+60,'minute','wfebpg_process_queue');
});
register_deactivation_hook(__FILE__, function(){
    wp_clear_scheduled_hook('wfebpg_process_queue');
});
add_action('wfebpg_process_queue',['WFEBPG_Generator','process_queue']);
add_filter('cron_schedules',function($s){$s['minute']=['interval'=>60,'display'=>'Every Minute'];return $s;});


/** Mark plugin-generated pages so the full-width CSS is strictly scoped. */
function wfebpg_generated_body_class($classes) {
    if (is_singular('page') && get_post_meta(get_queried_object_id(), '_wfebpg_generated', true) === '1') {
        $classes[] = 'wfebpg-generated-page';
    }
    return $classes;
}
add_filter('body_class', 'wfebpg_generated_body_class', 20);

/** Enqueue the generated-page full-width override only on plugin-created pages. */
function wfebpg_enqueue_generated_frontend_css() {
    if (!is_singular('page') || get_post_meta(get_queried_object_id(), '_wfebpg_generated', true) !== '1') {
        return;
    }
    $file = WFEBPG_DIR . 'assets/frontend.css';
    wp_enqueue_style(
        'wfebpg-generated-frontend',
        WFEBPG_URL . 'assets/frontend.css',
        array(),
        file_exists($file) ? filemtime($file) : WFEBPG_VERSION
    );
}
add_action('wp_enqueue_scripts', 'wfebpg_enqueue_generated_frontend_css', 999);
