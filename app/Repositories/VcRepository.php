<?php

namespace App\Repositories;

use App\Models\Vc;

class VcRepository extends BaseRepository
{
    public function __construct(Vc $vc)
    {
        parent::__construct($vc);
    }
    
    // You can add any specific methods related to User here

    public function getById($vc_id)
    {
        return $this->model->with('user')->where('id', $vc_id)->first();
    }

    public function getvcByUserId($userId){
        return $this->model->where('user_id', $userId)->first();
    }

    public function getAllvcs($company_id, $status = null)
    {
        return $this->model->with('user')
                ->where('company_id', $company_id)
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->orderBy('id', 'desc')
                ->get();
    }


    public function checkvcExist($company_id, $vc_id){
        return $this->model->where('company_id', $company_id)->where('id', $vc_id)->first();
    }

    public function getvcsCount($company_id, $status = null){
        return $this->model->where('company_id', $company_id)
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->count();        
    }

}
