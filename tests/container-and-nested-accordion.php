<?php
// Run: php tests/container-and-nested-accordion.php [template.json document.docx]
$fixture_args=$argv;
$argv=array_slice($argv,0,1);
require __DIR__.'/generation-mapping.php';
$argv=$fixture_args;
function container_node($id,$children,$classes='') {
    return ['id'=>$id,'elType'=>'container','settings'=>['css_classes'=>$classes,'flex_direction'=>'column'],'elements'=>$children];
}
$card=container_node('service-card',[
    ['id'=>'service-title','elType'=>'widget','widgetType'=>'heading','settings'=>['title'=>'Old service','_css_classes'=>'wf-h3 wf-link'],'elements'=>[]],
    ['id'=>'service-body','elType'=>'widget','widgetType'=>'text-editor','settings'=>['editor'=>'Old body','_css_classes'=>'body-style wf-p'],'elements'=>[]],
],'card-style wf-repeat');
$card['settings']['width']=['unit'=>'%','size'=>47];
$faq=['id'=>'faq','elType'=>'widget','widgetType'=>'nested-accordion','settings'=>['_css_classes'=>'faq-style wf-h3 wf-p wf-repeat','items'=>[['_id'=>'old','item_title'=>'Old question']]],
    'elements'=>[container_node('faq-panel',[['id'=>'faq-body','elType'=>'widget','widgetType'=>'text-editor','settings'=>['editor'=>'Old answer','_css_classes'=>'answer-style'],'elements'=>[]]])]];
$template=[container_node('page',[
    container_node('hero',[node('h1','h1')]),
    container_node('services',[node('services-heading','h2'),container_node('service-grid',[$card],'grid-style')]),
    container_node('faqs',[node('faq-heading','h2'),$faq]),
    container_node('after',[node('after-heading','h2'),node('after-body','p','text-editor')]),
])];
$doc=['items'=>[],'repeatables'=>[]];
foreach ([[1,'Page'],[2,'Services'],[3,'Service A'],[0,'Body A'],[3,'Service B'],[0,'Body B'],[3,'Service C'],[0,'Body C'],[3,'Service D'],[0,'Body D'],[2,'FAQs'],[3,'Question A?'],[0,'Answer A'],[3,'Question B?'],[0,'Answer B'],[2,'After'],[0,'After body']] as [$level,$text]) {
    $doc['items'][]=['text'=>$text,'heading'=>$level>0,'heading_level'=>$level,'repeatable'=>false];
}
if (isset($argv[1],$argv[2])) {
    $template=WFEBPG_Template::decode(file_get_contents($argv[1]));
    $doc=WFEBPG_DOCX_Reader::read($argv[2],true);
}
$source_cards=array_values(array_filter(flatten($template),static function($n){return ($n['elType'] ?? '')==='container' && WFEBPG_Template::custom_id($n)==='repeat';}));
$expected_width=$source_cards[0]['settings']['width'] ?? null;
foreach ([1,3,4] as $limit) foreach ([false,true] as $no_limit) {
    $elements=$template;
    $collections=invoke('build_repeatable_collections',$doc,$elements);
    check(count($collections)===1,'Nested FAQ leaked into external repeat collections');
    $internal=invoke('build_internal_repeatable_items',$doc,$elements);
    check(count($internal)===1 && $internal[0]['widget_type']==='nested-accordion','Nested FAQ not recognized as internal repeater');
    $exclusions=invoke('flatten_repeatable_collections',$collections);
    foreach($internal as $collection)$exclusions=array_merge($exclusions,$collection['items']);
    invoke('populate_nonrepeat',$elements,$doc,$exclusions);
    invoke('populate_inline_repeat_text_editors',$elements,$doc);
    invoke('populate_internal_repeaters',$elements,$doc);
    invoke('populate_repeatables',$elements,$collections,$limit,[],$doc,$no_limit);
    $flat=flatten($elements);
    $cards=array_values(array_filter($flat,static function($n){return ($n['elType'] ?? '')==='container' && WFEBPG_Template::custom_id($n)==='repeat';}));
    check(count($cards)===4,'Container repeat did not create four service cards');
    $items=array_values($collections)[0]['items'];
    foreach($cards as $i=>$c) {
        $title_nodes=array_values(array_filter(flatten($c['elements']),static function($n){return WFEBPG_Template::custom_id($n)==='h3';}));
        $body_nodes=array_values(array_filter(flatten($c['elements']),static function($n){return WFEBPG_Template::custom_id($n)==='p';}));
        check(($title_nodes[0]['settings']['title'] ?? '')===$items[$i]['heading'],'Service heading missing or reordered');
        check(plain($body_nodes[0]['settings']['editor'] ?? '')===plain(implode("\n\n",$items[$i]['content'])),'Service body missing or incorrect');
        check(($c['settings']['width'] ?? null)===$expected_width,'Native card width changed');
    }
    $accordions=array_values(array_filter($flat,static function($n){return ($n['widgetType'] ?? '')==='nested-accordion';}));
    check(count($accordions)===1,'FAQ widget was cloned');
    $n=$accordions[0];$expected=$internal[0]['items'];
    check(array_column($n['settings']['items'],'item_title')===array_column($expected,'heading'),'Nested FAQ titles not mapped');
    check(count($n['elements'])===count($expected),'Nested FAQ panel count incorrect');
    foreach($expected as $i=>$item) {
        check(plain($n['elements'][$i]['elements'][0]['settings']['editor'])===plain(implode("\n\n",$item['content'])),'Nested FAQ answer mismatched');
    }
    $ids=array_column(flatten($n['elements']),'id');
    check(count(array_unique($ids))===count($ids),'Nested panel IDs duplicated');
    if (!isset($argv[1])) {
        $grids=array_values(array_filter($flat,static function($n){return strpos($n['settings']['css_classes'] ?? '','grid-style')!==false;}));
        check(count($grids)===($no_limit?1:(int)ceil(4/$limit)),'Container row limit ignored');
        $by_id=array_column($flat,null,'id');
        check(plain($by_id['after-body']['settings']['title'] ?? $by_id['after-body']['settings']['editor'])==='After body','Following section content was consumed by repeats');
    }
}
echo "Container/Nested Accordion passed: independent service and FAQ collections, repeat limits, card/body/style preservation, unique panel IDs, and exact FAQ pairs.\n";
