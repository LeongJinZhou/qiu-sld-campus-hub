# Template fidelity check

Run it any time a template or renderer changes (needs LibreOffice and python-pptx):

```bash
QA_TEMPLATES=1 php artisan test --filter=TemplateFidelity
```

It fills every official template with a full sample scenario (an outdoor event from proposal to post mortem, a fund withdrawal, minutes, ledger, members, AGM with election data and an annual report) and writes each result to `storage/qa/` as `NN-name-filled.pdf` next to `NN-name-original.pdf`, with `REPORT.md` listing page counts and any placeholder left unfilled. Nothing in `storage/qa/` is committed.

## Result (30 Oct 2026)

All 10 templates render with **no placeholders left**. Layout, fonts, tables, logos and signature blocks match the originals. Differences found and what was done:

| Template | Difference | Status |
| --- | --- | --- |
| 10 Full Member List | Filling the value into B1/B2 clipped the "Club Name:" / "Advisor:" labels (column A is narrow and the label overflows into B). | **Fixed**: value now follows the label in the same cell. |
| 02 Finance Record | The template is set to "fit to 1 page", so a long ledger shrank to unreadable text. | **Fixed**: fits 1 page wide, as many pages tall as needed. |
| 02 Finance Record | Original prints on 4 pages because its Balance column falls on separate pages; filled prints on 2 (fit to width). | Expected. |
| 05 Indemnity | Original is 1 page; filled has 1 page per signer (2 in the sample). | Expected: the download bundles every signed form. |
| 01 AGM Report | 3 → 5 pages: the attendance table lists every member. | Expected. |
| 03 Attendance List | 4 → 3 pages: the Pictures sheet is empty when the event album has no photos. | Expected. |
| 12 Annual Activities Report | 6 → 4 slides: the template's sample slides are removed; one event gives cover, summary, details and photos. | Expected. |
| 13 Minutes / 01 AGM | The "Club logo" box stays empty when a club's logo is SVG (Word cannot embed SVG). The demo clubs use SVG logos. | Open: ask clubs to upload a PNG/JPG logo. |
| 10 Full Member List | Long names and emails are cut at the template's column widths. | Template layout, left unchanged (CLAUDE.md: never change layout). SLD may widen the columns in the original if wanted. |
