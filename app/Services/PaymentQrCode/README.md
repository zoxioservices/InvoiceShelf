# Payment QR Code Generators

This directory contains the payment QR code generation system for InvoiceShelf.

## Architecture

- `PaymentQrCodeService` - Orchestrates QR code generation, resolves generators by type
- `PaymentQrCodeGeneratorInterface` (in `app/Contracts/`) - Interface all generators implement
- `IbanValidator` - Shared IBAN checksum validator (modulo-97)
- Generator classes: `SepaEpcQrCodeGenerator`, `SpaydQrCodeGenerator`, `PayBySquareQrCodeGenerator`

## Adding a New QR Code Standard

To add support for a new payment QR code standard (e.g., Swiss QR-bill, UPI, PIX):

### 1. Create the Generator Class

Create a new file in this directory, e.g. `SwissQrBillGenerator.php`:

```php
<?php

namespace App\Services\PaymentQrCode;

use App\Contracts\PaymentQrCodeGeneratorInterface;
use App\Models\BankAccount;
use App\Models\Invoice;

class SwissQrBillGenerator implements PaymentQrCodeGeneratorInterface
{
    public function getType(): string { return 'swiss_qr'; }
    public function getLabel(): string { return 'Swiss QR-bill'; }
    public function supports(string $qrCodeType): bool { return $qrCodeType === 'swiss_qr'; }
    
    public function checkAvailability(): array
    {
        // Return ['available' => false, 'reason' => '...'] if system deps are missing
        return ['available' => true, 'reason' => null];
    }

    public function getSupportedCurrencies(): ?array
    {
        return ['CHF', 'EUR']; // or null for any currency
    }

    public function getRequiredFields(): array
    {
        // These drive the frontend form dynamically - no frontend changes needed
        return [
            ['key' => 'iban', 'label' => 'IBAN / QR-IBAN', 'type' => 'text', 'required' => true],
            ['key' => 'qr_reference', 'label' => 'QR Reference', 'type' => 'text', 'required' => false],
        ];
    }

    public function validateDetails(array $details): array
    {
        return [
            'iban' => ['required', 'string', 'max:34'],
            'qr_reference' => ['nullable', 'string', 'max:27'],
        ];
    }

    public function generatePayload(Invoice $invoice, BankAccount $bankAccount): string
    {
        // Build the Swiss QR-bill payload string
        // See https://www.six-group.com/en/products-services/banking-services/payment-standardization/standards/qr-bill.html
        return '...';
    }

    public function renderQrCode(string $payload): ?string
    {
        // Swiss QR-bill requires a Swiss cross embedded in the QR code
        // Return base64 PNG with the custom rendering, or null for default
        return null;
    }

    public function getDisplayLabel(BankAccount $bankAccount): string
    {
        $iban = strtoupper(str_replace(' ', '', $bankAccount->getDetail('iban', '')));
        return substr($iban, 0, 4) . '••••' . substr($iban, -4);
    }
}
```

### 2. Register the Generator

Add it to `app/Providers/PaymentQrCodeServiceProvider.php`:

```php
$service->registerGenerator(new SwissQrBillGenerator);
```

### 3. No Migration Needed

Bank account details are stored in a JSON column. Your generator reads its own fields from that JSON. No database schema changes required.

### 4. No Frontend Changes Needed

The frontend dynamically renders form fields based on `getRequiredFields()`. Your new type will automatically appear in the QR type dropdown with the correct form fields.

## System Requirements

- **SEPA EPC**: No special requirements (pure PHP)
- **SPAYD**: No special requirements (pure PHP)
- **Pay by Square**: Requires `xz` binary on the server for LZMA compression
