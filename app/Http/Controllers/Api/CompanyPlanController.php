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
use App\Repositories\CompanyPlanHistoryRepository;
use Illuminate\Support\Facades\Validator;
use exception;
use Carbon\Carbon;

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


    public function store(Request $request){
        $validator = Validator::make($request->all(), [
            'company_id' => 'required',
            'plan' => 'required|string',
            'total_amount' => 'required|numeric',
            'advance_amount' => 'required|numeric',
            'start_date' => 'required',
            'end_date' => 'required',
            'detail' => 'required',
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            $company = $this->companyRepository->find($request->company_id);
            if(!$company)
            {
                return sendErrorResponse('Company not found', 404);

            }
            else
            {
                $cleanedStartDate           = preg_replace('/\s*\(.*\)$/', '', $request->start_date);
                $cleanedEndDate             = preg_replace('/\s*\(.*\)$/', '', $request->end_date);
                $startDate                  = Carbon::parse($cleanedStartDate)->format('Y-m-d');
                $endDate                    = Carbon::parse($cleanedEndDate)->format('Y-m-d');
                $checkExistingPlan = $this->companyPlanRepository->checkExistingActivePlan($request->company_id, $startDate);
                if($checkExistingPlan)
                {
                    $error = 'A active plan already exists on this date for this company please create new plan after this date ='.$checkExistingPlan->end_date;
                    return sendErrorResponse($error, 409);
                }

                $postData = [
                    'company_id'        => $request->company_id, 
                    'plan'              => $request->plan,
                    'total_amount'      => $request->total_amount,
                    'advance_amount'    => $request->advance_amount,
                    'start_date'        => $startDate,
                    'end_date'          => $endDate,
                    'status'            => 'pending',
                ];

                if(Carbon::parse($startDate)->isToday())
                {
                    $postData['status'] = 'active';
                }WWW

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
                        'pay_date' =>$startDate,
                        'detail' => $request->detail,
                    ]);

                    if(!$planHistory){
                        return sendErrorResponse('Plan history not created!', 500);
                    }

                    return sendSuccessResponse('Plan created successfully!', 201, $plan);
                }
                else
                {
                    return sendErrorResponse('Plan not created!', 500);
                }
            }
        }
        catch(exception $e)
        {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    public function planHistory(Request $request){
        $validator = Validator::make($request->all(), [
            'company_id' => 'required',
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $CompanyPlan = CompanyPlan::where('company_id', $request->company_id)->with('companyPlanHistory')->orderBy('id', 'desc')->get();
        
        if($CompanyPlan->isEmpty())
        {
            return sendErrorResponse('Plan not found', 404);
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
            return sendSuccessResponse('Plan found successfully!', 200, $CompanyPlan);
        }
    }    
}
