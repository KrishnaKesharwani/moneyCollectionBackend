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
    
    // You can add any specific methods related to User here
}
