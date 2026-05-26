<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    // Simpan item ke temp_jual
    public function addToCart(Request $request)
    {
        try {
            $userId = (int)($request->user_id ?? 1);
            $kode = $request->a1;
            
            // 🔥 Hapus dulu jika sudah ada (biar update)
            DB::connection('mysql')->table('temp_jual')
                ->where('id_user', $userId)
                ->where('a1', $kode)
                ->delete();
            
            // 🔥 Insert dengan mapping benar
            DB::connection('mysql')->table('temp_jual')->insert([
                'a1' => $request->a1 ?? '',
                'a2' => $request->a2 ?? '',
                'a3' => $request->a3 ?? '1',
                'a4' => $request->a4 ?? '1',
                'a5' => $request->a5 ?? '',
                'a6' => (int)($request->a6 ?? 1),       // Qty
                'a7' => (float)($request->a7 ?? 0),     // Harga
                'a8' => (float)($request->a8 ?? 0),     // Disc %
                'a9' => (float)($request->a9 ?? 0),     // Disc Rp
                'a10' => (float)($request->a10 ?? 0),   // PPN
                'a11' => (float)($request->a11 ?? 0),   // Total
                'a12' => $request->a12 ?? '2',          // Flag PPN
                'a13' => $request->a13 ?? '',
                'a14' => $request->a14 ?? '1',
                'a15' => (float)($request->a15 ?? 0),
                'a16' => (float)($request->a16 ?? 0),
                'a17' => $request->a17 ?? '1',
                'num_item' => (int)($request->num_item ?? 1),
                'id_user' => $userId
            ]);
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    // Get cart items
    public function getCart(Request $request)
    {
        $userId = $request->user_id ?? 1;
        $data = DB::connection('mysql')->table('temp_jual')
            ->where('id_user', $userId)
            ->orderBy('num_item')
            ->get();
        return response()->json(['success' => true, 'data' => $data]);
    }
    
    // Update cart item
    public function updateCart(Request $request)
    {
        try {
            $userId = (int)($request->user_id ?? 1);
            $kode = $request->kode;
            
            DB::connection('mysql')->table('temp_jual')
                ->where('id_user', $userId)
                ->where('a1', $kode)
                ->update([
                    'a6' => (int)($request->qty ?? 1),       // Qty
                    'a7' => (float)($request->a7 ?? 0),      // Harga
                    'a8' => (float)($request->disc_pr ?? 0),  // Disc %
                    'a9' => (float)($request->disc_rp ?? 0),  // Disc Rp
                    'a10' => (float)($request->a10 ?? 0),     // PPN
                    'a11' => (float)($request->a11 ?? 0),     // Total
                ]);
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    // Delete cart item
    public function removeFromCart(Request $request)
    {
        try {
            $userId = $request->user_id ?? 1;
            $kode = $request->kode;
            
            DB::connection('mysql')->table('temp_jual')
                ->where('id_user', $userId)
                ->where('a1', $kode)
                ->delete();
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    // Clear cart
    public function clearCart(Request $request)
    {
        try {
            $userId = $request->user_id ?? 1;
            DB::connection('mysql')->table('temp_jual')->where('id_user', $userId)->delete();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    // Pending transaction (Ctrl+P)
    public function pendingTransaction(Request $request)
    {
        try {
            DB::connection('mysql')->beginTransaction();
            
            $userId = $request->user_id ?? 1;
            $customer = $request->customer ?? '';
            
            // Check if cart has items
            $cartItems = DB::connection('mysql')->table('temp_jual')
                ->where('id_user', $userId)->count();
            
            if ($cartItems == 0) {
                return response()->json(['success' => false, 'message' => 'Keranjang kosong']);
            }
            
            // Get max ID
            $maxId = DB::connection('mysql')->table('jual_pending_hdr')->max('id') ?? 0;
            $newId = $maxId + 1;
            
            // Insert header
            DB::connection('mysql')->table('jual_pending_hdr')->insert([
                'id' => $newId,
                'tgl' => now(),
                'cus' => $customer
            ]);
            
            // Move cart to pending detail
            $items = DB::connection('mysql')->table('temp_jual')->where('id_user', $userId)->get();
            foreach ($items as $item) {
                DB::connection('mysql')->table('jual_pending_det')->insert([
                    'header_id' => $newId,
                    'a1' => $item->a1, 'a2' => $item->a2, 'a3' => $item->a3, 'a4' => $item->a4,
                    'a5' => $item->a5, 'a6' => $item->a6, 'a7' => $item->a7, 'a8' => $item->a8,
                    'a9' => $item->a9, 'a10' => $item->a10, 'a11' => $item->a11,
                    'a12' => $item->a12, 'a13' => $item->a13, 'a14' => $item->a14,
                    'a15' => $item->a15, 'a16' => $item->a16, 'a17' => $item->a17,
                    'num_item' => $item->num_item, 'id_user' => $userId
                ]);
            }
            
            // Clear cart
            DB::connection('mysql')->table('temp_jual')->where('id_user', $userId)->delete();
            
            DB::connection('mysql')->commit();
            return response()->json(['success' => true, 'pending_id' => $newId]);
            
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    // Get pending list (Ctrl+S)
    public function getPendingList(Request $request)
    {
        $userId = $request->user_id ?? 1;
        $data = DB::connection('mysql')->select("
            SELECT a.id as no, a.tgl as tanggal, SUM(b.a11) as total, a.cus
            FROM jual_pending_hdr a
            INNER JOIN jual_pending_det b ON a.id = b.header_id
            WHERE b.id_user = ?
            GROUP BY a.id, a.tgl, a.cus
            ORDER BY a.id DESC
        ", [$userId]);
        
        return response()->json(['success' => true, 'data' => $data]);
    }
    
    // Load pending to cart (Ctrl+S select)
    public function loadPending(Request $request)
    {
        try {
            DB::connection('mysql')->beginTransaction();
            
            $userId = $request->user_id ?? 1;
            $pendingId = $request->pending_id;
            
            // Check if cart is empty
            $cartCount = DB::connection('mysql')->table('temp_jual')
                ->where('id_user', $userId)->count();
            
            if ($cartCount > 0) {
                return response()->json(['success' => false, 'message' => 'Keranjang masih ada item. Pending/clear terlebih dahulu.']);
            }
            
            // Move pending to cart
            DB::connection('mysql')->statement("
                INSERT INTO temp_jual 
                SELECT a1,a2,a3,a4,a5,a6,a7,a8,a9,a10,a11,a12,a13,a14,a15,a16,a17,num_item,id_user 
                FROM jual_pending_det 
                WHERE header_id = ? AND id_user = ?
            ", [$pendingId, $userId]);
            
            // Delete pending
            DB::connection('mysql')->table('jual_pending_det')->where('header_id', $pendingId)->delete();
            DB::connection('mysql')->table('jual_pending_hdr')->where('id', $pendingId)->delete();
            
            DB::connection('mysql')->commit();
            
            // Return cart data
            $cart = DB::connection('mysql')->table('temp_jual')->where('id_user', $userId)->get();
            return response()->json(['success' => true, 'data' => $cart]);
            
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Get detail barang untuk keranjang (include diskon setting)
    public function getBarangDetail(Request $request)
    {
        try {
            $kode = $request->query('kode');
            
            $barang = DB::connection('mysql')
                ->table('tbarang')
                ->where('brg_kode', $kode)
                ->first();
            
            if (!$barang) {
                return response()->json(['success' => false, 'message' => 'Barang tidak ditemukan'], 404);
            }
            
            // Cek diskon setting
            $diskon = DB::connection('mysql')
                ->select("
                    SELECT disd_disc_rp, disd_disc_pr 
                    FROM tsettingdisc_hdr 
                    INNER JOIN tsettingdisc_dtl ON dish_nomor = disd_dish_nomor 
                    WHERE disd_brg_kode = ? 
                    AND CURDATE() BETWEEN dish_periode1 AND dish_periode2 
                    LIMIT 1
                ", [$kode]);
            
            $discPr = 0;
            $discRp = 0;
            if (count($diskon) > 0) {
                $discPr = (float)$diskon[0]->disd_disc_pr;
                $discRp = (float)$diskon[0]->disd_disc_rp;
            }
            
            // Cek PPN (kolom 12 di Delphi = flag PPN)
            $flagPpn = 2; // Default tidak kena PPN (Delphi: 2 = tidak, 1 = kena)
            
            // Hitung PPN & Total
            $qty = 1;
            $harga = (float)($barang->brg_hrgjualpcs ?? 0);
            $hargaSetelahDiskon = ($harga * (100 - $discPr) / 100) - $discRp;
            
            $ppn = 0;
            $total = 0;
            if ($flagPpn == 1) {
                $ppn = 0.1 * $qty * $hargaSetelahDiskon; // PPN 10%
                $total = ($qty * $hargaSetelahDiskon) + $ppn;
            } else {
                $total = $qty * $hargaSetelahDiskon;
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'Kode' => $barang->brg_kode,
                    'Nama' => $barang->brg_nama_singkat ?? $barang->brg_nama,
                    'Satuan' => $barang->brg_satuanpcs ?? 'PCS',
                    'IsiCarton' => (int)($barang->brg_isipercrt ?? 1),
                    'IsiLusin' => (int)($barang->brg_isiperlsn ?? 1),
                    'Harga' => $harga,
                    'DiscPr' => $discPr,
                    'DiscRp' => $discRp,
                    'FlagPpn' => $flagPpn,
                    'Kategori' => $barang->brg_ktg_kode ?? '',
                    'HargaBeli' => (float)($barang->brg_hrgbeli ?? 0),
                    'Qty' => $qty,
                    'Ppn' => $ppn,
                    'Total' => $total
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Generate No Bon
    public function getNoBon(Request $request)
    {
        try {
            $userId = (int)($request->user_id ?? 1);
            
            // 🔥 Ambil kode kasir dari tuser
            $user = DB::connection('mysql')
                ->table('tuser')
                ->where('user_id', $userId)
                ->first();
            
            $kodeKasir = $user ? $user->user_id : str_pad($userId, 2, '0', STR_PAD_LEFT);
            
            // Prefix: SAL-{kodeKasir}{Ymd}
            $prefix = 'SAL-' . $kodeKasir . date('Ymd');
            
            // Cari nomor terakhir hari ini
            $last = DB::connection('mysql')
                ->table('tso_hdr')
                ->where('so_tanggal', date('Y-m-d'))
                ->where('so_nomor', 'LIKE', $prefix . '%')
                ->orderBy('so_nomor', 'desc')
                ->value('so_nomor');
            
            if ($last) {
                // Ambil 3 digit terakhir
                $lastNum = (int)substr($last, -3);
                $newNum = str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $newNum = '001';
            }
            
            $noBon = $prefix . $newNum;
            
            return response()->json([
                'success' => true, 
                'no_bon' => $noBon,
                'kode_kasir' => (string)$kodeKasir
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Simpan transaksi (bayar)
    public function simpanTransaksi(Request $request)
    {
        try {
            DB::connection('mysql')->beginTransaction();
        
            $userId = (int)($request->user_id ?? 1);
            $noBon = $request->no_bon;
            $customerKode = $request->customer_kode ?: '0000000001';
        
        $total = (float)($request->total ?? 0);
        $ongkir = (float)($request->ongkir ?? 0);
        $potongan = (float)($request->potongan ?? 0);
        $cash = (float)($request->cash ?? 0);
        $voucher = (float)($request->voucher ?? 0);
        $card = (float)($request->card ?? 0);
        $piutang = (float)($request->piutang ?? 0);
        $kembali = (float)($request->kembali ?? 0);
        
        // 🔥 Total bayar = cash + voucher + card + piutang (TANPA potongan)
        $totalBayar = $cash + $voucher + $card + $piutang;
        
        // Get kode kasir
        $user = DB::connection('mysql')->table('tuser')->where('user_id', $userId)->first();
        $kodeKasir = $user ? $user->user_id : str_pad($userId, 2, '0', STR_PAD_LEFT);
        
        // Insert tso_hdr
        DB::connection('mysql')->table('tso_hdr')->insert([
            'so_nomor' => $noBon,
            'so_tanggal' => now(),
            'so_cus_kode' => $customerKode,
            'so_amount' => $total,
            'so_taxamount' => 0,
            'so_bayar' => $totalBayar,          // ✅ Hanya cash+voucher+card+piutang
            'so_user_kasir' => $kodeKasir,
            'so_dp' => $cash,
            'so_disc_faktur' => $potongan,       // ✅ Potongan terpisah
            'so_ongkir' => $ongkir,
            'so_status_bayar' => 1,
            'so_card' => $card,
            'so_no_card' => $request->no_card ?? '',
            'so_bank_card' => $request->bank ?? '',
            'so_piutang' => $piutang,
            'so_kembali' => $kembali,
            'so_voucher' => $voucher,
            'so_no_voucher' => $request->no_voucher ?? '',
            'date_create' => now(),
            'user_create' => $kodeKasir
        ]);
            
            // Insert tso_dtl dari temp_jual
            $items = DB::connection('mysql')->table('temp_jual')
                ->where('id_user', $userId)
                ->orderBy('num_item')
                ->get();
            
            $nourut = 1;
            foreach ($items as $item) {
                $tipeHarga = $item->a17 ?? '2'; // Default '2' (PCS)
                $hargaAsli = (float)($item->a7 ?? 0);
                $qtyAsli = (float)($item->a6 ?? 1);
                $isiCarton = (int)($item->a3 ?? 1);
                $isiLusin = (int)($item->a4 ?? 1);
                
                if ($tipeHarga == '0') {
                    $hargaAsli = $hargaAsli / $isiCarton;
                    $qtyAsli = $qtyAsli * $isiCarton;
                } elseif ($tipeHarga == '1') {
                    $hargaAsli = $hargaAsli / $isiLusin;
                    $qtyAsli = $qtyAsli * $isiLusin;
                }
                
                if ($item->a12 == '1') {
                    $hargaAsli = $hargaAsli / 1.1; // Remove PPN
                }
                
                DB::connection('mysql')->table('tso_dtl')->insert([
                    'sod_nourut' => $nourut,
                    'sod_so_nomor' => $noBon,
                    'sod_brg_kode' => $item->a1,
                    'sod_brg_satuan' => $item->a5,
                    'sod_qty' => $qtyAsli,
                    'sod_harga' => $hargaAsli,
                    'sod_discpr' => (float)($item->a8 ?? 0),
                    'sod_discrp' => (float)($item->a9 ?? 0),
                    'sod_ktg_kode' => $item->a13 ?? '',
                    'sod_brg_avgcost' => (float)($item->a15 ?? 0),
                    'sod_qtykasir' => (float)($item->a6 ?? 1),
                    'sod_hargakasir' => (float)($item->a7 ?? 0),
                    'sod_tipeharga' => $tipeHarga
                ]);
                
                $nourut++;
            }
            
            // Clear temp_jual
            DB::connection('mysql')->table('temp_jual')
                ->where('id_user', $userId)
                ->delete();
            
            DB::connection('mysql')->commit();
            
            // Get struk data
            $struk = $this->getStrukData($noBon);
            
            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil',
                'no_bon' => $noBon,
                'struk' => $struk
            ]);
            
        } catch (\Exception $e) {
            DB::connection('mysql')->rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function getStrukData($noBon)
    {
        // Header
        $perusahaan = DB::connection('mysql')->table('tperusahaan')->first();
        
        // HDR
        $hdr = DB::connection('mysql')
            ->table('tso_hdr')
            ->where('so_nomor', $noBon)
            ->first();
        
        $customer = DB::connection('mysql')
            ->table('tcustomer')
            ->where('cus_kode', $hdr->so_cus_kode)
            ->first();
        
        // Items
        $items = DB::connection('mysql')
            ->table('tso_dtl as d')
            ->join('tbarang as b', 'd.sod_brg_kode', '=', 'b.brg_kode')
            ->where('d.sod_so_nomor', $noBon)
            ->orderBy('d.sod_nourut')
            ->select('d.*', 'b.brg_nama_singkat')
            ->get();
        
        return [
            'perusahaan' => $perusahaan,
            'hdr' => $hdr,
            'customer' => $customer,
            'items' => $items
        ];
    }
}