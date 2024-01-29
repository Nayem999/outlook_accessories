<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PartyController extends Controller
{
    public function index(Request $request)
    {
        // dd($request->all());
        $query = $request->all();
        $search = $request->input('search');
        $data = Party::orderBy('id', 'desc')->where('active_status', 1);

        if ($search) {
            $data = $data->where(function ($query) use ($search) {
                $party_type_arr = self::getPartyTypeList();

                $query->where('parties.name', 'LIKE', '%' . $search . '%')
                    ->orWhere('parties.email', 'LIKE', '%' . $search . '%')
                    ->orWhere('parties.phone', 'LIKE', '%' . $search . '%')
                    ->orWhere('parties.contact_person_name', 'LIKE', '%' . $search . '%');

                foreach ($party_type_arr as $partyTypeId => $partyTypeName) {
                    if (stripos($partyTypeName, $search) !== false) {
                        $query->orWhere('parties.party_type_id', $partyTypeId);
                    }
                }
            });
        }

        $data = $data->paginate(self::limit($query));

        if ($data->count() > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['getPartyTypeList'] = self::getPartyTypeList();
            $response['response_data'] = $data;
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }

    public function add()
    {
        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['getPartyTypeList'] = self::getPartyTypeList();
        $response['getAccountTypeList'] = self::getAccountTypeList();

        return response($response, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => "required|string|max:100",
            'party_type_id' => "required|numeric|max:999",
            'email' => "nullable|string|email|max:100",
            'phone' => "nullable|max:30",
            'address' => "nullable|max:200",
            'bin_no' => "nullable|string|max:100",
            'account_type' => "nullable|numeric|max:999",
            'opening_balance' => "nullable|numeric|max:99999999.99|regex:/^\d+(\.\d{1,2})?$/",
            'contact_person_name' => "nullable|string|max:100",
            'contact_person_email' => "nullable|string|max:100",
            'contact_person_phone' => "nullable|string|max:30",
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

        DB::beginTransaction();
        $data = Party::create($request_data);
        $trans_data = $party_trans_update = true;
        if ($request->account_type && $request->opening_balance > 0) {
            $request_trans_data = [
                'trans_page' => 4,
                'trans_type_id' => $request->account_type,
                'party_type_id' => $request->party_type_id,
                'party_id' => $data->id,
                'trans_purpose_id' => 0,
                'trans_method_id' => 7,
                'amount' => $request->opening_balance,
                'date' => now(),
                'created_by' => auth()->user()->id,
                'uuid' => Str::uuid()->toString(),
            ];
            $trans_data = Transaction::create($request_trans_data);
            if ($trans_data) {
                $party_trans_update = Party::where('id', $data->id)->update(['trans_id' => $trans_data->id]);
            }
        }

        if ($data && $trans_data && $party_trans_update) {
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
            'name' => "required|string|max:100",
            'party_type_id' => "required|numeric|max:999",
            'email' => "nullable|string|email|max:100",
            'phone' => "nullable|max:30",
            'address' => "nullable|max:200",
            'bin_no' => "nullable|string|max:100",
            'contact_person_name' => "nullable|string|max:100",
            'contact_person_email' => "nullable|string|max:100",
            'contact_person_phone' => "nullable|string|max:30",
            'uuid' => "required",
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
        $data = Party::where('uuid', $request->uuid);
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
        $update = Party::where('uuid', $uuid)->update([
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

    public static function getPartyInfo($uuid)
    {
        $data = Party::where('uuid', $uuid)->first();

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
