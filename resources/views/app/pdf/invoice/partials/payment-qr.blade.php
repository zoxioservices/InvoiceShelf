@if(isset($payment_qr_code) && $payment_qr_code && isset($payment_bank_account))
<div style="margin-top: 30px; padding: 0 30px; page-break-inside: avoid;">
    <table width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td width="165" style="vertical-align: top; padding-right: 15px;">
                <img src="{{ $payment_qr_code }}" style="width: 150px; height: 150px; display: block;" alt="Payment QR Code">
            </td>
            <td style="vertical-align: top;">
                <div style="font-size: 13px; font-weight: bold; color: #040405; margin-bottom: 6px;">
                    @lang('pdf_payment_qr_title')
                </div>
                <table width="100%" cellspacing="0" cellpadding="0" border="0">
                    <tr>
                        <td width="110" style="font-size: 11px; color: #55547A; white-space: nowrap; padding-right: 10px; padding-bottom: 4px; vertical-align: top;">
                            @lang('pdf_payment_qr_holder')
                        </td>
                        <td style="font-size: 11px; color: #040405; padding-bottom: 4px; vertical-align: top;">
                            {{ $payment_bank_account->account_holder_name }}
                        </td>
                    </tr>
                    @if($payment_bank_account->getDetail('iban'))
                    <tr>
                        <td width="110" style="font-size: 11px; color: #55547A; white-space: nowrap; padding-right: 10px; padding-bottom: 4px; vertical-align: top;">
                            IBAN:
                        </td>
                        <td style="font-size: 11px; color: #040405; padding-bottom: 4px; vertical-align: top; word-break: break-all;">
                            {{ $payment_bank_account->getDetail('iban') }}
                        </td>
                    </tr>
                    @endif
                    @if($payment_bank_account->getDetail('bic'))
                    <tr>
                        <td width="110" style="font-size: 11px; color: #55547A; white-space: nowrap; padding-right: 10px; padding-bottom: 4px; vertical-align: top;">
                            BIC:
                        </td>
                        <td style="font-size: 11px; color: #040405; padding-bottom: 4px; vertical-align: top;">
                            {{ $payment_bank_account->getDetail('bic') }}
                        </td>
                    </tr>
                    @endif
                    <tr>
                        <td width="110" style="font-size: 11px; color: #55547A; white-space: nowrap; padding-right: 10px; padding-bottom: 4px; vertical-align: top;">
                            @lang('pdf_payment_qr_reference')
                        </td>
                        <td style="font-size: 11px; color: #040405; padding-bottom: 4px; vertical-align: top;">
                            {{ $invoice->invoice_number }}
                        </td>
                    </tr>
                    <tr>
                        <td width="110" style="font-size: 11px; color: #55547A; white-space: nowrap; padding-right: 10px; padding-bottom: 4px; vertical-align: top;">
                            @lang('pdf_payment_qr_amount')
                        </td>
                        <td style="font-size: 11px; color: #040405; font-weight: bold; padding-bottom: 4px; vertical-align: top;">
                            {!! format_money_pdf($invoice->due_amount, $invoice->customer->currency) !!}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
@endif
