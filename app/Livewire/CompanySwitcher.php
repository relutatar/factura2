<?php

namespace App\Livewire;

use Livewire\Component;

class CompanySwitcher extends Component
{
    public function switchTo(int $companyId): void
    {
        session(['active_company_id' => $companyId]);
        $this->redirect(request()->header('Referer') ?? '/admin');
    }

    public function render()
    {
        if (! auth()->check()) {
            return '';
        }

        return view('livewire.company-switcher', [
            'companies'     => \App\Models\Company::all(),
            'activeCompany' => \App\Models\Company::find(session('active_company_id')),
        ]);
    }
}
