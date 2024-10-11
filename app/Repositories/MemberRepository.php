<?php

namespace App\Repositories;

use App\Models\Member;

class MemberRepository extends BaseRepository
{
    public function __construct(Member $member)
    {
        parent::__construct($member);
    }
    
    // You can add any specific methods related to User here
}
