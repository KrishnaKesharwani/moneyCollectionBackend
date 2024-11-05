<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\DepositHistory;
use Illuminate\Http\Request;
use App\Repositories\DepositHistoryRepository;
use App\Repositories\MemberRepository;
use App\Repositories\CustomerDepositRepository;
use App\Repositories\MemberFinanceHistoryRepository;
use App\Repositories\MemberFinanceRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use exception;
use Carbon\Carbon;    

class DepositHistoryController extends Controller
{
    protected $depositHistoryRepository;
    protected $memberRepository;
    protected $customerDepositRepository;
    protected $memberFinanceHistoryRepository;
    protected $memberFinanceRepository;
    public function __construct(
        DepositHistoryRepository $depositHistoryRepository,
        MemberRepository $memberRepository,
        CustomerDepositRepository $customerDepositRepository,
        MemberFinanceHistoryRepository $memberFinanceHistoryRepository,
        MemberFinanceRepository $memberFinanceRepository
        )
    {
        $this->depositHistoryRepository = $depositHistoryRepository;
        $this->memberRepository = $memberRepository;
        $this->customerDepositRepository = $customerDepositRepository;
        $this->memberFinanceHistoryRepository = $memberFinanceHistoryRepository;
        $this->memberFinanceRepository = $memberFinanceRepository;
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
        
        if($request->amount > $remainingAmount && $request->deposit_type == 'debit')
        {
            return sendErrorResponse('Customer does not have enough deposit!', 422);
        }
        elseif($request->deposit_type == 'debit' && $member->balance < $request->amount)
        {
            return sendErrorResponse('Member balance not enough!', 422);
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

            $checkDate = Carbon::now()->format('Y-m-d');

            // update member finance
            $memberFinance = $this->memberFinanceRepository->getMemberFinance($memberId,$member->company_id,$checkDate,'working');
            if($memberFinance)
            {
                if($request->deposit_type == 'debit')
                {
                    $memberFinance->balance = $memberFinance->balance - $request->amount;
                }
                else
                {
                    $memberFinance->balance = $memberFinance->balance + $request->amount;
                }
                $memberFinance->save();
            }
            else
            {
                if($request->deposit_type == 'debit'){
                    $balance = $member->balance - $request->amount;
                }else
                {
                    $balance = $member->balance + $request->amount;
                }
                $memberFinance = $this->memberFinanceRepository->create([
                    'member_id' => $memberId,
                    'company_id' => $member->company_id,
                    'collect_date' => $receiveDate,
                    'balance' => $balance,
                    'previous_balance' => $member->balance
                ]);

                $memberData = $this->memberFinanceRepository->getMemberFinance($memberId,$member->company_id,null,'working');
                if($memberData)
                {
                    $this->memberFinanceRepository->updateMemberFinance($memberId, $member->company_id,$checkDate);
                }
            }

            //update member finance history
            $this->memberFinanceHistoryRepository->create([
                'member_finance_id' => $memberFinance->id,
                'amount' => $request->amount,
                'amount_by' => 'deposit',
                'amount_by_id' => $request->deposit_id,
                'customer_id' => $deposit->customer_id,
                'amount_type' => $request->deposit_type,
                'amount_date' => $receiveDate
            ]);

            //update Member balance
            if($member){
                if($request->deposit_type == 'debit')
                {
                    $member->balance = $member->balance - $request->amount;
                }else
                {
                    $member->balance = $member->balance + $request->amount;
                }
                $member->save();
            }
            
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