<?php

namespace App\Enums;

/** Who acts at each approval step (CLAUDE.md §6 Approval chains). */
enum ChainRole: string
{
    case Preparer = 'preparer';
    case Secretary = 'secretary';
    case President = 'president';
    case Advisor = 'advisor';
    case Dean = 'dean';
    case SldOfficer = 'sld_officer';
    case AssistantRegistrar = 'assistant_registrar';
    case Registrar = 'registrar';
    case Bursar = 'bursar';
    case Coo = 'coo';

    public function label(): string
    {
        return match ($this) {
            self::SldOfficer => 'SLD Officer',
            self::AssistantRegistrar => 'Assistant Registrar',
            self::Coo => 'COO',
            default => ucfirst($this->value),
        };
    }

    /** The staff approver role behind this step, if it is a staff step. */
    public function approverRole(): ?ApproverRole
    {
        return ApproverRole::tryFrom($this->value);
    }
}
