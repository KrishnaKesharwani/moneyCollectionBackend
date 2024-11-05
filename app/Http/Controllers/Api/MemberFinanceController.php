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

    public function __construct(
        LoanHistoryRepository $loanHistoryRepository,
        MemberRepository $memberRepository,
        CustomerLoanRepository $customerLoanRepository,
        MemberFinanceHistoryRepository $memberFinanceHistoryRepository,
        MemberFinanceRepository $memberFinanceRepository
        )
    {
        $this->loanHistoryRepository = $loanHistoryRepository;
        $this->memberRepository = $memberRepository;
        $this->customerLoanRepository = $customerLoanRepository;
        $this->memberFinanceHistoryRepository = $memberFinanceHistoryRepository;
        $this->memberFinanceRepository = $memberFinanceRepository;
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
                'company_id' => $company_id,
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
            $filterDate = null;
            if($request->date && $request->date != 'null')
            {
                $date           = preg_replace('/\s*\(.*\)$/', '', $request->date);
                $filterDate     = Carbon::parse($date)->format('Y-m-d');    
            }

            $collections = $this->memberFinanceRepository->getCollection($request->company_id,$filterDate);

            if($collections)
            {
                foreach ($collections as $key => $value) {
                    $memberFinanceId = $value->id;
                    $customerCount = $this->memberFinanceHistoryRepository->getCustomerCount($memberFinanceId);
                    $value->customer_count = $customerCount;
                }
                return sendSuccessResponse('Collections found successfully!', 200, $collections);
            }
            else
            {
                return sendErrorResponse('Collections not found!', 404);
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
                return sendErrorResponse('Collection details not found!', 404);
            }
            
        }catch (\Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }
}