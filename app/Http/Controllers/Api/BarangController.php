<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\DatabaseManager;
use Illuminate\Support\Facades\Validator;

class BarangController extends Controller
{
    /**
     * GET /api/v1/barang
     */
    public function index(Request $request)
    {
        try {
            $search = $request->query('search');
            $sortBy = $request->query('sort_by', 'Nama');
            $sortOrder = $request->query('sort_order', 'asc');
            $filters = $request->query('filters');
            $perPage = $request->query('per_page', 15);
            
            $filtersArray = $filters ? json_decode($filters, true) : [];
            
            $query = DB::connection('mysql')
                ->table('tbarang as b')
                ->select([
                    'b.brg_kode as Kode',
                    'b.brg_nama as Nama',
                    'b.brg_satuan as Satuan',
                    'kt.ktg_nama as Kategori',
                    'g.gr_nama as Tipe',
                    'b.brg_hrgjual as HargaJual',
                    'b.brg_stok as Stok',
                    'b.brg_min_stok as Min',
                    'b.brg_merk as Merk',
                    'b.brg_isproductfocus as Product_Focus',
                ])
                ->leftJoin('tkategori as kt', 'b.brg_ktg_kode', '=', 'kt.ktg_kode')
                ->leftJoin('tgroup as g', 'b.brg_gr_kode', '=', 'g.gr_kode');
            
            // Global search
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('b.brg_nama', 'LIKE', "%{$search}%")
                      ->orWhere('b.brg_kode', 'LIKE', "%{$search}%")
                      ->orWhere('b.brg_satuan', 'LIKE', "%{$search}%")
                      ->orWhere('b.brg_merk', 'LIKE', "%{$search}%");
                });
            }
            
            // Advanced filters
            $columnMap = [
                'Kode' => 'b.brg_kode',
                'Nama' => 'b.brg_nama',
                'Satuan' => 'b.brg_satuan',
                'Kategori' => 'kt.ktg_nama',
                'Tipe' => 'g.gr_nama',
                'HargaJual' => 'b.brg_hrgjual',
                'Stok' => 'b.brg_stok',
                'Merk' => 'b.brg_merk',
                'Product_Focus' => 'b.brg_isproductfocus',
            ];
            
            foreach ($filtersArray as $field => $value) {
                if ($value && isset($columnMap[$field])) {
                    $column = $columnMap[$field];
                    if (is_array($value)) {
                        $query->whereIn($column, $value);
                    } else {
                        $query->where($column, 'LIKE', "%{$value}%");
                    }
                }
            }
            
            // Sorting
            $sortColumnMap = [
                'Kode' => 'b.brg_kode',
                'Nama' => 'b.brg_nama',
                'Satuan' => 'b.brg_satuan',
                'Kategori' => 'kt.ktg_nama',
                'Tipe' => 'g.gr_nama',
                'HargaJual' => 'b.brg_hrgjual',
                'Stok' => 'b.brg_stok',
                'Merk' => 'b.brg_merk',
                'Product_Focus' => 'b.brg_isproductfocus',
            ];
            
            $sortColumn = $sortColumnMap[$sortBy] ?? 'b.brg_nama';
            $query->orderBy($sortColumn, $sortOrder);
            
            $barangs = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $barangs->items(),
                'pagination' => [
                    'current_page' => $barangs->currentPage(),
                    'per_page' => $barangs->perPage(),
                    'total' => $barangs->total(),
                    'last_page' => $barangs->lastPage()
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
     * GET /api/v1/barang/max-kode
     * Generate kode barang baru
     */
    public function maxKode()
    {
        try {
            $maxKode = DB::connection('mysql')
                ->table('tbarang')
                ->max('brg_kode');
            
            $newKode = $maxKode ? (intval($maxKode) + 1) : 10001;
            
            return response()->json([
                'success' => true,
                'kode' => (string) $newKode
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/v1/barang
     * Insert barang baru (sesuai Delphi .pas)
     */
    public function store(Request $request)
    {
        try {
            DB::connection('mysql')->beginTransaction();
            
            $validator = Validator::make($request->all(), [
                'Kode' => 'required|unique:tbarang,brg_kode',
                'Nama' => 'required',
                'Satuan' => 'required',
                'Tipe' => 'required',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Insert barang
            DB::connection('mysql')->table('tbarang')->insert([
                'brg_kode' => $request->Kode,
                'brg_nama' => $request->Nama,
                'brg_satuan' => $request->Satuan,
                'brg_gr_kode' => $request->Tipe,
                'brg_ktg_kode' => $request->Kategori,
                'brg_merk' => $request->Merk,
                'brg_gdg_default' => $request->Gudang,
                'brg_sup_kode' => $request->Pemasok,
                'brg_isaktif' => $request->IsAktif ?? 1,
                'brg_isstok' => $request->IsStok ?? 1,
                'brg_isexpired' => $request->IsExpired ?? 0,
                'brg_isproductfocus' => $request->Product_Focus ?? 0,
                'brg_hrgbeli' => $request->HargaBeli ?? 0,
                'brg_hrgjual' => $request->HargaJual ?? 0,
                'brg_harga_min' => $request->HET ?? 0,
                'brg_min_stok' => $request->MinStok ?? 0,
                'brg_max_stok' => $request->MaxStok ?? 0,
                'brg_disc_sales' => $request->DiscSalesman ?? 0,
                'date_create' => now(),
                'user_create' => auth()->user()?->USER_KODE ?? 'admin'
            ]);
            
            // Insert harga khusus per jenis customer
            if ($request->HargaKhusus && is_array($request->HargaKhusus)) {
                foreach ($request->HargaKhusus as $item) {
                    if ($item['hargajual'] > 0) {
                        DB::connection('mysql')->table('thargajualjenis')->insert([
                            'hjj_brg_kode' => $request->Kode,
                            'hjj_jc_kode' => $item['kode'],
                            'hjj_hargajual' => $item['hargajual']
                        ]);
                    }
                }
            }
            
            DB::connection('mysql')->commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil disimpan',
                'data' => ['Kode' => $request->Kode]
            ], 201);
            
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/v1/barang/{id}
     */
    public function show($id)
    {
        try {
            $barang = DB::connection('mysql')
                ->table('tbarang')
                ->where('brg_kode', $id)
                ->first();
            
            if (!$barang) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barang tidak ditemukan'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'Kode' => $barang->brg_kode,
                    'Nama' => $barang->brg_nama,
                    'Tipe' => $barang->brg_gr_kode,
                    'Satuan' => $barang->brg_satuan,
                    'Kategori' => $barang->brg_ktg_kode,
                    'Merk' => $barang->brg_merk,
                    'Gudang' => $barang->brg_gdg_default,
                    'Pemasok' => $barang->brg_sup_kode,
                    'IsAktif' => $barang->brg_isaktif,
                    'IsStok' => $barang->brg_isstok,
                    'IsExpired' => $barang->brg_isexpired,
                    'Product_Focus' => $barang->brg_isproductfocus,
                    'HargaBeli' => (float) $barang->brg_hrgbeli,
                    'HargaJual' => (float) $barang->brg_hrgjual,
                    'HET' => (float) $barang->brg_harga_min,
                    'MinStok' => (int) $barang->brg_min_stok,
                    'MaxStok' => (int) $barang->brg_max_stok,
                    'DiscSalesman' => (float) $barang->brg_disc_sales,
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
     * PUT /api/v1/barang/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            DB::connection('mysql')->beginTransaction();
            
            $exists = DB::connection('mysql')
                ->table('tbarang')
                ->where('brg_kode', $id)
                ->exists();
            
            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Barang tidak ditemukan'
                ], 404);
            }
            
            // Update barang
            DB::connection('mysql')
                ->table('tbarang')
                ->where('brg_kode', $id)
                ->update([
                    'brg_nama' => $request->Nama,
                    'brg_satuan' => $request->Satuan,
                    'brg_gr_kode' => $request->Tipe,
                    'brg_ktg_kode' => $request->Kategori,
                    'brg_merk' => $request->Merk,
                    'brg_gdg_default' => $request->Gudang,
                    'brg_sup_kode' => $request->Pemasok,
                    'brg_isaktif' => $request->IsAktif ?? 1,
                    'brg_isstok' => $request->IsStok ?? 1,
                    'brg_isexpired' => $request->IsExpired ?? 0,
                    'brg_isproductfocus' => $request->Product_Focus ?? 0,
                    'brg_hrgbeli' => $request->HargaBeli ?? 0,
                    'brg_hrgjual' => $request->HargaJual ?? 0,
                    'brg_harga_min' => $request->HET ?? 0,
                    'brg_min_stok' => $request->MinStok ?? 0,
                    'brg_max_stok' => $request->MaxStok ?? 0,
                    'brg_disc_sales' => $request->DiscSalesman ?? 0,
                    'date_modified' => now(),
                    'user_modified' => auth()->user()?->USER_KODE ?? 'admin'
                ]);
            
            // Delete old harga khusus
            DB::connection('mysql')
                ->table('thargajualjenis')
                ->where('hjj_brg_kode', $id)
                ->delete();
            
            // Insert new harga khusus
            if ($request->HargaKhusus && is_array($request->HargaKhusus)) {
                foreach ($request->HargaKhusus as $item) {
                    if ($item['hargajual'] > 0) {
                        DB::connection('mysql')->table('thargajualjenis')->insert([
                            'hjj_brg_kode' => $id,
                            'hjj_jc_kode' => $item['kode'],
                            'hjj_hargajual' => $item['hargajual']
                        ]);
                    }
                }
            }
            
            DB::connection('mysql')->commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil diupdate'
            ]);
            
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/barang/{id}
     */
    public function destroy($id)
    {
        try {
            DB::connection('mysql')->beginTransaction();
            
            // Delete harga khusus
            DB::connection('mysql')
                ->table('thargajualjenis')
                ->where('hjj_brg_kode', $id)
                ->delete();
            
            // Delete barang
            $deleted = DB::connection('mysql')
                ->table('tbarang')
                ->where('brg_kode', $id)
                ->delete();
            
            if (!$deleted) {
                DB::connection('mysql')->rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Barang tidak ditemukan'
                ], 404);
            }
            
            DB::connection('mysql')->commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil dihapus'
            ]);
            
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus: ' . $e->getMessage()
            ], 500);
        }
    }

public function filterOptions()
{
    try {
        $connection = 'mysql';

        // Satuan - dengan count
        $satuanList = DB::connection($connection)
            ->table('tbarang')
            ->select('brg_satuan as value', DB::raw('COUNT(*) as count'))
            ->whereNotNull('brg_satuan')
            ->where('brg_satuan', '!=', '')
            ->groupBy('brg_satuan')
            ->orderBy('brg_satuan')
            ->get()
            ->map(fn($row) => [
                'label' => $row->value,
                'value' => $row->value,
                'count' => $row->count
            ]);

        // Kategori - dengan count
        $kategoriList = DB::connection($connection)
            ->table('tbarang as b')
            ->leftJoin('tkategori as kt', 'b.brg_ktg_kode', '=', 'kt.ktg_kode')
            ->select('kt.ktg_nama as value', DB::raw('COUNT(DISTINCT b.brg_kode) as count'))
            ->whereNotNull('kt.ktg_nama')
            ->where('kt.ktg_nama', '!=', '')
            ->groupBy('kt.ktg_nama')
            ->orderBy('kt.ktg_nama')
            ->get()
            ->map(fn($row) => [
                'label' => $row->value,
                'value' => $row->value,
                'count' => $row->count
            ]);

        // Tipe - dengan count
        $tipeList = DB::connection($connection)
            ->table('tbarang as b')
            ->leftJoin('tgroup as g', 'b.brg_gr_kode', '=', 'g.gr_kode')
            ->select('g.gr_nama as value', DB::raw('COUNT(DISTINCT b.brg_kode) as count'))
            ->whereNotNull('g.gr_nama')
            ->where('g.gr_nama', '!=', '')
            ->groupBy('g.gr_nama')
            ->orderBy('g.gr_nama')
            ->get()
            ->map(fn($row) => [
                'label' => $row->value,
                'value' => $row->value,
                'count' => $row->count
            ]);

        // Merk - dengan count
        $merkList = DB::connection($connection)
            ->table('tbarang')
            ->select('brg_merk as value', DB::raw('COUNT(*) as count'))
            ->whereNotNull('brg_merk')
            ->where('brg_merk', '!=', '')
            ->groupBy('brg_merk')
            ->orderBy('brg_merk')
            ->get()
            ->map(fn($row) => [
                'label' => $row->value,
                'value' => $row->value,
                'count' => $row->count
            ]);

        // Product Focus - static
        $productFocusList = [
            ['label' => 'Ya', 'value' => 1, 'count' => 0],
            ['label' => 'Tidak', 'value' => 0, 'count' => 0]
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'Satuan' => $satuanList->values(),
                'Kategori' => $kategoriList->values(),
                'Tipe' => $tipeList->values(),
                'Merk' => $merkList->values(),
                'Product_Focus' => $productFocusList,
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil filter options: ' . $e->getMessage()
        ], 500);
    }
}

// GET /api/v1/barang/distinct/{field}
 public function distinctValues(Request $request)
    {
        try {
            $field = $request->query('field');
            
            if (!$field) {
                return response()->json([
                    'success' => false,
                    'message' => 'Field parameter is required'
                ], 400);
            }
            
            // Map field frontend ke kolom database
            $columnMap = [
                'Kode' => 'b.brg_kode',
                'Nama' => 'b.brg_nama',
                'Satuan' => 'b.brg_satuan',
                'Kategori' => 'kt.ktg_nama',
                'Tipe' => 'g.gr_nama',
                'HargaJual' => 'b.brg_hrgjual',
                'Stok' => 'b.brg_stok',
                'Min' => 'b.brg_min_stok',
                'Merk' => 'b.brg_merk',
                'Product_Focus' => 'b.brg_isproductfocus',
            ];
            
            if (!isset($columnMap[$field])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Field tidak diizinkan: ' . $field
                ], 400);
            }
            
            $column = $columnMap[$field];
            
            // Build query dengan join yang diperlukan
            $query = DB::connection('mysql')
                ->table('tbarang as b')
                ->select(DB::raw($column . ' as value'), DB::raw('COUNT(DISTINCT b.brg_kode) as count'))
                ->whereNotNull($column)
                ->where($column, '!=', '');
            
            // Add joins jika perlu
            if ($field === 'Kategori') {
                $query->leftJoin('tkategori as kt', 'b.brg_ktg_kode', '=', 'kt.ktg_kode');
            } elseif ($field === 'Tipe') {
                $query->leftJoin('tgroup as g', 'b.brg_gr_kode', '=', 'g.gr_kode');
            }
            
            $values = $query->groupBy($column)
                ->orderBy($column)
                ->get()
                ->map(function($row) use ($field) {
                    $value = $row->value;
                    
                    // Format label sesuai tipe field
                    $label = (string) $value;
                    
                    if ($field === 'HargaJual') {
                        $label = 'Rp ' . number_format((float) $value, 0, ',', '.');
                    } elseif ($field === 'Product_Focus') {
                        $label = $value == 1 ? 'Ya' : 'Tidak';
                    }
                    
                    return [
                        'value' => $value,
                        'label' => $label,
                        'count' => (int) $row->count
                    ];
                });
            
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
 * GET /api/v1/barang/all-distinct-values
 * Returns distinct values for ALL filterable columns at once
 */
public function allDistinctValues()
{
    try {
        $connection = 'mysql';
        
        // Kode
        $kodeList = DB::connection($connection)
            ->table('tbarang')
            ->select('brg_kode as value', DB::raw('COUNT(*) as count'))
            ->whereNotNull('brg_kode')
            ->where('brg_kode', '!=', '')
            ->groupBy('brg_kode')
            ->orderBy('brg_kode')
            ->limit(1000) // Batasi untuk performance
            ->get()
            ->map(fn($row) => [
                'value' => $row->value,
                'label' => $row->value,
                'count' => $row->count
            ]);
            
        // Nama
        $namaList = DB::connection($connection)
            ->table('tbarang')
            ->select('brg_nama as value', DB::raw('COUNT(*) as count'))
            ->whereNotNull('brg_nama')
            ->where('brg_nama', '!=', '')
            ->groupBy('brg_nama')
            ->orderBy('brg_nama')
            ->limit(1000)
            ->get()
            ->map(fn($row) => [
                'value' => $row->value,
                'label' => $row->value,
                'count' => $row->count
            ]);
            
        // HargaJual
        $hargaList = DB::connection($connection)
            ->table('tbarang')
            ->select('brg_hrgjual as value', DB::raw('COUNT(*) as count'))
            ->whereNotNull('brg_hrgjual')
            ->groupBy('brg_hrgjual')
            ->orderBy('brg_hrgjual')
            ->limit(500)
            ->get()
            ->map(fn($row) => [
                'value' => $row->value,
                'label' => 'Rp ' . number_format((float) $row->value, 0, ',', '.'),
                'count' => $row->count
            ]);
            
        // Stok
        $stokList = DB::connection($connection)
            ->table('tbarang')
            ->select('brg_stok as value', DB::raw('COUNT(*) as count'))
            ->whereNotNull('brg_stok')
            ->groupBy('brg_stok')
            ->orderBy('brg_stok')
            ->limit(500)
            ->get()
            ->map(fn($row) => [
                'value' => $row->value,
                'label' => (string) $row->value,
                'count' => $row->count
            ]);
            
        return response()->json([
            'success' => true,
            'data' => [
                'Kode' => $kodeList->values(),
                'Nama' => $namaList->values(),
                'HargaJual' => $hargaList->values(),
                'Stok' => $stokList->values(),
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
 * GET /api/v1/barang/{kode}/detail-stok
 * Returns detail stok per gudang untuk barang tertentu
 */
public function detailStok($kode)
{
    try {
        $details = DB::connection('mysql')
            ->table('tmasterstok as ms')
            ->select([
                'ms.mst_brg_kode as Kode',
                'ms.mst_gdg_kode as KD_Gudang',
                'g.gdg_nama as Gudang',
                'ms.mst_expired_date as Expired',
                DB::raw('SUM(ms.mst_stok_in - ms.mst_stok_out) as Stok')
            ])
            ->join('tgudang as g', 'g.gdg_kode', '=', 'ms.mst_gdg_kode')
            ->where('ms.mst_brg_kode', $kode)
            ->groupBy('ms.mst_brg_kode', 'ms.mst_gdg_kode', 'g.gdg_nama', 'ms.mst_expired_date')
            ->orderBy('g.gdg_nama')
            ->orderBy('ms.mst_expired_date')
            ->get();
            
        // Format expired date
        $details = $details->map(function($item) {
            $item->Expired = $item->Expired ? date('d/m/Y', strtotime($item->Expired)) : '-';
            $item->Stok = (float) $item->Stok;
            return $item;
        });
        
        return response()->json([
            'success' => true,
            'data' => $details
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil detail stok: ' . $e->getMessage()
        ], 500);
    }
}
}