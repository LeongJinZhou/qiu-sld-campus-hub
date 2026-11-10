// Screenshot every page that loads for each role, from a route_sweep.py result file.
// Usage: node screenshots.mjs <sweep.json> <outDir> <baseUrl> [role,role,...]
// Appends to <outDir>/manifest.json; a (role, route pattern) already captured is skipped, so run it once per database.
import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { join } from 'node:path';

const [sweepFile, outDir, base, rolesArg] = process.argv.slice(2);
const sweep = JSON.parse(readFileSync(sweepFile, 'utf8'));
const EMAILS = {
    guest: null, alumni: 'weikeat.chong@example.com', student: 'kavitha.g@example.com', member: 'siti.wijaya@example.com',
    president: 'david.francis@example.com', vice_president: 'ahmad.razak@example.com', secretary: 'meiling.tan@example.com',
    treasurer: 'suresh.s@example.com', event_committee: 'liwei.zhang@example.com', advisor: 'advisor1.demo@qiu.edu.my',
    approver_dean: 'dean.demo@qiu.edu.my', approver_bursar: 'bursar.demo@qiu.edu.my', sld_officer: 'sld.officer.demo@qiu.edu.my',
    super_admin: 'jinzhou.leong@qiu.edu.my',
};
const roles = (rolesArg ? rolesArg.split(',') : Object.keys(EMAILS));
const SKIP = /export|download|\.ics|\.zip|attendance-qr|photos|\/file|\/receipt|\/payout|\/qr|poster\.png|\/live$|\/verify|\/p\/|\/c\/|\/t\//;
const manifestPath = join(outDir, 'manifest.json');
const manifest = existsSync(manifestPath) ? JSON.parse(readFileSync(manifestPath, 'utf8')) : [];
const seen = new Set(manifest.map((m) => `${m.role}|${m.uri}`));
const slug = (s) => s.replace(/[{}?]/g, '').replace(/[^A-Za-z0-9]+/g, '-').replace(/^-|-$/g, '').toLowerCase() || 'home';

const browser = await chromium.launch();
for (const role of roles) {
    const pages = Object.entries(sweep.all[role] ?? {}).filter(([p, v]) => v.status === 200 && !SKIP.test(p) && !seen.has(`${role}|${v.uri}`));
    // one URL per route pattern
    const byPattern = new Map();
    for (const [p, v] of pages) if (!byPattern.has(v.uri)) byPattern.set(v.uri, p);
    if (!byPattern.size) continue;
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 860 } });
    const page = await ctx.newPage();
    if (EMAILS[role]) {
        await page.goto(base + '/dev/login-as');
        const xs = (await ctx.cookies()).find((c) => c.name === 'XSRF-TOKEN');
        await ctx.request.post(base + '/dev/login-as', { data: { email: EMAILS[role] }, headers: { 'X-XSRF-TOKEN': decodeURIComponent(xs.value), Accept: 'application/json' }, maxRedirects: 0 });
    }
    mkdirSync(join(outDir, role), { recursive: true });
    for (const [uri, path] of byPattern) {
        try {
            const res = await page.goto(base + path, { waitUntil: 'networkidle', timeout: 45000 });
            if (!res || res.status() !== 200) continue;
            await page.locator('button:has-text("Remind me next time")').click({ timeout: 800 }).catch(() => {});
            await page.waitForTimeout(250);
            const file = `${role}/${slug(uri)}.jpg`;
            await page.screenshot({ path: join(outDir, file), type: 'jpeg', quality: 72, fullPage: true, animations: 'disabled' });
            const h1 = (await page.locator('h1').first().innerText().catch(() => '')).trim();
            manifest.push({ role, uri, url: path, title: (await page.title()).trim(), heading: h1, file });
        } catch (e) {
            console.log('skip', role, path, String(e).split('\n')[0].slice(0, 80));
        }
    }
    await ctx.close();
}
await browser.close();
writeFileSync(manifestPath, JSON.stringify(manifest, null, 1));
console.log('manifest entries:', manifest.length);
