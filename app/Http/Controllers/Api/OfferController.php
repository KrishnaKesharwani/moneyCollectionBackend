<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Repositories\OfferRepository;
use App\Repositories\ReportBackupRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
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
                return sendErrorResponse('offers not found!', 200);
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
                return sendErrorResponse('Offer not created!', 500);
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
                return sendErrorResponse('Offer not updated!', 500);
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

        // Define a unique file name
        $fileName = 'offers_' . time() . '.xlsx';
        $filePath = 'exports/' . $fileName;

        // Save the spreadsheet to storage
        $writer = new Xlsx($spreadsheet);
        ob_start(); // Start output buffering
        $writer->save('php://output'); // Write the file content to the output buffer
        $fileContent = ob_get_clean(); // Get the content and clear the buffer

        Storage::put($filePath, $fileContent); // Save the content to storage
        //return sendSuccessResponse('Customers downloaded successfully!',200,$response);
        $this->reportBackupRepository->create([
            'company_id'    => $companyId,
            'backup_type'   => 'offer_list',
            'backup_date'   => carbon::now()->format('Y-m-d'),
            'search_data'   => json_encode($request->all()),
            'backup_by'     => auth()->user()->id
        ]);

        // Generate a signed URL for secure download (optional)
        $downloadUrl = Storage::url($filePath);
        //add domain to download url
        $fullUrl = downloadFileUrl($fileName);

        // Return success response with download URL
        return sendSuccessResponse('Offers data is ready for download.',200, ['download_url' => $fullUrl]);

    }
}
