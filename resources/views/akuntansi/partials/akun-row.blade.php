<tr>
    <td style="padding-left: {{ 1 + ($depth * 2.5) }}rem;">
        <span class="{{ $depth === 0 ? 'fw-bold' : 'fw-semibold' }}">{{ $account->code }}</span>
    </td>
    <td class="{{ $depth === 0 ? 'fw-semibold' : '' }}">
        @if($depth > 0)<span class="text-secondary me-2">└</span>@endif{{ $account->name }}
    </td>
    <td>{{ $typeLabels[$account->type] ?? $account->type }}</td>
    <td>{{ ucfirst($account->normal_balance) }}</td>
    <td class="text-end">
        @if((int) $account->level > 0)
            <button class="btn btn-sm btn-outline-secondary me-1"
                    data-bs-toggle="modal"
                    data-bs-target="#account-edit-{{ $account->id }}">Edit</button>
        @endif
        @if((int) $account->level < 3)
            <button class="btn btn-sm btn-primary px-3"
                    data-bs-toggle="modal"
                    data-bs-target="#account-add-{{ $account->id }}">+</button>
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
