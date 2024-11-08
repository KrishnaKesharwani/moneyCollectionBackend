<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Repositories\OfferRepository;
use Carbon\Carbon;
use exception;

class OfferController extends Controller
{


    protected $offerRepository;

    public function __construct(
        OfferRepository $offerRepository,
        )
    {
        $this->offerRepository          = $offerRepository;
    }

    public function index(Request $request){
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|integer|exists:companies,id',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            $status = $request->status ?? null;
            $offers = $this->offerRepository->getAllOffers($request->company_id,$status);

            if($offers->isEmpty())
            {
                return sendErrorResponse('offers not found!', 404);
            }
            else
            {
                return sendSuccessResponse('offers found successfully!', 200, $offers);
            }
        }
        catch (\Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        // Validate the request

        $validator = Validator::make($request->all(), [
            'company_id' => 'required |integer|exists:companies,id',
            'name'  => 'required |string',
            'type' => 'required|string',
            'image' => 'nullable|string',
            'status' => 'required|string',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            
            DB::beginTransaction();
            // Process the base64 images
            $validatedData['image']            = storeBase64Image($request->image, 'offers');
            if(isset($request->default_offer) && $request->default_offer==true)
            {
                $validatedData['default_offer']    = 1;
            }
            else{
                $validatedData['default_offer']    = 0;
            }
            // Store the company data in the database
            $offer = $this->offerRepository->create($validatedData);

            // Check if the company was successfully created
            if ($offer)
            {   
                // Set default offer flag to 0 for all the offers except the given offer id
                if(isset($request->default_offer) && $request->default_offer==1)
                {
                    $this->offerRepository->updateDefaultOffer($offer->id,$offer->company_id);
                }
                DB::commit();
                return sendSuccessResponse('Offer created successfully!', 201, $offer);
            }
            else
            {
                return sendErrorResponse('Offer not created!', 404);
            }
        }
        catch (Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    public function update(Request $request)
    {
        // Validate the request

        $validator = Validator::make($request->all(), [
            'offer_id' => 'required|integer|exists:offers,id',
            'company_id' => 'required|integer|exists:companies,id',
            'name'  => 'required |string',
            'type' => 'required|string',
            'image' => 'nullable|string',
            'status' => 'required|string',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            
            DB::beginTransaction();
            // Process the base64 images
            if(isset($request->image) && $request->image!=''){
                $validatedData['image']   = storeBase64Image($request->image, 'offers');
            }else{
                unset($validatedData['image']);
            }

            if(isset($request->default_offer) && $request->default_offer==true)
            {
                $validatedData['default_offer']    = 1;
            }
            else{
                $validatedData['default_offer']    = 0;
            }
            // Store the company data in the database
            $offer = $this->offerRepository->update($request->offer_id,$validatedData);

            // Check if the company was successfully created
            if ($offer)
            {   
                // Set default offer flag to 0 for all the offers except the given offer id
                if(isset($request->default_offer) && $request->default_offer==1)
                {
                    $this->offerRepository->updateDefaultOffer($request->offer_id,$request->company_id);
                }
                DB::commit();
                return sendSuccessResponse('Offer updated successfully!', 201, $offer);
            }
            else
            {
                return sendErrorResponse('Offer not updated!', 404);
            }
        }
        catch (Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }
    

    public function updateOfferStatus(Request $request){

        $validator = Validator::make($request->all(), [
            'offer_id' => 'required',
            'status' => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $offer = $this->offerRepository->find($request->offer_id);
        if($offer)
        {
            DB::beginTransaction();
            
            $offer->status = $request->status;
            $offer->save();

            DB::commit();
            $offerData = $this->offerRepository->find($offer->id);
            if($request->status=='active')
            {
                return sendSuccessResponse('Offer Activated successfully!',200,$offerData);
            }else{
                return sendSuccessResponse('Offer Inactived successfully!',200,$offerData);
            }
        }
        else
        {
            return sendErrorResponse('Offer not found!', 404);
        }
    }

    public function updateDefaultOffer(Request $request){

        $validator = Validator::make($request->all(), [
            'offer_id' => 'required',
            'default_offer' => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $offer = $this->offerRepository->find($request->offer_id);
        if($offer)
        {
            DB::beginTransaction();
            
            $offer->default_offer = $request->default_offer;
            $offer->save();

            // Set default offer flag to 0 for all the offers except the given offer id
            if(isset($request->default_offer) && $request->default_offer==1)
            {
                $this->offerRepository->updateDefaultOffer($request->offer_id,$offer->company_id);
            }

            DB::commit();
            $offerData = $this->offerRepository->find($offer->id);
            if($request->default_offer==1)
            {
                return sendSuccessResponse('Offer set default successfully!',200,$offerData);
            }else{
                return sendSuccessResponse('Offer removed from default!',200,$offerData);
            }
        }
        else
        {
            return sendErrorResponse('Offer not found!', 404);
        }
    }
}
