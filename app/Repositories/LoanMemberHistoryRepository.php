<?php

namespace App\Repositories;

use App\Models\LoanMemberHistory;

class LoanMemberHistoryRepository extends BaseRepository
{
    public function __construct(LoanMemberHistory $loanMemberHistory)
    {
        parent::__construct($loanMemberHistory);
    }
    
    // You can add any specific methods related to User here
}
