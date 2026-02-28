<?php

namespace App\Providers;

use App\Services\PaymentQrCode\PayBySquareQrCodeGenerator;
use App\Services\PaymentQrCode\PaymentQrCodeService;
use App\Services\PaymentQrCode\SepaEpcQrCodeGenerator;
use App\Services\PaymentQrCode\SpaydQrCodeGenerator;
use Illuminate\Support\ServiceProvider;

class PaymentQrCodeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentQrCodeService::class, function () {
            $service = new PaymentQrCodeService;

            $service->registerGenerator(new SepaEpcQrCodeGenerator);
            $service->registerGenerator(new SpaydQrCodeGenerator);
            $service->registerGenerator(new PayBySquareQrCodeGenerator);

            return $service;
        });
    }
}
