// Click-through crawl: sign in as each role and follow every in-app link, recording any 404/5xx response,
// failed request, JavaScript console error or page error. 403 is reported separately (it is a permission decision).
// Usage: node crawl_ui.mjs <baseUrl> <out.json> [maxPagesPerRole=120] [role,role,...]
import { chromium } from 'playwright';
import { writeFileSync } from 'node:fs';

const [base, out, maxArg, rolesArg] = process.argv.slice(2);
const MAX = Number(maxArg ?? 120);
const EMAILS = {
    guest: null, alumni: 'weikeat.chong@example.com', student: 'kavitha.g@example.com', member: 'siti.wijaya@example.com',
    president: 'david.francis@example.com', vice_president: 'ahmad.razak@example.com', secretary: 'meiling.tan@example.com',
    treasurer: 'suresh.s@example.com', event_committee: 'liwei.zhang@example.com', advisor: 'advisor1.demo@qiu.edu.my',
    approver_dean: 'dean.demo@qiu.edu.my', approver_bursar: 'bursar.demo@qiu.edu.my', sld_officer: 'sld.officer.demo@qiu.edu.my',
    super_admin: 'jinzhou.leong@qiu.edu.my',
};
const roles = rolesArg ? rolesArg.split(',') : Object.keys(EMAILS);
const SKIP = /logout|\/download|\/export|\.zip|\.pdf|\.docx|\.xlsx|\.pptx|\.ics|\/storage\/|\/auth\/|\/dev\/|\/attendance-qr|\/photos|\/file\/|\/qr\b|\/live$/;
const browser = await chromium.launch();
const report = {};
for (const role of roles) {
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 860 } });
    const page = await ctx.newPage();
    const problems = [], errors = new Set(), denied = [];
    let current = '/';
    page.on('console', (m) => { if (m.type() === 'error') errors.add(`${current} :: ${m.text().slice(0, 140)}`); });
    page.on('pageerror', (e) => errors.add(`${current} :: PAGEERROR ${String(e).slice(0, 140)}`));
    page.on('requestfailed', (r) => { if (!/net::ERR_ABORTED/.test(r.failure()?.errorText ?? '')) problems.push(`FAILED ${r.method()} ${r.url().replace(base, '')} ${r.failure()?.errorText}`); });
    page.on('response', (r) => {
        const s = r.status(), u = r.url().replace(base, '');
        if (s >= 500 || s === 404) problems.push(`${s} ${r.request().method()} ${u} (on ${current})`);
        if (s === 403) denied.push(`${r.request().method()} ${u}`);
    });
    if (EMAILS[role]) {
        await page.goto(base + '/dev/login-as');
        const xs = (await ctx.cookies()).find((c) => c.name === 'XSRF-TOKEN');
        await ctx.request.post(base + '/dev/login-as', { data: { email: EMAILS[role] }, headers: { 'X-XSRF-TOKEN': decodeURIComponent(xs.value), Accept: 'application/json' }, maxRedirects: 0 });
    }
    const queue = [EMAILS[role] ? '/feed' : '/'], seen = new Set();
    while (queue.length && seen.size < MAX) {
        const u = queue.shift();
        if (seen.has(u)) continue;
        seen.add(u);
        current = u;
        const res = await page.goto(base + u, { waitUntil: 'networkidle', timeout: 45000 }).catch(() => null);
        if (!res) { problems.push(`NAVFAIL ${u}`); continue; }
        await page.locator('button:has-text("Remind me next time")').click({ timeout: 500 }).catch(() => {});
        // Open every tab/menu button once so content behind it is part of the page (no navigation, no form submit).
        const links = await page.$$eval('a[href]', (as) => as.map((a) => a.getAttribute('href')));
        for (const l of links) {
            if (!l || !l.startsWith('/') || l.startsWith('//')) continue;
            const p = l.split('#')[0];
            if (SKIP.test(p) || seen.has(p)) continue;
            queue.push(p);
        }
    }
    report[role] = { pages: seen.size, problems: [...new Set(problems)], denied: [...new Set(denied)].slice(0, 5), consoleErrors: [...errors].slice(0, 10) };
    await ctx.close();
}
await browser.close();
writeFileSync(out, JSON.stringify(report, null, 1));
for (const [r, v] of Object.entries(report)) console.log(`${r.padEnd(16)} pages=${String(v.pages).padEnd(4)} problems=${v.problems.length} consoleErrors=${v.consoleErrors.length}`);
