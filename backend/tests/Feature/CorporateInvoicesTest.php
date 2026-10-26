<?php

use App\Models\CorporateClient;
use App\Models\CorporateInvoice;
use App\Models\CorporatePackage;
use App\Models\CorporateWashMove;
use App\Services\CorporateWashes;
use Illuminate\Support\Facades\Hash;

/** A company wash package made in the Packages menu. */
function ciPackage(CorporateClient $corp, array $fields = []): CorporatePackage
{
    $package = new CorporatePackage($fields + ['title' => '100 რეცხვა', 'washes' => 100, 'price' => 1500, 'is_active' => true]);
    $package->forceFill(['corporate_client_id' => $corp->id, 'type' => $fields['type'] ?? 'washes'])->save();

    return $package;
}

function ciSession(CorporateClient $corp): array
{
    return ['corporate_client_id' => $corp->id, 'corporate_session_version' => 1];
}

it('lets the company order its wash package and see a printable invoice', function () {
    config(['invoices.vat_rate' => 18, 'invoices.seller.name' => 'Autopass LLC', 'invoices.seller.identification_code' => '400000000']);
    $corp = cwFleet()['corp'];
    $corp->forceFill(['identification_code' => '205000000', 'legal_form' => 'შპს'])->save();
    $package = ciPackage($corp);

    $this->withSession(ciSession($corp))->get('/partner/invoices')->assertOk()
        ->assertSee('100 რეცხვა')->assertSee(__('admin.inv_order'));
    $this->withSession(ciSession($corp))->post('/partner/invoices', ['corporate_package_id' => $package->id])->assertSessionHasNoErrors();

    $invoice = CorporateInvoice::query()->sole();
    expect($invoice->number)->toBe('AP-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT))
        ->and($invoice->status)->toBe('issued')->and($invoice->ordered_by)->toBe('company')
        ->and($invoice->washes)->toBe(100)->and((string) $invoice->amount)->toBe('1500.00')
        ->and($invoice->buyer['identification_code'])->toBe('205000000')
        ->and($invoice->vatAmount())->toBe('228.81');

    // The invoice keeps the details it was issued with.
    $corp->forceFill(['identification_code' => '999'])->save();
    $package->update(['price' => 2000]);
    $this->withSession(ciSession($corp))->get('/partner/invoices/'.$invoice->id)->assertOk()
        ->assertSee($invoice->number)->assertSee('205000000')->assertSee('Autopass LLC')
        ->assertSee('1 500.00 ₾')->assertSee('228.81 ₾');
    // Nothing is credited before the payment is confirmed.
    expect(CorporateWashes::balance($corp)['total'])->toBe(0);

    // Another company sees neither the invoice nor can it order this package.
    $other = CorporateClient::forceCreate(['name' => 'O', 'username' => 'o4', 'password' => Hash::make('x'), 'session_version' => 1]);
    $this->withSession(ciSession($other))->get('/partner/invoices/'.$invoice->id)->assertNotFound();
    $this->withSession(ciSession($other))->post('/partner/invoices/'.$invoice->id.'/cancel')->assertNotFound();
    $this->withSession(ciSession($other))->post('/partner/invoices', ['corporate_package_id' => $package->id])
        ->assertSessionHasErrors('corporate_package_id');
});

it('orders only active wash packages with a price and caps unpaid invoices', function () {
    $corp = cwFleet()['corp'];
    $session = ciSession($corp);
    foreach ([['price' => null], ['is_active' => false], ['type' => 'discount', 'washes' => 0]] as $fields) {
        $package = ciPackage($corp, $fields);
        $this->withSession($session)->post('/partner/invoices', ['corporate_package_id' => $package->id])->assertSessionHasErrors('invoice');
    }
    expect(CorporateInvoice::query()->count())->toBe(0);

    $package = ciPackage($corp);
    foreach (range(1, 3) as $i) {
        $this->withSession($session)->post('/partner/invoices', ['corporate_package_id' => $package->id])->assertSessionHasNoErrors();
    }
    $this->withSession($session)->post('/partner/invoices', ['corporate_package_id' => $package->id])->assertSessionHasErrors('invoice');
    expect(CorporateInvoice::query()->count())->toBe(3);

    // Cancelling one frees a place; a cancelled invoice can't be cancelled again.
    $first = CorporateInvoice::query()->orderBy('id')->first();
    $this->withSession($session)->post('/partner/invoices/'.$first->id.'/cancel')->assertSessionHasNoErrors();
    $this->withSession($session)->post('/partner/invoices/'.$first->id.'/cancel')->assertSessionHasErrors('invoice');
    $this->withSession($session)->post('/partner/invoices', ['corporate_package_id' => $package->id])->assertSessionHasNoErrors();
    expect($first->fresh()->status)->toBe('cancelled');
});

it('credits the washes once when the admin marks the invoice paid', function () {
    $corp = cwFleet()['corp'];
    $admin = cwAdmin();
    $package = ciPackage($corp);

    $this->actingAs($admin)->post('/dashboard/corporate/'.$corp->id.'/invoices', ['corporate_package_id' => $package->id])->assertSessionHasNoErrors();
    $invoice = CorporateInvoice::query()->sole();
    expect($invoice->ordered_by)->toBe('admin');

    $this->actingAs($admin)->get('/dashboard/corporate/invoices')->assertOk()->assertSee($invoice->number)->assertSee('Wash Co');
    $this->actingAs($admin)->get('/dashboard/corporate')->assertOk()->assertSee(__('admin.inv_title'));

    $this->actingAs($admin)->post('/dashboard/corporate/invoices/'.$invoice->id.'/paid')->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/dashboard/corporate/invoices/'.$invoice->id.'/paid')->assertSessionHasErrors('invoice');
    $this->actingAs($admin)->post('/dashboard/corporate/invoices/'.$invoice->id.'/cancel')->assertSessionHasErrors('invoice');

    $invoice->refresh();
    expect($invoice->status)->toBe('paid')->and((int) $invoice->paid_by)->toBe((int) $admin->id)
        ->and(CorporateWashes::balance($corp)['total'])->toBe(100);
    $move = CorporateWashMove::query()->sole();
    expect($move->type)->toBe('credit')->and($move->amount)->toBe(100)
        ->and((int) $move->corporate_invoice_id)->toBe((int) $invoice->id);

    $this->actingAs($admin)->get('/dashboard/corporate/'.$corp->id)->assertOk()
        ->assertSee(__('admin.inv_credit_note', ['number' => $invoice->number]))
        ->assertSee(__('admin.inv_status_paid'));
    $this->actingAs($admin)->get('/dashboard/corporate/invoices/'.$invoice->id)->assertOk()->assertSee(__('admin.inv_status_paid'));
});

it('does not credit a cancelled invoice', function () {
    $corp = cwFleet()['corp'];
    $admin = cwAdmin();
    $package = ciPackage($corp);
    $this->withSession(ciSession($corp))->post('/partner/invoices', ['corporate_package_id' => $package->id]);
    $invoice = CorporateInvoice::query()->sole();

    $this->actingAs($admin)->post('/dashboard/corporate/invoices/'.$invoice->id.'/cancel')->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/dashboard/corporate/invoices/'.$invoice->id.'/paid')->assertSessionHasErrors('invoice');
    expect(CorporateWashes::balance($corp)['total'])->toBe(0)
        ->and(CorporateWashMove::query()->exists())->toBeFalse();
    $this->actingAs($admin)->get('/dashboard/corporate/invoices?status=cancelled')->assertOk()->assertSee($invoice->number);
});
