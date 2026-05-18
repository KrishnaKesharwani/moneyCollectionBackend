<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\User;
use App\Models\Member;
use App\Models\Vc;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Repositories\MemberRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\UserRepository;
use App\Repositories\ReportBackupRepository;
use App\Repositories\DepositHistoryRepository;
use App\Repositories\CustomerLoanRepository;
use App\Repositories\CustomerDepositRepository;
use App\Repositories\LoanHistoryRepository;
use App\Repositories\MemberFinanceRepository;
use App\Repositories\VcRepository;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VcController extends Controller
{
    protected $memberRepository;
    protected $companyRepository;
    protected $userRepository;
    protected $reportBackupRepository;
    protected $depositHistoryRepository;
    protected $loanHistoryRepository;
    protected $customerLoanRepository;
    protected $customerDepositRepository;
    protected $memberFinanceRepository;
    protected $vcRepository;

    public function __construct(
        CompanyRepository $companyRepository,
        MemberRepository $memberRepository,
        UserRepository $userRepository,
        ReportBackupRepository $reportBackupRepository,
        DepositHistoryRepository $depositHistoryRepository,
        LoanHistoryRepository $loanHistoryRepository,
        CustomerLoanRepository $customerLoanRepository,
        CustomerDepositRepository $customerDepositRepository,
        MemberFinanceRepository $memberFinanceRepository,
        VcRepository $vcRepository
    ) {
        $this->companyRepository            = $companyRepository;
        $this->memberRepository             = $memberRepository;
        $this->vcRepository                 = $vcRepository;
        $this->userRepository               = $userRepository;
        $this->reportBackupRepository       = $reportBackupRepository;
        $this->depositHistoryRepository     = $depositHistoryRepository;
        $this->loanHistoryRepository        = $loanHistoryRepository;
        $this->customerLoanRepository       = $customerLoanRepository;
        $this->customerDepositRepository    = $customerDepositRepository;
        $this->memberFinanceRepository      = $memberFinanceRepository;
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|integer',
            'status'     => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        try {

            $companyId = $request->company_id;
            $status    = $request->status ?? null;

            /* =========================
         * SUMMARY COUNTS
         * ========================= */

            $totalVcs = DB::table('vcs')
                ->where('company_id', $companyId)
                ->when($status, fn($q) => $q->where('status', $status))
                ->count();

            $totalMembers = DB::table('vcs')
                ->where('status', 'active')
                ->sum('total_member');

            $totalUnpaidMembers = DB::table('vcs_installement')
                ->sum('unpaid_member');

            /* =========================
         * FETCH VC DATA
         * ========================= */

            $vcs = DB::table('vcs')
                ->where('company_id', $companyId)
                ->when($status, fn($q) => $q->where('status', $status))
                ->get();

            if ($vcs->isEmpty()) {
                return sendErrorResponse('VC not found!', 200);
            }

            foreach ($vcs as $vc) {

                /* =========================
             * VC ACTIVE CUSTOMERS
             * ========================= */
                $customers = DB::table('vc_customers as vm')
                    ->join('customers as u', 'u.id', '=', 'vm.customer_id')
                    ->where('vm.vc_id', $vc->id)
                    ->where('vm.status', 'active')
                    ->select(
                        'u.id',
                        'u.name',
                        'u.customer_no',
                        'u.mobile',
                        'u.status',
                        'u.image'
                    )
                    ->get();

                /* =========================
             * INSTALMENTS
             * ========================= */
                $instalments = DB::table('vcs_installement')
                    ->where('vc_id', $vc->id)
                    ->orderBy('id', 'desc')
                    ->get();

                $instalmentList = [];

                foreach ($instalments as $inst) {

                    /* =========================
                 * PAID USERS THIS INSTALMENT
                 * ========================= */
                    $paidUsers = DB::table('vcs_installement_customers as vim')
                        ->join('customers as u', 'u.id', '=', 'vim.customer_id')
                        ->where('vim.vc_id', $vc->id)
                        ->where('vim.instalment_id', $inst->id)
                        ->select(
                            'u.id',
                            'u.name',
                            'u.mobile',
                            'vim.payment_mode',
                            'vim.image_url',
                            'vim.details',
                            'vim.amount'
                        )
                        ->get();

                    /* ====================================================
                 * PAY & GO SPECIAL LOGIC
                 * If user paid once → remove from ALL unpaid lists
                 * ==================================================== */
                    if ($vc->type === 'pay_go') {

                        // All customers who paid any instalment
                        $paidOnce = DB::table('vcs_installement_customers')
                            ->where('vc_id', $vc->id)
                            ->pluck('customer_id')
                            ->toArray();

                        // Unpaid = active users NOT in paidOnce
                        $unpaidUsers = DB::table('vc_customers as vm')
                            ->join('customers as u', 'u.id', '=', 'vm.customer_id')
                            ->where('vm.vc_id', $vc->id)
                            ->whereNotIn('vm.customer_id', $paidOnce)
                            ->select('u.id', 'u.name', 'u.mobile')
                            ->get()
                            ->map(function ($user) {
                                return [
                                    'id'           => $user->id,
                                    'name'         => $user->name,
                                    'mobile'       => $user->mobile,
                                    'payment_mode' => null,
                                    'details'      => null,
                                    'image_url'    => null,
                                ];
                            });
                    } else {

                        /* ==========================================
                     * FIXED & BOLI normal unpaid behaviour
                     * ========================================== */
                        $unpaidUsers = DB::table('vc_customers as vm')
                            ->join('customers as u', 'u.id', '=', 'vm.customer_id')
                            ->where('vm.vc_id', $vc->id)
                            ->whereNotIn('vm.customer_id', function ($q) use ($inst) {
                                $q->select('customer_id')
                                    ->from('vcs_installement_customers')
                                    ->where('instalment_id', $inst->id);
                            })
                            ->select('u.id', 'u.name', 'u.mobile')
                            ->get()
                            ->map(function ($user) {
                                return [
                                    'id'           => $user->id,
                                    'name'         => $user->name,
                                    'mobile'       => $user->mobile,
                                    'payment_mode' => null,
                                    'details'      => null,
                                    'image_url'    => null,
                                ];
                            });
                    }

                    $instalmentList[] = [
                        'instalment_id' => $inst->id,
                        'amount'        => $inst->amount,
                        'status'        => $inst->status,
                        'start_date'    => $inst->start_date,
                        'end_date'      => $inst->end_date,
                        'paid'          => $paidUsers,
                        'unpaid'        => $unpaidUsers,
                    ];
                }

                $vc->customers   = $customers;
                $vc->instalments = $instalmentList;
            }

            return response()->json([
                'success' => true,
                'message' => 'VC data fetched successfully!',
                'summary' => [
                    'total_vcs'             => $totalVcs,
                    'total_members'         => (int) $totalMembers,
                    'total_unpaid_members'  => (int) $totalUnpaidMembers,
                ],
                'data' => $vcs
            ], 200);
        } catch (\Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    // public function index_past07012025(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'company_id' => 'required|integer',
    //         'status'     => 'nullable|string'
    //     ]);

    //     if ($validator->fails()) {
    //         return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
    //     }

    //     try {

    //         $companyId = $request->company_id;
    //         $status    = $request->status ?? null;

    //         /* =========================
    //      * SUMMARY COUNTS
    //      * ========================= */

    //         // Total VCs
    //         $totalVcs = DB::table('vcs')
    //             ->where('company_id', $companyId)
    //             ->when($status, fn($q) => $q->where('status', $status))
    //             ->count();

    //         // Total active members across all VCs
    //         // $totalMembers = DB::table('vc_customers')
    //         //     ->where('company_id', $companyId)
    //         //     ->where('status', 'active')
    //         //     // ->distinct('customer_id')
    //         //     ->count('customer_id');

    //         $totalMembers = DB::table('vcs')
    //             ->where('status', 'active')
    //             ->sum('total_member');

    //         // Total paid members (across all instalments)
    //         // $totalPaidMembers = DB::table('vcs_installement_customers')
    //         //     ->where('company_id', $companyId)
    //         //     ->distinct('customer_id')
    //         //     ->count('customer_id');

    //         // // Total pending members
    //         // $totalPendingMembers = max(0, $totalMembers - $totalPaidMembers);

    //         $totalUnpaidMembers = DB::table('vcs_installement')
    //             ->sum('unpaid_member');

    //         /* =========================
    //      * FETCH VC DATA
    //      * ========================= */

    //         $vcs = DB::table('vcs')
    //             ->where('company_id', $companyId)
    //             ->when($status, fn($q) => $q->where('status', $status))
    //             ->get();

    //         if ($vcs->isEmpty()) {
    //             return sendErrorResponse('VC not found!', 200);
    //         }

    //         foreach ($vcs as $vc) {

    //             // VC Customers
    //             $customers = DB::table('vc_customers as vm')
    //                 ->join('customers as u', 'u.id', '=', 'vm.customer_id')
    //                 ->where('vm.vc_id', $vc->id)
    //                 ->where('vm.status', 'active')
    //                 ->select(
    //                     'u.id',
    //                     'u.name',
    //                     'u.customer_no',
    //                     'u.mobile',
    //                     'u.status',
    //                     'u.image'
    //                 )
    //                 ->get();

    //             // Instalments
    //             $instalments = DB::table('vcs_installement')
    //                 ->where('vc_id', $vc->id)
    //                 ->orderBy('id', 'desc')
    //                 ->get();

    //             $instalmentList = [];

    //             foreach ($instalments as $inst) {

    //                 // Paid users
    //                 $paidUsers = DB::table('vcs_installement_customers as vim')
    //                     ->join('customers as u', 'u.id', '=', 'vim.customer_id')
    //                     ->where('vim.vc_id', $vc->id)
    //                     ->where('vim.instalment_id', $inst->id)
    //                     ->select(
    //                         'u.id',
    //                         'u.name',
    //                         'u.mobile',
    //                         'vim.payment_mode',
    //                         'vim.image_url',
    //                         'vim.details',
    //                         'vim.amount'
    //                     )
    //                     ->get();

    //                 // Unpaid users
    //                 $unpaidUsers = DB::table('vc_customers as vm')
    //                     ->join('customers as u', 'u.id', '=', 'vm.customer_id')
    //                     ->where('vm.vc_id', $vc->id)
    //                     ->whereNotIn('vm.customer_id', function ($q) use ($inst) {
    //                         $q->select('customer_id')
    //                             ->from('vcs_installement_customers')
    //                             ->where('instalment_id', $inst->id);
    //                     })
    //                     ->select('u.id', 'u.name', 'u.mobile')
    //                     ->get()
    //                     ->map(function ($user) {
    //                         return [
    //                             'id'           => $user->id,
    //                             'name'         => $user->name,
    //                             'mobile'       => $user->mobile,
    //                             'payment_mode' => null,
    //                             'details'      => null,
    //                             'image_url'    => null,
    //                         ];
    //                     });

    //                 $instalmentList[] = [
    //                     'instalment_id' => $inst->id,
    //                     'amount'        => $inst->amount,
    //                     'status'        => $inst->status,
    //                     'start_date'    => $inst->start_date,
    //                     'end_date'      => $inst->end_date,
    //                     'paid'          => $paidUsers,
    //                     'unpaid'        => $unpaidUsers,
    //                 ];

    //                 $vc_id = $vc->id;
    //                 $paidCustomers = DB::table('vc_customers')
    //                     ->join('vcs_payment', 'vcs_payment.customer_id', '=', 'vc_customers.customer_id')
    //                     ->join('customers', 'customers.id', '=', 'vc_customers.customer_id')
    //                     ->where('vc_customers.vc_id', $vc_id)
    //                     ->where('vcs_payment.status', 1)
    //                     ->select(
    //                         'customers.id',
    //                         'customers.customer_no',
    //                         'customers.name',
    //                         'customers.mobile',
    //                         'customers.email',
    //                         'vcs_payment.paid_amount',
    //                         'vcs_payment.paid_date',
    //                         'vcs_payment.amount_type',
    //                         'vcs_payment.status'
    //                     )
    //                     ->groupBy(
    //                         'customers.id',
    //                         'customers.customer_no',
    //                         'customers.name',
    //                         'customers.mobile',
    //                         'customers.email',
    //                         'vcs_payment.paid_amount',
    //                         'vcs_payment.paid_date',
    //                         'vcs_payment.amount_type',
    //                         'vcs_payment.status'
    //                     )
    //                     ->get();

    //                 // UNPAID CUSTOMERS
    //                 $unpaidCustomers = DB::table('vc_customers')
    //                     ->join('customers', 'customers.id', '=', 'vc_customers.customer_id')
    //                     ->where('vc_customers.vc_id', $vc_id)
    //                     ->whereNotIn('customers.id', function ($query) use ($vc_id) {
    //                         $query->select('customer_id')
    //                             ->from('vcs_payment')
    //                             ->where('vc_id', $vc_id)
    //                             ->where('status', 1);
    //                     })
    //                     ->select(
    //                         'customers.id',
    //                         'customers.customer_no',
    //                         'customers.name',
    //                         'customers.mobile',
    //                         'customers.email'
    //                     )
    //                     ->get();
    //             }

    //             $vc->customers   = $customers;
    //             $vc->instalments = $instalmentList;
    //         }

    //         /* =========================
    //      * FINAL RESPONSE
    //      * ========================= */

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'VC data fetched successfully!',
    //             'summary' => [
    //                 'total_vcs'             => $totalVcs,
    //                 'total_members'         => (int) $totalMembers,
    //                 'total_unpaid_members'  => (int) $totalUnpaidMembers,
    //             ],
    //             'data' => $vcs,
    //             'paidmember' => $paidCustomers,
    //             'unpaidmember' => $unpaidCustomers
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return sendErrorResponse($e->getMessage(), 500);
    //     }
    // }

    public function store(Request $request)
    {
        // Validate the request

        $validator = Validator::make($request->all(), [
            'company_id' => 'required',
            'vc_name'  => 'required',
            'type' => 'required',
            'total_month'  => 'required',
            'total_member' => 'required',
            'final_amount' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'details' => 'nullable|string',
            'instalment_amount' => 'nullable|string',
            'vc_image' => 'nullable|string',
        ]);


        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }
        $validatedData = $request->all();
        try {
            $companyId = $request->company_id;
            $company = $this->companyRepository->find($companyId);
            DB::beginTransaction();
            $cleanStartDate                             = preg_replace('/\s*\(.*\)$/', '', $request->start_date);
            $cleanEndDate                               = preg_replace('/\s*\(.*\)$/', '', $request->end_date);
            $validatedData['start_date']                = Carbon::parse($cleanStartDate)->format('Y-m-d');
            $validatedData['end_date']                  = Carbon::parse($cleanEndDate)->format('Y-m-d');
            $validatedData['vc_image']            = storeBase64Image($request->vc_image, 'vcs');


            $vc = $this->vcRepository->create($validatedData);
            DB::commit();

            // Check if the company was successfully created
            if ($vc) {
                $vcData = $this->vcRepository->getById($vc->id);
                return sendSuccessResponse('VC created successfully!', 201, $vcData);
            } else {
                return sendErrorResponse('VC not created!', 500);
            }
        } catch (Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    //  public function vcpaymentcreate(Request $request)
    // {
    //     echo "asdf";
    //     die;
    //     // Validate the request

    //     $validator = Validator::make($request->all(), [
    //         'company_id' => 'required',
    //         'customer_id'  => 'required',
    //         'vc_id' => 'required',
    //         'paid_amount'  => 'required',
    //         'paid_date' => 'required',
    //         'amount_type' => 'required',
    //         'details' => 'required',
    //         'document1' => 'required',
    //         'document2' => 'nullable|string',
    //         'status' => 'required',                  
    //     ]);     


    //     if ($validator->fails()) {
    //         return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
    //     }
    //     $validatedData = $request->all();        
    //     try {
    //         $companyId = $request->company_id;
    //         $company = $this->companyRepository->find($companyId);
    //         DB::beginTransaction(); 
    //         $cleanStartDate                             = preg_replace('/\s*\(.*\)$/', '', $request->start_date);
    //         $cleanEndDate                               = preg_replace('/\s*\(.*\)$/', '', $request->end_date);
    //         $validatedData['start_date']                = Carbon::parse($cleanStartDate)->format('d-m-Y');
    //         $validatedData['end_date']                  = Carbon::parse($cleanEndDate)->format('d-m-Y');
    //         $validatedData['vc_image']            = storeBase64Image($request->vc_image, 'vcs');


    //         $vc = $this->vcRepository->create($validatedData);
    //         DB::commit();

    //         // Check if the company was successfully created
    //         if ($vc)
    //         {   
    //             $vcData = $this->vcRepository->getById($vc->id);
    //             return sendSuccessResponse('VC created successfully!', 201, $vcData);
    //         }
    //         else
    //         {
    //             return sendErrorResponse('VC not created!', 500);
    //         }
    //     }
    //     catch (Exception $e) {
    //         return sendErrorResponse($e->getMessage(), 500);
    //     }
    // }

    public function update(Request $request)
    {
        // Validate the request


        $validator = Validator::make($request->all(), [
            'vc_id' => 'required',
            'company_id' => 'required',
            'vc_name'  => 'required',
            'type' => 'required',
            'total_month'  => 'required',
            'total_member' => 'required',
            'final_amount' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'details' => 'nullable|string',
            'instalment_amount' => 'nullable|string',
            'vc_image' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $validatedData = $request->all();

        try {
            $companyId      = $request->company_id;
            $company        = $this->companyRepository->find($request->company_id);
            if (!$company) {
                return sendErrorResponse('Company not found!', 404);
            }

            $vc         = $this->vcRepository->checkVcExist($request->company_id, $request->vc_id);
            if (!$vc) {
                return sendErrorResponse('Vc not found!', 404);
            }

            // Process the base64 images
            // if($request->vc_image!='' && $request->vc_image!='null') {
            //     $validatedData['vc_image']     = $this->storeBase64Image($request->vc_image, 'vc');
            // }       

            DB::beginTransaction();
            // Update the company data in the database
            $cleanStartDate                             = preg_replace('/\s*\(.*\)$/', '', $request->start_date);
            $cleanEndDate                               = preg_replace('/\s*\(.*\)$/', '', $request->end_date);
            $validatedData['start_date']                = Carbon::parse($cleanStartDate)->format('Y-m-d');
            $validatedData['end_date']                  = Carbon::parse($cleanEndDate)->format('Y-m-d');
            $vc = $this->vcRepository->update($request->vc_id, $validatedData);
            if (isset($request->vc_image) && $request->vc_image != '') {
                $validatedData['vc_image']   = storeBase64Image($request->vc_image, 'vcs');
            } else {
                unset($validatedData['vc_image']);
            }
            // $validatedData['vc_image'] = $this->storeBase64Image($request->vc_image, 'vcs');
            // Check if the company was successfully created
            if ($vc) {
                // update mobile number in user table

                DB::commit();
                //  $vcData = $this->vcRepository->getById($vc->id);
                return sendSuccessResponse('Vc Updated successfully!', 201, $vc);
            } else {
                return sendErrorResponse('VC not created!', 500);
            }
        } catch (Exception $e) {
            Log::error('VC Update Error: ' . $e->getMessage());
            return sendErrorResponse($e->getMessage(), 500);
            // return sendErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Decode and store base64 image.
     *
     * @param string|null $base64Image
     * @param string $directory
     * @return string|null
     */
    // private function storeBase64Image2($base64Image, $directory)
    // {
    //     if (!$base64Image) {
    //         return null; // Return null if no image is provided
    //     }

    //     // Extract the mime type and the Base64 data
    //     $imageParts = explode(';base64,', $base64Image);

    //     // Get the image extension from the mime type
    //     $imageTypeAux = explode('image/', $imageParts[0]);
    //     $imageType = $imageTypeAux[1]; // e.g., 'jpeg', 'png', 'gif'

    //     // Decode the Base64 string into binary data
    //     $imageData = base64_decode($imageParts[1]);

    //     // Generate a unique file name for the image
    //     $fileName = Str::random(10) . '.' . $imageType;

    //     // Store the image in the public storage folder (or any custom directory)
    //     $path = Storage::put("public/{$directory}/{$fileName}", $imageData);

    //     // Return the stored path or URL to save in the database
    //     return $directory . '/' . $fileName;
    // }

    private function storeBase64Image($base64Image, $directory)
    {
        if (!$base64Image) {
            return null;
        }

        $imageParts = explode(';base64,', $base64Image);
        $imageTypeAux = explode('image/', $imageParts[0]);
        $imageType = $imageTypeAux[1] ?? 'png';

        $imageData = base64_decode($imageParts[1]);
        $fileName = Str::random(10) . '.' . $imageType;

        // Store image
        Storage::put("public/{$directory}/{$fileName}", $imageData);

        // ✅ RETURN FULL PUBLIC URL
        return url("storage/app/public/{$directory}/{$fileName}");
        // return asset("storage/{$directory}/{$fileName}");
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

    public function updateVcStatus(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'vc_id' => 'required',
            'status' => 'required',
        ]);


        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $vc = Vc::find($request->vc_id);
        if ($vc) {
            DB::beginTransaction();

            $vc->status = $request->status;
            $vc->save();

            //update user status
            $userId = $vc->user_id;
            $this->userRepository->update($userId, ['status' => $request->status]);

            DB::commit();
            $vcData = $this->vcRepository->getById($vc->id);
            if ($request->status == 'active') {
                return sendSuccessResponse('Vc Activated successfully!', 200, $vcData);
            } else {
                return sendSuccessResponse('Vc Inactived successfully!', 200, $vcData);
            }
        } else {
            return sendErrorResponse('Vc not found!', 404);
        }
    }
    // update vc status 
    public function updateVcStatusByCompany(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vc_id'      => 'required|integer',
            'company_id' => 'required|integer',
            'status'     => 'required|in:active,inactive,completed,deleted',
            'reason'     => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            // Find VC with vc_id + company_id
            $vc = Vc::where('id', $request->vc_id)
                ->where('company_id', $request->company_id)
                ->first();

            if (!$vc) {
                return sendErrorResponse('VC not found or company mismatch', 404);
            }

            // Update fields
            $vc->status = $request->status;
            $vc->reason = $request->reason; // nullable, safe
            $vc->save();
            DB::commit();
            return sendSuccessResponse(
                'VC status updated successfully',
                200,
                [
                    'id'         => $vc->id,
                    'company_id' => $vc->company_id,
                    'status'     => $vc->status,
                    'reason'     => $vc->reason
                ]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    public function addVcInstalment(Request $request)
    {
        $isUpdate = $request->filled('instalment_id');

        $rules = [
            'vc_id'         => 'required|integer',
            'company_id'    => 'required|integer',
            'amount'        => $isUpdate ? 'nullable|numeric|min:0.01' : 'required|numeric|min:0.01',
            'start_date'    => $isUpdate ? 'nullable' : 'required',
            'end_date'      => $isUpdate ? 'nullable' : 'required',
            'instalment_id' => 'nullable|integer',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return sendErrorResponse('Validation error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            /** ===========================
             * Validate VC
             * =========================== */
            $vc = Vc::where('id', $request->vc_id)
                ->where('company_id', $request->company_id)
                ->first();

            if (!$vc) {
                return sendErrorResponse('VC not found or company mismatch', 404);
            }

            /** ===========================
             * DATE HANDLING (CRITICAL FIX)
             * =========================== */
            $startDate = null;
            if ($request->start_date) {
                $cleanStart = preg_replace('/\s*\(.*\)$/', '', $request->start_date);
                $startDate = Carbon::parse($cleanStart)->format('Y-m-d');
            }

            $endDate = null;
            if ($request->end_date) {
                $cleanEnd = preg_replace('/\s*\(.*\)$/', '', $request->end_date);
                $endDate = Carbon::parse($cleanEnd)->format('Y-m-d');
            }

            /** ===========================
             * UPDATE
             * =========================== */
            if ($isUpdate) {

                $instalment = DB::table('vcs_installement')
                    ->where('id', $request->instalment_id)
                    ->where('vc_id', $request->vc_id)
                    ->where('company_id', $request->company_id)
                    ->first();

                if (!$instalment) {
                    return sendErrorResponse('Instalment not found', 404);
                }

                DB::table('vcs_installement')
                    ->where('id', $request->instalment_id)
                    ->update([
                        'amount'     => $request->amount     ?? $instalment->amount,
                        'start_date' => $startDate           ?? $instalment->start_date,
                        'end_date'   => $endDate             ?? $instalment->end_date,
                        'updated_at' => now(),
                    ]);

                DB::commit();

                return sendSuccessResponse(
                    'VC instalment updated successfully',
                    200,
                    [
                        'instalment_id' => $request->instalment_id,
                        'vc_id'         => $request->vc_id,
                        'company_id'    => $request->company_id,
                    ]
                );
            }

            /** ===========================
             * ADD NEW
             * =========================== */
            $totalMembers = (int) $vc->total_member;

            $instalmentId = DB::table('vcs_installement')->insertGetId([
                'vc_id'         => $request->vc_id,
                'company_id'    => $request->company_id,
                'amount'        => $request->amount,
                'start_date'    => $startDate, // ✅ FIXED
                'end_date'      => $endDate,   // ✅ FIXED
                'status'        => 'pending',
                'paid_member'   => 0,
                'unpaid_member' => $totalMembers,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            DB::commit();

            return sendSuccessResponse(
                'VC instalment added successfully',
                201,
                [
                    'instalment_id' => $instalmentId,
                    'vc_id'         => $request->vc_id,
                    'company_id'    => $request->company_id,
                    'paid_member'   => 0,
                    'unpaid_member' => $totalMembers,
                    'status'        => 'pending',
                ]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return sendErrorResponse($e->getMessage(), 500);
        }
    }
    // public function addVcInstalment_past(Request $request)
    // {
    //     // Check if this is UPDATE or ADD
    //     $isUpdate = $request->filled('instalment_id');

    //     // Validation rules
    //     $rules = [
    //         'vc_id'         => 'required|integer',
    //         'company_id'    => 'required|integer',
    //         'amount'        => $isUpdate ? 'nullable|numeric|min:0.01' : 'required|numeric|min:0.01',
    //         'start_date'    => $isUpdate ? 'nullable|date' : 'required|date',
    //         'end_date'      => $isUpdate
    //             ? 'nullable|date|after_or_equal:start_date'
    //             : 'required|date|after_or_equal:start_date',
    //         'instalment_id' => 'nullable|integer',
    //     ];

    //     $validator = Validator::make($request->all(), $rules);

    //     if ($validator->fails()) {
    //         return sendErrorResponse('Validation error', 422, $validator->errors());
    //     }

    //     try {
    //         DB::beginTransaction();

    //         // Validate VC belongs to company
    //         $vc = Vc::where('id', $request->vc_id)
    //             ->where('company_id', $request->company_id)
    //             ->first();

    //         if (!$vc) {
    //             return sendErrorResponse('VC not found or company mismatch', 404);
    //         }


    //         if ($isUpdate) {

    //             $instalment = DB::table('vcs_installement')
    //                 ->where('id', $request->instalment_id)
    //                 ->where('vc_id', $request->vc_id)
    //                 ->where('company_id', $request->company_id)
    //                 ->first();

    //             if (!$instalment) {
    //                 return sendErrorResponse('Instalment not found', 404);
    //             }

    //             DB::table('vcs_installement')
    //                 ->where('id', $request->instalment_id)
    //                 ->update([
    //                     'amount'     => $request->amount     ?? $instalment->amount,
    //                     'start_date' => $request->start_date ?? $instalment->start_date,
    //                     'end_date'   => $request->end_date   ?? $instalment->end_date,
    //                     'updated_at' => now(),
    //                 ]);

    //             DB::commit();

    //             return sendSuccessResponse(
    //                 'VC instalment updated successfully',
    //                 200,
    //                 [
    //                     'instalment_id' => $request->instalment_id,
    //                     'vc_id'         => $request->vc_id,
    //                     'company_id'    => $request->company_id,
    //                 ]
    //             );
    //         }



    //         // Fetch total members from VC
    //         $totalMembers = (int) $vc->total_member;

    //         $instalmentId = DB::table('vcs_installement')->insertGetId([
    //             'vc_id'         => $request->vc_id,
    //             'company_id'    => $request->company_id,
    //             'amount'        => $request->amount,
    //             'start_date'    => $request->start_date,
    //             'end_date'      => $request->end_date,
    //             'status'        => 'pending',
    //             'paid_member'   => 0,
    //             'unpaid_member' => $totalMembers,
    //             'created_at'    => now(),
    //             'updated_at'    => now(),
    //         ]);

    //         DB::commit();

    //         return sendSuccessResponse(
    //             'VC instalment added successfully',
    //             201,
    //             [
    //                 'instalment_id' => $instalmentId,
    //                 'vc_id'         => $request->vc_id,
    //                 'company_id'    => $request->company_id,
    //                 'paid_member'   => 0,
    //                 'unpaid_member' => $totalMembers,
    //                 'status'        => 'pending',
    //             ]
    //         );
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return sendErrorResponse($e->getMessage(), 500);
    //     }
    // }
    // add member instalment 

    public function addInstalmentCustomer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vc_id'         => 'required|integer',
            'instalment_id' => 'required|integer',
            'company_id'    => 'required|integer',
            'customer_id'     => 'required|integer',
            'amount'        => 'required|numeric|min:0.01',

            // New fields
            'payment_mode'  => 'required|in:cash,online',
            'image_url'     => 'required_if:payment_mode,online|nullable|string',
            'details'       => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            // Validate instalment belongs to VC & company
            $instalment = DB::table('vcs_installement')
                ->where('id', $request->instalment_id)
                ->where('vc_id', $request->vc_id)
                ->where('company_id', $request->company_id)
                ->lockForUpdate()
                ->first();

            if (!$instalment) {
                return sendErrorResponse('Instalment not found or mismatch', 404);
            }

            // Prevent over-payment
            // if ($instalment->unpaid_member <= 0) {
            //     return sendErrorResponse('All members already paid', 400);
            // }

            // Prevent duplicate payment by same member
            $alreadyPaid = DB::table('vcs_installement_customers')
                ->where('instalment_id', $request->instalment_id)
                ->where('customer_id', $request->customer_id)
                ->exists();

            if ($alreadyPaid) {
                return sendErrorResponse('Member has already paid this instalment', 409);
            }
            $storedImagePath = null;
            if (
                $request->payment_mode === 'online' &&
                $request->image_url &&
                str_starts_with($request->image_url, 'data:image')
            ) {
                $storedImagePath = $this->storeBase64Image($request->image_url, 'vcs');
            }
            // Insert member payment
            DB::table('vcs_installement_customers')->insert([
                'vc_id'         => $request->vc_id,
                'instalment_id' => $request->instalment_id,
                'company_id'    => $request->company_id,
                'customer_id'     => $request->customer_id,
                'amount'        => $request->amount,

                // New fields
                'payment_mode'  => $request->payment_mode,
                // 'image_url'     => $request->payment_mode === 'online' ? $request->image_url : null,
                'image_url' => $storedImagePath,
                'details'       => $request->details,
                'created_at'    => now(),
            ]);

            // Update member counts
            $newPaid   = $instalment->paid_member + 1;
            $newUnpaid = $instalment->unpaid_member - 1;

            // Decide status
            $newStatus = ($newUnpaid === 0) ? 'paid' : $instalment->status;

            DB::table('vcs_installement')
                ->where('id', $request->instalment_id)
                ->update([
                    'paid_member'   => $newPaid,
                    'unpaid_member' => $newUnpaid,
                    'status'        => $newStatus,
                    'updated_at'    => now(),
                ]);

            DB::commit();

            return sendSuccessResponse(
                'Instalment payment added successfully',
                201,
                [
                    'instalment_id' => $request->instalment_id,
                    'customer_id'     => $request->customer_id,
                    'payment_mode'  => $request->payment_mode,
                    'paid_member'   => $newPaid,
                    'unpaid_member' => $newUnpaid,
                    'status'        => $newStatus,
                ]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return sendErrorResponse($e->getMessage(), 500);
        }
    }
    // public function addCustomerToVc(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'vc_id'       => 'required|integer',
    //         'company_id'  => 'required|integer',
    //         'customer_id' => 'required|integer',
    //         'status'      => 'nullable|in:active,inactive,removed',
    //     ]);

    //     if ($validator->fails()) {
    //         return sendErrorResponse('Validation error', 422, $validator->errors());
    //     }

    //     try {
    //         DB::beginTransaction();


    //         $vc = DB::table('vcs')
    //             ->where('id', $request->vc_id)
    //             ->where('company_id', $request->company_id)
    //             ->first();

    //         if (!$vc) {
    //             return sendErrorResponse('VC not found or company mismatch', 404);
    //         }


    //         $customerExists = DB::table('customers')
    //             ->where('id', $request->customer_id)
    //             ->exists();

    //         if (!$customerExists) {
    //             return sendErrorResponse('Customer not found', 404);
    //         }


    //         $alreadyAdded = DB::table('vc_customers')
    //             ->where('vc_id', $request->vc_id)
    //             ->where('customer_id', $request->customer_id)
    //             ->exists();

    //         if ($alreadyAdded) {
    //             return sendErrorResponse('Customer already added to this VC', 409);
    //         }


    //         DB::table('vc_customers')->insert([
    //             'vc_id'       => $request->vc_id,
    //             'customer_id' => $request->customer_id,
    //             'company_id'  => $request->company_id,
    //             'status'      => $request->status ?? 'active',
    //             'created_at'  => now(),
    //             'updated_at'  => now(),
    //         ]);

    //         DB::commit();

    //         return sendSuccessResponse(
    //             'Customer added to VC successfully',
    //             201,
    //             [
    //                 'vc_id'       => $request->vc_id,
    //                 'customer_id' => $request->customer_id,
    //                 'status'      => $request->status ?? 'active',
    //             ]
    //         );
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return sendErrorResponse($e->getMessage(), 500);
    //     }
    // }

    // public function addCustomerToVc(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'vc_id'       => 'required|integer',
    //         'company_id'  => 'required|integer',
    //         'customer_id' => 'required|integer',
    //     ]);

    //     if ($validator->fails()) {
    //         return sendErrorResponse('Validation error', 422, $validator->errors());
    //     }

    //     try {
    //         DB::beginTransaction();

    //         // Validate VC exists
    //         $vc = DB::table('vcs')
    //             ->where('id', $request->vc_id)
    //             ->where('company_id', $request->company_id)
    //             ->first();

    //         if (!$vc) {
    //             return sendErrorResponse('VC not found or belongs to another company', 404);
    //         }

    //         // Validate customer exists
    //         $customerExists = DB::table('customers')
    //             ->where('id', $request->customer_id)
    //             ->exists();

    //         if (!$customerExists) {
    //             return sendErrorResponse('Customer not found', 404);
    //         }

    //         $alreadyAdded = DB::table('vc_customers')
    //             ->where('vc_id', $request->vc_id)
    //             ->where('customer_id', $request->customer_id)
    //             ->exists();

    //         if ($alreadyAdded) {
    //             return sendErrorResponse('Customer already added to this VC', 409);
    //         }

    //         // Count currently active customers
    //         $activeCount = DB::table('vc_customers')
    //             ->where('vc_id', $request->vc_id)
    //             ->where('company_id', $request->company_id)
    //             ->where('status', 'active')
    //             ->count();

    //         if ($activeCount >= $vc->total_member) {
    //             return sendErrorResponse(
    //                 'VC member limit reached. Remove a customer first.',
    //                 422
    //             );
    //         }

    //         // Check record exists even if removed
    //         $existing = DB::table('vc_customers')
    //             ->where('vc_id', $request->vc_id)
    //             ->where('customer_id', $request->customer_id)
    //             ->first();

    //         if ($existing) {
    //             // if user was removed → restore
    //             DB::table('vc_customers')
    //                 ->where('vc_id', $request->vc_id)
    //                 ->where('customer_id', $request->customer_id)
    //                 ->update([
    //                     'status'     => 'active',
    //                     'updated_at' => now(),
    //                 ]);
    //         } else {
    //             // insert new
    //             DB::table('vc_customers')->insert([
    //                 'vc_id'       => $request->vc_id,
    //                 'customer_id' => $request->customer_id,
    //                 'company_id'  => $request->company_id,
    //                 'status'      => 'active',
    //                 'created_at'  => now(),
    //                 'updated_at'  => now(),
    //             ]);
    //         }

    //         DB::commit();

    //         return sendSuccessResponse(
    //             'Customer added to VC successfully',
    //             201,
    //             [
    //                 'vc_id'       => $request->vc_id,
    //                 'customer_id' => $request->customer_id,
    //                 'status'      => 'active',
    //             ]
    //         );
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return sendErrorResponse($e->getMessage(), 500);
    //     }
    // }

    public function addCustomerToVc(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vc_id'       => 'required|integer',
            'company_id'  => 'required|integer',
            'customer_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            // VC exists?
            $vc = DB::table('vcs')
                ->where('id', $request->vc_id)
                ->where('company_id', $request->company_id)
                ->first();

            if (!$vc) {
                return sendErrorResponse('VC not found or belongs to another company', 404);
            }

            // Customer exists?
            $customerExists = DB::table('customers')
                ->where('id', $request->customer_id)
                ->exists();

            if (!$customerExists) {
                return sendErrorResponse('Customer not found', 404);
            }

            // Check if record exists
            $existing = DB::table('vc_customers')
                ->where('vc_id', $request->vc_id)
                ->where('customer_id', $request->customer_id)
                ->first();

            // If exists and status is ACTIVE → reject
            if ($existing && $existing->status === 'active') {
                return sendErrorResponse('Customer already in this VC', 409);
            }

            // Count active members only
            $activeCount = DB::table('vc_customers')
                ->where('vc_id', $request->vc_id)
                ->where('company_id', $request->company_id)
                ->where('status', 'active')
                ->count();

            if ($activeCount >= $vc->total_member) {
                return sendErrorResponse(
                    'VC member limit reached. Remove a customer first.',
                    422
                );
            }

            // If exists and REMOVED → reactivate
            if ($existing && $existing->status === 'removed') {
                DB::table('vc_customers')
                    ->where('vc_id', $request->vc_id)
                    ->where('customer_id', $request->customer_id)
                    ->update([
                        'status'     => 'active',
                        'updated_at' => now(),
                    ]);
            } else {
                // Insert new
                DB::table('vc_customers')->insert([
                    'vc_id'       => $request->vc_id,
                    'customer_id' => $request->customer_id,
                    'company_id'  => $request->company_id,
                    'status'      => 'active',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            DB::commit();

            return sendSuccessResponse(
                'Customer added to VC successfully',
                201,
                [
                    'vc_id'       => $request->vc_id,
                    'customer_id' => $request->customer_id,
                    'status'      => 'active',
                ]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    public function removeCustomerFromVc(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vc_id'       => 'required|integer',
            'company_id'  => 'required|integer',
            'customer_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation error', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            $exists = DB::table('vc_customers')
                ->where('vc_id', $request->vc_id)
                ->where('company_id', $request->company_id)
                ->where('customer_id', $request->customer_id)
                ->where('status', 'active')
                ->exists();

            if (!$exists) {
                return sendErrorResponse('Customer not assigned or already removed', 404);
            }

            DB::table('vc_customers')
                ->where('vc_id', $request->vc_id)
                ->where('company_id', $request->company_id)
                ->where('customer_id', $request->customer_id)
                ->update([
                    'status'     => 'removed',
                    'updated_at' => now(),
                ]);

            DB::commit();

            return sendSuccessResponse(
                'Customer removed from VC successfully',
                200,
                [
                    'vc_id'       => $request->vc_id,
                    'customer_id' => $request->customer_id,
                    'status'      => 'removed',
                ]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    // CUSTOMER-WISE VC DETAILS
    // public function customerVcList(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'company_id'  => 'required|integer',
    //         'customer_id' => 'required|integer',
    //     ]);

    //     if ($validator->fails()) {
    //         return sendErrorResponse('Validation error', 422, $validator->errors());
    //     }

    //     try {
    //         $companyId  = $request->company_id;
    //         $customerId = $request->customer_id;

    //         /** =========================
    //          * Fetch VCs where customer joined
    //          * ========================= */
    //         $vcs = DB::table('vc_customers as vcus')
    //             ->join('vcs', 'vcs.id', '=', 'vcus.vc_id')
    //             ->where('vcus.company_id', $companyId)
    //             ->where('vcus.customer_id', $customerId)
    //             ->where('vcus.status', 'active')
    //             ->select(
    //                 'vcs.id as vc_id',
    //                 'vcs.vc_name',
    //                 'vcs.type',
    //                 'vcs.final_amount',
    //                 'vcs.start_date',
    //                 'vcs.end_date'
    //             )
    //             ->get();

    //         if ($vcs->isEmpty()) {
    //             return sendSuccessResponse('No VC found for this customer', 200, []);
    //         }

    //         /** =========================
    //          * Loop VC wise
    //          * ========================= */
    //         foreach ($vcs as $vc) {

    //             /** =========================
    //              * Instalments of VC
    //              * ========================= */
    //             $instalments = DB::table('vcs_installement')
    //                 ->where('vc_id', $vc->vc_id)
    //                 ->orderBy('id', 'asc')
    //                 ->get();

    //             $instalmentData = [];

    //             foreach ($instalments as $index => $inst) {

    //                 // Check if customer paid this instalment
    //                 $payment = DB::table('vcs_installement_customers')
    //                     ->where('vc_id', $vc->vc_id)
    //                     ->where('instalment_id', $inst->id)
    //                     ->where('customer_id', $customerId)
    //                     ->first();

    //                 $instalmentData[] = [
    //                     'month'        => ($index + 1) . ' Month',
    //                     'end_date'     => $inst->end_date,
    //                     'payment_date' => $payment ? $payment->created_at : null,
    //                     'status'       => $payment ? 'Paid' : 'Pending',
    //                     'amount'       => $payment ? $payment->amount : $inst->amount,
    //                     'profit'       => $payment ? ($payment->amount - $inst->amount) : 0,
    //                 ];
    //             }

    //             /** =========================
    //              * Received payments by customer
    //              * ========================= */
    //             $receivedPayments = DB::table('vcs_installement_customers')
    //                 ->where('vc_id', $vc->vc_id)
    //                 ->where('customer_id', $customerId)
    //                 ->select(
    //                     'amount',
    //                     'created_at as date',
    //                     'payment_mode as type',
    //                     'details',
    //                     'image_url'
    //                 )
    //                 ->orderBy('created_at', 'desc')
    //                 ->get();

    //             $vc->instalments = $instalmentData;
    //             $vc->received_details = $receivedPayments;
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Customer VC list fetched successfully',
    //             'data'    => $vcs
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return sendErrorResponse($e->getMessage(), 500);
    //     }
    // }

    public function customerVcList_past(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id'  => 'required|integer',
            'customer_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation error', 422, $validator->errors());
        }

        try {
            $companyId  = $request->company_id;
            $customerId = $request->customer_id;

            /** =========================
             * Fetch VCs where customer joined
             * ========================= */
            $vcs = DB::table('vc_customers as vcus')
                ->join('vcs', 'vcs.id', '=', 'vcus.vc_id')
                ->where('vcus.company_id', $companyId)
                ->where('vcus.customer_id', $customerId)
                ->where('vcus.status', 'active')
                ->select(
                    'vcs.id as vc_id',
                    'vcs.vc_name',
                    'vcs.type',
                    'vcs.final_amount',
                    'vcs.start_date',
                    'vcs.end_date'
                )
                ->get();

            if ($vcs->isEmpty()) {
                return sendSuccessResponse('No VC found for this customer', 200, []);
            }

            /** =========================
             * Loop VC wise
             * ========================= */
            foreach ($vcs as $vc) {

                /** =========================
                 * Instalments
                 * ========================= */
                $instalments = DB::table('vcs_installement')
                    ->where('vc_id', $vc->vc_id)
                    ->orderBy('id', 'asc')
                    ->get();

                $instalmentData  = [];
                $totalProfit     = 0;
                $totalInstalment = $instalments->count();

                foreach ($instalments as $index => $inst) {

                    $payment = DB::table('vcs_installement_customers')
                        ->where('vc_id', $vc->vc_id)
                        ->where('instalment_id', $inst->id)
                        ->where('customer_id', $customerId)
                        ->first();

                    $profit = $payment ? ($payment->amount - $inst->amount) : 0;

                    if ($profit > 0) {
                        $totalProfit += $profit;
                    }

                    $instalmentData[] = [
                        'month'        => ($index + 1) . ' Month',
                        'end_date'     => $inst->end_date,
                        'payment_date' => $payment ? $payment->created_at : null,
                        'status'       => $payment ? 'Paid' : 'Pending',
                        'amount'       => $payment ? $payment->amount : $inst->amount,
                        'profit'       => $profit,
                    ];
                }

                /** =========================
                 * Received Payments
                 * ========================= */
                $receivedPayments = DB::table('vcs_installement_customers')
                    ->where('vc_id', $vc->vc_id)
                    ->where('customer_id', $customerId)
                    ->select(
                        'amount',
                        'created_at as date',
                        'payment_mode as type',
                        'details',
                        'image_url'
                    )
                    ->orderBy('created_at', 'desc')
                    ->get();

                /** =========================
                 * Attach computed values
                 * ========================= */
                $vc->total_instalment  = $totalInstalment;
                $vc->total_profit     = $totalProfit;
                $vc->instalments      = $instalmentData;
                $vc->received_details = $receivedPayments;
            }

            return response()->json([
                'success' => true,
                'message' => 'Customer VC list fetched successfully',
                'data'    => $vcs
            ], 200);
        } catch (\Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }
    public function customerVcList_Last(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id'  => 'required|integer',
            'customer_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation error', 422, $validator->errors());
        }

        try {
            $companyId  = $request->company_id;
            $customerId = $request->customer_id;

            $vcs = DB::table('vc_customers as vcus')
                ->join('vcs', 'vcs.id', '=', 'vcus.vc_id')
                ->where('vcus.company_id', $companyId)
                ->where('vcus.customer_id', $customerId)
                ->where('vcus.status', 'active')
                ->select(
                    'vcs.id as vc_id',
                    'vcs.vc_name',
                    'vcs.type',
                    'vcs.final_amount',
                    'vcs.start_date',
                    'vcs.end_date',
                    'vcs.instalment_amount'
                )
                ->get();

            if ($vcs->isEmpty()) {
                return sendSuccessResponse('No VC found for this customer', 200, []);
            }

            foreach ($vcs as $vc) {

                $instalments = DB::table('vcs_installement')
                    ->where('vc_id', $vc->vc_id)
                    ->orderBy('id', 'asc')
                    ->get();

                $instalmentData   = [];
                $totalInstalment  = 0;
                $totalProfit      = 0;

                foreach ($instalments as $index => $inst) {

                    // SUM instalment amounts
                    $totalInstalment += (float) $inst->amount;

                    // PROFIT PER INSTALMENT
                    $profit = (float) $vc->instalment_amount - (float) $inst->amount;

                    // SUM profit
                    $totalProfit += $profit;

                    $instalmentData[] = [
                        'month'             => ($index + 1) . ' Month',
                        'amount'            => (float) $inst->amount,
                        'instalment_amount' => (float) $vc->instalment_amount,
                        'profit'            => $profit,
                        'end_date'          => $inst->end_date,
                    ];
                }
                /** =========================
                 * Received Payments
                 * ========================= */
                $receivedPayments = DB::table('vcs_installement_customers')
                    ->where('vc_id', $vc->vc_id)
                    ->where('customer_id', $customerId)
                    ->select(
                        'amount',
                        'created_at as date',
                        'payment_mode as type',
                        'details',
                        'image_url'
                    )
                    ->orderBy('created_at', 'desc')
                    ->get();

                /** =========================
                 * Attach computed values
                 * ========================= */

                $vc->total_instalment = $totalInstalment; // ✅ SUM of amount column
                $vc->total_profit     = $totalProfit;     // ✅ SUM of profit column
                $vc->instalments      = $instalmentData;
                $vc->received_details = $receivedPayments;
            }


            return response()->json([
                'success' => true,
                'message' => 'Customer VC list fetched successfully',
                'data'    => $vcs
            ], 200);
        } catch (\Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }

    public function customerVcList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id'  => 'required|integer',
            'customer_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation error', 422, $validator->errors());
        }

        try {
            $companyId  = $request->company_id;
            $customerId = $request->customer_id;

            /** =========================
             * Fetch VCs where customer joined
             * ========================= */
            $vcs = DB::table('vc_customers as vcus')
                ->join('vcs', 'vcs.id', '=', 'vcus.vc_id')
                ->where('vcus.company_id', $companyId)
                ->where('vcus.customer_id', $customerId)
                ->where('vcus.status', 'active')
                ->select(
                    'vcs.id as vc_id',
                    'vcs.vc_name',
                    'vcs.type',
                    'vcs.final_amount',
                    'vcs.start_date',
                    'vcs.end_date',
                    'vcs.instalment_amount'
                )
                ->get();

            if ($vcs->isEmpty()) {
                return sendSuccessResponse('No VC found for this customer', 200, []);
            }

            /** =========================
             * Loop VC wise
             * ========================= */
            foreach ($vcs as $vc) {

                $instalments = DB::table('vcs_installement')
                    ->where('vc_id', $vc->vc_id)
                    ->orderBy('id', 'asc')
                    ->get();

                $instalmentData  = [];
                $totalInstalment = 0;
                $totalProfit     = 0;

                foreach ($instalments as $index => $inst) {

                    // Check payment for this instalment by customer
                    $payment = DB::table('vcs_installement_customers')
                        ->where('vc_id', $vc->vc_id)
                        ->where('instalment_id', $inst->id)
                        ->where('customer_id', $customerId)
                        ->first();

                    // A. Total Instalment = SUM(amount)
                    $totalInstalment += (float) $inst->amount;

                    // B. Profit = instalment_amount - amount
                    $profit = (float) $vc->instalment_amount - (float) $inst->amount;

                    // C. Total Profit = SUM(profit)
                    $totalProfit += $profit;

                    $instalmentData[] = [
                        'month'             => ($index + 1) . ' Month',
                        'amount'            => (float) $inst->amount,
                        'instalment_amount' => (float) $vc->instalment_amount,
                        'profit'            => $profit,
                        'status'            => $payment ? 'Paid' : 'Pending',          // ✅ ADDED
                        'payment_date'      => $payment ? $payment->created_at : null, // ✅ ADDED
                        'end_date'          => $inst->end_date,
                    ];
                }

                /** =========================
                 * Received Payments (Customer wise)
                 * ========================= */
                $receivedPayments = DB::table('vcs_installement_customers')
                    ->where('vc_id', $vc->vc_id)
                    ->where('customer_id', $customerId)
                    ->select(
                        'amount',
                        'created_at as date',
                        'payment_mode as type',
                        'details',
                        'image_url'
                    )
                    ->orderBy('created_at', 'desc')
                    ->get();

                /** =========================
                 * Attach Final Values
                 * ========================= */
                $vc->total_instalment  = $totalInstalment; // ✅ SUM of amount column
                $vc->total_profit     = $totalProfit;     // ✅ SUM of profit column
                $vc->instalments      = $instalmentData;
                $vc->received_details = $receivedPayments;
            }

            return response()->json([
                'success' => true,
                'message' => 'Customer VC list fetched successfully',
                'data'    => $vcs
            ], 200);
        } catch (\Exception $e) {
            return sendErrorResponse($e->getMessage(), 500);
        }
    }
}
