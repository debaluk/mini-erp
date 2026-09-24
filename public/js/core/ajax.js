export async function fetchJson(url, options = {}) {
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        ...(options.headers || {}),
    };

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    if (csrf && !headers['X-CSRF-TOKEN']) {
        headers['X-CSRF-TOKEN'] = csrf;
    }

    const response = await fetch(url, {
        ...options,
        headers,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const errors = Object.values(data.errors || {})
            .flat()
            .filter(Boolean);

        const message = data.message ||
            (errors.length ? errors : 'Terjadi kesalahan pada server.');

        const error = new Error(
            Array.isArray(message) ? message.join(' ') : message
        );

        error.status = response.status;
        error.data = data;

        throw error;
    }

    return data;
}

export function initAjax() {
    window.erpFetchJson = fetchJson;
}
