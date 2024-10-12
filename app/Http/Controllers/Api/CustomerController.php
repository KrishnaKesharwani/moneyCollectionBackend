<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\User;
use App\Models\Customer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Repositories\CustomerRepository;
use App\Repositories\CompanyRepository;
use Carbon\Carbon;
use exception;
use Auth;

class CustomerController extends Controller
{


    protected $customerRepository;
    protected $companyRepository;

    public function __construct(
        CompanyRepository $companyRepository,
        customerRepository $customerRepository,
        )
    {
        $this->companyRepository        = $companyRepository;
        $this->customerRepository         = $customerRepository;
    }

    public function index(Request $request){
        $validator = Validator::make($request->all(), [
            'company_id' => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            $customers = $this->customerRepository->getAllCustomers($request->company_id);

            if($customers->isEmpty())
            {
                return sendErrorResponse('Customers not found!', 404);
            }
            else
            {
                return sendSuccessResponse('Customers found successfully!', 200, $customers);
            }
        }
        catch (\Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        // Validate the request

        $validator = Validator::make($request->all(), [
            'company_id' => 'required',
            'name'  => 'required',
            'mobile' => 'required|digits_between:10,15',
            'email'  => 'required|email',
            'join_date' => 'required',
            'aadhar_no' => 'nullable|string',
            'image' => 'nullable|string',
            'status' => 'required|string',
            'address' => 'nullable|string|max:500',
            'customer_login_id' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            $company = $this->companyRepository->find($request->company_id);
            $customer_no = 0;
            if(!$company)
            {
                return sendErrorResponse('Company not found!', 404);
            }
            else
            {
                $companyName                = $company->company_name;
                $companyprefix              = explode(' ', $companyName)[0];
                $customer_no                = $companyprefix.'-cus-'.$company->id.'-'.$company->customer_count + 1;
            }

            $checkUser = User::where('email', $request->customer_login_id)
                ->orWhere('mobile', $request->mobile)
                ->first();

            if ($checkUser)
            {
                if ($checkUser->email === $request->customer_login_id) 
                {
                    return sendErrorResponse('Email already exists!', 409);
                }
                
                if ($checkUser->mobile == $request->mobile)
                {
                    return sendErrorResponse('Mobile already exists!', 409);
                }
            }

            // Process the base64 images
            $validatedData['image']         = $this->storeBase64Image($request->image, 'customer');
            $validatedData['customer_no']   = $customer_no;
            $validatedData['created_by']    = Auth::user()->id;
            $cleanTimeString                = preg_replace('/\s*\(.*\)$/', '', $request->join_date);
            $validatedData['join_date']     = Carbon::parse($cleanTimeString)->format('Y-m-d');

            $user = $this->createUser($request);
            if($user){
                $validatedData['user_id'] = $user->id;
            }
            // Store the company data in the database
            $customer = $this->customerRepository->create($validatedData);

            // Check if the company was successfully created
            if ($customer)
            { 
                //update customer count 
                $company->customer_count    = $company->customer_count + 1;
                $company->save();

                $customerData = $this->customerRepository->getById($customer->id);
                return sendSuccessResponse('Customer created successfully!', 201, $customerData);
            }
            else
            {
                // delete user if customer not created
                User::where('email', $request->customer_login_id)->delete();
                return sendErrorResponse('Customer not created!', 404);
            }
        }
        catch (Exception $e) {
            return sendErrorResponse($e->getMessage().'at line number '.$e->getLine(), 500);
        }
    }

    public function update(Request $request)
    {
        // Validate the request

        $validator = Validator::make($request->all(), [
            'company_id' => 'required',
            'customer_id' => 'required',
            'name'  => 'required',
            'mobile' => 'required|digits_between:10,15',
            'email'  => 'required|email',
            'join_date' => 'required',
            'aadhar_no' => 'nullable|string',
            'image' => 'nullable|string',
            'status' => 'required|string',
            'address' => 'nullable|string|max:500',
        ]);
        
        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            $company        = $this->companyRepository->find($request->company_id);
            if(!$company)
            {
                return sendErrorResponse('Company not found!', 404);
            }

            $customer         = $this->customerRepository->checkCustomerExist($request->company_id, $request->customer_id);
            if(!$customer)
            {
                return sendErrorResponse('Customer not found!', 404);
            }

            $customerUserId   = $customer->user_id;

            $checkUserMobile = User::where('id', '!=', $customerUserId)->where('mobile', $request->mobile)->count();
            
            if($checkUserMobile>0){
                return sendErrorResponse('Mobile already exists!', 409);
            }

            // Process the base64 images
            if($request->image!=''){
                $validatedData['image']     = $this->storeBase64Image($request->image, 'customer');
            }

            $cleanTimeString            = preg_replace('/\s*\(.*\)$/', '', $request->join_date);
            $validatedData['join_date'] = Carbon::parse($cleanTimeString)->format('Y-m-d');

            // update the customer data in the database
            $customer = $this->customerRepository->update($request->customer_id,$validatedData);

            // Check if the company was successfully created
            if ($customer)
            {   
                // update mobile number in user table
                User::where('id', $customerUserId)->update(['mobile' => $request->mobile]);

                $customerData = $this->customerRepository->getById($customer->id);
                return sendSuccessResponse('Customer Updated successfully!', 201, $customerData);
            }
            else
            {
                return sendErrorResponse('Customer not created!', 404);
            }
        }
        catch (Exception $e) {
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
            'name' => $request->input('name'),
            'user_type' => 3,  // 3 for customer
            'email' => $request->input('customer_login_id'),  // Unique identifier for user
            'password' => Hash::make($request->input('password')),  // Hash the password
            'password_hint' => $request->input('password'),
            'mobile' => $request->input('mobile'),
        ]);        
    }

    public function updateCustomerStatus(Request $request){

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required',
            'status' => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $customer = Customer::find($request->customer_id);
        if($customer)
        {
            $customer->status = $request->status;
            $customer->save();
            $customerData = $this->customerRepository->getById($customer->id);
            if($request->status=='active')
            {
                return sendSuccessResponse('Customer Activated successfully!',200,$customerData);
            }else{
                return sendSuccessResponse('Customer Inactived successfully!',200,$customerData);
            }
        }
        else
        {
            return sendErrorResponse('Customer not found!', 404);
        }
    }
}
