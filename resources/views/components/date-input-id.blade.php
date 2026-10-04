@props([
    'name',
    'value' => null,
    'id' => null,
    'required' => false,
])

@php
    $inputId = $id ?: str_replace(['[', ']'], ['', ''], $name) . '_' . uniqid();
    $isoValue = $value ? \Carbon\Carbon::parse($value)->format('Y-m-d') : '';
    $displayValue = $isoValue ? \App\Helpers\DateHelper::formatShort($isoValue) : '';
@endphp

<div class="input-group date-input-id" data-date-input-id>
    <input
        type="text"
        id="{{ $inputId }}_display"
        class="form-control"
        value="{{ $displayValue }}"
        placeholder="dd/mm/yyyy"
        inputmode="numeric"
        autocomplete="off"
        maxlength="10"
        @required($required)
    >
    <button type="button" class="btn btn-outline-secondary" data-date-picker aria-label="Pilih tanggal">📅</button>
    <input type="date" id="{{ $inputId }}" name="{{ $name }}" value="{{ $isoValue }}" class="position-absolute opacity-0" style="width:1px;height:1px;pointer-events:none" tabindex="-1" aria-hidden="true">
</div>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function formatDateId(value) {
        const p = String(value || '').split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : '';
    }

    function parseDateId(value) {
        const m = String(value || '').trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (!m) return null;
        const d = new Date(Number(m[3]), Number(m[2]) - 1, Number(m[1]));
        if (d.getFullYear() !== Number(m[3]) || d.getMonth() !== Number(m[2]) - 1 || d.getDate() !== Number(m[1])) return null;
        return m[3] + '-' + m[2] + '-' + m[1];
    }

    document.querySelectorAll('[data-date-input-id]').forEach(function (wrapper) {
        const display = wrapper.querySelector('input[type="text"]');
        const date = wrapper.querySelector('input[type="date"]');
        const picker = wrapper.querySelector('[data-date-picker]');

        display?.addEventListener('change', function () {
            const iso = parseDateId(display.value);
            if (!iso) {
                display.classList.add('is-invalid');
                return;
            }
            date.value = iso;
            display.value = formatDateId(iso);
            display.classList.remove('is-invalid');
        });

        picker?.addEventListener('click', function () {
            if (typeof date.showPicker === 'function') date.showPicker();
            else date.click();
        });

        date?.addEventListener('change', function () {
            display.value = formatDateId(date.value);
            display.classList.remove('is-invalid');
        });
    });
});
</script>
@endpush
@endonce
