<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Lc_pi;
use App\Models\Pi_mst;
use App\Models\Pi_dtl;
use App\Models\Wo_mst;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PiController extends Controller
{
    public function add()
    {
        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['currency_list'] = self::getCurrencyList();
        $response['company_list'] = self::getPartyList(1);
        $response['buyer_list'] = self::getPartyList(2);
        $response['bank_list'] = Bank::select('id', 'name')->where('active_status', 1)->get();

        return response($response, 200);
    }

    public function index(Request $request)
    {
        $query = $request->all();
        $search = $request->input('search');
        $company_id = $request->input('company_id');
        $buyer_id = $request->input('buyer_id');

        $data = Pi_mst::select('pi_msts.id', 'pi_msts.uuid', 'pi_msts.pi_date', 'pi_msts.pi_no', 'a.name as company_name', 'b.name as buyer_name', DB::raw("group_concat(DISTINCT pi_dtls.style SEPARATOR', ') as style"))
            ->join('parties as a', 'pi_msts.company_id', '=', 'a.id')
            ->join('parties as b', 'pi_msts.buyer_id', '=', 'b.id')
            ->join('pi_dtls', function ($join) {
                $join->on('pi_msts.id', '=', 'pi_dtls.pi_id')
                    ->where('pi_dtls.active_status', 1);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('pi_msts.pi_no', 'LIKE', "%$search%")
                        ->orWhereDate('pi_msts.pi_date', 'LIKE', "%$search%")
                        ->orWhere('a.name', 'LIKE', "%$search%")
                        ->orWhere('b.name', 'LIKE', "%$search%")
                        ->orWhere(function ($query) use ($search) {
                            $query->where('pi_dtls.style', 'LIKE', "%$search%");
                        });
                });
            })
            ->when($company_id, function ($query) use ($company_id) {
                $query->where('pi_msts.company_id', $company_id);
            })
            ->when($buyer_id, function ($query) use ($buyer_id) {
                $query->where('pi_msts.buyer_id', $buyer_id);
            })
            ->groupBy('pi_msts.id', 'pi_msts.uuid', 'pi_msts.pi_date', 'pi_msts.pi_no', 'company_name', 'buyer_name')
            ->orderBy('pi_msts.id', 'desc')->where('pi_msts.active_status', 1)->paginate(self::limit($query));

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
            'bank_id' => "required|numeric",
            'pi_date' => "required|date",
            'pi_validity_date' => "required|date",
            'last_shipment_date' => "required|date",
            'currency_id' => "required|numeric",
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
            'bank_id' => $request->bank_id,
            'pi_date' => $request->pi_date,
            'pi_validity_date' => $request->pi_validity_date,
            'last_shipment_date' => $request->last_shipment_date,
            'currency_id' => $request->currency_id,
            'remarks' => $request->remarks,
            'created_by' => $user_id,
            'uuid' => Str::uuid()->toString(),
            'pi_no' => self::get_system_no('pi_msts', 'pi'),
        ];

        DB::beginTransaction();
        $data_mst = Pi_mst::create($request_data);
        $data_dtls_array = [];
        $pi_value = 0;
        foreach ($request->data_dtls as $row) {
            if ($row["order_dtls_id"] && $row["qnty"]) {
                $data_dtls_arr = [
                    'pi_id' => $data_mst->id,
                    'wo_id' => $row["wo_id"],
                    'wo_dtls_id' => $row["wo_dtls_id"],
                    'order_id' => $row["order_id"],
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
                ];
                $data_dtls_array[] = $data_dtls_arr;
                $pi_value +=  $row["amount"];
            }
        }

        $data_dtls = false;
        if (count($data_dtls_array) > 0) {
            $data_dtls = Pi_dtl::insert($data_dtls_array);
        }

        $update_pi_value = Pi_mst::findOrFail($data_mst->id)->update([
            'pi_value' => $pi_value
        ]);

        if ($data_mst && $data_dtls && $update_pi_value) {
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
            'bank_id' => "required|numeric",
            'pi_date' => "required|date",
            'pi_validity_date' => "required|date",
            'last_shipment_date' => "required|date",
            'currency_id' => "required|numeric",
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
            'bank_id' => $request->bank_id,
            'pi_date' => $request->pi_date,
            'pi_validity_date' => $request->pi_validity_date,
            'last_shipment_date' => $request->last_shipment_date,
            'currency_id' => $request->currency_id,
            'remarks' => $request->remarks,
        ];
        $request_data['updated_by'] = $user_id;

        DB::beginTransaction();
        $data_mst = Pi_mst::where('id', $mst_id)->update($request_data);
        $piDtlIds = Pi_dtl::where('pi_id', $mst_id)->where('active_status', 1)->pluck('id')->all();

        $data_dtls_insert = [];
        $active_dtls_id = array();
        $pi_value = 0;
        foreach ($request->data_dtls as $row) {
            if ($row["order_dtls_id"] && $row["qnty"]) {
                $data_dtls_arr = [
                    'pi_id' => $mst_id,
                    'wo_id' => $row["wo_id"],
                    'wo_dtls_id' => $row["wo_dtls_id"],
                    'order_id' => $row["order_id"],
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
                $pi_value +=  $row["amount"];
                if ($row["dtls_id"]) {
                    $data_dtls_arr['updated_by'] = $user_id;
                    Pi_dtl::where('id', $row["dtls_id"])->update($data_dtls_arr);
                    $active_dtls_id[] = $row["dtls_id"];
                } else {
                    $data_dtls_arr['created_by'] = $user_id;
                    $data_dtls_insert[] = $data_dtls_arr;
                }
            }
        }

        $update_pi_value = Pi_mst::findOrFail($mst_id)->update([
            'pi_value' => $pi_value
        ]);

        $data_dtls = $data_del_dtls = true;

        $piDtlIdsDiffArr = array_diff($piDtlIds, $active_dtls_id);
        if (count($piDtlIdsDiffArr) > 0) {
            $delete_info = [
                'active_status' => 2,
                'updated_by' => Auth()->user()->id,
            ];
            $data_del_dtls = Pi_dtl::whereIn('id', $piDtlIdsDiffArr)->update($delete_info);
        }

        if (count($data_dtls_insert) > 0) {
            $data_dtls = Pi_dtl::insert($data_dtls_insert);
        }

        if ($data_mst && $data_del_dtls && $data_dtls && $update_pi_value) {
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
        $data = Pi_mst::where('uuid', $uuid)->first();
        $lc_add = Lc_pi::where('pi_mst_id', $data->id)->where('lc_pis.active_status', 1)->join('lcs', 'lcs.id', '=', 'lc_pis.lc_id')->first();
        if ($lc_add) {
            $response['status'] = 'error';
            $response['message'] = 'PI can not delete. This PI found in LC page. LC No: ' . $lc_add->lc_no;
            return response($response, 422);
        }

        DB::beginTransaction();
        $update_mst = Pi_mst::findOrFail($data->id)->update([
            'active_status' => 2,
            'updated_by' => Auth()->user()->id,
        ]);
        $update_dtls = Pi_dtl::where('pi_id', $data->id)->where('active_status', 1)->update([
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

    public function getPiInfo($uuid)
    {
        $data = Pi_mst::where('uuid', $uuid)->with(['company_info', 'buyer_info', 'bank_info', 'data_dtls.color_info', 'data_dtls.size_info', 'data_dtls.unit_info', 'data_dtls.product_info', 'data_dtls.po_info', 'data_dtls.wo_info'])->where('active_status', 1)->first();
        // $data_dtls = Pi_dtl::where('wo_id', $data_mst->id)->with('product_info')->with('color_info')->with('size_info')->with('unit_info')->with('wo_info')->where('active_status', 1)->get();

        if ($data) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['response_data'] = $data;
            $response['currency_list'] = self::getCurrencyList();
            $response['currency_sign_list'] = self::getCurrencySignList();
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }

    public function get_wo_add(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'company_id' => "required|numeric",
            'buyer_id' => "required|numeric",
        ]);
        if ($validator->fails()) {
            $response = [
                "status" => "error",
                'message' => $validator->errors()->all()
            ];
            return response($response, 422);
        }

        $query = $request->all();
        // $search = $request->input('search');
        $company_id = $request->input('company_id');
        $buyer_id = $request->input('buyer_id');

        /* $data = Wo_mst::select('wo_msts.id as wo_id', 'wo_msts.wo_no', 'wo_msts.wo_date', 'a.name as supplier_name', 'wo_dtls.id as wo_dtls_id', 'wo_dtls.order_id', 'wo_dtls.order_dtls_id', 'wo_dtls.product_id', 'wo_dtls.style', 'wo_dtls.size_id', 'wo_dtls.color_id', 'wo_dtls.unit_id', 'wo_dtls.qnty', 'wo_dtls.price', 'wo_dtls.amount', 'products.name as product_name', 'sizes.name as size_name', 'colors.name as color_name', 'units.name as unit_name')
            ->join('wo_dtls', 'wo_dtls.wo_id', '=', 'wo_msts.id')
            ->join('parties as a', 'wo_msts.supplier_id', '=', 'a.id')
            ->join('products', 'wo_dtls.product_id', '=', 'products.id')
            ->leftJoin('sizes', 'wo_dtls.size_id', '=', 'sizes.id')
            ->leftJoin('colors', 'wo_dtls.color_id', '=', 'colors.id')
            ->leftJoin('units', 'wo_dtls.unit_id', '=', 'units.id')
            ->when($company_id, function ($query) use ($company_id) {
                $query->where('wo_msts.company_id', $company_id);
            })
            ->when($buyer_id, function ($query) use ($buyer_id) {
                $query->where('wo_msts.buyer_id', $buyer_id);
            })
            ->orderBy('wo_msts.id', 'desc')->where('wo_msts.active_status', 1)->where('wo_dtls.active_status', 1)->paginate(self::limit($query)); */
        // DB::enableQueryLog();
        $data = Wo_mst::select('wo_msts.id as wo_id', 'wo_msts.wo_no', 'wo_msts.wo_date', 'a.name as supplier_name', 'wo_dtls.id as wo_dtls_id', 'wo_dtls.order_id', 'wo_dtls.order_dtls_id', 'wo_dtls.product_id', 'wo_dtls.style', 'wo_dtls.size_id', 'wo_dtls.color_id', 'wo_dtls.unit_id', 'wo_dtls.qnty', 'wo_dtls.price', 'wo_dtls.amount', 'products.name as product_name', 'sizes.name as size_name', 'colors.name as color_name', 'units.name as unit_name')
            ->join('wo_dtls', 'wo_dtls.wo_id', '=', 'wo_msts.id')
            ->join('parties as a', 'wo_msts.supplier_id', '=', 'a.id')
            ->join('products', 'wo_dtls.product_id', '=', 'products.id')
            ->leftJoin('sizes', 'wo_dtls.size_id', '=', 'sizes.id')
            ->leftJoin('colors', 'wo_dtls.color_id', '=', 'colors.id')
            ->leftJoin('units', 'wo_dtls.unit_id', '=', 'units.id')
            ->when($company_id, function ($query) use ($company_id) {
                $query->where('wo_msts.company_id', $company_id);
            })
            ->when($buyer_id, function ($query) use ($buyer_id) {
                $query->where('wo_msts.buyer_id', $buyer_id);
            })
            ->whereNotIn('wo_dtls.id', function ($subquery) use ($company_id, $buyer_id) {
                $subquery->select('wo_dtls_id')
                    ->from('pi_dtls')
                    ->join('wo_msts', 'pi_dtls.wo_id', '=', 'wo_msts.id')
                    ->where('pi_dtls.active_status', 1)
                    ->where('wo_msts.company_id', $company_id)
                    ->where('wo_msts.buyer_id', $buyer_id);
            })
            ->where('wo_msts.active_status', 1)->where('wo_dtls.active_status', 1)->orderBy('wo_msts.id', 'desc')
            ->paginate(self::limit($query));
        // ->toSql();

        // $query_db = DB::getQueryLog();
        // dd($query_db);
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
