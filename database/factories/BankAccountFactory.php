<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Account',
            'account_holder_name' => $this->faker->name(),
            'currency_id' => null,
            'qr_code_type' => 'sepa_epc',
            'details' => [
                'iban' => 'DE89370400440532013000',
                'bic' => 'COBADEFFXXX',
            ],
            'is_default' => false,
            'company_id' => User::find(1)->companies()->first()->id,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function spayd(): static
    {
        return $this->state(fn (array $attributes) => [
            'qr_code_type' => 'spayd',
            'details' => [
                'iban' => 'CZ6508000000192000145399',
                'bic' => 'GIBACZPX',
            ],
        ]);
    }

    public function payBySquare(): static
    {
        return $this->state(fn (array $attributes) => [
            'qr_code_type' => 'pay_by_square',
            'details' => [
                'iban' => 'SK3112000000198742637541',
                'bic' => 'TATRSKBX',
            ],
        ]);
    }
}
