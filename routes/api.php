<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CompanyPlanController;
use App\Http\Controllers\Api\CompanyPlanHistoryController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerLoanController;
use App\Http\Controllers\Api\LoanHistoryController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
// Login Route


Route::middleware('api')->group(function () {
    Route::post('login', [LoginController::class, 'login']);
    // other API routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('createcompany', [CompanyController::class, 'store']);
        Route::post('updatecompany', [CompanyController::class, 'update']);
        Route::any('companies', [CompanyController::class, 'index']);
        Route::post('plandetails', [CompanyPlanController::class, 'planHistory']);
        Route::get('companydashboard',[CompanyController::class, 'companyDashboard']);
        Route::put('updatecompanystatus', [CompanyController::class, 'updateCompanyStatus']);
        Route::post('createplanhistory', [CompanyPlanHistoryController::class, 'store']);
        Route::post('createplan', [CompanyPlanController::class, 'store']);

        //Member
        Route::post('createmember', [MemberController::class, 'store']);
        Route::post('updatemember', [MemberController::class, 'update']);
        Route::post('members', [MemberController::class, 'index']);
        Route::put('updatememberstatus', [MemberController::class, 'updateMemberStatus']);

        //customer
        Route::post('createcustomer', [CustomerController::class, 'store']);
        Route::post('updatecustomer', [CustomerController::class, 'update']);
        Route::post('customers', [CustomerController::class, 'index']);
        Route::put('updatecustomerstatus', [CustomerController::class, 'updateCustomerStatus']);
        Route::post('importcustomers', [CustomerController::class, 'importCustomers']);

        //customerLoan

        Route::post('createcustomerloan', [CustomerLoanController::class, 'store']);
        Route::post('companycustomerloans', [CustomerLoanController::class, 'index']);
        Route::post('loanrequest',[CustomerLoanController::class,'loanRequest']);
        Route::post('updateappliedloan',[CustomerLoanController::class,'updateappliedloan']);
        Route::Post('collectmoney',[LoanHistoryController::class,'store']);

        //change password
        Route::post('changepassword', [UserController::class, 'changePassword']);
    });
});

// Logout Route (requires authentication)
Route::middleware('auth:sanctum')->post('logout', [LoginController::class, 'logout']);




// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
