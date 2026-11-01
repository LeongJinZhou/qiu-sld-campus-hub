<?php

namespace App\Services\Finance;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Club;
use App\Models\Document;
use App\Models\Event;
use App\Models\User;
use App\Services\Time\SessionResolver;
use Carbon\CarbonImmutable;
use DomainException;

/**
 * 07 Fund Withdrawal/Transfer, for an event (items from its approved budget) or for the club (purpose stated,
 * 2 weeks ahead). Any committee member fills it in; the Treasurer (the event's, for an event) signs and submits.
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
     * An event withdrawal is filed under its event and limited to its approved budget; a club withdrawal states its
     * purpose. The bank account the money goes to is compulsory.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalise(Club $club, array $input): array
    {
        $scope = ($input['scope'] ?? (isset($input['event_id']) ? 'event' : 'club')) === 'event' ? 'event' : 'club';
        $event = $scope === 'event' && isset($input['event_id']) ? Event::where('club_id', $club->id)->find($input['event_id']) : null;
        if ($scope === 'event') {
            if (! $event) {
                throw new DomainException('Choose the event this withdrawal is for.');
            }
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
            if (trim((string) ($input['purpose'] ?? '')) === '') {
                throw new DomainException('State what the club withdrawal is for.');
            }
            $neededBy = CarbonImmutable::parse((string) ($input['needed_by'] ?? 'now'));
            if ($neededBy->lt(CarbonImmutable::today()->addDays(self::WEEKLY_LEAD_DAYS))) {
                throw new DomainException('Club withdrawals must be requested at least 2 weeks before the money is needed.');
            }
        }
        $bank = array_map(fn ($v) => trim((string) $v), (array) ($input['bank'] ?? []));
        if (($bank['bank_name'] ?? '') === '' || ($bank['account_name'] ?? '') === '' || ! preg_match('/^\d[\d\s-]{5,28}\d$/', $bank['account_number'] ?? '')) {
            throw new DomainException('Fill in the bank, the account name and the account number (digits only) the money goes to.');
        }

        $items = array_values(array_map(fn ($i) => [
            'description' => $i['description'], 'unit_sen' => (int) $i['unit_sen'], 'quantity' => (int) $i['quantity'],
            'amount_sen' => (int) $i['unit_sen'] * (int) $i['quantity'], 'method' => $i['method'] ?? 'self',
        ], $input['items']));
        $total = array_sum(array_column($items, 'amount_sen'));
        if ($total <= 0) {
            throw new DomainException('Add at least one item with a price.');
        }

        return [
            'scope' => $scope,
            'event_id' => $event?->id,
            'purpose' => $event ? "the {$event->name} event" : trim((string) $input['purpose']),
            'needed_by' => $event?->starts_at->toDateString() ?? (string) $input['needed_by'],
            'items' => $items,
            'bank' => ['bank_name' => $bank['bank_name'], 'account_name' => $bank['account_name'], 'account_number' => (string) preg_replace('/\D/', '', $bank['account_number'])],
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
