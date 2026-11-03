/* global HCD_CONFIG */
(function () {
  'use strict';
  const app = document.getElementById('hcd-app');
  if (!app || typeof HCD_CONFIG === 'undefined') return;
  let report = HCD_CONFIG.lastScan && Array.isArray(HCD_CONFIG.lastScan.checks) ? HCD_CONFIG.lastScan : null;
  let filter = 'all';
  let moduleFilter = 'all';
  const q = (selector) => app.querySelector(selector);
  const make = (tag, cls, text) => {
    const element = document.createElement(tag);
    if (cls) element.className = cls;
    if (text !== undefined && text !== null) element.textContent = String(text);
    return element;
  };
  const labels = {critical: 'KRYTYCZNE', warning: 'DO SPRAWDZENIA', pass: 'POTWIERDZONE OK', info: 'INFORMACYJNE'};
  const categories = {core:'Checkout / konfiguracja',classic:'Checkout klasyczny',blocks:'Checkout Block / Store API',orders:'Zamówienia / HPOS',payments:'Płatności',shipping:'Wysyłka',conflicts:'Konflikty / motyw',logs:'Logi techniczne',readiness:'Gotowość WooCommerce',compatibility:'Zgodność i wydanie',integration:'Testy integracyjne',browser:'Przeglądarka JS',block_compat:'Zgodność Checkout Block',scenarios:'Scenariusze koszyka',visibility:'Widoczność płatności',store_api:'Błędy Store API',correlation:'Korelacja dowodów',events:'Monitoring'};
  const names = {classic_validation: 'Walidacja klasyczna', store_api_error: 'Błąd Store API', order_created: 'Utworzenie zamówienia', order_failed: 'Status Failed', payment_complete: 'Płatność potwierdzona'};

  function switchTab(tab) {
    const wanted = ['dashboard', 'diagnostics', 'checkout-core', 'payments', 'shipping', 'conflicts', 'logs', 'repairs', 'readiness', 'compatibility', 'integration', 'block-compat', 'scenarios', 'visibility', 'store-api', 'correlation', 'browser', 'events', 'settings'].includes(tab) ? tab : 'dashboard';
    app.querySelectorAll('[data-hcd-page]').forEach((page) => { page.hidden = page.dataset.hcdPage !== wanted; });
    app.querySelectorAll('[data-hcd-tab]').forEach((button) => {
      button.classList.toggle('is-active', button.dataset.hcdTab === wanted);
      button.setAttribute('aria-current', button.dataset.hcdTab === wanted ? 'page' : 'false');
    });
    if (history.replaceState) history.replaceState(null, '', location.pathname + location.search.split('#')[0] + '#' + wanted);
  }
  app.querySelectorAll('[data-hcd-tab]').forEach(button => button.addEventListener('click', () => switchTab(button.dataset.hcdTab)));
  const urlTab = new URLSearchParams(window.location.search).get('tab');
  switchTab(urlTab || window.location.hash.replace('#', '') || 'dashboard');

  function updateKpis() {
    const s = report ? report.summary : {critical: '—', warning: '—', pass: '—'};
    const cnt = HCD_CONFIG.counts || {};
    const total = ['classic_validation', 'store_api_error', 'order_created', 'order_failed', 'payment_complete'].reduce((sum, type) => sum + Number(cnt[type] || 0), 0);
    const values = [s.critical, s.warning, s.pass, HCD_CONFIG.monitoring ? total : 'OFF'];
    const styles = ['hcd-critical', 'hcd-warning', 'hcd-pass', ''];
    app.querySelectorAll('.hcd-kpi').forEach((box, i) => {
      box.classList.remove('hcd-critical', 'hcd-warning', 'hcd-pass');
      if (styles[i]) box.classList.add(styles[i]);
      box.querySelector('strong').textContent = String(values[i] === undefined ? '—' : values[i]);
    });
  }

  function updateCoreStrip() {
    const strip = q('#hcd-core-strip');
    const cells = strip ? Array.from(strip.querySelectorAll('.hcd-core-mode')) : [];
    if (cells.length !== 4) return;
    const meta = report && report.meta ? report.meta : {};
    const mode = value => value === 'blocks' ? 'BLOKI' : value === 'classic' ? 'KLASYCZNY' : value === 'custom_or_unknown' ? 'NIEZNANY' : '—';
    const values = [mode(meta.checkout_mode),mode(meta.cart_mode),
      meta.store_api_checkout_route_registered === true && meta.store_api_cart_route_registered === true ? '2 / 2' : (meta.checkout_mode === 'blocks' ? 'SPRAWDŹ' : 'N/D'),
      meta.hpos_enabled === true ? 'WŁĄCZONY' : meta.hpos_enabled === false ? 'LEGACY' : 'N/D'];
    cells.forEach((cell, index) => {
      const strong = cell.querySelector('strong');
      if (strong) strong.textContent = values[index];
    });
  }

  function updateCommerceStrip() {
    const meta = report && report.meta ? report.meta : {};
    const values = {
      gateways: meta.gateways_enabled,
      zones: meta.shipping_zones_count,
      methods: meta.shipping_methods_enabled
    };
    Object.keys(values).forEach(key => {
      const elem = q('[data-hcd-commerce="' + key + '"]');
      if (elem) elem.textContent = (values[key] === null || values[key] === undefined) ? '—' : String(values[key]);
    });
  }

  function updateCommerceFindings() {
    const meta = report && report.meta ? report.meta : {};
    const setValue = (id, value) => {
      const elem = q(id);
      if (elem) elem.textContent = value === undefined || value === null ? '—' : String(value);
    };
    setValue('#hcd-payment-registered', meta.payment_gateways_total);
    setValue('#hcd-payment-enabled', meta.gateways_enabled);
    setValue('#hcd-shipping-zones', meta.shipping_zones_count);
    setValue('#hcd-shipping-enabled', meta.shipping_methods_enabled);
    setValue('#hcd-shipping-issues', Array.isArray(meta.shipping_zones_without_methods) ? meta.shipping_zones_without_methods.length : null);
    ['payments', 'shipping'].forEach(category => {
      const container = q('#hcd-' + category + '-findings');
      if (!container) return;
      container.replaceChildren();
      if (!report) {
        container.append(make('div', 'hcd-empty', 'Brak danych. Uruchom diagnostykę.'));
        setValue('#hcd-payment-checks', null);
        return;
      }
      const order = {critical: 0, warning: 1, info: 2, pass: 3};
      const items = report.checks.filter(item => item.category === category).sort((a,b) => order[a.status] - order[b.status]);
      if (category === 'payments') setValue('#hcd-payment-checks', items.length);
      if (!items.length) container.append(make('div','hcd-empty','Brak ustaleń dla modułu.'));
      items.forEach(item => container.append(finding(item)));
    });
  }

  function updateConflictLogFindings() {
    const meta = report && report.meta ? report.meta : {};
    const stats = meta.technical_logs || {};
    const counters = {
      '#hcd-conflict-overrides': meta.theme_override_count,
      '#hcd-conflict-outdated': meta.theme_override_older_count,
      '#hcd-conflict-hooks': meta.checkout_hooks_with_multiple_plugin_owners,
      '#hcd-log-files': stats.files_inspected,
      '#hcd-log-critical': stats.critical,
      '#hcd-log-error': stats.error
    };
    Object.entries(counters).forEach(([id, value]) => {
      const elem = q(id);
      if (elem) elem.textContent = value === undefined ? '—' : String(value);
    });
    ['conflicts', 'logs'].forEach(category => {
      const container = q('#hcd-' + category + '-findings');
      if (!container) return;
      container.replaceChildren();
      if (!report) { container.append(make('div', 'hcd-empty', 'Brak danych. Uruchom diagnostykę.')); return; }
      const list = report.checks.filter(item => item.category === category);
      if (!list.length) container.append(make('div', 'hcd-empty', 'Nie ma ustaleń dla tego modułu.'));
      const priority = {critical:0, warning:1, info:2, pass:3};
      list.sort((a,b) => priority[a.status] - priority[b.status]).forEach(item => container.append(finding(item)));
    });
    const table = q('#hcd-theme-overrides');
    if (!table) return;
    table.replaceChildren();
    const entries = Array.isArray(meta.theme_overrides) ? meta.theme_overrides : [];
    if (!entries.length) {
      const tr = make('tr'); const cell = make('td', '', 'Brak wykrytych nadpisań szablonów.');
      cell.colSpan = 5; tr.append(cell); table.append(tr);
    }
    entries.forEach(entry => {
      const tr = make('tr');
      [entry.location, entry.template, entry.theme_version, entry.woocommerce_version, entry.version_relation]
        .forEach(value => tr.append(make('td', '', value || '—')));
      table.append(tr);
    });
    const owners = q('#hcd-hook-owners');
    if (!owners) return;
    owners.replaceChildren();
    const hooks = meta.hook_inspection && typeof meta.hook_inspection === 'object' ? meta.hook_inspection : {};
    if (!Object.keys(hooks).length) {
      const tr = make('tr'), td = make('td', '', 'Brak danych o hookach.');
      td.colSpan = 3; tr.append(td); owners.append(tr);
    }
    Object.entries(hooks).forEach(([name, info]) => {
      const tr = make('tr');
      const plugins = Array.isArray(info.plugin_owners) && info.plugin_owners.length ? info.plugin_owners.join(', ') : '—';
      [name, info.callbacks_count, plugins].forEach(value => tr.append(make('td', '', value)));
      owners.append(tr);
    });
  }

  function updateOverview() {
    const container = q('#hcd-overview');
    container.replaceChildren();
    if (!report) {
      container.className = 'hcd-empty';
      container.textContent = 'Brak raportu. Uruchom diagnostykę, aby sprawdzić stan konfiguracji sklepu.';
      q('#hcd-scan-date').textContent = 'Brak skanu';
      return;
    }
    container.className = '';
    updateCoreStrip();
    updateCommerceStrip();
    const date = new Date(report.generated_at);
    q('#hcd-scan-date').textContent = Number.isNaN(date.getTime()) ? 'Ostatni skan' : date.toLocaleString('pl-PL');
    const ordered = report.checks.filter(x => x.status === 'critical' || x.status === 'warning');
    const selected = ordered.length ? ordered.slice(0, 5) : report.checks.slice(0, 5);
    selected.forEach((item) => {
      const line = make('div', 'hcd-status-line');
      line.append(make('b', '', item.title));
      const status = make('span', 'hcd-state', labels[item.status] || 'INFORMACJA');
      status.dataset.status = item.status;
      line.append(status);
      container.append(line);
    });
    const button = make('button', 'hcd-btn', 'Otwórz pełny raport');
    button.type = 'button';
    button.style.marginTop = '20px';
    button.addEventListener('click', () => switchTab('diagnostics'));
    container.append(button);
  }

  function finding(item) {
    const entry = make('article', 'hcd-finding');
    entry.dataset.status = item.status;
    const head = make('div', 'hcd-finding-head');
    const intro = make('div');
    intro.append(make('h3', '', item.title));
    intro.append(make('div', 'hcd-finding-meta', (categories[item.category] || 'Konfiguracja') + '  ·  CHECK: ' + item.id + '   ·   ' + (item.confidence === 'confirmed' ? 'POTWIERDZONE DANYMI' : 'SYGNAŁ / WYMAGA WERYFIKACJI')));
    const state = make('span', 'hcd-state', labels[item.status] || 'INFORMACJA');
    state.dataset.status = item.status;
    head.append(intro, state);
    entry.append(head);
    const grid = make('div', 'hcd-finding-grid');
    [['DOWÓD / OBSERWACJA', item.evidence], ['ZNACZENIE', item.meaning], ['ZALECENIE', item.recommendation]].forEach(([title, value]) => {
      const card = make('div');
      card.append(make('b', '', title), make('p', '', value));
      grid.append(card);
    });
    entry.append(grid);
    return entry;
  }

  function updateChecks() {
    const container = q('#hcd-checks');
    container.replaceChildren();
    if (!report) {
      container.append(make('div', 'hcd-empty', 'Brak raportu. Wykonaj pierwszy skan na pulpicie.'));
      q('#hcd-check-count').textContent = '';
      return;
    }
    const filtered = report.checks.filter(item => (filter === 'all' || item.status === filter) && (moduleFilter === 'all' || (item.category || 'core') === moduleFilter));
    const order = {critical: 0, warning: 1, info: 2, pass: 3};
    filtered.sort((a, b) => (order[a.status] || 0) - (order[b.status] || 0));
    q('#hcd-check-count').textContent = 'Wyniki: ' + filtered.length + ' / ' + report.checks.length;
    if (!filtered.length) container.append(make('div', 'hcd-empty', 'Brak wyników w wybranej kategorii.'));
    filtered.forEach(item => container.append(finding(item)));
  }

  function updateCoreFindings() {
    const container = q('#hcd-core-findings');
    if (!container) return;
    container.replaceChildren();
    if (!report || !Array.isArray(report.checks)) {
      container.append(make('div','hcd-empty','Brak wyników. Uruchom diagnostykę.'));
      return;
    }
    const coreIds = new Set(['core','classic','blocks','orders']);
    const list = report.checks.filter(item => coreIds.has(item.category || 'core'));
    const order = {critical: 0, warning: 1, info: 2, pass: 3};
    list.sort((a,b) => order[a.status] - order[b.status]);
    if (!list.length) container.append(make('div','hcd-empty','Brak danych dla silnika checkout.'));
    list.forEach(item => container.append(finding(item)));
  }

  function updateRepairs() {
    const plan = q('#hcd-repair-list');
    const correlations = q('#hcd-correlations');
    if (!plan || !correlations) return;
    plan.replaceChildren();
    correlations.replaceChildren();
    const entries = report && Array.isArray(report.repair_plan) ? report.repair_plan : [];
    const summary = report && report.repair_summary ? report.repair_summary : {};
    [['#hcd-repair-p1','p1'],['#hcd-repair-p2','p2'],['#hcd-repair-p3','p3']].forEach(([selector,key]) => {
      const el = q(selector);
      if (el) el.textContent = report && summary[key] !== undefined ? String(summary[key]) : '—';
    });
    q('#hcd-repair-count').textContent = report ? ('Działań: ' + entries.length + ' · wymagają weryfikacji: ' + (summary.signals_to_verify || 0)) : 'Brak skanu';
    if (!report) {
      plan.append(make('div','hcd-empty','Wykonaj skan, aby zbudować plan napraw.'));
      correlations.append(make('div','hcd-empty','Brak danych korelacyjnych.'));
      return;
    }
    if (!entries.length) plan.append(make('div','hcd-empty','Brak krytycznych ustaleń i ostrzeżeń. To nie gwarantuje bezawaryjnego checkoutu.'));
    entries.forEach(item => {
      const row = make('article','hcd-repair-entry');
      row.dataset.priority = item.priority;
      const head = make('div','hcd-finding-head');
      const intro = make('div');
      intro.append(make('h3','',item.title), make('div','hcd-finding-meta', (categories[item.category] || item.category) + ' · ' + item.check_id + ' · ' + (item.confidence === 'confirmed' ? 'FAKT KONFIGURACYJNY' : 'SYGNAŁ DO WERYFIKACJI')));
      head.append(intro,make('span','hcd-state hcd-priority',item.priority));
      const body = make('div','hcd-finding-grid');
      [['DOWÓD / FAKT', item.evidence], ['CO ZROBIĆ', item.action], ['JAK POTWIERDZIĆ', item.verify]].forEach(([heading,detail]) => {
        const section = make('div');
        section.append(make('b','',heading),make('p','',detail));
        body.append(section);
      });
      row.append(head,body,make('p','hcd-repair-safety',item.safety));
      plan.append(row);
    });
    const links = Array.isArray(report.correlations) ? report.correlations : [];
    if (!links.length) correlations.append(make('div','hcd-empty','Brak współwystępowania kilku niezależnych ustaleń w tej samej kategorii.'));
    links.forEach(group => {
      const row = make('div','hcd-repair-correlation');
      row.append(make('h3','',categories[group.category] || group.category));
      row.append(make('p','',group.conclusion));
      row.append(make('p','hcd-repair-safety','Wspólne identyfikatory: ' + group.check_ids.join(', ')));
      row.append(make('p','',group.next_step));
      correlations.append(row);
    });
  }

  function updateReadiness() {
    const findings = q('#hcd-readiness-findings');
    const table = q('#hcd-reg-changes');
    if (!findings || !table) return;
    findings.replaceChildren();
    table.replaceChildren();
    const readinessChecks = report && Array.isArray(report.checks) ? report.checks.filter(x => x.category === 'readiness') : [];
    if (!readinessChecks.length) findings.append(make('div','hcd-empty','Brak wyników gotowości. Uruchom diagnostykę.'));
    readinessChecks.forEach(item => findings.append(finding(item)));
    const c = report && report.comparison ? report.comparison : {};
    [['#hcd-reg-new','new'],['#hcd-reg-cleared','cleared'],['#hcd-reg-worse','escalated']].forEach(([selector,key]) => {
      const el = q(selector);
      if (el) el.textContent = c.available && c.counts ? String(c.counts[key] || 0) : '—';
    });
    const summary = q('#hcd-reg-summary');
    const period = q('#hcd-reg-period');
    if (!c.available) {
      summary.textContent = 'Potrzebne są dwa zakończone skany. Po aktualizacji wystarczy uruchomić diagnostykę, aby porównać wynik z poprzednią wersją.';
      period.textContent = 'Brak wcześniejszego skanu do porównania';
      const row = make('tr'); const cell = make('td','','Brak danych — uruchom dwa skany.');
      cell.colSpan = 5; row.append(cell); table.append(row);
      return;
    }
    const when = value => { const d = new Date(value); return isNaN(d.getTime()) ? '—' : d.toLocaleString('pl-PL'); };
    period.textContent = when(c.previous_at) + ' → ' + when(c.current_at);
    summary.textContent = 'Nowe wykrycia: ' + (c.counts.new || 0) + '; ustąpiły w skanie: ' + (c.counts.cleared || 0) + '; wzrost poziomu: ' + (c.counts.escalated || 0) + '; nowe testy po aktualizacji: ' + (c.counts.new_check || 0) + '; inne zmiany: ' + (c.counts.changed || 0) + '; testy niedostępne w jednym ze skanów: ' + (c.counts.unavailable || 0) + '. Skan porównawczy nie potwierdza skuteczności płatności.';
    const types = {new:'NOWE WYKRYCIE',cleared:'USTĄPIŁO',escalated:'WZROST',changed:'ZMIANA',unavailable:'NIEPORÓWNYWALNE',new_check:'NOWY TEST'};
    const statuses = {critical:'Krytyczny',warning:'Ostrzeżenie',pass:'OK',info:'Informacyjny',not_tested:'Nie badano'};
    const rows = Array.isArray(c.changes) ? c.changes : [];
    if (!rows.length) { const row = make('tr'), td = make('td','','Nie wykryto zmian między skanami.'); td.colSpan = 5; row.append(td); table.append(row); }
    rows.forEach(entry => {
      const row = make('tr'); row.dataset.type = entry.type;
      [types[entry.type] || entry.type, categories[entry.category] || entry.category, entry.title, statuses[entry.from] || entry.from, statuses[entry.to] || entry.to]
        .forEach(value => row.append(make('td','',value)));
      table.append(row);
    });
  }

  function updateBrowser() {
    const container = q('#hcd-browser-findings');
    if (!container) return;
    container.replaceChildren();
    const b = report && report.meta && report.meta.browser_probe ? report.meta.browser_probe : null;
    const c = b && b.counts ? b.counts : {};
    const numbers = {'#hcd-browser-samples': b ? b.sample_count : null,
      '#hcd-browser-errors': b ? (Number(c.js_error || 0) + Number(c.promise_rejection || 0) + Number(c.resource_error || 0)) : null,
      '#hcd-browser-slow': b ? c.slow_store : null};
    Object.entries(numbers).forEach(([selector, value]) => {
      const el = q(selector);
      if (el) el.textContent = value == null ? '—' : String(value);
    });
    const checks = report && Array.isArray(report.checks) ? report.checks.filter(item => item.category === 'browser') : [];
    if (!checks.length) container.append(make('div', 'hcd-empty', 'Uruchom skan, aby zobaczyć obserwacje.'));
    checks.forEach(item => container.append(finding(item)));
  }

  function updateBlockCompat() {
    const meta = report && report.meta && report.meta.block_compat ? report.meta.block_compat : null;
    const gw = meta && meta.gateways ? meta.gateways : null;
    const fields = meta && meta.fields ? meta.fields : null;
    const set = (selector, value) => { const el = q(selector); if (el) el.textContent = value == null ? '—' : String(value); };
    set('#hcd-block-gateways', gw ? gw.enabled : null);
    set('#hcd-block-integrations', gw ? gw.registered : null);
    set('#hcd-block-fields', fields ? fields.total : null);
    const area = q('#hcd-block-findings');
    if (area) {
      area.replaceChildren();
      const checks = report && Array.isArray(report.checks) ? report.checks.filter(x => x.category === 'block_compat') : [];
      if (!checks.length) area.append(make('div','hcd-empty','Brak wyniku. Uruchom diagnostykę.'));
      checks.forEach(x => area.append(finding(x)));
    }
    const table = q('#hcd-block-field-rows');
    if (!table) return;
    table.replaceChildren();
    const samples = fields && Array.isArray(fields.samples) ? fields.samples : [];
    if (!samples.length) {
      const row = make('tr'), cell = make('td','',meta && meta.field_status === 'registry_read' ? 'Nie zarejestrowano dodatkowych pól.' : 'Nie odczytano pól.');
      cell.colSpan = 4; row.append(cell); table.append(row);
    }
    samples.forEach(entry => {
      const row = make('tr');
      [entry.id, entry.location, entry.type, entry.required].forEach(value => row.append(make('td','',value || '—')));
      table.append(row);
    });
  }

  function updateScenarios() {
    const meta = report && report.meta && report.meta.scenario_audit ? report.meta.scenario_audit : null;
    const gateways = meta && Array.isArray(meta.gateways) ? meta.gateways : [];
    const scenarios = meta && Array.isArray(meta.scenarios) ? meta.scenarios : [];
    const shipping = meta && Array.isArray(meta.shipping_rules) ? meta.shipping_rules : [];
    const set = (id, n) => { const el = q(id); if (el) el.textContent = meta ? String(n) : '—'; };
    set('#hcd-scen-gateway-count', gateways.length);
    set('#hcd-scen-case-count', scenarios.length);
    set('#hcd-scen-ship-count', shipping.length);
    const table = q('#hcd-scenario-rows');
    if (table) {
      table.replaceChildren();
      if (!scenarios.length) {
        const row = make('tr'), cell = make('td','','Brak scenariuszy COD. Bramka może być wyłączona lub rejestr niedostępny; inne bramki wymagają testu dynamicznego.');
        cell.colSpan = 4; row.append(cell); table.append(row);
      }
      const labels = {config_restriction:'Możliwe ograniczenie ustawienia',config_condition:'Warunek ustawienia zgodny',dynamic:'Nieocenione dynamicznie'};
      scenarios.forEach(item => {
        const row = make('tr');
        [item.name, item.gateway, labels[item.result] || 'Nieocenione', item.reason].forEach(value => row.append(make('td','',value || '—')));
        table.append(row);
      });
    }
    const ship = q('#hcd-scenario-shipping-rows');
    if (ship) {
      ship.replaceChildren();
      if (!shipping.length) {
        const row = make('tr'), cell = make('td','','Brak aktywnych reguł darmowej wysyłki w odczytanych strefach lub brak danych.');
        cell.colSpan = 4; row.append(cell); ship.append(row);
      }
      shipping.forEach(item => {
        const row = make('tr');
        [item.zone_id, item.instance_id, item.description, item.coupon_conflict ? 'Kupony wyłączone — sprawdź' : 'Warunek konfiguracyjny'].forEach(value => row.append(make('td','',value)));
        ship.append(row);
      });
    }
    const findings = q('#hcd-scenario-findings');
    if (findings) {
      findings.replaceChildren();
      const rows = report && Array.isArray(report.checks) ? report.checks.filter(x => x.category === 'scenarios') : [];
      if (!rows.length) findings.append(make('div','hcd-empty','Brak wyników. Uruchom diagnostykę.'));
      rows.forEach(item => findings.append(finding(item)));
    }
  }

  function updateCompatibility() {
    const container = q('#hcd-compat-findings');
    if (!container) return;
    container.replaceChildren();
    const meta = report && report.meta && report.meta.compatibility ? report.meta.compatibility : null;
    const fields = {'#hcd-compat-wp':'wordpress','#hcd-compat-wc':'woocommerce','#hcd-compat-php':'php'};
    Object.entries(fields).forEach(([selector,key]) => { const item = q(selector); if (item) item.textContent = meta ? String(meta[key] || '—') : '—'; });
    const checks = report && Array.isArray(report.checks) ? report.checks.filter(item => item.category === 'compatibility') : [];
    if (!checks.length) container.append(make('div','hcd-empty','Brak danych zgodności. Uruchom diagnostykę.'));
    checks.forEach(item => container.append(finding(item)));
    const gate = report && report.meta && report.meta.release_gate ? report.meta.release_gate : null;
    const textMap = {
      blocked: 'PUBLIKACJA WSTRZYMANA — znaleziono przeszkody',
      requires_tests: 'WYMAGANE DALSZE TESTY — brak pełnych potwierdzeń',
      manual_complete_pending_review: 'TESTY RĘCZNE POTWIERDZONE — wymagany przegląd końcowy'
    };
    const state = q('#hcd-release-state');
    if (state) state.textContent = gate ? (textMap[gate.state] || 'Stan nierozstrzygnięty') : 'Uruchom skan, aby odczytać bramkę wydania.';
    [['#hcd-release-passed', 'manual_current_passed'], ['#hcd-release-failed', 'manual_failed'], ['#hcd-release-stale', 'manual_stale']].forEach(([selector, key]) => {
      const target = q(selector);
      if (target) target.textContent = gate && Number.isInteger(gate[key]) ? String(gate[key]) : '—';
    });
    const reasons = q('#hcd-release-reasons');
    if (reasons) {
      reasons.replaceChildren();
      const items = gate && Array.isArray(gate.reasons) ? gate.reasons : [];
      if (!items.length) reasons.append(make('div', 'hcd-empty', gate ? 'Nie znaleziono braków w samej checkliście. Nadal potrzebny jest przegląd przed publikacją.' : 'Brak danych.'));
      items.forEach(reason => reasons.append(make('div', 'hcd-feature', String(reason))));
    }
  }

  function updateIntegration() {
    const area = q('#hcd-integration-findings');
    if (!area) return;
    area.replaceChildren();
    const checks = report && Array.isArray(report.checks) ? report.checks.filter(item => item.category === 'integration') : [];
    if (!checks.length) area.append(make('div','hcd-empty','Zapisz wyniki testów i uruchom skan, aby zobaczyć podsumowanie w raporcie.'));
    checks.forEach(item => area.append(finding(item)));
  }

  function updateVisibility() {
    const container = q('#hcd-visibility-findings');
    if (!container) return;
    container.replaceChildren();
    const meta = report && report.meta && report.meta.payment_visibility ? report.meta.payment_visibility : null;
    const samples = meta && Array.isArray(meta.samples) ? meta.samples : [];
    const last = meta && meta.last ? meta.last : null;
    const values = {'#hcd-vis-samples':meta ? meta.count : null, '#hcd-vis-methods':last ? last.visible : null, '#hcd-vis-mode':last ? last.mode : null};
    Object.entries(values).forEach(([key,value]) => { const el = q(key); if (el) el.textContent = value == null ? '—' : String(value); });
    const checks = report && Array.isArray(report.checks) ? report.checks.filter(item => item.category === 'visibility') : [];
    if (!checks.length) container.append(make('div','hcd-empty','Uruchom skan, aby zobaczyć wyniki.'));
    checks.forEach(item => container.append(finding(item)));
    const tbody = q('#hcd-vis-rows');
    if (!tbody) return;
    tbody.replaceChildren();
    if (!samples.length) { const tr = make('tr'), td = make('td','','Brak obserwacji.'); td.colSpan = 4; tr.append(td); tbody.append(tr); }
    samples.slice().reverse().forEach(item => {
      const tr = make('tr');
      [item.at || '—',item.mode,item.cart,item.visible].forEach(value => tr.append(make('td','',String(value))));
      tbody.append(tr);
    });
  }

  function updateStoreApi() {
    const meta = report && report.meta && report.meta.store_api_observations ? report.meta.store_api_observations : null;
    const values = { '#hcd-store-count':meta ? meta.count : null,
      '#hcd-store-4xx':meta ? meta.client_errors : null, '#hcd-store-5xx':meta ? meta.server_errors : null };
    Object.entries(values).forEach(([key,value]) => { const el=q(key); if(el) el.textContent=value == null ? '—' : String(value); });
    const area=q('#hcd-store-findings');
    if (area) {
      area.replaceChildren();
      const items=report && Array.isArray(report.checks) ? report.checks.filter(x=>x.category==='store_api') : [];
      if(!items.length) area.append(make('div','hcd-empty','Brak danych. Uruchom diagnostykę.'));
      items.forEach(item=>area.append(finding(item)));
    }
    const table=q('#hcd-store-rows');
    if(!table) return;
    table.replaceChildren();
    const rows=meta && Array.isArray(meta.samples) ? meta.samples : [];
    if(!rows.length) { const tr=make('tr'), cell=make('td','','Brak próbek.'); cell.colSpan=4; tr.append(cell); table.append(tr); }
    rows.slice().reverse().forEach(item => {
      const tr=make('tr'); [item.at||'—',item.area,item.method,item.status].forEach(v=>tr.append(make('td','',String(v)))); table.append(tr);
    });
  }

  function updateCorrelation() {
    const meta=report && report.meta && report.meta.evidence_correlation ? report.meta.evidence_correlation : null;
    const values={
      '#hcd-corr-sources':meta ? meta.signal_source_count + '/3' : '—',
      '#hcd-corr-http':meta ? Number(meta.store_http_4xx || 0)+Number(meta.store_http_5xx || 0) : '—',
      '#hcd-corr-logs':meta ? Number(meta.local_log_critical_lines || 0)+Number(meta.local_log_error_lines || 0) : '—',
      '#hcd-corr-retest':meta ? Number(meta.manual_reverification_needed || 0) : '—'
    };
    Object.entries(values).forEach(([id,value])=>{ const elem=q(id); if(elem) elem.textContent=String(value); });
    const list=q('#hcd-correlation-findings');
    if(list) {
      list.replaceChildren();
      const checks=report && Array.isArray(report.checks) ? report.checks.filter(item=>item.category==='correlation') : [];
      if(!checks.length) list.append(make('div','hcd-empty','Uruchom skan, aby zobaczyć wyniki korelacji.'));
      checks.forEach(item=>list.append(finding(item)));
    }
    const steps=q('#hcd-correlation-steps');
    if(steps && meta && Array.isArray(meta.manual_steps)) {
      steps.replaceChildren();
      meta.manual_steps.slice(0,4).forEach(step=>steps.append(make('li','',step)));
    }
  }

  function updateEvents() {
    const body = q('#hcd-events-rows');
    body.replaceChildren();
    const rows = HCD_CONFIG.events || [];
    if (!rows.length) {
      const tr = make('tr');
      const td = make('td', '', HCD_CONFIG.monitoring ? 'Brak zapisanych zdarzeń.' : 'Monitoring jest wyłączony. Można go włączyć w ustawieniach.');
      td.colSpan = 4;
      tr.append(td);
      body.append(tr);
    }
    rows.forEach((row) => {
      const tr = make('tr');
      [row.recorded_at, names[row.event_type] || row.event_type, row.source, row.detail || '—'].forEach(text => tr.append(make('td', '', text)));
      body.append(tr);
    });
  }

  let scanInProgress = false;
  async function runScan(targetTab) {
    if (scanInProgress) return;
    scanInProgress = true;
    const buttons = [q('#hcd-run-scan'), q('#hcd-run-scan-secondary'), q('#hcd-run-core-scan'), q('#hcd-run-payments'), q('#hcd-run-shipping'), q('#hcd-run-conflicts'), q('#hcd-run-logs'), q('#hcd-run-repairs'), q('#hcd-run-readiness'), q('#hcd-run-browser'), q('#hcd-run-block-compat'), q('#hcd-run-scenarios'), q('#hcd-run-visibility'), q('#hcd-run-store-api'), q('#hcd-run-correlation'), q('#hcd-run-compatibility'), q('#hcd-run-integration')].filter(Boolean);
    buttons.forEach(btn => { btn.disabled = true; });
    const flash = q('#hcd-message');
    flash.textContent = 'Trwa diagnostyka konfiguracji WooCommerce...';
    const body = new URLSearchParams({action: 'hcd_run_scan', nonce: HCD_CONFIG.nonce});
    try {
      const response = await fetch(HCD_CONFIG.ajaxUrl, {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}, credentials: 'same-origin', body: body.toString()});
      const data = await response.json();
      if (!response.ok || !data.success || !data.data || !data.data.checks) throw new Error(data?.data?.message || 'Nie można pobrać raportu.');
      report = data.data;
      updateKpis();
      updateOverview();
      updateChecks();
      updateCoreFindings();
      updateCommerceFindings();
      updateConflictLogFindings();
      updateRepairs();
      updateReadiness();
      updateCompatibility();
      updateIntegration();
      updateBrowser();
      updateBlockCompat();
      updateScenarios();
      updateVisibility();
      updateStoreApi();
      updateCorrelation();
      flash.textContent = 'Skan zakończony. Wyniki zaktualizowane.';
      switchTab(targetTab || 'diagnostics');
    } catch (error) {
      flash.textContent = 'Błąd skanowania: ' + error.message;
      switchTab('dashboard');
    } finally {
      scanInProgress = false;
      buttons.forEach(btn => { btn.disabled = false; });
    }
  }

  q('#hcd-run-scan').addEventListener('click', () => runScan('diagnostics'));
  q('#hcd-run-scan-secondary').addEventListener('click', () => runScan('diagnostics'));
  q('#hcd-run-core-scan').addEventListener('click', () => runScan('checkout-core'));
  q('#hcd-run-payments').addEventListener('click', () => runScan('payments'));
  q('#hcd-run-shipping').addEventListener('click', () => runScan('shipping'));
  q('#hcd-run-conflicts').addEventListener('click', () => runScan('conflicts'));
  q('#hcd-run-logs').addEventListener('click', () => runScan('logs'));
  q('#hcd-run-repairs').addEventListener('click', () => runScan('repairs'));
  q('#hcd-run-readiness').addEventListener('click', () => runScan('readiness'));
  q('#hcd-run-compatibility').addEventListener('click', () => runScan('compatibility'));
  q('#hcd-run-integration').addEventListener('click', () => runScan('integration'));
  q('#hcd-run-browser').addEventListener('click', () => runScan('browser'));
  q('#hcd-run-block-compat').addEventListener('click', () => runScan('block-compat'));
  q('#hcd-run-scenarios').addEventListener('click', () => runScan('scenarios'));
  q('#hcd-run-visibility').addEventListener('click', () => runScan('visibility'));
  q('#hcd-run-store-api').addEventListener('click', () => runScan('store-api'));
  q('#hcd-run-correlation').addEventListener('click', () => runScan('correlation'));
  q('#hcd-module-filter').addEventListener('change', e => { moduleFilter = e.target.value; updateChecks(); });
  q('#hcd-filter').addEventListener('change', e => { filter = e.target.value; updateChecks(); });
  updateKpis();
  updateOverview();
  updateChecks();
  updateCoreFindings();
  updateCommerceStrip();
  updateCommerceFindings();
  updateConflictLogFindings();
  updateRepairs();
  updateReadiness();
  updateCompatibility();
  updateIntegration();
  updateBrowser();
  updateBlockCompat();
  updateScenarios();
  updateVisibility();
  updateStoreApi();
  updateCorrelation();
  updateCoreStrip();
  updateEvents();
}());
