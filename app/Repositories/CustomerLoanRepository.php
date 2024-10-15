<?php

namespace App\Repositories;

use App\Models\CustomerLoan;

class CustomerLoanRepository extends BaseRepository
{
    public function __construct(CustomerLoan $customerLoan)
    {
        parent::__construct($customerLoan);
    }

    
    public function getLoanById($id){
        return $this->model->where('id', $id)->with('customer', 'member')->first();
    }

    public function getAllCustomerLoans($company_id){
        return $this->model->where('company_id', $company_id)->with('customer', 'member')->orderBy('id', 'desc')->get();
    }
    // You can add any specific methods related to User here
}
