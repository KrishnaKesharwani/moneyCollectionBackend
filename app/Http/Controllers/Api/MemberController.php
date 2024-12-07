<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\User;
use App\Models\Member;
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
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
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

    public function __construct(
        CompanyRepository $companyRepository,
        MemberRepository $memberRepository,
        UserRepository $userRepository,
        ReportBackupRepository $reportBackupRepository,
        DepositHistoryRepository $depositHistoryRepository,
        LoanHistoryRepository $loanHistoryRepository,
        CustomerLoanRepository $customerLoanRepository,
        CustomerDepositRepository $customerDepositRepository,
        MemberFinanceRepository $memberFinanceRepository
        )
    {
        $this->companyRepository            = $companyRepository;
        $this->memberRepository             = $memberRepository;
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
            $members = $this->memberRepository->getAllMembers($request->company_id,$status);

            if($members->isEmpty())
            {
                return sendErrorResponse('Members not found!', 404);
            }
            else
            {
                return sendSuccessResponse('Members found successfully!', 200, $members);
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
            'name'  => 'required',
            'mobile' => 'required|digits_between:10,15',
            'email'  => 'required|email',
            'join_date' => 'required',
            'aadhar_no' => 'nullable|string',
            'image' => 'nullable|string',
            'status' => 'required|string',
            'address' => 'nullable|string|max:500',
            'member_login_id' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            $companyId = $request->company_id;
            $company = $this->companyRepository->find($companyId);
            DB::beginTransaction();
            $member_no = 0;
            if(!$company)
            {
                return sendErrorResponse('Company not found!', 404);
            }
            else
            {
                $companyName            = $company->company_name;
                $companyprefix          = explode(' ', $companyName)[0];
                if($company->prefix!=null && $company->prefix!=''){
                    $companyprefix = $company->prefix;
                }
                $member_no              = $companyprefix.'-MEM-'.$company->id.'-'.$company->member_count + 1;
                $company->member_count  = $company->member_count + 1;
                $company->save();
            }

            $checkUser = User::where('email', $request->member_login_id)
                ->first();

            $checkMobile = DB::table('users')->where('users.mobile', $request->mobile)
                        ->join('members', function ($join) use($companyId) { 
                        $join->on('members.user_id', '=', 'users.id')
                                ->where('members.company_id', '=', $companyId);
                    })
                    ->select('users.id','users.name','users.email','users.mobile','users.status')
                    ->first();
            if ($checkUser)
            {
                if ($checkUser->email === $request->member_login_id) 
                {
                    return sendErrorResponse('Email already exists!', 409);
                }
            }

            if ($checkMobile)
            {
                return sendErrorResponse('Mobile already exists!', 409, null, $checkMobile);
            }


            //create user for member
            $user = $this->userRepository->create([
                'name' => $request->name,
                'user_type' => 2,  // 1 for company
                'email' => $request->member_login_id,  // Unique identifier for user
                'mobile' => $request->mobile,
                'password' => Hash::make($request->password),  // Hash the password
                'password_hint' => $request->password,
                'status' => $request->status,
            ]);

            // Process the base64 images
            $validatedData['image']     = $this->storeBase64Image($request->image, 'member');
            $validatedData['user_id']   = $user->id;
            $validatedData['member_no'] = $member_no;
            $cleanTimeString            = preg_replace('/\s*\(.*\)$/', '', $request->join_date);
            $validatedData['join_date'] = Carbon::parse($cleanTimeString)->format('Y-m-d');

            // Store the company data in the database
            $member = $this->memberRepository->create($validatedData);
            DB::commit();

            // Check if the company was successfully created
            if ($member)
            {   
                $memberData = $this->memberRepository->getById($member->id);
                return sendSuccessResponse('Member created successfully!', 201, $memberData);
            }
            else
            {
                return sendErrorResponse('Member not created!', 404);
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
            'company_id' => 'required',
            'member_id' => 'required',
            'name'  => 'required',
            'mobile' => 'required|digits_between:10,15',
            'email'  => 'required|email',
            'join_date' => 'required',
            'aadhar_no' => 'nullable|string',
            'image' => 'nullable|string',
            'status' => 'required|string',
            'address' => 'nullable|string|max:500',
        ]);
        
        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            $company        = $this->companyRepository->find($request->company_id);
            if(!$company)
            {
                return sendErrorResponse('Company not found!', 404);
            }

            $member         = $this->memberRepository->checkMemberExist($request->company_id, $request->member_id);
            if(!$member)
            {
                return sendErrorResponse('Member not found!', 404);
            }

            $memberUserId   = $member->user_id;

            $checkUserMobile = User::where('id', '!=', $memberUserId)->where('mobile', $request->mobile)->count();
            
            if($checkUserMobile>0){
                return sendErrorResponse('Mobile already exists!', 409);
            }

            // Process the base64 images
            if($request->image!='' && $request->image!='null') {
                $validatedData['image']     = $this->storeBase64Image($request->image, 'member');
            }

            $cleanTimeString            = preg_replace('/\s*\(.*\)$/', '', $request->join_date);
            $validatedData['join_date'] = Carbon::parse($cleanTimeString)->format('Y-m-d');

            DB::beginTransaction();
            // Update the company data in the database
            $member = $this->memberRepository->update($request->member_id,$validatedData);

            // Check if the company was successfully created
            if ($member)
            {   
                // update mobile number in user table
                User::where('id', $memberUserId)->update(['mobile' => $request->mobile,'status'=>$request->status]);
                DB::commit();
                $memberData = $this->memberRepository->getById($member->id);
                return sendSuccessResponse('Member Updated successfully!', 201, $memberData);
            }
            else
            {
                return sendErrorResponse('Member not created!', 404);
            }
        }
        catch (Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
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

    public function updateMemberStatus(Request $request){

        $validator = Validator::make($request->all(), [
            'member_id' => 'required',
            'status' => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $member = Member::find($request->member_id);
        if($member)
        {
            DB::beginTransaction();
            
            $member->status = $request->status;
            $member->save();

            //update user status
            $userId = $member->user_id;
            $this->userRepository->update($userId,['status'=>$request->status]);

            DB::commit();
            $membarData = $this->memberRepository->getById($member->id);
            if($request->status=='active')
            {
                return sendSuccessResponse('Member Activated successfully!',200,$membarData);
            }else{
                return sendSuccessResponse('Member Inactived successfully!',200,$membarData);
            }
        }
        else
        {
            return sendErrorResponse('Member not found!', 404);
        }
    }

    public function downloadMembers(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|exists:companies,id',
            'status' => 'required',
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $status = $request->status === 'all' ? null : $request->status;
        $companyId = $request->company_id;

        // Create new Spreadsheet object
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set the header
        $headers = ['A1' => 'Serial No', 'B1' => 'Member Number', 'C1' => 'Name', 'D1' => 'Email',
                'E1' => 'Mobile', 'F1' => 'Address', 'G1' => 'Join Date', 'H1' => 'Balance',
                'I1' => 'Status'];
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Retrieve data from the database
        $customers = $this->memberRepository->getAllMembers($companyId, $status);

        // Populate the spreadsheet with data
        $row = 2; // Start from row 2 to avoid overwriting headers
        foreach ($customers as $customer) {
            $sheet->setCellValue('A' . $row, $row - 1);
            $sheet->setCellValue('B' . $row, $customer->member_no);
            $sheet->setCellValue('C' . $row, $customer->name);
            $sheet->setCellValue('D' . $row, $customer->email);
            $sheet->setCellValue('E' . $row, $customer->mobile);
            $sheet->setCellValue('F' . $row, $customer->address);
            $sheet->setCellValue('G' . $row, $customer->join_date);
            $sheet->setCellValue('H' . $row, $customer->balance);
            $sheet->setCellValue('I' . $row, $customer->status);
            $row++;
        }

        // Define a unique file name
        $fileName = 'members_' . time() . '.xlsx';
        $filePath = 'exports/' . $fileName;

        // Save the spreadsheet to storage
        $writer = new Xlsx($spreadsheet);
        ob_start(); // Start output buffering
        $writer->save('php://output'); // Write the file content to the output buffer
        $fileContent = ob_get_clean(); // Get the content and clear the buffer

        Storage::put($filePath, $fileContent); // Save the content to storage

        // Create backup record
        $this->reportBackupRepository->create([
            'company_id'  => $companyId,
            'backup_type' => 'member_list',
            'backup_date' => Carbon::now()->format('Y-m-d'),
            'search_data' => json_encode($request->all()),
            'backup_by'   => auth()->user()->id
        ]);

        // Generate a signed URL for secure download (optional)
        $downloadUrl = Storage::url($filePath);
        //add domain to download url
        $fullUrl = downloadFileUrl($fileName);

        // Return success response with download URL
        return sendSuccessResponse('Members data is ready for download.',200, ['download_url' => $fullUrl]);

    }

    public function memberDashboard(){
        $userId = auth()->user()->id;
        //$userId = 5;
        $member = $this->memberRepository->getMemberByUserId($userId);
        if(empty($member))
        {
            return sendErrorResponse('Member not found!', 404);
        }
        else{
            $memberId               = $member->id;
            $companyId              = $member->company_id;
            $today                  = Carbon::now()->format('Y-m-d');
            //first date of current month
            $firstDate              = Carbon::now()->startOfMonth()->format('Y-m-d');
            //last date of current month
            $lastDate               = Carbon::now()->endOfMonth()->format('Y-m-d');

            //get today total collection
            $todayLoanAmount        = $this->loanHistoryRepository->getLoanReceivedAmountByDate($companyId,$memberId,$today);
            $todayDepositAmount     = $this->depositHistoryRepository->getDepositReceivedAmountByDate($companyId,$memberId,$today);
            $todayCollection        = $todayLoanAmount+$todayDepositAmount;

            //get monthly total collection
            $monthlyLoanAmount      = $this->loanHistoryRepository->getLoanReceivedAmountByDatewise($companyId,$memberId,$firstDate,$lastDate);
            $monthlyDepositAmount   = $this->depositHistoryRepository->getDepositReceivedAmountByDatewise($companyId,$memberId,$firstDate,$lastDate);
            $monthlyCollection      = $monthlyLoanAmount+$monthlyDepositAmount;

            //get total assigne customer count

            $totalLoanCustomersId       = $this->customerLoanRepository->getAssignedCustomersId($companyId,'paid',$memberId)->toArray();
            $totalDepositCustomersId    = $this->customerDepositRepository->getTotalDepositCustomersId($memberId,'active')->toArray();
            if(count($totalLoanCustomersId)>0 && count($totalDepositCustomersId)>0)
            {
                $totalCustomers = count(array_unique(array_merge($totalLoanCustomersId,$totalDepositCustomersId)));
            }
            elseif(count($totalLoanCustomersId)>0)
            {
                $totalCustomers = count($totalLoanCustomersId);
            }
            elseif(count($totalDepositCustomersId)>0)
            {
                $totalCustomers = count($totalDepositCustomersId);
            }
            else
            {
                $totalCustomers = 0;
            }

            //today advance money of member
            $advanceMoney = $this->memberFinanceRepository->getAdvanceMoney($memberId, $companyId,$today);
            $responseData = 
            [
                'today_collection' => $todayCollection,
                'monthly_collection' => $monthlyCollection,
                'assigned_customers' => $totalCustomers,
                'advance_money' => $advanceMoney,
                'member_balance' => (float)$member->balance
            ];
            
            return sendSuccessResponse('Member dashboard data.',200, $responseData);
        }
    }
}
