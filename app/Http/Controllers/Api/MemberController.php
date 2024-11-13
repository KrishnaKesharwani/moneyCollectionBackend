<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use App\Models\User;
use App\Models\Member;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Repositories\MemberRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{


    protected $memberRepository;
    protected $companyRepository;
    protected $userRepository;

    public function __construct(
        CompanyRepository $companyRepository,
        MemberRepository $memberRepository,
        UserRepository $userRepository,
        )
    {
        $this->companyRepository        = $companyRepository;
        $this->memberRepository         = $memberRepository;
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
            $members = $this->memberRepository->getAllMembers($request->company_id,$status);

            if($members->isEmpty())
            {
                return sendErrorResponse('Members not found!', 404);
            }
            else
            {
                return sendSuccessResponse('Members found successfully!', 200, $members);
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
            'member_login_id' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();
        
        try {
            $company = $this->companyRepository->find($request->company_id);
            DB::beginTransaction();
            $member_no = 0;
            if(!$company)
            {
                return sendErrorResponse('Company not found!', 404);
            }
            else
            {
                $companyName            = $company->company_name;
                $companyprefix          = explode(' ', $companyName)[0];
                if($company->prefix!=null && $company->prefix!=''){
                    $companyprefix = $company->prefix;
                }
                $member_no              = $companyprefix.'-MEM-'.$company->id.'-'.$company->member_count + 1;
                $company->member_count  = $company->member_count + 1;
                $company->save();
            }

            $checkUser = User::where('email', $request->member_login_id)
                ->orWhere('mobile', $request->mobile)
                ->first();

            if ($checkUser)
            {
                if ($checkUser->email === $request->member_login_id) 
                {
                    return sendErrorResponse('Email already exists!', 409);
                }
                
                if ($checkUser->mobile == $request->mobile)
                {
                    return sendErrorResponse('Mobile already exists!', 409);
                }
            }


            //create user for member
            $user = $this->userRepository->create([
                'name' => $request->name,
                'user_type' => 2,  // 1 for company
                'email' => $request->member_login_id,  // Unique identifier for user
                'mobile' => $request->mobile,
                'password' => Hash::make($request->password),  // Hash the password
                'password_hint' => $request->password,
                'status' => $request->status,
            ]);

            // Process the base64 images
            $validatedData['image']     = $this->storeBase64Image($request->image, 'member');
            $validatedData['user_id']   = $user->id;
            $validatedData['member_no'] = $member_no;
            $cleanTimeString            = preg_replace('/\s*\(.*\)$/', '', $request->join_date);
            $validatedData['join_date'] = Carbon::parse($cleanTimeString)->format('Y-m-d');

            // Store the company data in the database
            $member = $this->memberRepository->create($validatedData);
            DB::commit();

            // Check if the company was successfully created
            if ($member)
            {   
                $memberData = $this->memberRepository->getById($member->id);
                return sendSuccessResponse('Member created successfully!', 201, $memberData);
            }
            else
            {
                return sendErrorResponse('Member not created!', 404);
            }
        }
        catch (Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    public function update(Request $request)
    {
        // Validate the request

        $validator = Validator::make($request->all(), [
            'company_id' => 'required',
            'member_id' => 'required',
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

            $member         = $this->memberRepository->checkMemberExist($request->company_id, $request->member_id);
            if(!$member)
            {
                return sendErrorResponse('Member not found!', 404);
            }

            $memberUserId   = $member->user_id;

            $checkUserMobile = User::where('id', '!=', $memberUserId)->where('mobile', $request->mobile)->count();
            
            if($checkUserMobile>0){
                return sendErrorResponse('Mobile already exists!', 409);
            }

            // Process the base64 images
            if($request->image!='' && $request->image!='null') {
                $validatedData['image']     = $this->storeBase64Image($request->image, 'member');
            }

            $cleanTimeString            = preg_replace('/\s*\(.*\)$/', '', $request->join_date);
            $validatedData['join_date'] = Carbon::parse($cleanTimeString)->format('Y-m-d');

            DB::beginTransaction();
            // Update the company data in the database
            $member = $this->memberRepository->update($request->member_id,$validatedData);

            // Check if the company was successfully created
            if ($member)
            {   
                // update mobile number in user table
                User::where('id', $memberUserId)->update(['mobile' => $request->mobile,'status'=>$request->status]);
                DB::commit();
                $memberData = $this->memberRepository->getById($member->id);
                return sendSuccessResponse('Member Updated successfully!', 201, $memberData);
            }
            else
            {
                return sendErrorResponse('Member not created!', 404);
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
            'user_type' => 2,  // 1 for company
            'email' => $request->input('member_login_id'),  // Unique identifier for user
            'password' => Hash::make($request->input('password')),  // Hash the password
            'password_hint' => $request->input('password'),
            'mobile' => $request->input('mobile'),
            'status' => $request->input('status',)
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

        $member = Member::find($request->member_id);
        if($member)
        {
            DB::beginTransaction();
            
            $member->status = $request->status;
            $member->save();

            //update user status
            $userId = $member->user_id;
            $this->userRepository->update($userId,['status'=>$request->status]);

            DB::commit();
            $membarData = $this->memberRepository->getById($member->id);
            if($request->status=='active')
            {
                return sendSuccessResponse('Member Activated successfully!',200,$membarData);
            }else{
                return sendSuccessResponse('Member Inactived successfully!',200,$membarData);
            }
        }
        else
        {
            return sendErrorResponse('Member not found!', 404);
        }
    }

    public function downloadMembers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|exists:companies,id',
            'status' => 'required',
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        if ($request->status == 'all') {
            $status = null;
        } else {
            $status = $request->status;
        }

        $companyId = $request->company_id;

        // Create new Spreadsheet object
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set the header
        $sheet->setCellValue('A1', 'Serial No');
        $sheet->setCellValue('B1', 'Member Number');
        $sheet->setCellValue('C1', 'Name');
        $sheet->setCellValue('D1', 'Email');
        $sheet->setCellValue('E1', 'Mobile');
        $sheet->setCellValue('F1', 'Address');
        $sheet->setCellValue('G1', 'Join Date');
        $sheet->setCellValue('H1', 'Balance');
        $sheet->setCellValue('I1', 'Status');


        // Retrieve your data from the database (example: getting users)
        $customers = $this->memberRepository->getAllMembers($companyId, $status);

        // Populate the spreadsheet with data
        $row = 2; // Start from row 2 to avoid overwriting headers
        foreach ($customers as $customer) {
            $sheet->setCellValue('A' . $row, $row-1);
            $sheet->setCellValue('B' . $row, $customer->member_no);
            $sheet->setCellValue('c' . $row, $customer->name);
            $sheet->setCellValue('d' . $row, $customer->email);
            $sheet->setCellValue('e' . $row, $customer->mobile);
            $sheet->setCellValue('f' . $row, $customer->address);
            $sheet->setCellValue('g' . $row, $customer->join_date);
            $sheet->setCellValue('h' . $row, $customer->balance);
            $sheet->setCellValue('I' . $row, $customer->status);
            $row++;
        }

        // Set up the response for download
        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output'); // Stream the file directly to the response
        });

        // Set headers for file download
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="members.xlsx"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        // Return the response

        if($response){
            return $response;
        }else{
            return sendErrorResponse('Members data not downloaded!', 422);
        }
    }
}
