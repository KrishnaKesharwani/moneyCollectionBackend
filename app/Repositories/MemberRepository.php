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

    public function getById($member_id)
    {
        return $this->model->with('user')->where('id', $member_id)->first();
    }

    public function getMemberByUserId($userId){
        return $this->model->where('user_id', $userId)->first();
    }

    public function getAllMembers($company_id, $status = null)
    {
        return $this->model->with('user')
                ->where('company_id', $company_id)
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->orderBy('id', 'desc')
                ->get();
    }


    public function checkMemberExist($company_id, $member_id){
        return $this->model->where('company_id', $company_id)->where('id', $member_id)->first();
    }

    public function getMembersCount($company_id, $status = null){
        return $this->model->where('company_id', $company_id)
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->count();        
    }

}
