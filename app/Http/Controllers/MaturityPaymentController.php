<?php

namespace App\Http\Controllers;

use App\Models\Lc;
use App\Models\Maturity_payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MaturityPaymentController extends Controller
{

    public function index(Request $request)
    {
        $query = $request->all();
        $search = $request->input('search');
        $data = Maturity_payment::select('maturity_payments.*');

        if ($search) {
            $data = $data->where(function ($query) use ($search) {
                $query->where('maturity_payments.lc_num', 'LIKE', '%' . $search . '%')
                ->orWhere('maturity_payments.lc_value', 'LIKE', '%' . $search . '%')
                ->orWhere('maturity_payments.amount', 'LIKE', '%' . $search . '%')
                ->orWhereDate('maturity_payments.payment_date', 'LIKE', '%' . $search . '%');
            });
        }
        $data = $data->where('maturity_payments.active_status', 1)->orderBy('maturity_payments.id', 'desc')->paginate(self::limit($query));

        if ($data->count() > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['response_data'] = $data;
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }

    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'lc_id' => "required|numeric",
            'lc_num' => "required|string|max:100",
            'doc_acceptace_id' => "required|numeric",
            'payment_date' => "required|date",
            'lc_value' => "required|numeric",
            'exchange_rate' => "required|numeric",
            'amount' => "required|numeric",
            'remarks' => "nullable|string|max:200",
        ]);
        if ($validator->fails()) {
            $response = [
                "status" => "error",
                "message" => "Something went to wrong !",
                'error' => $validator->errors()->all()
            ];
            return response($response, 422);
        }
        $user_id = Auth()->user()->id;
        $request_data = [
            'lc_id' => $request->lc_id,
            'lc_num' => $request->lc_num,
            'doc_acceptace_id' => $request->doc_acceptace_id,
            'payment_date' => $request->payment_date,
            'lc_value' => $request->lc_value,
            'exchange_rate' => $request->exchange_rate,
            'amount' => $request->amount,
            'remarks' => $request->remarks,
            'created_by' => $user_id,
            'uuid' => Str::uuid()->toString(),
        ];
        $prv_trans_data = Transaction::where('lc_id', $request->lc_id)->where('active_status', 1)->first();

        if($prv_trans_data)
        {
            $response = [
                "status" => "error",
                "message" => "Already Payment Taken!",
                'error' => $validator->errors()->all()
            ];
            return response($response, 422);
        }
        else
        {
            $lc_data = Lc::where('id', $request->lc_id)->first();
            $request_trans_data = [
                'trans_page' => 2,
                'trans_type_id' => 1,
                'party_type_id' => 1,
                'party_id' => $lc_data->company_id,
                'trans_purpose_id' => 0,
                'trans_method_id' => 5,
                'bank_id' => $lc_data->advising_bank_id,
                'lc_id' => $request->lc_id,
                'amount' => $request->amount,
                'date' => $request->payment_date,
                'note' => $request->remarks,
                'created_by' => $user_id,
                'uuid' => Str::uuid()->toString(),
            ];
        }

        DB::beginTransaction();
        $trans_data = Transaction::create($request_trans_data);
        $request_data['trans_id'] = $trans_data->id;
        $maturity_data = Maturity_payment::create($request_data);

        if ($maturity_data && $trans_data) {
            DB::commit();
            $response['status'] = 'success';
            $response['message'] = 'Data inserted successfully.';
            return response($response, 200);
        } else {
            DB::rollBack();
            $response['status'] = 'error';
            $response['message'] = 'Something went to wrong!';
            return response($response, 422);
        }
    }

    public function update(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'mst_id' => "required|numeric",
            'trans_id' => "required|numeric",
            'lc_id' => "required|numeric",
            'lc_num' => "required|string|max:100",
            'doc_acceptace_id' => "required|numeric",
            'payment_date' => "required|date",
            'lc_value' => "required|numeric",
            'exchange_rate' => "required|numeric",
            'amount' => "required|numeric",
            'remarks' => "nullable|string|max:200",
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => "error",
                "message" => "Something went to wrong!",
                'errors' => $validator->errors()->all()
            ];
            return response($response, 422);
        }

        $user_id = Auth()->user()->id;
        $request_data = [
            'lc_id' => $request->lc_id,
            'lc_num' => $request->lc_num,
            'doc_acceptace_id' => $request->doc_acceptace_id,
            'payment_date' => $request->payment_date,
            'lc_value' => $request->lc_value,
            'exchange_rate' => $request->exchange_rate,
            'amount' => $request->amount,
            'remarks' => $request->remarks,
            'updated_by' => $user_id,
        ];

        DB::beginTransaction();
        $request_trans_data = [
            'amount' => $request->amount,
            'date' => $request->payment_date,
            'note' => $request->remarks,
            'updated_by' => $user_id,
        ];

        $maturity_data = Maturity_payment::where('id', $request->mst_id)->update($request_data);
        $trans_data = Transaction::where('id', $request->trans_id)->update($request_trans_data);

        if ($maturity_data && $trans_data) {
            DB::commit();
            $response['status'] = 'success';
            $response['message'] = 'Data updated successfully.';
            return response($response, 200);
        } else {
            DB::rollBack();
            $response['status'] = 'error';
            $response['message'] = 'Something went to wrong!';
            return response($response, 422);
        }
    }

    public function destroy($uuid)
    {
        $data = Maturity_payment::where('uuid', $uuid)->first();
        DB::beginTransaction();
        $data = Maturity_payment::where('id', $data->id)->first();
        $maturity_data = Maturity_payment::findOrFail($data->id)->update([
            'active_status' => 2,
            'updated_by' => Auth()->user()->id,
        ]);
        $trans_data = true;
        if($data->trans_id)
        {
            $trans_data = Transaction::where('id', $data->trans_id)->where('active_status', 1)->update([
                'active_status' => 2,
                'updated_by' => Auth()->user()->id,
            ]);
        }

        if ($maturity_data && $trans_data) {
            DB::commit();
            $response['status'] = 'success';
            $response['message'] = 'Data Deleted successfully.';
            return response($response, 200);
        } else {
            DB::rollBack();
            $response['status'] = 'error';
            $response['message'] = 'Something went to wrong!';
            return response($response, 422);
        }
    }

    public function getMaturityInfo($uuid)
    {
        // $data = Maturity_payment::where('id', $request->id)->first();
        $data = Maturity_payment::where('uuid', $uuid)->with('lc_info')->where('active_status', 1)->first();

        if ($data->count() > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['response_data'] = $data;
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }
}
