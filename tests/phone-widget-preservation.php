<?php
// Run: php tests/phone-widget-preservation.php
require __DIR__.'/generation-mapping.php';
function phone_widget($type,$settings) { return ['id'=>'test','elType'=>'widget','widgetType'=>$type,'settings'=>$settings,'elements'=>[]]; }
$cases=[
    phone_widget('button',['text'=>'Call (202) 866-5480','link'=>['url'=>'tel:+12028665480','is_external'=>false],'button_text_color'=>'#123456']),
    phone_widget('button',['text'=>'Call now','link'=>['url'=>'tel:+12028665480'],'description'=>'Other line 501-542-2531']),
    phone_widget('jkit_button',['sg_button_text'=>'Call now','sg_button_link'=>['url'=>'TEL:2028665480']]),
    phone_widget('elementskit-button',['text'=>'Call','url'=>['url'=>'tel:2028665480']]),
    phone_widget('heading',['title'=>'Call <span style="color:red">(202) 866-5480</span>','link'=>['url'=>'tel:+12028665480']]),
    phone_widget('heading',['title'=>'202-866-5480','link'=>['url'=>' TEL:%2B1%20202%20866%205480;ext=99 ']]),
    phone_widget('heading',['title'=>'Call 2028665480','link'=>'tel:+12028665480']),
    phone_widget('text-editor',['editor'=>'<p>Call (202) 866-5480.</p><p>Other 501-542-2531.</p>','link'=>['url'=>'tel:+12028665480']]),
    phone_widget('flip-box',['title_text_a'=>'(202) 866-5480','title_text_b'=>'Other','link'=>['url'=>'tel:2028665480']]),
    phone_widget('jkit_heading',['sg_title_text'=>'(202) 866-5480','sg_title_link'=>['url'=>'tel:2028665480']]),
];
foreach($cases as $widget){
    check(WFEBPG_Phone_Linker::preserve_widget($widget),'Native tel should preserve '.$widget['widgetType']);
    $elements=[$widget];invoke('apply_phone_links',$elements);
    check($elements[0]===$widget,'Preserved widget was modified: '.$widget['widgetType']);
}
foreach ([phone_widget('button',['text'=>'Call 202-866-5480','link'=>['url'=>'']]),
    phone_widget('heading',['title'=>'Call 501-542-2531','link'=>['url'=>'tel:+12028665480'],'_title'=>'202-866-5480']),
    phone_widget('heading',['title'=>'Call 501-542-2531','link'=>'tel:+12028665480'])] as $widget) {
    check(!WFEBPG_Phone_Linker::preserve_widget($widget),'Absent/mismatched native tel incorrectly preserved');
    $elements=[$widget];invoke('apply_phone_links',$elements);
    $key=$widget['widgetType']==='button'?'text':'title';
    check(strpos($elements[0]['settings'][$key],'<a href="tel:')!==false,'Unlinked/mismatched displayed number was not processed');
    check($elements[0]['settings']['link']===$widget['settings']['link'],'Native destination changed');
}
$original='<a href="tel:+12028665480" style="color:purple;text-decoration:none">(202) 866-5480</a>';
$elements=[phone_widget('text-editor',['editor'=>$original.' or 501-542-2531'])];
invoke('apply_phone_links',$elements);
check(strpos($elements[0]['settings']['editor'],$original)===0,'Existing HTML tel anchor changed');
check(strpos($elements[0]['settings']['editor'],'href="tel:+15015422531"')!==false,'Other unlinked number was not linked');
$elements=[['id'=>'parent','elType'=>'container','settings'=>[],'elements'=>[
    $cases[0],phone_widget('text-editor',['editor'=>'501-542-2531'])]]];
invoke('apply_phone_links',$elements);
check($elements[0]['elements'][0]===$cases[0],'Nested button changed');
check(strpos($elements[0]['elements'][1]['settings']['editor'],'href="tel:+15015422531"')!==false,'Child text not processed');
echo "Phone widget preservation passed: native tel buttons, matched heading/text/third-party links, normalized formats, unchanged URL settings, existing anchors, and unlinked-number processing.\n";
