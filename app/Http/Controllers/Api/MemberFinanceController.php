<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\LoanHistory;
use Illuminate\Http\Request;
use App\Repositories\LoanHistoryRepository;
use App\Repositories\MemberRepository;
use App\Repositories\CustomerLoanRepository;
use App\Repositories\CustomerDepositRepository;
use App\Repositories\MemberFinanceHistoryRepository;
use App\Repositories\MemberFinanceRepository;    
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use exception;
use Carbon\Carbon;    

class MemberFinanceController extends Controller
{
    protected $loanHistoryRepository;
    protected $memberRepository;
    protected $customerLoanRepository;
    protected $memberFinanceHistoryRepository;
    protected $memberFinanceRepository;
    protected $customerDepositRepository;

    public function __construct(
        LoanHistoryRepository $loanHistoryRepository,
        MemberRepository $memberRepository,
        CustomerLoanRepository $customerLoanRepository,
        MemberFinanceHistoryRepository $memberFinanceHistoryRepository,
        MemberFinanceRepository $memberFinanceRepository,
        CustomerDepositRepository $customerDepositRepository
        )
    {
        $this->loanHistoryRepository = $loanHistoryRepository;
        $this->memberRepository = $memberRepository;
        $this->customerLoanRepository = $customerLoanRepository;
        $this->memberFinanceHistoryRepository = $memberFinanceHistoryRepository;
        $this->memberFinanceRepository = $memberFinanceRepository;
        $this->customerDepositRepository = $customerDepositRepository;
    }


    public function store(Request $request){

        // Validate the request
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|integer|exists:companies,id',
            'member_id' => 'required|integer|exists:members,id',
            'amount' => 'required|numeric|min:1',
        ]);
        
        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }
        
        $receiveDate    = Carbon::now()->format('Y-m-d H:i:s');
        //$receiveDate    = '2024-10-26 12:00:00';

        DB::beginTransaction();
        
        // update member finance
        $checkDate  = Carbon::now()->format('Y-m-d');
        $memberId   = $request->member_id;
        $companyId  = $request->company_id;
        $member     = $this->memberRepository->find($memberId);
        //$checkDate    = '2024-10-26';
        $memberFinance = $this->memberFinanceRepository->getMemberFinance($memberId,$companyId,$checkDate,'working');
        if($memberFinance)
        {
            $memberFinance->balance = $memberFinance->balance + $request->amount;
            $memberFinance->save();
        }
        else
        {
            $memberFinance = $this->memberFinanceRepository->create([
                'member_id' => $memberId,
                'company_id' => $companyId,
                'collect_date' => $checkDate,
                'balance' => $member->balance + $request->amount
            ]);
            
            $memberData = $this->memberFinanceRepository->getMemberFinance($memberId,$companyId,null,'working');
            if($memberData)
            {
                $this->memberFinanceRepository->updateMemberFinance($memberId, $companyId,$checkDate);
            }
        }

        //create member finance history
        $memberFinanceHistory = $this->memberFinanceHistoryRepository->create([
            'member_finance_id' => $memberFinance->id,
            'amount' => $request->amount,
            'amount_by' => 'advance',
            'amount_by_id' => $request->company_id,
            'amount_type' => 'credit',
            'amount_date' => $receiveDate,
            'details' => $request->details ?? null
        ]);

        //update Member balance
        if($member)
        {
            $member->balance = $member->balance + $request->amount;
            $member->save();
        }

    
        DB::commit();
        return sendSuccessResponse('Advance Paid successfully!', 201, $memberFinanceHistory);
    }

    public function getCollections(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|integer|exists:companies,id',
        ]);
        
        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try
        {
            $filterDate = Carbon::now()->format('Y-m-d');
            if($request->date && $request->date != 'null')
            {
                $date           = preg_replace('/\s*\(.*\)$/', '', $request->date);
                $filterDate     = Carbon::parse($date)->format('Y-m-d');    
            }

            //echo $filterDate;
            $collections = $this->memberFinanceRepository->getCollection($request->company_id,$filterDate);

            // echo '<pre>';
            // print_r($collections);
            // echo '</pre>';
            // exit();
            // \Log::info($collections);

            if(!$collections->isEmpty())
            {
                $total_deposit_debit = 0;
                $total_deposit_credit = 0;
                $total_loan_credit = 0;

                foreach ($collections as $key => $value) {
                    $memberFinanceId = $value->id;
                    $memberId = $value->member->id;
                    $customerCount = $this->memberFinanceHistoryRepository->getCustomerCount($memberFinanceId);

                    $totalDepositCustomer   = $this->customerDepositRepository->getDepositCustomersIdbyCompany($request->company_id,null,null,$memberId);
                    //total loan customers
                    $totalLoanCustomer      = $this->customerLoanRepository->getLoanCustomersIdbyCompany($request->company_id,null,null,$memberId);

                    $totalCustomer = 0;
                    if(count($totalDepositCustomer)>0 && count($totalLoanCustomer)>0){
                        $totalCustomer = count(array_unique(array_merge($totalDepositCustomer,$totalLoanCustomer)));
                    }else if(count($totalDepositCustomer)>0){
                        $totalCustomer = count($totalDepositCustomer);
                    }else if(count($totalLoanCustomer)>0){
                        $totalCustomer = count($totalLoanCustomer);
                    }
                    
                    $value->customer_count = $customerCount;
                    $value->total_customer_count = $totalCustomer;
                    $value->remaining_customer = $totalCustomer - $customerCount;

                    if($value->member_finance_history){
                        foreach ($value->member_finance_history as $mkey => $mvalue) {
                            if($mvalue->amount_by=='deposit' && $mvalue->amount_type=='debit'){
                                $total_deposit_debit = $total_deposit_debit + $mvalue->amount;
                            }else if($mvalue->amount_by=='deposit' && $mvalue->amount_type=='credit'){
                                $total_deposit_credit = $total_deposit_credit + $mvalue->amount;
                            }else if($mvalue->amount_by=='loan' && $mvalue->amount_type=='credit'){
                                $total_loan_credit = $total_loan_credit + $mvalue->amount;
                            }
                        }
                    }
                }

                //unset the member_finance_history from collections
                foreach ($collections as $key => $value) {
                    unset($value->member_finance_history);
                }
                
                $today_paid_amount = $this->memberFinanceRepository->getPaidMemberBalance($request->company_id);
                $today_remaining_amount = $this->memberFinanceRepository->getWorkingMemberBalance($request->company_id);

                
                $responseData = [
                    'total_deposit_debit' => $total_deposit_debit,
                    'total_deposit_credit' => $total_deposit_credit,
                    'total_loan_credit' => $total_loan_credit,
                    'total_member_pending' => (float)$today_remaining_amount,
                    'total_member_received' => (float)$today_paid_amount,
                    'collections' => $collections
                ];
                return sendSuccessResponse('Collections found successfully!', 200, $responseData);
            }
            else
            {
                return sendErrorResponse('Collections not found!', 200);
            }
            
        }catch (\Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    public function collectionDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'collection_id' => 'required|integer|exists:member_finance,id',
        ]);
        
        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try
        {
            $details = $this->memberFinanceHistoryRepository->getCollectionDetails($request->collection_id);

            if($details)
            {
                return sendSuccessResponse('Collection details found successfully!', 200, $details);
            }
            else
            {
                return sendErrorResponse('Collection details not found!', 200);
            }
            
        }catch (\Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    public function payCollection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'collection_id' => 'required|integer|exists:member_finance,id',
            'amount' => 'required|numeric|min:1',
        ]);
        
        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try
        {
            $collection = $this->memberFinanceRepository->find($request->collection_id);
            if(!$collection && $collection->payment_status != 'working')
            {
                return sendErrorResponse('Collection not found!', 404);
            }
            $remainingAmount = $collection->balance - $request->amount;

            DB::beginTransaction();
            $updateData = [
                'paid_amount' => $request->amount,
                'payment_status' => 'paid',
                'balance' => $remainingAmount,
                'remaining_amount' => $remainingAmount,
                'details' => $request->details,
                'paid_date' => Carbon::now()->format('Y-m-d H:i:s')
            ];

            $details = $this->memberFinanceRepository->update($request->collection_id,$updateData);

            if($details)
            {
                //update member balance
                $member = $this->memberRepository->find($collection->member_id);
                $memberBalance = $member->balance;
                $member->balance = $memberBalance - $request->amount;
                $member->save();
                DB::commit();
                return sendSuccessResponse('Collection details updated successfully!', 200, $details);
            }
            else
            {
                return sendErrorResponse('Collection details not updated!', 500);
            }
            
        }catch (\Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }
}