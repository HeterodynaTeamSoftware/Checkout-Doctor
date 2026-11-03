'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const path = require('path');
const src = fs.readFileSync(path.join(__dirname, '../assets/js/checkout-observer.js'), 'utf8');
const handlers = {}, delays = [], outgoing = [];
const win = {
  location: {origin: 'https://shop.invalid', href:'https://shop.invalid/checkout/'},
  addEventListener: (name, callback) => {handlers[name]=callback;},
  setTimeout: (fn, millis) => {delays.push({fn,millis});},
  PerformanceObserver: class {
    constructor(callback) {this.callback=callback;}
    observe(opts) {
      assert.strictEqual(opts.type, 'resource');
      this.callback({getEntries: () => [
        {name: 'https://shop.invalid/wp-json/wc/store/v1/checkout?payment_token=TOP_SECRET', initiatorType:'fetch',duration:1888},
        {name: 'https://other.invalid/wp-json/wc/store/v1/checkout',initiatorType:'fetch',duration:22}
      ]});
    }
  }
};
const doc = {querySelector: (selector) => selector === '.wc-block-checkout' ? {nodeType:1} : null};
vm.runInNewContext(src, {
  window:win, document:doc,
  HCD_BROWSER_PROBE:{ajaxUrl:'https://shop.invalid/wp-admin/admin-ajax.php',nonce:'nonce'},
  URL,URLSearchParams,isFinite,PerformanceObserver:win.PerformanceObserver,
  fetch: async (_url, opts) => {outgoing.push(opts); return {ok:true};}
}, {filename:'checkout-observer.js'});
assert(handlers.error && handlers.unhandledrejection);
handlers.error({target:win, message:'ULTRA_SECRET_JS_STACK'});
handlers.unhandledrejection({reason:'ULTRA_SECRET_ORDER_DATA'});
for (const delay of delays) delay.fn();
const rows=outgoing.flatMap(opts => JSON.parse(new URLSearchParams(opts.body).get('events')));
assert(rows.some(x => x.kind==='render'&&x.context==='block'));
assert(rows.some(x => x.kind==='store_resource'&&x.bucket==='slow'));
assert(rows.some(x => x.kind==='js_error'));
assert(rows.some(x => x.kind==='promise_rejection'));
assert(!JSON.stringify(outgoing).includes('TOP_SECRET'));
assert(!JSON.stringify(outgoing).includes('ULTRA_SECRET'));
assert(rows.every(x => Object.keys(x).sort().join(',')==='bucket,context,kind'));
assert(outgoing.every(x => x.credentials === 'same-origin'));
console.log('PASS: browser runtime emits only fixed categories, blocks external URL data, no error contents');
