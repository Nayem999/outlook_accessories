<?php

namespace App\Http\Controllers;

use App\Models\Sample_mst;
use App\Models\Sample_dtl;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SampleController extends Controller
{
    public function add()
    {
        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['approved_list'] = self::getApprovedList();
        $response['company_list'] = self::getPartyList(1);
        $response['buyer_list'] = self::getPartyList(2);
        $response['product_list'] = self::getProductList();
        $response['color_list'] = self::getColorList();
        $response['size_list'] = self::getSizeList();
        $response['unit_list'] = self::getUnitList();

        return response($response, 200);
    }

    public function index(Request $request)
    {
        $query = $request->all();
        $search = $request->input('search');
        $data = Sample_mst::select('sample_msts.*', 'a.name as company_name', 'b.name as buyer_name')
            ->join('parties as a', 'sample_msts.company_id', '=', 'a.id')
            ->join('parties as b', 'sample_msts.buyer_id', '=', 'b.id');

        if ($search) {
            $data = $data->where(function ($query) use ($search) {
                $query->where('sample_msts.sample_no', 'LIKE', '%' . $search . '%')
                    ->orWhereDate('sample_msts.sample_date', 'LIKE', '%' . $search . '%')
                    ->orWhere('a.name', 'LIKE', '%' . $search . '%')
                    ->orWhere('b.name', 'LIKE', '%' . $search . '%');
            });
        }
        $data = $data->orderBy('sample_msts.id', 'desc')->where('sample_msts.active_status', 1)->paginate(self::limit($query));

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
            'company_id' => "required|numeric",
            'buyer_id' => "required|numeric",
            'inquire_id' => "required|numeric",
            'sample_date' => "required|date",
            'file_image' => "nullable|mimes:png,jpeg,jpg,gif,doc,docs,pdf,xlsx,xls",
            'remarks' => "nullable|string|max:200",
        ]);
        if ($validator->fails()) {
            $response = [
                "status" => "error",
                "message" => "Something went to wrong !",
                'error' => $validator->errors()->all()
            ];
            return response($response, 422);
        }
        $user_id = Auth()->user()->id;
        $request_data = [
            'company_id' => $request->company_id,
            'buyer_id' => $request->buyer_id,
            'inquire_id' => $request->inquire_id,
            'sample_date' => $request->sample_date,
            'remarks' => $request->remarks,
            'created_by' => $user_id,
            'uuid' => Str::uuid()->toString(),
            'sample_no' => self::get_system_no('sample_msts', 'sml'),
        ];

        if ($files = $request->file("file_image")) {
            $path = 'sample';
            $attachment = self::uploadImage($files, $path);
            $request_data['file_image'] = $attachment;
        }

        DB::beginTransaction();
        $data_mst = Sample_mst::create($request_data);
        $data_dtls_array = [];
        foreach ($request->data_dtls as $key => $row) {
            if ($row["product_id"] && $row["qnty"]) {
                $data_dtls_arr = [
                    'sample_id' => $data_mst->id,
                    'inquire_mst_id' => $request->inquire_id,
                    'inquire_dtls_id' => $row["inquire_dtls_id"],
                    'product_id' => $row["product_id"],
                    'style' => $row["style"],
                    'size_id' => $row["size_id"],
                    'color_id' => $row["color_id"],
                    'unit_id' => $row["unit_id"],
                    'qnty' => $row["qnty"],
                    'sample_status' => $row["sample_status"],
                    'remarks' => $row["remark"],
                    'log_date' => Carbon::now(),
                    'created_by' => $user_id,
                ];
                if (is_object($request->data_dtls[$key]["image"])) {
                    $files = $row["image"];
                    $path = 'sample';
                    $attachment = self::uploadImage($files, $path);
                    $data_dtls_arr['file_image'] = $attachment;
                } else {
                    $data_dtls_arr['file_image'] = '';
                }

                $data_dtls_array[] = $data_dtls_arr;
            }
        }

        $data_dtls = false;
        if (count($data_dtls_array) > 0) {
            $data_dtls = Sample_dtl::insert($data_dtls_array);
        }

        if ($data_mst && $data_dtls) {
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
            'mst_id' => "required|numeric",
            'company_id' => "required|numeric",
            'buyer_id' => "required|numeric",
            'inquire_id' => "required|numeric",
            'sample_date' => "required|date",
            'file_image' => "nullable|mimes:png,jpeg,jpg,gif,doc,docs,pdf,xlsx,xls",
            'remarks' => "nullable|string|max:200",
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => "error",
                "message" => "Something went to wrong!",
                'errors' => $validator->errors()->all()
            ];
            return response($response, 422);
        }
        $mst_id = $request->mst_id;
        $user_id = Auth()->user()->id;
        $request_data = [
            'company_id' => $request->company_id,
            'buyer_id' => $request->buyer_id,
            'inquire_id' => $request->inquire_id,
            'sample_date' => $request->sample_date,
            'remarks' => $request->remarks,
            'updated_by' => $user_id,
        ];

        if ($files = $request->file("file_image")) {
            $path = 'sample';
            $attachment = self::uploadImage($files, $path);
            $request_data['file_image'] = $attachment;
        }

        DB::beginTransaction();
        $data_mst = Sample_mst::where('id', $mst_id)->update($request_data);

        if ($data_mst) {
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

    public function destroy($uuid)
    {
        DB::beginTransaction();
        $data = Sample_mst::where('uuid', $uuid)->first();
        $update_mst = Sample_mst::findOrFail($data->id)->update([
            'active_status' => 2,
            'updated_by' => Auth()->user()->id,
        ]);
        $update_dtls = Sample_dtl::where('sample_id', $data->id)->where('active_status', 1)->update([
            'active_status' => 2,
            'updated_by' => Auth()->user()->id,
        ]);

        if ($update_mst && $update_dtls) {
            DB::commit();
            $response['status'] = 'success';
            $response['message'] = 'Data Deleted successfully.';
            return response($response, 200);
        } else {
            DB::rollBack();
            $response['status'] = 'error';
            $response['message'] = 'Something went to wrong!';
            return response($response, 422);
        }
    }

    public function getSampleInfo($uuid)
    {
        $data_mst = Sample_mst::where('uuid', $uuid)->with('company_info')->with('buyer_info')->with('inquire_info')->where('active_status', 1)->first();
        $data_dtls = DB::table('sample_dtls')
            ->select('sample_id', 'inquire_mst_id', 'style', 'inquire_dtls_id', 'products.name as product_name', 'colors.name as color_name', 'sizes.name as size_name', 'units.name as unit_name')
            ->join('products', 'sample_dtls.product_id', '=', 'products.id')
            ->join('colors', 'sample_dtls.color_id', '=', 'colors.id')
            ->join('sizes', 'sample_dtls.size_id', '=', 'sizes.id')
            ->join('units', 'sample_dtls.unit_id', '=', 'units.id')
            ->where('sample_dtls.sample_id', $data_mst->id)
            ->where('sample_dtls.active_status', 1)
            ->groupBy('sample_id', 'inquire_mst_id', 'style','inquire_dtls_id', 'product_name', 'color_name', 'size_name', 'unit_name')
            ->get();

        if ($data_mst->count() > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['data'] = $data_mst;
            $response['data_dtls'] = $data_dtls;
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }

    public function getSampleLogInfo($inquire_dtls, $uuid)
    {
        $data_mst = Sample_mst::where('uuid', $uuid)->with('company_info')->with('buyer_info')->with('inquire_info')->where('active_status', 1)->first();
        $data_dtls = DB::table('sample_dtls')
            ->select('sample_dtls.*', 'products.name as product_name', 'colors.name as color_name', 'sizes.name as size_name', 'units.name as unit_name')
            ->join('products', 'sample_dtls.product_id', '=', 'products.id')
            ->join('colors', 'sample_dtls.color_id', '=', 'colors.id')
            ->join('sizes', 'sample_dtls.size_id', '=', 'sizes.id')
            ->join('units', 'sample_dtls.unit_id', '=', 'units.id')
            ->where('sample_dtls.sample_id', $data_mst->id)
            ->where('sample_dtls.inquire_dtls_id', $inquire_dtls)
            ->where('sample_dtls.active_status', 1)
            ->get();

        if ($data_mst->count() > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['approved_list'] = self::getApprovedList();
            $response['company_list'] = self::getPartyList(1);
            $response['buyer_list'] = self::getPartyList(2);
            $response['data_mst'] = $data_mst;
            $response['data_dtls'] = $data_dtls;
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }

    public function log_store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'sample_id' => "required|numeric",
            'inquire_mst_id' => "required|numeric",
            'inquire_dtls_id' => "required|numeric",
            'product_id' => "required|numeric",
            'style' => "nullable|string",
            'size_id' => "nullable|numeric",
            'color_id' => "nullable|numeric",
            'unit_id' => "nullable|numeric",
            'qnty' => "required|numeric",
            'sample_status' => "nullable|numeric",
            'file_image' => "nullable|mimes:png,jpeg,jpg,gif,doc,docs,pdf,xlsx,xls",
            'remarks' => "nullable|string|max:200",
        ]);
        if ($validator->fails()) {
            $response = [
                "status" => "error",
                "message" => "Something went to wrong !",
                'error' => $validator->errors()->all()
            ];
            return response($response, 422);
        }
        $user_id = Auth()->user()->id;
        $request_data = [
            'sample_id' => $request->sample_id,
            'inquire_mst_id' => $request->inquire_mst_id,
            'inquire_dtls_id' => $request->inquire_dtls_id,
            'product_id' => $request->product_id,
            'style' => $request->style,
            'size_id' => $request->size_id,
            'color_id' => $request->color_id,
            'unit_id' => $request->unit_id,
            'qnty' => $request->qnty,
            'sample_status' => $request->sample_status,
            'remarks' => $request->remark,
            'log_date' => Carbon::now(),
            'created_by' => $user_id,
        ];

        if ($files = $request->file("file_image")) {
            $path = 'sample';
            $attachment = self::uploadImage($files, $path);
            $request_data['file_image'] = $attachment;
        }

        DB::beginTransaction();
        $data_dtls = Sample_dtl::create($request_data);

        if ($data_dtls) {
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
}
