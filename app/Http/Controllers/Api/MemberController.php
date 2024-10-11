<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Repositories\MemberRepository;
use App\Repositories\CompanyRepository;
use Carbon\Carbon;
use exception;

class MemberController extends Controller
{


    protected $memberRepository;
    protected $companyRepository;

    public function __construct(
        CompanyRepository $companyRepository,
        MemberRepository $memberRepository,
        )
    {
        $this->companyRepository        = $companyRepository;
        $this->memberRepository         = $memberRepository;
    }

    public function store(Request $request)
    {
        // Validate the request

        $validator = Validator::make($request->all(), [
            'company_id' => 'required',
            'name'  => 'required',
            'mobile' => 'required|digits_between:10,15',
            'email'  => 'required|email',
            'join_date' => 'required|date',
            'aadhar_no' => 'nullable|string',
            'image' => 'nullable|string',
            'status' => 'required|string',
            'address' => 'nullable|string|max:500',
            'member_login_id' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        $company = $this->companyRepository->find($request->company_id);
        $member_no = 0;
        if(!$company)
        {
            return sendErrorResponse('Company not found!', 404);
        }
        else
        {
            $companyName            = $company->company_name;
            $companyprefix          = explode(' ', $companyName)[0];
            $member_no              = $companyprefix.'-'.$company->id.'-'.$company->member_count + 1;
            $company->member_count  = $company->member_count + 1;
            $company->save();
        }

        $checkUser  = User::where('email', $request->member_login_id)->count();
        if($checkUser > 0){
            return sendErrorResponse('Email already exists!', 409, $checkUser);
        }

        //create user for member

        $user = $this->createUser($request);
        // Process the base64 images
        $validatedData['image']     = $this->storeBase64Image($request->image, 'member');
        $validatedData['user_id']   = $user->id;
        $validatedData['member_no'] = $member_no;

        // Store the company data in the database
        $member = $this->memberRepository->create($validatedData);

        // Check if the company was successfully created
        if ($member)
        {   
            return sendSuccessResponse('Member created successfully!', 201, $member);
        }
        else
        {
            return sendErrorResponse('Member not created!', 404);
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
            'user_type' => 2,  // 1 for company
            'email' => $request->input('member_login_id'),  // Unique identifier for user
            'password' => Hash::make($request->input('password')),  // Hash the password
        ]);        
    }

    public function updateMemberStatus(Request $request){

        $validator = Validator::make($request->all(), [
            'member_id' => 'required',
            'status' => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $member = $this->memberRepository->find($request->member_id);
        $member->update(['status' => $request->status]);
        if($member)
        {
            if($request->status=='active')
            {
                return sendSuccessResponse('Member Activated successfully!',200,$company);
            }else{
                return sendSuccessResponse('Member Inactived successfully!',200,$company);
            }
        }
        else
        {
            return sendErrorResponse('Member not found!', 404);
        }
    }
}
