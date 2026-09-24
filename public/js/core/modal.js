export function getModal(elementOrId) {
    const element = typeof elementOrId === 'string'
        ? document.getElementById(elementOrId)
        : elementOrId;

    if (!element || typeof bootstrap === 'undefined') {
        return null;
    }

    return bootstrap.Modal.getOrCreateInstance(element);
}

export function showModal(elementOrId) {
    const modal = getModal(elementOrId);

    if (modal) {
        modal.show();
    }

    return modal;
}

export function hideModal(elementOrId) {
    const modal = getModal(elementOrId);

    if (modal) {
        modal.hide();
    }

    return modal;
}
