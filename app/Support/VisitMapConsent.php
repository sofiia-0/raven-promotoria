<?php

namespace App\Support;

use App\Enums\UserRole;

trait VisitMapConsent
{
    private function mapConsentKey(): string
    {
        $actor = auth()->user();
        abort_unless($actor && $actor->role === UserRole::PROMOTER && $actor->is_active, 403);

        return 'promoter.maps.authorized.'.$actor->id;
    }

    public function authorizeVisitMap(): bool
    {
        session()->put($this->mapConsentKey(), true);

        return true;
    }

    public function revokeVisitMap(): bool
    {
        session()->forget($this->mapConsentKey());

        return true;
    }

    private function mapAuthorized(): bool
    {
        return session()->get($this->mapConsentKey(), false) === true;
    }
}
