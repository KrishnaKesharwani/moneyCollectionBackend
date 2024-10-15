<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Repositories\CustomerLoanRepository;
use App\Repositories\LoanStatusHistoryRepository;
use App\Repositories\LoanMemberHistoryRepository;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use exception;

class CustomerLoanController extends Controller
{


    protected $customerLoanRepository;
    protected $loanStatusHistoryRepository;
    protected $loanMemberHistoryRepository;

    public function __construct(
        CustomerLoanRepository $customerLoanRepository,
        LoanStatusHistoryRepository $loanStatusHistoryRepository,
        LoanMemberHistoryRepository $loanMemberHistoryRepository
        )
    {
        $this->customerLoanRepository               = $customerLoanRepository;
        $this->loanStatusHistoryRepository          = $loanStatusHistoryRepository;
        $this->loanMemberHistoryRepository          = $loanMemberHistoryRepository;
    }

    public function index(Request $request){
        $validator = Validator::make($request->all(), [
            'company_id' => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            $loans = $this->customerLoanRepository->getAllCustomerLoans($request->company_id);

            if($loans->isEmpty())
            {
                return sendErrorResponse('Members not found!', 404);
            }
            else
            {
                return sendSuccessResponse('Members found successfully!', 200, $loans);
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
            'company_id' => 'required|integer|exists:companies,id',
            'customer_id '  => 'required1|integer|exists:customers,id',
            'loan_amount' => 'required|numeric',
            'installment_amount'  => 'required|numeric',
            'start_date' => 'required',
            'end_date' => 'required',
            'no_of_days' => 'required|integer',
            'loan_status' => 'required|string',
            'status' => 'required|string',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            DB::beginTransaction();

            $cleanStartDate                             = preg_replace('/\s*\(.*\)$/', '', $request->start_date);
            $cleanEndDate                               = preg_replace('/\s*\(.*\)$/', '', $request->end_date);
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

}
