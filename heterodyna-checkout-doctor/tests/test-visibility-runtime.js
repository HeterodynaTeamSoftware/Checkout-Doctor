const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const source = fs.readFileSync(__dirname + '/../assets/js/payment-visibility.js', 'utf8');

async function scenario(config, dom, expected) {
  const requests = [];
  const seen = [];
  const elements = dom.inputs.map((visible) => ({getClientRects: () => visible ? [1] : []}));
  const context = {
    HCD_PAYMENT_VISIBILITY: config,
    URLSearchParams,
    document: {
      readyState:'complete',
      querySelector: (sel) => dom.block && sel.includes('block') ? {} : (!dom.block && sel === 'form.checkout, form.woocommerce-checkout' ? {} : null),
      querySelectorAll: (sel) => { seen.push(sel); return sel.includes('input') ? elements : []; }
    },
    window: {setTimeout: (fn, ms) => {assert.strictEqual(ms,4500);fn();}, addEventListener:()=>{}},
    fetch: (url,options) => { requests.push({url,options}); return Promise.resolve({ok:true}); },
  };
  vm.runInNewContext(source, context);
  assert.strictEqual(requests.length, expected === null ? 0 : 1);
  if (expected !== null) {
    const body = new URLSearchParams(requests[0].options.body);
    assert.strictEqual(body.get('action'), 'hcd_payment_visibility');
    assert.strictEqual(requests[0].options.credentials, 'same-origin');
    const snapshot = JSON.parse(body.get('snapshot'));
    assert.strictEqual(snapshot.mode, expected.mode);
    assert.strictEqual(snapshot.cart, expected.cart);
    assert.strictEqual(snapshot.visible, expected.visible);
    assert.deepStrictEqual(Object.keys(snapshot).sort(), ['cart','mode','visible']);
    assert(!requests[0].options.body.includes('card_number') && !requests[0].options.body.includes('customer'));
  }
}
(async function(){
  await scenario({ajaxUrl:'/admin-ajax.php',nonce:'abc',cartState:'nonempty'}, {block:false,inputs:[true,false]}, {mode:'classic',cart:'nonempty',visible:1});
  await scenario({ajaxUrl:'/admin-ajax.php',nonce:'abc',cartState:'unknown'}, {block:true,inputs:[true,true]}, {mode:'block',cart:'unknown',visible:2});
  await scenario(null, {block:true,inputs:[true]}, null);
  const codeOnly = source.replace(/\/\*[\s\S]*?\*\/|\/\/[^\n]*/g, '');
  if (/\.value\b|outerHTML|innerHTML|fetch\s*\(\s*['"]https?:/i.test(codeOnly)) throw new Error('Unsafe browser collection');
  console.log('PASS: JS stage browser snapshot, no raw values, bounded counts, single post');
})().catch(e=>{console.error(e);process.exit(1);});
