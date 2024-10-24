<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\Company;
use App\Models\CompanyPlan;
use App\Models\User;
use App\Models\CompanyPlanHistory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Repositories\CompanyRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use exception;

class CompanyController extends Controller
{

    protected $companyRepository;
    protected $userRepository;

    public function __construct(
        CompanyRepository $companyRepository,
        UserRepository $userRepository
        )
    {
        $this->companyRepository        = $companyRepository;
        $this->userRepository           = $userRepository;
    }

    public function index(Request $request){

        $upcomingExpire = $request->get('upcoming_expire', 0);
    
        // Get the current date
        $now = Carbon::now();
        
        if($upcomingExpire==0)
        {
            $companies = Company::with(['plans','plans.companyPlanHistory'])->orderBy('id', 'desc')->get();
        }
        else
        {
            $companies = Company::whereHas('plans', function($query) use ($now) {
                $query->where('status', 'active')
                      ->where('plan', '!=', 'demo')
                      ->where(function ($query) use ($now) {
                          // Clone $now before each modification
                          $nowForMonthly = clone $now;
                          $nowForThreeMonth = clone $now;
                          $nowForSixMonth = clone $now;
                          
                          // Check for monthly plans expiring within 7 days
                          $query->where(function ($q) use ($nowForMonthly) {
                              $q->where('plan', 'monthly')
                                ->whereDate('end_date', '<=', $nowForMonthly->addDays(7));
                          })
                          // Check for three-month plans expiring within 15 days
                          ->orWhere(function ($q) use ($nowForThreeMonth) {
                              $q->where('plan', 'three-month')
                                ->whereDate('end_date', '<=', $nowForThreeMonth->addDays(15));
                          })
                          // Check for six-month or yearly plans expiring within 30 days
                          ->orWhere(function ($q) use ($nowForSixMonth) {
                              $q->whereIn('plan', ['six-month', 'yearly'])
                                ->whereDate('end_date', '<=', $nowForSixMonth->addMonth());
                          });
                      });
            })
            ->with(['plans' => function($query) use ($now) {
                $query->where('status', 'active')
                      ->where('plan', '!=', 'demo')
                      ->where(function ($query) use ($now) {
                          $nowForMonthly = clone $now;
                          $nowForThreeMonth = clone $now;
                          $nowForSixMonth = clone $now;

                          $query->where(function ($q) use ($nowForMonthly) {
                              $q->where('plan', 'monthly')
                                ->whereDate('end_date', '<=', $nowForMonthly->addDays(7));
                          })
                          ->orWhere(function ($q) use ($nowForThreeMonth) {
                              $q->where('plan', 'three-month')
                                ->whereDate('end_date', '<=', $nowForThreeMonth->addDays(15));
                          })
                          ->orWhere(function ($q) use ($nowForSixMonth) {
                              $q->whereIn('plan', ['six-month', 'yearly'])
                                ->whereDate('end_date', '<=', $nowForSixMonth->addMonth());
                          });
                      });
            }, 'plans.companyPlanHistory'])
            ->orderBy('id', 'desc')
            ->get();
        }
        if($companies->isEmpty())
        {
            return sendErrorResponse('No companies found!', 404);
        }
        else
        {
            foreach ($companies as $company) {
                // Loop through each plan for this company
                $company->total_paid_amount = 0;
                $company->remaining_amount  = 0;
                $company->start_date        = '';
                $company->end_date          = '';
                foreach ($company->plans as $plan) {
                    $plan->total_paid_amount = 0;
                    $plan->remaining_amount = 0;
                    // Add the plan's total_amount to the company's total_paid_amount
                    foreach ($plan->companyPlanHistory as $history) {
                        $plan->total_paid_amount += $history->amount;
                    }
                    $plan->remaining_amount = $plan->total_amount - $plan->total_paid_amount;

                    if($plan->status=='active'){
                        $company->total_amount      = $plan->total_amount;
                        $company->total_paid_amount = $plan->total_paid_amount;
                        $company->remaining_amount  = $plan->remaining_amount;
                        $company->start_date        = $plan->start_date;
                        $company->end_date          = $plan->end_date;
                    }
                }
            }
            
            return sendSuccessResponse('Companies found successfully!', 200, $companies);
        }
    }


    public function companyDashboard(){
        $totalCompanies     = Company::count();
        $runningCompanies   = DB::table('companies')
                            ->join('company_plans', function($join){
                                $join->on('companies.id', '=', 'company_plans.company_id')
                                    ->where('company_plans.status', '=', 'active')
                                    ->whereIn('company_plans.plan', ['monthly', 'yearly','six-month','three-month'])
                                    ->orderBy('company_plans.end_date', 'desc')
                                    ->where('company_plans.end_date', '>=', date('Y-m-d'));
                            })
                            ->where('companies.status','=','active')
                            ->count();
        $expiredDemo       = DB::table('companies')
                                ->join('company_plans', function($join){
                                    $join->on('companies.id', '=', 'company_plans.company_id')
                                        ->whereIn('company_plans.plan', ['demo'])
                                        ->orderBy('company_plans.end_date', 'desc')
                                        ->where('company_plans.end_date', '<', date('Y-m-d'));
                                })
                                ->where('companies.status','=','active')
                                ->count();

        $runnigDemo        = DB::table('companies')
                                ->join('company_plans', function($join){
                                    $join->on('companies.id', '=', 'company_plans.company_id')
                                        ->whereIn('company_plans.plan', ['demo'])
                                        ->orderBy('company_plans.end_date', 'desc')
                                        ->where('company_plans.end_date', '>=', date('Y-m-d'));
                                })
                                ->where('companies.status','=','active')
                                ->count();
        return sendSuccessResponse('Dashboard Counts', 200, ['totalCompanies' => $totalCompanies, 'runningCompanies' => $runningCompanies, 'expiredDemo' => $expiredDemo, 'runnigDemo' => $runnigDemo]);
    }


    public function store(Request $request)
    {
        // Validate the request

        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string',
            'owner_name' => 'required|string',
            'mobile' => 'required|digits_between:10,15',
            'start_date' => 'required',
            'end_date' => 'required',
            'aadhar_no' => 'nullable|string',
            'plan' => 'required|string',
            'total_amount' => 'required|numeric',
            'advance_amount' => 'required|numeric',
            'status' => 'required|string',
            'main_logo' => 'nullable|string',
            'sidebar_logo' => 'nullable|string',
            'favicon_icon' => 'nullable|string',
            'owner_image' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'details' => 'nullable|string|max:500',
            'company_login_id' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            $checkUser  = User::where('email', $request->company_login_id)->count();
            if($checkUser > 0){
                return sendErrorResponse('Email already exists!', 409, $checkUser);
            }

            $checkUserMobile  = User::where('mobile', $request->mobile)->count();
            if($checkUserMobile > 0){
                return sendErrorResponse('Mobile already exists!', 409, $checkUserMobile);
            }

            $user = $this->createUser($request);
            //create company user after the company is created
            
            $cleanStartDate       = preg_replace('/\s*\(.*\)$/', '', $request->start_date);
            $cleanEndDate         = preg_replace('/\s*\(.*\)$/', '', $request->end_date);
            $startDate            = Carbon::parse($cleanStartDate)->format('Y-m-d');
            $endDate              = Carbon::parse($cleanEndDate)->format('Y-m-d');

            // Process the base64 images
            $validatedData['main_logo']     = $this->storeBase64Image($request->main_logo, 'logos/main');
            $validatedData['sidebar_logo']  = $this->storeBase64Image($request->sidebar_logo, 'logos/sidebar');
            $validatedData['favicon_icon']  = $this->storeBase64Image($request->favicon_icon, 'icons/favicon');
            $validatedData['owner_image']   = $this->storeBase64Image($request->owner_image, 'owners');
            $validatedData['user_id']       = $user->id;
            $validatedData['start_date']    = $startDate;
            $validatedData['end_date']      = $endDate;

            //\Log::info($validatedData);
            DB::beginTransaction();
            // Store the company data in the database
            $company = $this->companyRepository->create($validatedData);

            // Check if the company was successfully created
            if ($company)
            {   
                // Create the company plan after the company is created
                $companyPlan = $this->createCompanyPlan($company, $request,$startDate,$endDate);

                if($companyPlan)
                {
                    // Create the plan history after the plan is created
                    $this->createCompanyPlanHistory($companyPlan, $request);       
                }
                DB::commit();
            }

            return sendSuccessResponse('Company created successfully!', 201, $company);
        }
        catch (\Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);    
        }
    }

    public function update(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'company_id' => 'required',
            'company_name' => 'required|string',
            'owner_name' => 'required|string',
            'mobile' => 'required|digits_between:10,15',
            'aadhar_no' => 'nullable|string',
            'status' => 'required|string',
            'main_logo' => 'nullable|string',
            'sidebar_logo' => 'nullable|string',
            'favicon_icon' => 'nullable|string',
            'owner_image' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'details' => 'nullable|string|max:500',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

       
        // Validate the request
        $validatedData = $request->all();

            try {
            $company = Company::find($request->company_id);

            $checkUserMobile  = User::where('mobile', $request->mobile)->where('id', '!=', $company->user_id)->count();
            if($checkUserMobile > 0){
                return sendErrorResponse('Mobile already exists!', 409, $checkUserMobile);
            }

            DB::beginTransaction();

            if($company)
            {
                $company->company_name      = $request->company_name;
                $company->owner_name        = $request->owner_name;
                $company->aadhar_no         = $request->aadhar_no;
                $company->mobile            = $request->mobile;
                $company->status            = $request->status;
                $company->address           = $request->address;
                $company->details           = $request->details;
                $company->primary_color     = $request->primary_color ?? null;
                $company->secondary_color   = $request->secondary_color ?? null;
                $company->prefix            = $request->prefix ?? null;
                // Process the base64 images
                if($request->main_logo!='')
                {
                    $company->main_logo = $this->storeBase64Image($request->main_logo, 'logos/main');
                }
                if($request->sidebar_logo!='')
                {
                    $company->sidebar_logo = $this->storeBase64Image($request->sidebar_logo, 'logos/sidebar');
                }
                if($request->favicon_icon!='')
                {
                    $company->favicon_icon = $this->storeBase64Image($request->favicon_icon, 'icons/favicon');
                }
                if($request->owner_image!='')
                {
                    $company->owner_image = $this->storeBase64Image($request->owner_image, 'owners');
                }
                // Update the company data in the database

                // Check if the company was successfully created
                if ($company->save())
                {   
                    User::where('id', $company->user_id)->update(['mobile' => $request->mobile,'status'=>$request->status]);
                    DB::commit();
                    return sendSuccessResponse('Company updated successfully!', 200, $company);
                }
                else
                {
                    return sendErrorResponse('Company not updated successfully!', 500);
                }
                
            }
            else
            {
                return sendErrorResponse('Company not found!', 404);
            }
        }
        catch (\Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);    
        }
    }

    /**
     * Decode and store base64 image.
     *
     * @param string|null $base64Image
     * @param string $directory
     * @return string|null
     */
    private function storeBase64Image($base64Image, $directory)
    {
        if (!$base64Image) {
            return null; // Return null if no image is provided
        }

        // Extract the mime type and the Base64 data
        $imageParts = explode(';base64,', $base64Image);

        // Get the image extension from the mime type
        $imageTypeAux = explode('image/', $imageParts[0]);
        $imageType = $imageTypeAux[1]; // e.g., 'jpeg', 'png', 'gif'

        // Decode the Base64 string into binary data
        $imageData = base64_decode($imageParts[1]);

        // Generate a unique file name for the image
        $fileName = Str::random(10) . '.' . $imageType;

        // Store the image in the public storage folder (or any custom directory)
        $path = Storage::put("public/{$directory}/{$fileName}", $imageData);
        
        // Return the stored path or URL to save in the database
        return $directory.'/'.$fileName;
    }

    /**
     * Create a company plan after the company is created.
     *
     * @param \App\Models\Company $company
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\CompanyPlan
     */
    private function createCompanyPlan(Company $company, Request $request,$startDate,$endDate)
    {
        // Create a new company plan
        $planData = [
            'plan' => $request->plan, // assuming 'plan' is passed in the request
            'company_id' => $company->id,
            'total_amount' => $request->total_amount, // assuming 'total_amount' is passed in the request
            'advance_amount' => $request->advance_amount, // assuming 'advance_amount' is passed in the request
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $request->status,
        ];

        if($request->advance_amount < $request->total_amount)
        {
            $planData['full_paid'] = 0;
        }
        else{
            $planData['full_paid'] = 1;
        }

        return CompanyPlan::create($planData);
    }

    /**
     * Create plan history after the company plan is created.
     *
     * @param \App\Models\CompanyPlan $companyPlan
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\CompanyPlanHistory
     */
    private function createCompanyPlanHistory(CompanyPlan $companyPlan, Request $request)
    {
        // Create the plan history based on the company plan
        return CompanyPlanHistory::create([
            'plan_id' => $companyPlan->id,
            'amount' => $request->advance_amount, // or another amount as needed
            'pay_date' => now(), // or the payment date passed from the request
        ]);
    }

    /**
     * Create plan history after the company plan is created.
     *
     * @param \App\Models\Company $companyPlan
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\User
     */
    private function createUser(Request $request)
    {
        // Create the plan history based on the company plan
        return User::create([
            'name' => $request->input('owner_name'),
            'user_type' => 1,  // 1 for company
            'email' => $request->input('company_login_id'),  // Unique identifier for user
            'password' => Hash::make($request->input('password')),  // Hash the password
            'password_hint' => $request->input('password'),
            'mobile' => $request->input('mobile'),
            'status'=> $request->input('status')
        ]);        
    }

    public function updateCompanyStatus(Request $request){

        $validator = Validator::make($request->all(), [
            'company_id' => 'required',
            'status' => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        DB::beginTransaction();
        $company = Company::find($request->company_id);
        $company->update(['status' => $request->status]);
        if($company)
        {
            $userId = $company->user_id;
            $user = $this->userRepository->find($userId);
            $user->status = $request->status;
            $user->save();

            DB::commit();
            if($request->status=='active')
            {
                return sendSuccessResponse('Company Activated successfully!',200,$company);
            }else{
                return sendSuccessResponse('Company Inactived successfully!',200,$company);
            }
        }
        else
        {
            return sendErrorResponse('Company not found!', 404);
        }
    }
}
