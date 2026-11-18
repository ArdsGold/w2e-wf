<?php
// Run: php tests/title-linker.php [attribute|field|css] (no WordPress, Elementor, or network required).
define('ABSPATH', __DIR__);
define('OBJECT', 'OBJECT');
$options = $meta = $posts = $logs = $invalidated = [];
function get_option($key,$default=false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key,$value,$autoload=null) { $GLOBALS['options'][$key]=$value; }
function add_option($key,$value,$deprecated='',$autoload=false) {
    if (isset($GLOBALS['options'][$key])) return false;
    update_option($key,$value); return true;
}
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function absint($value) { return abs((int)$value); }
function wp_generate_uuid4() { return bin2hex(random_bytes(16)); }
function wp_strip_all_tags($value) { return strip_tags($value); }
function esc_html($value) { return htmlspecialchars($value,ENT_QUOTES,'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function wpautop($value) { return $value === '' ? '' : '<p>'.$value.'</p>'; }
function wp_json_encode($value,$flags=0) { return json_encode($value,$flags); }
function wp_slash($value) { return $value; }
function sanitize_title($value) { return strtolower(str_replace(' ','-',$value)); }
function is_wp_error($value) { return false; }
function get_page_by_path($path,$output,$type) { return null; }
function wp_insert_post($post,$error) {
    $id=count($GLOBALS['posts'])+1000;
    $GLOBALS['posts'][$id]=(object)($post+['ID'=>$id]);
    return $id;
}
function update_post_meta($id,$key,$value) { $GLOBALS['meta'][$id][$key]=$value; }
function get_post_meta($id,$key,$single) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function delete_post_meta($id,$key) {
    unset($GLOBALS['meta'][$id][$key]);
    if ($key === '_elementor_element_cache') $GLOBALS['invalidated'][]=$id;
}
function clean_post_cache($id) {}
function wp_cache_delete($id,$group) {}
function current_time($format) { return '2026-11-18 00:00:00'; }
function get_post($id) { return $GLOBALS['posts'][$id] ?? null; }
function get_permalink($id) { return $GLOBALS['permalinks'][$id] ?? 'https://example.test/page/'.$id.'/'; }
function get_posts($args) {
    check($args['post_type'] === 'page','Link lookup includes non-pages');
    return array_values(array_filter(array_keys($GLOBALS['posts']),static function($id) use ($args) {
        $post=$GLOBALS['posts'][$id];
        return $post->post_type === 'page' && ($args['post_status'] === 'any' || $post->post_status === 'publish')
            && !in_array($id,$args['post__not_in'] ?? [],true)
            && (isset($args['meta_value']) ? get_post_meta($id,$args['meta_key'],true) === $args['meta_value']
                : get_post_meta($id,$args['meta_key'],true) !== '');
    }));
}
class WFEBPG_Logger { public static function log($message,$level='info') { $GLOBALS['logs'][]=[$message,$level]; } }
class WFEBPG_DOCX_Reader {
    public static function read($path,$fallback) {
        if ($path !== 'source') return ['items'=>[],'repeatables'=>[]];
        $items=[];
        foreach ([[1,'Source'],[2,'Services'],[3,'Residential garage doors']] as [$level,$text]) {
            $items[]=['text'=>$text,'heading'=>true,'heading_level'=>$level,'repeatable'=>false];
        }
        return ['items'=>$items,'repeatables'=>[]];
    }
}
$wpdb=new class {
    public $posts='wp_posts';
    public function get_results($sql) {
        check(strpos($sql,"post_type = 'page'") !== false && strpos($sql,"post_status = 'publish'") !== false,'Unsafe target scope');
        return array_values(array_filter($GLOBALS['posts'],static function($post){return $post->post_type==='page' && $post->post_status==='publish';}));
    }
};
require __DIR__.'/../includes/class-template.php';
require __DIR__.'/../includes/class-phone-linker.php';
require __DIR__.'/../includes/class-title-linker.php';
require __DIR__.'/../includes/class-generator.php';
function check($condition,$message) { if (!$condition) throw new RuntimeException($message); }
function widget($type,$settings,$marker='link') {
    $source = $GLOBALS['argv'][1] ?? 'attribute';
    if ($source === 'field') $settings['wfebpg_marker'] = $marker;
    elseif ($source === 'css') $settings['_css_classes'] = 'keep-style wf-' . str_replace('|',' wf-',$marker);
    else $settings['_attributes'] = 'data-customID|' . $marker;
    return ['id'=>wp_generate_uuid4(),'elType'=>'widget','widgetType'=>$type,'settings'=>$settings,'elements'=>[]];
}
function page($id,$title,$status='publish',$type='page') {
    $GLOBALS['posts'][$id]=(object)['ID'=>$id,'post_title'=>$title,'post_status'=>$status,'post_type'=>$type];
}
page(1,'Residential Garage Doors');
page(2,'Draft Only','draft'); page(3,'Post Only','publish','post');
page(4,'Duplicate'); page(5,'DUPLICATE'); page(6,'ÉCLAIR'); page(7,'Rock & Roll');
$fixtures=[
    'heading'=>['title','link'], 'icon-box'=>['title_text','link'], 'image-box'=>['title_text','link'],
    'button'=>['text','link'], 'flip-box'=>['title_text_a','link'],
    'elementskit-heading'=>['ekit_heading_title','ekit_heading_link'],
    'elementskit-icon-box'=>['ekit_icon_box_title_text','ekit_icon_box_global_link'],
    'jkit_heading'=>['sg_title_text','sg_title_link'], 'jkit_icon_box'=>['sg_icon_text','sg_readmore_globallink'],
];
foreach ($fixtures as $type=>[$title_key,$link_key]) {
    $elements=[widget($type,[$title_key=>'<b> residential </b>  garage&#160;doors ',$link_key=>['url'=>'#','is_external'=>true,'nofollow'=>true],'color'=>'#123456'])];
    check(!WFEBPG_Title_Linker::apply($elements,99),'Matched title marked pending: '.$type);
    $s=$elements[0]['settings'];
    check($s[$link_key]['url']===get_permalink(1),'Native link field incorrect: '.$type);
    check($s[$link_key]['is_external'] && $s[$link_key]['nofollow'] && $s['color']==='#123456','Template options changed: '.$type);
    $again=$elements;
    WFEBPG_Title_Linker::apply($elements,99);
    check($elements===$again,'Second pass changed a resolved widget');
}
foreach (['Draft Only','Post Only','Duplicate','Residential garage door','Residential-garage doors'] as $title) {
    $elements=[widget('heading',['title'=>$title,'link'=>['url'=>'#']])];
    check(WFEBPG_Title_Linker::apply($elements,99),'Missing/ambiguous title not pending');
    check($elements[0]['settings']['link']['url']==='','Missing match did not leave blank');
}
check(count(array_filter($logs,static function($log){return strpos($log[0],'Ambiguous title link')!==false;}))===1,'Ambiguous match was not logged');
foreach (['éclair'=>6,'Rock &amp; Roll'=>7] as $title=>$id) {
    $elements=[widget('heading',['title'=>$title])];
    WFEBPG_Title_Linker::apply($elements,99);
    check($elements[0]['settings']['link']['url']===get_permalink($id),'Unicode/entity match failed');
}
$elements=[widget('heading',['title'=>'Residential Garage Doors'])];
check(WFEBPG_Title_Linker::apply($elements,1),'Self-link was allowed');
foreach ([['title'=>'Residential Garage Doors','link'=>['url'=>'https://manual.test/']],
    ['title'=>'Residential Garage Doors','__dynamic__'=>['link'=>'dynamic-tag']]] as $s) {
    $elements=[widget('heading',$s)]; $original=$elements;
    check(!WFEBPG_Title_Linker::apply($elements,99) && $elements===$original,'Manual/dynamic link changed');
}
$elements=[widget('heading',['title'=>'Residential Garage Doors'],'h3')];$original=$elements;
WFEBPG_Title_Linker::apply($elements,99);
check($elements===$original,'Unmarked link changed');
$elements=[widget('icon-list',['icon_list'=>[['text'=>'Residential Garage Doors'],['text'=>'éclair']]])];
WFEBPG_Title_Linker::apply($elements,99);
check($elements[0]['settings']['icon_list'][1]['link']['url']===get_permalink(6),'List item link missing');
$elements=[widget('elementskit-icon-box',['ekit_icon_box_title_text'=>'Residential Garage Doors','ekit_icon_box_enable_btn'=>'yes'])];
WFEBPG_Title_Linker::apply($elements,99);
check($elements[0]['settings']['ekit_icon_box_btn_url']['url']===get_permalink(1),'Active Icon Box link mode ignored');

// Legacy repeated title markers keep |link through cloning and content filling.
$card=['id'=>'column','elType'=>'column','settings'=>[],
    'elements'=>[widget('heading',['title'=>'Prototype'],'h3|repeat|link')]];
$elements=[['id'=>'repeat-row','elType'=>'section','settings'=>[], 'elements'=>[$card,$card]]];
$method=new ReflectionMethod(WFEBPG_Generator::class,'populate_repeatables');
if (PHP_VERSION_ID < 80100) $method->setAccessible(true);
$items=[['heading'=>'Residential Garage Doors','content'=>[]],['heading'=>'éclair','content'=>[]]];
$method->invokeArgs(null,[&$elements,['repeat-row'=>['target_level'=>3,'items'=>$items]],2]);
WFEBPG_Title_Linker::apply($elements,99);
check($elements[0]['elements'][1]['elements'][0]['settings']['link']['url']===get_permalink(6),'Repeated title link failed');
$validated=WFEBPG_Generator::validate_template_json(json_encode(['content'=>$elements]));
check($validated['counts']['link']===2 && $validated['counts']['h3']===2,'Validator did not count combined link markers');
$validated=WFEBPG_Generator::validate_template_json(json_encode(['content'=>[widget('text-editor',['editor'=>'Body'],'link')]]));
check(count($validated['warnings'])===1 && $validated['counts']['link']===1,'Unsupported widget marker not reported');

// Full generation and queue retry across separate worker batches.
$posts=$meta=$options=[];
$template=tempnam(sys_get_temp_dir(),'wfebpg-title-');
$empty=tempnam(sys_get_temp_dir(),'wfebpg-empty-');
try {
    file_put_contents($template,json_encode(['content'=>[
        widget('heading',['title'=>'placeholder'],'h1'), widget('heading',['title'=>'placeholder'],'h2'),
        widget('heading',['title'=>'placeholder'],'h3|link')
    ]]));
    file_put_contents($empty,'{"content":[]}');
    $jobs=[['docx'=>'source','page_title'=>'Source.docx','template'=>$template]];
    foreach (['Filler One','Filler Two','Residential Garage Doors'] as $title) $jobs[]=['docx'=>'empty','page_title'=>$title.'.docx','template'=>$empty];
    update_option(WFEBPG_Generator::QUEUE_OPTION,$jobs);
    $first=WFEBPG_Generator::process_queue();
    check($first['processed']===3 && $first['failed']===0 && $first['remaining']===1,'Initial batch failed');
    check(get_post_meta(1000,WFEBPG_Title_Linker::PENDING_META,true)==='1','Forward reference was not persisted');
    $data=json_decode(get_post_meta(1000,'_elementor_data',true),true);
    check($data[2]['settings']['title']==='Residential garage doors' && $data[2]['settings']['link']['url']==='','H3 link marker disrupted content/blank fallback');
    update_post_meta(1000,'_elementor_element_cache','stale HTML');
    $second=WFEBPG_Generator::process_queue();
    check($second['processed']===1 && $second['failed']===0 && $second['remaining']===0,'Second batch failed');
    $data=json_decode(get_post_meta(1000,'_elementor_data',true),true);
    check($data[2]['settings']['link']['url']===get_permalink(1003),'Later queue page did not backfill earlier heading');
    check(get_post_meta(1000,WFEBPG_Title_Linker::PENDING_META,true)==='','Resolved pending marker not cleared');
    check(in_array(1000,$invalidated,true) && get_post_meta(1000,'_elementor_element_cache',true)==='','Stale Elementor HTML cache survived');
} finally { unlink($template); unlink($empty); }
echo "Title links passed: native widget fields, normalized exact matching, published pages, ambiguity, self-link exclusion, manual links, and forward references across queue batches.\n";
