<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['club_id', 'session_id', 'kind', 'data', 'report_theme_id', 'pptx_path', 'pdf_path', 'generated_at', 'created_by'])]
class GeneratedFile extends Model
{
    public const ANNUAL_REPORT = 'annual_report';

    protected function casts(): array
    {
        return ['data' => 'array', 'generated_at' => 'datetime'];
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<ReportTheme, $this> */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(ReportTheme::class, 'report_theme_id');
    }

    /** @return BelongsTo<AcademicSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }
}
