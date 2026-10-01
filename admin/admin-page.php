<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function(){
    add_menu_page('Wolf Forge Page Generator', 'Wolf Forge Page Generator','manage_options','wfebpg','wfebpg_admin','dashicons-layout',58);
    add_submenu_page('wfebpg','Template Validator','Template Validator','manage_options','wfebpg-validator','wfebpg_template_validator');
});
add_action('admin_enqueue_scripts', function($hook){
    if (strpos($hook, 'wfebpg') === false) return;
    wp_enqueue_media();
    wp_enqueue_script('jquery');
    wp_enqueue_script('wfebpg-admin', WFEBPG_URL.'assets/admin.js', ['jquery'], WFEBPG_VERSION, true);

    // This callback has its own scope, so load the current user's saved image pool here.
    $saved_image_ids = array_values(array_unique(array_filter(array_map(
        'absint',
        (array) get_user_meta(get_current_user_id(), 'wfebpg_image_pool_ids', true)
    ))));

    wp_localize_script('wfebpg-admin', 'WFEBPG_Admin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'jsonNonce' => wp_create_nonce('wfebpg_save_template'),
        'docxNonce' => wp_create_nonce('wfebpg_upload_docx'),
        'imagePoolNonce' => wp_create_nonce('wfebpg_save_image_pool'),
        'templateImageNonce' => wp_create_nonce('wfebpg_template_images'),
        'savedImageIds' => $saved_image_ids,
    ]);
    wp_enqueue_style('wfebpg-admin', WFEBPG_URL.'assets/admin.css', [], WFEBPG_VERSION);
});
add_action('admin_post_wfebpg_generate','wfebpg_handle_generate');
add_action('admin_post_wfebpg_process_queue_now','wfebpg_process_queue_now');
add_action('admin_post_wfebpg_clear_logs','wfebpg_clear_logs');
add_action('admin_post_wfebpg_reset_generated','wfebpg_reset_generated');
add_action('admin_post_wfebpg_rollback','wfebpg_rollback');
add_action('admin_post_wfebpg_trash_generated','wfebpg_trash_generated');
add_action('admin_post_wfebpg_clear_templates','wfebpg_clear_templates');
add_action('wp_ajax_wfebpg_save_template','wfebpg_ajax_save_template');
add_action('wp_ajax_wfebpg_set_last_template','wfebpg_ajax_set_last_template');
add_action('wp_ajax_wfebpg_upload_docx','wfebpg_ajax_upload_docx');
add_action('wp_ajax_wfebpg_cancel_docx_batch','wfebpg_ajax_cancel_docx_batch');
add_action('wp_ajax_wfebpg_save_image_pool','wfebpg_ajax_save_image_pool');
add_action('wp_ajax_wfebpg_get_template_images','wfebpg_ajax_get_template_images');
add_action('wp_ajax_wfebpg_save_template_image_map','wfebpg_ajax_save_template_image_map');

function wfebpg_admin(){
    if(!current_user_can('manage_options'))return;
    $pages=get_pages(['post_status'=>['publish','draft','private']]);
    $logs=WFEBPG_Logger::get();
    $q=get_option('wfebpg_queue',[]);
    $created_pages=get_option('wfebpg_created_pages',[]);
    $saved_templates=wfebpg_get_saved_templates();
    $last_template=sanitize_file_name((string)get_user_meta(get_current_user_id(),'wfebpg_last_template',true));
    if($last_template && !wfebpg_find_saved_template($last_template))$last_template='';
    $saved_image_ids=array_values(array_unique(array_filter(array_map('absint',(array)get_user_meta(get_current_user_id(),'wfebpg_image_pool_ids',true)))));
    $image_pool_enabled=(bool)get_user_meta(get_current_user_id(),'wfebpg_image_pool_enabled',true);
    ?>
<div class="wrap wfebpg-admin"><h1>Wolf Forge Elementor Page Generator</h1>
<?php if(isset($_GET['wfebpg_reset'])): ?><div class="notice notice-success is-dismissible"><p>Generated-page To-Do table cleared. No WordPress pages were deleted and logs were left unchanged.</p></div><?php endif; ?>
<p>Upload DOCX content and an Elementor JSON template. Jobs are queued and processed in the background to reduce timeouts.</p>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" enctype="multipart/form-data">
<input type="hidden" name="action" value="wfebpg_generate"><?php wp_nonce_field('wfebpg_generate'); ?>
<table class="form-table">
<tr><th>Page Generation</th><td>
    <input type="hidden" name="mode" value="auto">
    <strong>Automatic</strong> — one Elementor template is used for every DOCX. The plugin reads the document structure and follows the markers in the Elementor template.
    <p class="description">No Generic/Unique selection is required. For repeatable cards, mark the parent Section/Container with <code>data-customID|h3|repeat</code> (or h1/h2). The first Heading and first Text Editor inside it are populated automatically.</p>
</td></tr>
<tr><th>DOCX Files</th><td><input type="file" name="docx[]" id="wfebpg-docx-upload" accept=".docx" multiple required><input type="hidden" name="docx_batch" id="wfebpg-docx-batch" value=""><p class="description">Upload any mix of DOCX files. Each file is interpreted independently using the same Elementor template.</p></td></tr>
<tr><th>Elementor JSON Template</th><td>
    <label for="wfebpg-saved-template"><strong>Use a previously uploaded template</strong></label><br>
    <select name="saved_template" id="wfebpg-saved-template" style="min-width:420px;max-width:100%;margin-top:6px">
        <option value="">— Upload a new template instead —</option>
        <?php foreach ($saved_templates as $template): ?>
            <option value="<?php echo esc_attr($template['name']); ?>" <?php selected($last_template,$template['name']); ?>><?php echo esc_html($template['name']); ?><?php if (!empty($template['modified'])): ?> — <?php echo esc_html(wp_date(get_option('date_format'), $template['modified'])); ?><?php endif; ?></option>
        <?php endforeach; ?>
    </select>
    <p class="description"><?php echo empty($saved_templates) ? 'No saved Elementor template yet. Upload one below.' : count($saved_templates).' saved Elementor template'.(count($saved_templates)===1?'':'s').'.'; ?><?php if($last_template): ?> Last used: <strong><?php echo esc_html($last_template); ?></strong><?php endif; ?></p>
    <p style="margin:14px 0 6px"><strong>Upload new JSON template</strong></p>
    <input type="file" name="template" id="wfebpg-template-upload" accept=".json">
    <input type="hidden" name="saved_template_choice" id="wfebpg-saved-template-choice" value="">
    <p class="description">The same template can generate pages with or without repeatable content. Only elements with <code>data-customID</code> are populated; unmarked elements are left untouched.</p>
    <div class="wfebpg-template-image-map-panel">
        <p style="margin:0 0 8px"><strong>Template Image Replacement</strong></p>
        <p class="description">Map images already inside the JSON template to images in your Media Library. The plugin suggests matches using filename similarity; you choose whether to apply each suggestion. This is separate from the Dynamic Image Pool.</p>
        <select id="wfebpg-image-map-template" style="min-width:420px;max-width:100%">
            <option value="">— Select a saved template —</option>
            <?php foreach ($saved_templates as $template): ?>
                <option value="<?php echo esc_attr($template['name']); ?>"><?php echo esc_html($template['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="button" id="wfebpg-load-template-images">Scan Template Images</button>
        <div id="wfebpg-template-image-map-status" class="description" style="margin-top:8px"></div>
        <div id="wfebpg-template-image-map-list"></div>
    </div>
    <?php $clear_templates_url=wp_nonce_url(admin_url('admin-post.php?action=wfebpg_clear_templates'),'wfebpg_clear_templates'); ?>
    <?php if (!empty($saved_templates)): ?>
        <a class="button button-link-delete" href="<?php echo esc_url($clear_templates_url); ?>" onclick="return confirm('Clear all remembered Elementor JSON templates? This does not affect templates already copied into queued jobs.');">Clear Saved JSON Templates</a>
    <?php endif; ?>
</td></tr>
<tr><th>Repeatable Section</th><td><label>Widgets per section <input type="number" name="widgets_per_section" value="4" min="1" max="100" style="width:80px"></label><p class="description">Maximum number of legacy repeatable card groups per Elementor section. Parent-container repeatables are cloned as their own complete content unit; the first Heading and Text Editor inside each parent are populated automatically.</p></td></tr>
<tr><th>Image Pool</th><td>
    <label class="wfebpg-toggle"><input type="checkbox" name="image_pool_enabled" id="wfebpg-image-pool-enabled" value="1" <?php checked($image_pool_enabled); ?>> <strong>Enable Media Library Image Pool</strong></label>
    <p class="description">Randomly assign selected Media Library images to repeatable card sections. Images are not duplicated within the same generated section.</p>
    <div id="wfebpg-image-pool-panel" class="wfebpg-image-pool-panel" style="display:none">
        <input type="hidden" name="image_pool_ids" id="wfebpg-image-pool-ids" value="<?php echo esc_attr(implode(",",$saved_image_ids)); ?>">
        <button type="button" class="button" id="wfebpg-select-images">Select Images from Media Library</button>
        <button type="button" class="button-link-delete" id="wfebpg-clear-images" style="margin-left:10px">Clear Selection</button>
        <div id="wfebpg-image-selection-count" class="description" style="margin-top:8px">No images selected.</div>
        <div id="wfebpg-image-preview" class="wfebpg-image-preview"></div>
        <p class="description">The same image can be used again in another section or another page. Only duplicates inside the same repeatable section are prevented.</p>
    </div>
</td></tr>
<tr><th>Parent Page</th><td><select name="parent"><option value="0">— No Parent —</option><?php foreach($pages as $p):?><option value="<?php echo esc_attr($p->ID);?>"><?php echo esc_html($p->post_title);?></option><?php endforeach;?></select></td></tr>
<tr><th>Existing Slugs</th><td><label><input type="checkbox" name="overwrite" value="1"> Overwrite matching existing pages</label><p class="description">Otherwise a short suffix is added to avoid overwriting.</p></td></tr>
</table><p><button type="submit" class="button button-primary button-hero">Queue Pages</button> <button type="button" class="button" id="wfebpg-stop-queueing" style="display:none;border-color:#b32d2e;color:#b32d2e">Stop Queueing</button> <span id="wfebpg-queue-status" class="description"></span></p></form>
<hr><h2>Queue</h2><p><strong><?php echo count($q);?></strong> job(s) waiting.</p>
<?php $next_cron=wp_next_scheduled('wfebpg_process_queue'); if($next_cron): ?>
<p class="description">Next WP-Cron run: <?php echo esc_html(wp_date(get_option('date_format').' '.get_option('time_format'),$next_cron)); ?></p>
<?php else: ?>
<p class="description">No WP-Cron worker is currently scheduled.</p>
<?php endif; ?>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" style="margin:10px 0 20px"><input type="hidden" name="action" value="wfebpg_process_queue_now"><?php wp_nonce_field('wfebpg_process_queue_now');?><button class="button button-primary" <?php disabled(empty($q)); ?>>Process Queue Now</button> <span class="description">Processes one queued job immediately.</span></form>
<h2>Newly Created Pages — To-Do</h2><p>Use these links to open a generated page in WordPress or Elementor for final review and editing.</p><table class="widefat striped" style="margin-top:10px"><thead><tr><th>Created</th><th>Page</th><th>Actions</th></tr></thead><tbody><?php if(empty($created_pages)):?><tr><td colspan="3">No generated pages yet.</td></tr><?php else: foreach(array_slice($created_pages,0,100) as $cp): $cp_id=absint($cp['id']??0); if(!$cp_id)continue; ?><tr><td><?php echo esc_html($cp['time']??'');?></td><td><?php echo esc_html($cp['title']??get_the_title($cp_id));?></td><td><a class="button button-small" href="<?php echo esc_url(get_edit_post_link($cp_id));?>">Edit Page</a> <a class="button button-small" href="<?php echo esc_url(admin_url('post.php?post='.$cp_id.'&action=elementor'));?>">Edit with Elementor</a> <a href="<?php echo esc_url(get_permalink($cp_id));?>" target="_blank" rel="noopener">View</a> <?php if(get_post_type($cp_id)==='page' && current_user_can('delete_post',$cp_id)): $trash_url=wp_nonce_url(admin_url('admin-post.php?action=wfebpg_trash_generated&id='.$cp_id),'wfebpg_trash_generated_'.$cp_id); ?><a class="button button-small" style="border-color:#b32d2e;color:#b32d2e" href="<?php echo esc_url($trash_url);?>" onclick="return confirm('Move this generated page to the WordPress Trash?');">Move to Trash</a><?php endif; ?></td></tr><?php endforeach; endif;?></tbody></table>
<h2>Generated Pages Table</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" style="margin:10px 0 20px"><input type="hidden" name="action" value="wfebpg_reset_generated"><?php wp_nonce_field('wfebpg_reset_generated');?><button class="button button-secondary" style="border-color:#b32d2e;color:#b32d2e" onclick="return confirm('Clear the generated-page To-Do table? No WordPress pages or logs will be deleted. Continue?');">Clear Generated Pages Table</button> <span class="description">Clears only this plugin's generated-page To-Do table. It does not delete WordPress pages or clear logs.</span></form>
<h2>Logs</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="wfebpg_clear_logs"><?php wp_nonce_field('wfebpg_clear_logs');?><button class="button">Clear Logs Only</button></form><table class="widefat striped" style="margin-top:10px"><thead><tr><th>Time</th><th>Level</th><th>Message</th></tr></thead><tbody><?php if(empty($logs)):?><tr><td colspan="3">No logs.</td></tr><?php else: foreach(array_slice($logs,0,100) as $l):?><tr><td><?php echo esc_html($l['time']);?></td><td><?php echo esc_html($l['level']);?></td><td><?php echo esc_html($l['message']);?></td></tr><?php endforeach; endif;?></tbody></table>
</div><?php
}

function wfebpg_template_validator(){
    if(!current_user_can('manage_options')) return;
    $result = null;
    $error = '';
    if (!empty($_POST['wfebpg_validate_template'])) {
        check_admin_referer('wfebpg_validate_template');
        try {
            if (empty($_FILES['validator_template']['tmp_name'])) throw new Exception('Please select an Elementor JSON template.');
            $json = file_get_contents($_FILES['validator_template']['tmp_name']);
            if ($json === false) throw new Exception('Unable to read the template file.');
            $doc = null;
            if (!empty($_FILES['validator_docx']['tmp_name'])) $doc = WFEBPG_DOCX_Reader::read($_FILES['validator_docx']['tmp_name']);
            $result = WFEBPG_Generator::validate_template_json($json, $doc);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
    ?>
    <div class="wrap wfebpg-admin wfebpg-validator">
        <h1>Wolf Forge Template Validator</h1>
        <p>Validate an Elementor JSON template before generating pages. Optionally upload the DOCX that will be used with it to check structural compatibility.</p>
        <?php if ($error): ?><div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="wfebpg-validator-form">
            <?php wp_nonce_field('wfebpg_validate_template'); ?>
            <input type="hidden" name="wfebpg_validate_template" value="1">
            <table class="form-table">
                <tr><th>Elementor JSON Template</th><td><input type="file" name="validator_template" accept=".json" required></td></tr>
                <tr><th>DOCX Content (optional)</th><td><input type="file" name="validator_docx" accept=".docx"><p class="description">Upload the DOCX to validate H1/H2/H3 slots and repeatable marker compatibility as well.</p></td></tr>
            </table>
            <p><button class="button button-primary button-hero">Validate Template</button></p>
        </form>
        <?php if ($result): ?>
            <div class="wfebpg-validator-grid">
                <div class="wfebpg-validator-card">
                    <h2>Content Markers</h2><p class="description">Counts are based on normalized <code>data-customID|type|flags</code> markers.</p>
                    <?php foreach ($result['counts'] as $key=>$count): ?><div class="wfebpg-check-row"><span><?php echo esc_html($key); ?></span><strong><?php echo absint($count); ?></strong></div><?php endforeach; ?>
                </div>
                <div class="wfebpg-validator-card">
                    <h2>Repeatable Structure</h2>
                    <div class="wfebpg-check-row"><span>Repeatable sections</span><strong><?php echo absint($result['repeat_sections']); ?></strong></div>
                    <div class="wfebpg-check-row"><span>Repeatable card columns with images</span><strong><?php echo absint($result['repeat_columns']); ?></strong></div>
                    <div class="wfebpg-check-row"><span>Image/background locations</span><strong><?php echo absint($result['image_locations']); ?></strong></div>
                    <div class="wfebpg-check-row"><span>Images inside repeatable sections</span><strong><?php echo absint($result['repeat_image_locations']); ?></strong></div>
                </div>
                <?php if ($result['doc']): ?><div class="wfebpg-validator-card"><h2>DOCX Compatibility</h2><p class="description">The document is analyzed using normal Heading 1/2/3 styles and plain paragraphs.</p><?php foreach ($result['doc'] as $key=>$value): ?><div class="wfebpg-check-row"><span><?php echo esc_html(strtoupper($key)); ?></span><strong><?php echo absint($value); ?></strong></div><?php endforeach; ?></div><?php endif; ?>
            </div>
            <?php if (!empty($result['mapping_debug'])): ?>
                <div class="wfebpg-validator-card wfebpg-mapping-debug" style="max-width:1200px;margin-top:20px">
                    <h2>Mapping Debugger</h2>
                    <p class="description">This trace shows the exact hierarchical scope used for every marked Elementor heading/paragraph. A child heading is scoped to the preceding marked heading one level above it: H2→H1, H3→H2, H4→H3, and so on. Unmatched slots are left untouched by the generator.</p>
                    <div style="overflow:auto">
                        <table class="widefat striped">
                            <thead><tr><th>Type</th><th>Element ID</th><th>Widget</th><th>Marker</th><th>Parent Scope</th><th>DOCX Source</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php foreach ($result['mapping_debug'] as $row): ?>
                                <tr>
                                    <td><?php echo esc_html($row['level'] ?? $row['kind'] ?? ''); ?></td>
                                    <td><code><?php echo esc_html($row['element_id'] ?? ''); ?></code></td>
                                    <td><?php echo esc_html($row['widget'] ?? ''); ?></td>
                                    <td><code><?php echo esc_html($row['marker'] ?? ''); ?></code></td>
                                    <td><?php echo esc_html(($row['parent'] ?? '') . ' / ' . ($row['scope'] ?? '')); ?></td>
                                    <td><?php echo esc_html($row['source'] ?? ''); ?></td>
                                    <td><strong><?php echo esc_html($row['status'] ?? ''); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($result['errors'])): ?><div class="notice notice-error inline"><p><strong>Errors</strong></p><ul><?php foreach($result['errors'] as $msg): ?><li><?php echo esc_html($msg); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <?php if (!empty($result['warnings'])): ?><div class="notice notice-warning inline"><p><strong>Warnings</strong></p><ul><?php foreach($result['warnings'] as $msg): ?><li><?php echo esc_html($msg); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
            <?php if (empty($result['errors'])): ?><div class="notice notice-success inline"><p><strong>READY TO GENERATE</strong> — no blocking template errors were detected.</p></div><?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

function wfebpg_template_library_dir(){
    $upload=wp_upload_dir();
    $dir=trailingslashit($upload['basedir']).'wfebpg-templates';
    if(!is_dir($dir)) wp_mkdir_p($dir);
    return $dir;
}

function wfebpg_classify_template_file($file){
    // Retained for saved-template metadata compatibility. The generator now
    // uses one automatic marker-driven mode for every template.
    return 'automatic';
}

function wfebpg_classify_docx($file){
    // Retained for the upload response API; generation is always automatic.
    WFEBPG_DOCX_Reader::read($file);
    return 'automatic';
}

function wfebpg_get_saved_templates(){
    $dir=wfebpg_template_library_dir();
    $files=glob(trailingslashit($dir).'*.json') ?: [];
    $templates=[];
    foreach($files as $file){
        if(!is_file($file)) continue;
        $name=basename($file);
        $kind=wfebpg_classify_template_file($file);
        $templates[]=['name'=>$name,'path'=>$file,'modified'=>filemtime($file) ?: 0,'kind'=>$kind];
    }
    usort($templates,function($a,$b){return strcasecmp($a['name'],$b['name']);});
    return $templates;
}

function wfebpg_find_saved_template($name){
    $name=sanitize_file_name(wp_unslash($name));
    if(!$name || strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='json') return false;
    foreach(wfebpg_get_saved_templates() as $template){
        if(hash_equals($template['name'],$name)) return $template['path'];
    }
    return false;
}

function wfebpg_save_template_upload($file){
    if (empty($file) || !isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_file($file['tmp_name'])) {
        throw new Exception('Please select an Elementor JSON template.');
    }
    $name=sanitize_file_name(basename($file['name'] ?? 'template.json'));
    if(!$name || strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='json') throw new Exception('The template must be a .json file.');
    $json=file_get_contents($file['tmp_name']);
    if($json===false) throw new Exception('Unable to read the template file.');
    WFEBPG_Template::decode($json);
    $dest=trailingslashit(wfebpg_template_library_dir()).$name;
    if(!move_uploaded_file($file['tmp_name'],$dest)) {
        if(!copy($file['tmp_name'],$dest)) throw new Exception('Unable to save the template.');
        @unlink($file['tmp_name']);
    }
    return $dest;
}

function wfebpg_docx_batch_dir($batch){
    if(!preg_match('/^[a-f0-9-]{36}$/i',(string)$batch)) return false;
    $upload=wp_upload_dir();
    return trailingslashit($upload['basedir']).'wfebpg-upload-batches/'.strtolower($batch);
}

function wfebpg_ajax_cancel_docx_batch(){
    if(!current_user_can('manage_options') || !check_ajax_referer('wfebpg_upload_docx','nonce',false)) {
        wp_send_json_error(['message'=>'Unauthorized.'],403);
    }
    $batch=sanitize_text_field(wp_unslash($_POST['batch']??''));
    $dir=wfebpg_docx_batch_dir($batch);
    if(!$dir || !is_dir($dir)) wp_send_json_success(['cancelled'=>false]);
    foreach(glob(trailingslashit($dir).'*') ?: [] as $file){
        if(is_file($file)) @unlink($file);
    }
    @rmdir($dir);
    wp_send_json_success(['cancelled'=>true]);
}

function wfebpg_ajax_upload_docx(){
    if(!current_user_can('manage_options') || !check_ajax_referer('wfebpg_upload_docx','nonce',false)) {
        wp_send_json_error(['message'=>'Unauthorized.'],403);
    }
    try {
        if(empty($_FILES['docx'])) throw new Exception('No DOCX file was received.');
        $file=$_FILES['docx'];
        if(!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name']) || !is_file($file['tmp_name'])) throw new Exception('Unable to receive the DOCX file.');
        $original_name=basename((string)($file['name'] ?? 'document.docx'));
        $original_name=preg_replace('/[\x00-\x1F\x7F]/u','',$original_name);
        if(!$original_name || strtolower(pathinfo($original_name,PATHINFO_EXTENSION))!=='docx') throw new Exception('Only .docx files are allowed.');
        $name=sanitize_file_name($original_name);
        if(!$name) throw new Exception('Invalid DOCX filename.');
        $batch=sanitize_text_field(wp_unslash($_POST['batch']??''));
        if(!preg_match('/^[a-f0-9-]{36}$/i',$batch)) $batch=wp_generate_uuid4();
        $dir=wfebpg_docx_batch_dir($batch);
        if(!$dir) throw new Exception('Invalid upload batch.');
        if(!wp_mkdir_p($dir)) throw new Exception('Unable to create the temporary DOCX upload folder.');
        $dest=trailingslashit($dir).$name;
        if(file_exists($dest)) {
            $dest=trailingslashit($dir).pathinfo($name,PATHINFO_FILENAME).'-'.wp_generate_password(6,false,false).'.docx';
        }
        if(!move_uploaded_file($file['tmp_name'],$dest)) {
            if(!copy($file['tmp_name'],$dest)) throw new Exception('Unable to save the DOCX file.');
            @unlink($file['tmp_name']);
        }
        $kind=wfebpg_classify_docx($dest);
        $map_file=trailingslashit($dir).'.original-names.json';
        $map=[];
        if(is_file($map_file)){ $raw=file_get_contents($map_file); $decoded=json_decode((string)$raw,true); if(is_array($decoded))$map=$decoded; }
        $map[basename($dest)]=$original_name;
        file_put_contents($map_file,wp_json_encode($map,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
        wp_send_json_success(['batch'=>$batch,'name'=>basename($dest),'kind'=>$kind]);
    } catch(Throwable $e) {
        wp_send_json_error(['message'=>$e->getMessage()],400);
    }
}

function wfebpg_ajax_set_last_template(){
    if(!current_user_can('manage_options') || !check_ajax_referer('wfebpg_save_template','nonce',false)){
        wp_send_json_error(['message'=>'Unauthorized.'],403);
    }
    $name=sanitize_file_name(wp_unslash($_POST['template']??''));
    if($name && !wfebpg_find_saved_template($name)) wp_send_json_error(['message'=>'Template not found.'],404);
    update_user_meta(get_current_user_id(),'wfebpg_last_template',$name);
    wp_send_json_success(['name'=>$name]);
}

function wfebpg_ajax_save_template(){
    if(!current_user_can('manage_options') || !check_ajax_referer('wfebpg_save_template','nonce',false)) {
        wp_send_json_error(['message'=>'Unauthorized.'],403);
    }
    try {
        if(empty($_FILES['template'])) throw new Exception('Please select an Elementor JSON template.');
        $path=wfebpg_save_template_upload($_FILES['template']);
        update_user_meta(get_current_user_id(),'wfebpg_last_template',sanitize_file_name(basename($path)));
        wp_send_json_success(['name'=>basename($path)]);
    } catch(Throwable $e) {
        wp_send_json_error(['message'=>$e->getMessage()],400);
    }
}

function wfebpg_template_image_signature($id,$url){ return md5((string)$id.'|'.(string)$url); }
function wfebpg_template_image_maps(){ $maps=get_user_meta(get_current_user_id(),'wfebpg_template_image_maps',true); return is_array($maps)?$maps:[]; }
function wfebpg_template_image_filename($url){ $path=parse_url((string)$url,PHP_URL_PATH); $base=$path?basename($path):basename((string)$url); return preg_replace('/[-_]+/',' ',pathinfo($base,PATHINFO_FILENAME)); }
function wfebpg_template_image_matches($json){
    $data=json_decode((string)$json,true); if(!is_array($data)) return []; $found=[];
    $walk=function($node) use (&$walk,&$found){
        if(!is_array($node)) return;
        if(isset($node['settings']) && is_array($node['settings'])){
            foreach(['background_image','background_image_mobile','image'] as $key){
                if(!empty($node['settings'][$key]) && is_array($node['settings'][$key])){ $img=$node['settings'][$key]; $url=(string)($img['url']??''); $id=absint($img['id']??0); if($url || $id){ $sig=wfebpg_template_image_signature($id,$url); if(!isset($found[$sig])) $found[$sig]=['key'=>$sig,'id'=>$id,'url'=>$url,'filename'=>wfebpg_template_image_filename($url),'field'=>$key]; } }
            }
        }
        foreach($node as $v) if(is_array($v)) $walk($v);
    };
    $walk($data); return array_values($found);
}
function wfebpg_image_similarity($a,$b){
    $norm=function($v){ $v=strtolower((string)$v); $v=preg_replace('/\.[a-z0-9]{2,5}$/i','',$v); $v=preg_replace('/[^a-z0-9]+/',' ',remove_accents($v)); $parts=array_values(array_filter(preg_split('/\s+/',trim($v)))); return array_values(array_unique($parts)); };
    $a=$norm($a); $b=$norm($b); if(!$a||!$b)return 0; $sa=array_flip($a); $sb=array_flip($b); $common=count(array_intersect_key($sa,$sb)); $score=($common/max(count($sa),count($sb)))*100;
    $as=implode(' ',$a); $bs=implode(' ',$b); if($as===$bs)$score=100; elseif(strpos($bs,$as)!==false || strpos($as,$bs)!==false)$score=max($score,78); return round($score,1);
}
function wfebpg_attachment_image_data($id){ $id=absint($id); if(!$id||get_post_type($id)!=='attachment'||strpos((string)get_post_mime_type($id),'image/')!==0)return false; return ['id'=>$id,'title'=>get_the_title($id),'filename'=>basename((string)get_attached_file($id)),'url'=>wp_get_attachment_image_url($id,'medium')?:wp_get_attachment_url($id)]; }
function wfebpg_ajax_get_template_images(){
    if(!current_user_can('manage_options')||!check_ajax_referer('wfebpg_template_images','nonce',false))wp_send_json_error(['message'=>'Unauthorized.'],403);
    $name=sanitize_file_name(wp_unslash($_POST['template']??'')); $path=wfebpg_find_saved_template($name); if(!$path)wp_send_json_error(['message'=>'Template not found.'],404);
    $json=file_get_contents($path); $images=wfebpg_template_image_matches($json); $maps=wfebpg_template_image_maps(); $map=$maps[$name]??[];
    $ids=get_posts(['post_type'=>'attachment','post_mime_type'=>'image','post_status'=>'inherit','posts_per_page'=>5000,'fields'=>'ids','no_found_rows'=>true,'orderby'=>'ID','order'=>'DESC']);
    foreach($images as &$img){ $img['suggestions']=[]; $mapped=absint($map[$img['key']]??0); if($mapped){ $img['mapped']=wfebpg_attachment_image_data($mapped); } else $img['mapped']=false;
        $scores=[]; foreach($ids as $aid){ $file=basename((string)get_attached_file($aid)); $title=get_the_title($aid); $score=max(wfebpg_image_similarity($img['filename'],$file),wfebpg_image_similarity($img['filename'],$title)); if($score>=35)$scores[]=['score'=>$score,'image'=>wfebpg_attachment_image_data($aid)]; }
        usort($scores,function($x,$y){return $y['score']<=>$x['score'];}); $img['suggestions']=array_slice($scores,0,3);
    } unset($img);
    wp_send_json_success(['images'=>$images]);
}
function wfebpg_ajax_save_template_image_map(){
    if(!current_user_can('manage_options')||!check_ajax_referer('wfebpg_template_images','nonce',false))wp_send_json_error(['message'=>'Unauthorized.'],403);
    $name=sanitize_file_name(wp_unslash($_POST['template']??'')); if(!$name||!wfebpg_find_saved_template($name))wp_send_json_error(['message'=>'Template not found.'],404);
    $raw=json_decode(wp_unslash($_POST['map']??'{}'),true); if(!is_array($raw))$raw=[]; $clean=[];
    foreach($raw as $key=>$id){ $id=absint($id); if(preg_match('/^[a-f0-9]{32}$/',$key)&&$id&&wfebpg_attachment_image_data($id))$clean[$key]=$id; }
    $maps=wfebpg_template_image_maps(); $maps[$name]=$clean; update_user_meta(get_current_user_id(),'wfebpg_template_image_maps',$maps); wp_send_json_success(['count'=>count($clean)]);
}
function wfebpg_apply_template_image_map($json,$template_name){
    $maps=wfebpg_template_image_maps(); $map=$maps[$template_name]??[]; if(!$map)return $json; $data=json_decode((string)$json,true); if(!is_array($data))return $json;
    $walk=function(&$node) use (&$walk,$map){ if(!is_array($node))return; if(isset($node['settings'])&&is_array($node['settings'])){ foreach(['background_image','background_image_mobile','image'] as $key){ if(!empty($node['settings'][$key])&&is_array($node['settings'][$key])){ $img=$node['settings'][$key]; $sig=wfebpg_template_image_signature(absint($img['id']??0),(string)($img['url']??'')); if(isset($map[$sig])){ $id=absint($map[$sig]); $url=wp_get_attachment_image_url($id,'full')?:wp_get_attachment_url($id); if($url){ $node['settings'][$key]['id']=$id; $node['settings'][$key]['url']=$url; } } } } } foreach($node as &$v)if(is_array($v))$walk($v); };
    $walk($data); return wp_json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}

function wfebpg_ajax_save_image_pool(){
    if(!current_user_can('manage_options') || !check_ajax_referer('wfebpg_save_image_pool','nonce',false)){
        wp_send_json_error(['message'=>'Unauthorized.'],403);
    }
    $raw=isset($_POST['image_pool_ids']) ? explode(',',sanitize_text_field(wp_unslash($_POST['image_pool_ids']))) : [];
    $ids=[];
    foreach($raw as $id){
        $id=absint($id);
        if($id && get_post_type($id)==='attachment' && strpos((string)get_post_mime_type($id),'image/')===0) $ids[]=$id;
    }
    $ids=array_values(array_unique($ids));
    update_user_meta(get_current_user_id(),'wfebpg_image_pool_ids',$ids);
    update_user_meta(get_current_user_id(),'wfebpg_image_pool_enabled',!empty($_POST['image_pool_enabled']) ? 1 : 0);
    wp_send_json_success(['ids'=>$ids,'enabled'=>!empty($_POST['image_pool_enabled'])]);
}

function wfebpg_handle_generate(){
    if(!current_user_can('manage_options')||!check_admin_referer('wfebpg_generate'))wp_die('Unauthorized.');
    $docx_batch=sanitize_text_field(wp_unslash($_POST['docx_batch']??''));
    $has_batch=(bool)preg_match('/^[a-f0-9-]{36}$/i',$docx_batch);
    if(!$has_batch && empty($_FILES['docx']['name'][0]))wp_die('DOCX files are required.');

    try{
        $saved_name=sanitize_file_name(wp_unslash($_POST['saved_template']??''));
        $template_path=false;
        if(!empty($_FILES['template']['tmp_name']) && isset($_FILES['template']['error']) && (int)$_FILES['template']['error']===UPLOAD_ERR_OK){
            $template_path=wfebpg_save_template_upload($_FILES['template']);
        }elseif($saved_name){
            $template_path=wfebpg_find_saved_template($saved_name);
            if(!$template_path)throw new Exception('The selected saved JSON template no longer exists.');
        }else{
            throw new Exception('Please select a saved JSON template or upload a new one.');
        }
    }catch(Throwable $e){wp_die(esc_html($e->getMessage()));}

    update_user_meta(get_current_user_id(),'wfebpg_last_template',sanitize_file_name(basename($template_path)));

    $upload=wp_upload_dir();$base=trailingslashit($upload['basedir']).'wfebpg/'.wp_generate_uuid4();wp_mkdir_p($base);
    $raw_template=file_get_contents($template_path);
    if($raw_template===false)wp_die('Unable to read the selected Elementor template for this job.');
    $mapped_template=wfebpg_apply_template_image_map($raw_template,basename($template_path));
    $template_copy=$base.'/template.json';
    if(file_put_contents($template_copy,$mapped_template,LOCK_EX)===false)wp_die('Unable to copy the selected Elementor template for this job.');

    $parent=absint($_POST['parent']??0);$overwrite=!empty($_POST['overwrite']);$widgets_per_section=max(1,min(100,absint($_POST['widgets_per_section']??4)));$count=0;
    $image_pool_enabled=!empty($_POST['image_pool_enabled']);
    update_user_meta(get_current_user_id(),'wfebpg_image_pool_enabled',$image_pool_enabled ? 1 : 0);
    $image_pool_ids=[];
    if($image_pool_enabled && !empty($_POST['image_pool_ids'])){
        $raw=explode(',',sanitize_text_field(wp_unslash($_POST['image_pool_ids'])));
        foreach($raw as $id){$id=absint($id);if($id && get_post_type($id)==='attachment' && strpos((string)get_post_mime_type($id),'image/')===0)$image_pool_ids[]=$id;}
        $image_pool_ids=array_values(array_unique($image_pool_ids));
        update_user_meta(get_current_user_id(),'wfebpg_image_pool_ids',$image_pool_ids);
    } elseif($image_pool_enabled) {
        // An enabled pool with no submitted IDs intentionally becomes empty.
        update_user_meta(get_current_user_id(),'wfebpg_image_pool_ids',[]);
    } else {
        // Disabling the pool should not destroy the saved pool configuration.
        $image_pool_ids=array_values(array_unique(array_filter(array_map('absint',(array)get_user_meta(get_current_user_id(),'wfebpg_image_pool_ids',true)))));
    }

    $sources=[];$original_names=[];
    if($has_batch){
        $batch_dir=wfebpg_docx_batch_dir($docx_batch);
        $sources=$batch_dir && is_dir($batch_dir) ? (glob(trailingslashit($batch_dir).'*.docx') ?: []) : [];
        if($batch_dir){
            $map_file=trailingslashit($batch_dir).'.original-names.json';
            if(is_file($map_file)){ $raw=file_get_contents($map_file); $decoded=json_decode((string)$raw,true); if(is_array($decoded))$original_names=$decoded; }
        }
    }else{
        foreach($_FILES['docx']['name'] as $i=>$name){
            if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='docx')continue;
            if(!empty($_FILES['docx']['tmp_name'][$i])){
                $sources[]=$_FILES['docx']['tmp_name'][$i];
                $original_names[$_FILES['docx']['tmp_name'][$i]]=basename((string)$name);
            }
        }
    }

    foreach($sources as $source){
        if(!is_file($source))continue;
        $name=basename($source);
        $original_name=(string)($original_names[$name] ?? $original_names[$source] ?? $name);
        $dest=$base.'/'.sanitize_file_name($name);
        if($has_batch){if(!copy($source,$dest))continue;}else{if(!move_uploaded_file($source,$dest))continue;}
        WFEBPG_Generator::enqueue(['docx'=>$dest,'page_title'=>$original_name,'template'=>$template_copy,'mode'=>'auto','parent'=>$parent,'overwrite'=>$overwrite,'widgets_per_section'=>$widgets_per_section,'image_pool_ids'=>$image_pool_ids]);
        $count++;
    }
    if($has_batch){foreach($sources as $source)@unlink($source);$batch_dir=wfebpg_docx_batch_dir($docx_batch);if($batch_dir){@unlink(trailingslashit($batch_dir).'.original-names.json');@rmdir($batch_dir);}}
    if($image_pool_ids)WFEBPG_Logger::log('Image Pool attached to queued job(s): '.count($image_pool_ids).' Media Library image(s).','info');
    WFEBPG_Logger::log('Queued '.$count.' DOCX page job(s) using automatic template mapping.','success');wp_safe_redirect(admin_url('admin.php?page=wfebpg'));exit;
}
function wfebpg_process_queue_now(){
    if(!current_user_can('manage_options')||!check_admin_referer('wfebpg_process_queue_now'))wp_die('Unauthorized.');
    $q=get_option('wfebpg_queue',[]);
    if(empty($q)){WFEBPG_Logger::log('Process Queue Now clicked, but the queue is empty.','info');}
    else{WFEBPG_Logger::log('Manual queue processing started.','info');WFEBPG_Generator::process_queue();}
    wp_safe_redirect(admin_url('admin.php?page=wfebpg'));exit;
}
function wfebpg_clear_logs(){if(!current_user_can('manage_options')||!check_admin_referer('wfebpg_clear_logs'))wp_die('Unauthorized.');delete_option('wfebpg_logs');wp_safe_redirect(admin_url('admin.php?page=wfebpg'));exit;}
function wfebpg_reset_generated(){
    if(!current_user_can('manage_options')||!check_admin_referer('wfebpg_reset_generated'))wp_die('Unauthorized.');
    // This button only clears the plugin's To-Do table. Never delete real WordPress pages here.
    delete_option('wfebpg_created_pages');
    wp_safe_redirect(add_query_arg(['page'=>'wfebpg','wfebpg_reset'=>1],admin_url('admin.php')));exit;
}
function wfebpg_clear_templates(){
    if(!current_user_can('manage_options')||!check_admin_referer('wfebpg_clear_templates'))wp_die('Unauthorized.');
    foreach(wfebpg_get_saved_templates() as $template) @unlink($template['path']);
    wp_safe_redirect(admin_url('admin.php?page=wfebpg'));exit;
}
function wfebpg_trash_generated(){
    $id=absint($_GET['id']??0);
    if(!$id || !current_user_can('delete_post',$id) || !check_admin_referer('wfebpg_trash_generated_'.$id))wp_die('Unauthorized.');
    if(get_post_type($id)!=='page')wp_die('Only WordPress pages can be moved to Trash.');
    $trashed=wp_trash_post($id);
    if($trashed){
        $created_pages=get_option('wfebpg_created_pages',[]);
        $created_pages=array_values(array_filter($created_pages,function($row)use($id){return absint($row['id']??0)!==$id;}));
        update_option('wfebpg_created_pages',$created_pages,false);
        WFEBPG_Logger::log('Moved generated page #'.$id.' to the WordPress Trash.','info');
    }
    wp_safe_redirect(admin_url('admin.php?page=wfebpg'));exit;
}
function wfebpg_rollback(){wp_safe_redirect(admin_url('admin.php?page=wfebpg'));exit;}
