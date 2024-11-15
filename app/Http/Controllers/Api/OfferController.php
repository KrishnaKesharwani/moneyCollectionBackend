<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Repositories\OfferRepository;
use App\Repositories\ReportBackupRepository;
use Carbon\Carbon;
use exception;
//excel library for download excel
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfferController extends Controller
{


    protected $offerRepository;
    protected $reportBackupRepository;

    public function __construct(
        OfferRepository $offerRepository,
        ReportBackupRepository $reportBackupRepository
        )
    {
        $this->offerRepository          = $offerRepository;
        $this->reportBackupRepository   = $reportBackupRepository;
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
                if(isset($request->default_offer) && $request->default_offer==true)
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
                if(isset($request->default_offer) && $request->default_offer==true)
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

    public function updateDefaultOffer(Request $request)
    {

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
            $defaultOffer = 0;
            if($request->default_offer==true)
            {
                $defaultOffer = 1;
            }
            $offer->default_offer = $defaultOffer;
            $offer->save();

            // Set default offer flag to 0 for all the offers except the given offer id
            if(isset($request->default_offer) && $defaultOffer==1)
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

    public function destroy($id)
    {
        $offer = $this->offerRepository->find($id);
        if($offer)
        {
            $this->offerRepository->delete($id);
            return sendSuccessResponse('Offer deleted successfully!', 200);
        }
        else
        {
            return sendErrorResponse('Offer not found!', 404);
        }
    }

    public function downloadOffers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|exists:companies,id',
            'status' => 'required',
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $status = null;
        if ($request->status == 'all') {
            $status = null;
        } else {
            $status = $request->status;
        }

        $companyId = $request->company_id;

        // Create new Spreadsheet object
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set the header
        $sheet->setCellValue('A1', 'Serial No');
        $sheet->setCellValue('B1', 'Name');
        $sheet->setCellValue('C1', 'Type');
        $sheet->setCellValue('D1', 'Status');
        $sheet->setCellValue('E1', 'Details');
        $sheet->setCellValue('F1', 'Default Offer');


        // Retrieve your data from the database (example: getting users)
        $offers = $this->offerRepository->getAllOffers($companyId, $status);

        // Populate the spreadsheet with data
        $row = 2; // Start from row 2 to avoid overwriting headers
        foreach ($offers as $offer) {
            $sheet->setCellValue('A' . $row, $row-1);
            $sheet->setCellValue('B' . $row, $offer->name);
            $sheet->setCellValue('c' . $row, $offer->type);
            $sheet->setCellValue('d' . $row, $offer->status);
            $sheet->setCellValue('e' . $row, $offer->details);
            $sheet->setCellValue('f' . $row, ($offer->default_offer==1)?'Yes':'No');
            $row++;
        }

        // Set up the response for download
        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output'); // Stream the file directly to the response
        });

        // Set headers for file download
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="offers.xlsx"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        // Return the response

        if($response){
            $this->reportBackupRepository->create([
                'company_id'    => $companyId,
                'backup_type'   => 'offer_list',
                'backup_date'   => carbon::now()->format('Y-m-d'),
                'search_data'   => json_encode($request->all()),
                'backup_by'     => auth()->user()->id
            ]);
            return $response;
        }else{
            return sendErrorResponse('Offers data not downloaded!', 422);
        }
    }
}
