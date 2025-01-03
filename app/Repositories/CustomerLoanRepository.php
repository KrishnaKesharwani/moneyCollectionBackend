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
        return $this->model->where('id', $id)->with('customer', 'member', 'document', 'loanHistory', 'loanHistory.recieved_member')->first();
    }

    /**
     * Get all customer loans by given conditions
     *
     * @param int|null $company_id
     * @param string|null $loanStatus
     * @param string|null $status
     * @param int|null $memberId
     * @param int|null $customerId
     * @param string|null $startDate
     * @param string|null $endDate
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
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
                    if($loanStatus == 'all'){
                        return $query->where('loan_status','!=','pending');
                    }{
                        return $query->where('loan_status', $loanStatus);
                    }
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


    public function getAllCustomerLoansStatus($company_id, $loanStatus =null, $status = null,$customerId = null)
    {
        $loans = $this->model->where('company_id', $company_id)
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->when($customerId, function ($query, $customerId) {
                    return $query->where('customer_id', $customerId);
                })
                ->when($loanStatus, function ($query, $loanStatus) {
                    return $query->where('loan_status', $loanStatus);
                })
                ->select('loan_no','id','loan_amount')
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

    public function getTotalCustomersId($memberId, $loanStatus) {
        return $this->model
            ->where('assigned_member_id', $memberId)
            ->where('loan_status', $loanStatus)
            ->where('status', 'active')
            ->distinct('customer_id')
            ->pluck('customer_id');
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

    public function getPaidAmountByLoanIds($customerId,$loanIds){
        $amount = DB::table('customer_loans')
            ->select('loan_history.*')
            ->join('loan_history', 'customer_loans.id', '=', 'loan_history.loan_id')
            ->where('customer_loans.customer_id', $customerId)
            ->whereIn('loan_history.loan_id', $loanIds)
            ->sum('loan_history.amount');

        return $amount;
    }

    
    

    public function getTotalLoanAmount($company_id, $memberId = null, $customerId = null) {
        return $this->model->where('company_id', $company_id)
                    ->when($memberId, function ($query) use ($memberId) {
                        return $query->where('assigned_member_id', $memberId);
                    })
                    ->when($customerId, function ($query) use ($customerId) {
                        return $query->where('customer_id', $customerId);
                    })
                    ->where('status', 'active')
                    ->where('loan_status', 'paid')
                    ->sum('loan_amount');
    }

    public function getRunningLoanIds($company_id, $memberId = null,$customerId = null) {
        return $this->model->where('company_id', $company_id)
                    ->when($memberId, function ($query) use ($memberId) {
                        return $query->where('assigned_member_id', $memberId);
                    })
                    ->when($customerId, function ($query) use ($customerId) {
                        return $query->where('customer_id', $customerId);
                    })
                    ->where('status', 'active')
                    ->where('loan_status', 'paid')
                    ->pluck('id');
    }

    /**
     * Get the total number of distinct active customers for a given company and loan status.
     *
     * @param int $company_id The ID of the company.
     * @param string $loanStatus The status of the loan.
     * @return int The count of distinct customers.
     */

    public function getTotalCustomer($company_id, $loanStatus,$memberId = null) {   
        return $this->model
            ->where('company_id', $company_id)
            ->when($memberId, function ($query) use ($memberId) {
                return $query->where('assigned_member_id', $memberId);
            })
            ->where('loan_status', $loanStatus)
            ->where('status', 'active')
            ->distinct('customer_id')
            ->count('customer_id');
    }

    public function getAssignedCustomersId($company_id, $loanStatus,$memberId = null) {   
        return $this->model
            ->where('company_id', $company_id)
            ->when($memberId, function ($query) use ($memberId) {
                return $query->where('assigned_member_id', $memberId);
            })
            ->where('loan_status', $loanStatus)
            ->where('status', 'active')
            ->distinct('customer_id')
            ->pluck('customer_id');
    }

    public function getCustomerLoansCount($companyId=null, $status=null,$customerId=null,$loanStatus=null) {
        return $this->model
                ->when($companyId, function ($query, $companyId) {
                    return $query->where('company_id', $companyId);
                })
                ->when($customerId, function ($query, $customerId) {
                    return $query->where('customer_id', $customerId);
                })
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                })
               ->when($loanStatus, function ($query, $loanStatus) {
                return $query->where('loan_status', $loanStatus);
                })
                ->where('loan_status', '!=', 'pending')
               ->count();
    }

    public function getCustomerLoansCounts(array $customerIds, string $status)
    {
        return $this->model
            ->selectRaw('customer_id, COUNT(*) as total, 
                        SUM(CASE WHEN loan_status = "paid" THEN 1 ELSE 0 END) as paid,
                        SUM(CASE WHEN loan_status = "cancelled" THEN 1 ELSE 0 END) as cancelled,
                        SUM(CASE WHEN loan_status = "completed" THEN 1 ELSE 0 END) as completed')
            ->whereIn('customer_id', $customerIds)
            ->when($status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->where('loan_status', '!=', 'pending')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id')
            ->toArray();
    }


    public function getCustomerLoansAmount($companyId,array $customerIds, string $loan_status)
    {
        return $this->model
            ->selectRaw('customer_id, sum(loan_amount) as total')
            ->whereIn('customer_id', $customerIds)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->when($loan_status, function ($query, $loan_status) {
                return $query->where('loan_status', $loan_status);
            })
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id')
            ->toArray();
    }


    public function getCustomerLoansHistoryAmount($companyId, array $customerIds, string $loanStatus)
    {
        return $this->model
            ->join('loan_history', 'customer_loans.id', '=', 'loan_history.loan_id') // Join loans with loan_history table
            ->selectRaw('customer_loans.customer_id, SUM(loan_history.amount) as total') // Calculate sum from loan_history
            ->whereIn('customer_loans.customer_id', $customerIds) // Filter by customer IDs
            ->where('customer_loans.company_id', $companyId) // Filter by company ID
            ->where('customer_loans.status', 'active') // Filter by loan status in loans table
            ->when($loanStatus, function ($query, $loanStatus) {
                return $query->where('customer_loans.loan_status', $loanStatus); // Apply additional loan_status filter
            })
            ->groupBy('customer_loans.customer_id') // Group by customer_id
            ->get()
            ->keyBy('customer_id') // Organize results using customer_id as the key
            ->toArray();
    }

    public function getLoanCustomersIdbyCompany($companyId,$fromDate=null,$toDate=null,$memberId=null) {
        return $this->model->where('company_id', $companyId)
                        ->when($fromDate, function ($query, $fromDate) {
                            return $query->whereDate('start_date','>=', $fromDate);
                        })
                        ->when($memberId, function ($query) use ($memberId) {
                            return $query->where('assigned_member_id', $memberId);
                        })
                        ->when($toDate, function ($query, $toDate) {
                            return $query->whereDate('start_date','<=', $toDate);
                        })
                        ->distinct('customer_id')
                        ->where('status', 'active')
                        ->whereNotIn('loan_status',['cancelled','other','pending'])
                        ->pluck('customer_id')
                        ->toArray();
    }
    // You can add any specific methods related to User here
}
