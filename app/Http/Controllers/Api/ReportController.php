<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * LAPORAN PERSEDIAAN
     */
    public function persediaan(Request $request)
    {
        try {
            $gudang = $request->query('gudang');
            $kategori = $request->query('kategori');

            $sql = "
                SELECT
                    brg_kode AS Kode,
                    brg_nama AS Nama,
                    ktg_nama AS Kategori,
                    brg_isiperCrt AS Carton,
                    brg_isiperLsn AS Box_Rtg,
                    gdg_nama AS Gudang,
                    SUM(mst_stok_in - mst_stok_out) AS Stok,
                    brg_hrgbeli AS HrgBeli,
                    SUM(mst_stok_in - mst_stok_out) * brg_hrgbeli AS Nilai,
                    brg_produsen AS Produsen,
                    brg_min_stok AS Min_Stok
                FROM tbarang
                INNER JOIN tmasterstok
                    ON mst_brg_kode = brg_kode
                INNER JOIN tgudang
                    ON gdg_kode = mst_gdg_kode
                LEFT JOIN tkategori
                    ON ktg_kode = brg_ktg_kode
                WHERE 1=1
            ";

            $params = [];

            if ($gudang) {
                $sql .= " AND mst_gdg_kode = ? ";
                $params[] = $gudang;
            }

            if ($kategori) {
                $sql .= " AND brg_ktg_kode = ? ";
                $params[] = $kategori;
            }

            $sql .= "
                GROUP BY
                    mst_gdg_kode,
                    brg_kode,
                    gdg_nama,
                    brg_nama,
                    ktg_nama,
                    brg_isiperCrt,
                    brg_isiperLsn,
                    brg_hrgbeli,
                    brg_produsen,
                    brg_min_stok
                ORDER BY brg_kode
            ";

            $data = DB::select($sql, $params);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * LAPORAN PEMBELIAN
     */
    public function pembelian(Request $request)
    {
        try {
            $startDate = $request->query('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $request->query('end_date', date('Y-m-d'));
            $supplier = $request->query('supplier');
            
            $query = DB::connection('mysql')
                ->table('tinv_hdr')
                ->select([
                    'inv_nomor as Nomor',
                    'inv_tanggal as Tanggal',
                    'sup_nama as Supplier',
                    DB::raw('MAX(inv_amount) as Total'),  // ✅ PAKAI MAX()
                    DB::raw('(SELECT SUM(ret_amount) FROM tret_hdr WHERE ret_inv_nomor = inv_nomor) as Retur'),
                    DB::raw('MAX(inv_bayar) as Bayar'),   // ✅ PAKAI MAX()
                    DB::raw("IF(MAX(inv_isbayar) = 0, 'Belum', 'Sudah') as Status_Bayar")  // ✅ PAKAI MAX()
                ])
                ->join('tbpb_hdr', 'bpb_nomor', '=', 'inv_bpb_nomor')
                ->join('tpo_hdr', 'po_nomor', '=', 'bpb_po_nomor')
                ->join('tsupplier', 'sup_kode', '=', 'po_sup_kode')
                ->join('tinv_dtl', 'invd_inv_nomor', '=', 'inv_nomor')
                ->whereBetween('inv_tanggal', [$startDate, $endDate])
                ->where('inv_nomor', 'LIKE', 'INV%')
                ->groupBy('inv_nomor', 'inv_tanggal', 'sup_nama');  // ✅ HAPUS inv_memo (tidak dipakai)
            
            if ($supplier) {
                $query->where('po_sup_kode', $supplier);
            }
            
            $data = $query->get();
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * DETAIL PEMBELIAN
     */
    public function pembelianDetail($nomor, Request $request)
    {
        try {
            $startDate = $request->query('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $request->query('end_date', date('Y-m-d'));
            
            $data = DB::connection('mysql')
                ->table('tinv_dtl')
                ->select([
                    'inv_nomor as Nomor',
                    'brg_kode as Kode',
                    'brg_nama as Nama',
                    'invd_brg_satuan as Satuan',
                    'invd_qty as Jumlah',
                    'invd_harga as Harga',
                    'invd_discpr as Disc',
                    DB::raw('(invd_harga * invd_qty * (100 - invd_discpr) / 100) as Nilai')
                ])
                ->join('tinv_hdr', 'invd_inv_nomor', '=', 'inv_nomor')
                ->join('tbarang', 'invd_brg_kode', '=', 'brg_kode')
                ->where('inv_nomor', $nomor)
                ->whereBetween('inv_tanggal', [$startDate, $endDate])
                ->orderBy('inv_nomor')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * LAPORAN PEMBELIAN PER ITEM
     */
    public function pembelianPerItem(Request $request)
    {
        try {
            $startDate = $request->query('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $request->query('end_date', date('Y-m-d'));
            
            $data = DB::connection('mysql')
                ->select("
                    SELECT 
                        MONTH(inv_tanggal) as Bulan,
                        YEAR(inv_tanggal) as Tahun,
                        inv_nomor as Nota,
                        inv_tanggal as Tanggal,
                        sup_nama as Supplier,
                        invd_brg_kode as Kode,
                        brg_nama as Nama,
                        brg_satuanpcs as Satuan,
                        ktg_nama as Kategori,
                        invd_qty as Qty,
                        ((100 - invd_discpr) / 100 * invd_harga) * invd_qty as Nilai
                    FROM tinv_dtl
                    INNER JOIN tinv_hdr ON inv_nomor = invd_inv_nomor
                    INNER JOIN tbarang ON brg_kode = invd_brg_kode
                    INNER JOIN tsupplier ON sup_kode = inv_sup_kode
                    LEFT JOIN tkategori ON ktg_kode = brg_ktg_kode
                    WHERE inv_tanggal BETWEEN ? AND ?
                    ORDER BY inv_tanggal, inv_nomor
                ", [$startDate, $endDate]);
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * LAPORAN PENJUALAN BY NOTA
     */
    public function penjualan(Request $request)
    {
        try {
            $startDate = $request->query('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $request->query('end_date', date('Y-m-d'));
            
            $data = DB::connection('mysql')
                ->select("
                    SELECT 
                        t.so_nomor as Nomor,
                        MAX(t.so_tanggal) as Tanggal,
                        MAX(DATE_FORMAT(t.date_create, '%H:%i:%s')) as Jam,
                        MAX(c.cus_nama) as Customer,
                        MAX(t.so_amount) as Total,
                        MAX(t.so_dp) as Cash,
                        MAX(t.so_card) as Card,
                        MAX(t.so_no_card) as No_Card,
                        MAX(t.so_bank_card) as Bank,
                        MAX(t.so_voucher) as Voucher,
                        MAX(t.so_no_voucher) as No_Voucher,
                        MAX(t.so_piutang) as Piutang,
                        (SELECT SUM(bycd_bayar) FROM tbayarcus_dtl WHERE bycd_fp_nomor = t.so_nomor) as Bayar_Piutang,
                        MAX(t.so_disc_faktur) as Potongan,
                        MAX(t.so_ongkir) as Ongkir,
                        MAX(t.isposting) as IsPosting,
                        MAX(t.noposting) as NoPosting
                    FROM tso_hdr as t
                    INNER JOIN tcustomer as c ON TRIM(c.cus_kode) = TRIM(t.so_cus_kode)
                    WHERE t.so_tanggal BETWEEN ? AND ?
                    GROUP BY t.so_nomor
                    ORDER BY Tanggal DESC, t.so_nomor
                ", [$startDate, $endDate]);
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DETAIL PENJUALAN
     */
    public function penjualanDetail($nomor, Request $request)
    {
        try {
            $startDate = $request->query('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $request->query('end_date', date('Y-m-d'));
            
            $data = DB::connection('mysql')
                ->select("
                    SELECT 
                        so_nomor as Nomor,
                        brg_kode as Kode,
                        brg_nama as Nama,
                        sod_brg_satuan as Satuan,
                        sod_qty as Jumlah,
                        sod_harga as Harga,
                        sod_discpr as Disc,
                        sod_discrp as DiscRp,
                        (sod_harga * sod_qty * (100 - sod_discpr) / 100) as Nilai
                    FROM tso_dtl
                    INNER JOIN tso_hdr ON sod_so_nomor = so_nomor
                    INNER JOIN tbarang ON sod_brg_kode = brg_kode
                    WHERE so_nomor = ?
                    AND so_tanggal BETWEEN ? AND ?
                    ORDER BY sod_nourut
                ", [$nomor, $startDate, $endDate]);
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * LAPORAN PENJUALAN PER ITEM
     */
    public function penjualanPerItem(Request $request)
    {
        try {
            $startDate = $request->query('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $request->query('end_date', date('Y-m-d'));
            $isAdmin = $request->query('is_admin', false);
            
            $selectFields = "
                MONTH(t.so_tanggal) as Bulan,
                YEAR(t.so_tanggal) as Tahun,
                t.so_nomor as Nota,
                t.so_tanggal as Tanggal,
                MAX(c.cus_nama) as Customer,
                d.sod_brg_kode as Kode,
                MAX(b.brg_nama) as Nama,
                MAX(b.brg_satuanpcs) as Satuan,
                MAX(kt.ktg_nama) as Kategori,
                SUM(d.sod_qty) as Qty,
                SUM(((100 - d.sod_discpr) / 100 * d.sod_harga - d.sod_discrp) * d.sod_qty) as Nilai,
                SUM(((d.sod_discpr * d.sod_harga / 100) + d.sod_discrp) * d.sod_qty) as Disc";
            
            // HPP & Margin hanya untuk ADMIN
            if ($isAdmin) {
                $selectFields .= ",
                    SUM(d.sod_qty * d.sod_brg_avgcost) as Hpp,
                    SUM(((100 - d.sod_discpr) / 100 * d.sod_harga - d.sod_discrp) * d.sod_qty) - SUM(d.sod_qty * d.sod_brg_avgcost) as Margin";
            }
            
            $data = DB::connection('mysql')
                ->select("
                    SELECT {$selectFields}
                    FROM tso_dtl as d
                    INNER JOIN tso_hdr as t ON t.so_nomor = d.sod_so_nomor
                    INNER JOIN tbarang as b ON b.brg_kode = d.sod_brg_kode
                    INNER JOIN tcustomer as c ON c.cus_kode = t.so_cus_kode
                    LEFT JOIN tkategori as kt ON kt.ktg_kode = d.sod_ktg_kode
                    WHERE t.so_tanggal BETWEEN ? AND ?
                    GROUP BY t.so_nomor, t.so_tanggal, d.sod_brg_kode
                    ORDER BY t.so_tanggal DESC, t.so_nomor
                ", [$startDate, $endDate]);
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'is_admin' => $isAdmin
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * LAPORAN KARTU STOCK
     */
    public function kartuStock(Request $request)
    {
        try {
            $startDate = $request->query('start_date', date('Y-m-d', strtotime('-30 days')));
            $endDate = $request->query('end_date', date('Y-m-d'));
            $gudang = $request->query('gudang', '');
            
            $gudangLike = $gudang ? $gudang . '%' : '%';
            
            $data = DB::connection('mysql')
                ->select("
                    SELECT 
                        brg_kode as Kode,
                        brg_nama as Nama,
                        ktg_nama as Kategori,
                        IFNULL(saldoawal, 0) as _Awal,
                        IFNULL(saldoawal, 0) * brg_hrgbeli as Rp_Awal,
                        IFNULL(Penjualan, 0) as Penjualan,
                        IFNULL(Mutasi_in, 0) as Mutasi_in,
                        IFNULL(Mutasi_out, 0) as Mutasi_out,
                        IFNULL(Penerimaan, 0) as Penerimaan,
                        IFNULL(Koreksi, 0) as Koreksi,
                        IFNULL(Pemusnahan, 0) as Pemusnahan,
                        IFNULL(Repacking, 0) as Repacking,
                        IFNULL(Retur_Beli, 0) as Retur_Beli,
                        IFNULL(akhir, 0) as _Akhir,
                        IFNULL(akhir, 0) * brg_hrgbeli as Rp_Akhir
                    FROM (
                        SELECT 
                            a.brg_kode, a.brg_nama, kt.ktg_nama, a.brg_hrgbeli,
                            (SELECT SUM(mst_stok_in - mst_stok_out) FROM tmasterstok 
                            WHERE mst_brg_kode = a.brg_kode AND mst_tanggal < ? 
                            AND mst_gdg_kode LIKE ?) as saldoawal,
                            (SELECT IFNULL(SUM(mst_stok_in - mst_stok_out), 0) FROM tmasterstok 
                            WHERE mst_noreferensi LIKE '%SAL%' AND mst_brg_kode = a.brg_kode 
                            AND mst_tanggal BETWEEN ? AND ?) as Penjualan,
                            (SELECT IFNULL(SUM(mst_stok_in - mst_stok_out), 0) FROM tmasterstok 
                            WHERE mst_noreferensi LIKE '%MTCI%' AND mst_brg_kode = a.brg_kode 
                            AND mst_tanggal BETWEEN ? AND ?) as Mutasi_in,
                            (SELECT IFNULL(SUM(mst_stok_in - mst_stok_out), 0) FROM tmasterstok 
                            WHERE mst_noreferensi LIKE '%MTC.%' AND mst_brg_kode = a.brg_kode 
                            AND mst_tanggal BETWEEN ? AND ?) as Mutasi_out,
                            (SELECT IFNULL(SUM(mst_stok_in - mst_stok_out), 0) FROM tmasterstok 
                            WHERE mst_noreferensi LIKE '%RI%' AND mst_brg_kode = a.brg_kode 
                            AND mst_tanggal BETWEEN ? AND ?) as Penerimaan,
                            (SELECT IFNULL(SUM(mst_stok_in - mst_stok_out), 0) FROM tmasterstok 
                            WHERE mst_noreferensi LIKE '%KOR%' AND mst_brg_kode = a.brg_kode 
                            AND mst_tanggal BETWEEN ? AND ?) as Koreksi,
                            (SELECT IFNULL(SUM(mst_stok_in - mst_stok_out), 0) FROM tmasterstok 
                            WHERE mst_noreferensi LIKE '%MUS%' AND mst_brg_kode = a.brg_kode 
                            AND mst_tanggal BETWEEN ? AND ?) as Pemusnahan,
                            (SELECT IFNULL(SUM(mst_stok_in - mst_stok_out), 0) FROM tmasterstok 
                            WHERE mst_noreferensi LIKE '%REP%' AND mst_brg_kode = a.brg_kode 
                            AND mst_tanggal BETWEEN ? AND ?) as Repacking,
                            (SELECT IFNULL(SUM(mst_stok_in - mst_stok_out), 0) FROM tmasterstok 
                            WHERE mst_noreferensi LIKE '%RET%' AND mst_brg_kode = a.brg_kode 
                            AND mst_tanggal BETWEEN ? AND ?) as Retur_Beli,
                            (SELECT SUM(mst_stok_in - mst_stok_out) FROM tmasterstok 
                            WHERE mst_brg_kode = a.brg_kode AND mst_tanggal <= ? 
                            AND mst_gdg_kode LIKE ?) as akhir
                        FROM tbarang a
                        INNER JOIN tkategori kt ON kt.ktg_kode = a.brg_ktg_kode
                    ) final
                    ORDER BY brg_kode
                ", [
                    $startDate, $gudangLike,
                    $startDate, $endDate,
                    $startDate, $endDate,
                    $startDate, $endDate,
                    $startDate, $endDate,
                    $startDate, $endDate,
                    $startDate, $endDate,
                    $startDate, $endDate,
                    $startDate, $endDate,
                    $endDate, $gudangLike
                ]);
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
 * LAPORAN MEMBER
 */
public function member(Request $request)
{
    try {
        $search = $request->query('search');
        $sortBy = $request->query('sort_by', 'Kode');
        $sortOrder = $request->query('sort_order', 'asc');
        $perPage = $request->query('per_page', 25);
        
        $query = DB::connection('mysql')
            ->table('tcustomer')
            ->select([
                'cus_kode as Kode',
                'cus_nama as Nama',
                'cus_alamat as Alamat',
                'cus_kota as Kota',
                'cus_telp as Telp',
                'cus_fax as Fax',
                'date_create as Tgl_Daftar',
                'cus_poin as Poin'
            ]);
        
        // Global search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('cus_kode', 'LIKE', "%{$search}%")
                  ->orWhere('cus_nama', 'LIKE', "%{$search}%")
                  ->orWhere('cus_alamat', 'LIKE', "%{$search}%")
                  ->orWhere('cus_kota', 'LIKE', "%{$search}%")
                  ->orWhere('cus_telp', 'LIKE', "%{$search}%");
            });
        }
        
        // Sorting
        $sortColumnMap = [
            'Kode' => 'cus_kode',
            'Nama' => 'cus_nama',
            'Alamat' => 'cus_alamat',
            'Kota' => 'cus_kota',
            'Telp' => 'cus_telp',
            'Fax' => 'cus_fax',
            'Tgl_Daftar' => 'date_create',
            'Poin' => 'cus_poin',
        ];
        
        $sortColumn = $sortColumnMap[$sortBy] ?? 'cus_kode';
        $query->orderBy($sortColumn, $sortOrder);
        
        $members = $query->paginate($perPage);
        
        // Total poin semua member
        $totalPoin = DB::connection('mysql')
            ->table('tcustomer')
            ->sum('cus_poin');
        
        return response()->json([
            'success' => true,
            'data' => $members->items(),
            'total_poin' => (int) $totalPoin,
            'pagination' => [
                'current_page' => $members->currentPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
                'last_page' => $members->lastPage()
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Gagal mengambil data: ' . $e->getMessage()
        ], 500);
    }
}
}