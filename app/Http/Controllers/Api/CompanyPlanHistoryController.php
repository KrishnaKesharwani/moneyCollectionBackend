<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\CompanyPlan;
use App\Models\CompanyPlanHistory;
use Illuminate\Http\Request;
use App\Repositories\CompanyPlanRepository;
use App\Repositories\CompanyPlanHistoryRepository;
use Illuminate\Support\Facades\Validator;

class CompanyPlanHistoryController extends Controller
{

    protected $companyPlanRepository;
    protected $companyPlanHistoryRepository;
    public function __construct(
        CompanyPlanRepository $companyPlanRepository,
        CompanyPlanHistoryRepository $companyPlanHistoryRepository
        )
    {
        $this->companyPlanRepository = $companyPlanRepository;
        $this->companyPlanHistoryRepository = $companyPlanHistoryRepository;
    }


    public function store(Request $request){

        // Validate the request
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required',
            'amount' => 'required',
            'received_date' => 'required|date',
            'detail' => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $plan = $this->companyPlanRepository->find($request->plan_id);
        if(!$plan){
            return sendErrorResponse('Plan not found', 404);
        }else{
            $totalPaidAmount = $this->companyPlanHistoryRepository->getTotalPaidAmount($plan->id);
            $remainingAmount = $plan->total_amount - $totalPaidAmount;

            if($request->amount>$remainingAmount)
            {
                return sendErrorResponse('Amount is greater than remaining amount!', 422);
            }
            else
            {
                $newTotalPaidAmount = $totalPaidAmount + $request->amount;
                if($newTotalPaidAmount == $plan->total_amount){
                    $plan->full_paid = 1;
                    $plan->save();
                }
            }
            // Create the plan history based on the company plan
            $planHistory = $this->companyPlanHistoryRepository->create([
                'plan_id' => $plan->id,
                'amount' => $request->amount,
                'pay_date' => $request->received_date,
                'detail' => $request->detail,
            ]);
            
            if($planHistory)
            {
                return sendSuccessResponse('Plan History created successfully!', 201, $planHistory);
            }
            else
            {
                return sendErrorResponse('Plan History not created!', 500);
            }
        }
    }
}
