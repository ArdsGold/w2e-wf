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
// This avoids PHP's max_file_uploads limit when many files are selected at once.
(function($){
    var $form = $('#wfebpg-docx-upload').closest('form');
    var $docx = $('#wfebpg-docx-upload');
    var $batch = $('#wfebpg-docx-batch');
    var $select = $('#wfebpg-saved-template');
    var $json = $('#wfebpg-template-upload');
    var $choice = $('#wfebpg-saved-template-choice');
    if (!$form.length || !$docx.length || !$batch.length || !$select.length || !$json.length) return;

    function syncTemplateChoice(){
        var value = $select.val() || '';
        $json.prop('required', !value);
        if ($choice.length) $choice.val(value);
    }

    var stopped = false;
    var activeRequest = null;

    function setBusy($button, text){
        $button.prop('disabled', true).text(text);
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
        if (!batch || !(window.WFEBPG_Admin && WFEBPG_Admin.ajaxUrl)) {
            if (callback) callback();
            return;
        }
        $.post(WFEBPG_Admin.ajaxUrl, {
            action: 'wfebpg_cancel_docx_batch',
            nonce: WFEBPG_Admin.docxNonce,
            batch: batch
        }).always(function(){
            if (callback) callback();
        });
    }

    function uploadDocxFiles(files, batch, done, fail){
        var index = 0;
        stopped = false;
        function stopIfRequested(currentBatch){
            if (!stopped) return false;
            if (activeRequest) { activeRequest.abort(); activeRequest = null; }
            cleanupBatch(currentBatch);
            return true;
        }
        
        function next(currentBatch){
            if (index >= files.length) return done(currentBatch);
            var file = files[index];
            index++;
            if (stopIfRequested(currentBatch)) return;
            setQueueingState(true, 'Uploading DOCX ' + index + '/' + files.length + '...');
            var data = new FormData();
            data.append('action', 'wfebpg_upload_docx');
            data.append('nonce', (window.WFEBPG_Admin && WFEBPG_Admin.docxNonce) || '');
            data.append('batch', currentBatch || '');
            data.append('docx', file);
            activeRequest = $.ajax({
                url: (window.WFEBPG_Admin && WFEBPG_Admin.ajaxUrl) || window.ajaxurl,
                type: 'POST',
                data: data,
                processData: false,
                contentType: false,
                dataType: 'json'
            }).done(function(response){
                activeRequest = null;
                if (stopped) { cleanupBatch(response && response.data ? response.data.batch : currentBatch); return; }
                if (!response || !response.success || !response.data || !response.data.batch) {
                    fail(response && response.data && response.data.message ? response.data.message : 'Unable to upload a DOCX file.');
                    return;
                }
                next(response.data.batch);
            }).fail(function(xhr, status){
                activeRequest = null;
                if (stopped || status === 'abort') return;
                fail(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Unable to upload a DOCX file.');
            });
        }
        next(batch || '');
    }

    function uploadJson(file, done, fail){
        setBusy($form.find('button[type="submit"]'), 'Uploading JSON template...');
        var data = new FormData();
        data.append('action', 'wfebpg_save_template');
        data.append('nonce', (window.WFEBPG_Admin && WFEBPG_Admin.jsonNonce) || '');
        data.append('template', file);
        $.ajax({
            url: (window.WFEBPG_Admin && WFEBPG_Admin.ajaxUrl) || window.ajaxurl,
            type: 'POST',
            data: data,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(response){
            if (!response || !response.success || !response.data || !response.data.name) {
                fail(response && response.data && response.data.message ? response.data.message : 'Unable to save the JSON template.');
                return;
            }
            done(response.data.name);
        }).fail(function(xhr){
            fail(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Unable to save the JSON template.');
        });
    }

    $('#wfebpg-stop-queueing').on('click', function(e){
        e.preventDefault();
        if (!$(this).prop('disabled')) {
            stopped = true;
            if (activeRequest) { activeRequest.abort(); activeRequest = null; }
            var batch = $batch.val() || '';
            cleanupBatch(batch, function(){
                $batch.val('');
                $form.removeData('wfebpg-upload-ready');
                setQueueingState(false);
                window.alert('Queueing stopped. No pages were queued from this submission.');
            });
        }
    });

    $select.on('change', syncTemplateChoice);
    $form.on('submit', function(e){
        var form = this;
        if ($(form).data('wfebpg-upload-ready')) {
            syncTemplateChoice();
            return;
        }
        e.preventDefault();

        var files = Array.prototype.slice.call($docx[0].files || []);
        var jsonFile = $json[0].files && $json[0].files[0];
        var savedTemplate = $select.val() || '';
        var $button = $form.find('button[type="submit"]');

        if (!files.length) {
            window.alert('Please select at least one DOCX file.');
            return;
        }
        if (!jsonFile && !savedTemplate) {
            window.alert('Please select a saved JSON template or upload a new one.');
            return;
        }

        stopped = false;
        $batch.val('');
        setQueueingState(true, 'Starting queue...');
        uploadDocxFiles(files, '', function(batch){
            if (stopped) { cleanupBatch(batch); setQueueingState(false); return; }
            $batch.val(batch);
            $docx.prop('required', false).val('');

            function finishWithTemplate(name){
                $select.val(name);
                if (!$select.val()) $select.append($('<option>', {value:name, text:name})).val(name);
                $json.val('');
                syncTemplateChoice();
                $(form).data('wfebpg-upload-ready', true);
                form.submit();
            }

            if (jsonFile) {
                uploadJson(jsonFile, finishWithTemplate, function(message){
                    window.alert(message);
                    setQueueingState(false);
                });
            } else {
                finishWithTemplate(savedTemplate);
            }
        }, function(message){
            window.alert(message);
            setQueueingState(false);
        });
    });

    syncTemplateChoice();
})(jQuery);
