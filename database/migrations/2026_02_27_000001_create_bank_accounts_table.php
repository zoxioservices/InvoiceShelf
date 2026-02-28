<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('company_id');
            $table->string('name');
            $table->string('account_holder_name');
            $table->unsignedInteger('currency_id')->nullable();
            $table->string('qr_code_type')->default('sepa_epc');
            $table->json('details');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
