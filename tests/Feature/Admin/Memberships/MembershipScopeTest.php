<?php

use App\Domain\Billing\Models\Invoice;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Locations\Models\Location;
use App\Livewire\Admin\Members\MemberBilling;
use App\Livewire\Admin\Members\MemberIndex;
use App\Livewire\Admin\Memberships\MembershipIndex;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->a = Location::factory()->create(['code' => 'AAA']);
    $this->b = Location::factory()->create(['code' => 'BBB']);
    $this->adminA = staffUser(RoleName::LocationManager, [$this->a]);
    $this->managerB = staffUser(RoleName::LocationManager, [$this->b]);
    $this->member = memberAt($this->a, ['first_name' => 'Cliente', 'last_name' => 'Viajero']);
});

it('un plan para todas las sedes hace visible al cliente en todas', function () {
    Livewire::actingAs($this->managerB)->test(MemberIndex::class)->assertDontSee('Cliente Viajero');

    sellTo($this->member, planFor(['access_scope' => 'all_locations']), $this->a, $this->adminA);

    Livewire::actingAs($this->managerB)->test(MemberIndex::class)->assertSee('Cliente Viajero');
    $this->actingAs($this->managerB)->get(route('admin.members.show', $this->member))->assertOk();
    Livewire::actingAs($this->managerB)->test(MembershipIndex::class)->assertSee('Cliente Viajero');
});

it('un plan solo para la sede A no lo hace visible en B', function () {
    $plan = planFor(['access_scope' => 'selected_locations']);
    $plan->locations()->sync([$this->a->id]);
    sellTo($this->member, $plan, $this->a, $this->adminA);

    Livewire::actingAs($this->managerB)->test(MemberIndex::class)->assertDontSee('Cliente Viajero');
    $this->actingAs($this->managerB)->get(route('admin.members.show', $this->member))->assertNotFound();
});

it('una membresía vencida deja de dar visibilidad', function () {
    $m = sellTo($this->member, planFor(), $this->a, $this->adminA);
    $m->update(['status' => 'expired']);

    Livewire::actingAs($this->managerB)->test(MemberIndex::class)->assertDontSee('Cliente Viajero');
});

it('los comprobantes de la sede A no se ven desde B aunque el cliente sea visible', function () {
    sellTo($this->member, planFor(), $this->a, $this->adminA);
    $invoice = Invoice::query()->sole();

    $this->actingAs($this->managerB)->get(route('admin.invoices.show', $invoice))->assertNotFound();
    Livewire::actingAs($this->managerB)->test(MemberBilling::class, ['memberId' => $this->member->id])
        ->assertDontSee('AAA-000001')
        ->call('openPay', $invoice->id)
        ->assertNotFound();
});

it('no vende en una sede fuera de su alcance', function () {
    sellTo($this->member, planFor(), $this->a, $this->adminA, payment: null);

    expect(fn () => sellTo(memberAt($this->a), planFor(), $this->b, $this->adminA))
        ->toThrow(AuthorizationException::class);
});
