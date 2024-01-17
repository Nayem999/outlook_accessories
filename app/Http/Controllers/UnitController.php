<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{

    public function index(Request $request)
    {
        $query = $request->all();
        $search = $request->input('search');
        $data = Unit::orderBy('id', 'desc')->select(
            'id',
            'uuid',
            'name'
        )->where('active_status', 1);

        if ($search) {
            $data = $data->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', '%' . $search . '%');
            });
        }
        $data = $data->paginate(self::limit($query));

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
            'name' => "required|unique:units,name,NULL,id,active_status,1",
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


        if ($data = Unit::create($request_data)) {
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
            'name' => "required|unique:units,name,$request->id,id,active_status,1|string",
            'uuid' => 'required',
            'id' => 'required',
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
        $data = Unit::where('uuid', $request->uuid);
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
        $update = Unit::where('uuid', $uuid)->update([
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
}
