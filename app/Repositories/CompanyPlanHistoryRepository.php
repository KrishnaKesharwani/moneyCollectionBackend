<?php

namespace App\Repositories;

use App\Models\CompanyPlanHistory;

class CompanyPlanHistoryRepository extends BaseRepository
{
    public function __construct(CompanyPlanHistory $companyPlanHistory)
    {
        parent::__construct($companyPlanHistory);
    }

    public function getTotalPaidAmount($planId){
        return $this->model->where('plan_id', $planId)->sum('amount');
    }

    // You can add any specific methods related to User here
}
