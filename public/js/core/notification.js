export function initNotification() {
    const messageEl = document.getElementById('erpMessageModal');
    const confirmEl = document.getElementById('erpConfirmModal');

    if (!messageEl || !confirmEl || typeof bootstrap === 'undefined') {
        return;
    }

    const messageModal = bootstrap.Modal.getOrCreateInstance(messageEl);
    const confirmModal = bootstrap.Modal.getOrCreateInstance(confirmEl);

    const messageTitle = document.getElementById('erpMessageTitle');
    const messageBody = document.getElementById('erpMessageBody');
    const confirmTitle = document.getElementById('erpConfirmTitle');
    const confirmBody = document.getElementById('erpConfirmBody');
    const confirmYes = document.getElementById('erpConfirmYes');
    const confirmNo = document.getElementById('erpConfirmNo');

    let confirmResolve = null;

    window.erpNotify = (message, type = 'success') => {
        const titles = {
            success: 'Berhasil',
            danger: 'Gagal',
            warning: 'Peringatan',
            info: 'Informasi',
        };

        const buttons = {
            success: 'btn-primary',
            danger: 'btn-danger',
            warning: 'btn-warning',
            info: 'btn-primary',
        };

        messageTitle.textContent = titles[type] || titles.info;

        messageBody.innerHTML = '';

        if (Array.isArray(message)) {
            const ul = document.createElement('ul');
            ul.className = 'mb-0 ps-3';

            message.forEach(item => {
                const li = document.createElement('li');
                li.textContent = item;
                ul.appendChild(li);
            });

            messageBody.appendChild(ul);
        } else {
            messageBody.textContent = String(message ?? '');
        }

        const okButton = messageEl.querySelector('.modal-footer button');

        if (okButton) {
            okButton.className =
                'btn btn-sm ' + (buttons[type] || buttons.info);
        }

        messageModal.show();
    };

    window.erpConfirm = (
        message = 'Apakah Anda yakin?',
        title = 'Konfirmasi'
    ) => new Promise(resolve => {
        confirmResolve = resolve;
        confirmTitle.textContent = title;
        confirmBody.textContent = message;
        confirmModal.show();
    });

    confirmYes?.addEventListener('click', () => {
        confirmModal.hide();

        if (confirmResolve) {
            confirmResolve(true);
        }

        confirmResolve = null;
    });

    confirmNo?.addEventListener('click', () => {
        confirmModal.hide();

        if (confirmResolve) {
            confirmResolve(false);
        }

        confirmResolve = null;
    });

    confirmEl.addEventListener('hidden.bs.modal', () => {
        if (confirmResolve) {
            confirmResolve(false);
        }

        confirmResolve = null;
    });
}
