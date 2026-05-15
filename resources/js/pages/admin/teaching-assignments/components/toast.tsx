import { CheckCircle2, X, XCircle } from 'lucide-react';
import type { ToastState } from '../types';

type ToastProps = {
    toast: ToastState;
    onClose: () => void;
};

export function Toast({ toast, onClose }: ToastProps) {
    if (!toast) return null;
    const isSuccess = toast.type === 'success';
    return (
        <div
            className={`fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-xl px-5 py-4 shadow-2xl border
                transition-all duration-300 animate-in slide-in-from-bottom-4
                ${isSuccess
                    ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                    : 'bg-red-50 border-red-200 text-red-800'
                }`}
        >
            {isSuccess
                ? <CheckCircle2 className="h-5 w-5 text-emerald-500 shrink-0" />
                : <XCircle className="h-5 w-5 text-red-500 shrink-0" />
            }
            <span className="text-sm font-medium">{toast.message}</span>
            <button
                onClick={onClose}
                className="ml-2 rounded-full p-0.5 hover:bg-black/10 transition-colors"
            >
                <X className="h-4 w-4" />
            </button>
        </div>
    );
}
