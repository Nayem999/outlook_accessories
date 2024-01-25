<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BankController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->all();
        $search = $request->input('search');

        $data = Bank::select(
            'banks.id',
            'banks.uuid',
            'banks.name',
            'banks.branch',
            'banks.account_number',
            DB::raw('SUM(CASE WHEN transactions.trans_type_id = 1 THEN transactions.amount ELSE 0 END) as income_amount'),
            DB::raw('SUM(CASE WHEN transactions.trans_type_id = 2 THEN transactions.amount ELSE 0 END) as expense_amount')
        )
            ->leftJoin('transactions', function ($transaction) {
                $transaction->on('banks.id', '=', 'transactions.bank_id')
                    ->where('transactions.active_status', 1)
                    ->where('transactions.approval_status', 1);
            });

        if ($search) {
            $data = $data->where(function ($query) use ($search) {
                $query->where('banks.name', 'LIKE', '%' . $search . '%')
                    ->orWhere('banks.account_number', 'LIKE', '%' . $search . '%')
                    ->orWhere('banks.branch', 'LIKE', '%' . $search . '%');
            });
        }

        // $data = $data->where('banks.active_status', 1)->paginate(self::limit($query));
        $data = $data->where('banks.active_status', 1)
            ->groupBy('banks.id', 'banks.uuid', 'banks.name', 'banks.branch', 'banks.account_number')
            ->orderBy('banks.id', 'desc')
            ->paginate(self::limit($query));

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

    public function filter($data, $query)
    {
        if (array_key_exists('search', $query)) {
            $data = $data->where('banks.name', 'LIKE', '%' . $query['search'] . '%');
            $data = $data->where('banks.account_number', 'LIKE', '%' . $query['search'] . '%');
            $data = $data->where('banks.branch', 'LIKE', '%' . $query['search'] . '%');
        }
        return $data;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => "required|string|max:100",
            'branch' => "required|string|max:100",
            'account_number' => "nullable|string|max:100",
            'address' => "nullable|string|max:250",
            'phone' => "nullable|string|max:30",
            'swift_code' => "nullable|string|max:30",
            'bin_no' => "nullable|string|max:100",
            'opening_balance' => "nullable|numeric|max:99999999.99|regex:/^\d+(\.\d{1,2})?$/",
        ]);
        if ($validator->fails()) {
            $response = [
                "status" => "error",
                'message' => $validator->errors()->all()
            ];
            return response($response, 422);
        }
        $request_data = $request->all();

        // Str::uuid();
        // Str::orderedUuid();
        $request_data['uuid'] = Str::uuid()->toString();
        $request_data['created_by'] = auth()->user()->id;
        DB::beginTransaction();
        $data = Bank::create($request_data);

        $trans_data = $bank_trans_update = true;
        if ($request->opening_balance > 0) {
            $request_trans_data = [
                'trans_page' => 3,
                'trans_type_id' => 1,
                'party_type_id' => 0,
                'party_id' => 0,
                'trans_purpose_id' => 0,
                'trans_method_id' => 6,
                'bank_id' => $data->id,
                'amount' => $request->opening_balance,
                'date' => now(),
                'created_by' => auth()->user()->id,
                'uuid' => Str::uuid()->toString(),
            ];
            $trans_data = Transaction::create($request_trans_data);
            if ($trans_data) {
                $bank_trans_update = Bank::where('id', $data->id)->update(['trans_id' => $trans_data->id]);
            }
        }

        if ($data && $trans_data && $bank_trans_update) {
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
            'uuid' => "required",
            'name' => "required|string|max:100",
            'branch' => "required|string|max:100",
            'account_number' => "nullable|string|max:100",
            'address' => "nullable|string|max:250",
            'phone' => "nullable|string|max:30",
            'swift_code' => "nullable|string",
            'bin_no' => "nullable|string|max:100",
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => "error",
                'message' => $validator->errors()->all()
            ];
            return response($response, 422);
        }
        // $request_data = $request->all();
        $request_data = $validator->validated();
        $request_data['updated_by'] = Auth()->user()->id;
        $data = Bank::where('uuid', $request->uuid);
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
        $update = Bank::where('uuid', $uuid)->update([
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

    public static function getBankInfo($uuid)
    {
        $data = Bank::where('uuid', $uuid)->first();

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

    public static function bank_approval_list($approval_type, Request $request)
    {
        $query = $request->all();
        $search = $request->input('search');

        // $data = Transaction::where('active_status', 1)->where('approval_status', $approval_type)->whereNotNull('bank_id')->where('bank_id', '>', 0)->orderBy('id', 'desc')->with('bank_info')->with('party_info')->with('trans_purpose_info')->paginate(self::limit($query));

        $data = Transaction::select('transactions.*')
            ->with('bank_info:id,name', 'party_info:id,name', 'trans_purpose_info:id,name')
            ->when($search, function ($query) use ($search) {
                $query->join('parties', 'transactions.party_id', '=', 'parties.id')
                    ->join('banks', 'transactions.bank_id', '=', 'banks.id')
                    ->leftJoin('trans_purposes', 'transactions.trans_purpose_id', '=', 'trans_purposes.id')
                    ->where(function ($query) use ($search) {
                        $query->where('parties.name', 'LIKE', '%' . $search . '%')
                            ->orWhere('trans_purposes.name', 'LIKE', '%' . $search . '%')
                            ->orWhere('banks.name', 'LIKE', '%' . $search . '%')
                            ->orWhere('transactions.check_number', 'LIKE', '%' . $search . '%')
                            ->orWhere('transactions.amount', 'LIKE', '%' . $search . '%');
                    });
            })
            ->where('transactions.active_status', 1)
            ->where('transactions.approval_status', $approval_type)
            ->whereNotNull('transactions.bank_id')->where('bank_id', '>', 0)
            ->orderBy('transactions.id', 'asc')
            ->paginate(self::limit($query));

        if ($data->count() > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['getPartyTypeList'] = self::getPartyTypeList();
            $response['getTransTypeList'] = self::getTransTypeList();
            $response['getTransMethodList'] = self::getTransMethodAllList();
            $response['response_data'] = $data;
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }

    public static function bank_approval_update(Request $request)
    {
        $data = Transaction::where('id', $request->trans_id)->first();
        $approval_status = 1;
        if ($data->approval_status == 1) {
            $approval_status = 2;
        }

        $update = Transaction::findOrFail($request->trans_id)->update([
            'approval_status' => $approval_status,
            'updated_by' => Auth()->user()->id,
        ]);

        if ($update) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['response_data'] = $update;
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }

    public static function bank_laser_info($uuid)
    {
        $bank_data = Bank::where('uuid', $uuid)->first();
        $data = Transaction::where('active_status', 1)->where('approval_status', 1)->where('bank_id', $bank_data->id)->orderBy('id')->with('party_info')->with('trans_purpose_info')->get();

        if ($bank_data) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['getTransTypeList'] = self::getTransTypeList();
            $response['getApprovedList'] = self::getApprovedList();
            $response['getPartyTypeList'] = self::getPartyTypeList();
            $response['getBankTransferMethodList'] = self::getBankTransferMethodList();
            $response['getTransMethodAllList'] = self::getTransMethodAllList();
            $response['bank_data'] = $bank_data;
            $response['response_data'] = $data;
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }
}
