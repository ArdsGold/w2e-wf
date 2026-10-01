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


// Shared saved-template selection across all template-related tabs.
(function($){
    function sync(name, persist){
        name=name||'';
        $('#wfebpg-saved-template, #wfebpg-image-map-template, #wfebpg-validator-saved-template').each(function(){
            $(this).val(name);
        });
        if(persist && window.WFEBPG_Admin && WFEBPG_Admin.ajaxUrl && WFEBPG_Admin.jsonNonce){
            $.post(WFEBPG_Admin.ajaxUrl,{action:'wfebpg_set_last_template',nonce:WFEBPG_Admin.jsonNonce,template:name});
        }
        $(document).trigger('wfebpg:template-changed',[name]);
        if(window.WFEBPG_UpdateTemplateImageStatus) window.WFEBPG_UpdateTemplateImageStatus(name);
        if(window.WFEBPG_UpdateTemplateValidatorStatus) window.WFEBPG_UpdateTemplateValidatorStatus(name);
    }
    window.WFEBPG_SyncSharedTemplateSelection=sync;
    $(document).on('change','#wfebpg-saved-template, #wfebpg-image-map-template, #wfebpg-validator-saved-template',function(){
        var name=$(this).val()||'';
        if(this.id==='wfebpg-saved-template' && name){
            var $json=$('#wfebpg-template-upload');
            if($json.length && $json[0].files && $json[0].files.length){
                $json.val('');
            }
        }
        sync(name,true);
        if(window.WFEBPG_SyncTemplateSourceUI) window.WFEBPG_SyncTemplateSourceUI();
    });
})(jQuery);

// Template Validator tab status. The selected saved template is checked in the background
// so warnings/errors are visible without requiring the user to click Validate Template.
(function($){
    var $root=$('.wfebpg-admin');
    if(!$root.length) return;
    var $tab=$root.find('.wfebpg-tab-button[data-tab="validator"]');
    if(!$tab.length) return;
    var $label=$tab.find('.wfebpg-tab-label'), baseLabel='Template Validator';
    var statuses={};
    try{statuses=JSON.parse($root.attr('data-template-validator-statuses')||'{}')||{};}catch(e){statuses={};}
    function setStatus(name,status){
        status=status||{};
        var hasError=!!status.has_errors;
        var hasWarning=!hasError && !!status.has_warnings;
        var errorCount=parseInt(status.error_count,10)||0;
        var warningCount=parseInt(status.warning_count,10)||0;
        $tab.removeClass('wfebpg-tab-has-error wfebpg-tab-has-warning');
        if(hasError){
            $tab.addClass('wfebpg-tab-has-error');
            $label.text('● '+baseLabel);
            $tab.attr('title',errorCount+' template validation error'+(errorCount===1?'':'s')+' in the selected template.');
        }else if(hasWarning){
            $tab.addClass('wfebpg-tab-has-warning');
            $label.text('⚠ '+baseLabel);
            $tab.attr('title',warningCount+' template validation warning'+(warningCount===1?'':'s')+' in the selected template.');
        }else{
            $label.text(baseLabel);
            $tab.attr('title','');
        }
    }
    function refresh(name){
        name=name||'';
        if(!name){setStatus('',{});return;}
        if(statuses[name]){setStatus(name,statuses[name]);}
        if(!(window.WFEBPG_Admin&&WFEBPG_Admin.ajaxUrl&&WFEBPG_Admin.validatorStatusNonce)) return;
        $.post(WFEBPG_Admin.ajaxUrl,{action:'wfebpg_get_validator_status',nonce:WFEBPG_Admin.validatorStatusNonce,template:name})
            .done(function(r){if(r&&r.success&&r.data){statuses[name]=r.data;setStatus(name,r.data);}});
    }
    window.WFEBPG_UpdateTemplateValidatorStatus=function(name){refresh(name||$('#wfebpg-validator-saved-template').val()||'');};
    $(document).on('wfebpg:template-changed',function(e,name){refresh(name||'');});
    refresh($('#wfebpg-validator-saved-template').val()||'');
})(jQuery);

// Saved JSON templates and DOCX uploads are sent separately before the main form.
(function($){
    var $form = $('#wfebpg-docx-upload').closest('form');
    var $docx = $('#wfebpg-docx-upload');
    var $batch = $('#wfebpg-docx-batch');
    var $select = $('#wfebpg-saved-template');
    var $json = $('#wfebpg-template-upload');
    var $clearJson = $('#wfebpg-clear-template-upload');
    var $sourceStatus = $('#wfebpg-template-source-status');
    if (!$form.length || !$docx.length || !$batch.length) return;

    var stopped = false;
    var activeRequest = null;

    function syncTemplateSourceUI(){
        var hasSaved = !!($select.val() || '');
        var hasUpload = !!($json.length && $json[0].files && $json[0].files.length);

        // Only one template source may be active at a time.
        if (hasUpload) {
            $select.prop('disabled', true);
            $json.prop('disabled', false);
            $clearJson.show();
            $sourceStatus.text('A new JSON template is selected for upload. The saved-template dropdown is disabled until you clear the uploaded template.');
        } else if (hasSaved) {
            $select.prop('disabled', false);
            $json.prop('disabled', true);
            $clearJson.hide();
            $sourceStatus.text('A saved JSON template is selected. Uploading a new template is disabled until you choose “Upload a new template instead”.');
        } else {
            $select.prop('disabled', false);
            $json.prop('disabled', false);
            $clearJson.hide();
            $sourceStatus.text('Choose either a saved Elementor template or upload a new JSON template. Only one template source can be active at a time.');
        }
    }
    window.WFEBPG_SyncTemplateSourceUI=syncTemplateSourceUI;

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

    $json.on('change', function(){
        if(this.files && this.files.length){
            if(window.WFEBPG_SyncSharedTemplateSelection) window.WFEBPG_SyncSharedTemplateSelection('',true);
        }
        syncTemplateSourceUI();
    });

    $clearJson.on('click', function(e){
        e.preventDefault();
        $json.val('');
        syncTemplateSourceUI();
    });

    syncTemplateSourceUI();

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
                    syncTemplateSourceUI();
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

// Click template/replacement previews to view the image larger without leaving the admin page.
(function($){
    var $lightbox=$('<div class="wfebpg-image-lightbox" role="dialog" aria-modal="true" aria-label="Image preview"><button type="button" class="wfebpg-image-lightbox-close" aria-label="Close image preview">&times;</button><img alt=""></div>');
    if(!$lightbox.length)return;
    $('body').append($lightbox);
    var $image=$lightbox.find('img');
    function close(){ $lightbox.removeClass('is-open'); $image.attr('src','').attr('alt',''); }
    function open(src,alt){ if(!src)return; $image.attr('src',src).attr('alt',alt||'Image preview'); $lightbox.addClass('is-open'); }
    $(document).on('click','.wfebpg-image-map-thumb img, .wfebpg-image-map-suggestion-preview img',function(e){
        e.preventDefault();
        e.stopPropagation();
        open($(this).attr('src'),$(this).attr('alt')||'Image preview');
    });
    $lightbox.on('click',function(e){ if(e.target===this)close(); });
    $lightbox.on('click','.wfebpg-image-lightbox-close',close);
    $(document).on('keydown',function(e){ if(e.key==='Escape' && $lightbox.hasClass('is-open'))close(); });
})(jQuery);

// Shared selected-template status indicator.
// The warning is based on the selected saved template's actual image references
// and saved mappings. It never depends on clicking the scan button.
(function($){
    var $root=$('.wfebpg-admin');
    if(!$root.length) return;
    var $tab=$root.find('.wfebpg-tab-button[data-tab="image-replacement"]');
    var $status=$tab.find('.wfebpg-tab-status');
    var request=null;
    function setStatus(unresolved,total){
        unresolved=parseInt(unresolved,10)||0;
        total=parseInt(total,10)||0;
        var warning=unresolved>0;
        var label='Template Image Replacement';
        $tab.toggleClass('wfebpg-tab-has-warning',warning);
        $tab.find('.wfebpg-tab-label').text(warning?'⚠ '+label:label);
        $status.text('');
        $tab.attr('title',warning?(unresolved+' template image'+(unresolved===1?'':'s')+' need mapping or replacement.'):'');
    }
    function apply(name,data){
        data=data||{};
        var unresolved=typeof data.unresolved_images!=='undefined'?data.unresolved_images:data.unresolved_external;
        var total=typeof data.total_images!=='undefined'?data.total_images:data.total_external;
        setStatus(unresolved,total);
    }
    function check(name){
        name=name||'';
        if(!name){setStatus(0,0);return;}
        if(!(window.WFEBPG_Admin && WFEBPG_Admin.ajaxUrl && WFEBPG_Admin.templateImageNonce)){setStatus(0,0);return;}
        if(request && request.readyState!==4) request.abort();
        request=$.post(WFEBPG_Admin.ajaxUrl,{action:'wfebpg_get_template_image_status',nonce:WFEBPG_Admin.templateImageNonce,template:name})
            .done(function(r){
                if(r&&r.success&&r.data) apply(name,r.data);
                else setStatus(0,0);
            })
            .fail(function(){ setStatus(0,0); });
    }
    window.WFEBPG_UpdateTemplateImageStatus=check;
    $(document).on('wfebpg:template-changed',function(e,name){check(name);});
    $(document).on('change','#wfebpg-saved-template, #wfebpg-image-map-template, #wfebpg-validator-saved-template',function(){check($(this).val()||'');});
    check($('#wfebpg-saved-template').val() || $('#wfebpg-image-map-template').val() || $('#wfebpg-validator-saved-template').val() || '');
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
        .done(function(r){ if(r.success){$status.text('Saved '+r.data.count+' image mapping(s).');if(window.WFEBPG_UpdateTemplateImageStatus){var st=r.data.status||{};window.WFEBPG_UpdateTemplateImageStatus($template.val()||'',parseInt(st.unresolved_images!==undefined?st.unresolved_images:st.unresolved_external,10)||0,parseInt(st.total_images!==undefined?st.total_images:st.total_external,10)||0);}} else $status.text(r.data&&r.data.message?r.data.message:'Unable to save mappings.'); })
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
        .done(function(r){ if(!r.success){$status.text(r.data&&r.data.message?r.data.message:'Unable to scan template.');return;} state.images=r.data.images||[];state.map={};state.images.forEach(function(img){if(img.mapped)state.map[img.key]=img.mapped.id;});$status.text(state.images.length+' template image(s) found. Review the suggestions below.');render();if(window.WFEBPG_UpdateTemplateImageStatus){var st=r.data.status||{};window.WFEBPG_UpdateTemplateImageStatus(name,parseInt(st.unresolved_images!==undefined?st.unresolved_images:st.unresolved_external,10)||0,parseInt(st.total_images!==undefined?st.total_images:st.total_external,10)||0);} })
        .fail(function(){ $status.text('Unable to scan template.'); })
        .always(function(){ $load.prop('disabled',false).text('Scan Template Images'); });
    });
    $list.on('click','.wfebpg-use-suggestion',function(){ state.map[$(this).data('key')]=parseInt($(this).data('id'),10); var img=state.images.find(function(x){return x.key===$(this).data('key');}.bind(this)); if(img){img.mapped=(img.suggestions||[]).find(function(x){return parseInt(x.image.id,10)===parseInt($(this).data('id'),10);}.bind(this)).image;} render(); saveMap(); });
    $list.on('click','.wfebpg-choose-template-image',function(){
        var key=$(this).data('key'), frame=wp.media({title:'Choose replacement image',button:{text:'Use this image'},multiple:false,library:{type:'image'}});
        frame.on('select',function(){ var a=frame.state().get('selection').first().toJSON(); state.map[key]=parseInt(a.id,10); var img=state.images.find(function(x){return x.key===key;}); if(img)img.mapped={id:a.id,title:a.title,filename:a.filename||a.filename,url:a.url||a.sizes&&a.sizes.medium&&a.sizes.medium.url}; render(); saveMap(); }); frame.open();
    });
    $list.on('click','.wfebpg-clear-template-image',function(){ var key=$(this).data('key'); delete state.map[key]; var img=state.images.find(function(x){return x.key===key;}); if(img)img.mapped=false; render(); saveMap(); });
    $template.on('change',function(){$list.empty();$status.text('Select Scan Template Images to load the current mappings and suggestions.');if(window.WFEBPG_UpdateTemplateImageStatus)window.WFEBPG_UpdateTemplateImageStatus($(this).val()||'');});
})(jQuery);

// New Pages tab notification. It polls the existing generated-page list so a page
// generated by the background queue can light the tab without requiring a scan or refresh.
(function($){
    var $root=$('.wfebpg-admin');
    if(!$root.length || !window.WFEBPG_Admin || !WFEBPG_Admin.ajaxUrl || !WFEBPG_Admin.newPagesNonce) return;
    var $tab=$root.find('.wfebpg-tab-button[data-tab="new-pages"]');
    if(!$tab.length) return;
    var $label=$tab.find('.wfebpg-tab-label');
    var baseLabel='New Pages';
    var pollTimer=null;
    var lastSignature='';
    function setUnread(unread,signature){
        unread=!!unread;
        if(signature!==undefined) lastSignature=signature||'';
        $tab.toggleClass('wfebpg-tab-has-new-pages',unread);
        $label.text(unread?'● '+baseLabel:baseLabel);
        $tab.attr('title',unread?'A new page has been generated and is waiting for review.':'');
    }
    function check(){
        $.post(WFEBPG_Admin.ajaxUrl,{action:'wfebpg_get_new_pages_status',nonce:WFEBPG_Admin.newPagesNonce})
            .done(function(r){if(r&&r.success&&r.data)setUnread(!!r.data.unread,r.data.signature||'');});
    }
    function markSeen(){
        $.post(WFEBPG_Admin.ajaxUrl,{action:'wfebpg_mark_new_pages_seen',nonce:WFEBPG_Admin.newPagesNonce})
            .done(function(r){if(r&&r.success&&r.data)setUnread(false,r.data.signature||lastSignature);});
    }
    $root.on('click','.wfebpg-tab-button[data-tab="new-pages"]',function(){
        setUnread(false,lastSignature);
        markSeen();
    });
    check();
    pollTimer=window.setInterval(check,5000);
    $root.data('wfebpg-new-pages-poll',pollTimer);
})(jQuery);

// Logs tab error notification. It watches for newly recorded error-level log entries
// without requiring the user to open or refresh the Logs tab.
(function($){
    var $root=$('.wfebpg-admin');
    if(!$root.length || !window.WFEBPG_Admin || !WFEBPG_Admin.ajaxUrl || !WFEBPG_Admin.logsErrorNonce) return;
    var $tab=$root.find('.wfebpg-tab-button[data-tab="logs"]');
    if(!$tab.length) return;
    var $label=$tab.find('.wfebpg-tab-label');
    var baseLabel='Logs';
    var lastSignature='';
    function setError(unread,signature){
        unread=!!unread;
        if(signature!==undefined) lastSignature=signature||'';
        $tab.toggleClass('wfebpg-tab-has-log-error',unread);
        $label.text(unread?'● '+baseLabel:baseLabel);
        $tab.attr('title',unread?'A new error has been recorded in the plugin log.':'');
    }
    function check(){
        $.post(WFEBPG_Admin.ajaxUrl,{action:'wfebpg_get_logs_error_status',nonce:WFEBPG_Admin.logsErrorNonce})
            .done(function(r){if(r&&r.success&&r.data)setError(!!r.data.unread,r.data.signature||'');});
    }
    function markSeen(){
        $.post(WFEBPG_Admin.ajaxUrl,{action:'wfebpg_mark_logs_error_seen',nonce:WFEBPG_Admin.logsErrorNonce})
            .done(function(r){if(r&&r.success&&r.data)setError(false,r.data.signature||lastSignature);});
    }
    $root.on('click','.wfebpg-tab-button[data-tab="logs"]',function(){
        setError(false,lastSignature);
        markSeen();
    });
    check();
    var timer=window.setInterval(check,5000);
    $root.data('wfebpg-log-error-poll',timer);
})(jQuery);

// Admin dashboard tabs, saved-template management, and client-side log filters.
(function($){
    var $root=$('.wfebpg-admin');
    if(!$root.length) return;

    function activateTab(tab, persist){
        var $button=$root.find('.wfebpg-tab-button[data-tab="'+tab+'"]');
        if(!$button.length) tab='generation';
        $root.find('.wfebpg-tab-button').removeClass('nav-tab-active');
        $root.find('.wfebpg-tab-button[data-tab="'+tab+'"]').addClass('nav-tab-active');
        $root.find('.wfebpg-tab-panel').removeClass('is-active');
        $root.find('.wfebpg-tab-panel[data-tab-panel="'+tab+'"]').addClass('is-active');
        if(persist){
            try{window.sessionStorage.setItem('wfebpg-active-tab',tab);}catch(e){}
        }
    }

    var initial=$root.attr('data-server-tab')||$root.find('.wfebpg-tab-button.nav-tab-active').data('tab')||'generation';
    if(initial==='generation')try{
        var remembered=sessionStorage.getItem('wfebpg-active-tab');
        if(remembered && $root.find('.wfebpg-tab-button[data-tab="'+remembered+'"]').length) initial=remembered;
    }catch(e){}
    activateTab(initial,false);

    $root.on('click','.wfebpg-tab-button',function(e){
        e.preventDefault();
        activateTab($(this).data('tab'),true);
    });

    // Keep POST-based validator submissions on the validator tab.
    $root.on('submit','.wfebpg-validator-form',function(){
        var $form=$(this);
        if(!$form.find('input[name="wfebpg_active_tab"]').length)$form.append('<input type="hidden" name="wfebpg_active_tab" value="validator">');
    });

    // Saved template management.
    function addSavedTemplateToSelectors(name){
        $('#wfebpg-saved-template, #wfebpg-image-map-template, #wfebpg-validator-saved-template').each(function(){
            var $select=$(this);
            if(!$select.find('option').filter(function(){return $(this).val()===name;}).length){
                $select.append($('<option>',{value:name,text:name}));
            }
        });
    }

    function addSavedTemplateManagerRow(name){
        var $list=$root.find('.wfebpg-template-manager-list');
        if(!$list.length){
            $list=$('<div class="wfebpg-template-manager-list"></div>');
            $root.find('.wfebpg-saved-template-manager .wfebpg-template-library-upload').after($list);
        }
        if($list.find('[data-template-name="'+name.replace(/"/g,'\\"')+'"]').length)return;
        var $row=$('<div class="wfebpg-template-manager-row"></div>').attr('data-template-name',name);
        var $info=$('<div class="wfebpg-template-manager-info"></div>');
        $info.append($('<strong class="wfebpg-template-manager-name"></strong>').text(name));
        var $actions=$('<div class="wfebpg-template-manager-actions"></div>');
        $actions.append($('<button type="button" class="button wfebpg-rename-template">Rename</button>').attr('data-template',name));
        $actions.append(' ');
        $actions.append($('<button type="button" class="button button-link-delete wfebpg-delete-template">Delete</button>').attr('data-template',name));
        $row.append($info,$actions);
        $list.append($row);
        $root.find('.wfebpg-saved-template-manager > p:contains("No saved templates yet.")').remove();
    }

    $root.on('click','#wfebpg-upload-library-template',function(){
        var $button=$(this), $input=$('#wfebpg-library-template-upload'), $status=$('#wfebpg-library-template-status');
        var file=$input[0]&&$input[0].files&&$input[0].files.length?$input[0].files[0]:null;
        if(!file){window.alert('Please choose an Elementor JSON template first.');return;}
        if(!/\.json$/i.test(file.name)){window.alert('Please choose a .json Elementor template file.');return;}
        var data=new FormData();
        data.append('action','wfebpg_save_template');
        data.append('nonce',(window.WFEBPG_Admin&&WFEBPG_Admin.jsonNonce)||'');
        data.append('template',file);
        $button.prop('disabled',true).text('Uploading...');
        $status.text('Saving template to the library...');
        $.ajax({url:(window.WFEBPG_Admin&&WFEBPG_Admin.ajaxUrl)||window.ajaxurl,type:'POST',data:data,processData:false,contentType:false,dataType:'json'})
            .done(function(r){
                if(!r||!r.success||!r.data||!r.data.name){$status.text('');window.alert(r&&r.data&&r.data.message?r.data.message:'Unable to save the template.');return;}
                var name=r.data.name;
                addSavedTemplateToSelectors(name);
                addSavedTemplateManagerRow(name);
                WFEBPG_SyncSharedTemplateSelection(name,true);
                $input.val('');
                $status.text('Saved: '+name);
            })
            .fail(function(xhr){$status.text('');window.alert(xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message?xhr.responseJSON.data.message:'Unable to save the template.');})
            .always(function(){$button.prop('disabled',false).text('Upload & Save Template');});
    });

    function refreshTemplateSelectors(oldName,newName,deleted){
        $('#wfebpg-saved-template, #wfebpg-image-map-template, #wfebpg-validator-saved-template').each(function(){
            var $select=$(this), $option=$select.find('option').filter(function(){return $(this).val()===oldName;});
            if(deleted){
                $option.remove();
            }else if($option.length){
                $option.val(newName).text(newName);
            }
        });
    }

    function showTemplateRenameEditor($row){
        if($row.hasClass('wfebpg-template-renaming'))return;
        var oldName=$row.attr('data-template-name')||'';
        var current=oldName.replace(/\.json$/i,'');
        var $info=$row.find('.wfebpg-template-manager-info');
        var $actions=$row.find('.wfebpg-template-manager-actions');
        var $name=$row.find('.wfebpg-template-manager-name');
        var $editor=$('<div class=\"wfebpg-template-rename-editor\"></div>');
        var $input=$('<input type=\"text\" class=\"regular-text wfebpg-template-rename-input\" />').val(current);
        var $save=$('<button type=\"button\" class=\"button button-primary wfebpg-save-template-rename\">Save Name</button>');
        var $cancel=$('<button type=\"button\" class=\"button wfebpg-cancel-template-rename\">Cancel</button>');
        $editor.append($input,' ',$save,' ',$cancel);
        $name.hide();
        $info.append($editor);
        $actions.find('.wfebpg-rename-template, .wfebpg-delete-template').hide();
        $row.addClass('wfebpg-template-renaming');
        $input.trigger('focus').select();
    }

    $root.on('click','.wfebpg-rename-template',function(){
        showTemplateRenameEditor($(this).closest('.wfebpg-template-manager-row'));
    });

    $root.on('click','.wfebpg-cancel-template-rename',function(){
        var $row=$(this).closest('.wfebpg-template-manager-row');
        $row.removeClass('wfebpg-template-renaming');
        $row.find('.wfebpg-template-rename-editor').remove();
        $row.find('.wfebpg-template-manager-name').show();
        $row.find('.wfebpg-rename-template, .wfebpg-delete-template').show();
    });

    $root.on('keydown','.wfebpg-template-rename-input',function(e){
        if(e.key==='Enter'){$(this).closest('.wfebpg-template-manager-row').find('.wfebpg-save-template-rename').trigger('click');}
        if(e.key==='Escape'){$(this).closest('.wfebpg-template-manager-row').find('.wfebpg-cancel-template-rename').trigger('click');}
    });

    $root.on('click','.wfebpg-save-template-rename',function(){
        var $button=$(this), $row=$button.closest('.wfebpg-template-manager-row'), oldName=$row.attr('data-template-name')||'';
        var newName=$.trim($row.find('.wfebpg-template-rename-input').val()||'');
        if(!newName){window.alert('Please enter a template name.');return;}
        if(!/\.json$/i.test(newName))newName+='.json';
        $button.prop('disabled',true).text('Saving...');
        $.post(WFEBPG_Admin.ajaxUrl,{action:'wfebpg_rename_template',nonce:WFEBPG_Admin.templateManagementNonce,template:oldName,new_name:newName})
            .done(function(r){
                if(!r.success){window.alert(r.data&&r.data.message?r.data.message:'Unable to rename template.');return;}
                var finalName=r.data.name;
                $row.attr('data-template-name',finalName).find('.wfebpg-template-manager-name').text(finalName).show();
                $row.find('.wfebpg-rename-template').attr('data-template',finalName);
                $row.find('.wfebpg-delete-template').attr('data-template',finalName);
                refreshTemplateSelectors(oldName,finalName,false);
                if($('#wfebpg-saved-template, #wfebpg-image-map-template, #wfebpg-validator-saved-template').filter(function(){return $(this).val()===oldName;}).length){
                    if(window.WFEBPG_SyncSharedTemplateSelection)window.WFEBPG_SyncSharedTemplateSelection(finalName,true);
                } else if(window.WFEBPG_UpdateTemplateImageStatus) window.WFEBPG_UpdateTemplateImageStatus(finalName);
                $row.removeClass('wfebpg-template-renaming').find('.wfebpg-template-rename-editor').remove();
                $row.find('.wfebpg-rename-template, .wfebpg-delete-template').show();
            })
            .fail(function(xhr){window.alert(xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message?xhr.responseJSON.data.message:'Unable to rename template.');})
            .always(function(){ $button.prop('disabled',false).text('Save Name'); });
    });

    $root.on('click','.wfebpg-delete-template',function(){
        var $button=$(this), name=$button.data('template')||'';
        if(!window.confirm('Delete the saved template "'+name+'"? This removes only the saved template file and its saved image mappings.'))return;
        $button.prop('disabled',true).text('Deleting...');
        $.post(WFEBPG_Admin.ajaxUrl,{action:'wfebpg_delete_template',nonce:WFEBPG_Admin.templateManagementNonce,template:name})
            .done(function(r){
                if(!r.success){window.alert(r.data&&r.data.message?r.data.message:'Unable to delete template.');return;}
                $button.closest('.wfebpg-template-manager-row').remove();
                refreshTemplateSelectors(name,'',true);
                if($('#wfebpg-saved-template, #wfebpg-image-map-template, #wfebpg-validator-saved-template').filter(function(){return $(this).val()===name;}).length){
                    if(window.WFEBPG_SyncSharedTemplateSelection)window.WFEBPG_SyncSharedTemplateSelection('',true);
                }
            })
            .fail(function(xhr){window.alert(xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message?xhr.responseJSON.data.message:'Unable to delete template.');})
            .always(function(){$button.prop('disabled',false).text('Delete');});
    });

    // Logs are filtered locally so changing filters never reloads the page.
    function filterLogs(){
        var level=($('#wfebpg-log-level').val()||'').toLowerCase();
        var query=($.trim($('#wfebpg-log-search').val()||'')).toLowerCase();
        var visible=0,total=$root.find('.wfebpg-log-table tbody tr[data-log-level]').length;
        $root.find('.wfebpg-log-table tbody tr[data-log-level]').each(function(){
            var $row=$(this), rowLevel=String($row.data('log-level')||'').toLowerCase(), text=String($row.attr('data-log-text')||'').toLowerCase();
            var show=(!level||rowLevel===level)&&(!query||text.indexOf(query)!==-1);
            $row.toggle(show); if(show)visible++;
        });
        $('#wfebpg-log-count').text(total ? ('Showing '+visible+' of '+total+' log entr'+(total===1?'y':'ies')+'.') : '');
    }
    $('#wfebpg-log-level').on('change',filterLogs);
    $('#wfebpg-log-search').on('input',filterLogs);
    filterLogs();
})(jQuery);
