<?php

namespace App\Repositories;

use App\Models\LoanDocument;

class LoanDocumentRepository extends BaseRepository
{
    public function __construct(LoanDocument $loanDocument)
    {
        parent::__construct($loanDocument);
    }
    
    // You can add any specific methods related to User here
}
