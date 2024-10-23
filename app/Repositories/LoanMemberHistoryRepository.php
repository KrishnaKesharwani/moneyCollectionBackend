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

    //delete member from loan
    public function deleteMember(array $data)
    {
        return $this->model->where(['loan_id' => $data['loan_id'], 'member_id' => $data['member_id']])->delete();
    }
}
