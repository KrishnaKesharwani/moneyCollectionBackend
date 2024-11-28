<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Company;
use App\Models\Member;
use App\Models\Customer;
use App\Repositories\CompanyPlanRepository;
use App\Models\Offer;
use Illuminate\Support\Facades\Validator;
use App\Repositories\UserRepository;


class LoginController extends Controller
{

    protected $userRepository;
    protected $companyPlanRepository;

    public function __construct(
        UserRepository $userRepository,
        companyPlanRepository $companyPlanRepository
        )
    {
        $this->userRepository           = $userRepository;
        $this->companyPlanRepository    = $companyPlanRepository;
    }


    /**
     * Handle a login request.
     */
    public function login(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        // Attempt to find the user
        $user = User::where('email', $request->email)->first();

        // Check if the user exists and if the password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return sendErrorResponse('Invalid credentials', 401);
        }

        if($user->status=='inactive'){
            return sendErrorResponse('Account is inactive now',401);
        }

        // Generate a new API token for the user
        $token = $user->createToken('API Token')->plainTextToken;

        $userData = User::where('id', $user->id)->first();
        if($userData){
            $userId     = $userData->id;
            $userType   = $userData->user_type;
            $userData['company']    = null;
            $userData['member']     = null;
            $userData['customer']   = null;
            $userData['offer']      = null;
            $userData['plan']       = null;
            if($userType==1){
                $company = Company::where('user_id', $userId)->first();
                if($company){
                    $userData['company'] = $company;
                    $companyPlan = $this->companyPlanRepository->getCompanyPlan($company->id);
                    if($companyPlan){
                        $userData['plan'] = $companyPlan;
                    }
                }
                
            }else if($userType==2){
                $member = Member::where('user_id', $userId)->first();
                if($member){
                    $userData['member'] = $member;
                    $userData['company'] = Company::where('id', $member->company_id)->first();
                    $userData['offer'] = Offer::where('default_offer',1)->where('company_id',$member->company_id)->first();
                }
            }elseif($userType==3){
                $customer = Customer::where('user_id', $userId)->first();
                if($customer){
                    $userData['customer'] = $customer;
                    $userData['company'] = Company::where('id', $customer->company_id)->first();
                    $userData['offer'] = Offer::where('default_offer',1)->where('company_id',$customer->company_id)->first();
                }
            }
        }

        // Return the token and user data
        return sendSuccessResponse('Login successful',200, $userData, $token);
    }

    /**
     * Handle a logout request.
     */
    public function logout(Request $request)
    {
        // Revoke the user's token
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
