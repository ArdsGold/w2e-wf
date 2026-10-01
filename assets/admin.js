jQuery(function($){
    var $enabled = $('#wfebpg-image-pool-enabled');
    var $panel = $('#wfebpg-image-pool-panel');
    var $ids = $('#wfebpg-image-pool-ids');
    var $preview = $('#wfebpg-image-preview');
    var $count = $('#wfebpg-image-selection-count');
    var frame = null;

    function getIds(){
        var raw = ($ids.val() || '').split(',');
        var out = [];
        $.each(raw, function(_, id){
            id = parseInt(id,10);
            if(id && $.inArray(id,out) === -1) out.push(id);
        });
        return out;
    }

    function renderSelection(attachments){
        var ids = getIds();
        $preview.empty();
        if(!ids.length){
            $count.text('No images selected.');
            return;
        }
        $count.text(ids.length + ' image' + (ids.length === 1 ? '' : 's') + ' selected.');
        $.each(ids, function(_, id){
            var attachment = attachments && attachments[id] ? attachments[id] : null;
            var url = attachment ? (attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url) : '';
            if(url) $preview.append($('<img>', {src:url, alt:'', title:attachment.filename || ''}));
        });
    }

    function saveImagePool(ids){
        if(!(window.WFEBPG_Admin && WFEBPG_Admin.ajaxUrl && WFEBPG_Admin.imagePoolNonce)) return;
        $.post(WFEBPG_Admin.ajaxUrl, {
            action: 'wfebpg_save_image_pool',
            nonce: WFEBPG_Admin.imagePoolNonce,
            image_pool_ids: ids.join(',')
        });
    }

    function loadSavedAttachments(ids){
        if(!ids.length) return;
        var attachments = {};
        var remaining = ids.length;
        $.each(ids, function(_, id){
            var attachment = wp.media.attachment(id);
            attachment.fetch().done(function(){
                attachments[id] = attachment.toJSON();
            }).always(function(){
                remaining--;
                if(remaining === 0) renderSelection(attachments);
            });
        });
    }

    function syncFrameSelection(){
        if(!frame) return;
        var ids = getIds();
        frame.state().get('selection').reset(ids);
    }

    $enabled.on('change', function(){
        $panel.toggle(this.checked);
    });

    var savedIds = getIds();
    $enabled.prop('checked', savedIds.length > 0).trigger('change');
    renderSelection({});
    loadSavedAttachments(savedIds);

    $('#wfebpg-select-images').on('click', function(e){
        e.preventDefault();
        if(!frame){
            frame = wp.media({
                title: 'Select Image Pool',
                button: {text: 'Use Selected Images'},
                library: {type: 'image'},
                multiple: true
            });
            frame.on('open', function(){ syncFrameSelection(); });
            frame.on('select', function(){
                var selection = frame.state().get('selection');
                var ids = [];
                var attachments = {};
                selection.each(function(att){
                    var json = att.toJSON();
                    ids.push(parseInt(json.id,10));
                    attachments[json.id] = json;
                });
                ids = ids.filter(function(id,index){ return id && ids.indexOf(id) === index; });
                $ids.val(ids.join(','));
                renderSelection(attachments);
                saveImagePool(ids);
            });
        }
        frame.open();
    });

    $('#wfebpg-clear-images').on('click', function(e){
        e.preventDefault();
        $ids.val('');
        renderSelection({});
        if(frame) frame.state().get('selection').reset();
        saveImagePool([]);
    });

    renderSelection({});
});


// Saved JSON templates and DOCX uploads are sent separately before the main form.
(function($){
    var $form = $('#wfebpg-docx-upload').closest('form');
    var $docx = $('#wfebpg-docx-upload');
    var $batch = $('#wfebpg-docx-batch');
    var $select = $('#wfebpg-saved-template');
    var $json = $('#wfebpg-template-upload');
    if (!$form.length || !$docx.length || !$batch.length) return;

    var stopped = false;
    var activeRequest = null;

    function setQueueingState(active, text){
        var $submit=$form.find('button[type="submit"]'), $stop=$('#wfebpg-stop-queueing');
        if(active){$submit.prop('disabled',true).text(text||'Queueing...');$stop.prop('disabled',false).show();}
        else{$stop.prop('disabled',true).hide();$submit.prop('disabled',false).text('Queue Pages');}
    }

    function cleanupBatch(batch, callback){
        if(!batch || !(window.WFEBPG_Admin && WFEBPG_Admin.ajaxUrl)){if(callback)callback();return;}
        $.post(WFEBPG_Admin.ajaxUrl,{action:'wfebpg_cancel_docx_batch',nonce:WFEBPG_Admin.docxNonce,batch:batch}).always(function(){if(callback)callback();});
    }

    function uploadDocxFiles(files,batch,done,fail){
        var index=0;
        stopped=false;
        function next(currentBatch){
            if(index>=files.length)return done(currentBatch);
            if(stopped){cleanupBatch(currentBatch);return;}
            var file=files[index++];
            setQueueingState(true,'Uploading DOCX '+index+'/'+files.length+'...');
            var data=new FormData();
            data.append('action','wfebpg_upload_docx');
            data.append('nonce',(window.WFEBPG_Admin&&WFEBPG_Admin.docxNonce)||'');
            data.append('batch',currentBatch||'');
            data.append('docx',file);
            activeRequest=$.ajax({url:(window.WFEBPG_Admin&&WFEBPG_Admin.ajaxUrl)||window.ajaxurl,type:'POST',data:data,processData:false,contentType:false,dataType:'json'})
            .done(function(response){
                activeRequest=null;
                if(stopped){cleanupBatch(response&&response.data?response.data.batch:currentBatch);return;}
                if(!response||!response.success||!response.data||!response.data.batch){fail(response&&response.data&&response.data.message?response.data.message:'Unable to upload a DOCX file.');return;}
                next(response.data.batch);
            }).fail(function(xhr,status){
                activeRequest=null;
                if(stopped||status==='abort')return;
                fail(xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message?xhr.responseJSON.data.message:'Unable to upload a DOCX file.');
            });
        }
        next(batch||'');
    }

    function uploadJson(file,done,fail){
        var data=new FormData();
        data.append('action','wfebpg_save_template');
        data.append('nonce',(window.WFEBPG_Admin&&WFEBPG_Admin.jsonNonce)||'');
        data.append('template',file);
        $.ajax({url:(window.WFEBPG_Admin&&WFEBPG_Admin.ajaxUrl)||window.ajaxurl,type:'POST',data:data,processData:false,contentType:false,dataType:'json'})
        .done(function(response){
            if(!response||!response.success||!response.data||!response.data.name){fail(response&&response.data&&response.data.message?response.data.message:'Unable to save the JSON template.');return;}
            done(response.data.name);
        }).fail(function(xhr){fail(xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message?xhr.responseJSON.data.message:'Unable to save the JSON template.');});
    }

    $('#wfebpg-stop-queueing').on('click',function(e){
        e.preventDefault();
        if($(this).prop('disabled'))return;
        stopped=true;
        if(activeRequest){activeRequest.abort();activeRequest=null;}
        var batch=$batch.val()||'';
        cleanupBatch(batch,function(){
            $batch.val('');
            $form.removeData('wfebpg-upload-ready');
            setQueueingState(false);
            window.alert('Queueing stopped. No pages were queued from this submission.');
        });
    });

    $select.on('change', function(){
        var name=$(this).val()||'';
        $.post(WFEBPG_Admin.ajaxUrl,{action:'wfebpg_set_last_template',nonce:WFEBPG_Admin.jsonNonce,template:name});
    });

    $form.on('submit',function(e){
        var form=this;
        if($(form).data('wfebpg-upload-ready'))return;
        e.preventDefault();
        var files=Array.prototype.slice.call($docx[0].files||[]);
        if(!files.length){window.alert('Please select at least one DOCX file.');return;}
        var jsonFile=$json.length&&$json[0].files?$json[0].files[0]:null;
        var savedTemplate=$select.val()||'';
        if(!jsonFile&&!savedTemplate){window.alert('Please select a saved Elementor template or upload a new one.');return;}
        stopped=false;
        $batch.val('');
        setQueueingState(true,'Starting queue...');
        uploadDocxFiles(files,'',function(batch){
            if(stopped){cleanupBatch(batch);setQueueingState(false);return;}
            $batch.val(batch);
            $docx.prop('required',false).val('');
            if(jsonFile){
                uploadJson(jsonFile,function(name){
                    $select.append($('<option>',{value:name,text:name})).val(name);
                    $json.val('');
                    $(form).data('wfebpg-upload-ready',true);
                    form.submit();
                },function(message){cleanupBatch(batch);setQueueingState(false);window.alert(message);});
            }else{
                $(form).data('wfebpg-upload-ready',true);
                form.submit();
            }
        },function(message){setQueueingState(false);window.alert(message);});
    });
})(jQuery);

// Template image replacement map: static/template images only.
(function($){
    var $template=$('#wfebpg-image-map-template'), $load=$('#wfebpg-load-template-images'), $list=$('#wfebpg-template-image-map-list'), $status=$('#wfebpg-template-image-map-status');
    if(!$template.length) return;
    var state={images:[],map:{}};
    function esc(v){return $('<div>').text(v||'').html();}
    function saveMap(){
        var payload={}; state.images.forEach(function(img){ if(state.map[img.key]) payload[img.key]=state.map[img.key]; });
        $.post(WFEBPG_Admin.ajaxUrl,{action:'wfebpg_save_template_image_map',nonce:WFEBPG_Admin.templateImageNonce,template:$template.val(),map:JSON.stringify(payload)})
        .done(function(r){ if(r.success)$status.text('Saved '+r.data.count+' image mapping(s).'); else $status.text(r.data&&r.data.message?r.data.message:'Unable to save mappings.'); })
        .fail(function(){ $status.text('Unable to save mappings.'); });
    }
    function render(){
        if(!state.images.length){$list.html('<p class="description">No template images found.</p>');return;}
        var html='';
        state.images.forEach(function(img,i){
            var mapped=img.mapped, chosen=state.map[img.key]?true:false, suggestion=img.suggestions&&img.suggestions[0];
            html+='<div class="wfebpg-image-map-row" data-key="'+esc(img.key)+'">';
            html+='<div class="wfebpg-image-map-old"><strong>Template image</strong><div class="wfebpg-image-map-thumb">'+(img.url?'<img src="'+esc(img.url)+'">':'<span>No preview</span>')+'</div><div class="wfebpg-image-map-name">'+esc(img.filename||img.url||'Unknown image')+'</div><small>'+esc(img.field)+'</small></div>';
            html+='<div class="wfebpg-image-map-arrow">→</div>';
            html+='<div class="wfebpg-image-map-new"><strong>Replacement</strong><div class="wfebpg-map-current">'+(mapped?'<div class="wfebpg-image-map-thumb"><img src="'+esc(mapped.url)+'"></div><div>'+esc(mapped.title||mapped.filename)+'</div>':'<span class="description">Not mapped</span>')+'</div>';
            if(suggestion){ html+='<div class="wfebpg-image-map-suggestion"><strong>Recommended replacement</strong><div class="wfebpg-image-map-suggestion-preview">'+(suggestion.image.url?'<div class="wfebpg-image-map-thumb"><img src="'+esc(suggestion.image.url)+'"></div>':'<span class="description">No preview</span>')+'<div><strong>'+esc(suggestion.image.title||suggestion.image.filename)+'</strong><br><span>('+esc(suggestion.score)+'% match)</span></div></div><button type="button" class="button button-small wfebpg-use-suggestion" data-key="'+esc(img.key)+'" data-id="'+suggestion.image.id+'">Use Suggestion</button></div>'; }
            html+='<button type="button" class="button button-small wfebpg-choose-template-image" data-key="'+esc(img.key)+'">Choose Media</button> <button type="button" class="button-link-delete wfebpg-clear-template-image" data-key="'+esc(img.key)+'">Clear</button></div></div>';
        });
        $list.html(html);
    }
    $load.on('click',function(){
        var name=$template.val(); if(!name){$status.text('Select a saved template first.');$list.empty();return;}
        $load.prop('disabled',true).text('Scanning...'); $status.text('Scanning template images and comparing Media Library filenames...');
        $.post(WFEBPG_Admin.ajaxUrl,{action:'wfebpg_get_template_images',nonce:WFEBPG_Admin.templateImageNonce,template:name})
        .done(function(r){ if(!r.success){$status.text(r.data&&r.data.message?r.data.message:'Unable to scan template.');return;} state.images=r.data.images||[];state.map={};state.images.forEach(function(img){if(img.mapped)state.map[img.key]=img.mapped.id;});$status.text(state.images.length+' template image(s) found. Review the suggestions below.');render(); })
        .fail(function(){ $status.text('Unable to scan template.'); })
        .always(function(){ $load.prop('disabled',false).text('Scan Template Images'); });
    });
    $list.on('click','.wfebpg-use-suggestion',function(){ state.map[$(this).data('key')]=parseInt($(this).data('id'),10); var img=state.images.find(function(x){return x.key===$(this).data('key');}.bind(this)); if(img){img.mapped=(img.suggestions||[]).find(function(x){return parseInt(x.image.id,10)===parseInt($(this).data('id'),10);}.bind(this)).image;} render(); saveMap(); });
    $list.on('click','.wfebpg-choose-template-image',function(){
        var key=$(this).data('key'), frame=wp.media({title:'Choose replacement image',button:{text:'Use this image'},multiple:false,library:{type:'image'}});
        frame.on('select',function(){ var a=frame.state().get('selection').first().toJSON(); state.map[key]=parseInt(a.id,10); var img=state.images.find(function(x){return x.key===key;}); if(img)img.mapped={id:a.id,title:a.title,filename:a.filename||a.filename,url:a.url||a.sizes&&a.sizes.medium&&a.sizes.medium.url}; render(); saveMap(); }); frame.open();
    });
    $list.on('click','.wfebpg-clear-template-image',function(){ var key=$(this).data('key'); delete state.map[key]; var img=state.images.find(function(x){return x.key===key;}); if(img)img.mapped=false; render(); saveMap(); });
    $template.on('change',function(){$list.empty();$status.text('Select Scan Template Images to load the current mappings and suggestions.');});
})(jQuery);
