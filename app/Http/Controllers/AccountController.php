<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 0);
    }

    private function level(?object $account): int
    {
        return $account ? (int) $account->level : 0;
    }

    private function nextCode(int $entity, object $parent): string
    {
        $prefix = $parent->code;
        $children = DB::table('chart_of_accounts')
            ->where('entity_id', $entity)
            ->where('parent_id', $parent->id)
            ->pluck('code');

        $max = 0;
        foreach ($children as $code) {
            $suffix = substr((string) $code, strlen($prefix));
            if (ctype_digit($suffix)) $max = max($max, (int) $suffix);
        }

        $next = $max + 1;
        if ($next > 99) {
            throw ValidationException::withMessages(['name' => 'Jumlah akun turunan untuk parent ini sudah mencapai batas.']);
        }

        return $prefix . str_pad((string) $next, 2, '0', STR_PAD_LEFT);
    }

    public function index()
    {
        $entity = $this->entityId();

        $accounts = DB::table('chart_of_accounts')
            ->where('entity_id', $entity)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $nextCodes = [];
        foreach ($accounts as $account) {
            if ((int) $account->level < 3) {
                $nextCodes[$account->id] = $this->nextCode($entity, $account);
            }
        }

        return view('akuntansi.akun', compact('accounts', 'nextCodes'));
    }

    public function exportExcel()
    {
        $entity = DB::table('entities')->where('id', $this->entityId())->first();
        $accounts = DB::table('chart_of_accounts')
            ->where('entity_id', $this->entityId())
            ->orderBy('code')
            ->get();

        $entityName = $entity->name ?? 'Entitas';
        $filename = 'coa-' . now()->format('Ymd-His') . '.xls';

        return response()->streamDownload(function () use ($accounts, $entityName) {
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<table border="0"><tr><th colspan="6">' . e($entityName) . '</th></tr>';
            echo '<tr><th colspan="6">CHART OF ACCOUNTS</th></tr>';
            echo '<tr><th colspan="6">Struktur 3 Level</th></tr></table>';
            echo '<br><table border="1">';
            echo '<tr><th>Kode</th><th>Nama Akun</th><th>Level</th><th>Normal Balance</th><th>Posting</th><th>Status</th></tr>';
            foreach ($accounts as $a) {
                echo '<tr>';
                echo '<td>' . e($a->code) . '</td>';
                echo '<td>' . e(str_repeat('    ', max(0, ((int) $a->level) - 1)) . $a->name) . '</td>';
                echo '<td>' . (int) $a->level . '</td>';
                echo '<td>' . e(ucfirst($a->normal_balance)) . '</td>';
                echo '<td>' . ($a->is_postable ? 'Ya' : 'Tidak') . '</td>';
                echo '<td>' . ($a->is_active ? 'Aktif' : 'Nonaktif') . '</td>';
                echo '</tr>';
            }
            echo '</table></body></html>';
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel']);
    }

    public function store(Request $request)
    {
        $entity = $this->entityId();
        $data = $request->validate([
            'parent_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'description' => ['nullable', 'string'],
        ]);

        $parent = DB::table('chart_of_accounts')
            ->where('entity_id', $entity)->where('id', $data['parent_id'])->first();

        if (!$parent || $this->level($parent) >= 3) {
            throw ValidationException::withMessages(['parent_id' => 'Akun ini tidak dapat memiliki turunan.']);
        }

        $code = $this->nextCode($entity, $parent);
        $level = $this->level($parent) + 1;

        DB::table('chart_of_accounts')->insert([
            'entity_id' => $entity,
            'code' => $code,
            'name' => $data['name'],
            'level' => $level,
            'type' => $parent->type,
            'normal_balance' => $data['normal_balance'],
            'parent_id' => $parent->id,
            'is_postable' => $level === 3,
            'is_cash_bank' => false,
            'description' => $data['description'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', "Akun {$code} berhasil ditambahkan.");
    }

    public function update(Request $request, int $id)
    {
        $entity = $this->entityId();
        $account = DB::table('chart_of_accounts')->where('entity_id', $entity)->where('id', $id)->first();
        abort_unless($account, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::table('chart_of_accounts')->where('id', $id)->update([
            'name' => $data['name'],
            'normal_balance' => $data['normal_balance'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Akun berhasil diperbarui.');
    }

    public function destroy(int $id)
    {
        $entity = $this->entityId();
        $account = DB::table('chart_of_accounts')->where('entity_id', $entity)->where('id', $id)->first();
        abort_unless($account, 404);

        if (DB::table('chart_of_accounts')->where('parent_id', $id)->exists() ||
            DB::table('journal_entries')->where('account_id', $id)->exists()) {
            return back()->with('error', 'Akun tidak dapat dihapus karena sudah memiliki turunan atau transaksi jurnal.');
        }

        DB::table('chart_of_accounts')->where('id', $id)->delete();
        return back()->with('success', 'Akun berhasil dihapus.');
    }
}
