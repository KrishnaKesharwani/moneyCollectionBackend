<?php

namespace App\Repositories;

use App\Models\CustomerDeposit;
use Illuminate\Support\Facades\DB;
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
    

    //get total credit amount by a member of last date
    public function getLastDateTransaction($companyId, $memberId, $customer, $depositType)
    {
        $latestTransactions = DB::table('deposit_history')
            ->select('deposit_id', DB::raw('MAX(action_date) as latest_date'))
            ->where('action_type', $depositType)
            ->groupBy('deposit_id');

        $amount = DB::table('customer_deposits')
            ->where('customer_deposits.company_id', $companyId)
            ->when($memberId, function ($query, $memberId) {
                return $query->where('customer_deposits.assigned_member_id', $memberId);
            })
            ->when($customer, function ($query, $customer) {
                return $query->where('customer_deposits.customer_id', $customer);
            })
            ->join('deposit_history', function ($join) use ($depositType) {
                $join->on('customer_deposits.id', '=', 'deposit_history.deposit_id')
                    ->where('deposit_history.action_type', '=', $depositType);
            })
            ->joinSub($latestTransactions, 'latest_transactions', function ($join) {
                $join->on('deposit_history.deposit_id', '=', 'latest_transactions.deposit_id')
                    ->on('deposit_history.action_date', '=', 'latest_transactions.latest_date');
            })
            ->sum('deposit_history.amount');

        return (float)$amount;
    }


    public function getdepositHistory($customerId,$depositId,$fromDate){
        $history = DB::table('customer_deposits')
            ->select('deposit_history.*')
            ->join('deposit_history', 'customer_deposits.id', '=', 'deposit_history.deposit_id')
            ->where('customer_deposits.customer_id', $customerId)
            ->where('deposit_history.deposit_id', $depositId)
            ->when($fromDate, function ($query, $fromDate) {
                return $query->whereDate('action_date','>=', $fromDate);
            })            
            ->orderBy('deposit_history.action_date', 'asc');
            if($fromDate){
                $history = $history->get();
            }else{
                $history = $history->take(10);
                $history = $history->get();
            }

        return $history;
    }
    // You can add any specific methods related to User here
}
