<?php

namespace App\Livewire;

use Livewire\Component;

class CompanySwitcher extends Component
{
    public function switchTo(int $companyId): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (! $user?->canAccessCompany($companyId)) {
            return;
        }

        session(['active_company_id' => $companyId]);
        $this->redirect(request()->header('Referer') ?? '/admin');
    }

    public function render()
    {
        if (! auth()->check()) {
            return '';
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return view('livewire.company-switcher', [
            'companies'     => $user->accessibleCompanies(),
            'activeCompany' => \App\Models\Company::find(session('active_company_id')),
        ]);
    }
}
