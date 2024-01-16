<?php

namespace App\Http\Controllers;

use App\Models\Doc_acpt_mst;
use App\Models\Doc_acpt_dtl;
use App\Models\Document;
use App\Models\Lc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DocAcptController extends Controller
{

    public function add()
    {
        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['doc_where_list'] = self::getDocPlaceList();
        $response['document_list'] = Document::select('id', 'name')->where('active_status', 1)->get();

        return response($response, 200);
    }

    public function index(Request $request)
    {
        $query = $request->all();
        $search = $request->input('search');
        $maturity_date = $request->input('maturity_date');
        $data = Doc_acpt_mst::select('doc_acpt_msts.*', 'lcs.uuid as lc_uuid', 'b.name as buyer_name')
            ->when($maturity_date, function ($query) use ($maturity_date) {
                $query->whereDate('doc_acpt_msts.maturity_date', '<=', $maturity_date);
            })
            ->join('lcs', 'lcs.id', '=', 'doc_acpt_msts.lc_id')
            ->join('parties as b', 'lcs.buyer_id', '=', 'b.id');
        if ($search) {
            $data = $data->where(function ($query) use ($search) {
                $query->where('doc_acpt_msts.lc_num', 'LIKE', '%' . $search . '%')
                    ->orWhereDate('doc_acpt_msts.doc_acpt_date', 'LIKE', '%' . $search . '%')
                    ->orWhereDate('doc_acpt_msts.maturity_date', 'LIKE', '%' . $search . '%');
            });
        }
        $data = $data->where('doc_acpt_msts.active_status', 1)->orderBy('doc_acpt_msts.id', 'desc')->paginate(self::limit($query));

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
            'lc_id' => "required|numeric",
            'lc_num' => "required|string|max:100",
            'doc_acpt_date' => "required|date",
            'maturity_date' => "nullable|date",
            'idbp' => "nullable|string",
            'discrepancy' => "nullable|string",
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
            'lc_id' => $request->lc_id,
            'lc_num' => $request->lc_num,
            'doc_acpt_date' => $request->doc_acpt_date,
            'maturity_date' => $request->maturity_date,
            'idbp' => $request->idbp,
            'discrepancy' => $request->discrepancy,
            'remarks' => $request->remarks,
            'created_by' => $user_id,
            'uuid' => Str::uuid()->toString(),
        ];

        DB::beginTransaction();
        $data_mst = Doc_acpt_mst::create($request_data);
        $data_dtls_array = [];

        foreach ($request->data_dtls as $key => $row) {
            if ($row["doc_id"]) {
                $data_dtls_arr = [
                    'doc_acpt_id' => $data_mst->id,
                    'doc_id' => $row["doc_id"],
                    'doc_where_id' => $row["doc_where_id"],
                    'original' => $row["original"],
                    'created_by' => $user_id,
                ];

                if (is_object($request->data_dtls[$key]["image"])) {
                    $files = $row["image"];
                    $path = 'document';
                    $attachment = self::uploadImage($files, $path);
                    $data_dtls_arr['file_image'] = $attachment;
                } else {
                    $data_dtls_arr['file_image'] = '';
                }
                $data_dtls_array[] = $data_dtls_arr;
            }
        }

        $data_dtls = true;
        if (count($data_dtls_array) > 0) {
            $data_dtls = Doc_acpt_dtl::insert($data_dtls_array);
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
            'lc_id' => "required|numeric",
            'lc_num' => "required|string|max:100",
            'doc_acpt_date' => "required|date",
            'maturity_date' => "nullable|date",
            'idbp' => "nullable|string",
            'discrepancy' => "nullable|string",
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
            'lc_id' => $request->lc_id,
            'lc_num' => $request->lc_num,
            'doc_acpt_date' => $request->doc_acpt_date,
            'maturity_date' => $request->maturity_date,
            'idbp' => $request->idbp,
            'discrepancy' => $request->discrepancy,
            'remarks' => $request->remarks,
            'updated_by' => $user_id,
        ];

        DB::beginTransaction();
        $data_mst = Doc_acpt_mst::where('id', $mst_id)->update($request_data);
        $docAcptDtlIds = Doc_acpt_dtl::where('doc_acpt_id', $mst_id)->where('active_status', 1)->pluck('id')->all();

        $data_dtls_insert = [];
        $active_dtls_id = array();
        foreach ($request->data_dtls as $key => $row) {
            if ($row["doc_id"]) {
                $data_dtls_arr = [
                    'doc_acpt_id' => $mst_id,
                    'doc_id' => $row["doc_id"],
                    'doc_where_id' => $row["doc_where_id"],
                    'original' => $row["original"],
                ];

                $attachment = '';
                if (is_object($request->data_dtls[$key]["image"])) {
                    $files = $row["image"];
                    $path = 'document';
                    $attachment = self::uploadImage($files, $path);
                }

                if ($row["dtls_id"]) {
                    if ($attachment) {
                        $data_dtls_arr['file_image'] = $attachment;
                    }
                    $data_dtls_arr['updated_by'] = $user_id;
                    Doc_acpt_dtl::where('id', $row["dtls_id"])->update($data_dtls_arr);
                    $active_dtls_id[] = $row["dtls_id"];
                } else {
                    $data_dtls_arr['file_image'] = $attachment;
                    $data_dtls_arr['created_by'] = $user_id;
                    $data_dtls_insert[] = $data_dtls_arr;
                }
            }
        }

        $data_dtls = $data_del_dtls = true;

        $docAcptDtlIdsDiffArr = array_diff($docAcptDtlIds, $active_dtls_id);
        if (count($docAcptDtlIdsDiffArr) > 0) {
            $delete_info = [
                'active_status' => 2,
                'updated_by' => Auth()->user()->id,
            ];
            $data_del_dtls = Doc_acpt_dtl::whereIn('id', $docAcptDtlIdsDiffArr)->update($delete_info);
        }

        if (count($data_dtls_insert) > 0) {
            $data_dtls = Doc_acpt_dtl::insert($data_dtls_insert);
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
        DB::beginTransaction();
        $data = Doc_acpt_mst::where('uuid', $uuid)->first();
        $update_mst = Doc_acpt_mst::findOrFail($data->id)->update([
            'active_status' => 2,
            'updated_by' => Auth()->user()->id,
        ]);
        $update_dtls = Doc_acpt_dtl::where('doc_acpt_id', $data->id)->where('active_status', 1)->update([
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

    public function getDocAcptInfo($uuid)
    {

        $data = Doc_acpt_mst::where('uuid', $uuid)->with(['data_dtls.doc_info'])->where('active_status', 1)->first();
        $data_lc = Lc::where('lc_no', $data->lc_num)->with(['company_info', 'buyer_info', 'opening_bank_info', 'advising_bank_info', 'data_dtls.pi_info', 'data_dtls.pi_info.data_dtls', 'data_dtls.pi_info.data_dtls.product_info', 'data_dtls.pi_info.data_dtls.color_info', 'data_dtls.pi_info.data_dtls.size_info', 'data_dtls.pi_info.data_dtls.unit_info'])->where('active_status', 1)->first();
        // $data_dtls = Doc_acpt_dtl::where('doc_acpt_id', $request->id)->where('active_status', 1)->get();

        if ($data) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['response_data'] = $data;
            $response['doc_where_list'] = self::getDocPlaceList();
            $response['data_lc'] = $data_lc;
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }

    public function getDocAcptLetterInfo(Request $request)
    {
        // $data = Doc_acpt_mst::where('id', $request->id)->first();
        $data_mst = Doc_acpt_mst::where('id', $request->id)->where('active_status', 1)->first();
        $data_dtls = Doc_acpt_dtl::where('doc_acpt_id', $request->id)->where('active_status', 1)->get();


        if ($data_mst->count() > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['doc_where_list'] = self::getDocPlaceList();
            $response['data_mst'] = $data_mst;
            $response['data_dtls'] = $data_dtls;
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }
}
