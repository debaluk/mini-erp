<tr>
    <td class="fw-semibold" style="padding-left: {{ 1 + ($depth * 3) }}rem;">
        {{ $account->code }}
    </td>
    <td class="{{ $depth === 0 ? 'fw-semibold' : '' }}">
        {{ $account->name }}
    </td>
    <td>{{ ucfirst($account->normal_balance) }}</td>
    <td class="text-end">
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
            'depth' => $depth + 1
        ])
    @endforeach
@endif
