<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\CompanyPlan;
use App\Models\User;
use App\Models\CompanyPlanHistory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Repositories\CompanyPlanRepository;
use App\Repositories\CompanyRepository;
use App\Http\Requests\StoreCompanyPlanRequest;
use App\Repositories\CompanyPlanHistoryRepository;
use exception;

class CompanyPlanController extends Controller
{

    protected $companyPlanRepository;
    protected $companyPlanHistoryRepository;

    public function __construct(
        CompanyRepository $companyRepository,
        CompanyPlanRepository $companyPlanRepository,
        CompanyPlanHistoryRepository $companyPlanHistoryRepository
        )
    {
        $this->companyRepository            = $companyRepository;
        $this->companyPlanRepository        = $companyPlanRepository;
        $this->companyPlanHistoryRepository = $companyPlanHistoryRepository;
    }


    public function store(StoreCompanyPlanRequest $request){
        $validatedData = $request->validated();
        try{
            $company = $this->companyRepository->find($request->company_id);
            if(!$company)
            {
                return response()->json(['message' => 'Company not found'], 404);
            }
            else
            {
                $postData = [
                    'company_id'        => $request->company_id, 
                    'plan'              => $request->plan,
                    'total_amount'      => $request->total_amount,
                    'advance_amount'    => $request->advance_amount,
                    'start_date'        => $request->start_date,
                    'end_date'          => $request->end_date,
                    'status'            => 'pending',
                ];

                if(date('Y-m-d',strtotime($request->start_date)) == date('Y-m-d'))
                {
                    $postData['status'] = 'active';
                }

                if($request->advance_amount < $request->total_amount){
                    $postData['full_paid'] = 0;
                }
                else{
                    $postData['full_paid'] = 1;
                }

                $plan = $this->companyPlanRepository->create($postData);
                if($plan)
                {
                    // Create the plan history based on the company plan
                    $planHistory = $this->companyPlanHistoryRepository->create([
                        'plan_id' => $plan->id,
                        'amount' => $request->advance_amount,
                        'pay_date' => $request->start_date,
                        'detail' => $request->detail,
                    ]);

                    if(!$planHistory){
                        return response()->json(['message' => 'Plan history not created!'], 500);
                    }
                    
                    return response()->json(['message' => 'Plan created successfully!','data' => $plan], 201);
                }
                else
                {
                    return response()->json(['message' => 'Plan not created!'], 500);
                }
            }
        }
        catch(exception $e)
        {
            return response()->json(['message' => $e->getMessage()], 500);
        }
        
    }

    public function planHistory(Request $request){
        $request->validate([
            'company_id' => 'required',
        ]);

        $CompanyPlan = CompanyPlan::where('company_id', $request->company_id)->with('companyPlanHistory')->orderBy('id', 'desc')->get();
        
        if($CompanyPlan->isEmpty())
        {
            return response()->json(['message' => 'Plan not found'], 404);
        }
        else
        {
                foreach ($CompanyPlan as $plan) {
                    $plan->total_paid_amount = 0;
                    $plan->remaining_amount = 0;
                    // Add the plan's total_amount to the company's total_paid_amount
                    foreach ($plan->companyPlanHistory as $history) {
                        $plan->total_paid_amount += $history->amount;
                    }
                    $plan->remaining_amount = $plan->total_amount - $plan->total_paid_amount;
                }
            return response()->json(['data' => $CompanyPlan], 200);
        }
    }    
}
