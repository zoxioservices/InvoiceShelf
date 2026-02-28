<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Company::each(function (Company $company): void {
            $company->setupRoles();
        });
    }

    public function down(): void
    {
        //
    }
};
