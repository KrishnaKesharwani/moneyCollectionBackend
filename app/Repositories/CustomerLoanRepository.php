<?php

namespace App\Repositories;

use App\Models\CustomerLoan;
use Illuminate\Support\Facades\DB;
class CustomerLoanRepository extends BaseRepository
{
    public function __construct(CustomerLoan $customerLoan)
    {
        parent::__construct($customerLoan);
    }

    
    public function getLoanById($id){
        return $this->model->where('id', $id)->with('customer', 'member', 'document')->first();
    }

    public function getAllCustomerLoans($company_id, $loanStatus =null, $status = null,$memberId = null,$customerId = null)
    {
        $loans = $this->model->with('customer', 'member', 'document', 'loanHistory', 'loanHistory.recieved_member')
                ->where('company_id', $company_id)
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->when($memberId, function ($query, $memberId) {
                    return $query->where('assigned_member_id', $memberId);
                })
                ->when($customerId, function ($query, $customerId) {
                    return $query->where('customer_id', $customerId);
                })
                ->when($loanStatus, function ($query, $loanStatus) {
                    return $query->where('loan_status', $loanStatus);
                })
                ->orderBy('id', 'desc')
                ->get();

        return $loans;
    }

    public function getTotalAttendedCustomer($loanId){
        //get unique customer count
        return $this->model->whereIn('id', $loanId)->distinct('customer_id')->count();
    }

    public function getTotalCustomers($memberId, $loanStatus) {
        return $this->model
            ->where('assigned_member_id', $memberId)
            ->where('loan_status', $loanStatus)
            ->where('status', 'active')
            ->distinct('customer_id')
            ->count('customer_id');
    }
    

    // You can add any specific methods related to User here
}
