<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\user;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => "error",
                "message" => "Something went to wrong!",
                'errors' => $validator->errors()->all()
            ];
            return response($response, 401);
        }

        $user = \App\Models\User::where('email', $request->email)->with('role_info')->first();

        if ($user) {
            if ($user->active_status) {
                $data = [
                    'email' => $request->email,
                    'password' => $request->password
                ];

                if (auth()->attempt($data)) {

                    $data_token = $user->createToken($user->name);
                    $response = [
                        'status' => 'success',
                        'token' => $data_token->accessToken,
                        'token_expires_at' => Carbon::parse($data_token->token->expires_at)->format('d-M-Y H:i:s'),
                        'role' => $user->role_info->name ?? '',
                        'user' => Auth::user()
                    ];
                    return response($response, 200);
                } else {
                    $response = [
                        "status" => "error",
                        "message" => "Password mismatch"
                    ];
                    return response($response, 422);
                }
            } else {
                $response = [
                    "status" => "error",
                    "message" => "Your account is deactivated. please contact with admin."
                ];
                return response($response, 422);
            }
        } else {
            $response = [
                "status" => "error",
                "message" => 'User does not exist'
            ];

            return response($response, 422);
        }
    }

    public function index(Request $request)
    {
        $query = $request->all();
        $search = $request->input('search');
        $data = User::select('users.*','roles.name as role_name')->orderBy('users.active_status', 'desc');
        $data = $data->join('roles','users.role_id','=','roles.id');

        if ($search) {
            $data = $data->where(function ($query) use ($search) {
                $query->where('users.name', 'LIKE', '%' . $search . '%')
                    ->orWhere('users.email', 'LIKE', '%' . $search . '%')
                    ->orWhere('roles.name', 'LIKE', '%' . $search . '%');
            });
        }
        $data = $data->paginate(self::limit($query));

        if ($data->count() > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['getActiveStatusList'] = self::getActiveStatusList();
            $response['getGenderList'] = self::getGenderList();
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
        $response['getGenderList'] = self::getGenderList();
        $response['getActiveStatusList'] = self::getActiveStatusList();
        $response['getRoleList'] = Role::where('active_status', 1)->select('id','name')->get();

        return response($response, 200);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'role_id' => 'required|numeric',
            'name' => 'required|string|max:100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'gender' => 'required|numeric',
            'dob' => 'nullable',
            'phone' => 'nullable',
            'address' => 'nullable',
            'active_status' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response(['errors' => $validator->errors()->all()], 422);
        }
        $request['uuid'] = Str::uuid()->toString();
        $request['password'] = Hash::make($request['password']);
        $request['created_by'] = Auth()->user()->id;
        $request['remember_token'] = Str::random(10);
        $response = User::create($request->toArray());
        return response($response, 200);
    }

    public function getUserInfo($uuid){
        $data = User::where('uuid',$uuid)->first();
        if ($data) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['getGenderList'] = self::getGenderList();
            $response['getActiveStatusList'] = self::getActiveStatusList();
            $response['getRoleList'] = Role::where('active_status', 1)->select('id','name')->get();
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
            'role_id' => 'required|numeric',
            'name' => 'required|string|max:100',
            'email' => "required|string|email|max:100|unique:users,email,$request->id",
            'password' => 'nullable|string|min:6|confirmed',
            'gender' => 'required|numeric',
            'dob' => 'nullable',
            'phone' => 'nullable',
            'address' => 'nullable',
            'active_status' => 'required|numeric',
            'id' => "required",
            'uuid' => "required",
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

        $data = User::where('uuid', $request->uuid);
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

    public function logout(Request $request)
    {
        $token = $request->user()->token();
        $token->revoke();
        $response = [
            "status" => "success",
            'message' => 'You have been successfully logged out!'
        ];
        return response($response, 200);
    }

    public function changePassword(Request $request)
    {
        $user = Auth::user();

        // Validate the input data
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        // Check if the old password matches
        if (!Hash::check($request->input('old_password'), $user->password)) {
            return response()->json(['message' => 'Invalid old password'], 422);
        }

        // Update the user's password
        $user->password = Hash::make($request->input('new_password'));
        $user->save();
        $response = [
            "status" => "success",
            "message" => 'Password updated successfully'
        ];
        return response($response, 200);
    }
}
