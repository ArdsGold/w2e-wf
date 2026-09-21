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

    function syncFrameSelection(){
        if(!frame) return;
        var ids = getIds();
        frame.state().get('selection').reset(ids);
    }

    $enabled.on('change', function(){
        $panel.toggle(this.checked);
    });

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
            });
        }
        frame.open();
    });

    $('#wfebpg-clear-images').on('click', function(e){
        e.preventDefault();
        $ids.val('');
        renderSelection({});
        if(frame) frame.state().get('selection').reset();
    });

    renderSelection({});
});


// Saved JSON templates: choosing one makes the upload optional; uploading a file always takes precedence.
(function($){
    var $select = $('#wfebpg-saved-template');
    var $upload = $('#wfebpg-template-upload');
    if (!$select.length || !$upload.length) return;
    function syncTemplateChoice(){
        $upload.prop('required', !$select.val());
    }
    $select.on('change', syncTemplateChoice);
    syncTemplateChoice();
})(jQuery);
