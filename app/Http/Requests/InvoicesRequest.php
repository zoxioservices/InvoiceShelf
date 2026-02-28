<?php

namespace App\Http\Requests;

use App\Models\BankAccount;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\PaymentQrCode\PaymentQrCodeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoicesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.s
     */
    public function rules(): array
    {
        $rules = [
            'invoice_date' => [
                'required',
            ],
            'due_date' => [
                'nullable',
            ],
            'customer_id' => [
                'required',
            ],
            'invoice_number' => [
                'required',
                Rule::unique('invoices')->where('company_id', $this->header('company')),
            ],
            'exchange_rate' => [
                'nullable',
            ],
            'discount' => [
                'numeric',
                'required',
            ],
            'discount_val' => [
                'integer',
                'required',
            ],
            'sub_total' => [
                'numeric',
                'required',
            ],
            'total' => [
                'numeric',
                'max:999999999999',
                'required',
            ],
            'tax' => [
                'required',
            ],
            'template_name' => [
                'required',
            ],
            'items' => [
                'required',
                'array',
            ],
            'items.*' => [
                'required',
                'max:255',
            ],
            'items.*.description' => [
                'nullable',
            ],
            'items.*.name' => [
                'required',
            ],
            'items.*.quantity' => [
                'numeric',
                'required',
            ],
            'items.*.price' => [
                'numeric',
                'required',
            ],
            'bank_account_id' => [
                'nullable',
                'integer',
                'exists:bank_accounts,id',
            ],
            'payment_details' => ['nullable', 'array'],
        ];

        $bankAccountId = $this->input('bank_account_id');
        if ($bankAccountId) {
            $bankAccount = BankAccount::find($bankAccountId);
            if ($bankAccount) {
                try {
                    $service = app(PaymentQrCodeService::class);
                    $generator = $service->getGenerator($bankAccount->qr_code_type);
                    if ($generator) {
                        $detailRules = $generator->validateInvoiceDetails($this->input('payment_details', []));
                        foreach ($detailRules as $key => $fieldRules) {
                            $rules["payment_details.{$key}"] = $fieldRules;
                        }

                        $supportedCurrencies = $generator->getSupportedCurrencies();
                        if ($supportedCurrencies !== null) {
                            $rules['bank_account_id'][] = function ($attribute, $value, $fail) use ($supportedCurrencies, $generator) {
                                $customer = Customer::find($this->input('customer_id'));
                                $currencyCode = $customer?->currency?->code;
                                if ($currencyCode && ! in_array($currencyCode, $supportedCurrencies)) {
                                    $fail(__('validation.bank_account_currency_mismatch', [
                                        'qr_type' => $generator->getLabel(),
                                        'supported' => implode(', ', $supportedCurrencies),
                                        'currency' => $currencyCode,
                                    ]));
                                }
                            };
                        }
                    }
                } catch (\Exception $e) {
                    // Service not available, skip invoice detail validation
                }
            }
        }

        $companyCurrency = CompanySetting::getSetting('currency', $this->header('company'));

        $customer = Customer::find($this->customer_id);

        if ($customer && $companyCurrency) {
            if ((string) $customer->currency_id !== $companyCurrency) {
                $rules['exchange_rate'] = [
                    'required',
                ];
            }
        }

        if ($this->isMethod('PUT')) {
            $rules['invoice_number'] = [
                'required',
                Rule::unique('invoices')
                    ->ignore($this->route('invoice')->id)
                    ->where('company_id', $this->header('company')),
            ];
        }

        return $rules;
    }

    public function getInvoicePayload(): array
    {
        $company_currency = CompanySetting::getSetting('currency', $this->header('company'));
        $current_currency = $this->currency_id;
        $exchange_rate = $company_currency != $current_currency ? $this->exchange_rate : 1;
        $currency = Customer::find($this->customer_id)->currency_id;

        return collect($this->except('items', 'taxes'))
            ->merge([
                'creator_id' => $this->user()->id ?? null,
                'status' => $this->has('invoiceSend') ? Invoice::STATUS_SENT : Invoice::STATUS_DRAFT,
                'paid_status' => Invoice::STATUS_UNPAID,
                'company_id' => $this->header('company'),
                'tax_per_item' => CompanySetting::getSetting('tax_per_item', $this->header('company')) ?? 'NO ',
                'discount_per_item' => CompanySetting::getSetting('discount_per_item', $this->header('company')) ?? 'NO',
                'due_amount' => $this->total,
                'sent' => (bool) $this->sent ?? false,
                'viewed' => (bool) $this->viewed ?? false,
                'exchange_rate' => $exchange_rate,
                'base_total' => $this->total * $exchange_rate,
                'base_discount_val' => $this->discount_val * $exchange_rate,
                'base_sub_total' => $this->sub_total * $exchange_rate,
                'base_tax' => $this->tax * $exchange_rate,
                'base_due_amount' => $this->total * $exchange_rate,
                'currency_id' => $currency,
            ])
            ->toArray();
    }
}
