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
            
            $query = DB::connection('mysql')
                ->table('tbarang')
                ->select([
                    'brg_kode as Kode',
                    'brg_nama as Nama',
                    'ktg_nama as Kategori',
                    'brg_isiperCrt as Carton',
                    'brg_isiperLsn as Box_Rtg',
                    'gdg_nama as Gudang',
                    DB::raw('SUM(mst_stok_in - mst_stok_out) as Stok'),
                    'brg_hrgbeli as HrgBeli',
                    DB::raw('SUM(mst_stok_in - mst_stok_out) * MAX(brg_hrgbeli) as Nilai'),
                    'brg_produsen as Produsen',
                    'brg_min_stok as Min_Stok'
                ])
                ->join('tmasterstok', 'mst_brg_kode', '=', 'brg_kode')
                ->join('tgudang', 'gdg_kode', '=', 'mst_gdg_kode')
                ->leftJoin('tkategori', 'ktg_kode', '=', 'brg_ktg_kode')
                ->groupBy(
                    'mst_gdg_kode', 
                    'brg_kode', 
                    'gdg_nama',
                    'brg_nama',        // ✅ Tambahkan
                    'ktg_nama',        // ✅ Tambahkan
                    'brg_isiperCrt',   // ✅ Tambahkan
                    'brg_isiperLsn',   // ✅ Tambahkan
                    'brg_hrgbeli',     // ✅ Tambahkan
                    'brg_produsen',    // ✅ Tambahkan
                    'brg_min_stok'     // ✅ Tambahkan
                )
                ->orderBy('brg_kode');
            
            if ($gudang) {
                $query->where('mst_gdg_kode', $gudang);
            }
            if ($kategori) {
                $query->where('brg_ktg_kode', $kategori);
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
}