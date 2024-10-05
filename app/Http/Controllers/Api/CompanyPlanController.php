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

class CompanyPlanController extends Controller
{

    protected $companyPlanRepository;
    public function __construct(CompanyPlanRepository $companyPlanRepository)
    {
        $this->companyPlanRepository = $companyPlanRepository;
    }

    public function planHistory(Request $request){
        $request->validate([
            'plan_id' => 'required',
        ]);

        $CompanyPlan = CompanyPlan::where('id', $request->plan_id)->with('companyPlanHistory')->orderBy('id', 'desc')->get();
        
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
