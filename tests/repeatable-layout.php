<?php
// Run: php tests/repeatable-layout.php [path/to/ImGonnaConsooooooooooooooooooooom.json]
require __DIR__.'/generation-mapping.php';

function assert_column_width($column, $capacity) {
    $settings = $column['settings'];
    check(is_int($settings['_column_size']), 'Fractional Elementor column class would be generated');
    check($settings['_column_size'] >= 1 && $settings['_column_size'] <= 100, 'Invalid column class');
    check(is_numeric($settings['_inline_size']) && abs($settings['_inline_size'] - 100/$capacity) < 0.000001, 'Exact custom width missing');
}
$columns = [];
for ($i=0; $i<3; $i++) {
    $columns[] = ['id'=>'column'.$i,'elType'=>'column','settings'=>['customID'=>'repeat','_column_size'=>33,'_inline_size'=>null],
        'elements'=>[['id'=>'flip'.$i,'elType'=>'widget','widgetType'=>'flip-box','elements'=>[],
            'settings'=>['customID'=>'h3|p','title_text_a'=>'Front','title_text_b'=>'Back','description_text_b'=>'Body',
                'background_a_image'=>['id'=>$i+1,'url'=>'https://example.com/image'.$i.'.jpg'],'title_color_a'=>'#ffffff']]]];
}
$template = [node('scope','h2'),node('row','','section',$columns)];
$key = 'structural:3|h1:0|h2:1';
if (isset($argv[1])) {
    $template = WFEBPG_Template::decode(file_get_contents($argv[1]));
    $key = 'structural:3|h1:1|h2:1';
}
$original_columns = array_values(array_filter(flatten($template), static function($node){
    return ($node['elType'] ?? '') === 'column' && WFEBPG_Template::custom_id($node) === 'repeat';
}));
foreach ([3,6,7] as $capacity) {
    foreach ([4,10,11] as $count) {
        $elements = $template;
        $items = [];
        for ($i=1; $i<=$count; $i++) $items[] = ['heading'=>'Item '.$i,'content'=>['Body '.$i]];
        invoke('populate_repeatables',$elements,[$key=>['target_level'=>3,'structural_repeat'=>true,'items'=>$items]],$capacity);
        $rows = [];
        foreach ($elements as $section) {
            $cards = array_values(array_filter($section['elements'] ?? [],static function($node){
                return ($node['elType'] ?? '') === 'column' && WFEBPG_Template::custom_id($node) === 'repeat';
            }));
            if ($cards) $rows[] = [$section,$cards];
        }
        check(count($rows) === (int)ceil($count/$capacity), 'Incorrect row count');
        $index = 0;
        foreach ($rows as [$section,$cards]) {
            check(count($cards) === min($capacity,$count-$index), 'Incorrect cards per row');
            if (count($cards)<$capacity) check(strpos($section['settings']['css_classes'] ?? '','wfebpg-partial-centered') !== false,'Partial row not centered');
            foreach ($cards as $column) {
                assert_column_width($column,$capacity);
                $source = $original_columns[$index % count($original_columns)]['elements'][0]['settings'];
                $index++;
                $source['title_text_a'] = $source['title_text_b'] = 'Item '.$index;
                $source['description_text_b'] = 'Body '.$index;
                check($column['elements'][0]['settings'] === $source, 'Card content, image, or styling was lost/reordered');
            }
        }
        check($index === $count,'Cards missing');
    }
}
// The legacy centering path must also avoid fractional elementor-col-* classes.
$legacy = $original_columns[0];
unset($legacy['settings']['customID'],$legacy['settings']['_attributes']);
$legacy['elements'][0]['settings']['customID'] = 'h3|p|repeat';
unset($legacy['elements'][0]['settings']['_attributes']);
$elements = [node('legacy-row','','section',[$legacy,$legacy,$legacy])];
$items = [];
for ($i=0; $i<4; $i++) $items[]=['heading'=>'Legacy '.$i,'content'=>['Legacy body']];
invoke('populate_repeatables',$elements,['legacy-row'=>['target_level'=>3,'items'=>$items]],3);
check(count($elements) === 2 && count($elements[1]['elements']) === 1,'Legacy partial row malformed');
assert_column_width($elements[1]['elements'][0],3);
echo "Repeat layout passed: valid Elementor column classes, exact widths for 3/6/7 columns, centered partial rows, prototype preservation, and legacy centering.\n";
