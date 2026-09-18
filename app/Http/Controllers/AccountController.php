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

    private function resolveLevel(string $code): int
    {
        return match (strlen($code)) {
            3 => 1,
            5 => 2,
            7 => 3,
            default => 0,
        };
    }

    private function validateStructure(int $entity, string $code, ?int $parentId, ?int $currentId = null): array
    {
        $level = $this->resolveLevel($code);
        if (!$level) {
            throw ValidationException::withMessages(['code' => 'Kode akun harus 3, 5, atau 7 digit.']);
        }

        $parent = null;
        if ($level > 1) {
            $parent = DB::table('chart_of_accounts')
                ->where('entity_id', $entity)
                ->where('id', $parentId ?? 0)
                ->first();

            if (!$parent || $parent->level !== $level - 1 || ($currentId && $parent->id === $currentId)) {
                throw ValidationException::withMessages(['parent_id' => 'Parent akun harus satu level di atas akun ini.']);
            }

            if (!str_starts_with($code, $parent->code)) {
                throw ValidationException::withMessages(['code' => 'Kode akun harus mengikuti kode parent.']);
            }
        } elseif ($parentId) {
            throw ValidationException::withMessages(['parent_id' => 'Level 1 tidak boleh memiliki parent.']);
        }

        return [$level, $parent];
    }

    public function index()
    {
        $entity = $this->entityId();
        $accounts = DB::table('chart_of_accounts as a')
            ->leftJoin('chart_of_accounts as p', 'p.id', '=', 'a.parent_id')
            ->where('a.entity_id', $entity)
            ->select('a.*', 'p.code as parent_code', 'p.name as parent_name')
            ->orderBy('a.code')
            ->get();

        $parents = $accounts->whereIn('level', [1, 2])->where('is_active', 1)->values();

        return view('akuntansi.akun', compact('accounts', 'parents'));
    }

    public function store(Request $request)
    {
        $entity = $this->entityId();
        $data = $request->validate([
            'code' => ['required', 'regex:/^\d{3}(?:\d{2})?(?:\d{2})?$/', 'max:7'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:asset,liability,equity,revenue,cogs,expense'],
            'parent_id' => ['nullable', 'integer'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'description' => ['nullable', 'string'],
            'is_cash_bank' => ['nullable', 'boolean'],
        ]);

        [$level, $parent] = $this->validateStructure($entity, $data['code'], $data['parent_id'] ?? null);

        if (DB::table('chart_of_accounts')->where('entity_id', $entity)->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode akun sudah digunakan.']);
        }

        DB::table('chart_of_accounts')->insert([
            'entity_id' => $entity,
            'code' => $data['code'],
            'name' => $data['name'],
            'level' => $level,
            'type' => $data['type'],
            'normal_balance' => $data['normal_balance'],
            'parent_id' => $parent?->id,
            'is_postable' => $level === 3,
            'is_cash_bank' => (bool) ($data['is_cash_bank'] ?? false),
            'description' => $data['description'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Akun berhasil ditambahkan.');
    }

    public function update(Request $request, int $id)
    {
        $entity = $this->entityId();
        $account = DB::table('chart_of_accounts')->where('entity_id', $entity)->where('id', $id)->first();
        abort_unless($account, 404);

        $data = $request->validate([
            'code' => ['required', 'regex:/^\d{3}(?:\d{2})?(?:\d{2})?$/', 'max:7'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:asset,liability,equity,revenue,cogs,expense'],
            'parent_id' => ['nullable', 'integer'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'description' => ['nullable', 'string'],
            'is_cash_bank' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        [$level, $parent] = $this->validateStructure($entity, $data['code'], $data['parent_id'] ?? null, $id);

        $hasChildren = DB::table('chart_of_accounts')->where('parent_id', $id)->exists();
        if ($hasChildren && $level === 3) {
            throw ValidationException::withMessages(['code' => 'Akun yang memiliki turunan tidak dapat diubah menjadi Level 3.']);
        }

        if (DB::table('chart_of_accounts')->where('entity_id', $entity)->where('code', $data['code'])->where('id', '<>', $id)->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode akun sudah digunakan.']);
        }

        DB::table('chart_of_accounts')->where('id', $id)->update([
            'code' => $data['code'],
            'name' => $data['name'],
            'level' => $level,
            'type' => $data['type'],
            'normal_balance' => $data['normal_balance'],
            'parent_id' => $parent?->id,
            'is_postable' => $level === 3,
            'is_cash_bank' => (bool) ($data['is_cash_bank'] ?? false),
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
