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
    var $mode = $('input[name="mode"]');
    var $autoTemplates = $('#wfebpg-auto-templates');
    var $manualTemplate = $('#wfebpg-manual-template');
    var $autoUploads = $('#wfebpg-auto-uploads');
    var $manualUpload = $('#wfebpg-manual-upload');
    var $select = $('#wfebpg-saved-template');
    var $json = $('#wfebpg-template-upload');
    var $uniqueSelect = $('#wfebpg-unique-template');
    var $genericSelect = $('#wfebpg-generic-template');
    var $uniqueJson = $('#wfebpg-unique-template-upload');
    var $genericJson = $('#wfebpg-generic-template-upload');
    var $choice = $('#wfebpg-saved-template-choice');
    var $summary = $('#wfebpg-detection-summary');
    if (!$form.length || !$docx.length || !$batch.length) return;

    function selectedMode(){ return $mode.filter(':checked').val() || 'auto'; }

    function syncTemplateUI(){
        var auto = selectedMode() === 'auto';
        $autoTemplates.toggle(auto);
        $manualTemplate.toggle(!auto);
        $autoUploads.toggle(auto);
        $manualUpload.toggle(!auto);
        if ($json.length) $json.prop('required', !auto && !$select.val());
        if ($uniqueJson.length) $uniqueJson.prop('required', false);
        if ($genericJson.length) $genericJson.prop('required', false);
        if ($choice.length && $select.length) $choice.val($select.val() || '');
    }

    var stopped = false;
    var activeRequest = null;
    var detectionCounts = {unique:0,generic:0};

    function setBusy($button, text){ $button.prop('disabled', true).text(text); }

    function renderDetectionSummary(total){
        if (!$summary.length) return;
        if (!total) {
            $summary.text('Upload your DOCX files and the plugin will detect the page type for each file before queueing.');
            return;
        }
        $summary.text('Detected so far: ' + detectionCounts.unique + ' Unique, ' + detectionCounts.generic + ' Generic (' + total + ' file' + (total === 1 ? '' : 's') + ').');
    }

    function setQueueingState(active, text){
        var $submit = $form.find('button[type="submit"]');
        var $stop = $('#wfebpg-stop-queueing');
        if (active) {
            $submit.prop('disabled', true).text(text || 'Queueing...');
            $stop.prop('disabled', false).show();
        } else {
            $stop.prop('disabled', true).hide();
            $submit.prop('disabled', false).text('Queue Pages');
        }
    }

    function cleanupBatch(batch, callback){
        if (!batch || !(window.WFEBPG_Admin && WFEBPG_Admin.ajaxUrl)) { if (callback) callback(); return; }
        $.post(WFEBPG_Admin.ajaxUrl, {action:'wfebpg_cancel_docx_batch', nonce:WFEBPG_Admin.docxNonce, batch:batch}).always(function(){ if (callback) callback(); });
    }

    function uploadDocxFiles(files, batch, done, fail){
        var index = 0;
        stopped = false;
        detectionCounts = {unique:0,generic:0};
        renderDetectionSummary(files.length);
        function stopIfRequested(currentBatch){
            if (!stopped) return false;
            if (activeRequest) { activeRequest.abort(); activeRequest = null; }
            cleanupBatch(currentBatch);
            return true;
        }
        function next(currentBatch){
            if (index >= files.length) return done(currentBatch, detectionCounts);
            var file = files[index]; index++;
            if (stopIfRequested(currentBatch)) return;
            setQueueingState(true, 'Uploading DOCX ' + index + '/' + files.length + '...');
            var data = new FormData();
            data.append('action','wfebpg_upload_docx');
            data.append('nonce',(window.WFEBPG_Admin && WFEBPG_Admin.docxNonce) || '');
            data.append('batch',currentBatch || '');
            data.append('docx',file);
            activeRequest=$.ajax({url:(window.WFEBPG_Admin && WFEBPG_Admin.ajaxUrl)||window.ajaxurl,type:'POST',data:data,processData:false,contentType:false,dataType:'json'})
            .done(function(response){
                activeRequest=null;
                if (stopped) { cleanupBatch(response && response.data ? response.data.batch : currentBatch); return; }
                if (!response || !response.success || !response.data || !response.data.batch) { fail(response && response.data && response.data.message ? response.data.message : 'Unable to upload a DOCX file.'); return; }
                var kind=response.data.kind==='unique'?'unique':'generic';
                detectionCounts[kind]++;
                renderDetectionSummary(index);
                next(response.data.batch);
            }).fail(function(xhr,status){
                activeRequest=null;
                if (stopped || status==='abort') return;
                fail(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Unable to upload a DOCX file.');
            });
        }
        next(batch || '');
    }

    function uploadJson(file, done, fail){
        var data=new FormData();
        data.append('action','wfebpg_save_template');
        data.append('nonce',(window.WFEBPG_Admin && WFEBPG_Admin.jsonNonce)||'');
        data.append('template',file);
        $.ajax({url:(window.WFEBPG_Admin && WFEBPG_Admin.ajaxUrl)||window.ajaxurl,type:'POST',data:data,processData:false,contentType:false,dataType:'json'})
        .done(function(response){
            if(!response||!response.success||!response.data||!response.data.name){fail(response&&response.data&&response.data.message?response.data.message:'Unable to save the JSON template.');return;}
            done(response.data.name);
        }).fail(function(xhr){fail(xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message?xhr.responseJSON.data.message:'Unable to save the JSON template.');});
    }

    function uploadTemplatesSequentially(files, callback){
        var index=0, names={};
        function next(){
            if(index>=files.length) return callback(null,names);
            var item=files[index++];
            if(!item) return next();
            setQueueingState(true,'Saving JSON template...');
            uploadJson(item.file,function(name){ names[item.kind]=name; next(); },function(message){ callback(message,names); });
        }
        next();
    }

    $('#wfebpg-stop-queueing').on('click',function(e){
        e.preventDefault();
        if(!$(this).prop('disabled')){
            stopped=true;
            if(activeRequest){activeRequest.abort();activeRequest=null;}
            var batch=$batch.val()||'';
            cleanupBatch(batch,function(){
                $batch.val('');
                $form.removeData('wfebpg-upload-ready');
                setQueueingState(false);
                window.alert('Queueing stopped. No pages were queued from this submission.');
            });
        }
    });

    $mode.on('change',syncTemplateUI);
    $select.on('change',syncTemplateUI);

    $form.on('submit',function(e){
        var form=this;
        if($(form).data('wfebpg-upload-ready')) return;
        e.preventDefault();
        var files=Array.prototype.slice.call($docx[0].files||[]);
        if(!files.length){window.alert('Please select at least one DOCX file.');return;}
        stopped=false;
        $batch.val('');
        setQueueingState(true,'Starting queue...');

        uploadDocxFiles(files,'',function(batch,counts){
            if(stopped){cleanupBatch(batch);setQueueingState(false);return;}
            $batch.val(batch);
            $docx.prop('required',false).val('');
            var mode=selectedMode();
            var uploads=[];
            if(mode==='auto'){
                if($uniqueJson.length && $uniqueJson[0].files[0]) uploads.push({kind:'unique',file:$uniqueJson[0].files[0]});
                if($genericJson.length && $genericJson[0].files[0]) uploads.push({kind:'generic',file:$genericJson[0].files[0]});
                uploadTemplatesSequentially(uploads,function(error,names){
                    if(error){window.alert(error);setQueueingState(false);return;}
                    if(names.unique){$uniqueSelect.append($('<option>',{value:names.unique,text:names.unique})).val(names.unique);}
                    if(names.generic){$genericSelect.append($('<option>',{value:names.generic,text:names.generic})).val(names.generic);}
                    var missing=[];
                    if(counts.unique && !$uniqueSelect.val()) missing.push('Unique');
                    if(counts.generic && !$genericSelect.val()) missing.push('Generic');
                    if(missing.length){window.alert('No saved '+missing.join(' or ')+' Elementor template is selected for the detected DOCX files.');setQueueingState(false);return;}
                    $(form).data('wfebpg-upload-ready',true); form.submit();
                });
            }else{
                var jsonFile=$json.length&&$json[0].files?$json[0].files[0]:null;
                var savedTemplate=$select.length?$select.val():'';
                if(!jsonFile&&!savedTemplate){window.alert('Please select a saved JSON template or upload a new one.');setQueueingState(false);return;}
                if(jsonFile){
                    uploadJson(jsonFile,function(name){$select.append($('<option>',{value:name,text:name})).val(name);$json.val('');syncTemplateUI();$(form).data('wfebpg-upload-ready',true);form.submit();},function(message){window.alert(message);setQueueingState(false);});
                }else{
                    syncTemplateUI();$(form).data('wfebpg-upload-ready',true);form.submit();
                }
            }
        },function(message){window.alert(message);setQueueingState(false);});
    });

    syncTemplateUI();
})(jQuery);
