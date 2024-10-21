<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Repositories\CustomerDepositRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\DepositMemberHistoryRepository;
use App\Repositories\DepositHistoryRepository;
use App\Repositories\MemberRepository;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use exception;

class CustomerDepositController extends Controller
{

    protected $customerRepository;
    protected $customerDepositRepository;
    protected $depositeMemberHistoryRepository;
    protected $depositeHistoryRepository;
    protected $memberRepository;

    public function __construct(
        CustomerRepository $customerRepository,
        CustomerDepositRepository $customerDepositRepository,
        DepositMemberHistoryRepository $depositeMemberHistoryRepository,
        DepositHistoryRepository $depositeHistoryRepository,
        MemberRepository $memberRepository
        )
    {
        $this->customerRepository                       = $customerRepository;
        $this->customerDepositRepository               = $customerDepositRepository;;
        $this->depositeMemberHistoryRepository          = $depositeMemberHistoryRepository;
        $this->depositeHistoryRepository                = $depositeHistoryRepository;
        $this->memberRepository                         = $memberRepository;
    }

    public function index(Request $request){

        $inputData = [
            'company_id' => 'required|exists:companies,id',
        ];

        if(auth()->user()->user_type == 3)
        {
            $inputData['customer_id'] = 'required|exists:customers,id';
        }

        if(auth()->user()->user_type == 2)
        {
            $inputData['member_id'] = 'required|exists:members,id';
        }

        $validator = Validator::make($request->all(), $inputData);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            $status = $request->status ?? 'active';
            $member = $request->member_id ?? null;
            $customer = $request->customer_id ?? null;
            $deposits = $this->customerDepositRepository->getAllCustomerDeposits($request->company_id,$status,$member,$customer);
            if($deposits->isEmpty())
            {
                return sendErrorResponse('Deposits not found!', 404);
            }
            else
            {
                $totalRemaingAmount = 0;
                $totalCustomer = [];

                foreach($deposits as $deposit)
                {
                    $paidAmount             = $this->depositeHistoryRepository->getTotalDepositAmount($deposit->id,'credit');
                    $recievedAmount         = $this->depositeHistoryRepository->getTotalDepositAmount($deposit->id,'debit');
                    $deposit->total_paid    = $paidAmount;
                    $deposit->total_recieve = $recievedAmount;
                    $remaingAmount          = $paidAmount - $recievedAmount;
                    $deposit->remaining_amount = $remaingAmount;
                    $deposit->paid_today = 'no';
                    if($deposit->status == 'active'){
                        $totalRemaingAmount = $totalRemaingAmount + $remaingAmount;
                        $totalCustomer[]    = $deposit->customer_id;
                    }
                    
                    $depositMaxDate = $this->depositeHistoryRepository->getMaxDepositHistoryDate($deposit->id,'credit');
                    if($depositMaxDate)
                    {
                        //convert loan max date to carbon Y-m-d format
                        $depositMaxDate = Carbon::parse($depositMaxDate)->format('Y-m-d');
                        if($depositMaxDate == Carbon::now()->format('Y-m-d'))
                        {
                            $deposit->paid_today = 'yes';
                        }
                    }
                }

                $totalCustomerCount = 0;
                if(!empty($totalCustomer)){
                    $totalCustomer = array_unique($totalCustomer);
                    $totalCustomerCount = count($totalCustomer);
                }
                $depositData = [
                    'deposits' => $deposits,
                    'total_remaining_amount' => $totalRemaingAmount,
                    'total_cusotomer' => $totalCustomerCount 
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
            'assigned_member_id' => 'required|integer|exists:members,id',
            'status' => 'required|string',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            $customer = $this->customerRepository->find($request->customer_id);
            $depositCount = $customer->deposit_count;
            DB::beginTransaction();

            $validatedData['deposit_no']                = 'Deposit-'.$customer->id.'-'.$depositCount+1;
            $validatedData['created_by']                = auth()->user()->id;
            $validatedData['status']                    = $request->status ?? 'active';
            $validatedData['assigned_member_id']        = $request->assigned_member_id ?? 0;

            // Store the company data in the database
            $customerDeposit = $this->customerDepositRepository->create($validatedData);

            // Check if the company was successfully created
            if ($customerDeposit)
            {   
                
                if($request->assigned_member_id){
                    $memberData = [
                        'deposit_id' => $customerDeposit->id,
                        'member_id' => $request->assigned_member_id,
                        'assigned_date' => Carbon::now()->format('Y-m-d H:i:s'),
                        'assigned_by' => auth()->user()->id,
                    ];

                    $memberHistory = $this->depositeMemberHistoryRepository->create($memberData);
                }

                //update the customer loan count
                $customer->deposit_count = $customer->deposit_count+1;
                $customer->save();

                DB::commit();

                $DepositData = $this->customerDepositRepository->getDepositById($customerDeposit->id);
                return sendSuccessResponse('Deposite added successfully!', 201, $DepositData);
            }
            else
            {
                return sendErrorResponse('Deposit not added!', 404);
            }
        }
        catch (Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

}
