import Swal, { type SweetAlertIcon, type SweetAlertOptions } from 'sweetalert2';

type ConfirmOptions = {
    title?: string;
    text?: string;
    icon?: SweetAlertIcon;
    confirmButtonText?: string;
    cancelButtonText?: string;
    focusCancel?: boolean;
};

export type FlashNotification = {
    success?: string | null;
    error?: string | null;
    warning?: string | null;
    info?: string | null;
    status?: string | null;
    message?: string | null;
};

const statusMessages: Record<string, string> = {
    'verification-link-sent': 'Link verifikasi berhasil dikirim.',
};

const appSwal = Swal.mixin({
    buttonsStyling: false,
    reverseButtons: true,
    showClass: {
        popup: 'swal2-show esakip-swal-enter',
    },
    hideClass: {
        popup: 'swal2-hide esakip-swal-leave',
    },
    customClass: {
        popup: 'esakip-swal-popup',
        title: 'esakip-swal-title',
        htmlContainer: 'esakip-swal-text',
        actions: 'esakip-swal-actions',
        confirmButton: 'esakip-swal-button esakip-swal-confirm',
        cancelButton: 'esakip-swal-button esakip-swal-cancel',
        denyButton: 'esakip-swal-button esakip-swal-deny',
    },
});

export async function confirmAction(options: ConfirmOptions): Promise<boolean> {
    const result = await appSwal.fire({
        title: options.title ?? 'Konfirmasi',
        text: options.text,
        icon: options.icon ?? 'question',
        showCancelButton: true,
        confirmButtonText: options.confirmButtonText ?? 'Ya, lanjutkan',
        cancelButtonText: options.cancelButtonText ?? 'Batal',
        focusCancel: options.focusCancel ?? false,
    });

    return result.isConfirmed;
}

export function confirmDelete(text: string, options: Omit<ConfirmOptions, 'text' | 'icon' | 'confirmButtonText'> = {}): Promise<boolean> {
    return confirmAction({
        title: options.title ?? 'Hapus data?',
        text,
        icon: 'warning',
        confirmButtonText: 'Ya, hapus',
        cancelButtonText: options.cancelButtonText ?? 'Batal',
        focusCancel: options.focusCancel ?? true,
    });
}

export function alertSuccess(title: string, text?: string): Promise<unknown> {
    return appSwal.fire({
        title,
        text,
        icon: 'success',
        confirmButtonText: 'Mengerti',
    });
}

export function alertError(title: string, text?: string): Promise<unknown> {
    return appSwal.fire({
        title,
        text,
        icon: 'error',
        confirmButtonText: 'Mengerti',
    });
}

export function alertInfo(title: string, text?: string): Promise<unknown> {
    return appSwal.fire({
        title,
        text,
        icon: 'info',
        confirmButtonText: 'Mengerti',
    });
}

export function toast(message: string, icon: SweetAlertIcon = 'success', options: SweetAlertOptions = {}): Promise<unknown> {
    return appSwal.fire({
        toast: true,
        position: 'top-end',
        title: message,
        icon,
        showConfirmButton: false,
        timer: 3500,
        timerProgressBar: true,
        customClass: {
            popup: 'esakip-swal-toast',
        },
        ...options,
    });
}

export function notifyFlash(flash?: FlashNotification | null): Promise<unknown> | null {
    if (!flash) {
        return null;
    }

    const notifications: Array<[keyof FlashNotification, SweetAlertIcon]> = [
        ['success', 'success'],
        ['error', 'error'],
        ['warning', 'warning'],
        ['info', 'info'],
        ['status', 'success'],
        ['message', 'info'],
    ];

    for (const [key, icon] of notifications) {
        const value = flash[key];

        if (typeof value === 'string' && value.trim().length > 0) {
            const message = statusMessages[value] ?? value;

            return toast(message, icon);
        }
    }

    return null;
}
