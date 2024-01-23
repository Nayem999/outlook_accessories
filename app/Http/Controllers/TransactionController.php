<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Trans_purpose;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TransactionController extends Controller
{

    public function add()
    {
        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['getPartyTypeList'] = self::getPartyTypeList();
        $response['getTransTypeList'] = self::getTransTypeList();
        $response['getTransMethodList'] = self::getTransMethodList();
        $response['getBankTransferMethodList'] = self::getBankTransferMethodList();
        $response['getBankList'] = Bank::select('id', 'name')->where('active_status', 1)->get();
        $response['getTransPurposeList'] = Trans_purpose::select('id', 'name')->where('active_status', 1)->get();
        return response($response, 200);
    }

    public function index(Request $request)
    {
        $query = $request->all();
        $search = $request->input('search');

        $data = Transaction::select('transactions.*')
            ->with('bank_info:id,name', 'party_info:id,name', 'trans_purpose_info:id,name')
            ->when($search, function ($query) use ($search) {
                $query->join('parties', 'transactions.party_id', '=', 'parties.id')
                    ->where(function ($query) use ($search) {
                        $query->where('parties.name', 'LIKE', '%' . $search . '%')
                            ->orWhere('transactions.amount', 'LIKE', '%' . $search . '%');
                    });
            })
            ->when($request->trans_type, function ($query) use ($request) {
                $query->where('transactions.trans_type_id', $request->trans_type);
            })
            ->when($request->start_date, function ($query) use ($request) {
                $query->where('transactions.date', '>=', $request->start_date);
            })
            ->when($request->end_date, function ($query) use ($request) {
                $query->where('transactions.date', '<=', $request->end_date);
            })
            ->where('transactions.active_status', 1)
            ->where('transactions.trans_page', 1)
            ->orderBy('transactions.id', 'desc')
            ->paginate(self::limit($query));


        if ($data->count() > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['getPartyTypeList'] = self::getPartyTypeList();
            $response['getTransTypeList'] = self::getTransTypeList();
            $response['getTransMethodList'] = self::getTransMethodAllList();
            $response['getBankTransferMethodList'] = self::getBankTransferMethodList();
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
            'trans_type_id' => "required|numeric|max:100",
            'party_type_id' => "required|numeric",
            'party_id' => "required|numeric",
            'trans_purpose_id' => "required|numeric",
            'trans_method_id' => "required|numeric",
            'bank_id' => "nullable|numeric",
            'check_number' => "nullable|string|max:100",
            'transfer_method_id' => "nullable|numeric",
            'amount' => "required|numeric|min:1|max:99999999.99|regex:/^\d+(\.\d{1,2})?$/ ",
            'date' => "nullable|date|max:100",
            'note' => "nullable|string|max:200",
            'file_image' => "nullable|mimes:png,jpeg,jpg,gif",
        ]);
        if ($validator->fails()) {
            $response = [
                "status" => "error",
                'message' => $validator->errors()->all()
            ];
            return response($response, 422);
        }
        $request_data = $request->all();
        $request_data['uuid'] = Str::uuid()->toString();
        $request_data['created_by'] = auth()->user()->id;
        if ($files = $request->file("file_image")) {
            $path = 'transaction';
            $attachment = self::uploadImage($files, $path);
            $request_data['file_image'] = $attachment;
        }
        if ($request->trans_method_id == 2 && $request->trans_type_id == 1) {
            $request_data['approval_status'] = 2;
        }

        if (Transaction::create($request_data)) {
            $response['status'] = 'success';
            $response['message'] = 'Data inserted successfully.';
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Something went to wrong!';
            return response($response, 422);
        }
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trans_type_id' => "required|numeric|max:100",
            'party_type_id' => "required|numeric|min:1",
            'party_id' => "required|numeric|max:11",
            'trans_purpose_id' => "required|numeric|max:11",
            'trans_method_id' => "required|numeric|min:1",
            'bank_id' => "nullable|numeric|max:11",
            'check_number' => "nullable|string|max:100",
            'transfer_method_id' => "nullable|numeric",
            'amount' => "required|numeric|min:1|max:99999999.99|regex:/^\d+(\.\d{1,2})?$/ ",
            'date' => "nullable|date|max:100",
            'note' => "nullable|string|max:200",
            'file_image' => "nullable|mimes:png,jpeg,jpg,gif",
            'uuid' => "required",
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => "error",
                'message' => $validator->errors()->all()
            ];
            return response($response, 422);
        }
        $request_data = $request->all();
        $request_data['updated_by'] = Auth()->user()->id;
        unset($request_data['file_image']);
        if ($files = $request->file("file_image")) {
            $path = 'transaction';
            $attachment = self::uploadImage($files, $path);
            $request_data['file_image'] = $attachment;
        }

        $data = Transaction::where('uuid', $request->uuid);
        $data = $data->first();

        if ($data) {
            $data->update($request_data);
            $response['status'] = 'success';
            $response['message'] = 'Data updated successfully.';
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Something went to wrong!';
            return response($response, 422);
        }
    }

    public function destroy($uuid)
    {
        $update = Transaction::where('uuid', $uuid)->update([
            'active_status' => 2,
            'updated_by' => Auth()->user()->id,
        ]);
        if ($update) {
            $response['status'] = 'success';
            $response['message'] = 'Data Deleted successfully.';
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Something went to wrong!';
            return response($response, 422);
        }
    }

    public function getTransInfo($uuid)
    {
        $data = Transaction::where('uuid', $uuid)->with('bank_info')->with('party_info')->with('trans_purpose_info')->first();

        if ($data) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['getPartyTypeList'] = self::getPartyTypeList();
            $response['getTransTypeList'] = self::getTransTypeList();
            $response['getTransMethodList'] = self::getTransMethodAllList();
            $response['getBankTransferMethodList'] = self::getBankTransferMethodList();
            $response['getBankList'] = Bank::select('id', 'name')->where('active_status', 1)->get();
            $response['getTransPurposeList'] = Trans_purpose::select('id', 'name')->where('active_status', 1)->get();
            $response['response_data'] = $data;
            $response['get_settings_info'] = self::get_settings_info();
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }
}
