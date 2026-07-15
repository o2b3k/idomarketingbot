<?php

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Pages\EditLead;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\User;
use App\Modules\Leads\Enums\LeadStatus;
use App\Modules\Leads\Models\Lead;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('lead resource page displays captured leads', function () {
    $leads = Lead::factory()->count(3)->create();

    $this->get(LeadResource::getUrl('index'))->assertOk();

    Livewire::test(ListLeads::class)
        ->assertOk()
        ->assertCanSeeTableRecords($leads);
});

test('lead table can search and filter records', function () {
    $qualifiedLead = Lead::factory()->create([
        'name' => 'Алия Тестовая',
        'company' => 'Acme Unique',
        'status' => LeadStatus::Qualified,
    ]);
    $newLead = Lead::factory()->create([
        'name' => 'Другой пользователь',
        'company' => 'Nomad',
        'status' => LeadStatus::New,
    ]);

    Livewire::test(ListLeads::class)
        ->searchTable('Acme Unique')
        ->assertCanSeeTableRecords([$qualifiedLead])
        ->assertCanNotSeeTableRecords([$newLead]);

    Livewire::test(ListLeads::class)
        ->filterTable('status', LeadStatus::Qualified->value)
        ->assertCanSeeTableRecords([$qualifiedLead])
        ->assertCanNotSeeTableRecords([$newLead]);
});

test('manager can update lead status and contact details', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);

    Livewire::test(EditLead::class, ['record' => $lead->getRouteKey()])
        ->fillForm([
            'name' => 'Обновлённое имя',
            'phone' => '+996555112233',
            'company' => 'Updated Company',
            'status' => LeadStatus::Contacted->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($lead->refresh()->name)->toBe('Обновлённое имя')
        ->and($lead->phone)->toBe('+996555112233')
        ->and($lead->company)->toBe('Updated Company')
        ->and($lead->status)->toBe(LeadStatus::Contacted);
});
