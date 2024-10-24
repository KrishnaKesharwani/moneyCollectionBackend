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
use App\Repositories\UserRepository;
use Carbon\Carbon;
use exception;
use Auth;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{


    protected $customerRepository;
    protected $companyRepository;
    protected $userRepository;

    public function __construct(
        CompanyRepository $companyRepository,
        customerRepository $customerRepository,
        userRepository $userRepository
        )
    {
        $this->companyRepository        = $companyRepository;
        $this->customerRepository       = $customerRepository;
        $this->userRepository           = $userRepository;
    }

    public function index(Request $request){
        $validator = Validator::make($request->all(), [
            'company_id' => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try{
            $status = $request->status ?? null;
            $customers = $this->customerRepository->getAllCustomers($request->company_id,$status);

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
            DB::beginTransaction();
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
                if($company->prefix)
                {
                    $companyprefix = $company->prefix;
                }
                
                $customer_no                = $companyprefix.'-'.$company->id.'-'.$company->customer_count + 1;
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

                DB::commit();

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
            DB::beginTransaction();
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
                User::where('id', $customerUserId)->update(['mobile' => $request->mobile,'status'=>$request->status]);
                DB::commit();
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

    public function importCustomers(Request $request)
    {
        // Validate the request for an array of customers

        $validator = Validator::make($request->all(), [
            'company_id' => 'required|integer|exists:companies,id',
            'customers' => 'required|array',
            'customers.*.name' => 'required|string',
            'customers.*.mobile' => 'required|digits_between:10,15',
            'customers.*.email' => 'required|email',
            'customers.*.join_date' => 'required',
            'customers.*.aadhar_no' => 'nullable|string',
            'customers.*.address' => 'nullable|string|max:500',
            'customers.*.customer_login_id' => 'required|email',
            'customers.*.password' => 'required|string|min:6',
            'customers.*.status' => 'required|string',
        ]);
    

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $customersData = $request->customers;

        // Extract customer_login_id and mobile from the post data to check for duplicates
        $loginIds = collect($customersData)->pluck('customer_login_id');
        $mobiles = collect($customersData)->pluck('mobile');

        // Check for duplicates in the post data
        if ($loginIds->duplicates()->isNotEmpty() || $mobiles->duplicates()->isNotEmpty()) {
            return sendErrorResponse('Duplicate customer_login_id or mobile found in the request data.', 409);
        }

        // Check for duplicates in the database
        $existingUsersEmails = User::whereIn('email', $loginIds)->get();
        $existingUsersMobiles = User::WhereIn('mobile', $mobiles)->get();

        if ($existingUsersEmails->isNotEmpty() || $existingUsersMobiles->isNotEmpty()) {

            $errors = [];
            if(!$existingUsersEmails->isEmpty()){
                $errors['emails'] = $existingUsersEmails->pluck('email')->toArray();
            }

            if(!$existingUsersMobiles->isEmpty()){
                $errors['mobiles'] = $existingUsersMobiles->pluck('mobile')->toArray();
            }

            return sendErrorResponse('Duplicate customer_login_id or mobile found in the existing records.', 409, $errors);
        }

        DB::beginTransaction();
        try {

            $company = $this->companyRepository->find($request->company_id);
            if (!$company) 
            {
                return sendErrorResponse('Company not found!', 404);
            }

            foreach ($customersData as $data) {
                // Find the company
                
                // Generate customer number
                $companyName            = $company->company_name;
                $companyPrefix          = explode(' ', $companyName)[0];
                $customer_no            = $companyPrefix . '-cus-' . $company->id . '-' . ($company->customer_count + 1);

                // Process images and other data
                //$data['image'] = $this->storeBase64Image($data['image'], 'customer');
                $data['customer_no']    = $customer_no;
                $data['company_id']     = $request->company_id;
                $data['created_by']     = Auth::user()->id;
                $cleanTimeString        = preg_replace('/\s*\(.*\)$/', '', $data['join_date']);
                $data['join_date']      = Carbon::parse($cleanTimeString)->format('Y-m-d');

                // Create user and customer records
                $user = $this->createUser(new Request($data));
                if ($user) {
                    $data['user_id'] = $user->id;
                }
                
                $customer = $this->customerRepository->create($data);

                // Increment the customer count
                $company->customer_count += 1;
                $company->save();
            }

            DB::commit();
            return sendSuccessResponse('Customers created successfully!', 201);
        } catch (Exception $e) {
            DB::rollBack();
            return sendErrorResponse('Failed to insert customers: ' . $e->getMessage(), 500);
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
            'status' => $request->input('status')
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
            DB::beginTransaction();
            $customer->status = $request->status;
            $customer->save();

            //update user status 
            $userId = $customer->user_id;
            $this->userRepository->update($userId,['status'=>$request->status]);

            DB::commit();

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
