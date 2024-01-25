<?php

namespace App\Http\Controllers;

use App\Models\Pi_dtl;
use App\Models\Wo_mst;
use App\Models\Wo_dtl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class WoController extends Controller
{
    public function add()
    {
        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['company_list'] = self::getPartyList(1);
        $response['buyer_list'] = self::getPartyList(2);
        $response['supplier_list'] = self::getPartyList(3);
        $response['currency_list'] = self::getCurrencyList();
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
        $supplier_id = $request->input('supplier_id');

        $data = Wo_mst::select('wo_msts.id', 'wo_msts.uuid', 'wo_msts.wo_date', 'wo_msts.wo_no', 'a.name as supplier_name', DB::raw("group_concat(DISTINCT wo_dtls.style SEPARATOR', ') as style"))
            ->join('parties as a', 'wo_msts.supplier_id', '=', 'a.id')
            ->join('wo_dtls', function ($join) {
                $join->on('wo_msts.id', '=', 'wo_dtls.wo_id')
                    ->where('wo_dtls.active_status', 1);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('wo_msts.wo_no', 'LIKE', '%' . $search . '%')
                        ->orWhereDate('wo_msts.wo_date', 'LIKE', '%' . $search . '%')
                        ->orWhere('a.name', 'LIKE', '%' . $search . '%')
                        ->orWhere(function ($query) use ($search) {
                            $query->where('wo_dtls.style', 'LIKE', "%$search%");
                        });
                });
            })
            ->when($supplier_id, function ($query) use ($supplier_id) {
                $query->where('wo_msts.supplier_id', $supplier_id);
            })
            ->groupBy('wo_msts.id', 'wo_msts.uuid', 'wo_msts.wo_date', 'wo_msts.wo_no', 'supplier_name')
            ->orderBy('wo_msts.id', 'desc')->where('wo_msts.active_status', 1)
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
            'supplier_id' => "required|numeric|max:99999999",
            'order_id' => "required|numeric|max:99999999",
            'wo_date' => "required|date",
            'delivery_req_date' => "required|date",
            'currency_id' => "required|numeric|max:999",
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
            'supplier_id' => $request->supplier_id,
            'order_id' => $request->order_id,
            'delivery_req_date' => $request->delivery_req_date,
            'wo_date' => $request->wo_date,
            'currency_id' => $request->currency_id,
            'remarks' => $request->remarks,
            'created_by' => $user_id,
            'uuid' => Str::uuid()->toString(),
            'wo_no' => self::get_system_no('wo_msts', 'wo'),
        ];

        DB::beginTransaction();
        $data_mst = Wo_mst::create($request_data);
        $data_dtls_array = [];
        foreach ($request->data_dtls as $row) {
            if ($row["order_dtls_id"] && $row["qnty"] && $row["product_id"]) {
                $data_dtls_arr = [
                    'wo_id' => $data_mst->id,
                    'order_id' => $data_mst->order_id,
                    'order_dtls_id' => $row["order_dtls_id"],
                    'product_id' => $row["product_id"],
                    'style' => $row["style"],
                    'size_id' => $row["size_id"],
                    'color_id' => $row["color_id"],
                    'unit_id' => $row["unit_id"],
                    'qnty' => $row["qnty"],
                    'price' => $row["price"],
                    'amount' => $row["amount"],
                    'remarks' => $row["remark"],
                    'created_by' => $user_id,
                    'created_at' => now(),
                ];

                $data_dtls_array[] = $data_dtls_arr;
            }
        }

        $data_dtls = false;
        if (count($data_dtls_array) > 0) {
            $data_dtls = Wo_dtl::insert($data_dtls_array);
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
            'supplier_id' => "required|numeric|max:99999999",
            'order_id' => "required|numeric|max:99999999",
            'wo_date' => "required|date",
            'delivery_req_date' => "required|date",
            'currency_id' => "required|numeric|max:999",
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
            'supplier_id' => $request->supplier_id,
            'order_id' => $request->order_id,
            'delivery_req_date' => $request->delivery_req_date,
            'wo_date' => $request->wo_date,
            'currency_id' => $request->currency_id,
            'remarks' => $request->remarks,
        ];
        $request_data['updated_by'] = $user_id;

        DB::beginTransaction();
        $data_mst = Wo_mst::where('id', $mst_id)->update($request_data);
        $woDtlIds = Wo_dtl::where('wo_id', $mst_id)->where('active_status', 1)->pluck('id')->all();

        $data_dtls_insert = [];
        $active_dtls_id = array();
        foreach ($request->data_dtls as $row) {
            if ($row["order_dtls_id"] && $row["qnty"] && $row["product_id"]) {
                $data_dtls_arr = [
                    'wo_id' => $mst_id,
                    'order_dtls_id' => $row["order_dtls_id"],
                    'product_id' => $row["product_id"],
                    'style' => $row["style"],
                    'size_id' => $row["size_id"],
                    'color_id' => $row["color_id"],
                    'unit_id' => $row["unit_id"],
                    'qnty' => $row["qnty"],
                    'price' => $row["price"],
                    'amount' => $row["amount"],
                    'remarks' => $row["remark"],
                ];

                if ($row["dtls_id"]) {
                    $data_dtls_arr['updated_by'] = $user_id;
                    $data_dtls_arr['updated_at'] = now();
                    Wo_dtl::where('id', $row["dtls_id"])->update($data_dtls_arr);
                    $active_dtls_id[] = $row["dtls_id"];
                } else {
                    $data_dtls_arr['created_by'] = $user_id;
                    $data_dtls_arr['created_at'] = now();
                    $data_dtls_insert[] = $data_dtls_arr;
                }

            }
        }

        $data_dtls = $data_del_dtls = true;

        $woDtlIdsDiffArr = array_diff($woDtlIds, $active_dtls_id);
        if (count($woDtlIdsDiffArr) > 0) {
            $delete_info = [
                'active_status' => 2,
                'updated_by' => Auth()->user()->id,
                'updated_at' => now()
            ];
            $data_del_dtls = Wo_dtl::whereIn('id', $woDtlIdsDiffArr)->update($delete_info);
        }

        if (count($data_dtls_insert) > 0) {
            $data_dtls = Wo_dtl::insert($data_dtls_insert);
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
        $data = Wo_mst::where('uuid', $uuid)->first();
        $pi_add = Pi_dtl::where('wo_id', $data->id)->where('pi_dtls.active_status', 1)->join('pi_msts', 'pi_msts.id', '=', 'Pi_dtls.pi_id')->first();
        if ($pi_add) {
            $response['status'] = 'error';
            $response['message'] = 'WO can not delete. This WO found in PI page. PI No: ' . $pi_add->pi_no;
            return response($response, 422);
        }

        DB::beginTransaction();
        $update_mst = Wo_mst::findOrFail($data->id)->update([
            'active_status' => 2,
            'updated_by' => Auth()->user()->id,
        ]);
        $update_dtls = Wo_dtl::where('wo_id', $data->id)->where('active_status', 1)->update([
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

    public function getWoInfo($uuid)
    {
        $data = Wo_mst::where('uuid', $uuid)->with(['supplier_info', 'company_info', 'buyer_info', 'buyer_info', 'order_info', 'data_dtls.color_info', 'data_dtls.size_info', 'data_dtls.unit_info', 'data_dtls.product_info', 'data_dtls.gd_rcv_info'])->where('active_status', 1)->first();
        // $data_dtls = Wo_dtl::where('wo_id', $data_mst->id)->with('product_info')->with('color_info')->with('size_info')->with('unit_info')->where('active_status', 1)->get();

        if ($data) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['currency_list'] = self::getCurrencyList();
            $response['currency_sign_list'] = self::getCurrencySignList();
            $response['response_data'] = $data;
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }
}
