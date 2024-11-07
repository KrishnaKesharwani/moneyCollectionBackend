<?php

namespace App\Repositories;

use App\Models\FixedDeposit;
use Illuminate\Support\Facades\DB;
class FixedDepositRepository extends BaseRepository
{
    public function __construct(FixedDeposit $fixedDeposit)
    {
        parent::__construct($fixedDeposit);
    }

    
    public function getDepositById($id){
        return $this->model->where('id', $id)->with('customer')->first();
    }

    public function getAllFixedDeposits($company_id, $status = null,$customerId = null)
    {
        $deposits = $this->model->with('customer','fixedDepositHistory')
                ->where('company_id', $company_id)
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
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

    //get total credit amount by a member of last date
    

    // public function getdepositHistory($customerId,$depositId,$fromDate){
    //     $history = DB::table('customer_deposits')
    //         ->select('deposit_history.*')
    //         ->join('deposit_history', 'customer_deposits.id', '=', 'deposit_history.deposit_id')
    //         ->where('customer_deposits.customer_id', $customerId)
    //         ->where('deposit_history.deposit_id', $depositId)
    //         ->when($fromDate, function ($query, $fromDate) {
    //             return $query->whereDate('action_date','>=', $fromDate);
    //         })            
    //         ->orderBy('deposit_history.action_date', 'asc');
    //         if($fromDate){
    //             $history = $history->get();
    //         }else{
    //             $history = $history->take(10);
    //             $history = $history->get();
    //         }

    //     return $history;
    // }
    // You can add any specific methods related to User here
}
