<?php

namespace App\Repositories;

use App\Models\MemberFinanceHistory;
use Illuminate\Support\Facades\DB;

class MemberFinanceHistoryRepository extends BaseRepository
{
    public function __construct(MemberFinanceHistory $memberFinanceHistory)
    {
        parent::__construct($memberFinanceHistory);
    }
    

    public function getCustomerCount($collectId){
        return $this->model->where('member_finance_id', $collectId)->whereIn('amount_by', ['loan', 'deposit'])->distinct('customer_id')->count();
    }

    /**
     * Get collection details
     *
     * @param int $collectId
     *
     * @return array
     */
    

     public function getCollectionDetails($collectId)
     {
         // Retrieve all necessary data in a single query
         $finance = DB::table('member_finance')->where('id', $collectId)->first();
         $records = $this->model
                         ->with('customer')
                         ->where('member_finance_id', $collectId)
                         ->whereIn('amount_by', ['loan', 'deposit', 'advance'])
                         ->get();
     
         // Initialize variables
         $advance = 0;
         $creditAmount = 0;
         $debitAmount = 0;
         $previousBalance = (float)$finance->previous_balance;
     
         // Calculate totals using collection methods
         foreach ($records as $record) {
             if ($record->amount_by === 'advance') {
                 $advance += $record->amount;
             } elseif (in_array($record->amount_by, ['loan', 'deposit'])) {
                 if ($record->amount_type === 'credit') {
                     $creditAmount += $record->amount;
                 } elseif ($record->amount_type === 'debit') {
                     $debitAmount += $record->amount;
                 }
             }
         }
     
         // Calculate total balance
         $totalBalance = ($advance + $previousBalance + $creditAmount) - $debitAmount;
     
         // Return results
         return [
             'advance' => $advance,
             'credit' => $creditAmount,
             'debit' => $debitAmount,
             'previous_balance' => $previousBalance,
             'total_balance' => $totalBalance,
             'details' => $records,

         ];
     }     
    // You can add any specific methods related to User here
}
