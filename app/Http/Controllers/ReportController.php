<?php

namespace App\Http\Controllers;

use App\Models\Goods_issue_mst;
use App\Models\Goods_rcv_mst;
use App\Models\Lc;
use App\Models\Maturity_payment;
use App\Models\Order_dtl;
use App\Models\Order_mst;
use App\Models\Party;
use App\Models\Pi_mst;
use App\Models\Service;
use App\Models\TemporaryTbl;
use App\Models\Transaction;
use App\Models\Wo_mst;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{

    public function indexAction()
    {
        $startDate = Carbon::now()->subDays(30)->startOfDay();
        $monthDate = Carbon::now()->subMonths(11)->firstOfMonth();
        $endDate = Carbon::now()->endOfDay();

        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['total_order'] = Order_mst::where('active_status', 1)->count();
        $response['last_total_order'] = Order_mst::where('active_status', 1)->whereBetween('created_at', [$startDate, $endDate])->count();
        $response['total_pi'] = Pi_mst::where('active_status', 1)->count();
        $response['last_total_pi'] = Pi_mst::where('active_status', 1)->whereBetween('created_at', [$startDate, $endDate])->count();
        $response['mature_lc'] = Maturity_payment::where('active_status', 1)->count();
        $response['current_lc'] = Lc::where('active_status', 1)->count() - $response['mature_lc'];


        /* $date_range = self::getDatesFromRange($monthDate, $endDate,"M",'P1M');
        $response['date_range'] = $date_range; */
        // $response['trans_type_list'] = self::getTransTypeList();
        $response['monthly_income'] = Transaction::select(DB::raw('SUM(amount) as total_amount'), DB::raw("DATE_FORMAT(created_at, '%M') as month"))
            ->where('trans_type_id', 1)->where('active_status', 1)->whereIn('trans_page', [1, 2])
            ->whereBetween('created_at', [$monthDate, $endDate])
            ->groupBy('month')->get();
        $response['monthly_expense'] = Transaction::select(DB::raw('SUM(amount) as total_amount'), DB::raw("DATE_FORMAT(created_at, '%M') as month"))
            ->where('trans_type_id', 2)->where('active_status', 1)->whereIn('trans_page', [1, 2])
            ->whereBetween('created_at', [$monthDate, $endDate])
            ->groupBy('month')->get();
        /*   $response['monthly_income'] = Transaction::select(DB::raw('SUM(amount) as total_amount'), DB::raw('MONTH(created_at) as month'))
            ->where('trans_type_id', 1)->where('active_status', 1)->whereIn('trans_page', [1, 2])
            ->whereBetween('created_at', [$monthDate, $endDate])
            ->groupBy('month')->get();
        $response['monthly_expense'] = Transaction::select(DB::raw('SUM(amount) as total_amount'), DB::raw('MONTH(created_at) as month'))
            ->where('trans_type_id', 2)->where('active_status', 1)->whereIn('trans_page', [1, 2])
            ->whereBetween('created_at', [$monthDate, $endDate])
            ->groupBy('month')->get(); */

        $category_wise_expense = Transaction::select('trans_purpose_id', DB::raw('SUM(amount) as total_amount'))
            ->where('active_status', 1)->where('trans_page', 1)->where('trans_type_id', 2)
            ->groupBy('trans_purpose_id')
            ->with('trans_purpose_info')->get();
        $category_wise_expense_data = array();
        foreach ($category_wise_expense as $key => $row) {
            $category_wise_expense_data[$key]['name'] = $row->trans_purpose_info->name;
            $category_wise_expense_data[$key]['amount'] = $row->total_amount;
        }

        $response['category_wise_expense'] = $category_wise_expense_data;


        return response($response, 200);
    }

    public function type_wise_party_list($type_id = 0)
    {
        if ($type_id) {
            $response = self::getPartyList($type_id);
        } else {
            $response['company_list'] = self::getPartyList(1);
            $response['buyer_list'] = self::getPartyList(2);
            $response['supplier_list'] = self::getPartyList(3);
            $response['employee_list'] = self::getPartyList(4);
            $response['others_list'] = self::getPartyList(4);
        }
        return response($response, 200);
    }

    /* public function po_wise_profit(Request $request)
    {
        $query = $request->all();
        $data = DB::table('order_msts')
            ->select(
                'order_msts.id',
                'order_msts.order_no',
                'order_msts.order_date',
                'a.name as company_name',
                'b.name as buyer_name',
                DB::raw('SUM(pi_dtls.amount) as pi_amount'),
                DB::raw('SUM(wo_dtls.amount) as wo_amount')
            )
            ->leftJoin('wo_dtls', function ($join) {
                $join->on('order_msts.id', '=', 'wo_dtls.order_id')
                    ->where('wo_dtls.active_status', 1);
            })
            ->leftJoin('pi_dtls', function ($join) {
                $join->on('order_msts.id', '=', 'pi_dtls.order_id')
                    ->on('pi_dtls.wo_dtls_id', '=', 'wo_dtls.id')
                    ->where('pi_dtls.active_status', 1);
            })
            ->leftJoin('parties as a', 'order_msts.company_id', '=', 'a.id')
            ->leftJoin('parties as b', 'order_msts.buyer_id', '=', 'b.id')
            ->where('order_msts.active_status', 1)
            ->groupBy('order_msts.id', 'order_msts.order_no', 'order_msts.order_date', 'a.name', 'b.name')
            ->orderByDesc('order_msts.id');


        if ($request->company_id) {
            $data = $data->where('order_msts.company_id', $request->company_id);
        }
        if ($request->buyer_id) {
            $data = $data->where('order_msts.buyer_id', $request->buyer_id);
        }
        if ($request->start_date) {
            $data = $data->whereDate('order_msts.order_date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $data = $data->whereDate('order_msts.order_date', '<=', $request->end_date);
        }

        $data = $data->paginate(self::limit($query));

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
    } */

    public function order_details_rpt(Request $request)
    {
        $query = $request->all();
        $company_id = $request->company_id;
        $buyer_id = $request->buyer_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $style = $request->style;

        $data = Order_mst::select(
            'order_msts.id',
            'order_msts.order_no as po_no',
            'a.name as company_name',
            'b.name as buyer_name',
            'products.id as product_id',
            'products.name as product_name',
            'order_msts.order_person',
            'order_dtls.style',
            'sizes.name as size_name',
            'colors.name as color_name',
            'order_dtls.qnty as po_qnty',
            'units.name as unit_name',
            'order_dtls.file_image as attachment_file',
            'c.name as supplier_name',
            'order_msts.order_date',
            'order_msts.delivery_req_date',
            'pi_msts.pi_no',
            'order_dtls.order_status',
            'order_dtls.remarks',
            'pi_dtls.qnty as pi_qnty',
            'pi_dtls.price as pi_price',
            'pi_dtls.amount as pi_amount',
            'wo_msts.wo_no',
            'wo_dtls.qnty as wo_qnty',
            'wo_dtls.price as wo_price',
            'wo_dtls.amount as wo_amount',
            'd.name as wo_unit_name',
            'goods_rcv_msts.goods_rcv_no',
            'goods_rcv_msts.rcv_date as goods_rcv_date',
            'goods_rcv_dtls.qnty as goods_rcv_qnty',
            'goods_rcv_dtls.extra_qnty as goods_rcv_extra_qnty',
            'goods_issue_msts.goods_issue_no',
            'goods_issue_msts.delivery_date as goods_issue_date',
            'goods_issue_dtls.qnty as goods_issue_qnty',
            'goods_issue_dtls.extra_qnty as goods_issue_extra_qnty',
        )
            ->join('parties as a', 'order_msts.company_id', '=', 'a.id')
            ->join('parties as b', 'order_msts.buyer_id', '=', 'b.id')
            ->join('order_dtls', function ($join) {
                $join->on('order_msts.id', '=', 'order_dtls.order_id')
                    ->where('order_dtls.active_status', 1);
            })
            ->join('products', 'products.id', '=', 'order_dtls.product_id')
            ->leftJoin('colors', 'colors.id', '=', 'order_dtls.color_id')
            ->leftJoin('units', 'units.id', '=', 'order_dtls.unit_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'order_dtls.size_id')
            ->leftJoin('wo_dtls', function ($join) {
                $join->on('order_msts.id', '=', 'wo_dtls.order_id')
                    ->on('order_dtls.id', '=', 'wo_dtls.order_dtls_id')
                    // ->on('wo_msts.id', '=', 'wo_dtls.wo_id')
                    ->where('wo_dtls.active_status', 1);
            })
            ->leftJoin('wo_msts', function ($join) {
                $join->on('wo_msts.id', '=', 'wo_dtls.wo_id')
                    ->on('wo_msts.order_id', '=', 'order_msts.id')
                    ->where('wo_msts.active_status', 1);
            })
            ->leftJoin('units as d', 'd.id', '=', 'wo_dtls.unit_id')
            ->leftJoin('parties as c', 'c.id', '=', 'wo_msts.supplier_id')
            ->leftJoin('pi_dtls', function ($join) {
                $join->on('order_msts.id', '=', 'pi_dtls.order_id')
                    ->on('order_dtls.id', '=', 'pi_dtls.order_dtls_id')
                    ->where('pi_dtls.active_status', 1);
            })
            ->leftJoin('pi_msts', function ($join) {
                $join->on('pi_dtls.pi_id', '=', 'pi_msts.id')
                    ->where('pi_msts.active_status', 1);
            })
            ->leftJoin('goods_rcv_dtls', function ($join) {
                $join->on('wo_dtls.id', '=', 'goods_rcv_dtls.wo_dtls_id')
                    // ->on('goods_rcv_msts.id', '=', 'goods_rcv_dtls.goods_rcv_id')
                    ->where('goods_rcv_dtls.active_status', 1);
            })
            ->leftJoin('goods_rcv_msts', function ($join) {
                $join->on('goods_rcv_msts.id', '=', 'goods_rcv_dtls.goods_rcv_id')
                    // ->on('goods_rcv_msts.wo_id', '=', 'wo_mst.id')
                    ->where('goods_rcv_msts.active_status', 1);
            })
            ->leftJoin('goods_issue_dtls', function ($join) {
                $join->on('order_dtls.id', '=', 'goods_issue_dtls.order_dtls_id')
                    // ->on('goods_issue_msts.id', '=', 'goods_issue_dtls.goods_issue_id')
                    ->where('goods_issue_dtls.active_status', 1);
            })
            ->leftJoin('goods_issue_msts', function ($join) {
                $join->on('goods_issue_msts.id', '=', 'goods_issue_dtls.goods_issue_id')
                    // ->on('goods_issue_msts.order_id', '=', 'order_mst.id')
                    ->where('goods_issue_msts.active_status', 1);
            })
            ->where('order_msts.active_status', 1)
            ->when($company_id, function ($query) use ($company_id) {
                $query->where('order_msts.company_id', $company_id);
            })
            ->when($buyer_id, function ($query) use ($buyer_id) {
                $query->where('order_msts.buyer_id', $buyer_id);
            })
            ->when($start_date, function ($query) use ($start_date) {
                $query->whereDate('order_msts.order_date', '>=', $start_date);
            })
            ->when($end_date, function ($query) use ($end_date) {
                $query->whereDate('order_msts.order_date', '<=', $end_date);
            })
            ->when($style, function ($query) use ($style) {
                $query->where('order_dtls.style', 'like', "%$style%");
            })
            ->orderByDesc('order_msts.id', 'products.id', 'order_dtls.style')
            ->groupBy('id', 'po_no', 'company_name', 'buyer_name', 'product_id', 'product_name', 'order_person', 'style', 'size_name', 'color_name', 'po_qnty', 'unit_name', 'attachment_file', 'supplier_name', 'order_date', 'delivery_req_date', 'order_status', 'remarks', 'pi_no', 'pi_amount', 'pi_qnty', 'pi_price', 'wo_no', 'wo_amount', 'wo_qnty', 'wo_price', 'wo_unit_name', 'goods_rcv_no', 'goods_rcv_date', 'goods_rcv_qnty', 'goods_rcv_extra_qnty', 'goods_issue_no', 'goods_issue_date', 'goods_issue_qnty', 'goods_issue_extra_qnty')
            ->paginate(self::limit($query));

        /* DB::raw('SUM(wo_dtls.amount) as wo_amount'), DB::raw('SUM(wo_dtls.qnty) as wo_qnty') */
        /* DB::raw('(SELECT SUM(amount) FROM pi_dtls WHERE pi_dtls.order_id = order_msts.id AND pi_dtls.active_status = 1) as pi_amount'),
        DB::raw('(SELECT SUM(qnty) FROM pi_dtls WHERE pi_dtls.order_id = order_msts.id AND pi_dtls.active_status = 1) as pi_qnty'),
        DB::raw('(SELECT SUM(amount) FROM wo_dtls WHERE wo_dtls.order_id = order_msts.id AND wo_dtls.active_status = 1) as wo_amount'),
        DB::raw('(SELECT SUM(qnty) FROM wo_dtls WHERE wo_dtls.order_id = order_msts.id AND wo_dtls.active_status = 1) as wo_qnty') */

        if ($data->count() > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['response_data'] = $data;
            $response['order_status_list'] = self::getOrderStatusList();
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }

    public function expenses_history_rpt(Request $request)
    {
        $query = $request->all();
        $party_type_id = $request->party_type_id;
        $party_id = $request->party_name_id;
        $purpose_id = $request->purpose_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        // ->where('transactions.party_type_id', 4)

        $data = Transaction::with('trans_purpose_info', 'party_info', 'bank_info')
            ->where('transactions.trans_page', 1)
            ->where('transactions.trans_type_id', 2)
            ->where('transactions.active_status', 1)
            ->when($party_type_id, function ($query) use ($party_type_id) {
                $query->where('transactions.party_type_id', $party_type_id);
            })
            ->when($party_id, function ($query) use ($party_id) {
                $query->where('transactions.party_id', $party_id);
            })
            ->when($purpose_id, function ($query) use ($purpose_id) {
                $query->where('transactions.trans_purpose_id', $purpose_id);
            })
            ->when($start_date, function ($query) use ($start_date) {
                $query->whereDate('transactions.date', '>=',  $start_date);
            })
            ->when($end_date, function ($query) use ($end_date) {
                $query->whereDate('transactions.date', '<=', $end_date);
            })
            ->orderByDesc('transactions.date')
            ->paginate(self::limit($query));

        if ($data->count() > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['trans_method_list'] = self::getTransMethodAllList();
            $response['party_type_list'] = self::getPartyTypeList();
            $response['response_data'] = $data;
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }

    public function party_laser_rpt(Request $request)
    {
        $query = $request->all();

        $party_data = Party::where('active_status', 1);

        if ($request->party_type_id) {
            $party_data = $party_data->where('party_type_id', $request->party_type_id);
        } else {
            $party_data = $party_data->whereIn('party_type_id', [1, 3, 4, 5]);
        }
        if ($request->party_name_id) {
            $party_data = $party_data->where('id', $request->party_name_id);
        }
        // $party_data = $party_data->get();
        $party_data = $party_data->paginate(self::limit($query));

        $data = [];
        foreach ($party_data as $key => $row) {
            $trans_data = Transaction::select(
                'party_id',
                DB::raw('SUM(CASE WHEN transactions.trans_type_id = 1 THEN transactions.amount ELSE 0 END) as income_amount'),
                DB::raw('SUM(CASE WHEN transactions.trans_type_id = 2 THEN transactions.amount ELSE 0 END) as expense_amount')
            )
                ->where('active_status', 1)->where('party_type_id', $row->party_type_id)->where('party_id', $row->id)
                ->groupBy('party_id')->get();

            if ($trans_data->count() > 0) {
                if ($row->party_type_id == 3 || $row->party_type_id == 4) {
                    $trans_balance = $trans_data[0]->expense_amount - $trans_data[0]->income_amount;
                } else {
                    $trans_balance = $trans_data[0]->income_amount - $trans_data[0]->expense_amount;
                }
            } else {
                $trans_balance = 0;
            }

            if ($row->party_type_id == 1) {

                /* $pi_val_after_gd_issue_data = Goods_issue_mst::select(
                    DB::raw('SUM(pi_dtls.amount) as receivable_amount')
                )
                    ->join('goods_issue_dtls', function ($join) {
                        $join->on('goods_issue_dtls.goods_issue_id', '=', 'goods_issue_msts.id')
                            ->where('goods_issue_dtls.active_status', 1);
                    })
                    ->join('pi_dtls', function ($join) {
                        $join->on('pi_dtls.order_dtls_id', '=', 'goods_issue_dtls.order_dtls_id')
                            ->where('pi_dtls.active_status', 1);
                    })
                    ->where('goods_issue_msts.company_id', $row->id)->where('goods_issue_msts.active_status', 1)
                    ->get();

                if ($pi_val_after_gd_issue_data->count() > 0) {
                    $pi_val_after_gd_issue = $pi_val_after_gd_issue_data[0]->receivable_amount;
                } else {
                    $pi_val_after_gd_issue = 0;
                }
                $party_data[$key]['account_type'] = 'Account Receivable';
                $party_data[$key]['balance_amount'] = $pi_val_after_gd_issue - $trans_balance; */
                $lc_data = Lc::select(
                    DB::raw('SUM(maturity_payments.amount) as payment_amount')
                )
                    ->join('maturity_payments', function ($join) {
                        $join->on('maturity_payments.lc_id', '=', 'lcs.id')
                            ->where('maturity_payments.active_status', 1);
                    })
                    ->where('lcs.company_id', $row->id)->where('lcs.active_status', 1)
                    ->get();

                if ($lc_data->count() > 0) {
                    $lc_payment_amount = $lc_data[0]->payment_amount;
                } else {
                    $lc_payment_amount = 0;
                }
                $party_data[$key]['account_type'] = 'Account Receivable';
                $party_data[$key]['balance_amount'] =  number_format($lc_payment_amount - $trans_balance,2);
            } else if ($row->party_type_id == 3) {

                /* $wo_val_after_gd_rcv_data = Goods_rcv_mst::select(
                    DB::raw('SUM(wo_dtls.price*goods_rcv_dtls.qnty) as payable_amount')
                )

                    ->join('goods_rcv_dtls', function ($join) {
                        $join->on('goods_rcv_dtls.goods_rcv_id', '=', 'goods_rcv_msts.id')
                            ->where('goods_rcv_dtls.active_status', 1);
                    })
                    ->join('wo_dtls', function ($join) {
                        $join->on('wo_dtls.id', '=', 'goods_rcv_dtls.wo_dtls_id')
                            ->where('wo_dtls.active_status', 1);
                    })
                    ->where('goods_rcv_msts.supplier_id', $row->id)->where('goods_rcv_msts.active_status', 1)
                    ->get();

                if ($wo_val_after_gd_rcv_data->count() > 0) {
                    $wo_val_after_gd_rcv = $wo_val_after_gd_rcv_data[0]->payable_amount;
                } else {
                    $wo_val_after_gd_rcv = 0;
                }

                $party_data[$key]['account_type'] = 'Account Payable';
                $party_data[$key]['balance_amount'] = $wo_val_after_gd_rcv - $trans_balance; */
                $wo_val_data = Wo_mst::select(
                    DB::raw('SUM(wo_dtls.price*wo_dtls.qnty) as payable_amount')
                )
                    ->join('wo_dtls', function ($join) {
                        $join->on('wo_msts.id', '=', 'wo_dtls.wo_id')
                            ->where('wo_dtls.active_status', 1);
                    })
                    ->where('wo_msts.supplier_id', $row->id)->where('wo_msts.active_status', 1)
                    ->get();

                if ($wo_val_data->count() > 0) {
                    $wo_val = $wo_val_data[0]->payable_amount;
                } else {
                    $wo_val = 0;
                }

                $party_data[$key]['account_type'] = 'Account Payable';
                $party_data[$key]['balance_amount'] = number_format($wo_val - $trans_balance,2);
            } else if ($row->party_type_id == 4) {

                $service_data = Service::select(
                    DB::raw('SUM(amount) as payable_amount')
                )
                    ->where('party_id', $row->id)->where('active_status', 1)
                    ->get();

                if ($service_data->count() > 0) {
                    $service_amount = $service_data[0]->payable_amount;
                } else {
                    $service_amount = 0;
                }

                $party_data[$key]['account_type'] = 'Account Payable';
                $party_data[$key]['balance_amount'] =  number_format($trans_balance - $service_amount,2);
            } else {

                if ($trans_balance > 0) {
                    $party_data[$key]['account_type'] = 'Account Payable';
                    $party_data[$key]['balance_amount'] = $trans_balance;
                } else if ($trans_balance < 0) {
                    $party_data[$key]['account_type'] = 'Account Receivable';
                    $party_data[$key]['balance_amount'] = abs($trans_balance);
                } else {
                    $party_data[$key]['account_type'] = 'Account Close';
                    $party_data[$key]['balance_amount'] = $trans_balance;
                }
            }
        }
        // dd($data);
        if (count($party_data) > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['getPartyTypeList'] = array(1 => 'Company', 3 => 'Supplier', 4 => 'Employee', 5 => 'Others');
            $response['response_data'] = $party_data;
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }

    public function party_laser_details_rpt($party_uuid)
    {

        $party_data = Party::where('active_status', 1)->where('uuid', $party_uuid)->first();
        $party_id = $party_data->id;
        $party_type_id = $party_data->party_type_id;

        if ($party_type_id == 1) {

            /* $pi_val_after_gd_issue_data = Goods_issue_mst::select(
                'goods_issue_msts.delivery_date as date',
                DB::raw('SUM(pi_dtls.amount) as receivable_amount')
            )
                ->join('goods_issue_dtls', function ($join) {
                    $join->on('goods_issue_dtls.goods_issue_id', '=', 'goods_issue_msts.id')
                        ->where('goods_issue_dtls.active_status', 1);
                })
                ->join('pi_dtls', function ($join) {
                    $join->on('pi_dtls.order_dtls_id', '=', 'goods_issue_dtls.order_dtls_id')
                        ->where('pi_dtls.active_status', 1);
                })
                ->where('goods_issue_msts.company_id', $party_id)->where('goods_issue_msts.active_status', 1)
                ->groupBy('date')
                ->get();

            $trans_data_array = [];
            foreach ($pi_val_after_gd_issue_data as $row) {
                $trans_data_arr = [
                    'date' => $row->date,
                    'trans_type' => 'Receivable',
                    'dr_amount' => 0,
                    'cr_amount' => $row->receivable_amount,
                    'entry_form' => 152,
                ];
                $trans_data_array[] = $trans_data_arr;
            } */
            $lc_data = Lc::select(
                'lcs.uuid',
                'lcs.lc_no',
                'lcs.lc_issue_date',
                DB::raw('SUM(maturity_payments.amount) as payment_amount')
            )
                ->join('maturity_payments', function ($join) {
                    $join->on('maturity_payments.lc_id', '=', 'lcs.id')
                        ->where('maturity_payments.active_status', 1);
                })
                ->where('lcs.company_id', $party_id)->where('lcs.active_status', 1)
                ->groupBy('uuid', 'lc_no', 'lc_issue_date')
                ->get();

            $trans_data_array = [];
            foreach ($lc_data as $val) {
                if ($val->payment_amount > 0) {
                    $trans_data_arr = [
                        'date' => $val->lc_issue_date,
                        'ext_val' => $val->lc_no,
                        'ext_val2' => '/pages/lc/commercia-iInvoice-details/' . $val->uuid,
                        'trans_type' => 'Receivable',
                        'dr_amount' => $val->payment_amount,
                        'cr_amount' => 0,
                        'entry_form' => 152,
                    ];
                    $trans_data_array[] = $trans_data_arr;
                }
            }
            // dd($trans_data_array);

            if (count($trans_data_array) > 0) {
                TemporaryTbl::insert($trans_data_array);
            }
        }

        if ($party_type_id == 3) {
            $wo_val_data = Wo_mst::select(
                'wo_msts.uuid',
                'wo_msts.wo_no as display',
                'wo_msts.wo_date as date',
                DB::raw('SUM(wo_dtls.price*wo_dtls.qnty) as payable_amount')
            )
                ->join('wo_dtls', function ($join) {
                    $join->on('wo_msts.id', '=', 'wo_dtls.wo_id')
                        ->where('wo_dtls.active_status', 1);
                })
                ->where('wo_msts.supplier_id', $party_id)->where('wo_msts.active_status', 1)
                ->groupBy('uuid', 'display', 'date')
                ->get();

            $trans_data_array = [];
            foreach ($wo_val_data as $row) {
                if ($row->payable_amount > 0) {
                    $trans_data_arr = [
                        'date' => $row->date,
                        'ext_val' => $row->display,
                        'ext_val2' => '/pages/work-list/details/' . $row->uuid,
                        'trans_type' => 'Payable',
                        'dr_amount' => 0,
                        'cr_amount' => $row->payable_amount,
                        'entry_form' => 152,
                    ];
                    $trans_data_array[] = $trans_data_arr;
                }
            }

            if (count($trans_data_array) > 0) {
                TemporaryTbl::insert($trans_data_array);
            }
        }

        if ($party_type_id == 4) {

            $service_data = Service::select(
                'services.id',
                'services.service_date',
                DB::raw('SUM(services.amount) as payable_amount')
            )
                ->where('services.party_id', $party_id)->where('services.active_status', 1)
                ->groupBy('id', 'service_date')
                ->get();

            $trans_data_array = [];
            foreach ($service_data as $row) {
                if ($row->payable_amount > 0) {
                    $trans_data_arr = [
                        'date' => $row->service_date,
                        'ext_val' => 'SV-' . $row->id,
                        'ext_val2' => '',
                        'trans_type' => 'Payable',
                        'dr_amount' => 0,
                        'cr_amount' => $row->payable_amount,
                        'entry_form' => 152,
                    ];
                    $trans_data_array[] = $trans_data_arr;
                }
            }

            if (count($trans_data_array) > 0) {
                TemporaryTbl::insert($trans_data_array);
            }
        }

        $trans_data = Transaction::select('id', 'uuid', 'trans_page', 'trans_type_id', 'date', 'amount')
            ->where('active_status', 1)->where('party_type_id', $party_type_id)->where('party_id', $party_id)->get();

        if ($trans_data->count() > 0) {
            $trans_data_array = [];
            foreach ($trans_data as $row) {
                if ($row->amount > 0) {
                    $trans_data_arr = [
                        'date' => $row->date,
                        'ext_val' => 'TR-' . $row->id,
                        'ext_val2' => '/pages/transaction/details/' . $row->uuid,
                        'entry_form' => 152,
                    ];

                    if ($row->trans_page == 4) {
                        $trans_data_arr['dr_amount'] = 0;
                        $trans_data_arr['cr_amount'] = $row->amount;

                        if ($row->trans_type_id == 1) {
                            $trans_data_arr['trans_type'] = 'Payable';
                        }
                        if ($row->trans_type_id == 2) {
                            $trans_data_arr['trans_type'] = 'Receivable';
                        }
                    } else if ($row->trans_type_id == 1) {
                        $trans_data_arr['trans_type'] = 'Income';
                        $trans_data_arr['dr_amount'] = 0;
                        $trans_data_arr['cr_amount'] = $row->amount;
                    } else {
                        $trans_data_arr['trans_type'] = 'Expense';
                        $trans_data_arr['dr_amount'] = $row->amount;
                        $trans_data_arr['cr_amount'] = 0;
                    }

                    $trans_data_array[] = $trans_data_arr;
                }
            }
            // dd($trans_data_array);
            if (count($trans_data_array) > 0) {
                TemporaryTbl::insert($trans_data_array);
            }
        }

        $data = TemporaryTbl::orderBy('date')->get();
        // dd($data);
        if ($data->count() > 0) {
            TemporaryTbl::where('entry_form', 152)->delete();
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['getPartyTypeList'] = self::getPartyTypeList();
            $response['party_data'] = $party_data;
            $response['response_data'] = $data;
            return response($response, 200);
        } else {
            $response['getPartyTypeList'] = self::getPartyTypeList();
            $response['party_data'] = $party_data;
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 200);
        }
    }

    public function supplier_leaser_info($party_id)
    {

        $party_data = Party::where('active_status', 1)->where('id', $party_id)->first();
        $party_id = $party_data->id;
        $party_type_id = $party_data->party_type_id;
        $wo_data_amount = $trans_data_amount = $trans_balance = 0;
        if ($party_type_id == 3) {
            $wo_val_after_gd_rcv_data = Goods_rcv_mst::select(
                DB::raw('SUM(wo_dtls.price*goods_rcv_dtls.qnty) as payable_amount')
            )
                ->join('goods_rcv_dtls', function ($join) {
                    $join->on('goods_rcv_dtls.goods_rcv_id', '=', 'goods_rcv_msts.id')
                        ->where('goods_rcv_dtls.active_status', 1);
                })
                ->join('wo_dtls', function ($join) {
                    $join->on('wo_dtls.id', '=', 'goods_rcv_dtls.wo_dtls_id')
                        ->where('wo_dtls.active_status', 1);
                })
                ->where('goods_rcv_msts.supplier_id', $party_id)->where('goods_rcv_msts.active_status', 1)
                ->first();

            $wo_data_amount = $wo_val_after_gd_rcv_data->payable_amount;

            $trans_data = Transaction::select(
                DB::raw('SUM(CASE WHEN trans_type_id=1 THEN amount ELSE 0 END) as trans_income'),
                DB::raw('SUM(CASE WHEN trans_type_id=2 THEN amount ELSE 0 END) as trans_expense')
            )
                ->where('active_status', 1)
                ->where('party_type_id', $party_type_id)
                ->where('party_id', $party_id)
                ->first();

            $trans_data_amount = abs($trans_data->trans_income - $trans_data->trans_expense);
            $trans_balance = $wo_data_amount - $trans_data_amount;
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 200);
        }

        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['response_data'] = "Total Payable: " . $wo_data_amount . ", Total Paid: " . $trans_data_amount . " and Balance: " . $trans_balance;
        return response($response, 200);
    }

    public function style_list()
    {

        $data = Order_dtl::select(
            'style'
        )
            ->where('order_dtls.active_status', 1)
            ->whereNotNull('order_dtls.style')
            ->groupBy('style')
            ->orderBy('style')
            ->get();

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

    public function style_wise_history(Request $request)
    {
        $query = $request->all();
        $style = $request->style;

        $data = Order_mst::select(
            'wo_msts.id',
            'wo_msts.wo_no',
            'wo_msts.wo_date',
            'c.name as supplier_name',
            'products.name as product_name',
            'order_dtls.style',
            'sizes.name as size_name',
            'colors.name as color_name',
            'wo_dtls.qnty as wo_qnty',
            'wo_dtls.price as unit_price',
            'wo_dtls.amount as wo_amount',
            'wo_dtls.remarks'
        )
            ->join('order_dtls', function ($join) {
                $join->on('order_msts.id', '=', 'order_dtls.order_id')
                    ->where('order_dtls.active_status', 1);
            })
            ->join('products', 'products.id', '=', 'order_dtls.product_id')
            ->leftJoin('colors', 'colors.id', '=', 'order_dtls.color_id')
            ->leftJoin('units', 'units.id', '=', 'order_dtls.unit_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'order_dtls.size_id')
            ->join('wo_dtls', function ($join) {
                $join->on('order_msts.id', '=', 'wo_dtls.order_id')
                    ->on('order_dtls.id', '=', 'wo_dtls.order_dtls_id')
                    ->where('wo_dtls.active_status', 1);
            })
            ->join('wo_msts', function ($join) {
                $join->on('wo_msts.id', '=', 'wo_dtls.wo_id')
                    ->on('wo_msts.order_id', '=', 'order_msts.id')
                    ->where('wo_msts.active_status', 1);
            })
            ->leftJoin('parties as c', 'c.id', '=', 'wo_msts.supplier_id')
            ->where('order_msts.active_status', 1)
            ->when($style, function ($query) use ($style) {
                $query->where('order_dtls.style', 'like', "%$style%");
            })
            ->orderByDesc('wo_msts.id')
            ->get();

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

    public function supplier_wise_wo_history(Request $request)
    {
        $query = $request->all();
        $supplier_id = $request->supplier_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;

        $data = Order_mst::select(
            'wo_msts.id',
            'wo_msts.wo_no',
            'wo_msts.wo_date',
            'c.name as supplier_name',
            'products.name as product_name',
            'order_dtls.style',
            'sizes.name as size_name',
            'colors.name as color_name',
            'units.name as unit_name',
            'wo_dtls.qnty as wo_qnty',
            'wo_dtls.price as unit_price',
            'wo_dtls.amount as wo_amount',
            'wo_dtls.remarks'
        )
            ->join('order_dtls', function ($join) {
                $join->on('order_msts.id', '=', 'order_dtls.order_id')
                    ->where('order_dtls.active_status', 1);
            })
            ->join('wo_dtls', function ($join) {
                $join->on('order_msts.id', '=', 'wo_dtls.order_id')
                    ->on('order_dtls.id', '=', 'wo_dtls.order_dtls_id')
                    ->where('wo_dtls.active_status', 1);
            })
            ->join('wo_msts', function ($join) {
                $join->on('wo_msts.id', '=', 'wo_dtls.wo_id')
                    ->on('wo_msts.order_id', '=', 'order_msts.id')
                    ->where('wo_msts.active_status', 1);
            })
            ->join('products', 'products.id', '=', 'wo_dtls.product_id')
            ->leftJoin('colors', 'colors.id', '=', 'wo_dtls.color_id')
            ->leftJoin('units', 'units.id', '=', 'wo_dtls.unit_id')
            ->leftJoin('sizes', 'sizes.id', '=', 'wo_dtls.size_id')
            ->join('parties as c', 'c.id', '=', 'wo_msts.supplier_id')
            ->where('order_msts.active_status', 1)
            ->when($supplier_id, function ($query) use ($supplier_id) {
                $query->where('wo_msts.supplier_id', $supplier_id);
            })
            ->when($start_date, function ($query) use ($start_date) {
                $query->where('wo_msts.wo_date', '>=', $start_date);
            })
            ->when($end_date, function ($query) use ($end_date) {
                $query->where('wo_msts.wo_date', '<=', $end_date);
            })
            ->orderByDesc('wo_msts.id')
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

    public function employee_wise_tada_history(Request $request)
    {
        $party_id = $request->employee_id;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $party_data = Party::where('active_status', 1)->where('id', $party_id)->first();
        if(!$party_data)
        {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
        $party_id = $party_data->id;
        $party_type_id = $party_data->party_type_id;

        if ($party_type_id == 4) {

            $service_data = Service::select(
                'id',
                'service_date',
                DB::raw('SUM(amount) as payable_amount')
            )
                ->where('party_id', $party_id)
                ->where('purpose_id', 1)
                ->where('active_status', 1)
                ->groupBy('id', 'service_date')
                ->when($start_date, function ($query) use ($start_date) {
                    $query->where('service_date', '>=', $start_date);
                })
                ->when($end_date, function ($query) use ($end_date) {
                    $query->where('service_date', '<=', $end_date);
                })
                ->get();

            $trans_data_array = [];
            foreach ($service_data as $row) {
                if ($row->payable_amount > 0) {
                    $trans_data_arr = [
                        'date' => $row->service_date,
                        'ext_val' => 'SV-' . $row->id,
                        'ext_val2' => '',
                        'trans_type' => 'Payable',
                        'dr_amount' => 0,
                        'cr_amount' => $row->payable_amount,
                        'entry_form' => 152,
                    ];
                    $trans_data_array[] = $trans_data_arr;
                }
            }

            if (count($trans_data_array) > 0) {
                TemporaryTbl::insert($trans_data_array);
            }
        }

        $trans_data = Transaction::select('id', 'uuid', 'trans_page', 'trans_type_id', 'date', 'amount')
            ->where('party_type_id', 4)
            ->where('trans_purpose_id', 1)
            ->where('party_id', $party_id)
            ->where('active_status', 1)
            ->when($start_date, function ($query) use ($start_date) {
                $query->where('date', '>=', $start_date);
            })
            ->when($end_date, function ($query) use ($end_date) {
                $query->where('date', '<=', $end_date);
            })
            ->get();

        if ($trans_data->count() > 0) {
            $trans_data_array = [];
            foreach ($trans_data as $row) {
                if ($row->amount > 0) {
                    $trans_data_arr = [
                        'date' => $row->date,
                        'ext_val' => 'TR-' . $row->id,
                        'ext_val2' => '/pages/transaction/details/' . $row->uuid,
                        'entry_form' => 152,
                    ];

                    if ($row->trans_page == 4) {
                        $trans_data_arr['dr_amount'] = 0;
                        $trans_data_arr['cr_amount'] = $row->amount;

                        if ($row->trans_type_id == 1) {
                            $trans_data_arr['trans_type'] = 'Payable';
                        }
                        if ($row->trans_type_id == 2) {
                            $trans_data_arr['trans_type'] = 'Receivable';
                        }
                    } else if ($row->trans_type_id == 1) {
                        $trans_data_arr['trans_type'] = 'Income';
                        $trans_data_arr['dr_amount'] = 0;
                        $trans_data_arr['cr_amount'] = $row->amount;
                    } else {
                        $trans_data_arr['trans_type'] = 'Expense';
                        $trans_data_arr['dr_amount'] = $row->amount;
                        $trans_data_arr['cr_amount'] = 0;
                    }

                    $trans_data_array[] = $trans_data_arr;
                }
            }
            // dd($trans_data_array);
            if (count($trans_data_array) > 0) {
                TemporaryTbl::insert($trans_data_array);
            }
        }

        $data = TemporaryTbl::orderBy('date')->get();
        // dd($data);
        if ($data->count() > 0) {
            TemporaryTbl::where('entry_form', 152)->delete();
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

    public static function limit($query)
    {
        $paginate = 10;
        if (array_key_exists('limit', $query)) {
            if ($query['limit']) {
                $paginate = $query['limit'];
            }
        }
        return $paginate;
    }
}
