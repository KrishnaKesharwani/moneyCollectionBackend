<?php

namespace App\Repositories;

use App\Models\CompanyPlan;

class CompanyPlanRepository extends BaseRepository
{
    public function __construct(CompanyPlan $companyPlan)
    {
        parent::__construct($companyPlan);
    }

    public function getCompanyPlan($planId){
        return $this->model->where('id', $planId)->get();
    }

    // You can add any specific methods related to User here
}
