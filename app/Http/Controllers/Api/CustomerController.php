<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
     private function getCabangKode(Request $request): string
    {
        // Prioritas 1: Dari header X-Cabang-Kode
        $kode = $request->header('X-Cabang-Kode');
        
        // Prioritas 2: Dari session (fallback)
        if (!$kode) {
            $kode = session('user.cabang_kode');
        }
        
        // Prioritas 3: Default
        if (!$kode) {
            $kode = '00';
        }
        
        Log::info('CustomerController - Cabang Kode', [
            'from_header' => $request->header('X-Cabang-Kode'),
            'from_session' => session('user.cabang_kode'),
            'final' => $kode
        ]);
        
        return $kode;
    }

    /**
     * GET /api/v1/customer
     */
    public function index(Request $request)
    {
        try {
            $cabangKode = $this->getCabangKode($request);
            
            $search = $request->query('search');
            $sortBy = $request->query('sort_by', 'Nama');
            $sortOrder = $request->query('sort_order', 'asc');
            $filters = $request->query('filters');
            $perPage = $request->query('per_page', 15);
            
            $filtersArray = $filters ? json_decode($filters, true) : [];
            
            $query = DB::connection('mysql')
                ->table('tcustomer as c')
                ->select([
                    'c.cus_kode as Kode',
                    'c.cus_nama as Nama',
                    'c.cus_alamat as Alamat',
                    'gc.gc_nama as Golongan',
                    'c.cus_kota as Kota',
                    'c.cus_telp as Telp',
                    'c.cus_fax as Fax',
                    'c.cus_cp as Contact',
                    'c.cus_piutang as Piutang',
                    'jc.jc_nama as Jenis_Customer',
                    'c.cus_npwp as NPWP',
                    'c.cus_namanpwp as NAMAnpwp',
                    'c.cus_alamatnpwp as ALAMATNPWP',
                    DB::raw('(SELECT byc_tanggal FROM tbayarcus_hdr WHERE byc_cus_kode = c.cus_kode ORDER BY byc_tanggal DESC LIMIT 1) as last_paid'),
                    'c.cus_top as Top',
                    DB::raw("IF(c.cus_locked = 0, 'Open', 'Locked') as Locked"),
                    DB::raw('(SELECT sls_nama FROM tsalescustomer sc INNER JOIN tsalesman s ON s.sls_kode = sc.sc_sls_kode WHERE sc.sc_cus_kode = c.cus_kode LIMIT 1) as Marketing')
                ])
                ->leftJoin('tgolongancustomer as gc', 'c.cus_gc_kode', '=', 'gc.gc_kode')
                ->leftJoin('tjeniscustomer as jc', 'c.cus_jc_kode', '=', 'jc.jc_kode')
                ->where('c.cus_cabang', $cabangKode); // ✅ FILTER BY CABANG DARI HEADER
            
            // Global search
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('c.cus_kode', 'LIKE', "%{$search}%")
                      ->orWhere('c.cus_nama', 'LIKE', "%{$search}%")
                      ->orWhere('c.cus_alamat', 'LIKE', "%{$search}%")
                      ->orWhere('c.cus_kota', 'LIKE', "%{$search}%")
                      ->orWhere('c.cus_telp', 'LIKE', "%{$search}%")
                      ->orWhere('c.cus_cp', 'LIKE', "%{$search}%");
                });
            }
            
            // Advanced filters
            $columnMap = [
                'Kode' => 'c.cus_kode',
                'Nama' => 'c.cus_nama',
                'Alamat' => 'c.cus_alamat',
                'Golongan' => 'gc.gc_nama',
                'Kota' => 'c.cus_kota',
                'Telp' => 'c.cus_telp',
                'Fax' => 'c.cus_fax',
                'Contact' => 'c.cus_cp',
                'Piutang' => 'c.cus_piutang',
                'Jenis_Customer' => 'jc.jc_nama',
                'NPWP' => 'c.cus_npwp',
                'Top' => 'c.cus_top',
                'Locked' => DB::raw("IF(c.cus_locked = 0, 'Open', 'Locked')"),
            ];
            
            foreach ($filtersArray as $field => $value) {
                if ($value && isset($columnMap[$field])) {
                    $column = $columnMap[$field];
                    if (is_array($value) && count($value) > 0) {
                        $query->whereIn($column, $value);
                    } elseif (!is_array($value) && $value !== '') {
                        $query->where($column, 'LIKE', "%{$value}%");
                    }
                }
            }
            
            // Sorting
            $sortColumnMap = [
                'Kode' => 'c.cus_kode',
                'Nama' => 'c.cus_nama',
                'Alamat' => 'c.cus_alamat',
                'Golongan' => 'gc.gc_nama',
                'Kota' => 'c.cus_kota',
                'Telp' => 'c.cus_telp',
                'Contact' => 'c.cus_cp',
                'Piutang' => 'c.cus_piutang',
                'Jenis_Customer' => 'jc.jc_nama',
                'Top' => 'c.cus_top',
            ];
            
            $sortColumn = $sortColumnMap[$sortBy] ?? 'c.cus_nama';
            $query->orderBy($sortColumn, $sortOrder);
            
            $customers = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $customers->items(),
                'pagination' => [
                    'current_page' => $customers->currentPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                    'last_page' => $customers->lastPage()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * GET /api/v1/customer/filter-options
     */
    public function filterOptions(Request $request)
    {
        try {
            $cabangKode = $this->getCabangKode($request);
            
            // ✅ KODE CUSTOMER
            $kodeList = DB::connection('mysql')
                ->table('tcustomer')
                ->where('cus_cabang', $cabangKode)
                ->select('cus_kode as value', DB::raw('COUNT(*) as count'))
                ->whereNotNull('cus_kode')
                ->groupBy('cus_kode')
                ->orderBy('cus_kode')
                ->limit(1000)
                ->get()
                ->map(fn($row) => ['label' => $row->value, 'value' => $row->value, 'count' => $row->count]);
            
            // ✅ NAMA CUSTOMER
            $namaList = DB::connection('mysql')
                ->table('tcustomer')
                ->where('cus_cabang', $cabangKode)
                ->select('cus_nama as value', DB::raw('COUNT(*) as count'))
                ->whereNotNull('cus_nama')
                ->groupBy('cus_nama')
                ->orderBy('cus_nama')
                ->limit(1000)
                ->get()
                ->map(fn($row) => ['label' => $row->value, 'value' => $row->value, 'count' => $row->count]);
            
            // ✅ ALAMAT
            $alamatList = DB::connection('mysql')
                ->table('tcustomer')
                ->where('cus_cabang', $cabangKode)
                ->select('cus_alamat as value', DB::raw('COUNT(*) as count'))
                ->whereNotNull('cus_alamat')
                ->groupBy('cus_alamat')
                ->orderBy('cus_alamat')
                ->limit(500)
                ->get()
                ->map(fn($row) => ['label' => $row->value, 'value' => $row->value, 'count' => $row->count]);
            
            // ✅ TELEPON
            $telpList = DB::connection('mysql')
                ->table('tcustomer')
                ->where('cus_cabang', $cabangKode)
                ->select('cus_telp as value', DB::raw('COUNT(*) as count'))
                ->whereNotNull('cus_telp')
                ->where('cus_telp', '!=', '')
                ->groupBy('cus_telp')
                ->orderBy('cus_telp')
                ->limit(500)
                ->get()
                ->map(fn($row) => ['label' => $row->value, 'value' => $row->value, 'count' => $row->count]);
            
            // Golongan
            $golonganList = DB::connection('mysql')
                ->table('tcustomer as c')
                ->join('tgolongancustomer as gc', 'c.cus_gc_kode', '=', 'gc.gc_kode')
                ->where('c.cus_cabang', $cabangKode)
                ->select('gc.gc_nama as value', DB::raw('COUNT(*) as count'))
                ->whereNotNull('gc.gc_nama')
                ->groupBy('gc.gc_nama')
                ->orderBy('gc.gc_nama')
                ->get()
                ->map(fn($row) => ['label' => $row->value, 'value' => $row->value, 'count' => $row->count]);
            
            // Kota
            $kotaList = DB::connection('mysql')
                ->table('tcustomer')
                ->where('cus_cabang', $cabangKode)
                ->select('cus_kota as value', DB::raw('COUNT(*) as count'))
                ->whereNotNull('cus_kota')
                ->where('cus_kota', '!=', '')
                ->groupBy('cus_kota')
                ->orderBy('cus_kota')
                ->get()
                ->map(fn($row) => ['label' => $row->value, 'value' => $row->value, 'count' => $row->count]);
            
            // Jenis Customer
            $jenisList = DB::connection('mysql')
                ->table('tcustomer as c')
                ->join('tjeniscustomer as jc', 'c.cus_jc_kode', '=', 'jc.jc_kode')
                ->where('c.cus_cabang', $cabangKode)
                ->select('jc.jc_nama as value', DB::raw('COUNT(*) as count'))
                ->whereNotNull('jc.jc_nama')
                ->groupBy('jc.jc_nama')
                ->orderBy('jc.jc_nama')
                ->get()
                ->map(fn($row) => ['label' => $row->value, 'value' => $row->value, 'count' => $row->count]);
            
            // Locked Status
            $lockedList = [
                ['label' => 'Open', 'value' => 'Open', 'count' => 0],
                ['label' => 'Locked', 'value' => 'Locked', 'count' => 0]
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'Kode' => $kodeList->values(),           // ✅ TAMBAHKAN
                    'Nama' => $namaList->values(),           // ✅ TAMBAHKAN
                    'Alamat' => $alamatList->values(),       // ✅ TAMBAHKAN
                    'Telp' => $telpList->values(),           // ✅ TAMBAHKAN
                    'Golongan' => $golonganList->values(),
                    'Kota' => $kotaList->values(),
                    'Jenis_Customer' => $jenisList->values(),
                    'Locked' => $lockedList,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil filter options: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
 * GET /api/v1/customer/distinct-values
 */
public function distinctValues(Request $request)
{
    try {
        $field = $request->query('field');
        $cabang = $request->header('X-Cabang-Database') ? substr($request->header('X-Cabang-Database'), -2) : '00';
        
        if (!$field) {
            return response()->json([
                'success' => false,
                'message' => 'Field parameter is required'
            ], 400);
        }
        
        // Map field ke kolom database
        $columnMap = [
            'Kode' => 'cus_kode',
            'Nama' => 'cus_nama',
            'Alamat' => 'cus_alamat',
            'Kota' => 'cus_kota',
            'Telp' => 'cus_telp',
            'Fax' => 'cus_fax',
            'Contact' => 'cus_cp',
            'Piutang' => 'cus_piutang',
            'Top' => 'cus_top',
            'NPWP' => 'cus_npwp',
        ];
        
        if (!isset($columnMap[$field])) {
            return response()->json([
                'success' => false,
                'message' => 'Field tidak diizinkan: ' . $field
            ], 400);
        }
        
        $column = $columnMap[$field];
        
        $values = DB::connection('mysql')
            ->table('tcustomer')
            ->where('cus_cabang', $cabang)
            ->select($column . ' as value', DB::raw('COUNT(*) as count'))
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->orderBy($column)
            ->limit(1000)
            ->get()
            ->map(fn($row) => [
                'value' => $row->value,
                'label' => (string) $row->value,
                'count' => (int) $row->count
            ]);
        
        return response()->json([
            'success' => true,
            'data' => $values
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil distinct values: ' . $e->getMessage()
        ], 500);
    }
}
    
    /**
     * GET /api/v1/customer/all-distinct-values
     */
    public function allDistinctValues(Request $request)
    {
        try {
            $cabang = $request->header('X-Cabang-Database') ? substr($request->header('X-Cabang-Database'), -2) : '00';
            
            // Kode
            $kodeList = DB::connection('mysql')
                ->table('tcustomer')
                ->where('cus_cabang', $cabang)
                ->select('cus_kode as value', DB::raw('COUNT(*) as count'))
                ->groupBy('cus_kode')
                ->orderBy('cus_kode')
                ->limit(1000)
                ->get()
                ->map(fn($row) => ['label' => $row->value, 'value' => $row->value, 'count' => $row->count]);
            
            // Nama
            $namaList = DB::connection('mysql')
                ->table('tcustomer')
                ->where('cus_cabang', $cabang)
                ->select('cus_nama as value', DB::raw('COUNT(*) as count'))
                ->whereNotNull('cus_nama')
                ->groupBy('cus_nama')
                ->orderBy('cus_nama')
                ->limit(1000)
                ->get()
                ->map(fn($row) => ['label' => $row->value, 'value' => $row->value, 'count' => $row->count]);
            
            // Telepon
            $telpList = DB::connection('mysql')
                ->table('tcustomer')
                ->where('cus_cabang', $cabang)
                ->select('cus_telp as value', DB::raw('COUNT(*) as count'))
                ->whereNotNull('cus_telp')
                ->where('cus_telp', '!=', '')
                ->groupBy('cus_telp')
                ->orderBy('cus_telp')
                ->limit(500)
                ->get()
                ->map(fn($row) => ['label' => $row->value, 'value' => $row->value, 'count' => $row->count]);
            
            // Contact
            $contactList = DB::connection('mysql')
                ->table('tcustomer')
                ->where('cus_cabang', $cabang)
                ->select('cus_cp as value', DB::raw('COUNT(*) as count'))
                ->whereNotNull('cus_cp')
                ->where('cus_cp', '!=', '')
                ->groupBy('cus_cp')
                ->orderBy('cus_cp')
                ->limit(500)
                ->get()
                ->map(fn($row) => ['label' => $row->value, 'value' => $row->value, 'count' => $row->count]);
            
            // TOP
            $topList = DB::connection('mysql')
                ->table('tcustomer')
                ->where('cus_cabang', $cabang)
                ->select('cus_top as value', DB::raw('COUNT(*) as count'))
                ->groupBy('cus_top')
                ->orderBy('cus_top')
                ->get()
                ->map(fn($row) => ['label' => (string) $row->value, 'value' => $row->value, 'count' => $row->count]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'Kode' => $kodeList->values(),
                    'Nama' => $namaList->values(),
                    'Telp' => $telpList->values(),
                    'Contact' => $contactList->values(),
                    'Top' => $topList->values()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil distinct values: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
 * GET /api/v1/customer/{id}/detail
 * Mengembalikan detail lengkap customer
 */
public function detail($id, Request $request)
{
    try {
        $cabangKode = $this->getCabangKode($request);
        
        $customer = DB::connection('mysql')
            ->table('tcustomer as c')
            ->select([
                'c.cus_kode as Kode',
                'c.cus_nama as Nama',
                'c.cus_alamat as Alamat',
                'c.cus_kota as Kota',
                'c.cus_telp as Telp',
                'c.cus_fax as Fax',
                'c.cus_cp as Contact',
                'c.cus_email as Email',
                'c.cus_top as TOP',
                'c.cus_piutang as Piutang',
                DB::raw("IF(c.cus_locked = 0, 'Open', 'Locked') as Status"),
                'gc.gc_nama as Golongan',
                'jc.jc_nama as Jenis_Customer',
                'c.cus_npwp as NPWP',
                'c.cus_namanpwp as Nama_NPWP',
                'c.cus_alamatnpwp as Alamat_NPWP',
                'c.cus_shipaddress as Alamat_Kirim',
                DB::raw('(SELECT byc_tanggal FROM tbayarcus_hdr WHERE byc_cus_kode = c.cus_kode ORDER BY byc_tanggal DESC LIMIT 1) as Pembayaran_Terakhir')
            ])
            ->leftJoin('tgolongancustomer as gc', 'c.cus_gc_kode', '=', 'gc.gc_kode')
            ->leftJoin('tjeniscustomer as jc', 'c.cus_jc_kode', '=', 'jc.jc_kode')
            ->where('c.cus_kode', $id)
            ->where('c.cus_cabang', $cabangKode)
            ->first();
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer tidak ditemukan'
            ], 404);
        }
        
        // Format sebagai array untuk DataTable (1 row aja)
        $detailData = [$customer];
        
        return response()->json([
            'success' => true,
            'data' => $detailData
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil detail: ' . $e->getMessage()
        ], 500);
    }
}
    
    /**
     * GET /api/v1/customer/max-kode
     */
    public function maxKode(Request $request)
    {
        try {
            $cabang = $request->header('X-Cabang-Database') ? substr($request->header('X-Cabang-Database'), -2) : '00';
            
            $maxKode = DB::connection('mysql')
                ->table('tcustomer')
                ->where('cus_cabang', $cabang)
                ->max('cus_kode');
            
            if (!$maxKode) {
                $newKode = 'CUS0001';
            } else {
                $num = intval(substr($maxKode, 3)) + 1;
                $newKode = 'CUS' . str_pad($num, 4, '0', STR_PAD_LEFT);
            }
            
            return response()->json([
                'success' => true,
                'kode' => $newKode
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * POST /api/v1/customer
     */
    public function store(Request $request)
    {
        try {
            $cabang = $request->header('X-Cabang-Database') ? substr($request->header('X-Cabang-Database'), -2) : '00';
            
            $validator = Validator::make($request->all(), [
                'Kode' => 'required|unique:tcustomer,cus_kode',
                'Nama' => 'required'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            DB::connection('mysql')->table('tcustomer')->insert([
                'cus_kode' => $request->Kode,
                'cus_nama' => $request->Nama,
                'cus_alamat' => $request->Alamat,
                'cus_kota' => $request->Kota,
                'cus_telp' => $request->Telp,
                'cus_fax' => $request->Fax,
                'cus_cp' => $request->Contact,
                'cus_email' => $request->Email,
                'cus_gc_kode' => $request->Golongan,
                'cus_jc_kode' => $request->Jenis_Customer,
                'cus_top' => $request->Top ?? 0,
                'cus_shipaddress' => $request->ShipAddress,
                'cus_npwp' => $request->NPWP,
                'cus_namanpwp' => $request->NamaNPWP,
                'cus_alamatnpwp' => $request->AlamatNPWP,
                'cus_kotanpwp' => $request->KotaNPWP,
                'cus_locked' => $request->Locked ? 1 : 0,
                'cus_cabang' => $cabang,
                'date_create' => now(),
                'user_create' => auth()->user()?->USER_KODE ?? 'admin'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Customer berhasil disimpan',
                'data' => ['Kode' => $request->Kode]
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * GET /api/v1/customer/{id}
     */
    public function show($id)
    {
        try {
            $customer = DB::connection('mysql')
                ->table('tcustomer')
                ->where('cus_kode', $id)
                ->first();
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer tidak ditemukan'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'Kode' => $customer->cus_kode,
                    'Nama' => $customer->cus_nama,
                    'Alamat' => $customer->cus_alamat,
                    'Kota' => $customer->cus_kota,
                    'Telp' => $customer->cus_telp,
                    'Fax' => $customer->cus_fax,
                    'Contact' => $customer->cus_cp,
                    'Email' => $customer->cus_email,
                    'Golongan' => $customer->cus_gc_kode,
                    'Jenis_Customer' => $customer->cus_jc_kode,
                    'Top' => (int) $customer->cus_top,
                    'ShipAddress' => $customer->cus_shipaddress,
                    'NPWP' => $customer->cus_npwp,
                    'NamaNPWP' => $customer->cus_namanpwp,
                    'AlamatNPWP' => $customer->cus_alamatnpwp,
                    'KotaNPWP' => $customer->cus_kotanpwp,
                    'Locked' => $customer->cus_locked == 1
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * PUT /api/v1/customer/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $exists = DB::connection('mysql')
                ->table('tcustomer')
                ->where('cus_kode', $id)
                ->exists();
            
            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer tidak ditemukan'
                ], 404);
            }
            
            DB::connection('mysql')
                ->table('tcustomer')
                ->where('cus_kode', $id)
                ->update([
                    'cus_nama' => $request->Nama,
                    'cus_alamat' => $request->Alamat,
                    'cus_kota' => $request->Kota,
                    'cus_telp' => $request->Telp,
                    'cus_fax' => $request->Fax,
                    'cus_cp' => $request->Contact,
                    'cus_email' => $request->Email,
                    'cus_gc_kode' => $request->Golongan,
                    'cus_jc_kode' => $request->Jenis_Customer,
                    'cus_top' => $request->Top ?? 0,
                    'cus_shipaddress' => $request->ShipAddress,
                    'cus_npwp' => $request->NPWP,
                    'cus_namanpwp' => $request->NamaNPWP,
                    'cus_alamatnpwp' => $request->AlamatNPWP,
                    'cus_kotanpwp' => $request->KotaNPWP,
                    'cus_locked' => $request->Locked ? 1 : 0,
                    'date_modified' => now(),
                    'user_modified' => auth()->user()?->USER_KODE ?? 'admin'
                ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Customer berhasil diupdate'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * DELETE /api/v1/customer/{id}
     */
    public function destroy($id)
    {
        try {
            $deleted = DB::connection('mysql')
                ->table('tcustomer')
                ->where('cus_kode', $id)
                ->delete();
            
            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer tidak ditemukan'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Customer berhasil dihapus'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus: ' . $e->getMessage()
            ], 500);
        }
    }
}