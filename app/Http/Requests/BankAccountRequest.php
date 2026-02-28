<?php

namespace App\Http\Requests;

use App\Services\PaymentQrCode\PaymentQrCodeService;
use Illuminate\Foundation\Http\FormRequest;

class BankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'account_holder_name' => ['required', 'string', 'max:255'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'qr_code_type' => ['required', 'string'],
            'details' => ['required', 'array'],
            'is_default' => ['sometimes', 'boolean'],
        ];

        $qrCodeType = $this->input('qr_code_type');
        if ($qrCodeType) {
            try {
                $service = app(PaymentQrCodeService::class);
                $generator = $service->getGenerator($qrCodeType);
                if ($generator) {
                    $availability = $generator->checkAvailability();
                    if (! $availability['available']) {
                        $rules['qr_code_type'][] = function ($attribute, $value, $fail) use ($availability) {
                            $fail($availability['reason'] ?? 'This QR code type is not available on this server.');
                        };
                    }

                    $detailRules = $generator->validateDetails($this->input('details', []));
                    foreach ($detailRules as $key => $fieldRules) {
                        $rules["details.{$key}"] = $fieldRules;
                    }
                } else {
                    $rules['qr_code_type'][] = 'in:'.implode(',', $service->getSupportedTypeKeys());
                }
            } catch (\Exception $e) {
                // Service not available, basic validation only
            }
        }

        return $rules;
    }

    public function getBankAccountPayload(): array
    {
        return [
            'name' => $this->name,
            'account_holder_name' => $this->account_holder_name,
            'currency_id' => $this->currency_id,
            'qr_code_type' => $this->qr_code_type,
            'details' => $this->details,
            'is_default' => $this->boolean('is_default', false),
            'company_id' => $this->header('company'),
        ];
    }
}
