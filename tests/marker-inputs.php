<?php
// Run: php tests/marker-inputs.php
require __DIR__.'/generation-mapping.php';

function marked_input($marker,$source,$structural=false) {
    $settings = ['title'=>'Placeholder'];
    if ($source==='field') $settings['wfebpg_marker']=$marker;
    elseif ($source==='css') $settings[$structural?'css_classes':'_css_classes']='keep-style wf-card wf-'.str_replace('|',' wf-',$marker);
    else $settings['_attributes']='data-customID|'.$marker;
    return ['id'=>'target','elType'=>$structural?'container':'widget','widgetType'=>'heading','settings'=>$settings,'elements'=>[]];
}
foreach (['h1','h2','h3','h4','p','step','repeat','repeatable','link','h3|p','h3|p|repeat|link',
    'h3|p|repeatable','h1nonrepeat','sectionTitleNonRepeat','hNonRepeat','pNonRepeat','repeatableItem','stepNumber'] as $marker) {
    $expected=WFEBPG_Template::marker(marked_input($marker,'attribute'));
    foreach (['field','css'] as $source) {
        foreach ([false,true] as $structural) {
            $node=marked_input($marker,$source,$structural);
            $actual=WFEBPG_Template::marker($node);
            check($actual['type']===$expected['type'] && $actual['flags']===$expected['flags'],'Input differs for '.$source.': '.$marker);
            $roundtrip=WFEBPG_Template::decode(WFEBPG_Template::encode([$node]));
            check(WFEBPG_Template::marker($roundtrip[0])===$actual,'Export/import lost marker');
        }
    }
}
$node=marked_input('h3|link','css');
$node['settings']['_css_classes']='wf-link keep-style wf-p wf-repeat wf-h3';
$marker=WFEBPG_Template::marker($node);
check($marker['type']==='h3' && in_array('p',$marker['flags'],true) && in_array('repeatable',$marker['flags'],true) && in_array('link',$marker['flags'],true),'CSS class order changed meaning');
$node['settings']['_attributes']="aria-label|Styling only\ndata-customID|h2|p\ndata-extra|keep";
check(WFEBPG_Template::custom_id($node)==='h2','Legacy marker not read from multiline attributes');
$node['settings']['wfebpg_marker']='h1';
check(WFEBPG_Template::custom_id($node)==='h1','Dedicated field priority incorrect');
$node['settings']['wfebpg_marker']=' ';
check(WFEBPG_Template::custom_id($node)==='h2','Empty field blocked fallback');
$node['settings']['_attributes']='aria-label|No marker';
check(WFEBPG_Template::custom_id($node)==='h3','Unrelated attribute blocked CSS marker');
$node['settings']=['_css_classes'=>'wf-card pink wf-headingish','wfebpg_marker'=>''];
check(WFEBPG_Template::custom_id($node)==='','Styling classes were treated as markers');
$node['settings']=['wfebpg_marker'=>'data-customID|h3|link'];
check(WFEBPG_Template::custom_id($node)==='h3','Pasted full attribute not accepted');
$node['settings']=['_attributes'=>['aria-label'=>'Keep','data-customID'=>'h3|link']];
check(WFEBPG_Template::has_flag($node,'link'),'Attribute map unsupported');

$node=marked_input('h3|repeat','field');
$node['settings']+=['_css_classes'=>'wf-h3 keep-style wf-repeat wf-card',
    'css_classes'=>'another-style wf-link', 'customID'=>'h2',
    '_attributes'=>"aria-label|Keep\ndata-customID|h1\ndata-extra|Preserve"];
WFEBPG_Template::clear_marker($node);
check(WFEBPG_Template::custom_id($node)==='','Clearing marker exposed a lower-priority source');
check($node['settings']['_css_classes']==='keep-style wf-card' && $node['settings']['css_classes']==='another-style','Clearing marker damaged styling classes');
check($node['settings']['_attributes']==="aria-label|Keep\ndata-extra|Preserve",'Clearing marker removed unrelated attributes');

$doc=['items'=>[]];
foreach ([[1,'Page'],[2,'Section'],[3,'Item'],[0,'Item paragraph']] as [$level,$text]) {
    $doc['items'][]=['text'=>$text,'heading'=>$level>0,'heading_level'=>$level,'repeatable'=>false];
}
foreach (['field','css','attribute'] as $source) {
    $elements=[];
    foreach (['h1','h2','h3'] as $type) $elements[]=marked_input($type,$source);
    invoke('populate_nonrepeat',$elements,$doc);
    check(array_column(array_column($elements,'settings'),'title')===['Page','Section','Item'],'DOCX heading mapping failed for '.$source);
    $card=marked_input('repeat',$source,true);
    $card['elements']=[marked_input('h3|p',$source)];
    $card['elements'][0]['widgetType']='icon-box';
    $card['elements'][0]['settings']+=['title_text'=>'Placeholder','description_text'=>'Body'];
    unset($card['elements'][0]['settings']['title']);
    invoke('populate_repeatable_card',$card,['heading'=>'Repeated','content'=>['Repeated body']]);
    check($card['elements'][0]['settings']['title_text']==='Repeated' && $card['elements'][0]['settings']['description_text']==='Repeated body','Structural repeat mapping failed for '.$source);
    $stream=marked_input('h3|p|repeat',$source);
    $stream['widgetType']='text-editor';
    $stream['settings']['editor']='Placeholder';
    $elements=[marked_input('h1',$source),marked_input('h2',$source),$stream];
    invoke('populate_inline_repeat_text_editors',$elements,$doc);
    check(strpos($elements[2]['settings']['editor'],'<h3>Item</h3>')!==false,'Inline repeat content missing for '.$source);
    check(WFEBPG_Template::custom_id($elements[2])==='' && !WFEBPG_Template::is_repeatable($elements[2]),'Inline stream marker was not cleared for '.$source);
}
echo "Marker inputs passed: field/CSS/attribute equivalence, all tokens, export roundtrip, priority, class preservation, heading mapping, and structural repeats.\n";
