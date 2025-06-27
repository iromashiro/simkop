<?php
// Account Requests
namespace App\Http\Requests\Web\Account;

use Illuminate\Foundation\Http\FormRequest;

class CreateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Domain\Accounting\Models\Account::class);
    }

    public function rules(): array
    {
        return [
            'cooperative_id' => ['required', 'integer', 'exists:cooperatives,id'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:asset,liability,equity,revenue,expense'],
            'parent_id' => ['nullable', 'integer', 'exists:accounts,id'],
        ];
    }
}

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('account'));
    }

    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'required', 'string', 'max:20'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }
}

// Fiscal Period Requests
namespace App\Http\Requests\Web\FiscalPeriod;

use Illuminate\Foundation\Http\FormRequest;

class CreateFiscalPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Domain\Accounting\Models\FiscalPeriod::class);
    }

    public function rules(): array
    {
        return [
            'cooperative_id' => ['required', 'integer', 'exists:cooperatives,id'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }
}

// Journal Entry Requests
namespace App\Http\Requests\Web\JournalEntry;

use Illuminate\Foundation\Http\FormRequest;

class CreateJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Domain\Accounting\Models\JournalEntry::class);
    }

    public function rules(): array
    {
        return [
            'cooperative_id' => ['required', 'integer', 'exists:cooperatives,id'],
            'fiscal_period_id' => ['required', 'integer', 'exists:fiscal_periods,id'],
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:accounts,id'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.debit_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

// Loan Requests
namespace App\Http\Requests\Web\Loan;

use Illuminate\Foundation\Http\FormRequest;

class CreateLoanAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Domain\Loan\Models\LoanAccount::class);
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'loan_type' => ['required', 'string', 'max:50'],
            'principal_amount' => ['required', 'numeric', 'min:1'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'term_months' => ['required', 'integer', 'min:1', 'max:360'],
            'disbursement_date' => ['required', 'date'],
        ];
    }
}

class LoanPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Domain\Loan\Models\LoanPayment::class);
    }

    public function rules(): array
    {
        return [
            'loan_account_id' => ['required', 'integer', 'exists:loan_accounts,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'principal_amount' => ['required', 'numeric', 'min:0'],
            'interest_amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'in:cash,transfer,check'],
        ];
    }
}

// Savings Requests
namespace App\Http\Requests\Web\Savings;

use Illuminate\Foundation\Http\FormRequest;

class CreateSavingsAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Domain\Savings\Models\SavingsAccount::class);
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'account_type' => ['required', 'string', 'max:50'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'minimum_balance' => ['required', 'numeric', 'min:0'],
            'initial_deposit' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

class SavingsTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Domain\Savings\Models\SavingsTransaction::class);
    }

    public function rules(): array
    {
        return [
            'savings_account_id' => ['required', 'integer', 'exists:savings_accounts,id'],
            'transaction_type' => ['required', 'string', 'in:deposit,withdrawal'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}

// User Requests
namespace App\Http\Requests\Web\User;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Domain\Auth\Models\User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'regex:/^(\+62|62|0)[0-9]{8,13}$/'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'email' => ['sometimes', 'required', 'email:rfc,dns', 'max:255', "unique:users,email,{$user->id}"],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^(\+62|62|0)[0-9]{8,13}$/'],
        ];
    }
}

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Users can update their own profile
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^(\+62|62|0)[0-9]{8,13}$/'],
            'current_password' => ['required_with:password', 'string'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
