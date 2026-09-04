<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Payments\ManualPayment;
use App\Payments\XenditPayment;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $gateway = config('payments.default_gateway', 'manual');

            return match ($gateway) {
                'xendit' => new XenditPayment(
                    config('payments.gateways.xendit.api_key', ''),
                    config('payments.gateways.xendit.base_url', 'https://api.xendit.co'),
                    config('payments.gateways.xendit.api_version', '2024-11-11'),
                ),
                default => new ManualPayment(),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
