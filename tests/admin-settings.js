// Run with: node tests/admin-settings.js (no browser or dependencies required).
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/admin.js'), 'utf8');
const handlers = {};
const requests = [];
let status = '';
const root = {length:1, on(event, selector, callback){ handlers[event + selector] = callback; }};
const checkbox = {checked:false, prop(name){return this[name];}, on(event, callback){this.change=callback;}};
const number = {value:'9', disabled:false, prop(name, value){this[name]=value;}};
function $(selector){
    if (typeof selector === 'object') return {val:()=>selector.value, trigger:event=>selector.trigger(event)};
    if (selector === '.wfebpg-admin') return root;
    if (selector === '#wfebpg-settings-status') return {text(value){if(value !== undefined) status=value; return status;}};
    if (selector === '#wfebpg-widgets-no-limit') return checkbox;
    if (selector === '#wfebpg-widgets-per-section') return number;
    throw new Error('Unexpected selector: ' + selector);
}
$.each = (values, callback)=>Object.entries(values).forEach(([key, value])=>callback(key, value));
$.extend = Object.assign;
$.param = JSON.stringify;
$.post = (url, data)=>{
    const request = {data, done(callback){this.success=callback;return this;},
        fail(callback){this.failure=callback;return this;}, always(callback){this.finish=callback;return this;}};
    requests.push(request);
    return request;
};
const lifecycle = {};
const beacons = [];
const window = {addEventListener(event, callback){lifecycle[event]=callback;}};
const config = {ajaxUrl:'/admin-ajax.php', settingsNonce:'nonce'};
window.WFEBPG_Admin = config;
vm.runInNewContext(source.slice(0, source.indexOf('jQuery(function($){')), {
    jQuery:$, window, WFEBPG_Admin:config, navigator:{sendBeacon(url, data){beacons.push(data);}}, Blob
});
function settle(request, success){
    if(success) request.success({success:true}); else request.failure();
    request.finish();
}
window.WFEBPG_SaveSettings({widgets_per_section:9});
window.WFEBPG_SaveSettings({widgets_no_limit:1});
window.WFEBPG_SaveSettings({widgets_no_limit:0});
lifecycle.pagehide();
assert.equal(beacons.length, 1, 'Navigation did not flush unsaved preferences');
beacons[0].text().then(text=>{
    const settings = JSON.parse(text).settings;
    assert.equal(settings.widgets_per_section, 9, 'Navigation omitted the in-flight number');
    assert.equal(settings.widgets_no_limit, 0, 'Navigation omitted the latest pending toggle');
});
assert.equal(requests.length, 1, 'Concurrent request bypassed the queue');
settle(requests[0], true);
assert.equal(requests.length, 2);
assert.equal(requests[1].data.settings.widgets_no_limit, 0, 'Latest toggle was not saved');
assert.equal(requests[1].data.settings.widgets_per_section, undefined, 'Unrelated preference resent');
settle(requests[1], false);
assert.match(status, /could not be saved/);
window.WFEBPG_SaveSettings({log_search:''});
assert.equal(requests[2].data.settings.widgets_no_limit, 0, 'Failed patch lost on retry');
assert.equal(requests[2].data.settings.log_search, '');
settle(requests[2], true);
assert.equal(status, 'Settings saved.');
handlers['changeinput[name="overwrite"]'].call({type:'checkbox', checked:false});
assert.equal(requests[3].data.settings.overwrite, 0, 'Unchecked state was not sent');
settle(requests[3], true);
const setup = source.slice(source.indexOf('    var $widgetsNoLimit'), source.indexOf('    var $enabled'));
vm.runInNewContext(setup, {$});
checkbox.checked = true;
checkbox.change();
assert.equal(number.disabled, true);
checkbox.checked = false;
checkbox.change();
assert.equal(number.disabled, false);
assert.equal(number.value, '9', 'Checkbox changed the remembered number');
console.log('Dashboard autosave passed: ordered patches, last change wins, failure retry, unchecked state, number restoration.');
