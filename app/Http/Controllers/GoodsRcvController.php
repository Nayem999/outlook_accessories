<?php

namespace App\Http\Controllers;

use App\Models\Goods_rcv_mst;
use App\Models\Goods_rcv_dtl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class GoodsRcvController extends Controller
{
    public function add()
    {
        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['supplier_list'] = self::getPartyList(3);
        return response($response, 200);
    }

    public function index(Request $request)
    {
        $query = $request->all();
        $search = $request->input('search');
        // $data = Goods_rcv_mst::with('supplier_info')->with('wo_info');

        $data = Goods_rcv_mst::select('goods_rcv_msts.id', 'goods_rcv_msts.uuid', 'goods_rcv_msts.rcv_date', 'goods_rcv_msts.goods_rcv_no', 'a.name as supplier_name', DB::raw("group_concat(DISTINCT goods_rcv_dtls.style SEPARATOR', ') as style"))
            ->join('parties as a', 'goods_rcv_msts.supplier_id', '=', 'a.id')
            ->join('goods_rcv_dtls', function ($join) {
                $join->on('goods_rcv_msts.id', '=', 'goods_rcv_dtls.goods_rcv_id')
                    ->where('goods_rcv_dtls.active_status', 1);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('goods_rcv_msts.goods_rcv_no', 'LIKE', '%' . $search . '%')
                        ->orWhereDate('goods_rcv_msts.rcv_date', 'LIKE', '%' . $search . '%')
                        ->orWhere('a.name', 'LIKE', '%' . $search . '%')
                        ->orWhere(function ($query) use ($search) {
                            $query->where('goods_rcv_dtls.style', 'LIKE', "%$search%");
                        });
                });
            })
            ->groupBy('goods_rcv_msts.id', 'goods_rcv_msts.uuid', 'goods_rcv_msts.rcv_date', 'goods_rcv_msts.goods_rcv_no', 'supplier_name')
            ->where('goods_rcv_msts.active_status', 1)->orderBy('goods_rcv_msts.id', 'desc')
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

    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'supplier_id' => "required|numeric",
            'wo_id' => "required|numeric",
            'rcv_date' => "required|date",
            'challan_no' => "nullable|string|max:200",
            'file_image' => "nullable|mimes:png,jpeg,jpg,gif,doc,docs,pdf,xlsx,xls",
            'remarks' => "nullable|string|max:200",
        ]);
        if ($validator->fails()) {
            $response = [
                "status" => "error",
                'message' => $validator->errors()->all()
            ];
            return response($response, 422);
        }

        $user_id = Auth()->user()->id;
        $request_data = [
            'supplier_id' => $request->supplier_id,
            'wo_id' => $request->wo_id,
            'rcv_date' => $request->rcv_date,
            'challan_no' => $request->challan_no,
            'remarks' => $request->remarks,
            'created_by' => $user_id,
            'uuid' => Str::uuid()->toString(),
            'goods_rcv_no' => self::get_system_no('goods_rcv_msts', 'gr'),
        ];

        if ($files = $request->file("file_image")) {
            $path = 'challan';
            $attachment = self::uploadImage($files, $path);
            $request_data['file_image'] = $attachment;
        }

        DB::beginTransaction();
        $data_mst = Goods_rcv_mst::create($request_data);
        $data_dtls_array = [];
        foreach ($request->data_dtls as $row) {
            if ($row["wo_dtls_id"] && $row["qnty"]) {
                $data_dtls_arr = [
                    'goods_rcv_id' => $data_mst->id,
                    'wo_dtls_id' => $row["wo_dtls_id"],
                    'product_id' => $row["product_id"],
                    'style' => $row["style"],
                    'size_id' => $row["size_id"],
                    'color_id' => $row["color_id"],
                    'unit_id' => $row["unit_id"],
                    'qnty' => $row["qnty"],
                    'remarks' => $row["remark"],
                    'created_by' => $user_id,
                ];
                $data_dtls_array[] = $data_dtls_arr;
            }
        }

        $data_dtls = false;
        if (count($data_dtls_array) > 0) {
            $data_dtls = Goods_rcv_dtl::insert($data_dtls_array);
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
            'supplier_id' => "required|numeric",
            'wo_id' => "required|numeric",
            'rcv_date' => "required|date",
            'challan_no' => "nullable|string|max:200",
            'file_image' => "nullable|mimes:png,jpeg,jpg,gif,doc,docs,pdf,xlsx,xls",
            'remarks' => "nullable|string|max:200",
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => "error",
                'message' => $validator->errors()->all()
            ];
            return response($response, 422);
        }
        $mst_id = $request->mst_id;
        $user_id = Auth()->user()->id;
        $request_data = [
            'rcv_date' => $request->rcv_date,
            'challan_no' => $request->challan_no,
            'remarks' => $request->remarks,
            'updated_by' => $user_id,
        ];
        if ($files = $request->file("file_image")) {
            $path = 'challan';
            $attachment = self::uploadImage($files, $path);
            $request_data['file_image'] = $attachment;
        }


        DB::beginTransaction();
        $data_mst = Goods_rcv_mst::where('id', $mst_id)->update($request_data);
        $data_dtls_insert = [];
        $data_dtls = $data_del_dtls = true;
        foreach ($request->data_dtls as $row) {
            if ($row["wo_dtls_id"] && $row["qnty"]) {
                $data_dtls_arr = [
                    'goods_rcv_id' => $mst_id,
                    'wo_dtls_id' => $row["wo_dtls_id"],
                    'product_id' => $row["product_id"],
                    'style' => $row["style"],
                    'size_id' => $row["size_id"],
                    'color_id' => $row["color_id"],
                    'unit_id' => $row["unit_id"],
                    'qnty' => $row["qnty"],
                    'remarks' => $row["remark"],
                ];

                if ($row["dtls_id"]) {
                    $data_dtls_arr['updated_by'] = $user_id;
                    if ($data_dtls) {
                        $data_dtls = Goods_rcv_dtl::where('id', $row["dtls_id"])->update($data_dtls_arr);
                    }
                    $active_dtls_id[] = $row["dtls_id"];
                } else {
                    $data_dtls_arr['created_by'] = $user_id;
                    $data_dtls_insert[] = $data_dtls_arr;
                }
            }
        }

        $gdRcvDtlIds = Goods_rcv_dtl::where('goods_rcv_id', $mst_id)->where('active_status', 1)->pluck('id')->all();
        $gdRcvDtlIdsDiffArr = array_diff($gdRcvDtlIds, $active_dtls_id);
        if (count($gdRcvDtlIdsDiffArr) > 0) {
            $delete_info = [
                'active_status' => 2,
                'updated_by' => Auth()->user()->id,
            ];
            $data_del_dtls = Goods_rcv_dtl::whereIn('id', $gdRcvDtlIdsDiffArr)->update($delete_info);
        }

        if (count($data_dtls_insert) > 0 && $data_dtls) {
            $data_dtls = Goods_rcv_dtl::insert($data_dtls_insert);
        }

        if ($data_mst && $data_dtls && $data_del_dtls) {
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
        $data = Goods_rcv_mst::where('uuid', $uuid)->first();
        $update_mst = Goods_rcv_mst::findOrFail($data->id)->update([
            'active_status' => 2,
            'updated_by' => Auth()->user()->id,
        ]);
        $update_dtls = Goods_rcv_dtl::where('goods_rcv_id', $data->id)->where('active_status', 1)->update([
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

    public function getGdRcvInfo($uuid)
    {
        $data = Goods_rcv_mst::where('uuid', $uuid)->with(['supplier_info', 'wo_info', 'data_dtls.product_info', 'data_dtls.color_info', 'data_dtls.size_info', 'data_dtls.unit_info', 'data_dtls.gd_rcv_info', 'data_dtls.wo_dtls_info'])->where('active_status', 1)->first();
        // $data_dtls = Goods_rcv_dtl::where('goods_rcv_id', $request->id)->with('product_info')->with('color_info')->with('size_info')->with('unit_info')->where('active_status', 1)->get();

        if ($data) {
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
