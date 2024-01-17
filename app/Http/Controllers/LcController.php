<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Doc_acpt_mst;
use App\Models\Lc;
use App\Models\Lc_pi;
use App\Models\Maturity_payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LcController extends Controller
{
    public function add()
    {
        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['company_list'] = self::getPartyList(1);
        $response['buyer_list'] = self::getPartyList(2);
        $response['pay_term_list'] = self::getPayTermList();
        $response['currency_list'] = self::getCurrencyList();
        $response['opening_bank_list'] = Bank::select('id', 'name')->where('active_status', 1)->get();
        $response['advising_bank_list'] = Bank::select('id', 'name')->where('active_status', 1)->whereNotNull('account_number')->get();

        return response($response, 200);
    }

    public function index(Request $request)
    {
        $query = $request->all();
        $search = $request->input('search');
        $data = Lc::select('lcs.*', 'a.name as company_name', 'b.name as buyer_name')
            ->join('parties as a', 'lcs.company_id', '=', 'a.id')
            ->join('parties as b', 'lcs.buyer_id', '=', 'b.id');

        if ($search) {
            $data = $data->where(function ($query) use ($search) {
                $query->where('lcs.lc_no', 'LIKE', '%' . $search . '%')
                    ->orWhereDate('lcs.contract_date', 'LIKE', '%' . $search . '%')
                    ->orWhere('a.name', 'LIKE', '%' . $search . '%')
                    ->orWhere('b.name', 'LIKE', '%' . $search . '%');
            });
        }
        $data = $data->where('lcs.active_status', 1)->orderBy('lcs.id', 'desc')->paginate(self::limit($query));

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
            'company_id' => "required|numeric",
            'buyer_id' => "required|numeric",
            'contract_no' => "required|string|max:100",
            'contract_date' => "required|date",
            'letter_of_credit_no' => "nullable|string|max:100",
            'letter_of_credit_date' => "nullable|date",
            'lc_issue_date' => "nullable|date",
            'lc_expiry_date' => "nullable|date",
            'currency_id' => "required|numeric",
            'lc_value' => "required|numeric",
            'opening_bank_id' => "required|numeric",
            'advising_bank_id' => "required|numeric",
            'amendment_no' => "nullable|string|max:10",
            'amendment_date' => "nullable|date",
            'pay_term_id' => "required|numeric",
            'tenor' => "nullable|numeric",
            'tolerance' => "nullable|numeric",
            'port_of_loading' => "nullable|string|max:100",
            'port_of_discharge' => "nullable|string|max:100",
            'last_shipment_date' => "nullable|date",
            'file_image' => "nullable|mimes:png,jpeg,jpg,gif,doc,docs,pdf,xlsx,xls",
            'remarks' => "nullable|string|max:200",
        ]);
        if ($validator->fails()) {
            $response = [
                "status" => "error",
                'message' => $validator->errors()->all()
            ];
            return response($response, 422);
        }
        $user_id = Auth()->user()->id;
        $request_data = [
            'company_id' => $request->company_id,
            'buyer_id' => $request->buyer_id,
            'contract_no' => $request->contract_no,
            'contract_date' => $request->contract_date,
            'letter_of_credit_no' => $request->letter_of_credit_no,
            'letter_of_credit_date' => $request->letter_of_credit_date,
            'lc_issue_date' => $request->lc_issue_date,
            'lc_expiry_date' => $request->lc_expiry_date,
            'currency_id' => $request->currency_id,
            'lc_value' => $request->lc_value,
            'opening_bank_id' => $request->opening_bank_id,
            'advising_bank_id' => $request->advising_bank_id,
            'amendment_no' => $request->amendment_no,
            'amendment_date' => $request->amendment_date,
            'pay_term_id' => $request->pay_term_id,
            'tenor' => $request->tenor,
            'tolerance' => $request->tolerance,
            'port_of_loading' => $request->port_of_loading,
            'port_of_discharge' => $request->port_of_discharge,
            'last_shipment_date' => $request->last_shipment_date,
            'remarks' => $request->remarks,
            'created_by' => $user_id,
            'uuid' => Str::uuid()->toString(),
            'lc_no' => self::get_system_no('lcs', 'LC'),
        ];

        $request_data['created_by'] = $user_id;
        $request_data['lc_no'] = self::get_system_no('lcs', 'LC');
        if ($files = $request->file("file_image")) {
            $path = 'lc';
            $attachment = self::uploadImage($files, $path);
            $request_data['file_image'] = $attachment;
        }

        DB::beginTransaction();
        $data_mst = LC::create($request_data);
        $data_dtls_array = [];
        foreach ($request->data_dtls as $row) {
            if ($row["pi_mst_id"]) {
                $data_dtls_arr = [
                    'lc_id' => $data_mst->id,
                    'pi_mst_id' => $row["pi_mst_id"],
                    'created_by' => $user_id,
                ];
                $data_dtls_array[] = $data_dtls_arr;
            }
        }

        $data_dtls = true;
        if (count($data_dtls_array) > 0) {
            $data_dtls = Lc_pi::insert($data_dtls_array);
        }

        if ($data_mst && $data_dtls) {
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
            'company_id' => "required|numeric",
            'buyer_id' => "required|numeric",
            'contract_no' => "required|string|max:100",
            'contract_date' => "required|date",
            'letter_of_credit_no' => "nullable|string|max:100",
            'letter_of_credit_date' => "nullable|date",
            'lc_issue_date' => "nullable|date",
            'lc_expiry_date' => "nullable|date",
            'currency_id' => "required|numeric",
            'lc_value' => "required|numeric",
            'opening_bank_id' => "required|numeric",
            'advising_bank_id' => "required|numeric",
            'amendment_no' => "nullable|string|max:10",
            'amendment_date' => "nullable|date",
            'pay_term_id' => "required|numeric",
            'tenor' => "nullable|numeric",
            'tolerance' => "nullable|numeric",
            'port_of_loading' => "nullable|string|max:100",
            'port_of_discharge' => "nullable|string|max:100",
            'last_shipment_date' => "nullable|date",
            'file_image' => "nullable|mimes:png,jpeg,jpg,gif,doc,docs,pdf,xlsx,xls",
            'remarks' => "nullable|string|max:200",
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => "error",
                'message' => $validator->errors()->all()
            ];
            return response($response, 422);
        }
        $mst_id = $request->mst_id;
        $user_id = Auth()->user()->id;
        $request_data = [
            'company_id' => $request->company_id,
            'buyer_id' => $request->buyer_id,
            'contract_no' => $request->contract_no,
            'contract_date' => $request->contract_date,
            'letter_of_credit_no' => $request->letter_of_credit_no,
            'letter_of_credit_date' => $request->letter_of_credit_date,
            'lc_issue_date' => $request->lc_issue_date,
            'lc_expiry_date' => $request->lc_expiry_date,
            'currency_id' => $request->currency_id,
            'lc_value' => $request->lc_value,
            'opening_bank_id' => $request->opening_bank_id,
            'advising_bank_id' => $request->advising_bank_id,
            'amendment_no' => $request->amendment_no,
            'amendment_date' => $request->amendment_date,
            'pay_term_id' => $request->pay_term_id,
            'tenor' => $request->tenor,
            'tolerance' => $request->tolerance,
            'port_of_loading' => $request->port_of_loading,
            'port_of_discharge' => $request->port_of_discharge,
            'last_shipment_date' => $request->last_shipment_date,
            'remarks' => $request->remarks,
        ];
        $request_data['updated_by'] = $user_id;
        if ($files = $request->file("file_image")) {
            $path = 'lc';
            $attachment = self::uploadImage($files, $path);
            $request_data['file_image'] = $attachment;
        }

        DB::beginTransaction();
        $data_mst = Lc::where('id', $mst_id)->update($request_data);
        $lcPiDtlIds = Lc_pi::where('lc_id', $mst_id)->where('active_status', 1)->pluck('id')->all();

        $data_dtls_insert = [];
        $active_dtls_id = array();
        foreach ($request->data_dtls as $row) {
            if ($row["pi_mst_id"]) {
                $data_dtls_arr = [
                    'lc_id' => $mst_id,
                    'pi_mst_id' => $row["pi_mst_id"]
                ];
                if ($row["dtls_id"]) {
                    $data_dtls_arr['updated_by'] = $user_id;
                    Lc_pi::where('id', $row["dtls_id"])->update($data_dtls_arr);
                    $active_dtls_id[] = $row["dtls_id"];
                } else {
                    $data_dtls_arr['created_by'] = $user_id;
                    $data_dtls_insert[] = $data_dtls_arr;
                }
            }
        }

        $data_dtls = $data_del_dtls = true;

        $lcPiDtlIdsDiffArr = array_diff($lcPiDtlIds, $active_dtls_id);
        if (count($lcPiDtlIdsDiffArr) > 0) {
            $delete_info = [
                'active_status' => 2,
                'updated_by' => Auth()->user()->id,
            ];
            $data_del_dtls = Lc_pi::whereIn('id', $lcPiDtlIdsDiffArr)->update($delete_info);
        }

        if (count($data_dtls_insert) > 0) {
            $data_dtls = Lc_pi::insert($data_dtls_insert);
        }

        if ($data_mst && $data_del_dtls && $data_dtls) {
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
        $data = Lc::where('uuid', $uuid)->first();
        $Maturity_payment_add = Maturity_payment::where('lc_id', $data->id)->where('active_status', 1)->first();
        if ($Maturity_payment_add) {
            $response['status'] = 'error';
            $response['message'] = 'LC can not delete. This LC found in Maturity Payment page.';
            return response($response, 422);
        }
        $doc_acpt_add = Doc_acpt_mst::where('lc_id', $data->id)->where('active_status', 1)->first();
        if ($doc_acpt_add) {
            $response['status'] = 'error';
            $response['message'] = 'LC can not delete. This LC found in Document Acceptance page.';
            return response($response, 422);
        }

        DB::beginTransaction();
        $update_mst = Lc::findOrFail($data->id)->update([
            'active_status' => 2,
            'updated_by' => Auth()->user()->id,
        ]);
        $update_dtls = Lc_pi::where('lc_id', $data->id)->where('active_status', 1)->update([
            'active_status' => 2,
            'updated_by' => Auth()->user()->id,
        ]);

        if ($update_mst && $update_dtls) {
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

    public function getLcInfo($uuid)
    {

        $data = Lc::where('uuid', $uuid)->with(['company_info', 'buyer_info', 'opening_bank_info', 'advising_bank_info', 'data_dtls.pi_info', 'data_dtls.pi_info.data_dtls', 'data_dtls.pi_info.data_dtls.product_info', 'data_dtls.pi_info.data_dtls.color_info', 'data_dtls.pi_info.data_dtls.size_info', 'data_dtls.pi_info.data_dtls.unit_info'])->where('active_status', 1)->first();
        // $data_pi = Lc_pi::where('lc_id', $request->id)->with('pi_info')->where('active_status', 1)->get();

        if ($data) {
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
