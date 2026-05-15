<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarangController;
use App\Http\Controllers\Api\DoController;
use App\Http\Controllers\Api\CustomerController; 
use App\Http\Controllers\Api\ReportController;  
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Services\DatabaseManager;

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

Route::get('/cabang', [AuthController::class, 'getCabangList']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes (TANPA middleware cabang)
Route::middleware(['auth.session'])->group(function () {
    Route::get('/user', [AuthController::class, 'getUser']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // BARANG
    Route::prefix('v1/barang')->group(function () {
        Route::get('/', [BarangController::class, 'index']);
        Route::get('/max-kode', [BarangController::class, 'maxKode']);
        Route::get('/filter-options', [BarangController::class, 'filterOptions']);
        Route::get('/all-distinct-values', [BarangController::class, 'allDistinctValues']);
        Route::get('/distinct-values', [BarangController::class, 'distinctValues']);
        Route::get('/{kode}/detail-stok', [BarangController::class, 'detailStok']);
        Route::post('/', [BarangController::class, 'store']);
        Route::get('/{id}', [BarangController::class, 'show']);
        Route::put('/{id}', [BarangController::class, 'update']);
        Route::delete('/{id}', [BarangController::class, 'destroy']);
    });
    
    // CUSTOMER
    Route::prefix('v1/customer')->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::get('/max-kode', [CustomerController::class, 'maxKode']);
        Route::get('/filter-options', [CustomerController::class, 'filterOptions']);
        Route::get('/all-distinct-values', [CustomerController::class, 'allDistinctValues']);
        Route::get('/distinct-values', [CustomerController::class, 'distinctValues']);
        Route::get('/{id}/detail', [CustomerController::class, 'detail']);
        Route::post('/', [CustomerController::class, 'store']);
        Route::get('/{id}', [CustomerController::class, 'show']);
        Route::put('/{id}', [CustomerController::class, 'update']);
        Route::delete('/{id}', [CustomerController::class, 'destroy']);
    });
    
    // DO
    Route::prefix('do')->group(function () {
        Route::get('/', [DoController::class, 'index']);
        Route::get('/generate-number', [DoController::class, 'generateNumber']);
        Route::get('/{nomor}', [DoController::class, 'show']);
        Route::get('/{nomor}/detail', [DoController::class, 'getDetail']);
        Route::post('/', [DoController::class, 'store']);
        Route::put('/{nomor}', [DoController::class, 'update']);
        Route::delete('/{nomor}', [DoController::class, 'destroy']);
    });

    // REPORT
    Route::prefix('v1/report')->group(function () {
        Route::get('/persediaan', [ReportController::class, 'persediaan']);
        Route::get('/pembelian', [ReportController::class, 'pembelian']);
        Route::get('/pembelian/{nomor}/detail', [ReportController::class, 'pembelianDetail']);
        Route::get('pembelian-per-item', [ReportController::class, 'pembelianPerItem']);
        Route::get('/penjualan', [ReportController::class, 'penjualan']);
        Route::get('/penjualan/{nomor}/detail', [ReportController::class, 'penjualanDetail']);
    });
    
    // GUDANG
    Route::get('/gudang', function () {
        $gudang = DB::connection('mysql')
            ->table('tgudang')
            ->select('gdg_kode as kode', 'gdg_nama as nama')
            ->get();
        return response()->json(['success' => true, 'data' => $gudang]);
    });
    
    // SALESMAN (untuk lookup)
    Route::get('/salesman', function (Request $request) {
        $search = $request->query('search');
        $query = DB::connection('mysql')
            ->table('tsalesman')
            ->select('sls_kode', 'sls_nama', 'sls_alamat');
        
        if ($search) {
            $query->where('sls_kode', 'LIKE', "%{$search}%")
                  ->orWhere('sls_nama', 'LIKE', "%{$search}%");
        }
        
        $data = $query->limit(100)->get();
        return response()->json(['success' => true, 'data' => $data]);
    });
    
    // JENIS CUSTOMER
    Route::get('/jenis-customer', function () {
        $data = DB::connection('mysql')
            ->table('tjeniscustomer')
            ->select('jc_kode as kode', 'jc_nama as nama')
            ->get();
        return response()->json(['success' => true, 'data' => $data]);
    });
    
    // GOLONGAN CUSTOMER
    Route::get('/golongan-customer', function () {
        $data = DB::connection('mysql')
            ->table('tgolongancustomer')
            ->select('gc_kode as kode', 'gc_nama as nama')
            ->get();
        return response()->json(['success' => true, 'data' => $data]);
    });
    
    // GROUP / TIPE BARANG
    Route::get('/group', function () {
        $data = DB::connection('mysql')
            ->table('tgroup')
            ->select('gr_kode as kode', 'gr_nama as nama')
            ->get();
        return response()->json(['success' => true, 'data' => $data]);
    });
    
    // KATEGORI
    Route::get('/kategori', function () {
        $data = DB::connection('mysql')
            ->table('tkategori')
            ->select('ktg_kode as kode', 'ktg_nama as nama')
            ->get();
        return response()->json(['success' => true, 'data' => $data]);
    });
    
    // SUPPLIER
    Route::get('/supplier', function () {
        $data = DB::connection('mysql')
            ->table('tsupplier')
            ->select('sup_kode as kode', 'sup_nama as nama')
            ->get();
        return response()->json(['success' => true, 'data' => $data]);
    });
});