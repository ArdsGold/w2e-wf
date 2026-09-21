<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function(){
    add_menu_page('WF Bulk Pages','WF Bulk Pages','manage_options','wfebpg','wfebpg_admin','dashicons-layout',58);
    add_submenu_page('wfebpg','Template Validator','Template Validator','manage_options','wfebpg-validator','wfebpg_template_validator');
});
add_action('admin_enqueue_scripts', function($hook){
    if (strpos($hook, 'wfebpg') === false) return;
    wp_enqueue_media();
    wp_enqueue_script('jquery');
    wp_enqueue_script('wfebpg-admin', WFEBPG_URL.'assets/admin.js', ['jquery'], WFEBPG_VERSION, true);
    wp_enqueue_style('wfebpg-admin', WFEBPG_URL.'assets/admin.css', [], WFEBPG_VERSION);
});
add_action('admin_post_wfebpg_generate','wfebpg_handle_generate');
add_action('admin_post_wfebpg_process_queue_now','wfebpg_process_queue_now');
add_action('admin_post_wfebpg_clear_logs','wfebpg_clear_logs');
add_action('admin_post_wfebpg_reset_generated','wfebpg_reset_generated');
add_action('admin_post_wfebpg_rollback','wfebpg_rollback');
add_action('admin_post_wfebpg_clear_templates','wfebpg_clear_templates');

function wfebpg_admin(){
    if(!current_user_can('manage_options'))return;
    $pages=get_pages(['post_status'=>['publish','draft','private']]);
    $logs=WFEBPG_Logger::get();
    $q=get_option('wfebpg_queue',[]);
    $created_pages=get_option('wfebpg_created_pages',[]);
    $saved_templates=wfebpg_get_saved_templates();
    ?>
<div class="wrap wfebpg-admin"><h1>Wolf Forge Elementor Bulk Page Generator</h1>
<?php if(isset($_GET['wfebpg_reset'])): ?><div class="notice notice-success is-dismissible"><p>Reset complete. <?php echo absint($_GET['deleted']??0); ?> generated page(s) deleted and the logs/To-Do list cleared<?php if(!empty($_GET['skipped'])): ?>. <?php echo absint($_GET['skipped']); ?> older tracked page(s) were left untouched because they were created before safe reset tracking was added<?php endif; ?>.</p></div><?php endif; ?>
<p>Upload DOCX content and an Elementor JSON template. Jobs are queued and processed in the background to reduce timeouts.</p>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" enctype="multipart/form-data">
<input type="hidden" name="action" value="wfebpg_generate"><?php wp_nonce_field('wfebpg_generate'); ?>
<table class="form-table">
<tr><th>Page Type</th><td><label><input type="radio" name="mode" value="unique" checked> Unique Pages</label> &nbsp; <label><input type="radio" name="mode" value="generic"> Generic Pages</label></td></tr>
<tr><th>DOCX Files</th><td><input type="file" name="docx[]" accept=".docx" multiple required><p class="description">Yellow-font headings are treated as repeatable markers in Unique mode.</p></td></tr>
<tr><th>Elementor JSON Template</th><td>
    <div class="wfebpg-template-library">
        <label for="wfebpg-saved-template"><strong>Use a previously uploaded template</strong></label><br>
        <select name="saved_template" id="wfebpg-saved-template" style="min-width:420px;max-width:100%;margin-top:6px">
            <option value="">— Upload a new template instead —</option>
            <?php foreach ($saved_templates as $template): ?>
                <option value="<?php echo esc_attr($template['name']); ?>"><?php echo esc_html($template['name']); ?><?php if (!empty($template['modified'])): ?> — <?php echo esc_html(wp_date(get_option('date_format'), $template['modified'])); ?><?php endif; ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($saved_templates)): ?>
            <p class="description">No saved JSON templates yet. Upload one below and it will appear here for future jobs.</p>
        <?php else: ?>
            <p class="description"><?php echo count($saved_templates); ?> saved template<?php echo count($saved_templates) === 1 ? '' : 's'; ?> available. Select one to reuse it without uploading again.</p>
        <?php endif; ?>
    </div>
    <p style="margin:14px 0 6px"><strong>Or upload a new JSON template</strong></p>
    <input type="file" name="template" id="wfebpg-template-upload" accept=".json">
    <p class="description">New uploads are remembered automatically. Uploading another .json with the same filename replaces the saved copy.</p>
    <?php $clear_templates_url=wp_nonce_url(admin_url('admin-post.php?action=wfebpg_clear_templates'),'wfebpg_clear_templates'); ?>
    <?php if (!empty($saved_templates)): ?>
        <a class="button button-link-delete" href="<?php echo esc_url($clear_templates_url); ?>" onclick="return confirm('Clear all remembered Elementor JSON templates? This does not affect templates already copied into queued jobs.');">Clear Saved JSON Templates</a>
    <?php endif; ?>
</td></tr>
<tr><th>Repeatable Section</th><td><label>Widgets per section <input type="number" name="widgets_per_section" value="4" min="1" max="100" style="width:80px"></label><p class="description">Unique mode: maximum number of data-customID|repeatableItem widgets in each section. When reached, the entire containing Elementor section is cloned and the next yellow DOCX headings continue in the new section.</p></td></tr>
<tr><th>Image Pool</th><td>
    <label class="wfebpg-toggle"><input type="checkbox" name="image_pool_enabled" id="wfebpg-image-pool-enabled" value="1"> <strong>Enable Media Library Image Pool</strong></label>
    <p class="description">Unique mode: randomly assign selected Media Library images to repeatable card sections. Images are not duplicated within the same generated section.</p>
    <div id="wfebpg-image-pool-panel" class="wfebpg-image-pool-panel" style="display:none">
        <input type="hidden" name="image_pool_ids" id="wfebpg-image-pool-ids" value="">
        <button type="button" class="button" id="wfebpg-select-images">Select Images from Media Library</button>
        <button type="button" class="button-link-delete" id="wfebpg-clear-images" style="margin-left:10px">Clear Selection</button>
        <div id="wfebpg-image-selection-count" class="description" style="margin-top:8px">No images selected.</div>
        <div id="wfebpg-image-preview" class="wfebpg-image-preview"></div>
        <p class="description">The same image can be used again in another section or another page. Only duplicates inside the same repeatable section are prevented.</p>
    </div>
</td></tr>
<tr><th>Parent Page</th><td><select name="parent"><option value="0">— No Parent —</option><?php foreach($pages as $p):?><option value="<?php echo esc_attr($p->ID);?>"><?php echo esc_html($p->post_title);?></option><?php endforeach;?></select></td></tr>
<tr><th>Existing Slugs</th><td><label><input type="checkbox" name="overwrite" value="1"> Overwrite matching existing pages</label><p class="description">Otherwise a short suffix is added to avoid overwriting.</p></td></tr>
</table><p><button class="button button-primary button-hero">Queue Pages</button></p></form>
<hr><h2>Queue</h2><p><strong><?php echo count($q);?></strong> job(s) waiting.</p>
<?php $next_cron=wp_next_scheduled('wfebpg_process_queue'); if($next_cron): ?>
<p class="description">Next WP-Cron run: <?php echo esc_html(wp_date(get_option('date_format').' '.get_option('time_format'),$next_cron)); ?></p>
<?php else: ?>
<p class="description">No WP-Cron worker is currently scheduled.</p>
<?php endif; ?>
<form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" style="margin:10px 0 20px"><input type="hidden" name="action" value="wfebpg_process_queue_now"><?php wp_nonce_field('wfebpg_process_queue_now');?><button class="button button-primary" <?php disabled(empty($q)); ?>>Process Queue Now</button> <span class="description">Processes one queued job immediately.</span></form>
<h2>Newly Created Pages — To-Do</h2><p>Use these links to open a generated page in WordPress or Elementor for final review and editing.</p><table class="widefat striped" style="margin-top:10px"><thead><tr><th>Created</th><th>Page</th><th>Actions</th></tr></thead><tbody><?php if(empty($created_pages)):?><tr><td colspan="3">No generated pages yet.</td></tr><?php else: foreach(array_slice($created_pages,0,100) as $cp): $cp_id=absint($cp['id']??0); if(!$cp_id)continue; ?><tr><td><?php echo esc_html($cp['time']??'');?></td><td><?php echo esc_html($cp['title']??get_the_title($cp_id));?></td><td><a class="button button-small" href="<?php echo esc_url(get_edit_post_link($cp_id));?>">Edit Page</a> <a class="button button-small" href="<?php echo esc_url(admin_url('post.php?post='.$cp_id.'&action=elementor'));?>">Edit with Elementor</a> <a href="<?php echo esc_url(get_permalink($cp_id));?>" target="_blank" rel="noopener">View</a></td></tr><?php endforeach; endif;?></tbody></table>
<h2>Reset</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>" style="margin:10px 0 20px"><input type="hidden" name="action" value="wfebpg_reset_generated"><?php wp_nonce_field('wfebpg_reset_generated');?><button class="button button-secondary" style="border-color:#b32d2e;color:#b32d2e" onclick="return confirm('This will permanently delete pages created by Wolf Forge Bulk Page Generator and clear the generated-page list and logs. Overwritten existing pages will not be deleted. Continue?');">Reset Logs &amp; Generated Pages</button> <span class="description">Permanently deletes pages created by this plugin, clears their To-Do list, and clears the logs. Existing pages that were overwritten are not deleted.</span></form>
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
                <tr><th>DOCX Content (optional)</th><td><input type="file" name="validator_docx" accept=".docx"><p class="description">Upload the DOCX to validate H1/H2/H3 and yellow repeatable compatibility as well.</p></td></tr>
            </table>
            <p><button class="button button-primary button-hero">Validate Template</button></p>
        </form>
        <?php if ($result): ?>
            <div class="wfebpg-validator-grid">
                <div class="wfebpg-validator-card">
                    <h2>Content Markers</h2>
                    <?php foreach ($result['counts'] as $key=>$count): ?><div class="wfebpg-check-row"><span><?php echo esc_html($key); ?></span><strong><?php echo absint($count); ?></strong></div><?php endforeach; ?>
                </div>
                <div class="wfebpg-validator-card">
                    <h2>Repeatable Structure</h2>
                    <div class="wfebpg-check-row"><span>Repeatable sections</span><strong><?php echo absint($result['repeat_sections']); ?></strong></div>
                    <div class="wfebpg-check-row"><span>Repeatable card columns with images</span><strong><?php echo absint($result['repeat_columns']); ?></strong></div>
                    <div class="wfebpg-check-row"><span>Image/background locations</span><strong><?php echo absint($result['image_locations']); ?></strong></div>
                    <div class="wfebpg-check-row"><span>Images inside repeatable sections</span><strong><?php echo absint($result['repeat_image_locations']); ?></strong></div>
                </div>
                <?php if ($result['doc']): ?><div class="wfebpg-validator-card"><h2>DOCX Compatibility</h2><?php foreach ($result['doc'] as $key=>$value): ?><div class="wfebpg-check-row"><span><?php echo esc_html(strtoupper($key)); ?></span><strong><?php echo absint($value); ?></strong></div><?php endforeach; ?></div><?php endif; ?>
            </div>
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

function wfebpg_get_saved_templates(){
    $dir=wfebpg_template_library_dir();
    $files=glob(trailingslashit($dir).'*.json') ?: [];
    $templates=[];
    foreach($files as $file){
        if(!is_file($file)) continue;
        $name=basename($file);
        $templates[]=['name'=>$name,'path'=>$file,'modified'=>filemtime($file) ?: 0];
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
    if(empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) throw new Exception('Please select an Elementor JSON template.');
    $name=sanitize_file_name(basename($file['name'] ?? 'template.json'));
    if(!$name || strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='json') throw new Exception('The template must be a .json file.');
    $json=file_get_contents($file['tmp_name']);
    if($json===false) throw new Exception('Unable to read the template file.');
    WFEBPG_Template::decode($json);
    $dest=trailingslashit(wfebpg_template_library_dir()).$name;
    if(!move_uploaded_file($file['tmp_name'],$dest)) throw new Exception('Unable to save the template.');
    return $dest;
}

function wfebpg_handle_generate(){
    if(!current_user_can('manage_options')||!check_admin_referer('wfebpg_generate'))wp_die('Unauthorized.');
    if(empty($_FILES['docx']['name'][0]))wp_die('DOCX files are required.');
    $saved_name=sanitize_file_name(wp_unslash($_POST['saved_template']??''));
    try{
        if(!empty($_FILES['template']['tmp_name'])){
            $library_template=wfebpg_save_template_upload($_FILES['template']);
        }elseif($saved_name){
            $library_template=wfebpg_find_saved_template($saved_name);
            if(!$library_template) throw new Exception('The selected saved JSON template no longer exists.');
        }else{
            throw new Exception('Please select a saved JSON template or upload a new one.');
        }
    }catch(Throwable $e){wp_die(esc_html($e->getMessage()));}
    $upload=wp_upload_dir();$base=trailingslashit($upload['basedir']).'wfebpg/'.wp_generate_uuid4();wp_mkdir_p($base);
    $template=$base.'/template.json';if(!copy($library_template,$template))wp_die('Unable to copy the saved template for this job.');
    $mode=sanitize_key($_POST['mode']??'generic');$parent=absint($_POST['parent']??0);$overwrite=!empty($_POST['overwrite']);$widgets_per_section=max(1,min(100,absint($_POST['widgets_per_section']??4)));$count=0;
    $image_pool_enabled=!empty($_POST['image_pool_enabled']);
    $image_pool_ids=[];
    if($image_pool_enabled && !empty($_POST['image_pool_ids'])){
        $raw=explode(',',sanitize_text_field(wp_unslash($_POST['image_pool_ids'])));
        foreach($raw as $id){$id=absint($id);if($id && get_post_type($id)==='attachment' && strpos((string)get_post_mime_type($id),'image/')===0)$image_pool_ids[]=$id;}
        $image_pool_ids=array_values(array_unique($image_pool_ids));
    }
    foreach($_FILES['docx']['name'] as $i=>$name){if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='docx')continue;$dest=$base.'/'.sanitize_file_name(basename($name));if(!move_uploaded_file($_FILES['docx']['tmp_name'][$i],$dest))continue;WFEBPG_Generator::enqueue(['docx'=>$dest,'template'=>$template,'mode'=>$mode,'parent'=>$parent,'overwrite'=>$overwrite,'widgets_per_section'=>$widgets_per_section,'image_pool_ids'=>$image_pool_ids]);$count++;}
    if($image_pool_ids) WFEBPG_Logger::log('Image Pool attached to queued job(s): '.count($image_pool_ids).' Media Library image(s).','info');
    WFEBPG_Logger::log('Queued '.$count.' DOCX page job(s).','success');wp_safe_redirect(admin_url('admin.php?page=wfebpg'));exit;
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
    $created=get_option('wfebpg_created_pages',[]);$deleted=0;$skipped=0;
    foreach($created as $cp){$id=absint($cp['id']??0);if(!$id||get_post_type($id)!=='page')continue;$safe_generated=!empty($cp['plugin_created'])||get_post_meta($id,'_wfebpg_generated',true)==='1';if(!$safe_generated){$skipped++;continue;}if(wp_delete_post($id,true))$deleted++;}
    delete_option('wfebpg_created_pages');delete_option('wfebpg_logs');wp_safe_redirect(add_query_arg(['page'=>'wfebpg','wfebpg_reset'=>1,'deleted'=>$deleted,'skipped'=>$skipped],admin_url('admin.php')));exit;
}
function wfebpg_clear_templates(){
    if(!current_user_can('manage_options')||!check_admin_referer('wfebpg_clear_templates'))wp_die('Unauthorized.');
    foreach(wfebpg_get_saved_templates() as $template) @unlink($template['path']);
    wp_safe_redirect(admin_url('admin.php?page=wfebpg'));exit;
}
function wfebpg_rollback(){wp_safe_redirect(admin_url('admin.php?page=wfebpg'));exit;}
