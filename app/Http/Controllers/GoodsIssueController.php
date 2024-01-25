<?php

namespace App\Http\Controllers;

use App\Models\Goods_issue_mst;
use App\Models\Goods_issue_dtl;
use App\Models\Order_dtl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class GoodsIssueController extends Controller
{
    public function add()
    {
        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['company_list'] = self::getPartyList(1);
        $response['buyer_list'] = self::getPartyList(2);
        return response($response, 200);
    }

    public function index(Request $request)
    {
        $query = $request->all();
        $search = $request->input('search');
        $data = Goods_issue_mst::select('goods_issue_msts.id', 'goods_issue_msts.uuid', 'goods_issue_msts.delivery_date', 'goods_issue_msts.goods_issue_no', 'a.name as company_name', 'b.name as buyer_name', DB::raw("group_concat(DISTINCT goods_issue_dtls.style SEPARATOR', ') as style"))
            ->join('parties as a', 'goods_issue_msts.company_id', '=', 'a.id')
            ->join('parties as b', 'goods_issue_msts.buyer_id', '=', 'b.id')
            ->join('goods_issue_dtls', function ($join) {
                $join->on('goods_issue_msts.id', '=', 'goods_issue_dtls.goods_issue_id')
                    ->where('goods_issue_dtls.active_status', 1);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('goods_issue_msts.goods_issue_no', 'LIKE', '%' . $search . '%')
                        ->orWhereDate('goods_issue_msts.delivery_date', 'LIKE', '%' . $search . '%')
                        ->orWhere('goods_issue_msts.challan_no', 'LIKE', '%' . $search . '%')
                        ->orWhere('a.name', 'LIKE', '%' . $search . '%')
                        ->orWhere('b.name', 'LIKE', '%' . $search . '%')
                        ->orWhere(function ($query) use ($search) {
                            $query->where('goods_issue_dtls.style', 'LIKE', "%$search%");
                        });
                });
            })
            ->groupBy('goods_issue_msts.id', 'goods_issue_msts.uuid', 'goods_issue_msts.delivery_date', 'goods_issue_msts.goods_issue_no', 'company_name', 'buyer_name')
            ->where('goods_issue_msts.active_status', 1)->orderBy('goods_issue_msts.id', 'desc')
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
            'company_id' => "required|numeric|max:99999999",
            'buyer_id' => "required|numeric|max:99999999",
            'order_id' => "required|numeric|max:99999999",
            'delivery_date' => "required|date",
            'challan_no' => "nullable|string|max:200",
            'file_image' => "nullable|mimes:png,jpeg,jpg,gif,doc,docs,pdf,xlsx,xls|max:5120",
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
            'company_id' => $request->company_id,
            'buyer_id' => $request->buyer_id,
            'order_id' => $request->order_id,
            'delivery_date' => $request->delivery_date,
            'challan_no' => $request->challan_no,
            'remarks' => $request->remarks,
            'created_by' => $user_id,
            'uuid' => Str::uuid()->toString(),
            'goods_issue_no' => self::get_system_no('goods_issue_msts', 'gi'),
        ];

        if ($files = $request->file("file_image")) {
            $path = 'challan';
            $attachment = self::uploadImage($files, $path);
            $request_data['file_image'] = $attachment;
        }

        DB::beginTransaction();
        $data_mst = Goods_issue_mst::create($request_data);
        $data_dtls_array = [];
        foreach ($request->data_dtls as $row) {
            if ($row["order_dtls_id"] && $row["qnty"]) {
                $data_dtls_arr = [
                    'goods_issue_id' => $data_mst->id,
                    'order_dtls_id' => $row["order_dtls_id"],
                    'product_id' => $row["product_id"],
                    'style' => $row["style"],
                    'size_id' => $row["size_id"],
                    'color_id' => $row["color_id"],
                    'unit_id' => $row["unit_id"],
                    'qnty' => $row["qnty"],
                    'remarks' => $row["remark"],
                    'created_by' => $user_id,
                    'created_at' => now(),
                ];
                $data_dtls_array[] = $data_dtls_arr;
            }
        }

        $data_dtls = false;
        if (count($data_dtls_array) > 0) {
            $data_dtls = Goods_issue_dtl::insert($data_dtls_array);
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
            'company_id' => "required|numeric|max:99999999",
            'buyer_id' => "required|numeric|max:99999999",
            'order_id' => "required|numeric|max:99999999",
            'delivery_date' => "required|date",
            'challan_no' => "nullable|string|max:200",
            'file_image' => "nullable|mimes:png,jpeg,jpg,gif,doc,docs,pdf,xlsx,xls|max:5120",
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
            'company_id' => $request->company_id,
            'buyer_id' => $request->buyer_id,
            'order_id' => $request->order_id,
            'delivery_date' => $request->delivery_date,
            'challan_no' => $request->challan_no,
            'remarks' => $request->remarks,
            'created_by' => $user_id,
        ];
        if ($files = $request->file("file_image")) {
            $path = 'challan';
            $attachment = self::uploadImage($files, $path);
            $request_data['file_image'] = $attachment;
        }

        DB::beginTransaction();
        $data_mst = Goods_issue_mst::where('id', $mst_id)->update($request_data);
        $issDtlIds = Goods_issue_dtl::where('goods_issue_id', $mst_id)->where('active_status', 1)->pluck('id')->all();

        $data_dtls = true;
        $active_dtls_id=array();
        foreach ($request->data_dtls as $row) {

            if ($row["order_dtls_id"] && $row["dtls_id"] && $row["qnty"]) {
                $active_dtls_id[] = $row["dtls_id"];
                $data_dtls_arr = [
                    'qnty' => $row["qnty"],
                    'remarks' => $row["remark"],
                    'updated_by' => $user_id,
                    'updated_at' => now(),
                ];

                if ($data_dtls) {
                    $data_dtls = Goods_issue_dtl::where('id', $row["dtls_id"])->update($data_dtls_arr);
                }

                if ($row["dtls_id"]) {
                    $ord_qnty = Order_dtl::where('active_status', 1)->where('id', $row["order_dtls_id"])->sum('qnty');
                    $prv_del = Goods_issue_dtl::where('active_status', 1)->whereNotIn('id', [$row["dtls_id"]])->where('order_dtls_id', $row["order_dtls_id"])->sum('qnty');

                    $total_del_qnty = $prv_del + $row["qnty"];
                    if ($total_del_qnty > $ord_qnty) {
                        DB::rollBack();
                        $response['status'] = 'error';
                        $response['message'] = 'Current Delivery Qunatity Over Order Qunatity';
                        return response($response, 422);
                    } else if ($total_del_qnty == $ord_qnty) {
                        Order_dtl::where('id', $row["order_dtls_id"])->update(['order_status' => 2]);
                    }
                } else {
                    $ord_qnty = Order_dtl::where('active_status', 1)->where('id', $row["order_dtls_id"])->sum('qnty');
                    $prv_del = Goods_issue_dtl::where('active_status', 1)->where('order_dtls_id', $row["order_dtls_id"])->sum('qnty');

                    $total_del_qnty = $prv_del + $row["qnty"];
                    if ($total_del_qnty > $ord_qnty) {
                        DB::rollBack();
                        $response['status'] = 'error';
                        $response['message'] = 'Current Delivery Qunatity Over Order Qunatity';
                        return response($response, 422);
                    } else if ($total_del_qnty == $ord_qnty) {
                        Order_dtl::where('id', $row["order_dtls_id"])->update(['order_status' => 2]);
                    }
                }
            }
        }

        $data_del_dtls = true;
        $issDtlIdsDiffArr = array_diff($issDtlIds, $active_dtls_id);
        if (count($issDtlIdsDiffArr) > 0) {
            $delete_info = [
                'active_status' => 2,
                'updated_by' => Auth()->user()->id,
                'updated_at' => now()
            ];
            $data_del_dtls = Goods_issue_dtl::whereIn('id', $issDtlIdsDiffArr)->update($delete_info);
        }


        if ($data_mst && $data_del_dtls &&$data_dtls) {
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
        $data = Goods_issue_mst::where('uuid', $uuid)->first();
        $update_mst = Goods_issue_mst::findOrFail($data->id)->update([
            'active_status' => 2,
            'updated_by' => Auth()->user()->id,
        ]);
        $update_dtls = Goods_issue_dtl::where('goods_issue_id', $data->id)->where('active_status', 1)->update([
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

    public function getGdIssueInfo($uuid)
    {
        // $data = Goods_issue_mst::where('id', $request->id)->first();
        $data = Goods_issue_mst::where('uuid', $uuid)->with(['company_info', 'buyer_info', 'order_info', 'data_dtls.product_info', 'data_dtls.color_info', 'data_dtls.color_info', 'data_dtls.size_info', 'data_dtls.unit_info', 'data_dtls.gd_issue_info', 'data_dtls.order_dtls_info'])->where('active_status', 1)->first();
        // $data_dtls = Goods_issue_dtl::where('goods_issue_id', $request->id)->with('product_info')->with('color_info')->with('size_info')->with('unit_info')->where('active_status', 1)->get();

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
