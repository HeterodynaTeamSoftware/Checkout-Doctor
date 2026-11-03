/* Heterodyna Checkout Doctor v1.6: admin-only opt-in, categorical browser observations. */
(function () {
  'use strict';
  if (typeof HCD_BROWSER_PROBE === 'undefined') return;
  const cfg = HCD_BROWSER_PROBE;
  const queue = [];
  let submitted = 0;
  let pending = false;
  const validKinds = new Set(['render', 'js_error', 'promise_rejection', 'resource_error', 'store_resource']);
  function push(kind, context, bucket) {
    if (!validKinds.has(kind) || submitted >= 8 || queue.length >= 8) return;
    queue.push({kind, context, bucket});
  }
  window.addEventListener('error', function (event) {
    if (event && event.target && event.target !== window) {
      const tag = String(event.target.tagName || '').toLowerCase();
      if (tag === 'script' || tag === 'link') push('resource_error', 'other', 'unknown');
    } else {
      push('js_error', 'other', 'unknown');
    }
  }, true);
  window.addEventListener('unhandledrejection', function () {
    push('promise_rejection', 'other', 'unknown');
  });
  function observeResource(entry) {
    if (!entry || !entry.name || entry.initiatorType === 'img') return;
    try {
      const url = new URL(entry.name, window.location.href);
      if (url.origin !== window.location.origin) return;
      const route = url.searchParams.get('rest_route') || url.pathname;
      const matched = route.match(/\/wc\/store\/v[0-9]+\/(checkout|cart)(?:\/|$)/);
      if (!matched) return;
      // Only two known resource categories + latency bucket are exported.
      const duration = Number(entry.duration);
      push('store_resource', matched[1], !isFinite(duration) ? 'unknown' : duration > 1500 ? 'slow' : duration > 500 ? 'medium' : 'fast');
    } catch (_ignored) { /* Invalid URL: ignore. */ }
  }
  try {
    if (window.PerformanceObserver) {
      const observer = new PerformanceObserver(function (list) {
        list.getEntries().forEach(observeResource);
      });
      observer.observe({type: 'resource', buffered: true});
    }
  } catch (_ignored) { /* Fallback is JS error/DOM observation. */ }
  function send() {
    if (pending || submitted >= 8 || !queue.length) return;
    const batch = queue.splice(0, Math.min(queue.length, 8 - submitted));
    submitted += batch.length;
    pending = true;
    const body = new URLSearchParams({action: 'hcd_browser_probe', nonce: cfg.nonce, events: JSON.stringify(batch)});
    fetch(cfg.ajaxUrl, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}, body: body.toString(), keepalive: true})
      .catch(function () { /* Network failure should never affect checkout. */ })
      .finally(function () { pending = false; });
  }
  function rendered() {
    const block = !!document.querySelector('.wc-block-checkout');
    const classic = !!document.querySelector('form.checkout.woocommerce-checkout');
    push('render', block && classic ? 'mixed' : block ? 'block' : classic ? 'classic' : 'missing', 'none');
    send();
  }
  // Do not inspect input values, cookies, HTML, raw URLs, error strings, stacks or payment responses.
  window.setTimeout(rendered, 2200);
  window.setTimeout(send, 4200);
}());
