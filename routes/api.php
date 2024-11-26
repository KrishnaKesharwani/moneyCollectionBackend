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
use App\Http\Controllers\Api\CustomerDepositController;
use App\Http\Controllers\Api\LoanHistoryController;
use App\Http\Controllers\Api\DepositHistoryController;
use App\Http\Controllers\Api\MemberFinanceController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\FixedDepositController;
use App\Http\Controllers\Api\FixedDepositHistoryController;
use App\Http\Controllers\Api\ReportController;

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

        //memberFinance
        Route::post('pay-advance-to-member', [MemberFinanceController::class, 'store']);
        Route::post('get-collections', [MemberFinanceController::class, 'getCollections']);
        Route::post('get-collection-details', [MemberFinanceController::class, 'collectionDetails']);
        Route::post('pay-collection', [MemberFinanceController::class, 'payCollection']);

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
        Route::post('collectmoney',[LoanHistoryController::class,'store']);
        Route::post('today-collection',[LoanHistoryController::class,'getTodayCollection']);
        Route::post('customer-loan-history', [CustomerLoanController::class, 'loanHistory']);
        Route::post('change-loan-member', [CustomerLoanController::class, 'changeLoanMember']);
        Route::post('remove-loan-member', [CustomerLoanController::class, 'removeLoanMember']);
        Route::post('unassigned-loan', [CustomerLoanController::class, 'unassignedLoans']);
        Route::put('update-loan-status', [CustomerLoanController::class, 'updateLoanStatus']);
        Route::post('completed-loan-list', [CustomerLoanController::class, 'LoanListByStatus']);
        Route::post('loan-status-graph', [CustomerLoanController::class, 'dashboardLoanStatus']);
        Route::post('member-received-amount-line-graph', [CustomerLoanController::class, 'calculateReceivedAmountForMemberLineGraph']);
        Route::post('customer-loan-status-graph', [CustomerLoanController::class, 'customerLoanStatusGraph']);
        //customerdeposit
        Route::post('create-customer-deposit', [CustomerDepositController::class, 'store']);
        Route::post('company-customer-deposits', [CustomerDepositController::class, 'index']);
        Route::post('collect-deposit-money', [DepositHistoryController::class, 'store']);
        Route::post('customer-deposit-history', [CustomerDepositController::class, 'depositHistory']);
        Route::post('change-deposit-member', [CustomerDepositController::class, 'changeDepositMember']);
        Route::post('company-deposit-status-graph', [CustomerDepositController::class, 'calculateDepositofLast6Months']);
        Route::put('update-deposit-status', [CustomerDepositController::class, 'updateDepositStatus']);

        //deposit request
        Route::post('create-deposit-request', [CustomerDepositController::class, 'storeRequest']);
        Route::put('update-deposit-request-status', [CustomerDepositController::class, 'updateDepositRequestStatus']);
        Route::post('deposit-request-list', [CustomerDepositController::class, 'depositRequestList']);
        Route::post('customer-request-list', [CustomerDepositController::class, 'depositLoanRequestList']);

        //offers
        Route::post('create-offer', [OfferController::class, 'store']);
        Route::post('update-offer', [OfferController::class, 'update']);
        Route::post('offers', [OfferController::class, 'index']);
        Route::put('update-offer-status',[OfferController::class,'updateOfferStatus']);
        Route::put('update-default-offer',[OfferController::class,'updateDefaultOffer']);
        //delete offer
        Route::delete('delete-offer/{id}', [OfferController::class, 'destroy']);

        //fixed deposit
        Route::post('create-fixed-deposit', [FixedDepositController::class, 'store']);
        Route::post('update-fixed-deposit', [FixedDepositController::class, 'update']);
        Route::post('fixed-deposits', [FixedDepositController::class, 'index']);
        Route::post('pay-fixed-deposit-money', [FixedDepositHistoryController::class, 'store']);
        Route::put('update-fixed-deposit-status', [FixedDepositController::class, 'updateFixedDepositStatus']);
        Route::put('update-fd-status', [FixedDepositController::class, 'updateStatus']);
        Route::delete('delete-fixed-deposit/{id}', [FixedDepositController::class, 'destroy']);
        //Route::post('fixed-deposit-history', [CustomerDepositController::class, 'fixedDepositHistory']);

        //report
        Route::post('backup-list', [ReportController::class, 'index']);
        Route::post('download-customers', [CustomerController::class, 'downloadCustomers']);
        Route::post('download-members', [MemberController::class, 'downloadMembers']);
        Route::post('download-offers', [OfferController::class, 'downloadOffers']);
        Route::post('download-loans', [CustomerLoanController::class, 'downloadLoanList']);
        Route::post('download-deposits', [CustomerDepositController::class, 'downloadDepositList']);
        Route::post('download-loan-history', [CustomerLoanController::class, 'downloadLoanHistory']);
        //change password
        Route::post('changepassword', [UserController::class, 'changePassword']);
    });
});

// Logout Route (requires authentication)
Route::middleware('auth:sanctum')->post('logout', [LoginController::class, 'logout']);




// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
