<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\User_permission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserPermissionController extends Controller
{

    public function index()
    {
        $role_list = Role::orderBy('id', 'desc')->select('id', 'name')->whereNotIn('id',[1])->where('active_status', 1)->get();
        $user_list = User::orderBy('id', 'desc')->select('id', 'name')->whereNotIn('id',[1])->where('active_status', 1)->get();
        $module_list = self::getModuleList();
        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['role_list'] = $role_list;
        $response['user_list'] = $user_list;
        $response['module_list'] = $module_list;
        return response($response, 200);
    }

    public function getRoleWiseUser($role_id)
    {
        $data = User::orderBy('id', 'desc')->select('id', 'name')->where('active_status', 1)->where('role_id', $role_id)->get();

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

    public function getPermissionInfo(Request $request)
    {
        $data = User_permission::where('role_id', $request->role_id)->where('user_id', $request->user_id)->get();
        if ($data->count() > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['response_data'] = $data;
            return response($response, 200);
        } else {
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
    }

    public function update(Request $request)
    {
        DB::beginTransaction();
        $pre_data = User_permission::where('role_id', $request->all_data[0]['role_id'])->where('user_id',  $request->all_data[0]['user_id'])->first();
        $old_data = True;
        if ($pre_data) {
            $old_data = User_permission::where('role_id', $request->all_data[0]['role_id'])->where('user_id',  $request->all_data[0]['user_id'])->delete();
        }
        // $module_list = self::getModuleList();
        $data_dtls_insert = [];
        $user_id = Auth()->user()->id;
        foreach ($request->all_data as $row) {
            if ($row["role_id"] && $row["module"]) {
                $data_dtls_arr = [
                    'role_id' => $row["role_id"],
                    'user_id' => $row["user_id"],
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
            $data = User_permission::insert($data_dtls_insert);
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

    public function permission_check($module_name = null)
    {
        $role_id = Auth()->user()->role_id;
        $user_id = Auth()->user()->id;

        $data = User_permission::where('role_id', $role_id)->where('user_id', $user_id)
            ->when($module_name, function ($query) use ($module_name) {
                $query->where('module', $module_name);
            })->get();

        if ($data->count() > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['module_list'] = self::getModuleList();
            $response['response_data'] = $data;
            return response($response, 200);
        } else {
            $data = Permission::where('role_id', $role_id)
                ->when($module_name, function ($query) use ($module_name) {
                    $query->where('module', $module_name);
                })->get();
            if ($data->count() > 0) {
                $response['status'] = 'success';
                $response['message'] = 'Data found.';
                $response['module_list'] = self::getModuleList();
                $response['response_data'] = $data;
                return response($response, 200);
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Data not found.';
                return response($response, 422);
            }
        }
    }
}
