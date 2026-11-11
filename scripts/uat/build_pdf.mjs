// Markdown -> styled PDF (cover, table of contents, page numbers) using Playwright's Chromium.
// Usage: node build_pdf.mjs input.md output.pdf "Title" "Subtitle" ["Version line"]
// Images in the Markdown are resolved relative to the input file.
import { chromium } from 'playwright';
import { marked } from 'marked';
import { readFileSync, writeFileSync, mkdtempSync } from 'node:fs';
import { dirname, resolve, join } from 'node:path';
import { tmpdir } from 'node:os';

const [input, output, title = 'Document', subtitle = '', version = ''] = process.argv.slice(2);
const dir = dirname(resolve(input));
const slug = (s) => s.toLowerCase().replace(/<[^>]+>/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

const headings = [];
const renderer = new marked.Renderer();
renderer.heading = (text, depth) => {
    const id = `${slug(text)}-${headings.length}`;
    if (depth <= 2) headings.push({ id, text: text.replace(/<[^>]+>/g, ''), depth });
    return `<h${depth} id="${id}">${text}</h${depth}>`;
};
// A table whose first column is "ID" keeps the ID on one line (G-01 must not wrap to G- / 01).
const body = marked.parse(readFileSync(input, 'utf8'), { renderer, gfm: true }).replace(/<table>(\s*<thead>\s*<tr>\s*<th>ID<\/th>)/g, '<table class="idtable">$1');
const toc = headings.map((h) => `<li class="d${h.depth}"><a href="#${h.id}">${h.text}</a></li>`).join('\n');

const css = `
@page { size: A4; margin: 18mm 16mm 20mm; }
* { box-sizing: border-box; }
body { font: 10.5pt/1.5 -apple-system, "Segoe UI", Helvetica, Arial, sans-serif; color: #1c2430; }
h1 { font-size: 20pt; color: #7a1020; border-bottom: 2px solid #7a1020; padding-bottom: 4px; margin: 26px 0 12px; page-break-before: always; }
h1:first-of-type { page-break-before: avoid; }
h2 { font-size: 14pt; color: #7a1020; margin: 20px 0 8px; page-break-after: avoid; }
h3 { font-size: 11.5pt; margin: 14px 0 6px; page-break-after: avoid; }
h4 { font-size: 10.5pt; margin: 10px 0 4px; }
p, li { orphans: 3; widows: 3; }
table { border-collapse: collapse; width: 100%; margin: 8px 0 14px; font-size: 9.2pt; page-break-inside: auto; }
tr { page-break-inside: avoid; }
th { background: #7a1020; color: #fff; text-align: left; padding: 5px 7px; }
td { border: 1px solid #d7dce3; padding: 4px 7px; vertical-align: top; }
tr:nth-child(even) td { background: #f6f7f9; }
.idtable td:first-child { white-space: nowrap; }
code { font: 9pt Menlo, Consolas, monospace; background: #eef0f4; padding: 1px 4px; border-radius: 3px; }
pre { background: #eef0f4; padding: 8px 10px; border-radius: 4px; overflow-wrap: anywhere; white-space: pre-wrap; font-size: 8.8pt; }
img { max-width: 100%; max-height: 225mm; width: auto; height: auto; object-fit: contain; display: block; border: 1px solid #d7dce3; border-radius: 4px; margin: 6px 0; page-break-inside: avoid; }
blockquote { border-left: 4px solid #c9a227; background: #fbf7e8; margin: 10px 0; padding: 6px 12px; }
.cover { height: 250mm; display: flex; flex-direction: column; justify-content: center; page-break-after: always; }
.cover .brand { color: #7a1020; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; font-size: 11pt; }
.cover h1 { font-size: 30pt; border: 0; margin: 10px 0 6px; page-break-before: avoid; color: #1c2430; }
.cover .sub { font-size: 14pt; color: #4a5565; }
.cover .ver { margin-top: 40px; color: #6b7686; font-size: 10pt; }
.cover hr { border: 0; border-top: 4px solid #7a1020; width: 90px; margin: 18px 0; }
.toc { page-break-after: always; }
.toc h2 { margin-top: 0; }
.toc ol { list-style: none; padding: 0; margin: 0; }
.toc li.d1 { font-weight: 600; margin-top: 7px; }
.toc li.d2 { margin-left: 18px; font-size: 9.5pt; }
.toc a { color: inherit; text-decoration: none; }
`;
const html = `<!doctype html><html><head><meta charset="utf-8"><title>${title}</title><style>${css}</style></head><body>
<section class="cover"><div class="brand">Quest International University · Student Life Division</div><h1>${title}</h1><hr><div class="sub">${subtitle}</div><div class="ver">${version}</div></section>
<section class="toc"><h2>Contents</h2><ol>${toc}</ol></section>
${body}</body></html>`;

const tmp = mkdtempSync(join(tmpdir(), 'pdf-'));
// Placed next to the input so relative image paths resolve.
const htmlPath = join(dir, `.build-${Date.now()}.html`);
writeFileSync(htmlPath, html);
const browser = await chromium.launch();
const page = await browser.newPage();
await page.goto('file://' + htmlPath, { waitUntil: 'networkidle' });
await page.pdf({
    path: output, format: 'A4', printBackground: true, displayHeaderFooter: true,
    headerTemplate: '<div></div>',
    footerTemplate: `<div style="font:8pt Helvetica,Arial,sans-serif;color:#6b7686;width:100%;padding:0 16mm;display:flex;justify-content:space-between"><span>QIU SLD Campus Hub · ${title}</span><span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span></div>`,
    margin: { top: '18mm', bottom: '20mm', left: '16mm', right: '16mm' },
});
await browser.close();
import('node:fs').then((fs) => { fs.unlinkSync(htmlPath); fs.rmSync(tmp, { recursive: true, force: true }); });
console.log('wrote', output);
