<?php

namespace App\Repositories;

use App\Models\LoanHistory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LoanHistoryRepository extends BaseRepository
{
    public function __construct(LoanHistory $loanHistory)
    {
        parent::__construct($loanHistory);
    }
    

    public function getTotalPaidAmount($loanId){
        return $this->model->where('loan_id', $loanId)->sum('amount');
    }

    public function getTotalPaidAmountByLoanIds($loanId){
        return $this->model->whereIn('loan_id', $loanId)->sum('amount');
    }

    public function getMaxLoanHistoryDate($loanId){
        $maxDate = $this->model->where('loan_id', $loanId)->max('receive_date');
        if($maxDate)
            return $maxDate;
        else
            return null;
    }

    public function getTodayCollection($memberId){
        $data = $this->model->with('loan.customer')->where('receiver_member_id', $memberId)->whereDate('receive_date', carbon::today())->get();
        return $data;
    }

    public function getLoanReceivedAmountByDate($companyId, $memberId, $date){
        $amount = DB::table('loan_history')
                    ->join('customer_loans', 'loan_history.loan_id', '=', 'customer_loans.id')
                    ->where('customer_loans.company_id', $companyId)
                    ->where('loan_history.receiver_member_id', $memberId)
                    ->whereDate('loan_history.receive_date', $date)
                    ->sum('loan_history.amount');
        return $amount;
    }
    
    // You can add any specific methods related to User here
}
