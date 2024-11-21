<?php

namespace App\Repositories;

use App\Models\DepositRequest;

class DepositRequestRepository extends BaseRepository
{
    public function __construct(DepositRequest $depositRequest)
    {
        parent::__construct($depositRequest);
    }

    
}