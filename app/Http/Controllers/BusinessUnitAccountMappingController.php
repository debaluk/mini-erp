<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use App\Models\BusinessUnitAccountMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessUnitAccountMappingController extends Controller
{
    private function entityId(Request $request): int
    {
        $id = (int) $request->user()->entity_id;
        abort_unless($id, 403);
        return $id;
    }

    public function index(Request $request)
    {
        return redirect()->to(route('pengaturan.konfigurasi') . '#setup-akun');
    }

    public function save(Request $request)
    {
        $entityId = $this->entityId($request);

        $data = $request->validate([
            'accounts' => ['required','array'],
            'defaults' => ['required','array'],
            'accounts.*' => ['array'],
            'accounts.*.*' => ['required','integer'],
            'defaults.*' => ['required','integer'],
        ]);

        $units = BusinessUnit::where('entity_id',$entityId)->orderBy('id')->get()->keyBy('id');

        if ($units->isEmpty()) {
            throw ValidationException::withMessages(['accounts'=>'Belum ada Unit Bisnis.']);
        }

        $accountIds = [];
        foreach ($data['accounts'] as $unitId => $items) {
            if (!$units->has((int)$unitId)) {
                throw ValidationException::withMessages(['accounts'=>'Unit Bisnis tidak valid.']);
            }
            foreach ($items as $accountId) $accountIds[] = (int)$accountId;
        }
        foreach ($data['defaults'] as $accountId) $accountIds[] = (int)$accountId;

        $accountIds = array_values(array_unique($accountIds));
        $validIds = DB::table('chart_of_accounts')
            ->where('entity_id',$entityId)->where('is_active',true)->where('is_postable',true)
            ->whereIn('id',$accountIds)->pluck('id')->map(fn($id)=>(int)$id)->all();

        if (count($validIds) !== count($accountIds)) {
            throw ValidationException::withMessages(['accounts'=>'Akun tidak valid untuk entitas ini.']);
        }

        DB::transaction(function () use ($data,$units,$entityId) {
            foreach ($data['accounts'] as $unitId => $items) {
                foreach ($items as $mappingKey => $accountId) {
                    BusinessUnitAccountMapping::updateOrCreate(
                        ['entity_id'=>$entityId,'business_unit_id'=>$units[(int)$unitId]->id,'mapping_key'=>$mappingKey],
                        ['account_id'=>$accountId]
                    );
                }
            }
            foreach ($units as $unit) {
                foreach ($data['defaults'] as $mappingKey => $accountId) {
                    BusinessUnitAccountMapping::updateOrCreate(
                        ['entity_id'=>$entityId,'business_unit_id'=>$unit->id,'mapping_key'=>$mappingKey],
                        ['account_id'=>$accountId]
                    );
                }
            }
        });

        return back()->with('toast', [
            'type'=>'success',
            'message'=>'Setup Akun berhasil disimpan.',
            'target'=>'setup-akun',
        ]);
    }
}
