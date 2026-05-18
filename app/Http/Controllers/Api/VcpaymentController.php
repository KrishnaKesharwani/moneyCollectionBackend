<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vcpayment;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class VcpaymentController extends Controller
{
    /**
     * Store New Record
     */
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'company_id'   => 'required|integer',
            'customer_id'  => 'required|integer',
            'vc_id'        => 'required|integer',
            'paid_amount'  => 'required|numeric',
            'paid_date'    => 'required|date',
            'amount_type'  => 'required|string',
            'status'       => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // File Upload (Optional)
        // $doc1 = $request->hasFile('document1')
        //     ? $request->file('document1')->store('payments')
        //     : $request->document1;

        // $doc2 = $request->hasFile('document2')
        //     ? $request->file('document2')->store('payments')
        //     : $request->document2;

        $doc1 = null;
        if ($request->document1 && str_starts_with($request->document1, 'data:image')) {
            $doc1 = $this->storeBase64Image($request->document1, 'payments');
        }

        $doc2 = null;
        if ($request->document2 && str_starts_with($request->document2, 'data:image')) {
            $doc2 = $this->storeBase64Image($request->document2, 'payments');
        }
        $cleanPaidDate = preg_replace('/\s*\(.*\)$/', '', $request->paid_date);
        $paidDate = Carbon::parse($cleanPaidDate)->format('Y-m-d');

        $payment = Vcpayment::create([
            'company_id'   => $request->company_id,
            'customer_id'  => $request->customer_id,
            'vc_id'        => $request->vc_id,
            'paid_amount'  => $request->paid_amount,
            'paid_date'    => $paidDate,
            'amount_type'  => $request->amount_type,
            'details'      => $request->details,
            'document1'    => $doc1,
            'document2'    => $doc2,
            'status'       => $request->status
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Payment saved successfully',
            'data'    => $payment
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'           => 'required|integer|exists:vcpayments,id',
            'company_id'   => 'required|integer',
            'customer_id'  => 'required|integer',
            'vc_id'        => 'required|integer',
            'paid_amount'  => 'required|numeric',
            'paid_date'    => 'required',
            'amount_type'  => 'required|string',
            'status'       => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $payment = Vcpayment::findOrFail($request->id);

        /** ===========================
         * Format Paid Date
         * =========================== */
        $cleanPaidDate = preg_replace('/\s*\(.*\)$/', '', $request->paid_date);
        $paidDate = Carbon::parse($cleanPaidDate)->format('Y-m-d');

        /** ===========================
         * Base64 Image Upload
         * =========================== */
        $doc1 = $payment->document1;
        if ($request->document1 && str_starts_with($request->document1, 'data:image')) {
            $doc1 = $this->storeBase64Image($request->document1, 'payments');
        }

        $doc2 = $payment->document2;
        if ($request->document2 && str_starts_with($request->document2, 'data:image')) {
            $doc2 = $this->storeBase64Image($request->document2, 'payments');
        }

        /** ===========================
         * Update Payment
         * =========================== */
        $payment->update([
            'company_id'   => $request->company_id,
            'customer_id'  => $request->customer_id,
            'vc_id'        => $request->vc_id,
            'paid_amount'  => $request->paid_amount,
            'paid_date'    => $paidDate,
            'amount_type'  => $request->amount_type,
            'details'      => $request->details,
            'document1'    => $doc1,
            'document2'    => $doc2,
            'status'       => $request->status
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Payment updated successfully',
            'data'    => $payment
        ]);
    }


    /**
     * Update Existing Record by Kamlesh 
     */
    public function update_past(Request $request)
    {

        $payment = Vcpayment::findOrFail($request->id);

        if (!$payment) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found'
            ], 404);
        }

        $payment->update($request->only([
            'company_id',
            'customer_id',
            'vc_id',
            'paid_amount',
            'paid_date',
            'amount_type',
            'details',
            'document1',
            'document2',
            'status'
        ]));

        return response()->json([
            'status'  => true,
            'message' => 'Payment updated successfully',
            'data'    => $payment
        ]);
    }

    /**
     * Get Single Record
     */
    public function edit($id)
    {
        $payment = Vcpayment::find($id);

        if (!$payment) {
            return response()->json([
                'status' => false,
                'message' => 'Record not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => $payment
        ]);
    }

    private function storeBase64Image2($base64Image, $directory)
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
        return $directory . '/' . $fileName;
    }

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

    public function customerPaymentStatus(Request $request)
    {
        // PAID CUSTOMERS
        $request->validate([
            'vc_id' => 'required|integer'
        ]);

        $vc_id = $request->vc_id;
        $paidCustomers = DB::table('vc_customers')
            ->join('vcs_payment', 'vcs_payment.customer_id', '=', 'vc_customers.customer_id')
            ->join('customers', 'customers.id', '=', 'vc_customers.customer_id')
            ->where('vc_customers.vc_id', $vc_id)
            ->where('vcs_payment.status', 1)
            ->select(
                'customers.id',
                'customers.customer_no',
                'customers.name',
                'customers.mobile',
                'customers.email',
                'customers.status',
                'customers.image',
                'vcs_payment.paid_amount',
                'vcs_payment.paid_date',
                'vcs_payment.amount_type',
                'vcs_payment.details',
                'vcs_payment.status',
                'vcs_payment.document1',
                'vcs_payment.document2'
            )
            ->groupBy(
                'customers.id',
                'customers.customer_no',
                'customers.name',
                'customers.mobile',
                'customers.email',
                'customers.status',
                'customers.image',
                'vcs_payment.paid_amount',
                'vcs_payment.paid_date',
                'vcs_payment.amount_type',
                'vcs_payment.details',
                'vcs_payment.status',
                'vcs_payment.document1',
                'vcs_payment.document2'
            )
            ->get();

        // UNPAID CUSTOMERS
        $unpaidCustomers = DB::table('vc_customers')
            ->join('customers', 'customers.id', '=', 'vc_customers.customer_id')
            ->where('vc_customers.vc_id', $vc_id)
            ->whereNotIn('customers.id', function ($query) use ($vc_id) {
                $query->select('customer_id')
                    ->from('vcs_payment')
                    ->where('vc_id', $vc_id)
                    ->where('status', 1);
            })
            ->select(
                'customers.id',
                'customers.customer_no',
                'customers.name',
                'customers.mobile',
                'customers.status',
                'customers.email',
                'customers.image',
            )
            ->get();

        return response()->json([
            'status' => true,
            'vc_id' => $vc_id,
            'paid_customers' => $paidCustomers,
            'unpaid_customers' => $unpaidCustomers
        ]);
    }
}
