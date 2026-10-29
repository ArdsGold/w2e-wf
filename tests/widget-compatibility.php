<?php
// Run: php tests/widget-compatibility.php [path/to/sudslingerspwtemplate1.0.json]
require __DIR__.'/generation-mapping.php';

function expected_widget_settings($settings, $type, $heading, $body, $has_heading, $has_body) {
    if ($has_heading) {
        if ($type === 'flip-box') {
            $settings['title_text_a'] = $heading;
            $settings['title_text_b'] = $heading;
        } else {
            $fields = ['jkit_heading'=>'sg_title_text','jkit_icon_box'=>'sg_icon_text','elementskit-heading'=>'ekit_heading_title'];
            $settings[$fields[$type]] = $heading;
        }
    }
    if ($has_body) {
        $fields = ['flip-box'=>'description_text_b','jkit_heading'=>'sg_description',
            'jkit_icon_box'=>'sg_icon_description','elementskit-heading'=>'ekit_heading_extra_title'];
        $settings[$fields[$type]] = $type === 'elementskit-heading' ? wpautop($body) : $body;
    }
    return $settings;
}

$doc = ['items'=>[]];
foreach ([[1,'Page title'],[0,'Intro body'],[2,'Section title'],[0,'Section body'],[3,'Item title'],[0,'Item body']] as $item) {
    $doc['items'][] = ['text'=>$item[1],'heading'=>$item[0]>0,'heading_level'=>$item[0],'repeatable'=>false];
}
$fixtures = [
    'flip-box'=>['title_text_a'=>'Front','title_text_b'=>'Back','description_text_a'=>'Keep front',
        'description_text_b'=>'Back placeholder','button_text'=>'Keep button'],
    'jkit_heading'=>['sg_title_text'=>'Title','sg_description'=>'Body','sg_title_before'=>'Keep prefix',
        'sg_title_focused'=>'Keep focused','sg_subtitle_heading'=>'Keep subtitle','sg_shadow_content'=>'Keep shadow'],
    'jkit_icon_box'=>['sg_icon_text'=>'Title','sg_icon_description'=>'Body','sg_badge_text'=>'Keep badge',
        'sg_readmore_button_label'=>'Keep button'],
    'elementskit-heading'=>['ekit_heading_title'=>'Title','ekit_heading_extra_title'=>'<p>Body</p>',
        'ekit_heading_focused_title'=>'Keep focused','ekit_heading_sub_title'=>'Keep subtitle',
        'ekit_heading_section_extra_title_show'=>'yes'],
];
foreach ($fixtures as $type=>$settings) {
    foreach (['h2','h2|p','h3','p','h3|p'] as $marker) {
        $widget = ['id'=>'target','elType'=>'widget','widgetType'=>$type,'settings'=>$settings + ['customID'=>$marker],'elements'=>[]];
        $original = $widget['settings'];
        $is_h2 = strpos($marker,'h2') === 0;
        $children = $is_h2 ? [$widget] : [node('h2','h2'),$widget];
        $elements = [node('h1','h1'),node('scope','','section',$children)];
        invoke('populate_nonrepeat', $elements, $doc);
        $expected = expected_widget_settings($original, $type, $is_h2 ? 'Section title' : 'Item title',
            ($marker === 'p' || $is_h2) ? 'Section body' : 'Item body', $marker !== 'p', in_array('p',explode('|',$marker),true));
        check($elements[1]['elements'][count($children)-1]['settings'] === $expected, $type.' failed marker '.$marker);
    }
    foreach (['h3|p|repeat','repeatableItem'] as $marker) {
        $widget = ['id'=>'target','elType'=>'widget','widgetType'=>$type,'settings'=>$settings + ['customID'=>$marker],'elements'=>[]];
        $expected = expected_widget_settings($widget['settings'], $type, 'Repeated heading', 'Repeated body', true, true);
        invoke('populate_repeatable_card', $widget, ['heading'=>'Repeated heading','content'=>['Repeated body']]);
        check($widget['settings'] === $expected, $type.' failed repeat path '.$marker);
    }
    $widget = ['id'=>'target','elType'=>'widget','widgetType'=>$type,'settings'=>$settings + ['customID'=>'h3|p'],'elements'=>[]];
    $container = ['id'=>'card','elType'=>'container','settings'=>['customID'=>'repeat'],'elements'=>[$widget]];
    invoke('populate_repeatable_card', $container, ['heading'=>'Structural heading','content'=>['Structural body']]);
    check($container['elements'][0]['settings'] === expected_widget_settings($widget['settings'],$type,'Structural heading','Structural body',true,true), $type.' failed structural path');
    unset($widget['settings']['customID']);
    $elements = [node('h1','h1'),node('h2','h2'),$widget];
    invoke('populate_nonrepeat', $elements, $doc);
    check($elements[2] === $widget, 'Unmarked '.$type.' changed');
}

if (isset($argv[1])) {
    $template = WFEBPG_Template::decode(file_get_contents($argv[1]));
    $tested = 0;
    foreach (flatten($template) as $widget) {
        $type = $widget['widgetType'] ?? '';
        if (!isset($fixtures[$type])) continue;
        $marker = WFEBPG_Template::marker($widget);
        if ($marker['type'] === '') continue;
        $heading = $marker['type'] === 'h2' ? 'Section title' : 'Item title';
        $body = $marker['type'] === 'h2' ? 'Section body' : 'Item body';
        $expected = expected_widget_settings($widget['settings'],$type,$heading,$body,true,in_array('p',$marker['flags'],true));
        $children = $marker['type'] === 'h2' ? [$widget] : [node('scope-heading','h2'),$widget];
        $elements = [node('h1','h1'),node('scope','','section',$children)];
        invoke('populate_nonrepeat', $elements, $doc);
        $mapped = $elements[1]['elements'][count($children)-1];
        check($mapped['settings'] === $expected, 'Attached template widget failed: '.$type.' #'.$widget['id']);
        $tested++;
    }
    check($tested > 0, 'No compatible marked widgets found');
    echo "Attached template passed: $tested marked JKit/Flip Box/ElementsKit Heading widgets; all other settings preserved.\n";
}
echo "Widget compatibility passed: heading/body markers, modern/legacy/structural repeats, and unchanged unmarked widgets.\n";
