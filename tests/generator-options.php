<?php
// Full generation with a small DOCX ZIP and in-memory WordPress storage.
require __DIR__.'/generation-mapping.php';

$xml = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>';
foreach ([[1,'Page'],[2,'Section'],[0,'Section body'],[3,'First card'],[0,'First body'],[3,'Second card'],[0,'Second body']] as [$level,$text]) {
    $xml .= '<w:p>'.($level ? '<w:pPr><w:pStyle w:val="Heading'.$level.'"/></w:pPr>' : '').'<w:r><w:t>'.$text.'</w:t></w:r></w:p>';
}
$xml .= '</w:body></w:document>';
// A stored ZIP entry keeps this test usable without the ZipArchive extension.
$name = 'word/document.xml';
$size = strlen($xml);
$crc = crc32($xml);
$local = pack('VvvvvvVVVvv',0x04034b50,20,0,0,0,0,$crc,$size,$size,strlen($name),0).$name.$xml;
$central = pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,0,0,$crc,$size,$size,strlen($name),0,0,0,0,0,0).$name;
$zip = $local.$central.pack('VvvvvVVv',0x06054b50,0,0,1,1,strlen($central),strlen($local),0);
$docx = tempnam(sys_get_temp_dir(),'wfebpg-options-docx-');
$json = tempnam(sys_get_temp_dir(),'wfebpg-options-json-');
$flip = static function($id,$marker,$back) {
    return ['id'=>$id,'elType'=>'widget','widgetType'=>'flip-box','elements'=>[],
        'settings'=>['customID'=>$marker,'title_text_a'=>'Front','title_text_b'=>$back,
            'description_text_a'=>'Keep front','description_text_b'=>'Body','title_color_a'=>'#123456']];
};
$column = ['id'=>'card','elType'=>'column','settings'=>['customID'=>'repeat','_column_size'=>33],
    'elements'=>[$flip('repeated','h3|p','Original card back')]];
$template = [node('page','h1'),node('normal-row','','section',[
    $flip('normal','h2|p','Original heading back'),$flip('untouched','','Unmarked back')]),
    node('cards','','section',[$column,$column,$column])];
try {
    file_put_contents($docx,$zip);
    foreach (['Original heading back',''] as $original_back) {
    $template[1]['elements'][0]['settings']['title_text_b'] = $original_back;
    file_put_contents($json,json_encode(['content'=>$template]));
    foreach ([false,true,null] as $copy_back) {
        foreach ([false,true] as $fill_partial) {
            $job = ['docx'=>$docx,'template'=>$json,'page_title'=>'Options.docx','widgets_per_section'=>3,
                'repeat_fill_partial'=>$fill_partial,'zip_free_fallback'=>true];
            if ($copy_back !== null) $job['flipbox_back_title'] = $copy_back;
            WFEBPG_Generator::generate($job);
            $elements = json_decode($GLOBALS['saved_meta']['_elementor_data'],true);
            $flat = flatten($elements);
            $by_id = array_column($flat,null,'id');
            check($by_id['normal']['settings']['title_text_a'] === 'Section','Front heading missing');
            check($by_id['normal']['settings']['title_text_b'] === ($copy_back === false ? $original_back : 'Section'),'Normal back title incorrect');
            check($by_id['normal']['settings']['description_text_b'] === 'Section body','Back description missing');
            check($by_id['untouched']['settings'] === $template[1]['elements'][1]['settings'],'Unmarked Flip Box changed');
            $cards = array_values(array_filter($flat,static function($n){return ($n['elType'] ?? '') === 'column';}));
            check(count($cards) === 2,'Repeated cards missing');
            foreach ($cards as $i=>$card) {
                $settings = $card['elements'][0]['settings'];
                $heading = $i ? 'Second card' : 'First card';
                check($settings['title_text_a'] === $heading,'Repeated front title missing');
                check($settings['title_text_b'] === ($copy_back === false ? 'Original card back' : $heading),'Repeated back title incorrect');
                check($settings['description_text_a'] === 'Keep front' && $settings['title_color_a'] === '#123456','Template styles/front description changed');
                check(abs($card['settings']['_inline_size'] - ($fill_partial ? 50 : 100/3)) < 0.000001,'Queued partial width ignored');
            }
        }
    }
    }
    // A failed job must not leak its option into the next generation.
    $failed = false;
    try {
        WFEBPG_Generator::generate(['docx'=>$docx,'template'=>$json,'page_title'=>'','flipbox_back_title'=>false]);
    } catch (Exception $e) { $failed = true; }
    check($failed,'Expected invalid-title job to fail');
    $settings = ['title_text_a'=>'Front','title_text_b'=>'Back'];
    invoke('set_widget_title',$settings,'Next heading');
    check($settings['title_text_b'] === 'Next heading','Failed job leaked its Flip Box option');
} finally {
    unlink($docx);
    unlink($json);
}
echo "Generator options passed: job snapshots, original/empty back titles, repeated cards, partial widths, defaults, and failure isolation.\n";
