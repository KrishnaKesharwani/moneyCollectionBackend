<?php

namespace App\Repositories;

use App\Models\MemberFinanceHistory;

class MemberFinanceHistoryRepository extends BaseRepository
{
    public function __construct(MemberFinanceHistory $memberFinanceHistory)
    {
        parent::__construct($memberFinanceHistory);
    }
    
    // You can add any specific methods related to User here
}
