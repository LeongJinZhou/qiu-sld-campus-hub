"""Annual Activities Report from the official 12 template (CLAUDE.md §6 Annual Activities Report).

Usage: python annual_report.py <job.json>
job.json: {"template", "output", "club", "events": [{"name", "date", "time", "venue", "details", "photos": [path]}],
           "theme": {"background_color", "title_color", "body_color", "font", "background_path"} | null}

Slides: cover, summary table (No, Event Name, Event Date; 8 rows per slide), then per event a details slide and an
Event Photos slide (up to 6 photos in a grid). The template's own slides are reused, so its layout never changes.
"""
import copy
import json
import sys

from PIL import Image
from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.util import Emu

COVER, SAMPLE_DETAILS, SAMPLE_PHOTOS, SUMMARY, DETAILS, PHOTOS = range(6)
ROWS_PER_SUMMARY = 8
PHOTO_AREA = (311700, 1017725, 8520600, 3975775)  # left, top, width, height under the "Event Photos" title
GAP = 76200


def duplicate(prs, source):
    """Appends a copy of a text/table slide (the duplicated template slides hold no pictures)."""
    slide = prs.slides.add_slide(source.slide_layout)
    for shape in list(slide.shapes):
        shape._element.getparent().remove(shape._element)
    for shape in source.shapes:
        slide.shapes._spTree.append(copy.deepcopy(shape._element))
    return slide


def remove(prs, slide):
    ids = prs.slides._sldIdLst
    for sld_id in list(ids):
        if prs.slides.get(int(sld_id.get("id"))) is slide:
            prs.part.drop_rel(sld_id.rId)
            ids.remove(sld_id)


def set_text(paragraph, text):
    runs = paragraph.runs
    if runs:
        runs[0].text = text
        for extra in runs[1:]:
            extra._r.getparent().remove(extra._r)
    else:
        paragraph.text = text


def fill_summary(slide, events, offset):
    table = next(s for s in slide.shapes if s.has_table).table
    template_row = table._tbl.tr_lst[1]
    for tr in table._tbl.tr_lst[1:]:
        table._tbl.remove(tr)
    for i, event in enumerate(events):
        tr = copy.deepcopy(template_row)
        table._tbl.append(tr)
        row = table.rows[len(table.rows) - 1]
        for cell, value in zip(row.cells, [str(offset + i + 1), event["name"], event["date"]]):
            set_text(cell.text_frame.paragraphs[0], value)


def fill_details(slide, event):
    title, body = [s for s in slide.shapes if s.has_text_frame][:2]
    set_text(title.text_frame.paragraphs[0], "Event Name: " + event["name"])
    labels = ["Date: ", "Time: ", "Venue: ", "Details: "]
    for paragraph, label, value in zip(body.text_frame.paragraphs, labels, [event["date"], event["time"], event["venue"], event["details"]]):
        set_text(paragraph, label + (value or ""))


def fill_photos(slide, photos):
    photos = photos[:6]
    if not photos:
        return
    cols = 1 if len(photos) == 1 else 2 if len(photos) <= 4 else 3
    rows = (len(photos) + cols - 1) // cols
    left, top, width, height = PHOTO_AREA
    cell_w = (width - GAP * (cols - 1)) // cols
    cell_h = (height - GAP * (rows - 1)) // rows
    for i, path in enumerate(photos):
        with Image.open(path) as im:
            ratio = im.width / im.height
        w, h = (cell_w, int(cell_w / ratio)) if cell_w / ratio <= cell_h else (int(cell_h * ratio), cell_h)
        x = left + (i % cols) * (cell_w + GAP) + (cell_w - w) // 2
        y = top + (i // cols) * (cell_h + GAP) + (cell_h - h) // 2
        slide.shapes.add_picture(path, Emu(x), Emu(y), Emu(w), Emu(h))


def apply_theme(prs, theme):
    if not theme:
        return
    for slide in prs.slides:
        slide.background.fill.solid()
        slide.background.fill.fore_color.rgb = RGBColor.from_string(theme["background_color"].lstrip("#"))
        if theme.get("background_path"):
            picture = slide.shapes.add_picture(theme["background_path"], 0, 0, prs.slide_width, prs.slide_height)
            slide.shapes._spTree.remove(picture._element)
            slide.shapes._spTree.insert(2, picture._element)  # behind everything else
        for shape in slide.shapes:
            frames = [shape.text_frame] if shape.has_text_frame else [c.text_frame for r in shape.table.rows for c in r.cells] if shape.has_table else []
            is_title = shape.is_placeholder and shape.placeholder_format.idx == 0
            for frame in frames:
                for paragraph in frame.paragraphs:
                    for run in paragraph.runs:
                        run.font.name = theme["font"]
                        run.font.color.rgb = RGBColor.from_string((theme["title_color"] if is_title else theme["body_color"]).lstrip("#"))


def main(job_path):
    with open(job_path, encoding="utf-8") as fh:
        job = json.load(fh)
    prs = Presentation(job["template"])
    originals = list(prs.slides)

    set_text([s for s in originals[COVER].shapes if s.has_text_frame][1].text_frame.paragraphs[0], job["club"])

    events = job["events"]
    chunks = [events[i:i + ROWS_PER_SUMMARY] for i in range(0, len(events), ROWS_PER_SUMMARY)] or [[]]
    for n, chunk in enumerate(chunks):
        fill_summary(duplicate(prs, originals[SUMMARY]), chunk, n * ROWS_PER_SUMMARY)
    for event in events:
        fill_details(duplicate(prs, originals[DETAILS]), event)
        fill_photos(duplicate(prs, originals[PHOTOS]), event.get("photos", []))

    for index in (SAMPLE_DETAILS, SAMPLE_PHOTOS, SUMMARY, DETAILS, PHOTOS):
        remove(prs, originals[index])
    apply_theme(prs, job.get("theme"))
    prs.save(job["output"])


if __name__ == "__main__":
    main(sys.argv[1])
