<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\LoanHistory;
use Illuminate\Http\Request;
use App\Repositories\LoanHistoryRepository;
use App\Repositories\MemberRepository;
use App\Repositories\CustomerLoanRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use exception;
use Carbon\Carbon;    

class LoanHistoryController extends Controller
{
    protected $loanHistoryRepository;
    protected $memberRepository;
    protected $customerLoanRepository;
    public function __construct(
        LoanHistoryRepository $loanHistoryRepository,
        MemberRepository $memberRepository,
        CustomerLoanRepository $customerLoanRepository
        )
    {
        $this->loanHistoryRepository = $loanHistoryRepository;
        $this->memberRepository = $memberRepository;
        $this->customerLoanRepository = $customerLoanRepository;
    }


    public function store(Request $request){

        // Validate the request
        $validator = Validator::make($request->all(), [
            'loan_id' => 'required|integer|exists:customer_loans,id',
            'amount' => 'required|numeric|min:1',
        ]);
        
        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $userId = auth()->user()->id;
        $member = $this->memberRepository->getMemberByUserId($userId);
        if(!$member)
        {
            return sendErrorResponse('Member not found!', 404);
        }

        $memberId = $member->id;

        $loan = $this->customerLoanRepository->find($request->loan_id);

        if(!$loan)
        {
            return sendErrorResponse('Loan not found!', 404);
        }
        else
        {
            if($loan->assigned_member_id != $memberId)
            {
                return sendErrorResponse('You are not the assigned member of this loan for collection!', 404);
            }
        }

        
        $installment     = $loan->installment_amount;
        $totalPaidAmount = $this->loanHistoryRepository->getTotalPaidAmount($request->loan_id);
        $remainingAmount = $loan->loan_amount - $totalPaidAmount;

        if($request->amount>$remainingAmount)
        {
            return sendErrorResponse('Amount is greater than remaining amount!', 422);
        }
        
        $receiveDate    = Carbon::now()->format('Y-m-d H:i:s');

        DB::beginTransaction();
        $LoanHistory    = $this->loanHistoryRepository->create([
            'loan_id' => $request->loan_id,
            'amount' => $request->amount,
            'receive_date' => $receiveDate,
            'detail' => $request->detail ?? '',
            'receiver_member_id' =>  $memberId
        ]);
        
        if($LoanHistory)
        {
            $totalPaidAmount = $this->loanHistoryRepository->getTotalPaidAmount($request->loan_id);
            if($totalPaidAmount >= $loan->loan_amount)
            {
                $loan->update(['loan_status' => 'completed']);
            }
            DB::commit();
            return sendSuccessResponse('Amount Paid successfully!', 201, $LoanHistory);
        }
        else
        {
            return sendErrorResponse('Amount not created!', 500);
        }
    }

    public function getTodayCollection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer|exists:members,id',
        ]);
        
        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            $collection = $this->loanHistoryRepository->getTodayCollection($request->member_id);
            if($collection->isEmpty())
            {
                return sendErrorResponse('Collection not found!', 404);
            }
            else
            {
                $totalCollection = 0;
                $totalAttendedCustomer = 0;
                $totalCustomer = 0;
                $loanIds = [];
                foreach ($collection as $item) {
                    $totalCollection += $item->amount;
                    $loanIds[] = $item->loan_id;                  
                }

                if(count($loanIds) > 1){
                    $loanIds = array_unique($loanIds);
                    $totalAttendedCustomer = $this->customerLoanRepository->getTotalAttendedCustomer($loanIds);
                }

                $totalCustomer = $this->customerLoanRepository->getTotalCustomers($request->member_id,'paid');
                $responseData = 
                [
                    'collection' => $collection,
                    'attended_customer' => $totalAttendedCustomer,
                    'total_customer' => $totalCustomer,
                ];


                return sendSuccessResponse('Collection found successfully!', 200, $responseData);
            }
        }
        catch (\Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }
}