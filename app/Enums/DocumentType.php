<?php

namespace App\Enums;

enum DocumentType: string
{
    case Proposal = 'proposal';
    case FundWithdrawal = 'fund_withdrawal';
    case AgmReport = 'agm_report';
    case Minutes = 'minutes';
    case FinanceRecord = 'finance_record';
    case AttendanceList = 'attendance_list';
    case PostMortem = 'post_mortem';

    public function label(): string
    {
        return match ($this) {
            self::Proposal => 'Event Proposal',
            self::FundWithdrawal => 'Fund Withdrawal / Transfer',
            self::AgmReport => 'AGM Report',
            self::Minutes => 'Meeting Minutes',
            self::FinanceRecord => 'Finance Record',
            self::AttendanceList => 'Attendance List',
            self::PostMortem => 'Post Mortem',
        };
    }
}
