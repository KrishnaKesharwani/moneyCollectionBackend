<?php

namespace App\Repositories;

use App\Models\Customer;

class CustomerRepository extends BaseRepository
{
    public function __construct(Customer $customer)
    {
        parent::__construct($customer);
    }
    
    // You can add any specific methods related to User here

    public function getById($customer_id)
    {
        return $this->model->with('user')->where('id', $customer_id)->first();
    }

    public function getCustomerbyUserId($userId){
        return $this->model->where('user_id', $userId)->first();
    }

    public function getAllCustomers($company_id, $status = null)
    {
        return $this->model->with('user')
                ->where('company_id', $company_id)
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->orderBy('id', 'desc')
                ->get();
    }

    public function checkCustomerExist($company_id, $member_id){
        return $this->model->where('company_id', $company_id)->where('id', $member_id)->first();
    }

    public function getCustomersCount($company_id, $status = null){
        return $this->model->where('company_id', $company_id)
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->count();        
    }
}