<?php

namespace App\Repositories;

use App\Models\LoanStatusHistory;

class LoanStatusHistoryRepository extends BaseRepository
{
    public function __construct(LoanStatusHistory $loanStatusHistory)
    {
        parent::__construct($loanStatusHistory);
    }
    
    // You can add any specific methods related to User here
}
