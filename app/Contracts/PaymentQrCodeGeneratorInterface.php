<?php

namespace App\Contracts;

use App\Models\BankAccount;
use App\Models\Invoice;

/**
 * Interface for payment QR code generators.
 *
 * Each QR code standard (SEPA EPC, SPAYD, Pay by Square, etc.) implements this interface.
 * To add support for a new QR code standard:
 *   1. Create a new class implementing this interface in app/Services/PaymentQrCode/
 *   2. Register it in App\Providers\PaymentQrCodeServiceProvider
 *   3. See app/Services/PaymentQrCode/README.md for detailed instructions
 */
interface PaymentQrCodeGeneratorInterface
{
    /**
     * Unique type identifier for this generator (e.g. 'sepa_epc', 'spayd', 'pay_by_square').
     */
    public function getType(): string;

    /**
     * Human-readable label (e.g. 'SEPA EPC QR Code').
     */
    public function getLabel(): string;

    /**
     * Whether this generator handles the given QR code type.
     */
    public function supports(string $qrCodeType): bool;

    /**
     * Check system requirements for this generator.
     * Returns ['available' => bool, 'reason' => ?string].
     */
    public function checkAvailability(): array;

    /**
     * ISO currency codes this standard supports, or null for any currency.
     * E.g. ['EUR'] for SEPA EPC, null for Pay by Square.
     */
    public function getSupportedCurrencies(): ?array;

    /**
     * Returns the field schema for the bank account form (stored in bank_accounts.details).
     * Each field: ['key' => string, 'label' => string, 'type' => 'text'|'select', 'required' => bool, ...]
     */
    public function getRequiredFields(): array;

    /**
     * Returns Laravel validation rules for the bank_accounts.details JSON column.
     * Keys should NOT include 'details.' prefix.
     * E.g. ['iban' => ['required', 'string'], 'bic' => ['nullable', 'string']]
     */
    public function validateDetails(array $details): array;

    /**
     * Returns the field schema for the invoice form (stored in invoices.payment_details).
     * Each field: ['key' => string, 'label' => string, 'type' => 'text'|'select', 'required' => bool, ...]
     * Return an empty array if this generator has no per-invoice fields.
     */
    public function getInvoiceFields(): array;

    /**
     * Returns Laravel validation rules for the invoices.payment_details JSON column.
     * Keys should NOT include 'payment_details.' prefix.
     * Return an empty array if this generator has no per-invoice fields.
     */
    public function validateInvoiceDetails(array $details): array;

    /**
     * Generate the raw text payload to encode in the QR code.
     */
    public function generatePayload(Invoice $invoice, BankAccount $bankAccount): string;

    /**
     * Optionally override QR code rendering.
     * Return base64 PNG string to use custom rendering, or null for default.
     */
    public function renderQrCode(string $payload): ?string;

    /**
     * Human-readable label for a specific bank account (for dropdowns).
     * E.g. "DE89•••0130 00" for SEPA, "•••456789" for PIX.
     */
    public function getDisplayLabel(BankAccount $bankAccount): string;
}
