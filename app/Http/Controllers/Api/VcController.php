<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\User;
use App\Models\Member;
use App\Models\Vc;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Repositories\MemberRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\UserRepository;
use App\Repositories\ReportBackupRepository;
use App\Repositories\DepositHistoryRepository;
use App\Repositories\CustomerLoanRepository;
use App\Repositories\CustomerDepositRepository;
use App\Repositories\LoanHistoryRepository;
use App\Repositories\MemberFinanceRepository;
use App\Repositories\VcRepository;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VcController extends Controller
{
    protected $memberRepository;
    protected $companyRepository;
    protected $userRepository;
    protected $reportBackupRepository;
    protected $depositHistoryRepository;
    protected $loanHistoryRepository;
    protected $customerLoanRepository;
    protected $customerDepositRepository;
    protected $memberFinanceRepository;
    protected $vcRepository;

    public function __construct(
        CompanyRepository $companyRepository,
        MemberRepository $memberRepository,
        UserRepository $userRepository,
        ReportBackupRepository $reportBackupRepository,
        DepositHistoryRepository $depositHistoryRepository,
        LoanHistoryRepository $loanHistoryRepository,
        CustomerLoanRepository $customerLoanRepository,
        CustomerDepositRepository $customerDepositRepository,
        MemberFinanceRepository $memberFinanceRepository,
        VcRepository $vcRepository
        )
    {
        $this->companyRepository            = $companyRepository;
        $this->memberRepository             = $memberRepository;
        $this->vcRepository                 = $vcRepository;
        $this->userRepository               = $userRepository;
        $this->reportBackupRepository       = $reportBackupRepository;
        $this->depositHistoryRepository     = $depositHistoryRepository;
        $this->loanHistoryRepository        = $loanHistoryRepository;
        $this->customerLoanRepository       = $customerLoanRepository;
        $this->customerDepositRepository    = $customerDepositRepository;
        $this->memberFinanceRepository      = $memberFinanceRepository;
    }

    public function index(Request $request){
        $validator = Validator::make($request->all(), [
            'company_id' => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            $status = $request->status ?? null;
            $vcs = $this->vcRepository->getAllvcs($request->company_id,$status);

            if($vcs->isEmpty())
            {
                return sendErrorResponse('Vc not found!', 200);
            }
            else
            {
                return sendSuccessResponse('Vc found successfully!', 200, $vcs);
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
            'company_id' => 'required',
            'vc_name'  => 'required',
            'type' => 'required',
            'total_month'  => 'required',
            'total_member' => 'required',
            'final_amount' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'details' => 'nullable|string',
            'instalment_amount' => 'nullable|string',
            'vc_image' => 'nullable|string',       
        ]);     

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }
        $validatedData = $request->all();        
        try {
            $companyId = $request->company_id;
            $company = $this->companyRepository->find($companyId);
            DB::beginTransaction(); 
            $cleanStartDate                             = preg_replace('/\s*\(.*\)$/', '', $request->start_date);
            $cleanEndDate                               = preg_replace('/\s*\(.*\)$/', '', $request->end_date);
            $validatedData['start_date']                = Carbon::parse($cleanStartDate)->format('Y-m-d');
            $validatedData['end_date']                  = Carbon::parse($cleanEndDate)->format('Y-m-d');
            $validatedData['vc_image']            = storeBase64Image($request->vc_image, 'vcs');
           

            $vc = $this->vcRepository->create($validatedData);
            DB::commit();

            // Check if the company was successfully created
            if ($vc)
            {   
                $vcData = $this->vcRepository->getById($vc->id);
                return sendSuccessResponse('VC created successfully!', 201, $vcData);
            }
            else
            {
                return sendErrorResponse('VC not created!', 500);
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
            'vc_id' => 'required',
            'company_id' => 'required',
            'vc_name'  => 'required',
            'type' => 'required',
            'total_month'  => 'required',
            'total_member' => 'required',
            'final_amount' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'details' => 'nullable|string',
            'instalment_amount' => 'nullable|string',
            'vc_image' => 'nullable|string',
        ]);
        
        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            $companyId      = $request->company_id;
            $company        = $this->companyRepository->find($request->company_id);
            if(!$company)
            {
                return sendErrorResponse('Company not found!', 404);
            }

            $vc         = $this->vcRepository->checkVcExist($request->company_id, $request->vc_id);
            if(!$vc)
            {
                return sendErrorResponse('Vc not found!', 404);
            }

            // Process the base64 images
            // if($request->vc_image!='' && $request->vc_image!='null') {
            //     $validatedData['vc_image']     = $this->storeBase64Image($request->vc_image, 'vc');
            // }       

            DB::beginTransaction();
            // Update the company data in the database
            $cleanStartDate                             = preg_replace('/\s*\(.*\)$/', '', $request->start_date);
            $cleanEndDate                               = preg_replace('/\s*\(.*\)$/', '', $request->end_date);
            $validatedData['start_date']                = Carbon::parse($cleanStartDate)->format('Y-m-d');
            $validatedData['end_date']                  = Carbon::parse($cleanEndDate)->format('Y-m-d');
            $vc = $this->vcRepository->update($request->vc_id,$validatedData);
             if(isset($request->vc_image) && $request->vc_image!=''){
                $validatedData['vc_image']   = storeBase64Image($request->vc_image, 'vcs');
            }else{
                unset($validatedData['vc_image']);
            }
            // $validatedData['vc_image'] = $this->storeBase64Image($request->vc_image, 'vcs');
            // Check if the company was successfully created
            if ($vc)
            {   
                // update mobile number in user table
          
                DB::commit();
              //  $vcData = $this->vcRepository->getById($vc->id);
                return sendSuccessResponse('Vc Updated successfully!', 201, $vc);
            }
            else
            {
                return sendErrorResponse('VC not created!', 500);
            }
        }
        catch (Exception $e) {
             \Log::error('VC Update Error: ' . $e->getMessage());
            return sendErrorResponse($e->getMessage(), 500);
            // return sendErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Decode and store base64 image.
     *
     * @param string|null $base64Image
     * @param string $directory
     * @return string|null
     */
    private function storeBase64Image($base64Image, $directory)
    {
        if (!$base64Image) {
            return null; // Return null if no image is provided
        }

        // Extract the mime type and the Base64 data
        $imageParts = explode(';base64,', $base64Image);

        // Get the image extension from the mime type
        $imageTypeAux = explode('image/', $imageParts[0]);
        $imageType = $imageTypeAux[1]; // e.g., 'jpeg', 'png', 'gif'

        // Decode the Base64 string into binary data
        $imageData = base64_decode($imageParts[1]);

        // Generate a unique file name for the image
        $fileName = Str::random(10) . '.' . $imageType;

        // Store the image in the public storage folder (or any custom directory)
        $path = Storage::put("public/{$directory}/{$fileName}", $imageData);
        
        // Return the stored path or URL to save in the database
        return $directory.'/'.$fileName;
    }


    /**
     * Create plan history after the company plan is created.
     *
     * @param \App\Models\Company $companyPlan
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\User
     */
    private function createUser(Request $request)
    {
        // Create the plan history based on the company plan
        return User::create([
            'name' => $request->input('name'),
            'user_type' => 2,  // 1 for company
            'email' => $request->input('member_login_id'),  // Unique identifier for user
            'password' => Hash::make($request->input('password')),  // Hash the password
            'password_hint' => $request->input('password'),
            'mobile' => $request->input('mobile'),
            'status' => $request->input('status',)
        ]);        
    }

    public function updateVcStatus(Request $request){

        $validator = Validator::make($request->all(), [
            'vc_id' => 'required',
            'status' => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $vc = Vc::find($request->vc_id);
        if($vc)
        {
            DB::beginTransaction();
            
            $vc->status = $request->status;
            $vc->save();

            //update user status
            $userId = $vc->user_id;
            $this->userRepository->update($userId,['status'=>$request->status]);

            DB::commit();
            $vcData = $this->vcRepository->getById($vc->id);
            if($request->status=='active')
            {
                return sendSuccessResponse('Vc Activated successfully!',200,$vcData);
            }else{
                return sendSuccessResponse('Vc Inactived successfully!',200,$vcData);
            }
        }
        else
        {
            return sendErrorResponse('Vc not found!', 404);
        }
    }

    

  
}
