<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\User;
use App\Models\Customer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Repositories\CustomerRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\UserRepository;
use App\Repositories\ReportBackupRepository;
use App\Repositories\CustomerLoanRepository;
use App\Repositories\CustomerDepositRepository;
use App\Repositories\FixedDepositRepository;
use Carbon\Carbon;
use exception;
use Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;


class CustomerController extends Controller
{


    protected $customerRepository;
    protected $companyRepository;
    protected $userRepository;
    protected $reportBackupRepository;
    protected $customerLoanRepository;
    protected $customerDepositRepository;
    protected $fixedDepositRepository;

    public function __construct(
        CompanyRepository $companyRepository,
        CustomerRepository $customerRepository,
        UserRepository $userRepository,
        ReportBackupRepository $reportBackupRepository,
        CustomerLoanRepository $customerLoanRepository,
        CustomerDepositRepository $customerDepositRepository,
        FixedDepositRepository $fixedDepositRepository
        )
    {
        $this->companyRepository        = $companyRepository;
        $this->customerRepository       = $customerRepository;
        $this->userRepository           = $userRepository;
        $this->reportBackupRepository   = $reportBackupRepository;
        $this->customerLoanRepository   = $customerLoanRepository;
        $this->customerDepositRepository = $customerDepositRepository;
        $this->fixedDepositRepository   = $fixedDepositRepository;
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
            $companyId = $request->company_id;
            $customers = $this->customerRepository->getAllCustomers($companyId,$status);

            if($customers->isEmpty())
            {
                return sendErrorResponse('Customers not found!', 404);
            }
            // Collect customer IDs for bulk query
            $customerIds = $customers->pluck('id')->toArray();

            // Fetch loan and deposit counts in bulk
            $loanCounts = $this->customerLoanRepository->getCustomerLoansCounts($customerIds, 'active');
            $depositCounts = $this->customerDepositRepository->getCustomerDpositsCounts($customerIds);
            $fixedDepositCounts = $this->fixedDepositRepository->getCustomerFixedDepositsCounts($customerIds, 'active');
            $loanAmount = $this->customerLoanRepository->getCustomerLoansAmount($companyId,$customerIds, 'paid');
            $loanPaidAmount = $this->customerLoanRepository->getCustomerLoansHistoryAmount($companyId,$customerIds, 'paid');
            // Map counts to customers
            foreach ($customers as $customer) {
                $customer->loan_count = $loanCounts[$customer->id]['total'] ?? 0;
                $customer->running_loan_count = $loanCounts[$customer->id]['paid'] ?? 0;
                $customer->loan_count_cancelled = $loanCounts[$customer->id]['cancelled'] ?? 0;
                $customer->loan_count_completed = $loanCounts[$customer->id]['completed'] ?? 0;
                $customer->deposit_count = $depositCounts[$customer->id]['active'] ?? 0;
                $customer->fixeddeposit_count = $fixedDepositCounts[$customer->id]['total'] ?? 0;
                $customer->loan_amount = $loanAmount[$customer->id]['total'] ?? 0;
                $customer->loan_paid_amount = $loanPaidAmount[$customer->id]['total'] ?? 0;
                if(isset($loanPaidAmount[$customer->id]['total']))
                {
                    $customer->pending_amount = $loanAmount[$customer->id]['total']-$loanPaidAmount[$customer->id]['total'];
                }
                else{
                    $customer->pending_amount = $loanAmount[$customer->id]['total'] ?? 0;
                }
            }

            return sendSuccessResponse('Customers fetched successfully.', 200, $customers);
        
        }
        catch (\Exception $e) {
           return sendErrorResponse($e->getMessage().' on line '.$e->getLine(), 500);
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
            'customer_login_id' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            DB::beginTransaction();
            $companyId = $request->company_id;
            $company = $this->companyRepository->find($companyId);
            $customer_no = 0;
            if(!$company)
            {
                return sendErrorResponse('Company not found!', 404);
            }
            else
            {
                $companyName                = $company->company_name;
                $companyprefix              = explode(' ', $companyName)[0];
                if($company->prefix!=null && $company->prefix!='') 
                {
                    $companyprefix = $company->prefix;
                }
                
                $customer_no                = $companyprefix.'-'.$company->id.'-'.$company->customer_count + 1;
            }

            $checkUser = User::where('email', $request->customer_login_id)->first();
            $checkMobile = DB::table('users')->where('users.mobile', $request->mobile)
                         ->join('customers', function ($join) use($companyId) { 
                            $join->on('customers.user_id', '=', 'users.id')
                                 ->where('customers.company_id', '=', $companyId);
                        })
                        ->select('users.id','users.name','users.email','users.mobile','users.status')
                        ->first();

            if ($checkUser)
            {
                if ($checkUser->email === $request->customer_login_id) 
                {
                    return sendErrorResponse('Email already exists!', 409);
                }
            }

            if ($checkMobile)
            {
                return sendErrorResponse('Mobile already exists!', 409, null, $checkMobile);
            }

            // Process the base64 images
            $validatedData['image']         = storeBase64Image($request->image, 'customer');
            $validatedData['adhar_front']   = storeBase64Image($request->adhar_front, 'customer');
            $validatedData['adhar_back']    = storeBase64Image($request->adhar_back, 'customer');
            $validatedData['customer_no']   = $customer_no;
            $validatedData['created_by']    = Auth::user()->id;
            $cleanTimeString                = preg_replace('/\s*\(.*\)$/', '', $request->join_date);
            $validatedData['join_date']     = Carbon::parse($cleanTimeString)->format('Y-m-d');

            $user = $this->createUser($request);
            if($user){
                $validatedData['user_id'] = $user->id;
            }
            // Store the company data in the database
            $customer = $this->customerRepository->create($validatedData);

            // Check if the company was successfully created
            if ($customer)
            { 
                //update customer count 
                $company->customer_count    = $company->customer_count + 1;
                $company->save();

                DB::commit();

                $customerData = $this->customerRepository->getById($customer->id);
                return sendSuccessResponse('Customer created successfully!', 201, $customerData);
            }
            else
            {
                // delete user if customer not created
                User::where('email', $request->customer_login_id)->delete();
                return sendErrorResponse('Customer not created!', 404);
            }
        }
        catch (Exception $e) {
            return sendErrorResponse($e->getMessage().'at line number '.$e->getLine(), 500);
        }
    }

    public function update(Request $request)
    {
        // Validate the request

        $validator = Validator::make($request->all(), [
            'company_id' => 'required',
            'customer_id' => 'required',
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
            DB::beginTransaction();
            $companyId      = $request->company_id;
            $company        = $this->companyRepository->find($request->company_id);
            if(!$company)
            {
                return sendErrorResponse('Company not found!', 404);
            }

            $customer         = $this->customerRepository->checkCustomerExist($request->company_id, $request->customer_id);
            if(!$customer)
            {
                return sendErrorResponse('Customer not found!', 404);
            }

            $customerUserId   = $customer->user_id;

            $checkUserMobile = DB::table('users')->where('users.id', '!=', $customerUserId)->where('users.mobile', $request->mobile)
            ->join('customers', function ($join) use($companyId) { 
               $join->on('customers.user_id', '=', 'users.id')
                    ->where('customers.company_id', '=', $companyId);
           })
           ->count();

            
            if($checkUserMobile>0){
                return sendErrorResponse('Mobile already exists!', 409);
            }

            // Process the base64 images
            if($request->image!=''){
                $validatedData['image']     = storeBase64Image($request->image, 'customer');
            }

            if($request->adhar_front!=''){
                $validatedData['adhar_front']   = storeBase64Image($request->adhar_front, 'customer');
            }

            if($request->adhar_back!=''){
                $validatedData['adhar_back']    = storeBase64Image($request->adhar_back, 'customer');
            }

            $cleanTimeString            = preg_replace('/\s*\(.*\)$/', '', $request->join_date);
            $validatedData['join_date'] = Carbon::parse($cleanTimeString)->format('Y-m-d');

            // update the customer data in the database
            $customer = $this->customerRepository->update($request->customer_id,$validatedData);

            // Check if the company was successfully created
            if ($customer)
            {   
                // update mobile number in user table
                User::where('id', $customerUserId)->update(['mobile' => $request->mobile,'status'=>$request->status]);
                DB::commit();
                $customerData = $this->customerRepository->getById($customer->id);
                return sendSuccessResponse('Customer Updated successfully!', 201, $customerData);
            }
            else
            {
                return sendErrorResponse('Customer not created!', 404);
            }
        }
        catch (Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    public function importCustomers(Request $request)
    {
        // Validate the request for an array of customers

        $validator = Validator::make($request->all(), [
            'company_id' => 'required|integer|exists:companies,id',
            'customers' => 'required|array',
            'customers.*.name' => 'required|string',
            'customers.*.mobile' => 'required|digits_between:10,15',
            'customers.*.email' => 'required|email',
            'customers.*.join_date' => 'required',
            'customers.*.aadhar_no' => 'nullable|string',
            'customers.*.address' => 'nullable|string|max:500',
            'customers.*.customer_login_id' => 'required|email',
            'customers.*.password' => 'required|string|min:6',
            'customers.*.status' => 'required|string',
        ]);
    

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $customersData = $request->customers;

        // Extract customer_login_id and mobile from the post data to check for duplicates
        $loginIds = collect($customersData)->pluck('customer_login_id');
        $mobiles = collect($customersData)->pluck('mobile');

        // Check for duplicates in the post data
        if ($loginIds->duplicates()->isNotEmpty() || $mobiles->duplicates()->isNotEmpty()) {
            return sendErrorResponse('Duplicate customer_login_id or mobile found in the request data.', 409);
        }

        // Check for duplicates in the database
        $existingUsersEmails = User::whereIn('email', $loginIds)->get();
        $existingUsersMobiles = User::WhereIn('mobile', $mobiles)->get();

        if ($existingUsersEmails->isNotEmpty() || $existingUsersMobiles->isNotEmpty()) {

            $errors = [];
            if(!$existingUsersEmails->isEmpty()){
                $errors['emails'] = $existingUsersEmails->pluck('email')->toArray();
            }

            if(!$existingUsersMobiles->isEmpty()){
                $errors['mobiles'] = $existingUsersMobiles->pluck('mobile')->toArray();
            }

            return sendErrorResponse('Duplicate customer_login_id or mobile found in the existing records.', 409, $errors);
        }

        DB::beginTransaction();
        try {

            $company = $this->companyRepository->find($request->company_id);
            if (!$company) 
            {
                return sendErrorResponse('Company not found!', 404);
            }

            foreach ($customersData as $data) {
                // Find the company
                
                // Generate customer number
                $companyName            = $company->company_name;
                $companyPrefix          = explode(' ', $companyName)[0];
                $customer_no            = $companyPrefix . '-cus-' . $company->id . '-' . ($company->customer_count + 1);

                // Process images and other data
                //$data['image'] = storeBase64Image($data['image'], 'customer');
                $data['customer_no']    = $customer_no;
                $data['company_id']     = $request->company_id;
                $data['created_by']     = Auth::user()->id;
                $cleanTimeString        = preg_replace('/\s*\(.*\)$/', '', $data['join_date']);
                $data['join_date']      = Carbon::parse($cleanTimeString)->format('Y-m-d');

                // Create user and customer records
                $user = $this->createUser(new Request($data));
                if ($user) {
                    $data['user_id'] = $user->id;
                }
                
                $customer = $this->customerRepository->create($data);

                // Increment the customer count
                $company->customer_count += 1;
                $company->save();
            }

            DB::commit();
            return sendSuccessResponse('Customers created successfully!', 201);
        } catch (Exception $e) {
            DB::rollBack();
            return sendErrorResponse('Failed to insert customers: ' . $e->getMessage(), 500);
        }
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
            'user_type' => 3,  // 3 for customer
            'email' => $request->input('customer_login_id'),  // Unique identifier for user
            'password' => Hash::make($request->input('password')),  // Hash the password
            'password_hint' => $request->input('password'),
            'mobile' => $request->input('mobile'),
            'status' => $request->input('status')
        ]);        
    }

    public function updateCustomerStatus(Request $request){

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required',
            'status' => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $customer = Customer::find($request->customer_id);
        if($customer)
        {
            DB::beginTransaction();
            $customer->status = $request->status;
            $customer->save();

            //update user status 
            $userId = $customer->user_id;
            $this->userRepository->update($userId,['status'=>$request->status]);

            DB::commit();

            $customerData = $this->customerRepository->getById($customer->id);
            if($request->status=='active')
            {
                return sendSuccessResponse('Customer Activated successfully!',200,$customerData);
            }else{
                return sendSuccessResponse('Customer Inactived successfully!',200,$customerData);
            }
        }
        else
        {
            return sendErrorResponse('Customer not found!', 404);
        }
    }


    public function downloadCustomers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|exists:companies,id',
            'status' => 'required',
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

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
        $sheet->setCellValue('B1', 'Customer Number');
        $sheet->setCellValue('C1', 'Name');
        $sheet->setCellValue('D1', 'Email');
        $sheet->setCellValue('E1', 'Mobile');
        $sheet->setCellValue('F1', 'Address');
        $sheet->setCellValue('G1', 'Join Date');
        $sheet->setCellValue('H1', 'Status');


        // Retrieve your data from the database (example: getting users)
        $customers = $this->customerRepository->getAllCustomers($companyId, $status);

        // Populate the spreadsheet with data
        $row = 2; // Start from row 2 to avoid overwriting headers
        foreach ($customers as $customer) {
            $sheet->setCellValue('A' . $row, $row-1);
            $sheet->setCellValue('B' . $row, $customer->customer_no);
            $sheet->setCellValue('c' . $row, $customer->name);
            $sheet->setCellValue('d' . $row, $customer->email);
            $sheet->setCellValue('e' . $row, $customer->mobile);
            $sheet->setCellValue('f' . $row, $customer->address);
            $sheet->setCellValue('g' . $row, $customer->join_date);
            $sheet->setCellValue('h' . $row, $customer->status);
            $row++;
        }

        // Define a unique file name
        $fileName = 'customers_' . time() . '.xlsx';
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
            'backup_type'   => 'customer_list',
            'backup_date'   => carbon::now()->format('Y-m-d'),
            'search_data'   => json_encode($request->all()),
            'backup_by'     => auth()->user()->id
        ]);

        // Generate a signed URL for secure download (optional)
        $downloadUrl = Storage::url($filePath);
        //add domain to download url
        $fullUrl = downloadFileUrl($fileName);

        // Return success response with download URL
        return sendSuccessResponse('customers data is ready for download.',200, ['download_url' => $fullUrl]);
    }

}
