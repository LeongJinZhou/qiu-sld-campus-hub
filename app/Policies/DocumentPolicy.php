<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Services\Approvals\ApproverResolver;
use App\Services\Approvals\Chains;
use App\Services\Roles\RoleService;

class DocumentPolicy
{
    public function __construct(
        private readonly RoleService $roles,
        private readonly Chains $chains,
        private readonly ApproverResolver $approvers,
    ) {}

    /** The club side, SLD, and anyone who signs somewhere in the chain. */
    public function view(User $user, Document $document): bool
    {
        if ($user->isSldAdmin() || $this->roles->belongsToClub($user, $document->club, $document->session)) {
            return true;
        }
        if ($document->event && $user->can('organises', $document->event)) {
            return true;
        }

        foreach ($this->chains->for($document) as $role) {
            if ($this->approvers->canAct($user, $document, $role)) {
                return true;
            }
        }

        return false;
    }
}
