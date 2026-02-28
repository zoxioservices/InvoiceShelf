<?php

use App\Models\BankAccount;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Tax;
use App\Models\User;
use App\Services\PaymentQrCode\IbanValidator;
use App\Services\PaymentQrCode\PayBySquareQrCodeGenerator;
use App\Services\PaymentQrCode\PaymentQrCodeService;
use App\Services\PaymentQrCode\SepaEpcQrCodeGenerator;
use App\Services\PaymentQrCode\SpaydQrCodeGenerator;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->withHeaders([
        'company' => $user->companies()->first()->id,
    ]);
    Sanctum::actingAs(
        $user,
        ['*']
    );
});

test('get bank accounts', function () {
    BankAccount::factory()->count(3)->create();

    $response = getJson('api/v1/bank-accounts?limit=all');

    $response->assertOk();
    $response->assertJsonCount(3, 'data');
});

test('create bank account', function () {
    $data = BankAccount::factory()->raw();

    $response = postJson('api/v1/bank-accounts', $data);

    $response->assertOk();

    $this->assertDatabaseHas('bank_accounts', [
        'name' => $data['name'],
        'account_holder_name' => $data['account_holder_name'],
        'qr_code_type' => 'sepa_epc',
    ]);
});

test('create bank account with invalid IBAN fails validation', function () {
    $data = BankAccount::factory()->raw([
        'details' => [
            'iban' => 'INVALIDIBAN',
            'bic' => 'COBADEFFXXX',
        ],
    ]);

    $response = postJson('api/v1/bank-accounts', $data);

    $response->assertStatus(422);
});

test('update bank account', function () {
    $bankAccount = BankAccount::factory()->create();

    $data = [
        'id' => $bankAccount->id,
        'name' => 'Updated Account Name',
        'account_holder_name' => $bankAccount->account_holder_name,
        'qr_code_type' => 'sepa_epc',
        'details' => $bankAccount->details,
    ];

    $response = putJson("api/v1/bank-accounts/{$bankAccount->id}", $data);

    $response->assertOk();

    $this->assertDatabaseHas('bank_accounts', [
        'id' => $bankAccount->id,
        'name' => 'Updated Account Name',
    ]);
});

test('delete bank account', function () {
    $bankAccount = BankAccount::factory()->create();

    $response = postJson('api/v1/bank-accounts/delete', [
        'ids' => [$bankAccount->id],
    ]);

    $response->assertOk();

    $this->assertDatabaseMissing('bank_accounts', [
        'id' => $bankAccount->id,
    ]);
});

test('set bank account as default unsets other defaults', function () {
    $first = BankAccount::factory()->default()->create();
    $second = BankAccount::factory()->create();

    expect($first->fresh()->is_default)->toBeTrue();
    expect($second->fresh()->is_default)->toBeFalse();

    $second->setAsDefault();

    expect($first->fresh()->is_default)->toBeFalse();
    expect($second->fresh()->is_default)->toBeTrue();
});

test('get payment qr types', function () {
    $response = getJson('api/v1/payment-qr-types');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            '*' => ['type', 'label', 'available', 'supported_currencies', 'fields', 'invoice_fields'],
        ],
    ]);

    $types = collect($response->json('data'));
    expect($types->pluck('type')->toArray())->toContain('sepa_epc', 'spayd', 'pay_by_square');

    $spayd = $types->firstWhere('type', 'spayd');
    expect(collect($spayd['fields'])->pluck('key')->toArray())->not->toContain('variable_symbol');
    expect(collect($spayd['invoice_fields'])->pluck('key')->toArray())->toContain('variable_symbol');

    $payBySquare = $types->firstWhere('type', 'pay_by_square');
    $payBySquareFieldKeys = collect($payBySquare['fields'])->pluck('key')->toArray();
    expect($payBySquareFieldKeys)->not->toContain('variable_symbol', 'specific_symbol', 'constant_symbol');
    $payBySquareInvoiceFieldKeys = collect($payBySquare['invoice_fields'])->pluck('key')->toArray();
    expect($payBySquareInvoiceFieldKeys)->toContain('variable_symbol', 'specific_symbol', 'constant_symbol');
});

test('IBAN validator accepts valid IBANs', function () {
    expect(IbanValidator::isValid('DE89370400440532013000'))->toBeTrue();
    expect(IbanValidator::isValid('CZ6508000000192000145399'))->toBeTrue();
    expect(IbanValidator::isValid('SK3112000000198742637541'))->toBeTrue();
    expect(IbanValidator::isValid('GB29 NWBK 6016 1331 9268 19'))->toBeTrue();
});

test('IBAN validator rejects invalid IBANs', function () {
    expect(IbanValidator::isValid('DE00000000000000000000'))->toBeFalse();
    expect(IbanValidator::isValid('INVALIDIBAN'))->toBeFalse();
    expect(IbanValidator::isValid(''))->toBeFalse();
    expect(IbanValidator::isValid('XX00'))->toBeFalse();
});

test('SEPA EPC generator produces correct payload', function () {
    $bankAccount = BankAccount::factory()->create([
        'account_holder_name' => 'John Doe',
        'details' => [
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
        ],
    ]);

    $invoice = Invoice::factory()->create([
        'due_amount' => 10050,
        'invoice_number' => 'INV-001',
        'company_id' => $bankAccount->company_id,
    ]);

    $generator = new SepaEpcQrCodeGenerator;
    $payload = $generator->generatePayload($invoice, $bankAccount);

    expect($payload)->toContain('BCD');
    expect($payload)->toContain('SCT');
    expect($payload)->toContain('COBADEFFXXX');
    expect($payload)->toContain('John Doe');
    expect($payload)->toContain('DE89370400440532013000');
    expect($payload)->toContain('EUR100.50');
    expect($payload)->toContain('INV-001');
});

test('SPAYD generator produces correct payload', function () {
    $bankAccount = BankAccount::factory()->spayd()->create([
        'account_holder_name' => 'Jan Novak',
    ]);

    $invoice = Invoice::factory()->create([
        'due_amount' => 5000,
        'invoice_number' => 'INV-002',
        'company_id' => $bankAccount->company_id,
        'payment_details' => ['variable_symbol' => '2024001'],
    ]);

    $generator = new SpaydQrCodeGenerator;
    $payload = $generator->generatePayload($invoice, $bankAccount);

    expect($payload)->toStartWith('SPD*1.0');
    expect($payload)->toContain('ACC:CZ6508000000192000145399+GIBACZPX');
    expect($payload)->toContain('AM:50.00');
    expect($payload)->toContain('MSG:INV-002');
    expect($payload)->toContain('X-VS:2024001');
});

test('SPAYD generator omits variable symbol when not set', function () {
    $bankAccount = BankAccount::factory()->spayd()->create();

    $invoice = Invoice::factory()->create([
        'due_amount' => 1000,
        'company_id' => $bankAccount->company_id,
    ]);

    $generator = new SpaydQrCodeGenerator;
    $payload = $generator->generatePayload($invoice, $bankAccount);

    expect($payload)->not->toContain('X-VS:');
});

test('Pay by Square generator uses invoice payment_details for symbols', function () {
    $bankAccount = BankAccount::factory()->payBySquare()->create();

    $invoice = Invoice::factory()->create([
        'due_amount' => 15000,
        'invoice_number' => 'INV-003',
        'company_id' => $bankAccount->company_id,
        'payment_details' => [
            'variable_symbol' => '123',
            'specific_symbol' => '456',
            'constant_symbol' => '0308',
        ],
    ]);

    $generator = new PayBySquareQrCodeGenerator;
    $payload = $generator->generatePayload($invoice, $bankAccount);

    expect($payload)->not->toBeEmpty();
});

test('payment QR code service generates base64 image', function () {
    $bankAccount = BankAccount::factory()->create();

    $invoice = Invoice::factory()->create([
        'due_amount' => 10000,
        'company_id' => $bankAccount->company_id,
    ]);

    $service = app(PaymentQrCodeService::class);
    $result = $service->generateQrCode($invoice, $bankAccount);

    expect($result)->not->toBeNull();
    expect($result)->toContain('data:image/png;base64,');
});

test('create invoice with bank account', function () {
    $bankAccount = BankAccount::factory()->create();

    $invoice = Invoice::factory()->raw([
        'items' => [InvoiceItem::factory()->raw()],
        'taxes' => [Tax::factory()->raw()],
        'bank_account_id' => $bankAccount->id,
    ]);

    $response = postJson('api/v1/invoices', $invoice);

    $response->assertOk();

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => $invoice['invoice_number'],
        'bank_account_id' => $bankAccount->id,
    ]);
});

test('invoice resource includes bank account data', function () {
    $bankAccount = BankAccount::factory()->create();

    $invoice = Invoice::factory()->create([
        'bank_account_id' => $bankAccount->id,
        'company_id' => $bankAccount->company_id,
    ]);

    $response = getJson("api/v1/invoices/{$invoice->id}");

    $response->assertOk();
    $response->assertJsonPath('data.bank_account_id', $bankAccount->id);
    $response->assertJsonPath('data.bank_account.id', $bankAccount->id);
});

test('bank account display label is generated', function () {
    $bankAccount = BankAccount::factory()->create([
        'details' => [
            'iban' => 'DE89370400440532013000',
        ],
    ]);

    $response = getJson("api/v1/bank-accounts/{$bankAccount->id}");

    $response->assertOk();
    $response->assertJsonPath('data.display_label', 'DE89••••3000');
});

test('SPAYD bank account details do not contain symbol fields', function () {
    $bankAccount = BankAccount::factory()->spayd()->create();

    $details = $bankAccount->details;
    expect($details)->not->toHaveKey('variable_symbol');
    expect($details)->toHaveKey('iban');
    expect($details)->toHaveKey('bic');
});

test('Pay by Square bank account details do not contain symbol fields', function () {
    $bankAccount = BankAccount::factory()->payBySquare()->create();

    $details = $bankAccount->details;
    expect($details)->not->toHaveKey('variable_symbol');
    expect($details)->not->toHaveKey('specific_symbol');
    expect($details)->not->toHaveKey('constant_symbol');
    expect($details)->toHaveKey('iban');
    expect($details)->toHaveKey('bic');
});

test('invoice resource includes payment_details', function () {
    $bankAccount = BankAccount::factory()->spayd()->create();

    $invoice = Invoice::factory()->create([
        'bank_account_id' => $bankAccount->id,
        'company_id' => $bankAccount->company_id,
        'payment_details' => ['variable_symbol' => '9999'],
    ]);

    $response = getJson("api/v1/invoices/{$invoice->id}");

    $response->assertOk();
    $response->assertJsonPath('data.payment_details.variable_symbol', '9999');
});

test('SEPA EPC generator reports EUR as supported currency', function () {
    $generator = new SepaEpcQrCodeGenerator;

    expect($generator->getSupportedCurrencies())->toBe(['EUR']);
});

test('SPAYD generator supports any currency', function () {
    $generator = new SpaydQrCodeGenerator;

    expect($generator->getSupportedCurrencies())->toBeNull();
});

test('Pay by Square generator reports EUR as supported currency', function () {
    $generator = new PayBySquareQrCodeGenerator;

    expect($generator->getSupportedCurrencies())->toBe(['EUR']);
});

test('QR code service returns null for currency-incompatible invoice', function () {
    $bankAccount = BankAccount::factory()->create(); // SEPA EPC = EUR only

    $czk = Currency::where('code', 'CZK')->first()
        ?? Currency::factory()->create(['code' => 'CZK']);

    $invoice = Invoice::factory()->create([
        'due_amount' => 10000,
        'currency_id' => $czk->id,
        'company_id' => $bankAccount->company_id,
    ]);

    $service = app(PaymentQrCodeService::class);
    $result = $service->generateQrCode($invoice, $bankAccount);

    expect($result)->toBeNull();
});

test('QR code service generates QR for currency-compatible invoice', function () {
    $bankAccount = BankAccount::factory()->create(); // SEPA EPC = EUR only

    $eur = Currency::where('code', 'EUR')->first()
        ?? Currency::factory()->create(['code' => 'EUR']);

    $invoice = Invoice::factory()->create([
        'due_amount' => 10000,
        'currency_id' => $eur->id,
        'company_id' => $bankAccount->company_id,
    ]);

    $service = app(PaymentQrCodeService::class);
    $result = $service->generateQrCode($invoice, $bankAccount);

    expect($result)->not->toBeNull();
    expect($result)->toContain('data:image/png;base64,');
});
