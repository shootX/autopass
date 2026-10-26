// Rebuilds backend/resources/site/{home,business}.json (the admin editor's defaults) from the landing.
// Run from the repo root after changing landing text: node lending/tools/site-defaults.js lending gewash backend/resources/site
// Needs gewash/node_modules (jsdom).
const fs = require('fs'), vm = require('vm'), path = require('path');
const { JSDOM } = require(require('path').resolve(process.argv[3], 'node_modules/jsdom'));
const L = process.argv[2], OUT = process.argv[4];
const GROUPS = {
  home: [['nav', ['nav.']], ['hero', ['hero.', 'card.']], ['how', ['how.']], ['price', ['price.', 'plan.']], ['biz', ['biz.']],
    ['feat', ['feat.']], ['branches', ['br.']], ['app', ['cta.']], ['faq', ['faq.']], ['footer', ['ft.']]],
  business: [['nav', ['nav.', 'doc']], ['calc', ['biz.', 'calc.', 'form.', 'price.']], ['models', ['mod.', 'm1.', 'm2.']],
    ['start', ['st.']], ['faq', ['faq.']], ['go', ['go.']], ['footer', ['ft.', 'hero.']]],
};
const SECTIONS = { home: ['hero', 'how', 'price', 'biz', 'feat', 'branches', 'app', 'faq'], business: ['calc', 'models', 'start', 'faq', 'go'] };
for (const page of ['home', 'business']) {
  const html = fs.readFileSync(`${L}/${page === 'home' ? 'index' : 'business'}.html`, 'utf8');
  const doc = new JSDOM(html).window.document;
  const ctx = { window: {} }; vm.runInNewContext(fs.readFileSync(`${L}/content/${page}.js`, 'utf8'), ctx);
  const T = ctx.window.AP_TEXT;
  const ka = {};
  doc.querySelectorAll('[data-i18n]').forEach(e => { ka[e.dataset.i18n] ??= e.textContent; });
  doc.querySelectorAll('[data-i18n-ph]').forEach(e => { ka[e.dataset.i18nPh] ??= e.placeholder; });
  doc.querySelectorAll('[data-i18n-alt]').forEach(e => { ka[e.dataset.i18nAlt] ??= e.alt; });
  const flat = l => { const o = {}; for (const [k, v] of Object.entries(T[l])) { if (k === 'plan') { for (const [pk, pv] of Object.entries(v)) if (typeof pv === 'string') o['plan.' + pk] = pv; } else if (typeof v === 'string') o[k] = v; } return o; };
  const langs = { ka: { ...ka, ...flat('ka') }, en: flat('en'), ru: flat('ru') };
  const keys = [...new Set([...Object.keys(langs.ka), ...Object.keys(langs.en)])];
  const group = k => (GROUPS[page].find(([, ps]) => ps.some(p => k === p || k.startsWith(p))) || ['general'])[0];
  const texts = {};
  for (const k of keys) texts[k] = { group: group(k), ka: langs.ka[k] ?? '', en: langs.en[k] ?? '', ru: langs.ru[k] ?? '' };
  const lists = {};
  const addList = (name, grp, fields, get) => {
    const n = get('ka').length;
    lists[name] = { group: grp, fields, items: Array.from({ length: n }, (_, i) => Object.fromEntries(['ka', 'en', 'ru'].map(l => [l, get(l)[i]]))) };
  };
  addList('faq', 'faq', ['q', 'a'], l => T[l].faq.map(f => [f[1], f[2]]));
  if (page === 'home') {
    addList('branches', 'branches', ['name', 'address'], l => T[l].branches);
    addList('plan.l', 'price', ['text'], l => T[l].plan.l.map(x => [x]));
    addList('plan.lu', 'price', ['text'], l => T[l].plan.lu.map(x => [x]));
  }
  const missing = keys.filter(k => !langs.ka[k] || !langs.en[k] || !langs.ru[k]);
  fs.writeFileSync(`${OUT}/${page}.json`, JSON.stringify({ sections: SECTIONS[page], groups: GROUPS[page].map(g => g[0]).concat('general'), texts, lists }, null, 1) + '\n');
  console.log(page, keys.length, 'keys; missing in some lang:', missing.join(','));
}
