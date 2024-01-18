<?php

namespace App\Http\Controllers;

use App\Models\Color;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ColorController extends Controller
{

    public function index(Request $request)
    {
        $query = $request->all();
        $search = $request->input('search');

        $data = Color::orderBy('id', 'desc')->select( 'id', 'uuid', 'name','code'
        )->where('active_status',1);

        if ($search) {
            $data = $data->where(function ($query) use ($search) {
                $query->where('colors.name', 'LIKE', '%' . $search . '%')
                    ->orWhere('colors.code', 'LIKE', '%' . $search . '%');
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
            'name' => "required|unique:colors,name,NULL,id,active_status,1",
            'code' => "nullable|string|max:60",
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

        if ($data=Color::create($request_data)) {
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
            'name' => "required|unique:colors,name,$request->id,id,active_status,1",
            'code' => "nullable|string|max:60",
            'uuid' => "required",
            'id' => "required",
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
        $data = Color::where('uuid', $request->uuid);
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
        $update = Color::where('uuid', $uuid)->update([
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
