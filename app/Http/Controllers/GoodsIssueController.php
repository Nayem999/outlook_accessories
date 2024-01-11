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
        $data = Goods_issue_mst::select('goods_issue_msts.*','a.name as company_name','b.name as buyer_name')
        ->join('parties as a', 'goods_issue_msts.company_id', '=', 'a.id')
        ->join('parties as b', 'goods_issue_msts.buyer_id', '=', 'b.id');

        if ($search) {
            $data = $data->where(function ($query) use ($search) {
                $query->where('goods_issue_msts.goods_issue_no', 'LIKE', '%' . $search . '%')
                ->orWhereDate('goods_issue_msts.delivery_date', 'LIKE', '%' . $search . '%')
                ->orWhere('goods_issue_msts.challan_no', 'LIKE', '%' . $search . '%')
                ->orWhere('a.name', 'LIKE', '%' . $search . '%')
                ->orWhere('b.name', 'LIKE', '%' . $search . '%');
            });
        }
        $data = $data->where('goods_issue_msts.active_status', 1)->orderBy('goods_issue_msts.id', 'desc')->paginate(self::limit($query));

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
            'order_id' => "required|numeric",
            'delivery_date' => "required|date",
            'challan_no' => "nullable|string|max:200",
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
            'company_id' => "required|numeric",
            'buyer_id' => "required|numeric",
            'order_id' => "required|numeric",
            'delivery_date' => "required|date",
            'challan_no' => "nullable|string|max:200",
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

        $data_dtls = true;
        foreach ($request->data_dtls as $row) {

            if ($row["order_dtls_id"] && $row["dtls_id"] && $row["qnty"]) {

                $data_dtls_arr = [
                    'qnty' => $row["qnty"],
                    'remarks' => $row["remark"],
                    'updated_by' => $user_id,
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

        if ($data_mst && $data_dtls) {
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
        $data = Goods_issue_mst::where('uuid', $uuid)->with(['company_info','buyer_info','order_info','data_dtls.product_info','data_dtls.color_info','data_dtls.color_info','data_dtls.size_info','data_dtls.unit_info'])->where('active_status', 1)->first();
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
