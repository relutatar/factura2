<div class="flex items-center gap-2 px-2">
    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
        {{ $activeCompany?->name ?? 'Selectează firma' }}
    </span>
    <x-filament::dropdown>
        <x-slot name="trigger">
            <x-filament::icon-button icon="heroicon-o-building-office-2" />
        </x-slot>
        <x-filament::dropdown.list>
            @foreach($companies as $company)
                <x-filament::dropdown.list.item wire:click="switchTo({{ $company->id }})">
                    {{ $company->name }}
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
</div>
