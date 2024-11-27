<?php

namespace App\Repositories;

use App\Models\MemberFinance;
use Illuminate\Support\Facades\DB;

class MemberFinanceRepository extends BaseRepository
{
    public function __construct(MemberFinance $memberFinance)
    {
        parent::__construct($memberFinance);
    }
    
    // You can add any specific methods related to User here

    public function getMemberFinance($memberId, $companyId, $collectDate=null,$paymentStatus = null)
    {
        return $this->model->where('member_id', $memberId)
            ->where('company_id', $companyId)
            ->when($collectDate, function ($query, $collectDate) {
                return $query->whereDate('collect_date', $collectDate);
            })
            ->when($paymentStatus, function ($query, $paymentStatus) {
                return $query->where('payment_status', $paymentStatus);
            })
            ->first();
    }
    

    public function updateMemberFinance($memberId, $companyId,$reciveDate)
    {
        return $this->model->where('member_id', $memberId)
            ->where('company_id', $companyId)
            ->where('payment_status', 'working')
            ->whereDate('collect_date','!=', $reciveDate)
            ->update(['payment_status' => 'unpaid']);
    }

    public function getCollection($companyId, $collectDate=null)
    {
        return $this->model->with('member')->where('company_id', $companyId)
            ->when($collectDate, function ($query, $collectDate) {
                return $query->whereDate('collect_date', $collectDate);
            })
            ->orderBy('id', 'desc')
            ->get();
    }

    public function getAdvanceMoney($memberId, $companyId,$date)
    {
        return DB::table('member_finance')
            ->join('member_finance_history', 'member_finance.id', '=', 'member_finance_history.member_finance_id')
            ->where('member_finance.member_id', $memberId)
            ->where('member_finance.company_id', $companyId)
            ->where('member_finance.payment_status','!=','paid')
            ->whereDate('member_finance_history.amount_date', $date)
            ->where('member_finance_history.amount_by', 'advance')
            ->sum('amount');
    }
}
