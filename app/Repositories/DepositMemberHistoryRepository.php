<?php

namespace App\Repositories;

use App\Models\DepositMemberHistory;

class DepositMemberHistoryRepository extends BaseRepository
{
    public function __construct(DepositMemberHistory $depositMemberHistory)
    {
        parent::__construct($depositMemberHistory);
    }
    
    // You can add any specific methods related to User here
}
