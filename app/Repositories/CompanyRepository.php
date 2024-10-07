<?php

namespace App\Repositories;

use App\Models\Company;

class CompanyRepository extends BaseRepository
{
    public function __construct(Company $company)
    {
        parent::__construct($company);
    }
    
    // You can add any specific methods related to User here
}
