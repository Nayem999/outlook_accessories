<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PermissionController extends Controller
{

    public function index()
    {
        $role_list = Role::orderBy('id', 'desc')->select('id', 'name')->whereNotIn('id',[1])->where('active_status', 1)->get();
        $module_list = self::getModuleList();
        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['role_list'] = $role_list;
        $response['module_list'] = $module_list;
        return response($response, 200);
    }

    public function getPermissionInfo(Request $request)
    {
        $data = Permission::where('role_id', $request->role_id)->get();
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
    public function getPermissionData($role_id)
    {
        $data = Permission::where('role_id', $role_id)->get();
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
        DB::beginTransaction();
        $pre_data = Permission::where('role_id', $request->all_data[0]['role_id'])->first();
        $old_data = True;
        if($pre_data){
            $old_data = Permission::where('role_id', $request->all_data[0]['role_id'])->delete();
        }
        $user_id = Auth()->user()->id;
        $data_dtls_insert = [];
        foreach ($request->all_data as $row) {
            if ($row["role_id"] && $row["module"]) {
                $data_dtls_arr = [
                    'role_id' => $row["role_id"],
                    'module' => $row["module"],
                    'view' => $row["view"],
                    'add' => $row["add"],
                    'edit' => $row["edit"],
                    'delete' => $row["delete"],
                    'created_by' => $user_id,
                    'created_at' => now(),
                ];
                $data_dtls_insert[] = $data_dtls_arr;
            }
        }
        $data = false;
        if (count($data_dtls_insert) > 0) {
            $data = Permission::insert($data_dtls_insert);
        }

        if ($data && $old_data) {
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
}
