const vm = require('node:vm');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const source = fs.readFileSync(__dirname + '/../assets/js/store-api-observer.js','utf8');
let posts=[];const timers=[];
const env={
  document:{baseURI:'https://staging.example.test/shop/checkout/'},
  URL, URLSearchParams,
  window:{location:{origin:'https://staging.example.test'}, HCD_STORE_OBSERVER:{nonce:'nonce123',ajaxUrl:'https://staging.example.test/wp-admin/admin-ajax.php'},
    setTimeout:(fn)=>{timers.push(fn);return timers.length;},
    fetch:(input,opts)=>{const addr=String(input); if(addr.includes('admin-ajax.php'))posts.push(opts.body);return Promise.resolve({status:addr.includes('500')?500:addr.includes('403')?403:200});}}
};
vm.runInNewContext(source,env);
async function main(){
 const w=env.window;
 await w.fetch('https://external.test/wp-json/wc/store/v1/checkout?email=private');
 await w.fetch('https://staging.example.test/shop/wp-json/wc/store/v1/checkout?email=private','');
 await w.fetch('https://staging.example.test/shop/wp-json/wc/store/v1/checkout/500?payment_data=private',{method:'POST',body:'card=SECRET_CARD'});
 await w.fetch('https://staging.example.test/shop/?rest_route=%2Fwc%2Fstore%2Fv1%2Fcart%2F403&token=SECRET_TOKEN',{method:'GET'});
 assert.equal(posts.length,0,'Should wait for debounce');
 for (let i=0;i<6 && timers.length;i++) await timers.shift()();
 assert.equal(posts.length,1,'One bounded telemetry request');
 const params=new URLSearchParams(posts[0]);
 assert.equal(params.get('action'),'hcd_store_api_observation');
 const rows=JSON.parse(params.get('observations'));
 assert.equal(rows.length,2);
 assert.equal(rows[0].area,'checkout'); assert.equal(rows[0].status,500);
 assert.equal(rows[1].area,'cart'); assert.equal(rows[1].status,403);
 for(const secret of ['private','SECRET_CARD','SECRET_TOKEN','external.test'])assert(!posts[0].includes(secret),'Telemetry leaked '+secret);
 console.log('PASS: browser observes existing responses, denies external origins, strips URL/body, no proactive store requests');
}
main().catch(e=>{console.error(e);process.exitCode=1});
