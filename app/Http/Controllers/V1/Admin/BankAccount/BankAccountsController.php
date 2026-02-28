<?php

namespace App\Http\Controllers\V1\Admin\BankAccount;

use App\Http\Controllers\Controller;
use App\Http\Requests\BankAccountRequest;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use Illuminate\Http\Request;

class BankAccountsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', BankAccount::class);

        $limit = $request->input('limit', 10);

        $bankAccounts = BankAccount::whereCompany()
            ->applyFilters($request->all())
            ->latest()
            ->paginateData($limit);

        return BankAccountResource::collection($bankAccounts);
    }

    public function store(BankAccountRequest $request)
    {
        $this->authorize('create', BankAccount::class);

        $bankAccount = BankAccount::createBankAccount($request);

        return new BankAccountResource($bankAccount);
    }

    public function show(Request $request, BankAccount $bankAccount)
    {
        $this->authorize('view', $bankAccount);

        return new BankAccountResource($bankAccount);
    }

    public function update(BankAccountRequest $request, BankAccount $bankAccount)
    {
        $this->authorize('update', $bankAccount);

        $bankAccount->updateBankAccount($request);

        return new BankAccountResource($bankAccount);
    }

    public function delete(Request $request)
    {
        $this->authorize('deleteMultiple', BankAccount::class);

        BankAccount::deleteBankAccounts($request->ids);

        return response()->json([
            'success' => true,
        ]);
    }
}
