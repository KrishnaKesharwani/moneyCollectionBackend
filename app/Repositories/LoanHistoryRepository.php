<?php

namespace App\Repositories;

use App\Models\LoanHistory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LoanHistoryRepository extends BaseRepository
{
    public function __construct(LoanHistory $loanHistory)
    {
        parent::__construct($loanHistory);
    }
    

    public function getTotalPaidAmount($loanId){
        return $this->model->where('loan_id', $loanId)->sum('amount');
    }

    public function getTotalPaidAmountByLoanIds($loanId){
        return $this->model->whereIn('loan_id', $loanId)->sum('amount');
    }


    public function getMaxLoanHistoryDate($loanId){
        $maxDate = $this->model->where('loan_id', $loanId)->max('receive_date');
        if($maxDate)
            return $maxDate;
        else
            return null;
    }

    public function getTodayCollection($memberId){
        $data = $this->model->with('loan.customer')->where('receiver_member_id', $memberId)->whereDate('receive_date', carbon::today())->get();
        return $data;
    }

    public function getLoanReceivedAmountByDate($companyId, $memberId, $date){
        $amount = DB::table('loan_history')
                    ->join('customer_loans', 'loan_history.loan_id', '=', 'customer_loans.id')
                    ->where('customer_loans.company_id', $companyId)
                    ->where('loan_history.receiver_member_id', $memberId)
                    ->whereDate('loan_history.receive_date', $date)
                    ->sum('loan_history.amount');
        return $amount;
    }

    public function getLoanReceivedAmountByloanIds($companyId,$loanIds,$date=null,$fromDate=null,$toDate=null){
        $amount = DB::table('loan_history')
                    ->join('customer_loans', 'loan_history.loan_id', '=', 'customer_loans.id')
                    ->where('customer_loans.company_id', $companyId)
                    ->whereIn('loan_history.loan_id', $loanIds);
        if($date!=null){
            $amount = $amount->whereDate('loan_history.receive_date', $date);
        }

        if($fromDate!=null && $toDate!=null){
            $amount = $amount->whereDate('loan_history.receive_date','>=',$fromDate)
                      ->whereDate('loan_history.receive_date','<=',$toDate);
        }
            $amount = $amount->sum('loan_history.amount');
        return $amount;
    }


    public function getLoanReceivedAmountByDatewise($companyId, $memberId, $fromDate, $toDate){
        $amount = DB::table('loan_history')
                    ->join('customer_loans', 'loan_history.loan_id', '=', 'customer_loans.id')
                    ->where('customer_loans.company_id', $companyId)
                    ->where('loan_history.receiver_member_id', $memberId)
                    ->whereDate('loan_history.receive_date','>=',$fromDate)
                    ->whereDate('loan_history.receive_date','<=',$toDate)
                    ->sum('loan_history.amount');
        return $amount;
    }


    public function getTotalPaidLoanAmount($companyId){
        $amount = DB::table('loan_history')
                    ->join('customer_loans', function ($join) use ($companyId) {
                        $join->on('customer_loans.id', '=', 'loan_history.loan_id')
                            ->where('customer_loans.company_id', $companyId)
                            ->where('customer_loans.status', 'active')
                            ->where('customer_loans.loan_status', 'paid');
                    })
                    ->sum('loan_history.amount');
        return $amount;
    }
    

    public function getAttendedCustomerIds($companyId, $memberId, $today){
        $customerIds = DB::table('loan_history')
                    ->join('customer_loans', function ($join) use ($companyId) {
                        $join->on('customer_loans.id', '=', 'loan_history.loan_id')
                            ->where('customer_loans.company_id', $companyId);
                    })
                    ->when($memberId, function ($query, $memberId) {
                        return $query->where('loan_history.receiver_member_id', $memberId);
                    })
                    ->whereDate('loan_history.receive_date','=',$today)
                    ->distinct('customer_loans.customer_id')
                    ->pluck('customer_loans.customer_id')->toArray();
        return $customerIds;
    }


    
    
    // You can add any specific methods related to User here
}
