<?php
// Standalone mapping checks; optionally pass an Elementor JSON and DOCX for full generation into in-memory WordPress stubs.
define('ABSPATH', __DIR__);
define('OBJECT', 'OBJECT');
function absint($value) { return abs((int)$value); }
function wp_generate_uuid4() { return bin2hex(random_bytes(16)); }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function wpautop($value) { return $value === '' ? '' : '<p>'.str_replace("\n\n", "</p>\n<p>", $value).'</p>'; }
function sanitize_title($value) { return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $value)); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_slash($value) { return $value; }
function get_page_by_path($path, $output, $type) { return null; }
function wp_insert_post($post, $error) { return 123; }
function is_wp_error($value) { return false; }
function update_post_meta($id, $key, $value) { $GLOBALS['saved_meta'][$key] = $value; }
function clean_post_cache($id) {}
function wp_cache_delete($id, $group) {}
function get_option($key, $default = false) { return $default; }
function update_option($key, $value, $autoload = null) {}
function current_time($format) { return '2026-10-20 00:00:00'; }
class WFEBPG_Logger { public static function log($message, $level = 'info') {} }
require __DIR__.'/../includes/class-template.php';
require __DIR__.'/../includes/class-docx-reader.php';
require __DIR__.'/../includes/class-phone-linker.php';
require __DIR__.'/../includes/class-generator.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function invoke($name, &$elements, ...$args) {
    $method = new ReflectionMethod(WFEBPG_Generator::class, $name);
    if (PHP_VERSION_ID < 80100) $method->setAccessible(true);
    return $method->invokeArgs(null, array_merge([&$elements], $args));
}
function node($id, $marker, $type = 'heading', $children = []) {
    return ['id'=>$id,'elType'=>$type === 'section' ? 'section' : 'widget','widgetType'=>$type,
        'settings'=>['customID'=>$marker,'title'=>'Placeholder'],'elements'=>$children];
}
function flatten($nodes) {
    $out = [];
    foreach ($nodes as $node) { $out[] = $node; $out = array_merge($out, flatten($node['elements'] ?? [])); }
    return $out;
}
function plain($value) { return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8'))); }

// The FAQ widget precedes its scope heading in Elementor's layout order.
$doc = ['items'=>[]];
foreach ([[1,'Page'],[0,'Intro body'],[2,'Process'],[3,'Process step'],[0,'Process body'],
    [2,'FAQs'],[3,'Question one'],[0,'Answer one'],[3,'Question two'],[0,'Answer two'],[2,'Contact'],[0,'Contact body']] as $item) {
    $doc['items'][] = ['text'=>$item[1],'heading'=>$item[0]>0,'heading_level'=>$item[0],'repeatable'=>false];
}
$template = [node('intro','','section',[node('h1','h1'),node('p','p')]),
    node('process','','section',[node('process-h2','h2')]),
    node('faq','','section',[node('faq-widget','h3|p|repeat','accordion'),node('faq-heading','h2')]),
    node('contact','','section',[node('contact-h2','h2'),node('contact-p','p')])];
$internal = invoke('build_internal_repeatable_items', $doc, $template);
check(array_column($internal[0]['items'], 'heading') === ['Question one','Question two'], 'FAQ collection used preceding section');
$exclusions = $internal[0]['items'];
invoke('populate_nonrepeat', $template, $doc, $exclusions);
invoke('populate_internal_repeaters', $template, $doc);
check($template[0]['elements'][1]['settings']['title'] === 'Intro body', 'Paragraph on Heading widget missing');
check($template[3]['elements'][1]['settings']['title'] === 'Contact body', 'Contact paragraph missing');
check(array_column($template[2]['elements'][0]['settings']['tabs'], 'tab_title') === ['Question one','Question two'], 'FAQ content repeated from process');
check($template[2]['elements'][0]['id'] === 'faq-widget', 'Mapping rearranged layout order');
$settings = ['title'=>'Placeholder'];
invoke('set_widget_marker_content', $settings, ['type'=>'h3','flags'=>['p']], 'Heading', 'Body');
check($settings['title'] === 'Heading', 'Paragraph fallback overwrote combined heading');

if (isset($argv[1], $argv[2])) {
    $source = WFEBPG_DOCX_Reader::read($argv[2], true);
    $faq = invoke('build_repeatable_items_for_scope', $source, 3, 2, 4);
    $validation = WFEBPG_Generator::validate_template_json(file_get_contents($argv[1]), $source);
    $trace = array_column($validation['mapping_debug'], null, 'element_id');
    check($trace['27a1e10f']['status'] === 'MATCH' && $trace['5b6b27f']['status'] === 'MATCH', 'Validator paragraph trace differs from generation');
    $faq_trace = array_values(array_filter($trace, static function($row){return $row['widget'] === 'accordion';}));
    check(count($faq_trace) === 1 && $faq_trace[0]['scope'] === '1.4', 'Validator FAQ trace uses preceding section');
    foreach ([false,true] as $no_limit) {
        WFEBPG_Generator::generate(['docx'=>$argv[2],'template'=>$argv[1],'page_title'=>'Regression check.docx',
            'widgets_per_section'=>4,'widgets_no_limit'=>$no_limit,'zip_free_fallback'=>true]);
        $generated = json_decode($GLOBALS['saved_meta']['_elementor_data'], true);
        $flat = flatten($generated);
        $by_id = array_column($flat, null, 'id');
        check(plain($by_id['27a1e10f']['settings']['title']) === plain($source['items'][1]['text']), 'Attached template intro body missing');
        $expected_contact = implode("\n\n", array_column(array_slice($source['items'],55), 'text'));
        check(plain($by_id['5b6b27f']['settings']['editor']) === plain($expected_contact), 'Attached template contact paragraphs missing');
        $accordions = array_values(array_filter($flat, static function($node){return ($node['widgetType'] ?? '') === 'accordion';}));
        check(count($accordions) === 1, 'FAQ layout was cloned');
        $tabs = $accordions[0]['settings']['tabs'];
        check(array_column($tabs,'tab_title') === array_column($faq,'heading'), 'Attached template FAQ titles incorrect');
        foreach ($faq as $i=>$item) check(plain($tabs[$i]['tab_content']) === plain(implode("\n\n",$item['content'])), 'FAQ answer mismatched');
        $icons = array_values(array_filter($flat, static function($node){return ($node['widgetType'] ?? '') === 'icon-box' && WFEBPG_Template::marker($node)['type'] === 'h3';}));
        check(count($icons) === 18, 'Services, benefits, or process cards lost/duplicated');
        $expected_icons = [];
        foreach ([1,2,3] as $scope) $expected_icons = array_merge($expected_icons, invoke('build_repeatable_items_for_scope', $source, 3, 2, $scope));
        foreach ($icons as $i=>$icon) {
            check(plain($icon['settings']['title_text']) === plain($expected_icons[$i]['heading']), 'Card heading reordered or mismatched');
            check(plain($icon['settings']['description_text']) === plain(implode("\n\n",$expected_icons[$i]['content'])), 'Card paragraph lost or mismatched');
        }
        echo 'Attached files passed ('.($no_limit ? 'unlimited' : 'numeric').'): intro, contact paragraphs, 18 cards, '.count($tabs)." FAQ answers.\n";
    }
    // Removing external card repeat markers must not leave an FAQ-only repeater untouched.
    $faq_only = WFEBPG_Template::decode(file_get_contents($argv[1]));
    $remove = function (&$nodes) use (&$remove) {
        foreach ($nodes as &$node) {
            if (($node['widgetType'] ?? '') === 'icon-box') $node['settings']['_attributes'] = 'data-customID|h3|p';
            if (isset($node['elements'])) $remove($node['elements']);
        }
        unset($node);
    };
    $remove($faq_only);
    $temporary = tempnam(sys_get_temp_dir(), 'wfebpg-mapping-');
    try {
        file_put_contents($temporary, json_encode(['content'=>$faq_only]));
        WFEBPG_Generator::generate(['docx'=>$argv[2],'template'=>$temporary,'page_title'=>'FAQ-only regression.docx','zip_free_fallback'=>true]);
        $flat = flatten(json_decode($GLOBALS['saved_meta']['_elementor_data'], true));
        $accordions = array_values(array_filter($flat, static function($node){return ($node['widgetType'] ?? '') === 'accordion';}));
        check(array_column($accordions[0]['settings']['tabs'],'tab_title') === array_column($faq,'heading'), 'FAQ-only generation skipped internal repeater');
    } finally {
        unlink($temporary);
    }
    echo "Attached files passed: Validator paragraph/FAQ scopes and FAQ-only generation.\n";
}
echo "Paragraph and FAQ mapping checks passed.\n";
