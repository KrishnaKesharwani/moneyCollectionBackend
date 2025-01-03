<?php

namespace App\Repositories;

use App\Models\MemberFinance;
use Hamcrest\Arrays\IsArray;
use Illuminate\Support\Facades\DB;

class MemberFinanceRepository extends BaseRepository
{
    public function __construct(MemberFinance $memberFinance)
    {
        parent::__construct($memberFinance);
    }
    
    // You can add any specific methods related to User here

    public function getMemberFinance($memberId, $companyId, $paidDate=null,$paymentStatus = null)
    {
        return $this->model->where('member_id', $memberId)
            ->where('company_id', $companyId)
            ->when($paidDate, function ($query, $paidDate) {
                return $query->whereDate('collect_date', $paidDate);
            })
            ->when($paymentStatus, function ($query, $paymentStatus) {
                if(is_array($paymentStatus)){
                    return $query->whereIn('payment_status', $paymentStatus);
                }else{
                    return $query->where('payment_status', $paymentStatus);
                }
                
            })
            ->first();
    }

    public function getLastDateMemberFinance($memberId, $companyId)
    {
        return $this->model->where('member_id', $memberId)
            ->where('company_id', $companyId)
            ->orderBy('collect_date', 'desc')
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
        return $this->model->with('member','member_finance_history')->where('company_id', $companyId)
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

    public function getMemberFinanceHistoryDetail($depositId)
    {
        return DB::table('member_finance')
            ->join('member_finance_history', 'member_finance.id', '=', 'member_finance_history.member_finance_id')
            ->join('members', 'members.id', '=', 'member_finance.member_id')
            ->where('member_finance_history.history_id', $depositId)
            ->where('member_finance_history.amount_by','deposit')
            ->where('member_finance.payment_status','working')
            ->select('member_finance_history.*', 'member_finance.payment_status','member_finance.id as finance_id','member_finance.balance as member_finance_balance','member_finance.member_id','members.balance as member_balance','member_finance.company_id')
            ->first();
    }


    public function getMemberFinanceBalance($memberId, $companyId)
    {
        return $this->model->where('member_id', $memberId)
            ->join('members', 'members.id', '=', 'member_finance.member_id')
            ->where('member_finance.company_id', $companyId)
            ->select('member_finance.*', 'members.balance as member_balance')
            ->orderBy('member_finance.id', 'desc')
            ->take(1)
            ->first();
    }
}
