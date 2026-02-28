<?php

namespace App\Http\Resources;

use App\Services\PaymentQrCode\PaymentQrCodeService;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    public function toArray($request): array
    {
        $displayLabel = '';
        try {
            $service = app(PaymentQrCodeService::class);
            $generator = $service->getGenerator($this->qr_code_type);
            if ($generator) {
                $displayLabel = $generator->getDisplayLabel($this->resource);
            }
        } catch (\Exception $e) {
            $displayLabel = '';
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'account_holder_name' => $this->account_holder_name,
            'currency_id' => $this->currency_id,
            'qr_code_type' => $this->qr_code_type,
            'details' => $this->details,
            'is_default' => $this->is_default,
            'display_label' => $displayLabel,
            'company_id' => $this->company_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'currency' => $this->when($this->currency, function () {
                return new CurrencyResource($this->currency);
            }),
        ];
    }
}
