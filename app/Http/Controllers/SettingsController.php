<?php

namespace App\Http\Controllers;

use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Helper\Helper;
use Illuminate\Support\Str;



class SettingsController extends Controller
{
    use Helper;
    public function index()
    {

        $data = Settings::where('active_status',1)->first();

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

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => "required|string|max:100",
            'company_email' => "nullable|string|max:100",
            'office_add' => "nullable|string|max:200",
            'office_phone' => "nullable|string|max:30",
            'head_office_add' => "nullable|string|max:200",
            'head_office_phone' => "nullable|string|max:30",
            'tin_number' => "nullable|numeric",
            'bin_number' => "nullable|numeric",
            'logo' => "nullable|mimes:png,jpeg,jpg,gif",
            'signature' => "nullable|mimes:png,jpeg,jpg,gif",
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
        unset($request_data['logo']);
        unset($request_data['signature']);
        $request_data['updated_by'] = Auth()->user()->id;

        if ($files = $request->file('logo')) {
            $path = 'others';
            $attachment = self::uploadImage($files, $path);
            $request_data['logo'] = $attachment;
        }

        if ($files = $request->file('signature')) {
            $path = 'others';
            $attachment = self::uploadImage($files, $path);
            $request_data['signature'] = $attachment;
        }

        $data = Settings::where('active_status',1)->first();

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

}