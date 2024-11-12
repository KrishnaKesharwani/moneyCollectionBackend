<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Repositories\FixedDepositRepository;
use App\Repositories\FixedDepositHistoryRepository;
use App\Repositories\CustomerRepository;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use exception;

class FixedDepositController extends Controller
{

    protected $customerRepository;
    protected $fixedDepositRepository;
    protected $fixedDepositHistoryRepository;


    public function __construct(
        CustomerRepository $customerRepository,
        FixedDepositRepository $fixedDepositRepository,
        FixedDepositHistoryRepository $fixedDepositHistoryRepository
        )
    {
        $this->customerRepository                   = $customerRepository;
        $this->fixedDepositRepository               = $fixedDepositRepository;
        $this->fixedDepositHistoryRepository         = $fixedDepositHistoryRepository;
    }

    public function index(Request $request){

        $inputData = [
            'company_id' => 'required|exists:companies,id',
        ];

        $validator = Validator::make($request->all(), $inputData);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            $status = $request->status ?? 'active';
            $customer = $request->customer_id ?? null;
            $deposits = $this->fixedDepositRepository->getAllFixedDeposits($request->company_id,$status,$customer);
            if($deposits->isEmpty())
            {
                return sendErrorResponse('Deposits not found!', 404);
            }
            else
            {
                $totalCustomer = [];
                $totalPaidAmount = 0;
                
                foreach($deposits as $deposit)
                {
                    $paidAmount             = $this->fixedDepositHistoryRepository->getTotalDepositAmount($deposit->id,'debit');
                    $deposit->total_paid    = $paidAmount;
                    if($deposit->status == 'active'){
                        $totalPaidAmount    = $totalPaidAmount + $paidAmount;
                        $totalCustomer[]    = $deposit->customer_id;
                    }
                }

                $totalCustomerCount = 0;
                if(!empty($totalCustomer)){
                    $totalCustomer = array_unique($totalCustomer);
                    $totalCustomerCount = count($totalCustomer);
                }
                $depositData = [
                    'deposits' => $deposits,
                    'total_cusotomer' => $totalCustomerCount,
                    'total_paid_amount' => $totalPaidAmount,
                ];

                return sendSuccessResponse('Deposits found successfully!', 200, $depositData);
            }
        }
        catch (\Exception $e) {
            return sendErrorResponse($e->getMessage().' on line '.$e->getLine(), 500);
        }
    }

    public function store(Request $request)
    {
        // Validate the request

        $validator = Validator::make($request->all(), [
            'company_id' => 'required|integer|exists:companies,id',
            'customer_id '  => 'required1|integer|exists:customers,id',
            'name' => 'required|string',
            'start_date' => 'required',
            'end_date' => 'required',
            'deposit_amount' => 'required|numeric',
            'status' => 'required|string',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            DB::beginTransaction();
            $cleanStartDate                             = preg_replace('/\s*\(.*\)$/', '', $request->start_date);
            $cleanEndDate                               = preg_replace('/\s*\(.*\)$/', '', $request->end_date);
            $validatedData['start_date']                = Carbon::parse($cleanStartDate)->format('Y-m-d');
            $validatedData['end_date']                  = Carbon::parse($cleanEndDate)->format('Y-m-d');
            $validatedData['apply_date']                = Carbon::now()->format('Y-m-d');
            $validatedData['status']                    = $request->status ?? 'active';
            
            
            // Store the company data in the database
            $customerDeposit = $this->fixedDepositRepository->create($validatedData);

            DB::commit();

            // Check if the company was successfully created
            if ($customerDeposit)
            {   
                return sendSuccessResponse('Fixed Deposite added successfully!', 201, $customerDeposit);
            }
            else
            {
                return sendErrorResponse('Fixed Deposit not added!', 404);
            }
        }
        catch (Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }


    public function update(Request $request)
    {
        // Validate the request

        $validator = Validator::make($request->all(), [
            'deposit_id'  => 'required|integer|exists:fixed_deposits,id',
            'name' => 'required|string',
            'start_date' => 'required',
            'end_date' => 'required',
            'deposit_amount' => 'required|numeric',
            'status' => 'required|string',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            DB::beginTransaction();
            $cleanStartDate                             = preg_replace('/\s*\(.*\)$/', '', $request->start_date);
            $cleanEndDate                               = preg_replace('/\s*\(.*\)$/', '', $request->end_date);
            $validatedData['start_date']                = Carbon::parse($cleanStartDate)->format('Y-m-d');
            $validatedData['end_date']                  = Carbon::parse($cleanEndDate)->format('Y-m-d');
            $validatedData['status']                    = $request->status ?? 'active';
            
            
            // Store the company data in the database
            $customerDeposit = $this->fixedDepositRepository->update($request->deposit_id,$validatedData);

            DB::commit();

            // Check if the company was successfully created
            if ($customerDeposit)
            {   
                return sendSuccessResponse('Fixed Deposite updated successfully!', 201, $customerDeposit);
            }
            else
            {
                return sendErrorResponse('Fixed Deposit not updated!', 404);
            }
        }
        catch (Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }


    public function updateFixedDepositStatus(Request $request){

        $validator = Validator::make($request->all(), [
            'fixed_deposit_id' => 'required|integer|exists:fixed_deposits,id',
            'deposit_status' => 'required',
            'reason' => 'nullable|string',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            DB::beginTransaction();
            //update user status
            $fixedDepositId = $request->fixed_deposit_id;
            $updateFixedDepositData = [
                'deposit_status' => $request->deposit_status,
                'reason' => $request->reason ?? null,
                'status_change_date' => Carbon::now()->format('Y-m-d H:i:s'),
            ];
            $deposit = $this->fixedDepositRepository->update($fixedDepositId,$updateFixedDepositData);

            if($deposit)
            {
            
                DB::commit();
                $depositData = $this->fixedDepositRepository->find($fixedDepositId);
                return sendSuccessResponse('Deposit status updated successfully!',200,$depositData);
            }
            else
            {
                return sendErrorResponse('Deposit status not updated',422);
            }
        }
        catch (Exception $e)
        {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    public function updateStatus(Request $request){

        $validator = Validator::make($request->all(), [
            'fixed_deposit_id' => 'required|integer|exists:fixed_deposits,id',
            'status' => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $fixedDeposit = $this->fixedDepositRepository->find($request->fixed_deposit_id);
        if($fixedDeposit)
        {   
            $fixedDeposit->status = $request->status;
            $fixedDeposit->save();
            if($request->status=='active')
            {
                return sendSuccessResponse('Fixed deposit activated successfully!',200,$fixedDeposit);
            }else{
                return sendSuccessResponse('Fixed deposit inactived successfully!',200,$fixedDeposit);
            }
        }
        else
        {
            return sendErrorResponse('Fixed deposit not found!', 404);
        }
    }

    //delete fixed deposit
    public function destroy($id){
        try{
            $fixedDeposit = $this->fixedDepositRepository->find($id);
            if($fixedDeposit)
            {
                DB::beginTransaction();
                if($fixedDeposit->delete())
                {
                    $this->fixedDepositHistoryRepository->deleteByFixedDepositId($id);
                    DB::commit();
                }
                return sendSuccessResponse('Fixed deposit deleted successfully!',200);
            }
            else
            {
                return sendErrorResponse('Fixed deposit not found!', 404);
            }
        }
        catch(Exception $e){
            return sendErrorResponse($e->getMessage(),500);
        }
    }
}
