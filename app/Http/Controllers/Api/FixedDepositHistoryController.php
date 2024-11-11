<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\FixedDepositHistory;
use Illuminate\Http\Request;
use App\Repositories\FixedDepositHistoryRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use exception;
use Carbon\Carbon;    

class FixedDepositHistoryController extends Controller
{
    protected $fixedDepositHistoryRepository;

    public function __construct(
        FixedDepositHistoryRepository $fixedDepositHistoryRepository,
        )
    {
        $this->fixedDepositHistoryRepository = $fixedDepositHistoryRepository;
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

            $message = 'Amount debit successfully!';

            return sendSuccessResponse($message, 201, $depositHistory);
        }
        else
        {
            return sendErrorResponse('Something went wrong!', 500);
        }
    }
}