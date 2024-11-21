<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\FixedDepositHistory;
use Illuminate\Http\Request;
use App\Repositories\FixedDepositHistoryRepository;
use App\Repositories\FixedDepositRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use exception;
use Carbon\Carbon;    

class FixedDepositHistoryController extends Controller
{
    protected $fixedDepositHistoryRepository;
    protected $fixedDepositRepository;

    public function __construct(
        FixedDepositHistoryRepository $fixedDepositHistoryRepository,
        FixedDepositRepository $fixedDepositRepository
        )
    {
        $this->fixedDepositHistoryRepository = $fixedDepositHistoryRepository;
        $this->fixedDepositRepository = $fixedDepositRepository;
    }


    public function store(Request $request){

        // Validate the request
        $validator = Validator::make($request->all(), [
            'fixed_deposit_id' => 'required|integer|exists:fixed_deposits,id',
            'amount' => 'required|numeric|min:1',
            'debit_type' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }
        
        $receiveDate    = Carbon::now()->format('Y-m-d H:i:s');

        DB::beginTransaction();
        $depositHistory    = $this->fixedDepositHistoryRepository->create([
            'fixed_deposit_id' => $request->fixed_deposit_id,
            'amount' => $request->amount,
            'action_type' => 'debit',
            'action_date' => $receiveDate,
            'debit_type' => $request->debit_type,
            'details' => $request->details ?? null
        ]);
        
        if($depositHistory)
        {
            if($request->debit_type == 'money back'){
                $fixedDeposit = $this->fixedDepositRepository->find($request->fixed_deposit_id);
                if(!$fixedDeposit)
                {
                    return sendErrorResponse('Fixed deposit not found!', 404);
                }

                if($fixedDeposit->deposit_amount >= $request->amount){
                    $fixedDeposit->deposit_amount = $fixedDeposit->deposit_amount - $request->amount;
                    $fixedDeposit->save();
                    DB::commit();
                    return sendSuccessResponse('Amount debit successfully!', 201, $depositHistory);
                }
                else
                {
                    return sendErrorResponse('Money Back amount is greater than deposit amount', 422);
                }
            }else{
                DB::commit();
                return sendSuccessResponse('Amount debit successfully!', 201, $depositHistory);
            }
        }
        else
        {
            return sendErrorResponse('Something went wrong!', 500);
        }
    }
}