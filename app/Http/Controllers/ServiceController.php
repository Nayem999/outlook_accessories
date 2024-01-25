<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Trans_purpose;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->all();
        $search = $request->input('search');
        $data = Service::Select('Services.*','parties.name as party_name','trans_purposes.name as purposes_name')->orderBy('services.id', 'desc')->where('services.active_status',1)
        ->join('parties', 'services.party_id', '=', 'parties.id')
        ->join('trans_purposes', 'services.purpose_id', '=', 'trans_purposes.id')
        ->when($search, function ($query) use ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('parties.name', 'LIKE', "%$search%")
                    ->orWhereDate('services.service_date', 'LIKE', "%$search%")
                    ->orWhere('trans_purposes.name', 'LIKE', "%$search%")
                    ->orWhere('services.amount', 'LIKE', "%$search%");
            });
        })
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

    public function add()
    {
        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['employee_list'] = self::getPartyList(4);
        $response['purpose_data'] = Trans_purpose::select('id', 'name')->where('active_status', 1)->get();
        return response($response, 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'party_id' => "required|numeric|max:99999999",
            'purpose_id' => "required|numeric|max:99999999",
            'service_date' => "required|date",
            'amount' => "required|numeric|max:99999999.99|regex:/^\d+(\.\d{1,2})?$/",
        ]);
        if ($validator->fails()) {
            $response = [
                "status" => "error",
                'message' => $validator->errors()->all()
            ];
            return response($response, 422);
        }
        $request_data = $request->all();
        $request_data['party_type_id'] = 4;
        $request_data['uuid'] = Str::uuid()->toString();
        $request_data['created_by'] = auth()->user()->id;


        if ($data=Service::create($request_data)) {
            $response['data'] = $data;
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
            'party_id' => "required|numeric|max:99999999",
            'purpose_id' => "required|numeric|max:99999999",
            'service_date' => "required|date",
            'amount' => "required|numeric|max:99999999.99|regex:/^\d+(\.\d{1,2})?$/",
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
        $data = Service::where('uuid', $request->uuid);
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
        $update = Service::where('uuid', $uuid)->update([
            'active_status' => 2,
            'updated_by'=>Auth()->user()->id,
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
}
