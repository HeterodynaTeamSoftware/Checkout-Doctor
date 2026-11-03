/* global HCD_PAYMENT_VISIBILITY */
/* One administrator-only, staging-only, bounded DOM snapshot. Never read input values. */
(function () {
  'use strict';
  const cfg = typeof HCD_PAYMENT_VISIBILITY === 'object' && HCD_PAYMENT_VISIBILITY;
  if (!cfg || !cfg.ajaxUrl || !cfg.nonce) return;
  let sent = false;
  const visible = (el) => !!(el && el.getClientRects && el.getClientRects().length);
  function observe() {
    const block = !!document.querySelector('.wc-block-checkout, .wp-block-woocommerce-checkout');
    const classic = !!document.querySelector('form.checkout, form.woocommerce-checkout');
    const mode = block && classic ? 'mixed' : block ? 'block' : classic ? 'classic' : 'unknown';
    // CSS selectors only. No access to name/email/address/card values, input.value or network traffic.
    const selectors = block ? [
      '.wc-block-checkout__payment-method input[type="radio"]',
      '.wc-block-components-payment-methods input[type="radio"]',
      '.wc-block-checkout__payment-method .wc-block-components-radio-control__input'
    ] : [
      'form.checkout #payment input[type="radio"]',
      'form.checkout #payment input[name="payment_method"]'
    ];
    const radioMatches = new Set();
    selectors.forEach(s => document.querySelectorAll(s).forEach(el => { if (visible(el)) radioMatches.add(el); }));
    // Some gateway themes visually hide native inputs: count the rendered option wrappers instead.
    const optionSelectors = block ? [
      '.wc-block-checkout__payment-method .wc-block-components-radio-control__option',
      '.wc-block-components-payment-methods .wc-block-components-radio-control__option'
    ] : ['form.checkout #payment li.wc_payment_method'];
    const optionMatches = new Set();
    optionSelectors.forEach(s => document.querySelectorAll(s).forEach(el => { if (visible(el)) optionMatches.add(el); }));
    return {mode, cart: ['empty','nonempty'].includes(cfg.cartState) ? cfg.cartState : 'unknown', visible: Math.min(20, Math.max(radioMatches.size, optionMatches.size))};
  }
  function submit() {
    if (sent) return;
    sent = true;
    const data = observe();
    const body = new URLSearchParams({action:'hcd_payment_visibility',nonce:cfg.nonce,snapshot:JSON.stringify(data)});
    fetch(cfg.ajaxUrl, {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},body:body.toString()})
      .catch(() => {});
  }
  if (document.readyState === 'complete') window.setTimeout(submit, 4500);
  else window.addEventListener('load', () => window.setTimeout(submit, 4500), {once:true});
}());
