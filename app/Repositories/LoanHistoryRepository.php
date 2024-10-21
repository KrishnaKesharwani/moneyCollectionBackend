<?php

namespace App\Repositories;

use App\Models\LoanHistory;
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
    
    // You can add any specific methods related to User here
}
