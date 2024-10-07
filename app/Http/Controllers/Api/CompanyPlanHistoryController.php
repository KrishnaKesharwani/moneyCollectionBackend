<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\CompanyPlan;
use App\Models\CompanyPlanHistory;
use Illuminate\Http\Request;
use App\Repositories\CompanyPlanRepository;
use App\Repositories\CompanyPlanHistoryRepository;
use App\Http\Requests\StoreCompanyPlanHistoryRequest;

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


    public function store(StoreCompanyPlanHistoryRequest $request){

        // Validate the request
        $validatedData = $request->validated();

        $plan = $this->companyPlanRepository->find($request->plan_id);
        if(!$plan){
            return response()->json(['message' => 'Plan not found'], 404);
        }else{
            $totalPaidAmount = $this->companyPlanHistoryRepository->getTotalPaidAmount($plan->id);
            $remainingAmount = $plan->total_amount - $totalPaidAmount;

            if($request->amount>$remainingAmount)
            {
                return response()->json(['message' => 'Amount is greater than remaining amount!'], 422);
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
                return response()->json(['message' => 'Plan History created successfully!','data' => $planHistory], 201);
            }
            else
            {
                return response()->json(['message' => 'Plan History not created!'], 500);
            }
        }
    }
}
