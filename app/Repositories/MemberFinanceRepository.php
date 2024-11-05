<?php

namespace App\Repositories;

use App\Models\MemberFinance;

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
                return $query->whereDate('payment_status', $paymentStatus);
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

}
