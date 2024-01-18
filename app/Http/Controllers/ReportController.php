<?php

namespace App\Http\Controllers;

use App\Models\Goods_issue_mst;
use App\Models\Goods_rcv_mst;
use App\Models\Lc;
use App\Models\Maturity_payment;
use App\Models\Order_mst;
use App\Models\Party;
use App\Models\Pi_mst;
use App\Models\TemporaryTbl;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{

    public function indexAction()
    {
        $startDate = Carbon::now()->subDays(30)->startOfDay();
        $monthDate = Carbon::now()->subMonths(12)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $response['status'] = 'success';
        $response['message'] = 'Data found.';
        $response['total_order'] = Order_mst::where('active_status', 1)->count();
        $response['last_total_order'] = Order_mst::where('active_status', 1)->whereBetween('created_at', [$startDate, $endDate])->count();
        $response['total_pi'] = Pi_mst::where('active_status', 1)->count();
        $response['last_total_pi'] = Pi_mst::where('active_status', 1)->whereBetween('created_at', [$startDate, $endDate])->count();
        $response['mature_lc'] = Maturity_payment::where('active_status', 1)->count();;
        $response['current_lc'] = Lc::where('active_status', 1)->count() - $response['mature_lc'];

        // $response['trans_type_list'] = self::getTransTypeList();
        $response['monthly_income'] = Transaction::select(DB::raw('SUM(amount) as total_amount'), DB::raw("DATE_FORMAT(created_at, '%b') as month"))
            ->where('trans_type_id', 1)->where('active_status', 1)->whereIn('trans_page', [1, 2])
            ->whereBetween('created_at', [$monthDate, $endDate])
            ->groupBy('month')->get();
        $response['monthly_expense'] = Transaction::select(DB::raw('SUM(amount) as total_amount'), DB::raw("DATE_FORMAT(created_at, '%b') as month"))
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

        $response['category_wise_expense'] = Transaction::select('trans_purpose_id', DB::raw('SUM(amount) as total_amount'))
            ->where('active_status', 1)->where('trans_page', 1)->where('trans_type_id', 2)
            ->groupBy('trans_purpose_id')
            ->with('trans_purpose_info')->get();

        return response($response, 200);
    }

    public function type_wise_party_list($type_id)
    {
        $response = self::getPartyList($type_id);
        return response($response, 200);
    }

    public function po_wise_profit(Request $request)
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
    }

    public function order_details_rpt(Request $request)
    {
        $query = $request->all();
        $data = Order_mst::select(
            'order_msts.id',
            'a.name as company_name',
            'b.name as buyer_name',
            'products.name as product_name',
            'order_msts.order_person',
            'order_dtls.style',
            'sizes.name as size_name',
            'colors.name as color_name',
            'order_dtls.file_image as attachment_file',
            'c.name as supplier_name',
            'order_msts.order_date',
            'order_msts.delivery_req_date',
            'order_dtls.qnty',
            'order_msts.delivery_req_date',
            'pi_msts.pi_no',
            'order_dtls.order_status',
            'order_dtls.remarks',
            DB::raw('SUM(pi_dtls.amount) as pi_amount'),
            DB::raw('SUM(wo_dtls.qnty) as wo_qnty'),
            DB::raw('SUM(pi_dtls.amount) as pi_amount'),
            DB::raw('SUM(wo_dtls.qnty) as wo_qnty')
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
                    ->where('wo_dtls.active_status', 1);
            })
            ->leftJoin('wo_msts', function ($join) {
                $join->on('wo_msts.id', '=', 'wo_dtls.wo_id')
                    ->where('wo_msts.active_status', 1);
            })
            ->leftJoin('parties as c', 'c.id', '=', 'wo_msts.supplier_id')
            ->leftJoin('pi_dtls', function ($join) {
                $join->on('order_msts.id', '=', 'pi_dtls.order_id')
                    ->on('wo_dtls.id', '=', 'pi_dtls.wo_dtls_id')
                    ->on('wo_msts.id', '=', 'pi_dtls.wo_id')
                    ->where('pi_dtls.active_status', 1);
            })
            ->leftJoin('pi_msts', function ($join) {
                $join->on('pi_dtls.pi_id', '=', 'pi_msts.id')
                    ->where('pi_msts.active_status', 1);
            })
            ->where('order_msts.active_status', 1)
            ->orderByDesc('order_msts.id')
            ->groupBy('id', 'company_name', 'buyer_name', 'product_name', 'order_person', 'style', 'size_name', 'color_name', 'attachment_file', 'supplier_name', 'order_date', 'delivery_req_date', 'qnty', 'delivery_req_date', 'pi_no', 'order_status', 'remarks');


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
        $data = Transaction::with('trans_purpose_info', 'party_info', 'bank_info')
            ->where('transactions.trans_page', 1)
            ->where('transactions.trans_type_id', 2)
            ->where('transactions.party_type_id', 4)
            ->where('transactions.active_status', 1)
            ->orderByDesc('transactions.date');

        if ($request->party_id) {
            $data = $data->where('transactions.party_id', $request->party_id);
        }
        if ($request->puspose_id) {
            $data = $data->where('transactions.trans_purpose_id', $request->puspose_id);
        }
        if ($request->start_date) {
            $data = $data->whereDate('transactions.date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $data = $data->whereDate('transactions.date', '<=', $request->end_date);
        }

        $data = $data->paginate(self::limit($query));

        if ($data->count() > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['trans_method_list'] = self::getTransMethodAllList();
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

        if ($request->party_id) {
            $party_data = $party_data->where('party_type_id', $request->party_id);
        } else {
            $party_data = $party_data->whereIn('party_type_id', [1, 3, 5]);
        }
        $party_data = $party_data->get();

        $data = [];
        foreach ($party_data as $row) {
            $trans_data = Transaction::select(
                'party_id',
                DB::raw('SUM(CASE WHEN transactions.trans_type_id = 1 THEN transactions.amount ELSE 0 END) as income_amount'),
                DB::raw('SUM(CASE WHEN transactions.trans_type_id = 2 THEN transactions.amount ELSE 0 END) as expense_amount')
            )
                ->where('active_status', 1)->where('party_type_id', $row->party_type_id)->where('party_id', $row->id)
                ->groupBy('party_id')->get();

            if ($trans_data->count() > 0) {
                if ($row->party_type_id == 3) {
                    $trans_balance = $trans_data[0]->expense_amount - $trans_data[0]->income_amount;
                } else {
                    $trans_balance = $trans_data[0]->income_amount - $trans_data[0]->expense_amount;
                }
            } else {
                $trans_balance = 0;
            }

            if ($row->party_type_id == 1) {

                $pi_val_after_gd_issue_data = Goods_issue_mst::select(
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

                $data[] = array('party_type' => $row->party_type_id, 'party_name' => $row->name, 'account_type' => 'Account Receivable', 'balance_amount' => $pi_val_after_gd_issue - $trans_balance);
            } else if ($row->party_type_id == 3) {

                $wo_val_after_gd_rcv_data = Goods_rcv_mst::select(
                    DB::raw('SUM(wo_dtls.amount) as payable_amount')
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

                $data[] = array('party_type' => $row->party_type_id, 'party_name' => $row->name, 'account_type' => 'Account Payable', 'balance_amount' => $wo_val_after_gd_rcv - $trans_balance);
            } else {

                if ($trans_balance > 0) {
                    $data[] = array('party_type' => $row->party_type_id, 'party_name' => $row->name, 'account_type' => 'Account Payable', 'balance_amount' => $trans_balance);
                } else if ($trans_balance < 0) {
                    $data[] = array('party_type' => $row->party_type_id, 'party_name' => $row->name, 'account_type' => 'Account Receivable', 'balance_amount' => abs($trans_balance));
                } else {
                    $data[] = array('party_type' => $row->party_type_id, 'party_name' => $row->name, 'account_type' => 'Account Close', 'balance_amount' => $trans_balance);
                }
            }
        }
        // dd($data);
        if (count($data) > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Data found.';
            $response['getPartyTypeList'] = array(1 => 'Company', 3 => 'Supplier', 5 => 'Others');;
            $response['response_data'] = $data;
            return response($response, 200);
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Data not found.';
            return response($response, 422);
        }
    }

    public function party_laser_details_rpt($party_id)
    {
        $party_id = $party_id;

        $party_data = Party::where('active_status', 1)->where('id', $party_id)->first();
        $party_id = $party_data->id;
        $party_type_id = $party_data->party_type_id;

        if ($party_type_id == 1) {

            $pi_val_after_gd_issue_data = Goods_issue_mst::select(
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
            }

            if (count($trans_data_array) > 0) {
                TemporaryTbl::insert($trans_data_array);
            }
        }

        if ($party_type_id == 3) {

            $wo_val_after_gd_rcv_data = Goods_rcv_mst::select(
                'goods_rcv_msts.rcv_date as date',
                DB::raw('SUM(wo_dtls.amount) as payable_amount')
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
                ->groupBy('date')
                ->get();

            $trans_data_array = [];
            foreach ($wo_val_after_gd_rcv_data as $row) {
                $trans_data_arr = [
                    'date' => $row->date,
                    'trans_type' => 'Payable',
                    'dr_amount' => $row->payable_amount,
                    'cr_amount' => 0,
                    'entry_form' => 152,
                ];
                $trans_data_array[] = $trans_data_arr;
            }

            if (count($trans_data_array) > 0) {
                TemporaryTbl::insert($trans_data_array);
            }
        }

        $trans_data = Transaction::select('date', 'amount')
            ->where('active_status', 1)->where('party_type_id', $party_type_id)->where('party_id', $party_id)->get();

        if ($trans_data->count() > 0) {
            $trans_data_array = [];
            foreach ($trans_data as $row) {
                if ($row->trans_type_id == 1) {
                    $trans_data_arr = [
                        'date' => $row->date,
                        'trans_type' => 'Payment',
                        'dr_amount' => 0,
                        'cr_amount' => $row->amount,
                        'entry_form' => 152,
                    ];
                } else {
                    $trans_data_arr = [
                        'date' => $row->date,
                        'trans_type' => 'Payment',
                        'dr_amount' => $row->amount,
                        'cr_amount' => 0,
                        'entry_form' => 152,
                    ];
                }
                $trans_data_array[] = $trans_data_arr;
            }

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
            $response['getPartyTypeList'] = array(1 => 'Company', 3 => 'Supplier', 5 => 'Others');
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
