<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\LoanHistory;
use Illuminate\Http\Request;
use App\Repositories\LoanHistoryRepository;
use App\Repositories\MemberRepository;
use App\Repositories\CustomerLoanRepository;
use App\Repositories\MemberFinanceHistoryRepository;
use App\Repositories\MemberFinanceRepository;
use App\Repositories\DepositHistoryRepository;
use App\Repositories\CustomerDepositRepository;    
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use exception;
use Carbon\Carbon;    

class LoanHistoryController extends Controller
{
    protected $loanHistoryRepository;
    protected $memberRepository;
    protected $customerLoanRepository;
    protected $memberFinanceHistoryRepository;
    protected $memberFinanceRepository;
    protected $depositHistoryRepository;
    protected $customerDepositRepository;

    public function __construct(
        LoanHistoryRepository $loanHistoryRepository,
        MemberRepository $memberRepository,
        CustomerLoanRepository $customerLoanRepository,
        MemberFinanceHistoryRepository $memberFinanceHistoryRepository,
        MemberFinanceRepository $memberFinanceRepository,
        DepositHistoryRepository $depositHistoryRepository,
        CustomerDepositRepository $customerDepositRepository,
        )
    {
        $this->loanHistoryRepository = $loanHistoryRepository;
        $this->memberRepository = $memberRepository;
        $this->customerLoanRepository = $customerLoanRepository;
        $this->memberFinanceHistoryRepository = $memberFinanceHistoryRepository;
        $this->memberFinanceRepository = $memberFinanceRepository;
        $this->depositHistoryRepository = $depositHistoryRepository;
        $this->customerDepositRepository = $customerDepositRepository;
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
        //$receiveDate    = '2024-10-26 12:00:00';

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
            // update member finance
            $checkDate = Carbon::now()->format('Y-m-d');
            //$checkDate    = '2024-10-26';
            $memberFinance = $this->memberFinanceRepository->getMemberFinance($memberId,$member->company_id,$checkDate,'working');
            if($memberFinance)
            {
                $memberFinance->balance = $memberFinance->balance + $request->amount;
                $memberFinance->save();
            }
            else
            {
                $memberFinance = $this->memberFinanceRepository->create([
                    'member_id' => $memberId,
                    'company_id' => $member->company_id,
                    'collect_date' => $checkDate,
                    'balance' => $member->balance + $request->amount,
                    'previous_balance' => $member->balance,
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
                'amount_by' => 'loan',
                'amount_by_id' => $request->loan_id,
                'customer_id' => $loan->customer_id,
                'amount_type' => 'credit',
                'amount_date' => $receiveDate
            ]);

            //update Member balance
            if($member)
            {
                $member->balance = $member->balance + $request->amount;
                $member->save();
            }

            $totalPaidAmount = $this->loanHistoryRepository->getTotalPaidAmount($request->loan_id);
            if($totalPaidAmount >= $loan->loan_amount)
            {
                $loan->update(['loan_status' => 'completed','loan_status_changed_by' => $userId,'loan_status_change_date' => Carbon::now()->format('Y-m-d H:i:s'),'loan_status_message' => 'Due to pay complete paid amount loan auto completed']);
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
            $memberId = $request->member_id;
            $collection = $this->loanHistoryRepository->getTodayCollection($memberId);

            $collectedData = [];
            $attendedCustomer = [];
            $totalCollection = 0;


            if(!$collection->isEmpty()){
                foreach($collection as $loan){
                    $container['customer_name'] = $loan?->loan?->customer?->name;
                    $container['customer_mobile'] = $loan?->loan?->customer?->mobile;
                    $container['amount'] = $loan->amount;
                    $container['receive_date'] = Carbon::parse($loan->receive_date)->format('Y-m-d');
                    $container['receive_time'] = Carbon::parse($loan->receive_date)->format('H:i:s');
                    $container['collection_type'] = 'loan';
                    $container['received_type'] = 'credit';
                    $attendedCustomer[] = $loan?->loan?->customer?->id;
                    $totalCollection += $loan->amount;
                    $collectedData[] = $container;
                }
            }

            $depositCollection = $this->depositHistoryRepository->getTodayCollection($memberId);

            if(!$depositCollection->isEmpty()){
                foreach($depositCollection as $deposit){
                    $container['customer_name'] = $deposit->customer_name;
                    $container['customer_mobile'] = $deposit->mobile;
                    $container['amount'] = $deposit->amount;
                    $container['receive_date'] = Carbon::parse($deposit->created_at)->format('Y-m-d');
                    $container['receive_time'] = Carbon::parse($deposit->created_at)->format('H:i:s');
                    $container['collection_type'] = 'deposit';
                    $container['received_type'] = $deposit->action_type;
                    $attendedCustomer[] = $deposit->customer_id;
                    if($deposit->action_type=='credit'){
                        $totalCollection += $deposit->amount;
                    }else{
                        $totalCollection -= $deposit->amount;
                    }
                    
                    $collectedData[] = $container;
                }
            }

            //get total loan cutomer id
            $totalLoanCustomerId = $this->customerLoanRepository->getTotalCustomersId($memberId,'paid')->toArray();

            //get total deposit customer id 
            $totalDepositCustomerId = $this->customerDepositRepository->getTotalDepositCustomersId($memberId,'active')->toArray();

            $totalCustomerId = 0;
            if(count($totalLoanCustomerId)>0 && count($totalDepositCustomerId)>0)
            {
                $totalCustomerId = count(array_unique(array_merge($totalLoanCustomerId,$totalDepositCustomerId)));
            }
            elseif(count($totalLoanCustomerId)>0)
            {
                $totalCustomerId = count($totalLoanCustomerId);
            }
            elseif(count($totalDepositCustomerId)>0)
            {
                $totalCustomerId = count($totalDepositCustomerId);
            }

            if(count($collectedData)==0)
            {
                return sendErrorResponse('Collection not found!', 404);
            }
            else
            {
            $responseData = 
                [
                    'collection' => $collectedData,
                    'attended_customer' => count($attendedCustomer)>0?count(array_unique($attendedCustomer)):0,
                    'collect_money' => $totalCollection,
                    'total_customer' => $totalCustomerId,
                ];
            return $responseData;
            }
        }
        catch (\Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }
}