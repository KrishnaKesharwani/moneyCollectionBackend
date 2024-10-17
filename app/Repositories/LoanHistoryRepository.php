<?php

namespace App\Repositories;

use App\Models\LoanHistory;

class LoanHistoryRepository extends BaseRepository
{
    public function __construct(LoanHistory $loanHistory)
    {
        parent::__construct($loanHistory);
    }
    

    public function getTotalPaidAmount($loanId){
        return $this->model->where('loan_id', $loanId)->sum('amount');
    }
    // You can add any specific methods related to User here
}
