<?php

namespace App\Services\PaymentQrCode;

use App\Contracts\PaymentQrCodeGeneratorInterface;
use App\Models\BankAccount;
use App\Models\Invoice;

class SepaEpcQrCodeGenerator implements PaymentQrCodeGeneratorInterface
{
    public function getType(): string
    {
        return 'sepa_epc';
    }

    public function getLabel(): string
    {
        return 'SEPA EPC QR Code';
    }

    public function supports(string $qrCodeType): bool
    {
        return $qrCodeType === 'sepa_epc';
    }

    public function checkAvailability(): array
    {
        return ['available' => true, 'reason' => null];
    }

    public function getSupportedCurrencies(): ?array
    {
        return ['EUR'];
    }

    public function getRequiredFields(): array
    {
        return [
            ['key' => 'iban', 'label' => 'IBAN', 'type' => 'text', 'required' => true, 'placeholder' => 'DE89 3704 0044 0532 0130 00'],
            ['key' => 'bic', 'label' => 'BIC/SWIFT', 'type' => 'text', 'required' => false, 'placeholder' => 'COBADEFFXXX'],
        ];
    }

    public function validateDetails(array $details): array
    {
        $rules = [
            'iban' => ['required', 'string', 'max:34'],
            'bic' => ['nullable', 'string', 'max:11'],
        ];

        if (! empty($details['iban'])) {
            $rules['iban'][] = function ($attribute, $value, $fail) {
                if (! IbanValidator::isValid($value)) {
                    $fail('The IBAN is invalid.');
                }
            };
        }

        return $rules;
    }

    public function generatePayload(Invoice $invoice, BankAccount $bankAccount): string
    {
        $iban = strtoupper(str_replace(' ', '', $bankAccount->getDetail('iban', '')));
        $bic = strtoupper(str_replace(' ', '', $bankAccount->getDetail('bic', '')));
        $name = mb_substr($bankAccount->account_holder_name, 0, 70);
        $amount = number_format($invoice->due_amount / 100, 2, '.', '');
        $reference = mb_substr($invoice->invoice_number, 0, 140);

        $lines = [
            'BCD',           // Service Tag
            '002',           // Version
            '1',             // Character set (UTF-8)
            'SCT',           // Identification
            $bic,            // BIC
            $name,           // Beneficiary name
            $iban,           // IBAN
            'EUR'.$amount,   // Amount
            '',              // Purpose
            '',              // Structured reference (empty, using text instead)
            $reference,      // Unstructured remittance text
        ];

        return implode("\n", $lines);
    }

    public function getInvoiceFields(): array
    {
        return [];
    }

    public function validateInvoiceDetails(array $details): array
    {
        return [];
    }

    public function renderQrCode(string $payload): ?string
    {
        return null;
    }

    public function getDisplayLabel(BankAccount $bankAccount): string
    {
        $iban = $bankAccount->getDetail('iban', '');
        $iban = strtoupper(str_replace(' ', '', $iban));

        if (strlen($iban) < 8) {
            return $iban;
        }

        return substr($iban, 0, 4).'••••'.substr($iban, -4);
    }
}
