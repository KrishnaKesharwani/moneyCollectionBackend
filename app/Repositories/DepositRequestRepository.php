<?php

namespace App\Repositories;

use App\Models\DepositRequest;
use Illuminate\Support\Facades\DB;

class DepositRequestRepository extends BaseRepository
{
    public function __construct(DepositRequest $depositRequest)
    {
        parent::__construct($depositRequest);
    }

    public function depositRequestList($companyId,$customerId=null,$status=null,$requestDate=null)
    {
        $requests = DB::table('deposit_requests')
                    ->select('deposit_requests.*','customer_deposits.deposit_no','customers.name as customer_name')
                    ->join('customer_deposits', 'deposit_requests.deposit_id', '=', 'customer_deposits.id')
                    ->join('customers', 'customer_deposits.customer_id', '=', 'customers.id')
                    ->where('customer_deposits.company_id', $companyId)
                    ->when($requestDate, function ($query) use ($requestDate) {
                        return $query->whereDate('deposit_requests.request_date', $requestDate);
                    })
                    ->when($customerId, function ($query) use ($customerId) {
                        return $query->where('customer_deposits.customer_id', $customerId);
                    })
                    ->when($status, function ($query) use ($status) {
                        return $query->whereIn('deposit_requests.status', $status);
                    })
                    ->orderBy('deposit_requests.id', 'desc');
        return $requests->get();
    }


    public function loanRequestList($companyId,$customerId)
    {
        $requests = DB::table('customer_loans')
                    ->select(
                        'customer_loans.id',
                        'customer_loans.loan_no as request_no',
                        'customer_loans.loan_amount as amount',
                        'customer_loans.apply_date as request_date',
                        'customer_loans.loan_status as status',
                        'customer_loans.details as reason',
                        'customer_loans.id as request_id',
                        'customer_loans.loan_status_message as replied_message',
                        'customer_loans.applied_by',
                        'customer_loans.applied_user_type',
                        'customers.name as customer_name',
                    )
                    ->join('customers', 'customer_loans.customer_id', '=', 'customers.id')
                    ->where('customer_loans.company_id', $companyId)
                    ->where('customer_loans.applied_by','!=',0)
                    //->where('customer_loans.loan_status', 'pending')
                    ->when($customerId, function ($query) use ($customerId) {
                        return $query->where('customer_loans.customer_id', $customerId);
                    })
                    ->orderBy('customer_loans.id', 'desc');
        return $requests->get();
    }
}