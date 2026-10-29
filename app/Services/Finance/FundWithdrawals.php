<?php

namespace App\Services\Finance;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Club;
use App\Models\Document;
use App\Models\Event;
use App\Models\Payee;
use App\Models\User;
use App\Services\Time\SessionResolver;
use Carbon\CarbonImmutable;
use DomainException;

/**
 * 07 Fund Withdrawal/Transfer (CLAUDE.md §6 Finance): items come from the approved proposal budget; weekly
 * activities need the request 2 weeks ahead; events only once their proposal is approved; several payees.
 */
class FundWithdrawals
{
    public const WEEKLY_LEAD_DAYS = 14;

    /** Payment methods printed on the 07 form. Both are paid by transfer: to the vendor, or to the treasurer's account. */
    public const METHODS = ['vendor' => 'QIU to vendor', 'self' => 'Student self purchase'];

    public function __construct(private readonly SessionResolver $sessions) {}

    /**
     * Budget expense lines the club may draw on for an approved event (money in sen).
     *
     * @return list<array{description: string, unit_sen: int, quantity: int, amount_sen: int}>
     */
    public function budgetLines(Event $event): array
    {
        $data = $event->proposal?->data() ?? [];

        return array_values(array_map(fn (array $i) => [
            'description' => (string) $i['description'],
            'unit_sen' => (int) $i['price_sen'],
            'quantity' => (int) $i['quantity'],
            'amount_sen' => (int) $i['price_sen'] * (int) $i['quantity'],
        ], $data['budget']['expenses'] ?? []));
    }

    /**
     * Validates and normalises the form, checking every rule against records (never trusting totals from the browser).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalise(Club $club, array $input): array
    {
        $event = isset($input['event_id']) ? Event::where('club_id', $club->id)->find($input['event_id']) : null;
        if ($event) {
            if ($event->status !== DocumentStatus::Approved) {
                throw new DomainException('Fund withdrawal opens once the event proposal is approved.');
            }
            $lines = collect($this->budgetLines($event))->keyBy('description');
            foreach ($input['items'] as $item) {
                $line = $lines[$item['description']] ?? throw new DomainException("“{$item['description']}” is not in the approved budget.");
                if ((int) $item['unit_sen'] * (int) $item['quantity'] > $line['amount_sen']) {
                    throw new DomainException("“{$item['description']}” is more than the approved RM ".number_format($line['amount_sen'] / 100, 2).'.');
                }
            }
        } else {
            $neededBy = CarbonImmutable::parse((string) ($input['needed_by'] ?? 'now'));
            if ($neededBy->lt(CarbonImmutable::today()->addDays(self::WEEKLY_LEAD_DAYS))) {
                throw new DomainException('Weekly activity withdrawals must be requested at least 2 weeks before the money is needed.');
            }
        }

        $items = array_values(array_map(fn ($i) => [
            'description' => $i['description'], 'unit_sen' => (int) $i['unit_sen'], 'quantity' => (int) $i['quantity'],
            'amount_sen' => (int) $i['unit_sen'] * (int) $i['quantity'], 'method' => $i['method'],
        ], $input['items']));
        $total = array_sum(array_column($items, 'amount_sen'));
        $payees = Payee::where('club_id', $club->id)->whereIn('id', array_column($input['payees'], 'payee_id'))->pluck('id')->all();
        if (count($payees) !== count($input['payees'])) {
            throw new DomainException('Choose payees saved for this club.');
        }
        if ($input['payees'] === [] || array_sum(array_map(fn ($p) => (int) $p['amount_sen'], $input['payees'])) !== $total) {
            throw new DomainException('Payee amounts must add up to the total (RM '.number_format($total / 100, 2).').');
        }

        return [
            'event_id' => $event?->id,
            'purpose' => trim((string) $input['purpose']),
            'needed_by' => $event?->starts_at->toDateString() ?? (string) $input['needed_by'],
            'items' => $items,
            'payees' => array_values(array_map(fn ($p) => ['payee_id' => (int) $p['payee_id'], 'amount_sen' => (int) $p['amount_sen']], $input['payees'])),
            'total_sen' => $total,
        ];
    }

    public function draft(Club $club, User $user, ?Event $event): Document
    {
        return Document::create([
            'type' => DocumentType::FundWithdrawal,
            'club_id' => $club->id,
            'event_id' => $event?->id,
            'session_id' => $this->sessions->current()->id,
            'created_by' => $user->id,
        ]);
    }
}
