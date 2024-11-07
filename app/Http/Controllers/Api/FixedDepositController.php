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


    // public function depositHistory(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'customer_id' => 'required|integer|exists:customers,id',
    //         'deposit_id' => 'required|integer|exists:customer_deposits,id',
    //     ]);
        
    //     if ($validator->fails()) {
    //         return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
    //     }


    //     try{

    //         $fromDay = $request->from_day ?? null;
    //         //get the previous date by the day count 
    //         if($fromDay){
    //             $fromDate = Carbon::now()->subDays($fromDay)->format('Y-m-d');
    //         }else{
    //             $fromDate = null;
    //         }
            
    //         $collection = $this->customerDepositRepository->getdepositHistory($request->customer_id,$request->deposit_id,$fromDate);
    //         if($collection->isEmpty())
    //         {
    //             return sendErrorResponse('Collection not found!', 404);
    //         }
    //         else
    //         {
    //             $balance = 0;
    //             foreach ($collection as $key => $value) {
    //                 $collection[$key]->balance = $balance;
    //                 if ($value->action_type == 'credit') {
    //                     $balance += $value->amount;
    //                 }
    //                 // If action_type is 'debit', subtract the amount from the balance
    //                 elseif ($value->action_type == 'debit') {
    //                     $balance -= $value->amount;
    //                 }
    //             }

    //             $sortedCollection = $collection->sortByDesc('created_at')->values();
    //             $responseData = 
    //             [
    //                 'collection' => $sortedCollection,
    //             ];
    //             return sendSuccessResponse('Collection found successfully!', 200, $responseData);
    //         }
    //     }
    //     catch (\Exception $e) {
    //         return sendErrorResponse($e->getMessage(), 500);
    //     }
    // }

    // public function changeDepositMember(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'deposit_id' => 'required|integer|exists:customer_deposits,id',
    //         'member_id' => 'required|integer|exists:members,id',
    //     ]);
        
    //     if ($validator->fails()) {
    //         return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
    //     }    

    //     try{            
    //         $customerDeposit   = $this->customerDepositRepository->find($request->deposit_id);
    //         if($customerDeposit->assigned_member_id == $request->member_id)
    //         {
    //             return sendErrorResponse('Deposite member already assigned!', 409);
    //         }
    //         DB::beginTransaction();
    //         $customerDeposit->assigned_member_id = $request->member_id;
    //         $customerDeposit->member_changed_reason = $request->reason ?? null;
    //         if($customerDeposit->save())
    //         {
    //             $memberData = [
    //                 'deposit_id' => $customerDeposit->id,
    //                 'member_id' => $request->member_id,
    //                 'member_changed_reason' => $request->reason ?? null,
    //                 'assigned_date' => Carbon::now()->format('Y-m-d H:i:s'),
    //                 'assigned_by' => auth()->user()->id,
    //             ];
    //             $memberHistory = $this->depositeMemberHistoryRepository->create($memberData);
    //             DB::commit();
    //             return sendSuccessResponse('Deposit member changed successfully!', 200);
    //         }
    //         else
    //         {   
    //             return sendErrorResponse('Deposit member not changed!', 404);
    //         }
    //     }
    //     catch (Exception $e) {
    //         return sendErrorResponse($e->getMessage(), 500);
    //     }
    // }
}
