/* Heterodyna Store API staging observer. Never reads request/response bodies, headers or field values. */
(function () {
  'use strict';
  const cfg = window.HCD_STORE_OBSERVER;
  if (!cfg || typeof cfg.ajaxUrl !== 'string' || typeof cfg.nonce !== 'string' || typeof window.fetch !== 'function') return;
  if (window.__hcdStoreObserverInstalled) return;
  window.__hcdStoreObserverInstalled = true;
  const nativeFetch = window.fetch;
  const pending = [];
  let sending = false;
  let debounce = null;
  let observed = 0;

  function target(raw) {
    try {
      const url = new URL(raw, document.baseURI);
      if (url.origin !== window.location.origin) return null;
      // WordPress may be installed in a subdirectory; do not copy the URL into telemetry.
      const route = url.searchParams.get('rest_route') || '';
      const match = route.match(/^\/wc\/store\/v\d+\/(checkout|cart)(?:\/|$)/) ||
        url.pathname.match(/\/wp-json\/wc\/store\/v\d+\/(checkout|cart)(?:\/|$)/);
      return match ? match[1] : null;
    } catch (_) { return null; }
  }
  function requestInfo(input, options) {
    try {
      const raw = typeof input === 'string' || input instanceof URL ? String(input) : input && input.url;
      const area = target(raw);
      const method = String(options && options.method || input && input.method || 'GET').toUpperCase();
      if (!area || !['GET', 'POST', 'PUT', 'DELETE'].includes(method)) return null;
      return {area, method};
    } catch (_) { return null; }
  }
  async function transmit() {
    if (sending || !pending.length) return;
    sending = true;
    const batch = pending.splice(0, 8);
    const body = new URLSearchParams({action:'hcd_store_api_observation',nonce:cfg.nonce,observations:JSON.stringify(batch)});
    try {
      await nativeFetch.call(window, cfg.ajaxUrl, {method:'POST', credentials:'same-origin',
        headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString(),keepalive:true});
    } catch (_) { /* Do not alter checkout UX when telemetry is unavailable. */ }
    sending = false;
    if (pending.length) debounce = window.setTimeout(transmit, 1000);
  }
  function collect(info, status) {
    if (!info || !Number.isInteger(status) || status < 400 || status > 599 || observed >= 24) return;
    observed++;
    pending.push({area:info.area, method:info.method, status});
    if (!debounce) debounce = window.setTimeout(() => { debounce = null; transmit(); }, 250);
  }
  // Observe responses to Store API requests ALREADY initiated by WooCommerce or the test administrator.
  // Preserve original response/promise; no checkout/cart requests are initiated here.
  window.fetch = function () {
    const args = arguments;
    const info = requestInfo(args[0], args[1]);
    const result = nativeFetch.apply(this, args);
    if (!info || !result || typeof result.then !== 'function') return result;
    return result.then(response => {
      try { collect(info, response.status); } catch (_) { /* Non-interfering diagnostics. */ }
      return response;
    });
  };
}());
