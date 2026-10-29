<?php

namespace App\Services\Documents;

use App\Enums\ScopedRole;
use App\Models\Event;
use App\Models\Indemnity;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * 05 SLD Indemnity Form, rebuilt as DOCX with the original layout (QIU logo, black title bar, the same
 * table rows and the full consent text), one page per signer, then converted to a single PDF.
 */
class IndemnityPdf
{
    public const CONSENT = [
        'I am signing this indemnity form so that I can participate in the activity described above. This indemnity statement covers all events, and occurrences associated with the activities, including any associated travel, meals, and loading. I understand that if I have concerns about my health or ability to participate, it is my responsibility to discuss my concerns with a doctor before deciding to participate.',
        'I understand and acknowledge that participating in events/activities organised externally by Quest International University may carry certain risks such as physical injury, personal injury, property damage or illness which may occur through known and unknown causes which is not the fault of QIU staff/coach/event committee or other QIU employee/students who were involved with the activity. I fully understand that the program may be strenuous and choose to participate voluntarily. I accept full responsibility for my own safety and will not act in a manner that will bring harm to me and to others.',
    ];

    private const FONT = ['name' => 'Times New Roman', 'size' => 11];

    private const LABEL_WIDTH = 2100;

    private const VALUE_WIDTH = 7900;

    public function __construct(private readonly PdfConverter $pdf) {}

    public function render(Event $event): string
    {
        $event->load(['club', 'indemnities.user.student', 'indemnities.signature']);
        $advisor = $event->club->roleAssignments()->where('session_id', $event->session_id)->where('role', ScopedRole::Advisor)->first()?->user;

        $word = new PhpWord;
        $word->setDefaultFontName(self::FONT['name']);
        $word->setDefaultFontSize(self::FONT['size']);

        foreach ($event->indemnities->sortBy(fn (Indemnity $i) => $i->user->name) as $indemnity) {
            $this->page($word->addSection(['marginTop' => 500, 'marginBottom' => 500, 'marginLeft' => 1300, 'marginRight' => 1300]), $event, $indemnity, $advisor?->name);
        }
        if ($event->indemnities->isEmpty()) {
            $word->addSection()->addText('No indemnity forms have been signed yet.');
        }

        $docx = storage_path("app/private/generated/indemnity-{$event->id}-".now()->format('YmdHis').'.docx');
        if (! is_dir(dirname($docx))) {
            mkdir(dirname($docx), 0775, true);
        }
        IOFactory::createWriter($word, 'Word2007')->save($docx);
        $pdf = $this->pdf->convert($docx);
        @unlink($docx);

        return $pdf;
    }

    private function page(Section $section, Event $event, Indemnity $i, ?string $staffInCharge): void
    {
        $section->addImage(public_path('brand/QIU_Wordmark.jpg'), ['width' => 110, 'alignment' => Jc::CENTER]);
        $bar = $section->addTable(['bgColor' => '231F20', 'cellMargin' => 40]);
        $bar->addRow();
        $bar->addCell(self::LABEL_WIDTH + self::VALUE_WIDTH, ['bgColor' => '231F20'])
            ->addText('STUDENT LIFE DIVISION – INDEMNITY FORM', ['name' => 'Arial', 'bold' => true, 'size' => 13, 'color' => 'FFFFFF']);
        $section->addText('', ['size' => 6]);

        $table = $section->addTable(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 30]);
        $this->heading($table, 'Activity Details');
        $this->row($table, 'Name of Activity:', $event->name);
        $this->row($table, 'Venue:', (string) $event->venue);
        $this->row($table, 'Date (s) of travel:', $event->starts_at->format('j M Y').($event->ends_at->isSameDay($event->starts_at) ? '' : ' – '.$event->ends_at->format('j M Y')));
        $this->row($table, 'Organizer:', $event->club->name);
        $this->row($table, 'Staff In charge:', (string) $staffInCharge);
        $this->wide($table, ['Remarks:', '', '']);

        $this->heading($table, 'Emergency Contact Person');
        $this->row($table, 'Name of the Emergency contact person:', $i->emergency_name);
        $this->row($table, 'Home/Mobile Phone Number:', $i->emergency_mobile_phone);
        $this->row($table, 'Work Phone Number:', (string) $i->emergency_work_phone);
        $this->row($table, 'Relationship:', $i->emergency_relationship);
        $this->wide($table, [
            'If the emergency contact person is not available, please contact:',
            'Name: '.($i->alternate_name ?? ''),
            'Phone Number: '.($i->alternate_phone ?? ''),
        ]);

        $this->heading($table, 'Participant Consent');
        $this->wide($table, self::CONSENT, justify: true);
        $student = $i->user->student;
        $this->row($table, 'Name:', $i->user->name);
        $this->row($table, 'Student ID:', (string) $student?->student_number);
        $this->row($table, 'Programme:', (string) $student?->programme);
        $this->row($table, 'Contact Number:', $i->contact_number);
        $this->row($table, 'Address:', $i->address);
        $this->row($table, 'Date:', $i->signed_at->format('d/m/Y g:i A'));

        $table->addRow();
        $cell = $table->addCell(self::LABEL_WIDTH + self::VALUE_WIDTH, ['gridSpan' => 2]);
        $cell->addText('Signature:');
        $image = Storage::disk('local')->path($i->signature->image_path);
        if (is_file($image)) {
            $cell->addImage($image, ['height' => 32]);
        }
        $cell->addText('----------------------------------');
        $cell->addText("Verify: {$i->signature->verification_code} · ".route('verify', $i->signature->verification_code), ['size' => 8, 'color' => '555555']);
    }

    private function heading(Table $table, string $text): void
    {
        $table->addRow();
        $table->addCell(self::LABEL_WIDTH + self::VALUE_WIDTH, ['gridSpan' => 2])->addText($text, ['bold' => true]);
    }

    private function row(Table $table, string $label, string $value): void
    {
        $table->addRow();
        $table->addCell(self::LABEL_WIDTH)->addText($label);
        $table->addCell(self::VALUE_WIDTH)->addText($value);
    }

    /** @param list<string> $lines */
    private function wide(Table $table, array $lines, bool $justify = false): void
    {
        $table->addRow();
        $cell = $table->addCell(self::LABEL_WIDTH + self::VALUE_WIDTH, ['gridSpan' => 2]);
        foreach ($lines as $line) {
            $cell->addText($line, [], $justify ? ['alignment' => Jc::BOTH] : []);
        }
    }
}
