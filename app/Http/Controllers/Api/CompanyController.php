<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Http\Requests\StoreCompanyRequest;
use App\Models\Company;
use App\Models\CompanyPlan;
use App\Models\User;
use App\Models\CompanyPlanHistory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CompanyController extends Controller
{
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
        return response()->json(['message' => 'Company and Plan created successfully!'], 201);
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

        // Decode the base64 image
        $image = base64_decode($base64Image);

        // Generate a unique name for the image
        $imageName = Str::random(10) . '.png';

        // Store the image in the specified directory (e.g., storage/app/public/logos/main)
        $path = Storage::put("public/{$directory}/{$imageName}", $image);

        // Return the stored path or URL to save in the database
        return $path ? Storage::url($path) : null;
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
}
