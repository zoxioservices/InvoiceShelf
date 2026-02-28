<?php

namespace App\Services\PaymentQrCode;

use App\Contracts\PaymentQrCodeGeneratorInterface;
use App\Models\BankAccount;
use App\Models\Invoice;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class PaymentQrCodeService
{
    /** @var PaymentQrCodeGeneratorInterface[] */
    private array $generators = [];

    public function registerGenerator(PaymentQrCodeGeneratorInterface $generator): void
    {
        $this->generators[] = $generator;
    }

    public function getGenerator(string $qrCodeType): ?PaymentQrCodeGeneratorInterface
    {
        foreach ($this->generators as $generator) {
            if ($generator->supports($qrCodeType)) {
                return $generator;
            }
        }

        return null;
    }

    public function isCurrencyCompatible(Invoice $invoice, BankAccount $bankAccount): bool
    {
        $generator = $this->getGenerator($bankAccount->qr_code_type);
        if (! $generator) {
            return false;
        }

        $supported = $generator->getSupportedCurrencies();
        if ($supported === null) {
            return true;
        }

        $invoiceCurrencyCode = $invoice->currency?->code;

        return $invoiceCurrencyCode !== null && in_array($invoiceCurrencyCode, $supported);
    }

    public function generateQrCode(Invoice $invoice, BankAccount $bankAccount): ?string
    {
        $generator = $this->getGenerator($bankAccount->qr_code_type);
        if (! $generator) {
            return null;
        }

        $availability = $generator->checkAvailability();
        if (! $availability['available']) {
            return null;
        }

        if (! $this->isCurrencyCompatible($invoice, $bankAccount)) {
            return null;
        }

        $payload = $generator->generatePayload($invoice, $bankAccount);

        $customRendered = $generator->renderQrCode($payload);
        if ($customRendered !== null) {
            return $customRendered;
        }

        return $this->renderDefaultQrCode($payload);
    }

    private function renderDefaultQrCode(string $payload): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'eccLevel' => EccLevel::M,
            'scale' => 5,
            'outputBase64' => true,
            'addQuietzone' => true,
        ]);

        $qrcode = new QRCode($options);

        return $qrcode->render($payload);
    }

    public function getSupportedTypes(): array
    {
        $types = [];
        foreach ($this->generators as $generator) {
            $availability = $generator->checkAvailability();
            $types[] = [
                'type' => $generator->getType(),
                'label' => $generator->getLabel(),
                'available' => $availability['available'],
                'unavailable_reason' => $availability['reason'] ?? null,
                'supported_currencies' => $generator->getSupportedCurrencies(),
                'fields' => $generator->getRequiredFields(),
                'invoice_fields' => $generator->getInvoiceFields(),
            ];
        }

        return $types;
    }

    public function getSupportedTypeKeys(): array
    {
        return array_map(fn ($g) => $g->getType(), $this->generators);
    }
}
