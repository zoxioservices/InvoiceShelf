<?php

namespace App\Http\Controllers\V1\Admin\BankAccount;

use App\Http\Controllers\Controller;
use App\Services\PaymentQrCode\PaymentQrCodeService;
use Illuminate\Http\JsonResponse;

class PaymentQrTypesController extends Controller
{
    public function __invoke(PaymentQrCodeService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->getSupportedTypes(),
        ]);
    }
}
