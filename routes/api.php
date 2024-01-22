<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\DocAcptController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GoodsIssueController;
use App\Http\Controllers\GoodsRcvController;
use App\Http\Controllers\InquireController;
use App\Http\Controllers\LcController;
use App\Http\Controllers\MaturityPaymentController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PartyController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PiController;
use App\Http\Controllers\PiTncController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SampleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SizeController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransPurposeController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserPermissionController;
use App\Http\Controllers\WoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

/* Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
}); */

Route::group(['middleware' => ['cors', 'json.response']], function () {

    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change_password', [AuthController::class, 'changePassword']);
        // Route::apiResource('role', RoleController::class);

        //Role
        Route::get('role_get_all', [RoleController::class, 'index']);
        Route::post('role_add', [RoleController::class, 'store']);
        Route::post('role_update', [RoleController::class, 'update']);
        Route::delete('role_delete/{uuid}', [RoleController::class, 'destroy']);

        //Product
        Route::get('product_get_all', [ProductController::class, 'index']);
        Route::post('product_add', [ProductController::class, 'store']);
        Route::post('product_update', [ProductController::class, 'update']);
        Route::delete('product_delete/{uuid}', [ProductController::class, 'destroy']);

        //Color
        Route::get('color_get_all', [ColorController::class, 'index']);
        Route::post('color_add', [ColorController::class, 'store']);
        Route::post('color_update', [ColorController::class, 'update']);
        Route::delete('color_delete/{uuid}', [ColorController::class, 'destroy']);

        //size
        Route::get('size_get_all', [SizeController::class, 'index']);
        Route::post('size_add', [SizeController::class, 'store']);
        Route::post('size_update', [SizeController::class, 'update']);
        Route::delete('size_delete/{uuid}', [SizeController::class, 'destroy']);

        //unit
        Route::get('unit_get_all', [UnitController::class, 'index']);
        Route::post('unit_add', [UnitController::class, 'store']);
        Route::post('unit_update', [UnitController::class, 'update']);
        Route::delete('unit_delete/{id}', [UnitController::class, 'destroy']);

        //pi_tnc
        Route::get('pi_tnc_get_all', [PiTncController::class, 'index']);
        Route::post('pi_tnc_add', [PiTncController::class, 'store']);
        Route::post('pi_tnc_update', [PiTncController::class, 'update']);
        Route::delete('pi_tnc_delete/{uuid}', [PiTncController::class, 'destroy']);

        //document
        Route::get('document_get_all', [DocumentController::class, 'index']);
        Route::post('document_add', [DocumentController::class, 'store']);
        Route::post('document_update', [DocumentController::class, 'update']);
        Route::delete('document_delete/{uuid}', [DocumentController::class, 'destroy']);

        //bank
        Route::get('bank_get_all', [BankController::class, 'index']);
        Route::get('bank_laser/{id?}', [BankController::class, 'bank_laser_info']);
        Route::get('bank_get/{uuid?}', [BankController::class, 'getBankInfo']);
        Route::post('bank_add', [BankController::class, 'store']);
        Route::post('bank_update', [BankController::class, 'update']);
        Route::delete('bank_delete/{id}', [BankController::class, 'destroy']);
        Route::get('bank_approval/{approval_type?}', [BankController::class, 'bank_approval_list']);
        Route::post('bank_approval_update', [BankController::class, 'bank_approval_update']);

        //party
        Route::get('party_get_all', [PartyController::class, 'index']);
        Route::get('party_add', [PartyController::class, 'add']);
        Route::post('party_add', [PartyController::class, 'store']);
        Route::post('party_update', [PartyController::class, 'update']);
        Route::delete('party_delete/{uuid}', [PartyController::class, 'destroy']);
        Route::get('party_details/{uuid?}', [PartyController::class, 'getPartyInfo']);

        //settings
        Route::get('settings_get_all', [SettingsController::class, 'index']);
        Route::post('settings_update', [SettingsController::class, 'update']);

        //Transaction Purpose
        Route::get('trans_purpose_get_all', [TransPurposeController::class, 'index']);
        Route::post('trans_purpose_add', [TransPurposeController::class, 'store']);
        Route::post('trans_purpose_update', [TransPurposeController::class, 'update']);
        Route::delete('trans_purpose_delete/{uuid}', [TransPurposeController::class, 'destroy']);

        //User
        Route::get('user_get_all', [AuthController::class, 'index']);
        Route::get('user_add', [AuthController::class, 'add']);
        Route::post('user_add', [AuthController::class, 'register']);
        Route::post('user_update', [AuthController::class, 'update']);
        Route::get('user_details/{uuid}', [AuthController::class, 'getUserInfo']);

        //Permission
        Route::get('permission', [PermissionController::class, 'index']);
        // Route::post('permission_details', [PermissionController::class, 'getPermissionInfo']);
        Route::get('permission_details_data/{role_id}', [PermissionController::class, 'getPermissionData']);
        Route::post('permission', [PermissionController::class, 'update']);

        // User Permission
        Route::get('user_permission_get', [UserPermissionController::class, 'index']);
        Route::get('role_wise_user/{role_id}', [UserPermissionController::class, 'getRoleWiseUser']);
        Route::post('user_permission_details', [UserPermissionController::class, 'getPermissionInfo']);
        Route::post('user_permission', [UserPermissionController::class, 'update']);
        Route::get('permission_check/{module_name?}', [UserPermissionController::class, 'permission_check']);

        //Transaction
        Route::get('transaction_get_all', [TransactionController::class, 'index']);
        Route::get('transaction_add', [TransactionController::class, 'add']);
        Route::post('transaction_add', [TransactionController::class, 'store']);
        Route::post('transaction_update', [TransactionController::class, 'update']);
        Route::delete('transaction_delete/{uuid}', [TransactionController::class, 'destroy']);
        Route::get('transaction_details/{uuid}', [TransactionController::class, 'getTransInfo']);

        //Inquire
        Route::get('inquire_get_all', [InquireController::class, 'index']);
        Route::get('inquire_add', [InquireController::class, 'add']);
        Route::post('inquire_add', [InquireController::class, 'store']);
        Route::post('inquire_update', [InquireController::class, 'update']);
        Route::delete('inquire_delete/{uuid}', [InquireController::class, 'destroy']);
        Route::get('inquire_details/{uuid}', [InquireController::class, 'getInquireInfo']);

        // Sample
        Route::get('sample_get_all', [SampleController::class, 'index']);
        Route::get('sample_add', [SampleController::class, 'add']);
        Route::post('sample_add', [SampleController::class, 'store']);
        Route::post('sample_update', [SampleController::class, 'update']);
        Route::delete('sample_delete/{uuid}', [SampleController::class, 'destroy']);
        Route::get('sample_details/{uuid}', [SampleController::class, 'getSampleInfo']);
        Route::get('sample_log_details/{inquire_dtls}/{uuid}', [SampleController::class, 'getSampleLogInfo']);
        Route::post('sample_log_add', [SampleController::class, 'log_store']);

        //PO
        Route::get('po_get_all', [OrderController::class, 'index']);
        Route::get('po_add', [OrderController::class, 'add']);
        Route::post('po_add', [OrderController::class, 'store']);
        Route::post('po_update', [OrderController::class, 'update']);
        Route::delete('po_delete/{uuid}', [OrderController::class, 'destroy']);
        Route::get('po_details/{uuid}', [OrderController::class, 'getOrderInfo']);

        //Quotation
        Route::get('quotation_get_all', [QuotationController::class, 'index']);
        Route::get('quotation_add', [QuotationController::class, 'add']);
        Route::post('quotation_add', [QuotationController::class, 'store']);
        Route::post('quotation_update', [QuotationController::class, 'update']);
        Route::delete('quotation_delete/{uuid}', [QuotationController::class, 'destroy']);
        Route::get('quotation_details/{uuid}', [QuotationController::class, 'getQuotationInfo']);

        //WO
        Route::get('wo_get_all', [WoController::class, 'index']);
        Route::get('wo_add', [WoController::class, 'add']);
        Route::post('wo_add', [WoController::class, 'store']);
        Route::post('wo_update', [WoController::class, 'update']);
        Route::delete('wo_delete/{id?}', [WoController::class, 'destroy']);
        Route::get('wo_details/{uuid}', [WoController::class, 'getWoInfo']);

        //PI
        Route::get('pi_get_all', [PiController::class, 'index']);
        Route::get('pi_add', [PiController::class, 'add']);
        Route::post('pi_add', [PiController::class, 'store']);
        Route::post('pi_update', [PiController::class, 'update']);
        Route::delete('pi_delete/{uuid}', [PiController::class, 'destroy']);
        Route::get('pi_details/{uuid}', [PiController::class, 'getPiInfo']);
        Route::get('pi_wo_add', [PiController::class, 'get_wo_add']);

        //LC
        Route::get('lc_get_all', [LcController::class, 'index']);
        Route::get('lc_add', [LcController::class, 'add']);
        Route::post('lc_add', [LcController::class, 'store']);
        Route::post('lc_update', [LcController::class, 'update']);
        Route::delete('lc_delete/{uuid}', [LcController::class, 'destroy']);
        Route::get('lc_details/{uuid}', [LcController::class, 'getLcInfo']);

        //Document Acceptance
        Route::get('doc_acceptance_get_all', [DocAcptController::class, 'index']);
        Route::get('doc_acceptance_add', [DocAcptController::class, 'add']);
        Route::post('doc_acceptance_add', [DocAcptController::class, 'store']);
        Route::post('doc_acceptance_update', [DocAcptController::class, 'update']);
        Route::delete('doc_acceptance_delete/{uuid}', [DocAcptController::class, 'destroy']);
        Route::get('doc_acceptance_details/{uuid}', [DocAcptController::class, 'getDocAcptInfo']);

        //Maturity Payment
        Route::get('maturity_get_all', [MaturityPaymentController::class, 'index']);
        Route::post('maturity_add', [MaturityPaymentController::class, 'store']);
        Route::post('maturity_update', [MaturityPaymentController::class, 'update']);
        Route::delete('maturity_delete/{uuid}', [MaturityPaymentController::class, 'destroy']);
        Route::get('maturity_details/{uuid}', [MaturityPaymentController::class, 'getMaturityInfo']);

        //Goods Receive
        Route::get('gd_rcv_get_all', [GoodsRcvController::class, 'index']);
        Route::get('gd_rcv_add', [GoodsRcvController::class, 'add']);
        Route::post('gd_rcv_add', [GoodsRcvController::class, 'store']);
        Route::post('gd_rcv_update', [GoodsRcvController::class, 'update']);
        Route::delete('gd_rcv_delete/{uuid}', [GoodsRcvController::class, 'destroy']);
        Route::get('gd_rcv_details/{uuid}', [GoodsRcvController::class, 'getGdRcvInfo']);

        //Goods Issue
        Route::get('gd_issue_get_all', [GoodsIssueController::class, 'index']);
        Route::get('gd_issue_add', [GoodsIssueController::class, 'add']);
        Route::post('gd_issue_add', [GoodsIssueController::class, 'store']);
        Route::post('gd_issue_update', [GoodsIssueController::class, 'update']);
        Route::delete('gd_issue_delete/{uuid}', [GoodsIssueController::class, 'destroy']);
        Route::get('gd_issue_details/{uuid}', [GoodsIssueController::class, 'getGdIssueInfo']);

        //Dashboard & Report
        Route::get('dashboard', [ReportController::class, 'indexAction']);
        Route::get('type_wise_party/{id?}', [ReportController::class, 'type_wise_party_list']);
        Route::post('po_wise_profit_rpt', [ReportController::class, 'po_wise_profit']);
        Route::post('order_details_rpt', [ReportController::class, 'order_details_rpt']);
        Route::post('expenses_history_rpt', [ReportController::class, 'expenses_history_rpt']);
        Route::post('party_laser_rpt', [ReportController::class, 'party_laser_rpt']);
        Route::get('party_laser_details_rpt/{uuid}', [ReportController::class, 'party_laser_details_rpt']);
    });
});



/* Method 	                        Status Code
ok($data) 	                        200
created($data) 	                    201
accepted($data) 	                202
no_content() 	                    204
bad_request($message, $errors) 	    400
unauthenticated($message, $errors) 	401
forbidden($message, $errors) 	    403
not_found($message, $errors) 	    404
method_not_allowed($message, $errors)405
not_acceptable($message, $errors) 	406
teapot($message, $errors) 	        418
unprocessable_entity($message, $errors)422 */
