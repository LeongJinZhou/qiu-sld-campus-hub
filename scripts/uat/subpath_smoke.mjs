// Main-path browser test for a subfolder install (Plesk: https://aihubdev.qiu.edu.my/campus-hub/) or a domain root.
// Checks: a signed-out deep link lands on the sign-in page (no 404); each key page renders (no black screen) with
// no console errors, no failed requests and no 404/5xx; every in-app link stays inside the folder; no sideways
// scrolling on a phone; the SLD Admin meeting exports download.
// Needs DEMO_LOGIN=true and a demo SLD Admin (default sld.officer.demo@qiu.edu.my from StaffSeeder).
// Usage: node scripts/uat/subpath_smoke.mjs http://127.0.0.1:8090/campus-hub [email]
import { chromium } from 'playwright';

const [baseArg, emailArg] = process.argv.slice(2);
const base = (baseArg ?? 'http://127.0.0.1:8090/campus-hub').replace(/\/+$/, '');
const folder = new URL(base).pathname.replace(/\/+$/, ''); // '' or '/campus-hub'
const email = emailArg ?? 'sld.officer.demo@qiu.edu.my'; // demo SLD Admin
const PAGES = ['/feed', '/search', '/calendar', '/notifications', '/me', '/clubs', '/approvals', '/admin', '/admin/fees', '/admin/clubs', '/admin/meetings', '/admin/finance', '/admin/audit', '/admin/roles'];
const failures = [];
const fail = (msg) => failures.push(msg);

const browser = await chromium.launch();

async function check(ctx, label, viewport) {
    const page = await ctx.newPage();
    await page.setViewportSize(viewport);
    let current = '';
    page.on('console', (m) => m.type() === 'error' && fail(`${label} ${current}: console error ${m.text().slice(0, 160)}`));
    page.on('pageerror', (e) => fail(`${label} ${current}: page error ${String(e).slice(0, 160)}`));
    page.on('requestfailed', (r) => !/ERR_ABORTED/.test(r.failure()?.errorText ?? '') && fail(`${label} ${current}: request failed ${r.url()}`));
    page.on('response', (r) => (r.status() === 404 || r.status() >= 500) && fail(`${label} ${current}: ${r.status()} ${r.url()}`));
    for (const p of PAGES) {
        current = p;
        const res = await page.goto(base + p, { waitUntil: 'networkidle', timeout: 45000 }).catch(() => null);
        if (!res) { fail(`${label} ${p}: did not load`); continue; }
        if (res.status() === 403) continue;
        const state = await page.evaluate((root) => ({
            rendered: (document.getElementById('app')?.innerText.trim().length ?? 0) > 20,
            overflow: document.documentElement.scrollWidth - window.innerWidth,
            outside: [...document.querySelectorAll('a[href]')].map((a) => a.getAttribute('href'))
                .filter((h) => h && h.startsWith('/') && !h.startsWith('//') && root && !(h === root || h.startsWith(`${root}/`) || h.startsWith(`${root}?`))),
        }), folder);
        if (!state.rendered) fail(`${label} ${p}: page is empty (black screen)`);
        if (state.overflow > 1) fail(`${label} ${p}: scrolls sideways by ${state.overflow}px`);
        if (state.outside.length) fail(`${label} ${p}: links outside ${folder}: ${[...new Set(state.outside)].slice(0, 5).join(', ')}`);
    }
    await page.close();
}

// 1. Signed out: a deep link lands on the sign-in page.
const guest = await browser.newContext();
const gp = await guest.newPage();
const landed = await gp.goto(`${base}/feed`, { waitUntil: 'networkidle' });
if (landed?.status() !== 200 || !gp.url().startsWith(`${base}/`)) fail(`guest /feed ended on ${gp.url()} (${landed?.status()})`);
if (!(await gp.locator('#app').innerText()).trim()) fail('guest sign-in page is empty (black screen)');
await guest.close();

// 2. Signed in as the demo account: desktop and phone.
const ctx = await browser.newContext();
const lp = await ctx.newPage();
await lp.goto(`${base}/dev/login-as`);
const xsrf = (await ctx.cookies()).find((c) => c.name === 'XSRF-TOKEN');
const login = await ctx.request.post(`${base}/dev/login-as`, { data: { email }, headers: { 'X-XSRF-TOKEN': decodeURIComponent(xsrf?.value ?? ''), Accept: 'application/json' }, maxRedirects: 0 });
const signedIn = await ctx.request.get(`${base}/feed`, { maxRedirects: 0 });
if (login.status() >= 400 || signedIn.status() !== 200) fail(`demo sign-in as ${email} failed (${login.status()}, /feed ${signedIn.status()})`);
await lp.close();
await check(ctx, 'desktop', { width: 1280, height: 860 });
await check(ctx, 'phone', { width: 390, height: 780 });

// 3. SLD Admin meeting exports download inside the folder.
const mp = await ctx.newPage();
await mp.goto(`${base}/admin/meetings`, { waitUntil: 'networkidle' });
const exports = await mp.$$eval('a[href*="/admin/meetings/export"]', (as) => as.map((a) => a.href));
if (!exports.length) fail('/admin/meetings: no export links found');
for (const href of exports) {
    const r = await ctx.request.get(href);
    if (r.status() !== 200) fail(`export ${href} returned ${r.status()}`);
}
await browser.close();

const unique = [...new Set(failures)];
console.log(unique.length ? unique.join('\n') : `OK: ${base} (${PAGES.length} pages x 2 sizes, ${exports.length} exports)`);
process.exit(unique.length ? 1 : 0);
