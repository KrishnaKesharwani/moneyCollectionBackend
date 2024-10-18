<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\LoanHistory;
use Illuminate\Http\Request;
use App\Repositories\LoanHistoryRepository;
use App\Repositories\MemberRepository;
use App\Repositories\CustomerLoanRepository;
use Illuminate\Support\Facades\Validator;
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
            'amount' => 'required',
        ]);
        
        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $userId = auth()->user()->id;
        //\Log::info('user'.$userId);
        
        $member = $this->memberRepository->getMemberByUserId($userId);
        //\Log::info($member);

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
        $LoanHistory    = $this->loanHistoryRepository->create([
            'loan_id' => $request->loan_id,
            'amount' => $request->amount,
            'receive_date' => $receiveDate,
            'detail' => $request->detail ?? '',
            'receiver_member_id' =>  $memberId
        ]);
        
        if($LoanHistory)
        {
            return sendSuccessResponse('Amount Paid successfully!', 201, $LoanHistory);
        }
        else
        {
            return sendErrorResponse('Amount not created!', 500);
        }
    }
}