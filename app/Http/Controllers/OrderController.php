<?php

namespace App\Http\Controllers;

use App\Models\Order_mst;
use App\Models\Order_dtl;
use App\Models\Wo_mst;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function add()
    {
        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['season_list'] = self::getSeasonList();
        $response['season_year'] = self::getSeasonYear();
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
        $company_id = $request->input('company_id');
        $buyer_id = $request->input('buyer_id');

        $data = Order_mst::select('order_msts.*', 'a.name as company_name', 'b.name as buyer_name')
            ->join('parties as a', 'order_msts.company_id', '=', 'a.id')
            ->join('parties as b', 'order_msts.buyer_id', '=', 'b.id')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('order_msts.order_no', 'LIKE', "%$search%")
                        ->orWhereDate('order_msts.order_date', 'LIKE', "%$search%")
                        ->orWhere('a.name', 'LIKE', "%$search%")
                        ->orWhere('b.name', 'LIKE', "%$search%");
                });
            })
            ->when($company_id, function ($query) use ($company_id) {
                $query->where('order_msts.company_id', $company_id);
            })
            ->when($buyer_id, function ($query) use ($buyer_id) {
                $query->where('order_msts.buyer_id', $buyer_id);
            })
            ->orderBy('order_msts.id', 'desc')->where('order_msts.active_status', 1)->paginate(self::limit($query));

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
            'inquire_id' => "nullable|numeric",
            'order_date' => "required|date",
            'delivery_req_date' => "required|date",
            'merchandiser_name' => "nullable|string|max:100",
            'merchandiser_phone' => "nullable|string|max:30",
            'order_person' => "nullable|string|max:100",
            'attntion' => "nullable|string|max:100",
            'season_year' => "nullable|string|max:4",
            'season' => "nullable|numeric",
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
            'company_id' => $request->company_id,
            'buyer_id' => $request->buyer_id,
            'inquire_id' => $request->inquire_id,
            'order_date' => $request->order_date,
            'delivery_req_date' => $request->delivery_req_date,
            'merchandiser_name' => $request->merchandiser_name,
            'merchandiser_phone' => $request->merchandiser_phone,
            'order_person' => $request->order_person,
            'attntion' => $request->attntion,
            'season_year' => $request->season_year,
            'season' => $request->season,
            'remarks' => $request->remarks,
            'created_by' => $user_id,
            'uuid' => Str::uuid()->toString(),
            'order_no' => self::get_system_no('order_msts', 'po'),
        ];

        if ($files = $request->file("file_image")) {
            $path = 'order';
            $attachment = self::uploadImage($files, $path);
            $request_data['file_image'] = $attachment;
        }

        DB::beginTransaction();
        $data_mst = Order_mst::create($request_data);
        $data_dtls_array = [];
        foreach ($request->data_dtls as $key=>$row) {
            if ($row["product_id"] && $row["qnty"]) {
                $data_dtls_arr = [
                    'order_id' => $data_mst->id,
                    'product_id' => $row["product_id"],
                    'style' => $row["style"],
                    'size_id' => $row["size_id"],
                    'color_id' => $row["color_id"],
                    'unit_id' => $row["unit_id"],
                    'qnty' => $row["qnty"],
                    'remarks' => $row["remark"],
                    'created_by' => $user_id,
                ];
                if (is_object($request->data_dtls[$key]["image"])) {
                    $files = $row["inquire"];
                    $path = 'order';
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
            $data_dtls = Order_dtl::insert($data_dtls_array);
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
            'inquire_id' => "nullable|numeric",
            'order_date' => "required|date",
            'delivery_req_date' => "required|date",
            'merchandiser_name' => "nullable|string|max:100",
            'merchandiser_phone' => "nullable|string|max:30",
            'order_person' => "nullable|string|max:100",
            'attntion' => "nullable|string|max:100",
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
            'company_id' => $request->company_id,
            'buyer_id' => $request->buyer_id,
            'order_date' => $request->order_date,
            'inquire_id' => $request->inquire_id,
            'delivery_req_date' => $request->delivery_req_date,
            'merchandiser_name' => $request->merchandiser_name,
            'merchandiser_phone' => $request->merchandiser_phone,
            'order_person' => $request->order_person,
            'attntion' => $request->attntion,
            'season_year' => $request->season_year,
            'season' => $request->season,
            'remarks' => $request->remarks,
        ];
        $request_data['updated_by'] = $user_id;
        if ($files = $request->file("file_image")) {
            $path = 'order';
            $attachment = self::uploadImage($files, $path);
            $request_data['file_image'] = $attachment;
        }

        DB::beginTransaction();
        $data_mst = Order_mst::where('id', $mst_id)->update($request_data);
        $poDtlIds = Order_dtl::where('order_id', $mst_id)->where('active_status', 1)->pluck('id')->all();

        $data_dtls_insert = [];
        $active_dtls_id = array();
        foreach ($request->data_dtls as $key=>$row) {
            if ($row["product_id"] && $row["qnty"]) {
                $data_dtls_arr = [
                    'order_id' => $mst_id,
                    'inquire_dtls_id' => $row["inquire_dtls_id"],
                    'product_id' => $row["product_id"],
                    'style' => $row["style"],
                    'size_id' => $row["size_id"],
                    'color_id' => $row["color_id"],
                    'unit_id' => $row["unit_id"],
                    'qnty' => $row["qnty"],
                    'remarks' => $row["remark"],
                ];
                $attachment = '';
                if (is_object($request->data_dtls[$key]["image"])) {
                    $files = $row["image"];
                    $path = 'order';
                    $attachment = self::uploadImage($files, $path);
                }

                if ($row["dtls_id"]) {
                    if ($attachment) {
                        $data_dtls_arr['file_image'] = $attachment;
                    }
                    $data_dtls_arr['updated_by'] = $user_id;
                    Order_dtl::where('id', $row["dtls_id"])->update($data_dtls_arr);
                    $active_dtls_id[] = $row["dtls_id"];
                } else {
                    $data_dtls_arr['file_image'] = $attachment;
                    $data_dtls_arr['created_by'] = $user_id;
                    $data_dtls_insert[] = $data_dtls_arr;
                }
            }
        }

        $data_dtls = $data_del_dtls = true;

        $poDtlIdsDiffArr = array_diff($poDtlIds, $active_dtls_id);
        if (count($poDtlIdsDiffArr) > 0) {
            $delete_info = [
                'active_status' => 2,
                'updated_by' => Auth()->user()->id,
            ];
            $data_del_dtls = Order_dtl::whereIn('id', $poDtlIdsDiffArr)->update($delete_info);
        }

        if (count($data_dtls_insert) > 0) {
            $data_dtls = Order_dtl::insert($data_dtls_insert);
        }

        if ($data_mst && $data_del_dtls && $data_dtls) {
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
        $data = Order_mst::where('uuid', $uuid)->first();
        $wo_add = Wo_mst::where('order_id', $data->id)->where('active_status', 1)->first();
        if ($wo_add) {
            $response['status'] = 'error';
            $response['message'] = 'PO can not delete. This PO found in WO page. WO No: ' . $wo_add->wo_no;
            return response($response, 422);
        }

        DB::beginTransaction();
        $update_mst = Order_mst::findOrFail($data->id)->update([
            'active_status' => 2,
            'updated_by' => Auth()->user()->id,
        ]);
        $update_dtls = Order_dtl::where('order_id', $data->id)->where('active_status', 1)->update([
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

    public function getOrderInfo($uuid)
    {
        $data = Order_mst::where('uuid', $uuid)->with(['company_info', 'buyer_info', 'inquire_info', 'data_dtls.color_info', 'data_dtls.size_info', 'data_dtls.unit_info', 'data_dtls.product_info'])->where('active_status', 1)->first();
        // $data_dtls = Order_dtl::where('order_id', $data_mst->id)->with('product_info')->with('color_info')->with('size_info')->with('unit_info')->where('active_status', 1)->get();

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
