<?php
// Run with: php tests/repeatable-limit.php (no WordPress installation required).
define('ABSPATH', __DIR__);
function absint($value) { return abs((int) $value); }
function wp_generate_uuid4() { return bin2hex(random_bytes(16)); }
require __DIR__ . '/../includes/class-template.php';
require __DIR__ . '/../includes/class-generator.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
function widget($id, $marker) {
    return ['id'=>$id, 'elType'=>'widget', 'widgetType'=>'heading',
        'settings'=>['customID'=>$marker, 'title'=>'Prototype'], 'elements'=>[]];
}
function section($id, $children) {
    return ['id'=>$id, 'elType'=>'section', 'settings'=>[], 'elements'=>$children];
}
function items($prefix, $count) {
    return array_map(static function ($i) use ($prefix) {
        return ['heading'=>$prefix.$i, 'content'=>[]];
    }, range(1, $count));
}
function titles($elements) {
    $result = [];
    foreach ($elements as $element) {
        if (isset($element['settings']['title']) && $element['settings']['title'] !== 'Prototype') {
            $result[] = $element['settings']['title'];
        }
        $result = array_merge($result, titles($element['elements'] ?? []));
    }
    return $result;
}
$populate = new ReflectionMethod(WFEBPG_Generator::class, 'populate_repeatables');
if (PHP_VERSION_ID < 80100) $populate->setAccessible(true);
foreach ([false, true] as $structural) {
    $card = $structural
        ? ['id'=>'card', 'elType'=>'column', 'settings'=>['customID'=>'repeat'], 'elements'=>[widget('heading', 'h3')]]
        : widget('heading', 'h3|repeat');
    foreach ([false, true] as $no_limit) {
        // Both modes must preserve independent structural heading scopes.
        $sizes = ['a'=>105, 'b'=>7];
        $elements = [];
        $collections = [];
        $expected = [];
        foreach ($sizes as $id=>$count) {
            $elements[] = section($id, $structural ? [widget('scope-'.$id, 'h2'), $card] : [$card]);
            $key = $structural ? 'structural:3|h1:0|h2:'.($id === 'a' ? 1 : 2) : $id;
            $records = items($id, $count);
            $collections[$key] = ['target_level'=>3, 'structural_repeat'=>$structural, 'items'=>$records];
            $expected = array_merge($expected, array_column($records, 'heading'));
        }
        $populate->invokeArgs(null, [&$elements, $collections, 4, [], null, $no_limit]);
        $rows = $no_limit ? count($sizes) : array_sum(array_map(static function ($size) { return (int)ceil($size/4); }, $sizes));
        check(count($elements) === $rows, 'Unexpected section count');
        check(titles($elements) === $expected, 'Content was lost, reordered, or mixed');
        if ($no_limit) {
            check(count(titles([$elements[0]])) === 105, 'First group was split');
            check(count(titles([$elements[1]])) === 7, 'Second group was split');
        } else {
            foreach ($elements as $row) check(count(titles([$row])) <= 4, 'Numeric limit exceeded');
        }
    }
}
foreach ([false, true] as $no_limit) {
    $card = ['id'=>'card','elType'=>'column','settings'=>['customID'=>'repeat'],'elements'=>[widget('title-a','h3')]];
    $elements = [section('a',[widget('scope','h2'),$card]),section('b',[widget('title-b','h3|repeat')])];
    $collections = [
        'structural:3|h1:0|h2:1'=>['target_level'=>3,'structural_repeat'=>true,'items'=>items('a',7)],
        'b'=>['target_level'=>3,'structural_repeat'=>false,'items'=>items('b',5)],
    ];
    $populate->invokeArgs(null,[&$elements,$collections,4,[],null,$no_limit]);
    check(titles($elements) === array_merge(array_column(items('a',7),'heading'),array_column(items('b',5),'heading')), 'Mixed repeat styles lost content');
}
echo "Repeatable limits passed: numeric/unlimited modes, independent structural groups (105+7), mixed structural/widget groups.\n";
