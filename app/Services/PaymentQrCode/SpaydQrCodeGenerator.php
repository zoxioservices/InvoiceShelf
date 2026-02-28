<?php

namespace App\Services\PaymentQrCode;

use App\Contracts\PaymentQrCodeGeneratorInterface;
use App\Models\BankAccount;
use App\Models\Invoice;

class SpaydQrCodeGenerator implements PaymentQrCodeGeneratorInterface
{
    public function getType(): string
    {
        return 'spayd';
    }

    public function getLabel(): string
    {
        return 'SPAYD / Short Payment Descriptor (CZ)';
    }

    public function supports(string $qrCodeType): bool
    {
        return $qrCodeType === 'spayd';
    }

    public function checkAvailability(): array
    {
        return ['available' => true, 'reason' => null];
    }

    public function getSupportedCurrencies(): ?array
    {
        return null;
    }

    public function getRequiredFields(): array
    {
        return [
            ['key' => 'iban', 'label' => 'IBAN', 'type' => 'text', 'required' => true, 'placeholder' => 'CZ65 0800 0000 1920 0014 5399'],
            ['key' => 'bic', 'label' => 'BIC/SWIFT', 'type' => 'text', 'required' => false, 'placeholder' => 'GIBACZPX'],
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

    public function getInvoiceFields(): array
    {
        return [
            ['key' => 'variable_symbol', 'label' => 'Variable Symbol', 'type' => 'text', 'required' => false, 'placeholder' => ''],
        ];
    }

    public function validateInvoiceDetails(array $details): array
    {
        return [
            'variable_symbol' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function generatePayload(Invoice $invoice, BankAccount $bankAccount): string
    {
        $iban = strtoupper(str_replace(' ', '', $bankAccount->getDetail('iban', '')));
        $bic = strtoupper(str_replace(' ', '', $bankAccount->getDetail('bic', '')));
        $amount = number_format($invoice->due_amount / 100, 2, '.', '');
        $currencyCode = $invoice->currency->code ?? 'CZK';
        $variableSymbol = data_get($invoice->payment_details, 'variable_symbol', '');

        $parts = [
            'SPD*1.0',
            'ACC:'.$iban.($bic ? '+'.$bic : ''),
            'AM:'.$amount,
            'CC:'.$currencyCode,
            'MSG:'.mb_substr($invoice->invoice_number, 0, 60),
        ];

        if ($variableSymbol) {
            $parts[] = 'X-VS:'.$variableSymbol;
        }

        return implode('*', $parts);
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
