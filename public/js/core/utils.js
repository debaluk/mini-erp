export function money(value) {
    return new Intl.NumberFormat('id-ID').format(Number(value || 0));
}

export function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
}
