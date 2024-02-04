<?php

namespace App\Helper;

use DateInterval;
use DatePeriod;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use App\Models\Color;
use App\Models\Party;
use App\Models\Product;
use App\Models\Settings;
use App\Models\Size;
use App\Models\Unit;


trait Helper
{
    public static function get_system_no($table, $prefix)
    {
        $currentYear = date('Y');
        $max_id = DB::table($table)->whereYear('created_at', $currentYear)->count();
        $num = $max_id + 1;

        $data = strtoupper($prefix) . '-' . date('Ym') . str_pad($num, 5, '0', STR_PAD_LEFT);
        return $data;
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

    /* public static function get_system_no($table,$prefix)
    {
        $max_id = DB::table($table)->max('id');
        $num = $max_id+1;
        $data = strtoupper($prefix).'-'.date('Ym').str_pad($num, 5, 0, STR_PAD_LEFT);
        return $data;
    } */

    public static function get_settings_info()
    {
        $data = Settings::first();
        return $data;
    }
    public static function getPartyList($party_type_id)
    {
        $data = Party::select('id', 'name')->where('party_type_id', $party_type_id)->where('active_status', 1)->get();
        return $data;
    }

    public static function getProductList()
    {
        $data = Product::select('id', 'name')->where('active_status', 1)->get();
        return $data;
    }

    public static function getColorList()
    {
        $data = Color::select('id', 'name')->where('active_status', 1)->get();
        return $data;
    }

    public static function getSizeList()
    {
        $data = Size::select('id', 'name')->where('active_status', 1)->get();
        return $data;
    }

    public static function getUnitList()
    {
        $data = Unit::select('id', 'name')->where('active_status', 1)->get();
        return $data;
    }

    public static function getAccountTypeList()
    {
        $data = array(1 => 'Payable', 2 => 'Receivable');
        return $data;
    }

    public static function getGenderList()
    {
        $data = array(1 => 'Male', 2 => 'Female', 3 => 'Other');
        return $data;
    }

    public static function getOrderStatusList()
    {
        $data = array(1 => 'Pending', 2 => 'Completed');
        return $data;
    }

    public static function getActiveStatusList()
    {
        $data = array(1 => 'Active', 2 => 'Deactive');
        return $data;
    }

    public static function getDocPlaceList()
    {
        $data = array(1 => 'Buyer', 2 => 'Seller', 3 => 'Mine');
        return $data;
    }

    public static function getPayTermList()
    {
        $data = array(1 => 'At Sight', 2 => 'Usance');
        return $data;
    }

    public static function getCurrencyList()
    {
        $data = array(1 => 'USD', 2 => 'BDT', 3 => "EURO", 4 => "Pound", 5 => "YEN");
        return $data;
    }

    public static function getCurrencySignList()
    {
        $data = array(1 => '$', 2 => '৳', 3 => '€', 4 => '£', 5 => '¥');
        return $data;
    }
    public static function getCurrencyDecimalList()
    {
        $data = array(1 => 'Cent', 2 => 'Poisa', 3 => 'Cent');
        return $data;
    }

    public static function getQuotationList()
    {
        $data = array(1 => 'Inquire', 2 => 'Order');
        return $data;
    }

    public static function getSeasonList()
    {
        $data = array(1 => 'Summer', 2 => 'Rain', 3 => 'Winter');
        return $data;
    }
    public static function getSeasonYear()
    {
        $data = array(2024 => '2024', 2025 => '2025', 2026 => '2026', 2027 => '2027', 2028 => '2028');
        return $data;
    }

    public static function getPartyTypeList()
    {
        $data = array(1 => 'Company', 2 => 'Buyer', 3 => 'Supplier', 4 => 'Employee', 5 => 'Others');
        return $data;
    }

    public static function getTransTypeList()
    {
        $data = array(1 => 'Income', 2 => 'Expenses');
        return $data;
    }

    public static function getBankTransferMethodList()
    {
        $data = array(1 => 'BEFTN', 2 => 'NPSB', 3 => 'RTGS');
        return $data;
    }

    public static function getTransMethodList()
    {
        $data = array(1 => 'Cash', 2 => 'Bank Check', 3 => 'TT', 4 => 'Bank Deposit', 8 => 'Bank Transfer');
        return $data;
    }

    public static function getTransMethodAllList()
    {
        $data = array(1 => 'Cash', 2 => 'Bank Check', 3 => 'TT', 4 => 'Bank Deposit', 5 => 'LC Payment Receive', 6 => 'Bank Opening Balance', 7 => 'Party Opening Balance', 8 => 'Bank Transfer');
        return $data;
    }

    public static function getTransPageList()
    {
        $data = array(1 => 'Transaction', 2 => 'Maturity', 3 => 'Bank', 4 => 'Party');
        return $data;
    }

    public static function getApprovedList()
    {
        $data = array(1 => 'Approved', 2 => 'Unapproved');
        return $data;
    }

    public static function getModuleList()
    {
        $data = array('Inquiry', 'Quotation', 'Sample', 'Order', 'Work Order', 'Proforma Invoice', 'Goods Receive', 'Goods Delivery', 'LC Management', 'Document Acceptance', 'Maturity', 'Bank', 'Transaction', 'User', 'Party', 'Product', 'Color', 'Size', 'Unit', 'Settings', 'Report', 'Service');
        return $data;
    }

    public static function uploadImage($files, $path)
    {
        $fileName = $files->getClientOriginalName();
        $fileName = str_replace(' ', '-', $fileName);
        $fileName = preg_replace('/[^A-Za-z0-9\-.]/', '', $fileName);
        $fileName = Str::random(6) . time() . Str::random(4) . $fileName;
        $path = 'uploads/' . date('Ym') . '/' . $path;
        $dbName = $path . '/' . $fileName;
        $files->move($path, $fileName);
        $image = $dbName;

        return $image;
    }

    public static function deleteFile($path)
    {
        //        $path = explode("public/",$path);
        File::delete($path);
        //        unlink($path[1]);
    }


    public static function getDatesFromRange($from_date, $to_date, $format = 'Y-m-d', $interval='P1D')
    {

        // Declare an empty array
        $array = array();

        // Variable that store the date interval
        // of period 1 day
        $interval = new DateInterval($interval);

        $realEnd = new DateTime($to_date);
        //add 1 (day or month or year)
        // $realEnd->add($interval);

        $period = new DatePeriod(new DateTime($from_date), $interval, $realEnd);

        // Use loop to store date into array
        foreach ($period as $key => $date) {
            $array[] = $date->format($format);
        }

        // Return the array elements
        return $array;
    }

    ######################################################################################################
    ######################################################################################################
    ######################################################################################################

    public static function sendSuccess($message, $result = null, $code = 200)
    {
        $response = [
            'code'    => $code,
            'status' => 'success',
            'message' => $message,
            'response_data'    => $result,
        ];

        return response($response, $code);
    }

    public static function getDayNames($start_date, $end_date)
    {
        $day_names = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
        $date_list = array();

        // Convert start and end dates to DateTime objects
        $start_date_obj = new DateTime($start_date);
        $end_date_obj = new DateTime($end_date);

        // Create a DateInterval object with one day difference
        $interval = new DateInterval('P1D');

        // Iterate through the date range and add each date to the date_list array
        $current_date = $start_date_obj;
        while ($current_date <= $end_date_obj) {
            $date_list[] = clone $current_date;
            $current_date->add($interval);
        }

        // Get the first three letters of the day names from the date_list array
        $day_name_list = array_map(function ($date) use ($day_names) {
            return substr($day_names[$date->format('w')], 0, 3);
        }, $date_list);

        return $day_name_list;
    }

    public static function getTotalDayCountInDateRange($startDate, $endDate, $dayNames)
    {
        $dayCount = array();
        foreach ($dayNames as $dayName) {
            $dayCount[$dayName] = 0;
        }

        $currentDate = strtotime($startDate);
        $endDate = strtotime($endDate);

        while ($currentDate <= $endDate) {
            $dayOfWeek = date("D", $currentDate);
            if (in_array($dayOfWeek, $dayNames)) {
                $dayCount[$dayOfWeek]++;
            }
            $currentDate = strtotime("+1 day", $currentDate);
        }

        $totalCount = array_sum($dayCount);

        return $totalCount;
    }
    /* public static function getUserInfo(){
        $user = \App\Models\User::where('id',1)->first();
        $token = $user->createToken($user->name)->accessToken;
        return $token;
    } */
}
