<?php

namespace App\Repositories;

use App\Models\Offer;
use App\Repositories\OfferRepository;

class OfferRepository extends BaseRepository
{
    public function __construct(Offer $offer)
    {
        parent::__construct($offer);
    }

    public function getAllOffers($company_id, $status = null)
    {
        return $this->model->with('company')
                ->where('company_id', $company_id)
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->orderBy('id', 'desc')
                ->get();
    }

    /**
     * Set default offer flag to 0 for all the offers except the given offer id
     * 
     * @param int $offerId The id of the offer for which we want to set default offer flag to 1
     * 
     * @return void
     */
    public function updateDefaultOffer($offerId,$companyId){
        return $this->model->where('id','!=',$offerId)->where('company_id',$companyId)->update(['default_offer' => 0]);
    }

    public function getOffersCount($company_id, $status = null)
    {
        return $this->model->where('company_id', $company_id)
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->count();
    }
}