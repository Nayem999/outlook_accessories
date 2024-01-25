<?php

namespace App\Http\Controllers;

use App\Models\Quotation_mst;
use App\Models\Quotation_dtl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class QuotationController extends Controller
{
    public function add()
    {
        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['company_list'] = self::getPartyList(1);
        $response['buyer_list'] = self::getPartyList(2);
        $response['quotation_type'] = self::getQuotationList();
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
        $data = Quotation_mst::select('quotation_msts.id', 'quotation_msts.uuid', 'quotation_msts.quotation_date', 'quotation_msts.quotation_no', 'a.name as company_name', 'b.name as buyer_name', DB::raw("group_concat(DISTINCT quotation_dtls.style SEPARATOR', ') as style"))
            ->join('parties as a', 'quotation_msts.company_id', '=', 'a.id')
            ->join('parties as b', 'quotation_msts.buyer_id', '=', 'b.id')
            ->join('quotation_dtls', function ($join) {
                $join->on('quotation_msts.id', '=', 'quotation_dtls.quotation_id')
                    ->where('quotation_dtls.active_status', 1);
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('quotation_msts.quotation_no', 'LIKE', '%' . $search . '%')
                    ->orWhereDate('quotation_msts.quotation_date', 'LIKE', '%' . $search . '%')
                    ->orWhere('a.name', 'LIKE', '%' . $search . '%')
                    ->orWhere('b.name', 'LIKE', '%' . $search . '%')
                        ->orWhere(function ($query) use ($search) {
                            $query->where('quotation_dtls.style', 'LIKE', "%$search%");
                        });
                });
            })
        ->groupBy('quotation_msts.id', 'quotation_msts.uuid', 'quotation_msts.quotation_date', 'quotation_msts.quotation_no', 'company_name', 'buyer_name')
        ->orderBy('quotation_msts.id', 'desc')->where('quotation_msts.active_status', 1)
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
            'quotation_type' => "required|numeric|max:999",
            'order_inquire_id' => "required|numeric|max:99999999",
            'quotation_date' => "required|date",
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
            'quotation_type' => $request->quotation_type,
            'order_inquire_id' => $request->order_inquire_id,
            'quotation_date' => $request->quotation_date,
            'currency_id' => $request->currency_id,
            'remarks' => $request->remarks,
            'created_by' => $user_id,
            'uuid' => Str::uuid()->toString(),
            'quotation_no' => self::get_system_no('quotation_msts', 'qt'),
        ];

        DB::beginTransaction();
        $data_mst = Quotation_mst::create($request_data);
        $data_dtls_array = [];
        foreach ($request->data_dtls as $row) {
            if ($row["product_id"] && $row["qnty"]) {
                $data_dtls_arr = [
                    'quotation_id' => $data_mst->id,
                    'quotation_type' => $request->quotation_type,
                    'order_inquire_dtls_id' => $row["order_inquire_dtls_id"],
                    'product_id' => $row["product_id"],
                    'style' => $row["style"],
                    'size_id' => $row["size_id"],
                    'color_id' => $row["color_id"],
                    'unit_id' => $row["unit_id"],
                    'qnty' => $row["qnty"],
                    'price' => $row["price"],
                    'amount' => $row["amount"],
                    'created_by' => $user_id,
                    'created_at' => now(),
                ];
                $data_dtls_array[] = $data_dtls_arr;
            }
        }

        $data_dtls = false;
        if (count($data_dtls_array) > 0) {
            $data_dtls = Quotation_dtl::insert($data_dtls_array);
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
            'mst_id' => "required|numeric|max:99999999",
            'company_id' => "required|numeric|max:99999999",
            'buyer_id' => "required|numeric|max:99999999",
            'quotation_type' => "required|numeric|max:999",
            'order_inquire_id' => "required|numeric|max:99999999",
            'quotation_date' => "required|date",
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
            'quotation_type' => $request->quotation_type,
            'order_inquire_id' => $request->order_inquire_id,
            'quotation_date' => $request->quotation_date,
            'currency_id' => $request->currency_id,
            'remarks' => $request->remarks,
        ];
        $request_data['updated_by'] = $user_id;
        DB::beginTransaction();
        $data_mst = Quotation_mst::where('id', $mst_id)->update($request_data);
        $quotationDtlIds = Quotation_dtl::where('quotation_id', $mst_id)->where('active_status', 1)->pluck('id')->all();

        $data_dtls_insert = [];
        $active_dtls_id = array();
        foreach ($request->data_dtls as $row) {
            if ($row["product_id"] && $row["qnty"]) {
                $data_dtls_arr = [
                    'quotation_id' => $mst_id,
                    'quotation_type' => $request->quotation_type,
                    'order_inquire_dtls_id' => $row["order_inquire_dtls_id"],
                    'product_id' => $row["product_id"],
                    'style' => $row["style"],
                    'size_id' => $row["size_id"],
                    'color_id' => $row["color_id"],
                    'unit_id' => $row["unit_id"],
                    'qnty' => $row["qnty"],
                    'price' => $row["price"],
                    'amount' => $row["amount"],
                ];
                if ($row["dtls_id"]) {
                    $data_dtls_arr['updated_by'] = $user_id;
                    $data_dtls_arr['updated_at'] = now();
                    Quotation_dtl::where('id', $row["dtls_id"])->update($data_dtls_arr);
                    $active_dtls_id[] = $row["dtls_id"];
                } else {
                    $data_dtls_arr['created_by'] = $user_id;
                    $data_dtls_arr['created_at'] = now();
                    $data_dtls_insert[] = $data_dtls_arr;
                }

            }
        }

        $data_dtls = $data_del_dtls = true;

        $quotationDtlIdsDiffArr = array_diff($quotationDtlIds, $active_dtls_id);
        if (count($quotationDtlIdsDiffArr) > 0) {
            $delete_info = [
                'active_status' => 2,
                'updated_by' => Auth()->user()->id,
                'updated_at' => now()
            ];
            $data_del_dtls = Quotation_dtl::whereIn('id', $quotationDtlIdsDiffArr)->update($delete_info);
        }

        if (count($data_dtls_insert) > 0) {
            $data_dtls = Quotation_dtl::insert($data_dtls_insert);
        }

        if ($data_mst && $data_del_dtls && $data_dtls) {
            DB::commit();
            $response['status'] = 'success';
            $response['message'] = 'Data updated successfully.';
            return response($response, 200);
        } else {
            DB::rollBack();
            // $response['err'] = $data_mst.'**'.$data_del_dtls.'**'.$data_dtls;
            $response['status'] = 'error';
            $response['message'] = 'Something went to wrong!';
            return response($response, 422);
        }
    }

    public function destroy($uuid)
    {
        DB::beginTransaction();
        $data = Quotation_mst::where('uuid', $uuid)->first();
        $update_mst = Quotation_mst::findOrFail($data->id)->update([
            'active_status' => 2,
            'updated_by' => Auth()->user()->id,
        ]);
        $update_dtls = Quotation_dtl::where('quotation_id', $data->id)->where('active_status', 1)->update([
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

    public function getQuotationInfo($uuid)
    {

        // $data = Quotation_mst::where('uuid', $uuid)->with(['company_info','buyer_info','inquire_info','order_info','data_dtls.color_info','data_dtls.size_info','data_dtls.unit_info','data_dtls.product_info'])->where('active_status', 1)->first();
        $data = Quotation_mst::where('uuid', $uuid)
            ->with(['company_info', 'buyer_info', 'data_dtls.color_info', 'data_dtls.size_info', 'data_dtls.unit_info', 'data_dtls.product_info'])
            ->where('active_status', 1)
            ->first();

        if ($data->quotation_type==1) {
            $data->load([
                'inquire_info' => function ($query) {
                    $query->select('id', 'inquire_no');
                }
            ]);
        }
        if ($data->quotation_type==2) {
            $data->load([
                'order_info' => function ($query) {
                    $query->select('id', 'order_no');
                },
            ]);
        }

        if ($data) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['quotation_type'] = self::getQuotationList();
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
