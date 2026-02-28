<?php

namespace App\Services\PaymentQrCode;

class IbanValidator
{
    public static function isValid(string $iban): bool
    {
        $iban = strtoupper(str_replace(' ', '', $iban));

        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{4,30}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);

        $numeric = '';
        for ($i = 0; $i < strlen($rearranged); $i++) {
            $char = $rearranged[$i];
            if (ctype_alpha($char)) {
                $numeric .= (ord($char) - 55);
            } else {
                $numeric .= $char;
            }
        }

        return bcmod($numeric, '97') === '1';
    }
}
