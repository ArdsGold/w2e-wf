<?php
// Run: php tests/title-link-refresh.php
$hooks = [];
function add_action($hook, $callback) { $GLOBALS['hooks'][$hook][] = $callback; }
require __DIR__ . '/title-linker.php';
$posts = $meta = $permalinks = [];
page(10, 'Target'); page(11, 'Parent'); page(12, 'Child'); page(20, 'Source');
$elements = [widget('heading', ['title'=>'Target']), widget('icon-list', ['icon_list'=>[
    ['_id'=>'child', 'text'=>'Child'], ['_id'=>'manual', 'text'=>'Target', 'link'=>['url'=>'https://manual.test/']],
]])];
WFEBPG_Title_Linker::apply($elements, 20);
update_post_meta(20, '_wfebpg_generated', '1');
update_post_meta(20, '_elementor_data', WFEBPG_Template::encode($elements));
check(count(get_post_meta(20, WFEBPG_Title_Linker::TARGETS_META, true)) === 2, 'Targets were not recorded');
$before = (object)['post_name'=>'parent', 'post_parent'=>0, 'post_status'=>'publish'];
$after = (object)['post_type'=>'page', 'post_name'=>'new-parent', 'post_parent'=>0, 'post_status'=>'publish'];
WFEBPG_Title_Linker::page_saved(11, $after, true, $before);
WFEBPG_Title_Linker::page_saved(11, $after, true, $before);
check(count($hooks['shutdown']) === 1, 'Bulk saves were not coalesced');
$permalinks[10] = 'https://example.test/new-parent/target/';
$permalinks[12] = 'https://example.test/new-parent/child/';
$posts[10]->post_title = 'Renamed target';
$hooks['shutdown'][0]();
$elements = json_decode(get_post_meta(20, '_elementor_data', true), true);
check($elements[0]['settings']['link']['url'] === get_permalink(10), 'Moved/renamed target lost its identity');
check($elements[1]['settings']['icon_list'][0]['link']['url'] === get_permalink(12), 'Descendant URL did not refresh');
check($elements[1]['settings']['icon_list'][1]['link']['url'] === 'https://manual.test/', 'Manual link changed');
check(WFEBPG_Title_Linker::refresh_generated() === 0, 'Refresh was not idempotent');

// Manual overrides release tracking, and disappear from subsequent automatic updates.
$elements[0]['settings']['link']['url'] = 'https://override.test/';
update_post_meta(20, '_elementor_data', WFEBPG_Template::encode($elements));
WFEBPG_Title_Linker::refresh_generated(true);
$permalinks[10] = 'https://example.test/another/';
WFEBPG_Title_Linker::refresh_generated();
$elements = json_decode(get_post_meta(20, '_elementor_data', true), true);
check($elements[0]['settings']['link']['url'] === 'https://override.test/', 'Manual override was overwritten');

// Unpublished targets are blanked, retain their ID, and reconnect after republishing.
$posts[12]->post_status = 'draft';
WFEBPG_Title_Linker::refresh_generated();
$elements = json_decode(get_post_meta(20, '_elementor_data', true), true);
check($elements[1]['settings']['icon_list'][0]['link']['url'] === '', 'Private target URL survived');
$posts[12]->post_status = 'publish';
WFEBPG_Title_Linker::refresh_generated();
$elements = json_decode(get_post_meta(20, '_elementor_data', true), true);
check($elements[1]['settings']['icon_list'][0]['link']['url'] === get_permalink(12), 'Republished target did not reconnect');

// Only the explicit refresh action adopts verified old URL/title pairs.
page(21, 'Legacy source');
$legacy = [widget('heading', ['title'=>'Child', 'link'=>['url'=>get_permalink(12)]]),
    widget('heading', ['title'=>'Child', 'link'=>['url'=>'https://custom.test/']])];
update_post_meta(21, '_wfebpg_generated', '1');
update_post_meta(21, '_elementor_data', WFEBPG_Template::encode($legacy));
WFEBPG_Title_Linker::refresh_generated();
check(!get_post_meta(21, WFEBPG_Title_Linker::TARGETS_META, true), 'Automatic refresh adopted an old link');
WFEBPG_Title_Linker::refresh_generated(true);
check(count(get_post_meta(21, WFEBPG_Title_Linker::TARGETS_META, true)) === 1, 'Legacy verification adopted an unsafe URL');
$permalinks[12] = 'https://example.test/final-child/';
WFEBPG_Title_Linker::refresh_generated();
$legacy = json_decode(get_post_meta(21, '_elementor_data', true), true);
check($legacy[0]['settings']['link']['url'] === get_permalink(12), 'Adopted legacy target did not follow its page');
check($legacy[1]['settings']['link']['url'] === 'https://custom.test/', 'Unverified legacy URL changed');

// Manually created pages participate without acquiring the plugin-generated flag.
page(30, 'Manual Elementor source'); page(31, 'Unmarked manual page'); page(32, 'Plain WordPress page');
$manual = [widget('heading', ['title'=>'Child']),
    widget('heading', ['title'=>'Child', 'link'=>['url'=>'https://custom.test/manual/']]),
    widget('heading', ['title'=>'Child', 'link'=>['url'=>get_permalink(12)]])];
$unmarked = [widget('heading', ['title'=>'Child'], 'h3')];
$unmarked_json = json_encode($unmarked, JSON_PRETTY_PRINT);
update_post_meta(30, '_elementor_data', WFEBPG_Template::encode($manual));
update_post_meta(31, '_elementor_data', $unmarked_json);
check(WFEBPG_Title_Linker::refresh_generated(true) === 1, 'Manual Elementor page was not refreshed');
$manual = json_decode(get_post_meta(30, '_elementor_data', true), true);
check($manual[0]['settings']['link']['url'] === get_permalink(12), 'Manual page blank marked link was not filled');
check($manual[1]['settings']['link']['url'] === 'https://custom.test/manual/', 'Manual page custom URL changed');
check(count(get_post_meta(30, WFEBPG_Title_Linker::TARGETS_META, true)) === 2, 'Manual page links were not tracked/adopted');
check(get_post_meta(30, '_wfebpg_generated', true) === '', 'Manual page was relabeled as generated');
check(get_post_meta(31, '_elementor_data', true) === $unmarked_json, 'Unmarked page JSON changed');
check(get_post_meta(32, '_elementor_data', true) === '', 'Non-Elementor page was changed');
$permalinks[12] = 'https://example.test/moved-again/child/';
WFEBPG_Title_Linker::refresh_generated();
$manual = json_decode(get_post_meta(30, '_elementor_data', true), true);
check($manual[0]['settings']['link']['url'] === get_permalink(12)
    && $manual[2]['settings']['link']['url'] === get_permalink(12), 'Manual page links did not follow target moves');
WFEBPG_Title_Linker::page_deleted(12, $posts[12]);
unset($posts[12]);
$hooks['shutdown'][0]();
$legacy = json_decode(get_post_meta(21, '_elementor_data', true), true);
check($legacy[0]['settings']['link']['url'] === '', 'Permanently deleted target retained its URL');
check(count($hooks['shutdown']) === 1, 'Deletion scheduled a duplicate refresh');
echo "Link refresh passed: stable IDs, parent/descendant moves, manual and generated pages, unchanged unmarked pages, overrides, unpublished targets, legacy adoption, cache invalidation, and coalesced save hooks.\n";
