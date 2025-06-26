<?php
// app/Http/Requests/API/Report/GenerateReportRequest.php
namespace App\Http\Requests\API\Report;

use Illuminate\Foundation\Http\FormRequest;

class GenerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Domain\Reporting\Models\GeneratedReport::class);
    }

    public function rules(): array
    {
        return [
            'report_type' => 'required|string|in:balance_sheet,income_statement,cash_flow,equity_changes,financial_notes,member_savings,loan_receivables,non_performing_loans,shu_distribution,budget_variance',
            'start_date' => 'required|date|before_or_equal:end_date',
            'end_date' => 'required|date|after_or_equal:start_date|before_or_equal:today',
            'format' => 'nullable|string|in:pdf,excel,csv',
            'parameters' => 'nullable|array',
            'parameters.include_notes' => 'nullable|boolean',
            'parameters.show_comparison' => 'nullable|boolean',
            'parameters.comparison_period' => 'nullable|string|in:previous_year,previous_quarter,previous_month',
            'parameters.department_breakdown' => 'nullable|boolean',
            'parameters.member_filter' => 'nullable|array',
            'parameters.account_filter' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'report_type.required' => 'Jenis laporan wajib dipilih.',
            'report_type.in' => 'Jenis laporan tidak valid.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.before_or_equal' => 'Tanggal mulai harus sebelum atau sama dengan tanggal akhir.',
            'end_date.required' => 'Tanggal akhir wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal akhir harus setelah atau sama dengan tanggal mulai.',
            'end_date.before_or_equal' => 'Tanggal akhir tidak boleh di masa depan.',
            'format.in' => 'Format laporan tidak valid.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $startDate = $this->input('start_date');
            $endDate = $this->input('end_date');

            if ($startDate && $endDate) {
                $start = \Carbon\Carbon::parse($startDate);
                $end = \Carbon\Carbon::parse($endDate);

                if ($start->diffInDays($end) > 365) {
                    $validator->errors()->add('end_date', 'Rentang tanggal tidak boleh lebih dari 365 hari.');
                }
            }
        });
    }
}
