<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->first()?->id ?? 0);
    }

    private function openShift(int $entity)
    {
        return DB::table('cash_shifts')
            ->where('entity_id', $entity)
            ->where('user_id', auth()->id())
            ->where('status', 'open')
            ->latest('id')
            ->first();
    }

    private function summary(int $shiftId): array
    {
        $shift = DB::table('cash_shifts')->where('id', $shiftId)->first();

        $payments = DB::table('payments as p')
            ->join('sales as s', 's.id', '=', 'p.sale_id')
            ->where('s.shift_id', $shiftId)
            ->select(
                'p.method',
                DB::raw('SUM(p.amount) as amount'),
                DB::raw('SUM(COALESCE(p.change_amount,0)) as change_amount')
            )
            ->groupBy('p.method')
            ->get();

        $cashSales = (float) ($payments->firstWhere('method', 'Tunai')?->amount ?? 0);
        $cashChange = (float) ($payments->firstWhere('method', 'Tunai')?->change_amount ?? 0);

        $movements = DB::table('shift_cash_movements')
            ->where('cash_shift_id', $shiftId)
            ->get();

        $cashIn = (float) $movements->where('movement_type', 'in')->sum('amount');
        $cashOut = (float) $movements->where('movement_type', 'out')->sum('amount');

        $expected = (float) $shift->opening_cash + $cashSales - $cashChange + $cashIn - $cashOut;
        $closing = $shift->closing_cash !== null ? (float) $shift->closing_cash : null;
        $difference = $closing === null ? null : $closing - $expected;

        return [
            'opening_cash' => (float) $shift->opening_cash,
            'cash_sales' => $cashSales,
            'cash_change' => $cashChange,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_cash' => $expected,
            'closing_cash' => $closing,
            'difference' => $difference,
            'payments' => $payments,
            'movements' => $movements,
        ];
    }

    public function index()
    {
        $entity = $this->entityId();
        $openShift = $this->openShift($entity);

        $summary = $openShift ? $this->summary($openShift->id) : null;

        $shifts = DB::table('cash_shifts as cs')
            ->join('users as u', 'u.id', '=', 'cs.user_id')
            ->where('cs.entity_id', $entity)
            ->where('cs.user_id', auth()->id())
            ->select('cs.*', 'u.name as user_name')
            ->latest('cs.id')
            ->paginate(15)
            ->withQueryString();

        $entityData = DB::table('entities')->where('id', $entity)->first();

        return view('erp.shifts', compact('openShift', 'summary', 'shifts', 'entityData'));
    }

    public function open(Request $request)
    {
        $data = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ]);

        $entity = $this->entityId();
        abort_if($this->openShift($entity), 422, 'Shift masih terbuka.');

        $user = DB::table('users')
            ->where('id', auth()->id())
            ->where('entity_id', $entity)
            ->first();
        abort_unless($user && $user->default_business_unit_id, 422, 'Default Business Unit user belum ditentukan.');

        $mapped = DB::table('user_business_units')
            ->where('user_id', $user->id)
            ->where('business_unit_id', $user->default_business_unit_id)
            ->exists();
        abort_unless($mapped, 422, 'Default Business Unit belum dipetakan ke user.');

        // IMPORTANT: shift BU is the transaction context for all POS sales in this shift.
        DB::table('cash_shifts')->insert([
            'entity_id' => $entity,
            'business_unit_id' => $user->default_business_unit_id,
            'user_id' => auth()->id(),
            'opened_at' => now(),
            'opening_cash' => $data['opening_cash'],
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Shift kasir berhasil dibuka.');
    }

    public function movement(Request $request)
    {
        $data = $request->validate([
            'movement_type' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $entity = $this->entityId();
        $shift = $this->openShift($entity);
        abort_unless($shift, 422, 'Buka shift terlebih dahulu.');

        DB::table('shift_cash_movements')->insert([
            'cash_shift_id' => $shift->id,
            'entity_id' => $entity,
            'business_unit_id' => $shift->business_unit_id,
            'user_id' => auth()->id(),
            'movement_type' => $data['movement_type'],
            'amount' => $data['amount'],
            'description' => $data['description'],
            'movement_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', $data['movement_type'] === 'in' ? 'Kas masuk berhasil dicatat.' : 'Kas keluar berhasil dicatat.');
    }

    public function close(Request $request)
    {
        $data = $request->validate([
            'closing_cash' => ['required', 'numeric', 'min:0'],
        ]);

        $entity = $this->entityId();
        $shift = $this->openShift($entity);
        abort_unless($shift, 422, 'Tidak ada shift terbuka.');

        $summary = $this->summary($shift->id);

        DB::table('cash_shifts')
            ->where('id', $shift->id)
            ->update([
                'closed_at' => now(),
                'closing_cash' => $data['closing_cash'],
                'expected_cash' => $summary['expected_cash'],
                'cash_difference' => (float) $data['closing_cash'] - $summary['expected_cash'],
                'status' => 'closed',
                'updated_at' => now(),
            ]);

        return back()->with('success', 'Shift ditutup. Selisih kas: Rp '.number_format((float) $data['closing_cash'] - $summary['expected_cash'], 2, ',', '.'));
    }

    public function detail(int $id)
    {
        $entity = $this->entityId();

        $shift = DB::table('cash_shifts')
            ->where('entity_id', $entity)
            ->where('user_id', auth()->id())
            ->where('id', $id)
            ->first();

        abort_unless($shift, 404);

        return response()->json([
            'shift' => $shift,
            'summary' => $this->summary($id),
        ]);
    }
}
