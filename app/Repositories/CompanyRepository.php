<?php

namespace App\Repositories;

use App\Models\Company;

class CompanyRepository extends BaseRepository
{
    public function __construct(Company $company)
    {
        parent::__construct($company);
    }

    public function getCompanyIdByUserId($userId){
        return $this->model->where('user_id',$userId)->first();
    }
    
    // You can add any specific methods related to User here
}
