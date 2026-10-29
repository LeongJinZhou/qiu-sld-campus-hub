<?php

namespace App\Enums;

/** Staff approvers in the document chains. */
enum ApproverRole: string
{
    case AssistantRegistrar = 'assistant_registrar';
    case Registrar = 'registrar';
    case Bursar = 'bursar';
    case Coo = 'coo';
    case Dean = 'dean';

    public function label(): string
    {
        return match ($this) {
            self::AssistantRegistrar => 'Assistant Registrar',
            self::Coo => 'COO',
            default => ucfirst($this->value),
        };
    }
}
