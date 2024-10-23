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
use App\Repositories\MemberRepository;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use exception;

class CustomerLoanController extends Controller
{

    protected $customerRepository;
    protected $customerLoanRepository;
    protected $loanStatusHistoryRepository;
    protected $loanMemberHistoryRepository;
    protected $loanDocumentRepository;
    protected $loanHistoryRepository;
    protected $memberRepository;

    public function __construct(
        CustomerRepository $customerRepository,
        CustomerLoanRepository $customerLoanRepository,
        LoanStatusHistoryRepository $loanStatusHistoryRepository,
        LoanMemberHistoryRepository $loanMemberHistoryRepository,
        LoanDocumentRepository $loanDocumentRepository,
        LoanHistoryRepository $loanHistoryRepository,
        MemberRepository $memberRepository
        )
    {
        $this->customerRepository                   = $customerRepository;
        $this->customerLoanRepository               = $customerLoanRepository;
        $this->loanStatusHistoryRepository          = $loanStatusHistoryRepository;
        $this->loanMemberHistoryRepository          = $loanMemberHistoryRepository;
        $this->loanDocumentRepository               = $loanDocumentRepository;
        $this->loanHistoryRepository                = $loanHistoryRepository;
        $this->memberRepository                     = $memberRepository;
    }

    public function index(Request $request){

        $inputData = [
            'company_id' => 'required|exists:companies,id',
        ];

        if(auth()->user()->user_type == 3)
        {
            $inputData['customer_id'] = 'required|exists:customers,id';
        }

        if(auth()->user()->user_type == 2)
        {
            $inputData['member_id'] = 'required|exists:members,id';
        }

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
                    if($loan->loan_status == 'paid'){
                        $totalRemaingAmount = $totalRemaingAmount + $remaingAmount;
                        $totalCustomer[] = $loan->customer_id;
                    }

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
            $customer = $this->customerRepository->find($request->customer_id);
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
            
            $collection = $this->customerLoanRepository->getLoanHistory($request->customer_id,$request->loan_id,$fromDate);
            if($collection->isEmpty())
            {
                return sendErrorResponse('Collection not found!', 404);
            }
            else
            {
                $responseData = 
                [
                    'collection' => $collection,
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
}