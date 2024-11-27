<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Repositories\CustomerLoanRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\LoanStatusHistoryRepository;
use App\Repositories\LoanMemberHistoryRepository;
use App\Repositories\LoanDocumentRepository;
use App\Repositories\LoanHistoryRepository;
use App\Repositories\DepositHistoryRepository;
use App\Repositories\MemberRepository;
use App\Repositories\ReportBackupRepository;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use exception;
//excel library for download excel
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerLoanController extends Controller
{

    protected $customerRepository;
    protected $customerLoanRepository;
    protected $loanStatusHistoryRepository;
    protected $loanMemberHistoryRepository;
    protected $loanDocumentRepository;
    protected $loanHistoryRepository;
    protected $depositHistoryRepository;
    protected $memberRepository;
    protected $reportBackupRepository;

    public function __construct(
        CustomerRepository $customerRepository,
        CustomerLoanRepository $customerLoanRepository,
        LoanStatusHistoryRepository $loanStatusHistoryRepository,
        LoanMemberHistoryRepository $loanMemberHistoryRepository,
        LoanDocumentRepository $loanDocumentRepository,
        LoanHistoryRepository $loanHistoryRepository,
        DepositHistoryRepository $depositHistoryRepository,
        MemberRepository $memberRepository,
        ReportBackupRepository $reportBackupRepository
        )
    {
        $this->customerRepository                   = $customerRepository;
        $this->customerLoanRepository               = $customerLoanRepository;
        $this->loanStatusHistoryRepository          = $loanStatusHistoryRepository;
        $this->loanMemberHistoryRepository          = $loanMemberHistoryRepository;
        $this->loanDocumentRepository               = $loanDocumentRepository;
        $this->loanHistoryRepository                = $loanHistoryRepository;
        $this->depositHistoryRepository             = $depositHistoryRepository;
        $this->memberRepository                     = $memberRepository;
        $this->reportBackupRepository               = $reportBackupRepository;
    }

    public function index(Request $request){

        $inputData = [
            'company_id' => 'required|exists:companies,id',
        ];

    

        $validator = Validator::make($request->all(), $inputData);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            $status = $request->status ?? 'active';
            $loanStatus = $request->loan_status ?? null;
            $member = $request->member_id ?? null;
            $customer = $request->customer_id ?? null;
            $loans = $this->customerLoanRepository->getAllCustomerLoans($request->company_id,$loanStatus,$status,$member,$customer);
            if($loans->isEmpty())
            {
                return sendErrorResponse('Loans not found!', 404);
            }
            else
            {
                $totalRemaingAmount = 0;
                $totalCustomer = [];

                foreach($loans as $loan)
                {
                    $paidAmount = $this->loanHistoryRepository->getTotalPaidAmount($loan->id);
                    $loan->applied_user_name = '';
                    if($loan->apply_date!=null){
                        if($loan->applied_user_type==3){
                            $loan->applied_user_name = 'self';
                        }
                        if($loan->applied_user_type==2){
                            $member = $this->memberRepository->getMemberByUserId($loan->applied_by);
                            if($member)
                            {
                                $loan->applied_user_name = $member->name;
                            }
                        }
                    }
                    $loan->total_paid = $paidAmount;
                    $remaingAmount = $loan->loan_amount - $paidAmount;
                    $loan->remaining_amount = $remaingAmount;
                    //if($loan->loan_status == 'paid'){
                        $totalRemaingAmount = $totalRemaingAmount + $remaingAmount;
                        $totalCustomer[] = $loan->customer_id;
                    //}

                    $loan->paid_today = 'no';
                    $loanMaxDate = $this->loanHistoryRepository->getMaxLoanHistoryDate($loan->id);
                    if($loanMaxDate)
                    {
                        //convert loan max date to carbon Y-m-d format
                        $loanMaxDate = Carbon::parse($loanMaxDate)->format('Y-m-d');
                        if($loanMaxDate == Carbon::now()->format('Y-m-d'))
                        {
                            $loan->paid_today = 'yes';
                        }
                    }
                }

                $totalCustomerCount = 0;
                if(!empty($totalCustomer)){
                    $totalCustomer = array_unique($totalCustomer);
                    $totalCustomerCount = count($totalCustomer);
                }
                $loanData = [
                    'loans' => $loans,
                    'total_remaining_amount' => $totalRemaingAmount,
                    'total_cusotomer' => $totalCustomerCount 
                ];
                return sendSuccessResponse('Loans found successfully!', 200, $loanData);
            }
        }
        catch (\Exception $e) {
            return sendErrorResponse($e->getMessage().' on line '.$e->getLine(), 500);
        }
    }

    public function unassignedLoans(Request $request){

        $inputData = [
            'company_id' => 'required|exists:companies,id',
        ];

        $validator = Validator::make($request->all(), $inputData);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            $loanStatus = $request->loan_status ?? ['paid','approved'];
            $loans = $this->customerLoanRepository->getAllmemberNotAssignedLoans($request->company_id,$loanStatus);
            if($loans->isEmpty())
            {
                return sendErrorResponse('Loans not found!', 404);
            }
            else
            {
                $loanData = [
                    'loans' => $loans,
                ];
                return sendSuccessResponse('Loans found successfully!', 200, $loanData);
            }
        }
        catch (\Exception $e) {
            return sendErrorResponse($e->getMessage().' on line '.$e->getLine(), 500);
        }
    }

    public function store(Request $request)
    {
        // Validate the request

        $validator = Validator::make($request->all(), [
            'company_id' => 'required|integer|exists:companies,id',
            'customer_id '  => 'required1|integer|exists:customers,id',
            'loan_amount' => 'required|numeric',
            'installment_amount'  => 'required|numeric',
            'start_date' => 'required',
            'end_date' => 'required',
            'no_of_days' => 'required|integer',
            'loan_status' => 'required|string',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            $customer = $this->customerRepository->getById($request->customer_id);
            if(empty($customer))
            {
                return sendErrorResponse('Customer not found!', 404);
            }
            $loanCount = $customer->loan_count;
            DB::beginTransaction();

            $cleanStartDate                             = preg_replace('/\s*\(.*\)$/', '', $request->start_date);
            $cleanEndDate                               = preg_replace('/\s*\(.*\)$/', '', $request->end_date);
            $validatedData['loan_no']                   = 'Loan-'.$customer->id.'-'.$loanCount+1;
            $validatedData['start_date']                = Carbon::parse($cleanStartDate)->format('Y-m-d');
            $validatedData['end_date']                  = Carbon::parse($cleanEndDate)->format('Y-m-d');
            $validatedData['created_by']                = auth()->user()->id;
            $validatedData['loan_status_changed_by']    = auth()->user()->id;
            $validatedData['loan_status_change_date']   = Carbon::now()->format('Y-m-d H:i:s');
            $validatedData['status']                    = $request->status ?? 'active';
            $validatedData['assigned_member_id']        = $request->assigned_member_id ?? 0;

            // Store the company data in the database
            $customerLoan = $this->customerLoanRepository->create($validatedData);

            // Check if the company was successfully created
            if ($customerLoan)
            {   
                //\Log::info($request->document);
                if($request->document){
                    $loanDocument = json_decode($request->document);
                    if(count($loanDocument)>0){
                        foreach ($loanDocument as $document) {
                            if($document!=''){
                                $savedDocURL = $this->storeBase64Image($document, 'loandocument');
                                $documentData = [
                                    'loan_id' => $customerLoan->id,
                                    'document_url' => $savedDocURL
                                ];
                                $documentHistory = $this->loanDocumentRepository->create($documentData);
                            }
                        }
                    }
                }
                $statusData = [
                    'loan_id' => $customerLoan->id,
                    'loan_status' => $request->loan_status,
                    'loan_status_message' => $request->loan_status_message ?? null,
                    'loan_status_changed_by' => auth()->user()->id,
                    'loan_status_change_date' => Carbon::now()->format('Y-m-d H:i:s')
                ];

                $statusHistory = $this->loanStatusHistoryRepository->create($statusData);

                if($request->assigned_member_id){
                    $memberData = [
                        'loan_id' => $customerLoan->id,
                        'member_id' => $request->assigned_member_id,
                        'assigned_date' => Carbon::now()->format('Y-m-d H:i:s'),
                        'assigned_by' => auth()->user()->id,
                    ];

                    $memberHistory = $this->loanMemberHistoryRepository->create($memberData);
                }

                //update the customer loan count
                $customer->loan_count = $customer->loan_count+1;
                $customer->save();

                DB::commit();

                $loanData = $this->customerLoanRepository->getLoanById($customerLoan->id);
                return sendSuccessResponse('Loan provided successfully!', 201, $loanData);
            }
            else
            {
                return sendErrorResponse('Loan not provided!', 404);
            }
        }
        catch (Exception $e) {
            return sendErrorResponse($e->getMessage().' on line '.$e->getLine(), 500);
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

    public function loanRequest(Request $request)
    {
        // Validate the request

        $validator = Validator::make($request->all(), [
            'company_id' => 'required|integer|exists:companies,id',
            'customer_id '  => 'required1|integer|exists:customers,id',
            'loan_amount' => 'required|numeric',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            DB::beginTransaction();
            $validatedData['details']                   = $request->details ?? null;    
            $validatedData['applied_by']                = auth()->user()->id;
            $validatedData['applied_user_type']         = auth()->user()->user_type;
            $validatedData['apply_date']                = Carbon::now()->format('Y-m-d');
            $validatedData['status']                    = 'active';
            $validatedData['loan_status']               = 'pending';    

            // Store the company data in the database
            $customerLoan = $this->customerLoanRepository->create($validatedData);

            // Check if the company was successfully created
            if ($customerLoan)
            {   
                DB::commit();

                $loanData = $this->customerLoanRepository->getLoanById($customerLoan->id);
                return sendSuccessResponse('Applied successfully!', 201, $loanData);
            }
            else
            {
                return sendErrorResponse('Something is wrong!', 404);
            }
        }
        catch (Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    public function updateappliedloan(Request $request)
    {
        // Validate the request

        $validator = Validator::make($request->all(), [
            'loan_id' => 'required|integer|exists:customer_loans,id',
            'installment_amount'  => 'required|numeric',
            'start_date' => 'required',
            'end_date' => 'required',
            'no_of_days' => 'required|integer',
            'loan_status' => 'required|string',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            $customerLoan   = $this->customerLoanRepository->find($request->loan_id);
            $customer       = $this->customerRepository->find($customerLoan->customer_id);
            $loanCount      = $customer->loan_count;
            DB::beginTransaction();

            $cleanStartDate                             = preg_replace('/\s*\(.*\)$/', '', $request->start_date);
            $cleanEndDate                               = preg_replace('/\s*\(.*\)$/', '', $request->end_date);
            $validatedData['loan_no']                   = 'Loan-'.$customer->id.'-'.$loanCount+1;
            $validatedData['start_date']                = Carbon::parse($cleanStartDate)->format('Y-m-d');
            $validatedData['end_date']                  = Carbon::parse($cleanEndDate)->format('Y-m-d');
            $validatedData['created_by']                = auth()->user()->id;
            $validatedData['loan_status_changed_by']    = auth()->user()->id;
            $validatedData['loan_status_change_date']   = Carbon::now()->format('Y-m-d H:i:s');
            $validatedData['status']                    = $request->status ?? 'active';
            $validatedData['assigned_member_id']        = $request->assigned_member_id ?? 0;

            // Store the company data in the database
            $customerLoan = $this->customerLoanRepository->update($request->loan_id,$validatedData);

            // Check if the company was successfully created
            if ($customerLoan)
            {   

                if($request->document){
                    $loanDocument = json_decode($request->document);
                    if(count($loanDocument)>0){
                        foreach ($loanDocument as $document) {
                            if($document!=''){
                                $savedDocURL = $this->storeBase64Image($document, 'loandocument');
                                $documentData = [
                                    'loan_id' => $customerLoan->id,
                                    'document_url' => $savedDocURL
                                ];
                                $documentHistory = $this->loanDocumentRepository->create($documentData);
                            }
                        }
                    }
                }

                $statusData = [
                    'loan_id' => $customerLoan->id,
                    'loan_status' => $request->loan_status,
                    'loan_status_message' => $request->loan_status_message ?? null,
                    'loan_status_changed_by' => auth()->user()->id,
                    'loan_status_change_date' => Carbon::now()->format('Y-m-d H:i:s')
                ];

                $statusHistory = $this->loanStatusHistoryRepository->create($statusData);

                if($request->assigned_member_id){
                    $memberData = [
                        'loan_id' => $customerLoan->id,
                        'member_id' => $request->assigned_member_id,
                        'assigned_date' => Carbon::now()->format('Y-m-d H:i:s'),
                        'assigned_by' => auth()->user()->id,
                    ];

                    $memberHistory = $this->loanMemberHistoryRepository->create($memberData);
                }

                //update the customer loan count
                $customer->loan_count = $customer->loan_count+1;
                $customer->save();

                DB::commit();

                $loanData = $this->customerLoanRepository->getLoanById($customerLoan->id);
                return sendSuccessResponse('Loan provided successfully!', 201, $loanData);
            }
            else
            {
                return sendErrorResponse('Loan not provided!', 404);
            }
        }
        catch (Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    public function loanHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|integer|exists:customers,id',
            'loan_id' => 'required|integer|exists:customer_loans,id',
        ]);
        
        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }


        try{

            $fromDay = $request->from_day ?? null;
            //get the previous date by the day count 
            if($fromDay){
                $fromDate = Carbon::now()->subDays($fromDay)->format('Y-m-d');
            }else{
                $fromDate = null;
            }
            $loan       = $this->customerLoanRepository->getLoanById($request->loan_id);

            $loanAmount = 0;
            if(!$loan)
            {
                return sendErrorResponse('Loan not found!', 404);
            }
            else{
                $loanAmount = $loan->loan_amount;
            }

            $collection = $this->customerLoanRepository->getLoanHistory($request->customer_id,$request->loan_id,$fromDate);
            if($collection->isEmpty())
            {
                return sendErrorResponse('Collection not found!', 404);
            }
            else
            {
                $remainingAmount = $loanAmount;  // Initialize the remaining amount with the total loan amount
                $i = 1;
                foreach ($collection as $key => $value) {
                    // Deduct the installment amount from the remaining amount
                    if($i == 1){
                        $collection[$key]->balance = (float)$loanAmount;
                    }else{
                        $remainingAmount -= $collection[$key - 1]->amount; 
                        // Set the remaining amount for each collection entry
                        $collection[$key]->balance = $remainingAmount;
                    }
                    $i++;
                }                
                // Sort the collection by 'created_at' in descending order using sortByDesc
                $sortedCollection = $collection->sortByDesc('created_at')->values();
                $responseData = 
                [
                    'collection' => $sortedCollection,
                ];
                return sendSuccessResponse('Collection found successfully!', 200, $responseData);
            }
        }
        catch (\Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }
    
    public function changeLoanMember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'loan_id' => 'required|integer|exists:customer_loans,id',
            'member_id' => 'required|integer|exists:members,id',
        ]);
        
        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }    

        try{            
            $customerLoan   = $this->customerLoanRepository->find($request->loan_id);
            if($customerLoan->assigned_member_id == $request->member_id)
            {
                // this loan member already asigned
                return sendErrorResponse('Loan member already assigned!', 409);
            }
            DB::beginTransaction();
            $customerLoan->assigned_member_id = $request->member_id;
            $customerLoan->member_changed_reason = $request->reason ?? null;
            if($customerLoan->save())
            {
                $memberData = [
                    'loan_id' => $customerLoan->id,
                    'member_id' => $request->member_id,
                    'member_changed_reason' => $request->reason ?? null,
                    'assigned_date' => Carbon::now()->format('Y-m-d H:i:s'),
                    'assigned_by' => auth()->user()->id,
                ];
                $memberHistory = $this->loanMemberHistoryRepository->create($memberData);
                DB::commit();
                return sendSuccessResponse('Loan member changed successfully!', 200);
            }
            else
            {   
                return sendErrorResponse('Loan member not changed!', 404);
            }
        }
        catch (Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    //remove member from loan
    public function removeLoanMember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'loan_id' => 'required|integer|exists:customer_loans,id',
            'member_id' => 'required|integer|exists:members,id',
        ]);
        
        if ($validator->fails())
        {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }


        try{            
            $customerLoan   = $this->customerLoanRepository->find($request->loan_id);
            if($customerLoan->assigned_member_id == $request->member_id)
            {
                DB::beginTransaction();
                $customerLoan->assigned_member_id = 0;
                if($customerLoan->save())
                {
                    //delete member from loan member history
                    $this->loanMemberHistoryRepository->deleteMember(['loan_id' => $request->loan_id, 'member_id' => $request->member_id]);
                    DB::commit();
                    return sendSuccessResponse('Loan member removed successfully!', 200);
                }
                else
                {   
                    return sendErrorResponse('Loan member not removed!', 404);
                }
            }
            else
            {
                return sendErrorResponse('This member not assigned to this loan!', 404);
            }
        }
        catch (Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }


    /**
     * Update loan status by given loan id
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateLoanStatus(Request $request){

        $validator = Validator::make($request->all(), [
            'loan_id' => 'required|integer|exists:customer_loans,id',
            'loan_status' => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            DB::beginTransaction();
            //update user status
            $loanId = $request->loan_id;
            $updateLoanData = [
                'loan_status' => $request->loan_status,
                'loan_status_message' => $request->reason ?? null,
                'loan_status_changed_by' => auth()->user()->id,
                'loan_status_change_date' => Carbon::now()->format('Y-m-d H:i:s'),
            ];
            $loan = $this->customerLoanRepository->update($loanId,$updateLoanData);

            if($loan)
            {
                $statusData = [
                    'loan_id' => $loanId,
                    'loan_status' => $request->loan_status,
                    'loan_status_message' => $request->reason ?? null,
                    'loan_status_changed_by' => auth()->user()->id,
                    'loan_status_change_date' => Carbon::now()->format('Y-m-d H:i:s')
                ];

                $statusHistory = $this->loanStatusHistoryRepository->create($statusData);

                DB::commit();
                $loanData = $this->customerLoanRepository->find($loanId);
                return sendSuccessResponse('Loan status updated successfully!',200,$loanData);
            }
            else
            {
                return sendErrorResponse('Loan status not updated',422);
            }
        }
        catch (Exception $e)
        {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    public function LoanListByStatus(Request $request){

        $inputData = [
            'company_id' => 'required|exists:companies,id',
        ];

    

        $validator = Validator::make($request->all(), $inputData);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            $status = $request->status ?? 'active';
            $loanStatus = $request->loan_status ?? 'completed';
            $member = $request->member_id ?? null;
            $customer = $request->customer_id ?? null;
            //set current month if month value is null
            $month = $request->month ?? carbon::now()->month;
            //set current year if year value is null
            $year = $request->year ?? carbon::now()->year;
            //get the date of the first day and last day according to this month and year
            $startDate = Carbon::createFromDate($year, $month, 1)->format('Y-m-d');
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d');
            $loans = $this->customerLoanRepository->getAllCustomerLoans($request->company_id,$loanStatus,$status,$member,$customer,$startDate,$endDate);
            if($loans->isEmpty())
            {
                return sendErrorResponse('Loans not found!', 404);
            }
            else
            {
                $totalRemaingAmount = 0;
                $totalCustomer = [];

                foreach($loans as $loan)
                {
                    $paidAmount = $this->loanHistoryRepository->getTotalPaidAmount($loan->id);
                    $loan->applied_user_name = '';
                    if($loan->apply_date!=null){
                        if($loan->applied_user_type==3){
                            $loan->applied_user_name = 'self';
                        }
                        if($loan->applied_user_type==2){
                            $member = $this->memberRepository->getMemberByUserId($loan->applied_by);
                            if($member)
                            {
                                $loan->applied_user_name = $member->name;
                            }
                        }
                    }
                    $loan->total_paid = $paidAmount;
                    $remaingAmount = $loan->loan_amount - $paidAmount;
                    $loan->remaining_amount = $remaingAmount;
                    //if($loan->loan_status == 'paid'){
                        $totalRemaingAmount = $totalRemaingAmount + $remaingAmount;
                        $totalCustomer[] = $loan->customer_id;
                    //}

                    $loan->paid_today = 'no';
                    $loanMaxDate = $this->loanHistoryRepository->getMaxLoanHistoryDate($loan->id);
                    if($loanMaxDate)
                    {
                        //convert loan max date to carbon Y-m-d format
                        $loanMaxDate = Carbon::parse($loanMaxDate)->format('Y-m-d');
                        if($loanMaxDate == Carbon::now()->format('Y-m-d'))
                        {
                            $loan->paid_today = 'yes';
                        }
                    }
                }

                $totalCustomerCount = 0;
                if(!empty($totalCustomer)){
                    $totalCustomer = array_unique($totalCustomer);
                    $totalCustomerCount = count($totalCustomer);
                }
                $loanData = [
                    'loans' => $loans,
                    'total_remaining_amount' => $totalRemaingAmount,
                    'total_cusotomer' => $totalCustomerCount 
                ];
                return sendSuccessResponse('Loans found successfully!', 200, $loanData);
            }
        }
        catch (\Exception $e) {
            return sendErrorResponse($e->getMessage().' on line '.$e->getLine(), 500);
        }
    }

    public function dashboardLoanStatus(Request $request){

        $inputData = [
            'company_id' => 'required|exists:companies,id',
        ];

        $validator = Validator::make($request->all(), $inputData);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            $memberId           = $request->member_id ?? null;
            $totalLoanAmount    = $this->customerLoanRepository->getTotalLoanAmount($request->company_id,$memberId);
            $loanIds            = $this->customerLoanRepository->getRunningLoanIds($request->company_id,$memberId);
            $totalCustomerCount = $this->customerLoanRepository->getTotalCustomer($request->company_id,'paid',$memberId);
            if(count($loanIds) > 0)
            {
                $totalPaidAmount = $this->loanHistoryRepository->getTotalPaidAmountByLoanIds($loanIds);
                $totalRemaingAmount = $totalLoanAmount - $totalPaidAmount;
                //get the percentage of paid amount
                $percentage = ($totalPaidAmount / $totalLoanAmount) * 100;
                //get the percentage of remaining amount
                $remainingPercentage = ($totalRemaingAmount / $totalLoanAmount) * 100;

                $loanData = [
                    'total_loan_amount' => (float)$totalLoanAmount,
                    'total_paid_amount' => (float)$totalPaidAmount,
                    'total_remaining_amount' => $totalRemaingAmount,
                    'total_cusotomer' => $totalCustomerCount,
                    'paid_percentage' => round($percentage,2),
                    'remaining_percentage' => round($remainingPercentage,2), 
                ];
                return sendSuccessResponse('Loans found successfully!', 200, $loanData);
            }
            else
            {
                return sendErrorResponse('Loans not found!', 404);
            }
        }
        catch (\Exception $e) {
            return sendErrorResponse($e->getMessage().' on line '.$e->getLine(), 500);
        }
    }

    public function calculateReceivedAmountForMemberLineGraph(Request $request){

        $inputData = [
            'company_id' => 'required|exists:companies,id',
            'member_id' => 'required|exists:members,id',
        ];

        $validator = Validator::make($request->all(), $inputData);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            //current date
            $currentDate = Carbon::now()->format('Y-m-d');
            $data = [];
            for($i = 0; $i < 10; $i++)
            {
                $searchDate = '';
                if($i == 0) $searchDate = $currentDate;
                else $searchDate        = Carbon::now()->subDays($i)->format('Y-m-d');
                $loanReceivedAmount     = $this->loanHistoryRepository->getLoanReceivedAmountByDate($request->company_id,$request->member_id,$searchDate);
                $depositReceivedAmount  = $this->depositHistoryRepository->getDepositReceivedAmountByDate($request->company_id,$request->member_id,$searchDate);
                $totalReceivedAmount    = $loanReceivedAmount + $depositReceivedAmount;
                //format search date in Y-m-d
                $searchDate = Carbon::parse($searchDate)->format('d M');
                $data[$i] = [
                    'date' => $searchDate,
                    'received_amount' => $totalReceivedAmount,
                ];
            }
            
            return sendSuccessResponse('Last 10 days received amount found successfully!', 200, $data);
        }
        catch (\Exception $e) {
            return sendErrorResponse($e->getMessage().' on line '.$e->getLine(), 500);
        }
    }

    public function customerDashboardLoanStatus(Request $request){

        $inputData = [
            'company_id' => 'required|exists:companies,id',
            'customer_id' => 'required|exists:customers,id',
        ];

        $validator = Validator::make($request->all(), $inputData);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            
        }
        catch (\Exception $e) {
            return sendErrorResponse($e->getMessage().' on line '.$e->getLine(), 500);
        }
    }

    public function customerLoanStatusGraph(Request $request){

        $inputData = [
            'company_id' => 'required|exists:companies,id',
            'customer_id' => 'required|exists:customers,id',
        ];

    

        $validator = Validator::make($request->all(), $inputData);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            $status = $request->status ?? 'active';
            $loanStatus = $request->loan_status ?? 'paid';
            $customer = $request->customer_id ?? null;
            $loans = $this->customerLoanRepository->getAllCustomerLoansStatus($request->company_id,$loanStatus,$status,$customer);
            if($loans->isEmpty())
            {
                return sendErrorResponse('Loans not found!', 404);
            }
            else
            {
                $totalRemaingAmount = 0;
                $totalCustomer = [];

                foreach($loans as $loan)
                {
                    $paidAmount = $this->loanHistoryRepository->getTotalPaidAmount($loan->id);
                    $loan->loan_amount = (float)$loan->loan_amount;
                    $loan->total_paid = (float)$paidAmount;
                    $remaingAmount = $loan->loan_amount - $paidAmount;
                    $loan->remaining_amount = (float)$remaingAmount;
                    $loan->paidPercentage = round(($paidAmount/$loan->loan_amount)*100,2);
                    $loan->remainingPercentage = round(($remaingAmount/$loan->loan_amount)*100,2);
                    $totalRemaingAmount = $totalRemaingAmount + $remaingAmount;
                    $totalCustomer[] = $loan->customer_id;

                }

                $totalCustomerCount = 0;
                if(!empty($totalCustomer)){
                    $totalCustomer = array_unique($totalCustomer);
                    $totalCustomerCount = count($totalCustomer);
                }
                $loanData = [
                    'loans' => $loans,
                    'total_remaining_amount' => $totalRemaingAmount,
                    'total_cusotomer' => $totalCustomerCount 
                ];
                return sendSuccessResponse('Loans found successfully!', 200, $loanData);
            }
        }
        catch (\Exception $e) {
            return sendErrorResponse($e->getMessage().' on line '.$e->getLine(), 500);
        }
    }

    public function downloadLoanList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|exists:companies,id',
            'status' => 'required',
            'loan_status' => 'required',
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

        $loanStatus = null;

        if ($request->loan_status == 'all') {
            $loanStatus = null;
        } else {
            $loanStatus = $request->loan_status;
        }

        $companyId  = $request->company_id ?? 1;
        

        // Create new Spreadsheet object
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set the header
        $sheet->setCellValue('A1', 'Serial No');
        $sheet->setCellValue('B1', 'Loan Number');
        $sheet->setCellValue('C1', 'Loan Amount');
        $sheet->setCellValue('D1', 'Installment Amount');
        $sheet->setCellValue('E1', 'No. of Days');
        $sheet->setCellValue('F1', 'Start Date');
        $sheet->setCellValue('G1', 'End Date');
        $sheet->setCellValue('H1', 'Details');
        $sheet->setCellValue('I1', 'Member Name');
        $sheet->setCellValue('J1', 'Customer Name');
        $sheet->setCellValue('K1', 'Applied By');
        $sheet->setCellValue('L1', 'Status');
        $sheet->setCellValue('M1', 'Loan Status');
        $sheet->setCellValue('N1', 'Paid Amount');
        $sheet->setCellValue('O1', 'Remaining Amount');


        // Retrieve your data from the database (example: getting users)
        $loans = $this->customerLoanRepository->getAllCustomerLoans($companyId,$loanStatus,$status);

        // Populate the spreadsheet with data
        $row = 2; // Start from row 2 to avoid overwriting headers
        foreach ($loans as $loan) {
            $memberName = isset($loan->member) && $loan->member!=null ? $loan->member->name : '';
            $customerName = isset($loan->customer) && $loan->customer!=null ? $loan->customer->name : '';
            $appliedBy  = '';
            if($loan->applied_user_type==3)
            {
                $appliedBy = 'self';
            }
            else if($loan->applied_user_type==2){
                $member = $this->memberRepository->getMemberByUserId($loan->applied_by);
                if($member)
                {
                    $appliedBy = $member->name;
                }
            }
            
            $paidAmount = $this->loanHistoryRepository->getTotalPaidAmount($loan->id);
            $paidAmount = (float)$paidAmount;
            $remaingAmount = (float)($loan->loan_amount - $paidAmount);

            $sheet->setCellValue('A' . $row, $row-1);
            $sheet->setCellValue('B' . $row, $loan->loan_no);
            $sheet->setCellValue('c' . $row, $loan->loan_amount);
            $sheet->setCellValue('d' . $row, $loan->installment_amount);
            $sheet->setCellValue('e' . $row, $loan->no_of_days);
            $sheet->setCellValue('f' . $row, $loan->start_date);
            $sheet->setCellValue('g' . $row, $loan->end_date);
            $sheet->setCellValue('h' . $row, $loan->details);
            $sheet->setCellValue('i' . $row, $memberName);
            $sheet->setCellValue('j' . $row, $customerName);
            $sheet->setCellValue('k' . $row, $appliedBy);
            $sheet->setCellValue('l' . $row, $loan->status);
            $sheet->setCellValue('m' . $row, $loan->loan_status);
            $sheet->setCellValue('n' . $row, $paidAmount);
            $sheet->setCellValue('o' . $row, $remaingAmount);
            $row++;
        }


        // Define a unique file name
        $fileName = 'Loans_' . time() . '.xlsx';
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
            'backup_type'   => 'loan_list',
            'backup_date'   => carbon::now()->format('Y-m-d'),
            'search_data'   => json_encode($request->all()),
            'backup_by'     => auth()->user()->id
        ]);

        // Generate a signed URL for secure download (optional)
        $downloadUrl = Storage::url($filePath);
        //add domain to download url
        $fullUrl = downloadFileUrl($fileName);

        // Return success response with download URL
        return sendSuccessResponse('Loan data is ready for download.',200, ['download_url' => $fullUrl]);
    }

    public function downloadLoanHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'loan_id' => 'required|exists:customer_loans,id',
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $loanId = $request->loan_id;
        

        // Create new Spreadsheet object
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set the header
        $sheet->setCellValue('A1', 'Serial No');
        $sheet->setCellValue('B1', 'Loan Number');
        $sheet->setCellValue('C1', 'Loan Amount');
        $sheet->setCellValue('D1', 'Installment Amount');
        $sheet->setCellValue('E1', 'No. of Days');
        $sheet->setCellValue('F1', 'Start Date');
        $sheet->setCellValue('G1', 'End Date');
        $sheet->setCellValue('H1', 'Details');
        $sheet->setCellValue('I1', 'Member Name');
        $sheet->setCellValue('J1', 'Customer Name');
        $sheet->setCellValue('K1', 'Applied By');
        $sheet->setCellValue('L1', 'Status');
        $sheet->setCellValue('M1', 'Loan Status');
        $sheet->setCellValue('N1', 'Paid Amount');
        $sheet->setCellValue('O1', 'Remaining Amount');


        // Retrieve your data from the database (example: getting users)
        $loan           = $this->customerLoanRepository->getLoanById($loanId);
        $loanHistory    = $loan?->loanHistory;

        // Populate the spreadsheet with data
        $row = 2; // Start from row 2 to avoid overwriting headers
    
        $memberName = isset($loan->member) && $loan->member!=null ? $loan->member->name : '';
        $customerName = isset($loan->customer) && $loan->customer!=null ? $loan->customer->name : '';
        $appliedBy  = '';
        if($loan->applied_user_type==3)
        {
            $appliedBy = 'self';
        }
        else if($loan->applied_user_type==2){
            $member = $this->memberRepository->getMemberByUserId($loan->applied_by);
            if($member)
            {
                $appliedBy = $member->name;
            }
        }
        
        $paidAmount = $this->loanHistoryRepository->getTotalPaidAmount($loan->id);
        $paidAmount = (float)$paidAmount;
        $remaingAmount = (float)($loan->loan_amount - $paidAmount);

        $sheet->setCellValue('A' . $row, $row-1);
        $sheet->setCellValue('B' . $row, $loan->loan_no);
        $sheet->setCellValue('c' . $row, $loan->loan_amount);
        $sheet->setCellValue('d' . $row, $loan->installment_amount);
        $sheet->setCellValue('e' . $row, $loan->no_of_days);
        $sheet->setCellValue('f' . $row, $loan->start_date);
        $sheet->setCellValue('g' . $row, $loan->end_date);
        $sheet->setCellValue('h' . $row, $loan->details);
        $sheet->setCellValue('i' . $row, $memberName);
        $sheet->setCellValue('j' . $row, $customerName);
        $sheet->setCellValue('k' . $row, $appliedBy);
        $sheet->setCellValue('l' . $row, $loan->status);
        $sheet->setCellValue('m' . $row, $loan->loan_status);
        $sheet->setCellValue('n' . $row, $paidAmount);
        $sheet->setCellValue('o' . $row, $remaingAmount);
        
        if(count($loanHistory)>0)
        {
            $sheet->setCellValue('A4', 'Loan History');
            
          
            $sheet->setCellValue('A5', 'Serial No');
            $sheet->setCellValue('B5', 'Amount');
            $sheet->setCellValue('C5', 'Paid Date');
            $sheet->setCellValue('D5', 'Member Name');
            
            $row = 6;
            foreach ($loanHistory as $key => $history) {
                $sheet->setCellValue('A' . $row, $key+1);
                $sheet->setCellValue('B' . $row, $history->amount);
                $sheet->setCellValue('C' . $row, carbon::parse($history->paid_date)->format('Y-m-d'));
                $sheet->setCellValue('D' . $row, isset($history->recieved_member) && $history->recieved_member!=null ? $history->recieved_member->name:'');
                $row++;
            }
        }
    

        // Define a unique file name
        $fileName = $loan->loan_no.'_'. time() . '.xlsx';
        $filePath = 'exports/' . $fileName;

        // Save the spreadsheet to storage
        $writer = new Xlsx($spreadsheet);
        ob_start(); // Start output buffering
        $writer->save('php://output'); // Write the file content to the output buffer
        $fileContent = ob_get_clean(); // Get the content and clear the buffer

        Storage::put($filePath, $fileContent); // Save the content to storage
        //return sendSuccessResponse('Customers downloaded successfully!',200,$response);
        $this->reportBackupRepository->create([
            'company_id'    => $loan->company_id,
            'backup_type'   => 'customer_loan_list',
            'backup_date'   => carbon::now()->format('Y-m-d'),
            'search_data'   => json_encode($request->all()),
            'backup_by'     => auth()->user()->id
        ]);

        // Generate a signed URL for secure download (optional)
        $downloadUrl = Storage::url($filePath);
        //add domain to download url
        $fullUrl = downloadFileUrl($fileName);

        // Return success response with download URL
        return sendSuccessResponse('loan details is ready for download.',200, ['download_url' => $fullUrl]);
    }

    public function customerDashboard(){
        $userId = auth()->user()->id;
        $customer = $this->customerRepository->getCustomerbyUserId($userId);
        if(empty($customer)){
            return sendErrorResponse('Customer not found!', 404);
        }else{
            $customerId = $customer->id;
            $companyId  = $customer->company_id;

            //get customer loan data
            $totalLoanAmount = $this->customerLoanRepository->getTotalLoanAmount($companyId,null,$customerId);
            $loanIds         = $this->customerLoanRepository->getRunningLoanIds($companyId,null,$customerId)->toArray();
            $totalPaidAmount = 0;
            $totalRemainingAmount = 0;
            if(count($loanIds)>0){
                $totalPaidAmount = $this->customerLoanRepository->getPaidAmountByLoanIds($customerId,$loanIds);
                $totalRemainingAmount = $totalLoanAmount-$totalPaidAmount;
            }
            $runningLoans = count($loanIds);

            //get cutomer Deposit data
            $depositamount = $this->depositHistoryRepository->getdepositAmountByCustomerId($customerId,'credit');
            $withdrawamount = $this->depositHistoryRepository->getdepositAmountByCustomerId($customerId,'debit');
            $totalDepositamount = $depositamount - $withdrawamount;
            
            $responseData = [
                'loan_amount'           => (float)$totalLoanAmount,
                'paid_amount'           => (float)$totalPaidAmount,
                'remaining_amount'      => $totalRemainingAmount,
                'running_loans'         => $runningLoans,
                'daily_amount_balance'  => $totalDepositamount,
            ];

            return sendSuccessResponse('Customer Dashboard Data.',200, $responseData);

        }

    }
}