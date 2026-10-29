<?php

/**
 * Minimal DOCX editor for making placeholder copies of the official templates. It only rewrites the text
 * of existing paragraphs (keeping the first run's formatting) and deletes sample rows, so layout is untouched.
 */
final class DocxPlaceholders
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public DOMDocument $dom;

    public DOMXPath $xp;

    public function __construct(private readonly string $source)
    {
        $zip = new ZipArchive;
        $zip->open($source);
        $this->dom = new DOMDocument;
        $this->dom->preserveWhiteSpace = true;
        $this->dom->loadXML((string) $zip->getFromName('word/document.xml'));
        $zip->close();
        $this->xp = new DOMXPath($this->dom);
        $this->xp->registerNamespace('w', self::W);
    }

    /** Top-level body element by index (paragraphs, tables and other blocks, in document order). */
    public function block(int $index): DOMElement
    {
        $node = $this->xp->query('/w:document/w:body/*')->item($index);
        if (! $node instanceof DOMElement) {
            throw new RuntimeException("No body block {$index}");
        }

        return $node;
    }

    public function paragraphContaining(string $text, int $occurrence = 0): DOMElement
    {
        $matches = [];
        foreach ($this->xp->query('//w:p') as $p) {
            if (str_contains($this->text($p), $text)) {
                $matches[] = $p;
            }
        }

        return $matches[$occurrence] ?? throw new RuntimeException("Paragraph containing '{$text}' #{$occurrence} not found");
    }

    /** @return list<DOMElement> */
    public function rows(DOMElement $table): array
    {
        return iterator_to_array($this->xp->query('w:tr', $table));
    }

    public function cell(DOMElement $table, int $row, int $col): DOMElement
    {
        return $this->xp->query('w:tc', $this->rows($table)[$row])->item($col) ?? throw new RuntimeException("Cell {$row},{$col} not found");
    }

    /** First paragraph of a table cell. */
    public function cellParagraph(DOMElement $table, int $row, int $col, int $paragraph = 0): DOMElement
    {
        return $this->xp->query('w:p', $this->cell($table, $row, $col))->item($paragraph) ?? throw new RuntimeException('Cell paragraph not found');
    }

    /** Replaces a paragraph's text with one run that keeps the first run's (or the paragraph's) formatting. */
    public function setText(DOMElement $p, string $text): void
    {
        $runs = iterator_to_array($this->xp->query('w:r', $p));
        $rPr = $runs[0] ?? null ? $this->xp->query('w:rPr', $runs[0])->item(0) : $this->xp->query('w:pPr/w:rPr', $p)->item(0);
        foreach ($runs as $run) {
            $p->removeChild($run);
        }
        foreach (iterator_to_array($this->xp->query('w:hyperlink|w:proofErr', $p)) as $other) {
            $p->removeChild($other);
        }

        $run = $this->dom->createElementNS(self::W, 'w:r');
        if ($rPr) {
            $copy = $this->dom->createElementNS(self::W, 'w:rPr');
            foreach ($rPr->childNodes as $child) {
                $copy->appendChild($child->cloneNode(true));
            }
            $run->appendChild($copy);
        }
        $t = $this->dom->createElementNS(self::W, 'w:t', htmlspecialchars($text, ENT_XML1));
        $t->setAttribute('xml:space', 'preserve');
        $run->appendChild($t);
        $p->appendChild($run);
    }

    /** Inserts a paragraph after $p with new text, formatted like $styleFrom (default: like $p itself). */
    public function insertAfter(DOMElement $p, string $text, ?DOMElement $styleFrom = null): DOMElement
    {
        $copy = ($styleFrom ?? $p)->cloneNode(true);
        $p->parentNode->insertBefore($copy, $p->nextSibling);
        $this->setText($copy, $text);

        return $copy;
    }

    public function removeRows(DOMElement $table, int $from, int $to): void
    {
        $rows = $this->rows($table);
        for ($i = $to; $i >= $from; $i--) {
            $table->removeChild($rows[$i]);
        }
    }

    public function text(DOMNode $node): string
    {
        $out = '';
        foreach ($this->xp->query('.//w:t', $node) as $t) {
            $out .= $t->textContent;
        }

        return $out;
    }

    public function save(string $target): void
    {
        copy($this->source, $target);
        $zip = new ZipArchive;
        $zip->open($target);
        $zip->addFromString('word/document.xml', $this->dom->saveXML());
        $zip->close();
    }
}
