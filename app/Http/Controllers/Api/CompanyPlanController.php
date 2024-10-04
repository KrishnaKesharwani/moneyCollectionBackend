<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\CompanyPlan;
use App\Models\User;
use App\Models\CompanyPlanHistory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class CompanyPlanController extends Controller
{

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
            return response()->json(['data' => $CompanyPlan], 200);
        }
    }
    
}
