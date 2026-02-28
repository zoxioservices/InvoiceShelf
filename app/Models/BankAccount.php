<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'details' => 'array',
        'is_default' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class);
    }

    public function scopeWhereCompany($query)
    {
        return $query->where('bank_accounts.company_id', request()->header('company'));
    }

    public function scopeApplyFilters($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('account_holder_name', 'like', '%'.$search.'%');
            });
        });

        return $query;
    }

    public function scopePaginateData($query, $limit)
    {
        if ($limit == 'all') {
            return $query->get();
        }

        return $query->paginate($limit);
    }

    public function getDetail(string $key, $default = null)
    {
        return data_get($this->details, $key, $default);
    }

    public static function createBankAccount($request): self
    {
        $data = $request->getBankAccountPayload();
        $bankAccount = self::create($data);

        if ($bankAccount->is_default) {
            $bankAccount->setAsDefault();
        }

        return $bankAccount;
    }

    public function updateBankAccount($request): self
    {
        $data = $request->getBankAccountPayload();
        $this->update($data);

        if ($this->is_default) {
            $this->setAsDefault();
        }

        return $this;
    }

    public static function deleteBankAccounts($ids): bool
    {
        self::whereIn('id', $ids)->delete();

        return true;
    }

    public function setAsDefault(): void
    {
        self::where('company_id', $this->company_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        if (! $this->is_default) {
            $this->update(['is_default' => true]);
        }
    }
}
