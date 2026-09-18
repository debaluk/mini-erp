<tr>
    <td style="padding-left: {{ 1 + ($depth * 2.5) }}rem;">
        <span class="{{ $depth === 0 ? 'fw-bold' : 'fw-semibold' }}">{{ $account->code }}</span>
    </td>
    <td class="{{ $depth === 0 ? 'fw-semibold' : '' }}">
        @if($depth > 0)<span class="text-secondary me-2">└</span>@endif{{ $account->name }}
    </td>
    <td>{{ $typeLabels[$account->type] ?? $account->type }}</td>
    <td>{{ ucfirst($account->normal_balance) }}</td>
    <td class="text-end text-nowrap">
        @if((int) $account->level < 3)
            <button class="btn btn-sm btn-outline-primary me-1"
                    title="Tambah akun"
                    aria-label="Tambah akun"
                    data-bs-toggle="modal"
                    data-bs-target="#account-add-{{ $account->id }}">+</button>
        @endif
        @if(strlen((string) $account->code) > 3)
            <button class="btn btn-sm btn-outline-secondary me-1"
                    title="Edit akun"
                    aria-label="Edit akun"
                    data-bs-toggle="modal"
                    data-bs-target="#account-edit-{{ $account->id }}"><i class="bi bi-pencil"></i></button>
            <form method="POST" action="{{ route('akuntansi.akun.delete', $account->id) }}" class="d-inline"
                  onsubmit="return confirm('Hapus akun {{ addslashes($account->code) }} — {{ addslashes($account->name) }}?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger"
                        title="Hapus akun"
                        aria-label="Hapus akun"><i class="bi bi-trash"></i></button>
            </form>
        @endif
    </td>
</tr>

@if($children->has($account->id))
    @foreach($children->get($account->id) as $child)
        @include('akuntansi.partials.akun-row', [
            'account' => $child,
            'children' => $children,
            'nextCodes' => $nextCodes,
            'depth' => $depth + 1,
            'typeLabels' => $typeLabels
        ])
    @endforeach
@endif
