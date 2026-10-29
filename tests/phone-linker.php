<?php
// Run: php tests/phone-linker.php (no WordPress installation required).
define('ABSPATH', __DIR__);
function esc_attr($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
require __DIR__.'/../includes/class-phone-linker.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }

foreach (['501-542-2531','(501) 542-2531','5015422531','+1 501 542 2531'] as $number) {
    $source = '<span style="color: #c02030">Call '.$number.'</span>';
    $result = WFEBPG_Phone_Linker::html($source);
    $dom = new DOMDocument();
    $dom->loadHTML($result);
    $links = $dom->getElementsByTagName('a');
    check($links->length === 1, 'Expected exactly one phone link');
    $link = $links->item(0);
    check($link->getAttribute('href') === 'tel:+15015422531', 'Phone destination changed');
    check($link->getAttribute('style') === 'color: inherit !important;', 'Phone color does not inherit');
    check($link->textContent === $number, 'Displayed number changed');
    check($link->parentNode->getAttribute('style') === 'color: #c02030', 'Surrounding color changed');
    check(WFEBPG_Phone_Linker::html($result) === $result, 'Second pass modified existing link');
}
foreach ([
    '<a href="tel:+15015422531" style="color: blue">501-542-2531</a>',
    '<a href="https://example.com">501-542-2531</a>',
    '<span data-phone="5015422531">No phone here</span>',
    '<script>var number="5015422531";</script>',
    '<style>.phone-5015422531 {color:red}</style>',
] as $source) check(WFEBPG_Phone_Linker::html($source) === $source, 'Existing markup changed');

echo "Phone links passed: inherited color, tel destinations, displayed numbers, idempotence, and unchanged existing markup.\n";
