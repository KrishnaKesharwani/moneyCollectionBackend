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

    public function getAllCustomerLoans($company_id, $loanStatus =null, $status = null,$memberId = null,$customerId = null,$startDate = null,$endDate = null)
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
                ->when($startDate, function ($query, $startDate) {
                    return $query->whereDate('loan_status_change_date', '>=', $startDate);
                })
                ->when($endDate, function ($query, $endDate) {
                    return $query->whereDate('loan_status_change_date', '<=', $endDate);
                })
                ->orderBy('id', 'desc')
                ->get();

        return $loans;
    }

    /**
     * Get all member not assigned loans
     *
     * @param int $company_id
     * @param string|null $loanStatus
     * @return \Illuminate\Database\Eloquent\Collection
     */

    public function getAllmemberNotAssignedLoans($company_id, $loanStatus =null)
    {
        $loans = $this->model
                ->where('company_id', $company_id)
                ->where('status', 'active')
                ->where('assigned_member_id', 0)
                ->when($loanStatus, function ($query, $loanStatus) {
                    return $query->whereIn('loan_status', $loanStatus);
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

    public function getLoanHistory($customerId,$loanId,$fromDate){
        $history = DB::table('customer_loans')
            ->select('loan_history.*')
            ->join('loan_history', 'customer_loans.id', '=', 'loan_history.loan_id')
            ->where('customer_loans.customer_id', $customerId)
            ->where('loan_history.loan_id', $loanId)
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('loan_history.receive_date','>=', $fromDate);
            })            
            ->orderBy('loan_history.receive_date', 'asc');
            if($fromDate){
                $history = $history->get();
            }else{
                $history = $history->take(10);
                $history = $history->get();
            }

        return $history;
    }
    

    public function getTotalLoanAmount($company_id){
        return $this->model->totalLoanAmount($company_id)->sum('loan_amount');
    }

    public function getRunningLoanIds($company_id){
        return $this->model->totalLoanAmount($company_id)->pluck('id');
    }

    public function getTotalCustomer($company_id, $loanStatus){
        return $this->model
            ->where('company_id', $company_id)
            ->where('loan_status', $loanStatus)
            ->where('status', 'active')
            ->distinct('customer_id')
            ->count('customer_id');
    }
    // You can add any specific methods related to User here
}
