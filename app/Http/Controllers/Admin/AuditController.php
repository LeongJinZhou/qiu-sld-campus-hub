<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agm;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\Document;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** The audit log in plain language: what happened, to which club, event or person, and the details that changed. */
class AuditController extends Controller
{
    public function index(): Response
    {
        $page = AuditLog::with('actor:id,name')->latest('id')->paginate(50);
        $names = $this->names(collect($page->items()));

        return Inertia::render('Admin/Audit/Index', [
            'entries' => $page->through(fn (AuditLog $e) => [
                'id' => $e->id,
                'at' => $e->created_at?->format('j M Y, g:i A'),
                'actor' => $e->actor?->name ?? 'System',
                'action' => $this->action($e->action),
                'code' => $e->action,
                'subject' => $e->subject_type ? trim(Str::headline($e->subject_type).': '.($names[$e->subject_type][$e->subject_id] ?? "#{$e->subject_id}")) : null,
                'details' => $this->details($e->data ?? []),
            ]),
        ]);
    }

    /** "payment.cash_completed" → "Payment · Cash completed". */
    private function action(string $code): string
    {
        return collect(explode('.', $code))->map(fn (string $part) => Str::ucfirst(str_replace('_', ' ', $part)))->implode(' · ');
    }

    /**
     * Names for every subject on the page, one query per type.
     *
     * @param  Collection<int, AuditLog>  $entries
     * @return array<string, array<int, string>>
     */
    private function names(Collection $entries): array
    {
        $models = ['Club' => [Club::class, 'name'], 'Event' => [Event::class, 'name'], 'User' => [User::class, 'name'], 'Document' => [Document::class, 'type'], 'Agm' => [Agm::class, 'club_id']];
        $names = [];
        foreach ($entries->whereNotNull('subject_type')->groupBy('subject_type') as $type => $rows) {
            if (! isset($models[$type])) {
                continue;
            }
            [$class, $column] = $models[$type];
            $found = $class::query()->when(in_array($type, ['Document', 'Agm'], true), fn ($q) => $q->with('club:id,name'))->whereIn('id', $rows->pluck('subject_id')->unique())->get();
            $names[$type] = $found->mapWithKeys(fn ($m) => [$m->id => match ($type) {
                'Document' => $m->type->label().($m->club ? " · {$m->club->name}" : ''),
                'Agm' => 'AGM · '.($m->club->name ?? ''),
                default => (string) $m->{$column},
            }])->all();
        }

        return $names;
    }

    /**
     * Each recorded field as "Label: value". Amounts in sen are shown in ringgit.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function details(array $data): array
    {
        return collect($data)->map(function ($value, string $key) {
            $label = Str::ucfirst(str_replace('_', ' ', preg_replace('/_sen$/', '', $key)));
            $text = match (true) {
                str_ends_with($key, '_sen') && is_numeric($value) => 'RM '.number_format($value / 100, 2),
                is_bool($value) => $value ? 'Yes' : 'No',
                is_array($value) => collect($value)->map(fn ($v, $k) => is_int($k) ? (is_scalar($v) ? $v : json_encode($v)) : "{$k} ".(is_scalar($v) ? $v : json_encode($v)))->implode(', '),
                $value === null => '—',
                default => (string) $value,
            };

            return "{$label}: {$text}";
        })->values()->all();
    }
}
