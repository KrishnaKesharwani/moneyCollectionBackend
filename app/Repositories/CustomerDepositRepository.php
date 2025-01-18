<?php

namespace App\Repositories;

use App\Models\CustomerDeposit;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class CustomerDepositRepository extends BaseRepository
{
    public function __construct(CustomerDeposit $customerDeposit)
    {
        parent::__construct($customerDeposit);
    }

    
    public function getDepositById($id){
        return $this->model->where('id', $id)->with('customer', 'member')->first();
    }

    public function getAllActiveDeposits($company_id,$customerId = null){
        return $this->model->where('company_id', $company_id)
        ->when($customerId, function ($query, $customerId) {
            return $query->where('customer_id', $customerId);
        })
        ->where('status', 'active');
    }

    public function getTotalCustomerByDepositIds($depositIds){
        return $this->model->whereIn('id', $depositIds)->distinct('customer_id')->count();
    }


    public function getAllCustomerDeposits($company_id, $status = null,$memberId = null,$customerId = null)
    {
        $deposits = $this->model->with('customer', 'member','depositHistory', 'depositHistory.recieved_member')
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
                ->orderBy('id', 'desc')
                ->get();

        return $deposits;
    }


    public function getTotalAttendedDepositCustomer($depositId){
        //get unique customer count
        return $this->model->whereIn('id', $depositId)->distinct('customer_id')->count();
    }

    public function getTotalDepositCustomers($memberId, $status) {
        return $this->model
            ->where('assigned_member_id', $memberId)
            ->where('status', $status)
            ->distinct('customer_id')
            ->count('customer_id');
    }

    public function getTotalDepositCustomersId($memberId, $status) {
        return $this->model
            ->where('assigned_member_id', $memberId)
            ->where('status', $status)
            ->distinct('customer_id')
            ->pluck('customer_id');
    }
    

    //get total credit amount by a member of last date
    public function getLastDateTransaction($companyId, $memberId, $customer, $depositType)
    {
        $latestTransaction = DB::table('deposit_history as dh1')
            ->where('dh1.action_type', $depositType)
            ->orderByDesc('dh1.created_at')
            ->first();

        $amount = 0;

        if (!empty($latestTransaction)) {
            $latestDate = Carbon::parse($latestTransaction->created_at)->format('Y-m-d'); // Convert to Y-m-d format
            
            $amount = DB::table('customer_deposits as cd')
                ->where('cd.company_id', $companyId)
                ->when($memberId, function ($query, $memberId) {
                    return $query->where('cd.assigned_member_id', $memberId);
                })
                ->when($customer, function ($query, $customer) {
                    return $query->where('cd.customer_id', $customer);
                })
                ->join('deposit_history as dh', 'cd.id', '=', 'dh.deposit_id')
                ->where('dh.action_type', '=', $depositType)
                ->whereDate('dh.created_at', '=', $latestDate) // Compare both dates in Y-m-d format
                ->sum('dh.amount');
        }

        return (float)$amount;
    }
    
    public function getdepositHistory($customerId,$depositId,$fromDate){
        $history = DB::table('customer_deposits')
            ->select('deposit_history.*')
            ->join('deposit_history', 'customer_deposits.id', '=', 'deposit_history.deposit_id')
            ->where('customer_deposits.customer_id', $customerId)
            ->where('deposit_history.deposit_id', $depositId)
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('deposit_history.created_at','>=', $fromDate);
            })            
            ->orderBy('deposit_history.created_at', 'desc');
            if($fromDate){
                $history = $history->get();
            }else{
                $history = $history->take(10);
                $history = $history->get();
            }

        return $history;
    }


    public function getDepositsCount($companyId=null,$status = null,$customerId = null){
        return $this->model
        ->when($companyId, function ($query, $companyId) {
            return $query->where('company_id', $companyId);
        })
        ->when($customerId, function ($query, $customerId) {
            return $query->whereIn('customer_id', $customerId);
        })
        ->when($status, function ($query, $status) {
            return $query->where('status', $status);
        })
        ->count();
    }


    public function getCustomerDpositsCounts(array $customerIds)
    {
        return $this->model
            ->selectRaw('customer_id, COUNT(*) as total, 
                        SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN status = "inactive" THEN 1 ELSE 0 END) as inactive')
            ->whereIn('customer_id', $customerIds)
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id')
            ->toArray();
    }


    /**
     * Get distinct customer id of deposits by company id with optional date range
     *
     * @param int $companyId
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return array
     */

    public function getDepositCustomersIdbyCompany($companyId,$fromDate=null,$toDate=null,$memberId=null){
        return $this->model->where('company_id', $companyId)
                        ->when($fromDate, function ($query, $fromDate) {
                            return $query->whereDate('created_at','>=', $fromDate);
                        })
                        ->when($toDate, function ($query, $toDate) {
                            return $query->whereDate('created_at','<=', $toDate);
                        })
                        ->when($memberId, function ($query, $memberId) {
                            return $query->where('assigned_member_id', $memberId);
                        })
                        ->distinct('customer_id')
                        ->where('status', 'active')
                        ->pluck('customer_id')
                        ->toArray();
    }
    // You can add any specific methods related to User here
}
