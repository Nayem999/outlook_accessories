<?php

namespace App\Http\Controllers;

use App\Models\Pi_tnc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PiTncController extends Controller
{

    public function index(Request $request)
    {
        $query = $request->all();
        $search = $request->input('search');
        $data = Pi_tnc::orderBy('id', 'desc')->select(
            'id','uuid',
            'name','active_status'
        );

        if ($search) {
            $data = $data->where('pi_tncs.name', 'LIKE', '%' . $search . '%');
        }
        // $data = $data->paginate(self::limit($query));
        $data = $data->get();

        if ($data->count() > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['getActiveStatusList'] = self::getActiveStatusList();
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
            'name' => "required|unique:pi_tncs,name,NULL,id,active_status,1",
            'active_status' => "required",
        ]);
        if ($validator->fails()) {
            $response = [
                "status" => "error",
                "message" => "Something went to wrong !",
                'error' => $validator->errors()->all()
            ];
            return response($response, 422);
        }
        $request_data = $request->all();
        $request_data['uuid'] = Str::uuid()->toString();
        $request_data['created_by'] = auth()->user()->id;


        if ($data=Pi_tnc::create($request_data)) {
            $response['status'] = 'success';
            $response['message'] = 'Data inserted successfully.';
            $response['data'] = $data;
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
            'name' => "required|unique:pi_tncs,name,$request->id,id,active_status,1",
            'uuid' => "required",
            'id' => "required",
        ]);
        if ($validator->fails()) {
            $response = [
                "status" => "error",
                "message" => "Something went to wrong!",
                'errors' => $validator->errors()->all()
            ];
            return response($response, 422);
        }
        $request_data = $request->all();
        $request_data['updated_by'] = Auth()->user()->id;
        $data = Pi_tnc::where('uuid', $request->uuid);
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
        $update = Pi_tnc::where('uuid', $uuid)->update([
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
