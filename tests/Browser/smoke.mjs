// Browser smoke test over the Chrome DevTools Protocol (Node 22+ built-in WebSocket, no npm packages).
// Visits every page per role and fails on console errors, JS exceptions, failed requests or wrong pages.
//
// usage:   node tests/Browser/smoke.mjs <baseUrl>          (needs APP_DEMO=true on the target)
// e.g.     node tests/Browser/smoke.mjs http://127.0.0.1:8000
//          node tests/Browser/smoke.mjs https://asset-laravel.onrender.com
// env:     CHROME_PATH (default: macOS Google Chrome), SMOKE_PORT (default 9444), SMOKE_TMP
import { spawn } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const base = (process.argv[2] ?? 'http://127.0.0.1:8123').replace(/\/$/, '');
const CHROME = process.env.CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT = Number(process.env.SMOKE_PORT ?? 9444);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// Render's free plan sleeps when idle and answers 503 while waking; wait (up to ~3 min) for the app itself.
for (let i = 0; i < 36 && (await fetch(`${base}/up`).then((r) => r.status, () => 0)) !== 200; i++) await sleep(5000);

const profile = mkdtempSync(join(process.env.SMOKE_TMP ?? tmpdir(), 'smoke-'));
const chrome = spawn(CHROME, ['--headless=new', '--disable-gpu', `--remote-debugging-port=${PORT}`, `--user-data-dir=${profile}`, '--window-size=1366,900', 'about:blank'], { stdio: 'ignore' });

let ws;
for (let i = 0; i < 50 && !ws; i++) {
  try {
    const target = await (await fetch(`http://127.0.0.1:${PORT}/json/new?about:blank`, { method: 'PUT' })).json();
    ws = new WebSocket(target.webSocketDebuggerUrl);
  } catch { await sleep(200); }
}
await new Promise((r) => ws.addEventListener('open', r, { once: true }));

let nextId = 0;
const pending = new Map();
const listeners = [];
const problems = []; // console errors, exceptions, failed requests
let expectStatus = null; // status allowed for the main document of the current step
ws.addEventListener('message', (event) => {
  const msg = JSON.parse(event.data);
  if (msg.id && pending.has(msg.id)) { pending.get(msg.id)(msg); pending.delete(msg.id); return; }
  listeners.forEach((fn) => fn(msg));
  if (msg.method === 'Runtime.exceptionThrown') problems.push(`exception: ${msg.params.exceptionDetails.exception?.description ?? msg.params.exceptionDetails.text}`);
  if (msg.method === 'Runtime.consoleAPICalled' && msg.params.type === 'error') problems.push(`console.error: ${msg.params.args.map((a) => a.value ?? a.description).join(' ')}`);
  if (msg.method === 'Log.entryAdded' && msg.params.entry.level === 'error' && !(expectStatus && msg.params.entry.text.includes(String(expectStatus)))) problems.push(`log: ${msg.params.entry.text} ${msg.params.entry.url ?? ''}`);
  if (msg.method === 'Network.responseReceived') {
    const { response, type } = msg.params;
    if (response.status >= 400 && !(type === 'Document' && response.status === expectStatus)) problems.push(`HTTP ${response.status} ${response.url}`);
  }
  if (msg.method === 'Network.loadingFailed' && !msg.params.canceled) problems.push(`failed: ${msg.params.errorText} ${msg.params.type}`);
});
const send = (method, params = {}) => new Promise((resolve) => { const id = ++nextId; pending.set(id, resolve); ws.send(JSON.stringify({ id, method, params })); });
const waitFor = (method, timeout = 90000) => new Promise((resolve, reject) => {
  const t = setTimeout(() => reject(new Error(`timeout waiting for ${method}`)), timeout);
  listeners.push(function fn(msg) { if (msg.method === method) { clearTimeout(t); listeners.splice(listeners.indexOf(fn), 1); resolve(msg); } });
});
const evaluate = async (expression) => (await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true })).result?.result?.value;

await Promise.all(['Page.enable', 'Runtime.enable', 'Log.enable', 'Network.enable'].map((m) => send(m)));

let docStatus = null;
listeners.push((msg) => { if (msg.method === 'Network.responseReceived' && msg.params.type === 'Document') docStatus = msg.params.response.status; });
async function go(path, { status = 200, settle = 2500 } = {}) {
  expectStatus = status === 200 ? null : status;
  docStatus = null;
  const loaded = waitFor('Page.loadEventFired');
  await send('Page.navigate', { url: base + path });
  await loaded;
  await sleep(settle); // lazy widgets and Livewire requests
  return docStatus;
}

const results = [];
const check = (name, ok, detail = '') => { results.push({ name, ok, detail }); };
const run = async (name, fn) => {
  const before = problems.length;
  try { await fn(); } catch (e) { check(name, false, e.message); return; }
  const fresh = problems.slice(before);
  check(name, fresh.length === 0, fresh.join(' | '));
};

// ---- officer journey ----
await run('officer: demo login lands on dashboard', async () => {
  await go('/demo/officer');
  const title = await evaluate('document.title');
  if (!title.includes('แดชบอร์ด')) throw new Error(`title was "${title}"`);
});
for (const path of ['/admin', '/admin/assets', '/admin/assets/create', '/admin/loans', '/admin/repair-orders']) {
  await run(`officer: ${path} loads clean`, async () => {
    const s = await go(path);
    if (s !== 200) throw new Error(`status ${s}`);
  });
}
await run('officer: asset view + edit load clean', async () => {
  await go('/admin/assets');
  const href = await evaluate(`[...document.querySelectorAll('a[href*="/admin/assets/"]')].map(a => a.href).find(h => /\\/assets\\/\\d+$/.test(h))`);
  if (!href) throw new Error('no asset link found');
  const path = new URL(href).pathname;
  if (await go(path) !== 200) throw new Error('view not 200');
  if (await go(path + '/edit') !== 200) throw new Error('edit not 200');
});
await run('browser back/forward keep the right page', async () => {
  await go('/admin/assets');
  await go('/admin/loans');
  await evaluate('history.back()'); await sleep(2500);
  const back = await evaluate('location.pathname');
  await evaluate('history.forward()'); await sleep(2500);
  const fwd = await evaluate('location.pathname');
  if (back !== '/admin/assets' || fwd !== '/admin/loans') throw new Error(`back=${back} forward=${fwd}`);
});
await run('modal opens and closes with ESC', async () => {
  await go('/admin/loans');
  const clicked = await evaluate(`(() => { const b = [...document.querySelectorAll('button')].find(x => x.textContent.includes('ขอยืมทรัพย์สิน')); b?.click(); return !!b; })()`);
  if (!clicked) throw new Error('create button not found');
  await sleep(2000);
  const open = await evaluate(`!!document.querySelector('.fi-modal.fi-modal-open, .fi-modal-window')?.checkVisibility?.()`);
  if (!open) throw new Error('modal did not open');
  for (const type of ['keyDown', 'keyUp']) await send('Input.dispatchKeyEvent', { type, key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
  await sleep(1200);
  const stillOpen = await evaluate(`[...document.querySelectorAll('.fi-modal-window')].some(w => w.checkVisibility())`);
  if (stillOpen) throw new Error('modal still open after ESC');
});
await run('meta description present, offline banner hidden while online', async () => {
  await go('/admin');
  const meta = await evaluate(`document.querySelector('meta[name="description"]')?.content ?? ''`);
  const offlineVisible = await evaluate(`!!document.querySelector('.asset-offline')?.checkVisibility()`);
  if (!meta.includes('ระบบบริหารทรัพย์สิน')) throw new Error('meta description missing');
  if (offlineVisible) throw new Error('offline banner visible while online');
});
await run('empty state shows the Thai message', async () => {
  await go('/admin/repair-orders');
  await evaluate(`[...document.querySelectorAll('.fi-tabs-item')].find(t => t.textContent.includes('เลยกำหนดรับคืน'))?.click()`);
  await sleep(2500);
  const text = await evaluate('document.body.innerText');
  if (!text.includes('ไม่มีงานซ่อมในหมวดนี้')) throw new Error('empty state text not shown');
});
await run('404 page is the themed Thai page', async () => {
  const s = await go('/admin/does-not-exist', { status: 404, settle: 500 });
  const text = await evaluate('document.body.innerText');
  if (s !== 404 || !text.includes('ไม่พบหน้า')) throw new Error(`status ${s}, text "${text.slice(0, 60)}"`);
});

// ---- admin journey ----
await run('admin: settings pages load clean', async () => {
  await go('/demo/admin');
  for (const path of ['/admin/users', '/admin/departments', '/admin/categories']) {
    const s = await go(path);
    if (s !== 200) throw new Error(`${path} status ${s}`);
  }
});

// ---- staff journey ----
await run('staff: sees own pages, gets themed 403 on admin pages', async () => {
  await go('/demo/staff');
  if (await go('/admin/assets') !== 200) throw new Error('assets not 200');
  const s = await go('/admin/users', { status: 403, settle: 500 });
  const text = await evaluate('document.body.innerText');
  if (s !== 403 || !text.includes('ไม่มีสิทธิ์')) throw new Error(`status ${s}, text "${text.slice(0, 60)}"`);
});

// ---- report ----
for (const r of results) console.log(`${r.ok ? 'PASS' : 'FAIL'}  ${r.name}${r.ok ? '' : `\n      ${r.detail}`}`);
const failed = results.filter((r) => !r.ok).length;
console.log(`\n${results.length - failed}/${results.length} passed against ${base}`);
ws.close(); chrome.kill(); await sleep(300); rmSync(profile, { recursive: true, force: true });
process.exit(failed ? 1 : 0);
