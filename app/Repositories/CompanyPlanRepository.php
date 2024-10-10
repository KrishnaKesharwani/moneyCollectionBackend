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

    /**
     * Check if a company has an existing active plan
     *
     * @param int $company_id The ID of the company
     * @param string $date The date to check for active plan
     *
     * @return \App\Models\CompanyPlan|null
     */
    public function checkExistingActivePlan($company_id,$date){
       return $this->model->where('company_id', $company_id)->whereIn('status', ['active', 'pending'])->wheredate('end_date','>=', $date)->first();    
    }

    // You can add any specific methods related to User here
}
