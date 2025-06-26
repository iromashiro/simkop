<?php
// app/Domain/Report/Reports/MemberSavingsReport.php
namespace App\Domain\Report\Reports;

use App\Domain\Report\Abstracts\BaseReport;
use App\Domain\Report\DTOs\ReportParameterDTO;
use Illuminate\Support\Facades\DB;

/**
 * Member Savings Report
 * SRS Reference: Section 3.4.8 - Member Savings Report
 */
class MemberSavingsReport extends BaseReport
{
    protected string $reportCode = 'MEMBER_SAVINGS';
    protected string $reportName = 'Laporan Simpanan Anggota';
    protected string $description = 'Laporan rincian simpanan anggota per jenis simpanan';

    protected function generateReportData(ReportParameterDTO $parameters): array
    {
        $savingsData = $this->getMemberSavingsData($parameters);

        return [
            'period' => [
                'start_date' => $parameters->startDate->format('Y-m-d'),
                'end_date' => $parameters->endDate->format('Y-m-d'),
            ],
            'members' => $savingsData,
        ];
    }

    protected function generateSummary(array $data, ReportParameterDTO $parameters): array
    {
        $summary = [
            'total_members' => count($data['members']),
            'savings_types' => [],
            'grand_total' => 0,
        ];

        foreach ($data['members'] as $member) {
            foreach ($member['savings'] as $type => $savings) {
                if (!isset($summary['savings_types'][$type])) {
                    $summary['savings_types'][$type] = [
                        'total_balance' => 0,
                        'member_count' => 0,
                    ];
                }

                if ($savings['ending_balance'] > 0) {
                    $summary['savings_types'][$type]['total_balance'] += $savings['ending_balance'];
                    $summary['savings_types'][$type]['member_count']++;
                }

                $summary['grand_total'] += $savings['ending_balance'];
            }
        }

        return $summary;
    }

    protected function getReportTitle(ReportParameterDTO $parameters): string
    {
        return "Laporan Simpanan Anggota Periode {$parameters->startDate->format('d F Y')} s/d {$parameters->endDate->format('d F Y')}";
    }

    /**
     * Get comprehensive member savings data with optimized query
     */
    private function getMemberSavingsData(ReportParameterDTO $parameters): array
    {
        // PERFORMANCE: Single query to get all member savings data
        $query = "
            WITH member_savings_summary AS (
                SELECT
                    m.id as member_id,
                    m.member_number,
                    m.name as member_name,
                    s.type,
                    COALESCE(SUM(CASE WHEN s.transaction_date < ? THEN s.amount ELSE 0 END), 0) as beginning_balance,
                    COALESCE(SUM(CASE WHEN s.transaction_date BETWEEN ? AND ? AND s.amount > 0 THEN s.amount ELSE 0 END), 0) as deposits,
                    COALESCE(SUM(CASE WHEN s.transaction_date BETWEEN ? AND ? AND s.amount < 0 THEN ABS(s.amount) ELSE 0 END), 0) as withdrawals,
                    COALESCE(SUM(CASE WHEN s.transaction_date <= ? THEN s.amount ELSE 0 END), 0) as ending_balance
                FROM members m
                CROSS JOIN (
                    SELECT unnest(ARRAY['pokok', 'wajib', 'khusus', 'sukarela']) as type
                ) types
                LEFT JOIN savings s ON m.id = s.member_id AND s.type = types.type
                WHERE m.cooperative_id = ?
                    AND m.status = 'active'
                GROUP BY m.id, m.member_number, m.name, s.type
            )
            SELECT
                member_id,
                member_number,
                member_name,
                json_object_agg(
                    type,
                    json_build_object(
                        'beginning_balance', beginning_balance,
                        'deposits', deposits,
                        'withdrawals', withdrawals,
                        'ending_balance', ending_balance
                    )
                ) as savings_data
            FROM member_savings_summary
            GROUP BY member_id, member_number, member_name
            ORDER BY member_number
        ";

        $results = DB::select($query, [
            $parameters->startDate->format('Y-m-d'), // beginning balance cutoff
            $parameters->startDate->format('Y-m-d'), // deposits start
            $parameters->endDate->format('Y-m-d'),   // deposits end
            $parameters->startDate->format('Y-m-d'), // withdrawals start
            $parameters->endDate->format('Y-m-d'),   // withdrawals end
            $parameters->endDate->format('Y-m-d'),   // ending balance cutoff
            $parameters->cooperativeId
        ]);

        // Transform results
        $members = [];
        foreach ($results as $result) {
            $members[] = [
                'member_id' => $result->member_id,
                'member_number' => $result->member_number,
                'member_name' => $result->member_name,
                'savings' => json_decode($result->savings_data, true),
            ];
        }

        return $members;
    }
}
