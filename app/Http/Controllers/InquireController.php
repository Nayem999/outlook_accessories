<?php

namespace App\Http\Controllers;

use App\Models\Inquire_mst;
use App\Models\Inquire_dtl;
use App\Models\Order_mst;
use App\Models\Quotation_mst;
use App\Models\Sample_mst;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InquireController extends Controller
{
    public function add()
    {
        $response['status'] = 'success';
        $response['message'] = 'Data found.';
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
        $data = Inquire_mst::select('inquire_msts.id', 'inquire_msts.uuid', 'inquire_msts.inquire_date', 'inquire_msts.inquire_no', 'a.name as company_name', 'b.name as buyer_name', DB::raw("group_concat(DISTINCT inquire_dtls.style SEPARATOR', ') as style"))
            ->join('parties as a', 'inquire_msts.company_id', '=', 'a.id')
            ->join('parties as b', 'inquire_msts.buyer_id', '=', 'b.id')
            ->join('inquire_dtls', function ($join) {
                $join->on('inquire_msts.id', '=', 'inquire_dtls.inquire_id')
                    ->where('inquire_dtls.active_status', 1);
            })
            ->where('inquire_msts.active_status', 1)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('inquire_msts.inquire_no', 'LIKE', "%$search%")
                        ->orWhereDate('inquire_msts.inquire_date', 'LIKE', "%$search%")
                        ->orWhere('a.name', 'LIKE', "%$search%")
                        ->orWhere('b.name', 'LIKE', "%$search%")
                        ->orWhere(function ($query) use ($search) {
                            $query->where('inquire_dtls.style', 'LIKE', "%$search%");
                        });
                });
            })
            ->when($company_id, function ($query) use ($company_id) {
                $query->where('inquire_msts.company_id', $company_id);
            })
            ->when($buyer_id, function ($query) use ($buyer_id) {
                $query->where('inquire_msts.buyer_id', $buyer_id);
            })
            ->groupBy('inquire_msts.id', 'inquire_msts.uuid', 'inquire_msts.inquire_date', 'inquire_msts.inquire_no', 'company_name', 'buyer_name')
            ->orderBy('inquire_msts.id', 'desc')
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
        // dd($request->all());
        $validator = Validator::make($request->all(), [
            'company_id' => "required|numeric|max:99999999",
            'buyer_id' => "required|numeric|max:99999999",
            'inquire_date' => "required|date",
            'delivery_req_date' => "required|date",
            'merchandiser_name' => "nullable|string|max:100",
            'merchandiser_phone' => "nullable|string|max:30",
            'inquire_person' => "nullable|string|max:100",
            'attntion' => "nullable|string|max:100",
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
            'inquire_date' => $request->inquire_date,
            'delivery_req_date' => $request->delivery_req_date,
            'merchandiser_name' => $request->merchandiser_name,
            'merchandiser_phone' => $request->merchandiser_phone,
            'inquire_person' => $request->inquire_person,
            'attntion' => $request->attntion,
            'remarks' => $request->remarks,
            'created_by' => $user_id,
            'uuid' => Str::uuid()->toString(),
            'inquire_no' => self::get_system_no('inquire_msts', 'inq'),
        ];

        if ($files = $request->file("file_image")) {
            $path = 'inquire';
            $attachment = self::uploadImage($files, $path);
            $request_data['file_image'] = $attachment;
        }

        DB::beginTransaction();
        $data_mst = Inquire_mst::create($request_data);
        $data_dtls_array = [];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $maxSize = 2048;
        foreach ($request->data_dtls as $key => $row) {
            if ($row["product_id"] && $row["qnty"]) {
                $data_dtls_arr = [
                    'inquire_id' => $data_mst->id,
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
                    $files = $row["image"];
                    $extension = strtolower($files->getClientOriginalExtension());
                    if (!in_array($extension, $allowedExtensions)) {
                        DB::rollBack();
                        $response['status'] = 'error';
                        $response['message'] = 'Invalid file extension. Allowed extensions: ' . implode(', ', $allowedExtensions);
                        return response($response, 422);
                    }
                    /* $fileSize = $files->getSize(); // in bytes
                    $maxSizeInBytes = $maxSize * 1024; // Convert size to bytes
                    if ($fileSize > $maxSizeInBytes) {
                        DB::rollBack();
                        $response['status'] = 'error';
                        $response['message'] = 'File size exceeds the maximum allowed size: (' . $maxSize . 'KB)';
                        return response($response, 422);
                    } */
                    $path = 'inquire';
                    $attachment = self::uploadImage($files, $path);
                    $data_dtls_arr['file_image'] = $attachment;
                } else {
                    $data_dtls_arr['file_image'] = '';
                }

                $data_dtls_array[] = $data_dtls_arr;
            }
        }
        // dd($data_dtls_array);

        $data_dtls = false;
        if (count($data_dtls_array) > 0) {
            $data_dtls = Inquire_dtl::insert($data_dtls_array);
        }

        if ($data_mst && $data_dtls) {
            DB::commit();
            $response['status'] = 'success';
            $response['message'] = 'Data inserted successfully.';
            return response($response, 200);
        } else {
            DB::rollBack();
            // $response['err'] = $data_mst.'**'.$data_dtls;
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
            'inquire_date' => "required|date",
            'delivery_req_date' => "required|date",
            'merchandiser_name' => "nullable|string|max:100",
            'merchandiser_phone' => "nullable|string|max:30",
            'inquire_person' => "nullable|string|max:100",
            'attntion' => "nullable|string|max:100",
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
            'inquire_date' => $request->inquire_date,
            'delivery_req_date' => $request->delivery_req_date,
            'merchandiser_name' => $request->merchandiser_name,
            'merchandiser_phone' => $request->merchandiser_phone,
            'inquire_person' => $request->inquire_person,
            'attntion' => $request->attntion,
            'remarks' => $request->remarks,
        ];
        $request_data['updated_by'] = $user_id;
        if ($files = $request->file("file_image")) {
            $path = 'inquire';
            $attachment = self::uploadImage($files, $path);
            $request_data['file_image'] = $attachment;
        }

        DB::beginTransaction();
        $data_mst = Inquire_mst::where('id', $mst_id)->update($request_data);
        $inquireDtlIds = Inquire_dtl::where('inquire_id', $mst_id)->where('active_status', 1)->pluck('id')->all();

        $data_dtls_insert = [];
        $active_dtls_id = array();
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $maxSize = 2048;
        foreach ($request->data_dtls as $key => $row) {
            if ($row["product_id"] && $row["qnty"]) {
                $data_dtls_arr = [
                    'inquire_id' => $mst_id,
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
                    $extension = strtolower($files->getClientOriginalExtension());
                    if (!in_array($extension, $allowedExtensions)) {
                        DB::rollBack();
                        $response['status'] = 'error';
                        $response['message'] = 'Invalid file extension. Allowed extensions: ' . implode(', ', $allowedExtensions);
                        return response($response, 422);
                    }
                    /* $fileSize = $files->getSize(); // in bytes
                    $maxSizeInBytes = $maxSize * 1024; // Convert size to bytes
                    if ($fileSize > $maxSizeInBytes) {
                        DB::rollBack();
                        $response['status'] = 'error';
                        $response['message'] = 'File size exceeds the maximum allowed size: (' . $maxSize . 'KB)';
                        return response($response, 422);
                    } */
                    $path = 'inquire';
                    $attachment = self::uploadImage($files, $path);
                }

                if ($row["dtls_id"]) {
                    if ($attachment) {
                        $data_dtls_arr['file_image'] = $attachment;
                    }
                    $data_dtls_arr['updated_by'] = $user_id;
                    Inquire_dtl::where('id', $row["dtls_id"])->update($data_dtls_arr);
                    $active_dtls_id[] = $row["dtls_id"];
                } else {
                    $data_dtls_arr['file_image'] = $attachment;
                    $data_dtls_arr['created_by'] = $user_id;
                    $data_dtls_insert[] = $data_dtls_arr;
                }
                // dd($request["dtls_id"]);

            }
        }
        /* $i = sizeof($request->product_id);
        for ($r = 0; $r < $i; $r++) {
            if ($request->product_id[$r] && $request->qnty[$r]) {

                $data_dtls_arr = [
                    'inquire_id' => $mst_id,
                    'product_id' => $request->product_id[$r],
                    'style' => $request->style[$r],
                    'size_id' => $request->size_id[$r],
                    'color_id' => $request->color_id[$r],
                    'unit_id' => $request->unit_id[$r],
                    'qnty' => $request->qnty[$r],
                    'remarks' => $request->remark[$r],
                ];

                if ($files = $request->file("image[$r]")) {
                    $path = 'inquire';
                    $attachment = self::uploadImage($files, $path);
                    $data_dtls_arr['file_image'] = $attachment;
                }

                if ($request->dtls_id[$r]) {
                    $data_dtls_arr['updated_by'] = $user_id;
                    Inquire_dtl::where('id', $request->dtls_id[$r])->update($data_dtls_arr);
                    $active_dtls_id[] = $request->dtls_id[$r];
                } else {
                    $data_dtls_arr['created_by'] = $user_id;
                    $data_dtls_insert[] = $data_dtls_arr;
                }
            }
        } */

        $data_dtls = $data_del_dtls = true;

        $inquireDtlIdsDiffArr = array_diff($inquireDtlIds, $active_dtls_id);
        if (count($inquireDtlIdsDiffArr) > 0) {
            $delete_info = [
                'active_status' => 2,
                'updated_by' => Auth()->user()->id,
            ];
            $data_del_dtls = Inquire_dtl::whereIn('id', $inquireDtlIdsDiffArr)->update($delete_info);
        }

        if (count($data_dtls_insert) > 0) {
            $data_dtls = Inquire_dtl::insert($data_dtls_insert);
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

        $data = Inquire_mst::where('uuid', $uuid)->first();
        $qutation_add = Quotation_mst::where('order_inquire_id', $data->id)->where('active_status', 1)->where('quotation_type', 1)->first();
        if ($qutation_add) {
            $response['status'] = 'error';
            $response['message'] = 'Inquire can not delete. This inquiry found in Qutation page. Qutation No: ' . $qutation_add->quotation_no;
            return response($response, 422);
        }
        $sample_add = Sample_mst::where('inquire_id', $data->id)->where('active_status', 1)->first();
        if ($sample_add) {
            $response['status'] = 'error';
            $response['message'] = 'Inquire can not delete. This inquiry found in Sample page. Sample No: ' . $sample_add->sample_no;
            return response($response, 422);
        }
        $order_add = Order_mst::where('inquire_id', $data->id)->where('active_status', 1)->first();
        if ($order_add) {
            $response['status'] = 'error';
            $response['message'] = 'Inquire can not delete. This inquiry found in Order page. Order No: ' . $order_add->order_no;
            return response($response, 422);
        }

        DB::beginTransaction();
        $update_mst = Inquire_mst::findOrFail($data->id)->update([
            'active_status' => 2,
            'updated_by' => Auth()->user()->id,
        ]);
        $update_dtls = Inquire_dtl::where('inquire_id', $data->id)->where('active_status', 1)->update([
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

    public function getInquireInfo($uuid)
    {
        $data = Inquire_mst::where('uuid', $uuid)->with(['company_info', 'buyer_info', 'dtls_info.color_info', 'dtls_info.size_info', 'dtls_info.unit_info', 'dtls_info.product_info'])->where('active_status', 1)->first();
        // $data_dtls = Inquire_dtl::where('inquire_id', $data_mst->id)->with('product_info')->with('color_info')->with('size_info')->with('unit_info')->where('active_status', 1)->get();

        if ($data) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['response_data'] = $data;
            // $response['data_dtls'] = $data_dtls;
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }
}
