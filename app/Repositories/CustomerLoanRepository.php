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
        return $this->model->where('id', $id)->with('customer', 'member', 'document')->first();
    }

    public function getAllCustomerLoans($company_id, $loanStatus =null, $status = null)
    {
        return $this->model->with('customer', 'member', 'document')
                ->where('company_id', $company_id)
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->when($loanStatus, function ($query, $loanStatus) {
                    return $query->where('loan_status', $loanStatus);
                })
                ->orderBy('id', 'desc')
                ->get();
    }

    // You can add any specific methods related to User here
}
