<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\DepositHistory;
use Illuminate\Http\Request;
use App\Repositories\DepositHistoryRepository;
use App\Repositories\MemberRepository;
use App\Repositories\CustomerDepositRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use exception;
use Carbon\Carbon;    

class DepositHistoryController extends Controller
{
    protected $depositHistoryRepository;
    protected $memberRepository;
    protected $customerDepositRepository;
    public function __construct(
        DepositHistoryRepository $depositHistoryRepository,
        MemberRepository $memberRepository,
        CustomerDepositRepository $customerDepositRepository
        )
    {
        $this->depositHistoryRepository = $depositHistoryRepository;
        $this->memberRepository = $memberRepository;
        $this->customerDepositRepository = $customerDepositRepository;
    }


    public function store(Request $request){

        // Validate the request
        $validator = Validator::make($request->all(), [
            'deposit_id' => 'required|integer|exists:customer_deposits,id',
            'amount' => 'required|numeric|min:1',
            'deposit_type' => 'required|string',
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

        $deposit = $this->customerDepositRepository->find($request->deposit_id);

        if(!$deposit)
        {
            return sendErrorResponse('Deposit not found!', 404);
        }
        else
        {
            if($deposit->assigned_member_id != $memberId)
            {
                return sendErrorResponse('You are not the assigned member of this deposit for collection!', 404);
            }
        }

        
        $totalPaidAmount        = $this->depositHistoryRepository->getTotalDepositAmount($request->deposit_id, 'credit');
        $totalRecievedAmount    = $this->depositHistoryRepository->getTotalDepositAmount($request->deposit_id, 'debit');
        $remainingAmount        = $totalPaidAmount - $totalRecievedAmount;

        if($request->amount>$remainingAmount && $request->deposit_type == 'debit')
        {
            return sendErrorResponse('Requested Amount is greater than remaining amount!', 422);
        }
        
        $receiveDate    = Carbon::now()->format('Y-m-d H:i:s');

        $depositHistory    = $this->depositHistoryRepository->create([
            'deposit_id' => $request->deposit_id,
            'amount' => $request->amount,
            'action_type' => $request->deposit_type,
            'action_date' => $receiveDate,
            'receiver_member_id' =>  $memberId
        ]);
        
        if($depositHistory)
        {
            if($request->deposit_type == 'credit')
            {
                $message = 'Amount credit successfully!';
            }
            else
            {
                $message = 'Amount debit successfully!';
            }

            return sendSuccessResponse($message, 201, $depositHistory);
        }
        else
        {
            return sendErrorResponse('Something went wrong!', 500);
        }
    }

}