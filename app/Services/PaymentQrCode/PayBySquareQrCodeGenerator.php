<?php

namespace App\Services\PaymentQrCode;

use App\Contracts\PaymentQrCodeGeneratorInterface;
use App\Models\BankAccount;
use App\Models\Invoice;

class PayBySquareQrCodeGenerator implements PaymentQrCodeGeneratorInterface
{
    public function getType(): string
    {
        return 'pay_by_square';
    }

    public function getLabel(): string
    {
        return 'Pay by Square (SK)';
    }

    public function supports(string $qrCodeType): bool
    {
        return $qrCodeType === 'pay_by_square';
    }

    public function checkAvailability(): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open(['xz', '--version'], $descriptorSpec, $pipes);
        if (! is_resource($process)) {
            return [
                'available' => false,
                'reason' => 'The xz binary is required but not found on this server. Install xz-utils (Linux: apt install xz-utils) or xz (macOS: brew install xz) to enable Pay by Square.',
            ];
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return ['available' => true, 'reason' => null];
    }

    public function getSupportedCurrencies(): ?array
    {
        return ['EUR'];
    }

    public function getRequiredFields(): array
    {
        return [
            ['key' => 'iban', 'label' => 'IBAN', 'type' => 'text', 'required' => true, 'placeholder' => 'SK31 1200 0000 1987 4263 7541'],
            ['key' => 'bic', 'label' => 'BIC/SWIFT', 'type' => 'text', 'required' => true, 'placeholder' => 'TATRSKBX'],
        ];
    }

    public function validateDetails(array $details): array
    {
        $rules = [
            'iban' => ['required', 'string', 'max:34'],
            'bic' => ['required', 'string', 'max:11'],
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
            ['key' => 'specific_symbol', 'label' => 'Specific Symbol', 'type' => 'text', 'required' => false, 'placeholder' => ''],
            ['key' => 'constant_symbol', 'label' => 'Constant Symbol', 'type' => 'text', 'required' => false, 'placeholder' => ''],
        ];
    }

    public function validateInvoiceDetails(array $details): array
    {
        return [
            'variable_symbol' => ['nullable', 'string', 'max:10'],
            'specific_symbol' => ['nullable', 'string', 'max:10'],
            'constant_symbol' => ['nullable', 'string', 'max:4'],
        ];
    }

    public function generatePayload(Invoice $invoice, BankAccount $bankAccount): string
    {
        $iban = strtoupper(str_replace(' ', '', $bankAccount->getDetail('iban', '')));
        $bic = strtoupper(str_replace(' ', '', $bankAccount->getDetail('bic', '')));
        $amount = number_format($invoice->due_amount / 100, 2, '.', '');
        $currencyCode = $invoice->currency->code ?? 'EUR';

        $variableSymbol = data_get($invoice->payment_details, 'variable_symbol', '');
        $specificSymbol = data_get($invoice->payment_details, 'specific_symbol', '');
        $constantSymbol = data_get($invoice->payment_details, 'constant_symbol', '');

        $dueDate = $invoice->due_date ? date('Ymd', strtotime($invoice->due_date)) : '';
        $note = mb_substr($invoice->invoice_number, 0, 140);

        // Build the tab-separated data per Pay by Square specification v1.1.0
        // Fields are separated by tabs, sections by newlines
        $data = implode("\t", [
            '',               // Invoice ID (optional)
            '1',              // Payment count
            '1',              // Regular payment
            $amount,          // Amount
            $currencyCode,    // Currency
            $dueDate,         // Due date
            $variableSymbol,  // Variable symbol
            $constantSymbol,  // Constant symbol
            $specificSymbol,  // Specific symbol
            '',               // Previous reference
            $note,            // Note for recipient
            '1',              // Number of accounts
            $iban,            // IBAN
            $bic,             // BIC
            '0',              // Standing order flag
            '0',              // Direct debit flag
            '',               // Beneficiary name
            '',               // Beneficiary address line 1
            '',               // Beneficiary address line 2
        ]);

        return $this->encode($data);
    }

    private function encode(string $data): string
    {
        $crc = crc32($data);
        $crcBytes = pack('V', $crc);
        $dataWithCrc = $crcBytes.$data;

        $compressed = $this->lzmaCompress($dataWithCrc);
        if ($compressed === null) {
            throw new \RuntimeException('LZMA compression failed. Ensure xz is installed on the server.');
        }

        // Prepend header: 2 bytes for data length (big-endian uint16)
        $header = pack('n', strlen($dataWithCrc));

        // Base32hex encode
        $encoded = $this->base32hexEncode($header.$compressed);

        return $encoded;
    }

    private function lzmaCompress(string $data): ?string
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open(
            ['xz', '--format=raw', '--lzma1=lc=3,lp=0,pb=2,dict=128KiB', '-c'],
            $descriptorSpec,
            $pipes
        );

        if (! is_resource($process)) {
            return null;
        }

        fwrite($pipes[0], $data);
        fclose($pipes[0]);

        $compressed = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            return null;
        }

        return $compressed;
    }

    private function base32hexEncode(string $data): string
    {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUV';
        $binary = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $binary .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }

        // Pad to multiple of 5
        $padding = (5 - (strlen($binary) % 5)) % 5;
        $binary .= str_repeat('0', $padding);

        $encoded = '';
        for ($i = 0; $i < strlen($binary); $i += 5) {
            $chunk = substr($binary, $i, 5);
            $encoded .= $alphabet[bindec($chunk)];
        }

        return $encoded;
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
