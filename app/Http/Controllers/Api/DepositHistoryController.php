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
            'select_date' => 'required',
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
            if($deposit->status != 'active')
            {
                return sendErrorResponse('Deposit is not active!', 404);
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
        
        $receiveDate    = ($request->select_date)?Carbon::parse($request->select_date)->format('Y-m-d H:i:s'): Carbon::now()->format('Y-m-d H:i:s');

        $depositHistory    = $this->depositHistoryRepository->create([
            'deposit_id' => $request->deposit_id,
            'amount' => $request->amount,
            'action_type' => $request->deposit_type,
            'action_date' => $receiveDate,
            'receiver_member_id' =>  $memberId
        ]);
        
        if($depositHistory)
        {
            $payDate   = Carbon::parse($request->select_date)->format('Y-m-d');
            $checkDate = Carbon::now()->format('Y-m-d');

            //check payDate is not today date

            if($payDate != $checkDate)
            {
                $memberFinance = $this->memberFinanceRepository->getMemberFinance($memberId,$member->company_id,$payDate);
            }else{
                $memberFinance = $this->memberFinanceRepository->getMemberFinance($memberId,$member->company_id,$checkDate,'working');
            }

            // update member finance
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

                if($payDate == $checkDate)
                {
                    $memberData = $this->memberFinanceRepository->getMemberFinance($memberId,$member->company_id,null,'working');
                    if($memberData)
                    {
                        $this->memberFinanceRepository->updateMemberFinance($memberId, $member->company_id,$checkDate);
                    }
                }
            }

            //update member finance history
            $this->memberFinanceHistoryRepository->create([
                'member_finance_id' => $memberFinance->id,
                'amount' => $request->amount,
                'amount_by' => 'deposit',
                'amount_by_id' => $request->deposit_id,
                'history_id'   => $depositHistory->id,
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


    public function update(Request $request){

        // Validate the request
        $validator = Validator::make($request->all(), [
            'deposit_history_id' => 'required|integer|exists:deposit_history,id',
            'amount' => 'required|numeric|min:1',
        ]);
        
        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }
        $depositHistoryId = $request->deposit_history_id;
        $deposit = $this->depositHistoryRepository->find($depositHistoryId);

        if(!$deposit)
        {
            return sendErrorResponse('Deposit history not found!', 404);
        }

        $amountType = '';
        $changedAmount = 0;

        if($deposit->action_type == 'credit'){
            if($request->amount > $deposit->amount)
            {
                $amountType = 'add';
                $changedAmount = $request->amount - $deposit->amount;
            }
            else if($request->amount < $deposit->amount)
            {
                $amountType = 'substract';
                $changedAmount = $deposit->amount - $request->amount;
            }
        }else if($deposit->action_type == 'debit'){
            if($request->amount > $deposit->amount)
            {
                $amountType = 'Substract';
                $changedAmount = $request->amount - $deposit->amount;
            }
            else if($request->amount < $deposit->amount)
            {
                $amountType = 'add';
                $changedAmount = $deposit->amount - $request->amount;
            }
        }



        elseif($request->amount == $deposit->amount)
        {
            return sendErrorResponse('The requested amount is same as the deposit amount already.There is no need to update!', 422);
        }

        DB::beginTransaction();
        
        $depositHistory    = $this->depositHistoryRepository->update($depositHistoryId,[
            'amount' => $request->amount,
        ]);
        
        if($depositHistory)
        {
            // update member finance
            $memberFinanceHistory = $this->memberFinanceRepository->getMemberFinanceHistoryDetail($depositHistoryId);
            \Log::info(json_encode($memberFinanceHistory));
            if($memberFinanceHistory)
            {
                                
                //update member finance history
                $this->memberFinanceHistoryRepository->update($memberFinanceHistory->id,[
                    'amount' => $request->amount,
                ]);

                $memberFinanceBalance = $memberFinanceHistory->member_finance_balance;
                $memberBalance        = $memberFinanceHistory->member_balance;
                if($amountType == 'substract')
                {
                    $memberFinanceBalance = $memberFinanceBalance - $changedAmount;
                    $memberBalance        = $memberBalance - $changedAmount;
                }
                else if($amountType == 'add')
                {
                    $memberFinanceBalance = $memberFinanceBalance + $changedAmount;
                    $memberBalance        = $memberBalance + $changedAmount;
                }

                //update member finance balance
                $this->memberFinanceRepository->update($memberFinanceHistory->finance_id,[
                    'balance' => $memberFinanceBalance,
                ]);

                //update Member balance
                $member = $this->memberRepository->find($memberFinanceHistory->member_id);
                if($member){
                    $member->balance = $memberBalance;
                    $member->save();
                }
                DB::commit();

                $message = 'Amount updated successfully!';

                return sendSuccessResponse($message, 201, $depositHistory);
                
            }
            else{
                return sendErrorResponse('you can not edit this!', 500);
            }
            
        }
        else
        {
            return sendErrorResponse('Something went wrong!', 500);
        }
    }

}