<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\CustomerRepository;
use App\Repositories\MemberRepository;
use App\Repositories\OfferRepository;
use App\Repositories\CustomerLoanRepository;
use App\Repositories\CustomerDepositRepository;
use App\Repositories\ReportBackupRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use exception;

class ReportController extends Controller
{
    protected $customerRepository;
    protected $memberRepository;
    protected $offerRepository;
    protected $customerLoanRepository;
    protected $customerDepositRepository;
    protected $reportBackupRepository;

    public function __construct(
        CustomerRepository $customerRepository,
        MemberRepository $memberRepository,
        OfferRepository $offerRepository,
        CustomerLoanRepository $customerLoanRepository,
        CustomerDepositRepository $customerDepositRepository,
        ReportBackupRepository $reportBackupRepository
    )
    {
        $this->customerRepository = $customerRepository;
        $this->memberRepository = $memberRepository;
        $this->offerRepository = $offerRepository;
        $this->customerLoanRepository = $customerLoanRepository;
        $this->customerDepositRepository = $customerDepositRepository;
        $this->reportBackupRepository = $reportBackupRepository;
    }

    public function index(Request $request){
        $validator = Validator::make($request->all(), [
            'company_id' => 'required|integer|exists:companies,id',
        ]);

        if ($validator->fails()) {
            return sendErrorResponse('Validation errors occurred.', 422, $validator->errors());
        }

        $reportType = reportTypes();
        $reportList = [];
        $row = 0;
        foreach ($reportType as $key => $value) {
            $reportList[$row]['type']               = $key;
            $reportList[$row]['name']               = $value;
            $reportList[$row]['active']             = 0;
            $reportList[$row]['inactive']           = 0;
            $reportList[$row]['total']              = 0;
            $reportList[$row]['last_backup_date']   = 0;
            if($key=='customer_list')
            {
                $reportList[$row]['active']             = $this->customerRepository->getCustomersCount($request->company_id, 'active');
                $reportList[$row]['inactive']           = $this->customerRepository->getCustomersCount($request->company_id, 'inactive');
                $reportList[$row]['total']              = $this->customerRepository->getCustomersCount($request->company_id, null);
                $reportList[$row]['last_backup_date']   = $this->reportBackupRepository->getLastBackupDate($request->company_id, 'customer_list');
            }

            if($key=='member_list')
            {
                $reportList[$row]['active']             = $this->memberRepository->getMembersCount($request->company_id, 'active');
                $reportList[$row]['inactive']           = $this->memberRepository->getMembersCount($request->company_id, 'inactive');
                $reportList[$row]['total']              = $this->memberRepository->getMembersCount($request->company_id, null);
                $reportList[$row]['last_backup_date']   = $this->reportBackupRepository->getLastBackupDate($request->company_id, $key);
            }

            if($key=='offer_list')
            {
                $reportList[$row]['active']             = $this->offerRepository->getOffersCount($request->company_id, 'active');
                $reportList[$row]['inactive']           = $this->offerRepository->getOffersCount($request->company_id, 'inactive');
                $reportList[$row]['total']              = $this->offerRepository->getOffersCount($request->company_id, null);
                $reportList[$row]['last_backup_date']   = $this->reportBackupRepository->getLastBackupDate($request->company_id, $key);
            }

            if($key=='loan_list'){
                $reportList[$row]['active']             = $this->customerLoanRepository->getCustomerLoansCount($request->company_id, 'active');
                $reportList[$row]['inactive']           = $this->customerLoanRepository->getCustomerLoansCount($request->company_id, 'inactive');
                $reportList[$row]['total']              = $this->customerLoanRepository->getCustomerLoansCount($request->company_id, null);
                $reportList[$row]['last_backup_date']   = $this->reportBackupRepository->getLastBackupDate($request->company_id, $key);
            }

            if($key=='deposit_list'){
                $reportList[$row]['active']             = $this->customerDepositRepository->getDepositsCount($request->company_id, 'active');
                $reportList[$row]['inactive']           = $this->customerDepositRepository->getDepositsCount($request->company_id, 'inactive');
                $reportList[$row]['total']              = $this->customerDepositRepository->getDepositsCount($request->company_id, null);
                $reportList[$row]['last_backup_date']   = $this->reportBackupRepository->getLastBackupDate($request->company_id, $key);
                       
            }   

            $row++;
        }

        return sendSuccessResponse($reportList, 200);
    }
}
