<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function changePassword(Request $request)
    {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'previous_password' => 'required',
            'new_password'      => 'required|min:8|confirmed',
            'new_password_confirmation'  => 'required',
        ]);
        

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }
        

        $user = Auth::user();  // Get the currently authenticated user

        // Check if the previous password matches
        if (!Hash::check($request->previous_password, $user->password)) {
            return sendErrorResponse('The provided previous password does not match our records.', 422);
        }

        // Check if the new password is the same as the old one
        if (Hash::check($request->new_password, $user->password)) {
            return sendErrorResponse('The new password cannot be the same as the previous password.', 422);
        }

        // Hash and update the new password
        $user->password = Hash::make($request->new_password);
        $user->save();

        // Return success response
        return sendSuccessResponse('Password successfully updated.', 200);
    }


    public function updateLanguage(Request $request){
        $validator = Validator::make($request->all(), [
            'language' => 'required',
        ]);
        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }
        $user = Auth::user();
        $user->language = $request->language;
        if($user->save()){
            return sendSuccessResponse('Language successfully updated.', 200);
        }
        return sendErrorResponse('Something went wrong.', 500);
    }
}
