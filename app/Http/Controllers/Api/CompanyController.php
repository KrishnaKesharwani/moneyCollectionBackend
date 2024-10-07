<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Http\Requests\UpdateCompanyRequest;
use App\Http\Requests\StoreCompanyRequest;
use App\Models\Company;
use App\Models\CompanyPlan;
use App\Models\User;
use App\Models\CompanyPlanHistory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{


    public function index(){
        $companies = Company::with(['plans','plans.companyPlanHistory'])->orderBy('id', 'desc')->get();
        if($companies->isEmpty())
        {
            return response()->json(['message' => 'No companies found!'], 404);
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
    
            return response()->json(['data' => $companies], 200);
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
                            
        return response()->json(['totalCompanies' => $totalCompanies, 'runningCompanies' => $runningCompanies, 'expiredDemo' => $expiredDemo, 'runnigDemo' => $runnigDemo], 200);
    }


    public function store(StoreCompanyRequest $request)
    {
        // Validate the request
        $validatedData = $request->validated();
        
        $checkUser  = User::where('email', $request->company_login_id)->count();
        if($checkUser > 0){
            return response()->json(['message' => 'Company already exists!'], 409);
        }
        // Process the base64 images
        $validatedData['main_logo']     = $this->storeBase64Image($request->main_logo, 'logos/main');
        $validatedData['sidebar_logo']  = $this->storeBase64Image($request->sidebar_logo, 'logos/sidebar');
        $validatedData['favicon_icon']  = $this->storeBase64Image($request->favicon_icon, 'icons/favicon');
        $validatedData['owner_image']   = $this->storeBase64Image($request->owner_image, 'owners');

        // Store the company data in the database
        $company = Company::create($validatedData);

        // Check if the company was successfully created
        if ($company)
        {   
            //create company user after the company is created

            $this->createUser($company, $request);
            // Create the company plan after the company is created
            $companyPlan = $this->createCompanyPlan($company, $request);

            if($companyPlan)
            {
                \Log::info('Company and Plan created successfully!');
                // Create the plan history after the plan is created
                $this->createCompanyPlanHistory($companyPlan, $request);
                
            }
        }

        return response()->json(['message' => 'Company and Plan created successfully!', 'data' => $company], 201);
    }

    public function update(UpdateCompanyRequest $request)
    {
        // Validate the request
        $validatedData = $request->validated();
        $company = Company::find($request->company_id);

        if($company)
        {
            $company->company_name  = $request->company_name;
            $company->owner_name    = $request->owner_name;
            $company->aadhar_no     = $request->aadhar_no;
            $company->mobile        = $request->mobile;
            $company->status        = $request->status;
            $company->address       = $request->address;
            $company->details       = $request->details;
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
                return response()->json(['message' => 'Company updated successfully!', 'data' => $company], 200);
            }
            else
            {
                return response()->json(['message' => 'Company not updated successfully!'], 500);
            }
            
        }
        else
        {
            return response()->json(['message' => 'Company not found!'], 404);
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
    private function createCompanyPlan(Company $company, Request $request)
    {
        // Create a new company plan
        $planData = [
            'plan' => $request->plan, // assuming 'plan' is passed in the request
            'company_id' => $company->id,
            'total_amount' => $request->total_amount, // assuming 'total_amount' is passed in the request
            'advance_amount' => $request->advance_amount, // assuming 'advance_amount' is passed in the request
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
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
    private function createUser(Company $company, Request $request)
    {
        // Create the plan history based on the company plan
        return User::create([
            'company_id' => $company->id,  // Associate with the company
            'name' => $request->input('owner_name'),
            'user_type' => 1,  // 1 for company
            'email' => $request->input('company_login_id'),  // Unique identifier for user
            'password' => Hash::make($request->input('password')),  // Hash the password
        ]);        
    }

    public function updateCompanyStatus(Request $request){

        $validatedData = $request->validate([
            'company_id' => 'required',
            'status' => 'required',
        ]);

        $company = Company::find($request->company_id);
        $company->update(['status' => $request->status]);
        if($company)
        {
            if($request->status=='active')
            {
                return response()->json(['message' => 'Company Activated successfully!','data' => $company],200);
            }else{
                return response()->json(['message' => 'Company Inactived successfully!','data'=> $company], 200);
            }
        }
        else
        {
            return response()->json(['message' => 'Company not found!'], 404);
        }
    }
}
