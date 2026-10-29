<?php

namespace App\Services\Documents;

use App\Enums\ApprovalDecision;
use App\Enums\ChainRole;
use App\Models\Approval;
use App\Models\Document;
use App\Models\Payee;
use App\Models\Staff;
use App\Services\Finance\FundWithdrawals;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

/** Renders a fund withdrawal into the official 07 template. Bank details are decrypted only here. */
class FundWithdrawalDocx
{
    public const TEMPLATE = '07. Student Organization Fund Withdrawal and Transfer Form template.docx';

    private const SIGNERS = [ChainRole::Secretary, ChainRole::President, ChainRole::Advisor, ChainRole::AssistantRegistrar, ChainRole::Bursar, ChainRole::Coo];

    public function __construct(private readonly DocxRenderer $docx) {}

    public function render(Document $document): string
    {
        $document->loadMissing(['club', 'event', 'approvals.signature']);
        $d = $document->data();
        $output = storage_path("app/private/generated/fund-{$document->id}-".now()->format('YmdHis').'.docx');

        return $this->docx->render(self::TEMPLATE, function (TemplateProcessor $t) use ($document, $d): void {
            $rm = fn (int $sen) => number_format($sen / 100, 2);
            $t->setValues([
                'from' => $document->club->name,
                'date' => ($document->submitted_at ?? now())->format('j.n.Y'),
                'total' => $rm((int) ($d['total_sen'] ?? 0)),
                'purpose' => $document->event ? "the {$document->event->name} event that will be held on {$document->event->starts_at->format('j.n.Y')}" : (string) ($d['purpose'] ?? ''),
            ]);
            $t->cloneRowAndSetValues('item_no', array_map(fn (array $i, int $n) => [
                'item_no' => (string) ($n + 1), 'item_description' => $i['description'], 'item_price' => $rm($i['unit_sen']),
                'item_quantity' => (string) $i['quantity'], 'item_total' => $rm($i['amount_sen']), 'item_method' => FundWithdrawals::METHODS[$i['method']] ?? $i['method'],
            ], $d['items'] ?? [], array_keys($d['items'] ?? [])) ?: [array_fill_keys(['item_no', 'item_description', 'item_price', 'item_quantity', 'item_total', 'item_method'], '')]);

            $payees = Payee::whereIn('id', array_column($d['payees'] ?? [], 'payee_id'))->get()->keyBy('id');
            DocxRenderer::block($t, 'payees', array_map(function (array $p) use ($payees, $rm) {
                $payee = $payees[$p['payee_id']];

                return [
                    'payee_name' => $payee->name, 'payee_email' => (string) $payee->email, 'payee_contact' => (string) $payee->contact_no,
                    'payee_bank' => $payee->bank_name, 'payee_kind' => $payee->kind, 'payee_holder' => (string) $payee->account_holder,
                    'payee_account' => (string) $payee->account_number, 'payee_amount' => $rm($p['amount_sen']),
                ];
            }, $d['payees'] ?? []) ?: [array_fill_keys(['payee_name', 'payee_email', 'payee_contact', 'payee_bank', 'payee_kind', 'payee_holder', 'payee_account', 'payee_amount'], '')]);

            $approvals = $document->approvals->keyBy(fn (Approval $a) => $a->role->value);
            foreach (self::SIGNERS as $i => $role) {
                $k = $i + 1;
                $approval = $approvals[$role->value] ?? null;
                $signed = $approval?->decision === ApprovalDecision::Approved ? $approval->signature : null;
                $staff = $role->approverRole() ? Staff::where('approver_role', $role->approverRole())->where('active', true)->first() : null;
                $t->setValues([
                    "sig{$k}_name" => $signed->signer_name ?? $staff->name ?? '',
                    "sig{$k}_date" => $signed ? $signed->signed_at->format('d/m/Y g:i A')." · Verify {$signed->verification_code}" : '',
                ]);
                $image = $signed ? Storage::disk('local')->path($signed->image_path) : null;
                $image && is_file($image)
                    ? $t->setImageValue("sig{$k}_image", ['path' => $image, 'height' => 36, 'ratio' => true])
                    : $t->setValue("sig{$k}_image", '');
            }
        }, $output);
    }
}
