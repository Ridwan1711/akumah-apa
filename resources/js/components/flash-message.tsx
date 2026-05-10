import { usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, XCircle } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

export default function FlashMessage() {
    const { props } = usePage<{ flash?: { success?: string; error?: string; warning?: string } }>();
    const flash = props.flash;

    if (!flash?.success && !flash?.error && !flash?.warning) return null;

    return (
        <div className="mb-4">
            {flash.success && (
                <Alert>
                    <CheckCircle2 className="text-green-600" />
                    <AlertTitle>Berhasil</AlertTitle>
                    <AlertDescription>{flash.success}</AlertDescription>
                </Alert>
            )}
            {flash.warning && (
                <Alert className="border-amber-400 bg-amber-50 text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100">
                    <AlertTriangle className="text-amber-600 dark:text-amber-400" />
                    <AlertTitle>Perhatian</AlertTitle>
                    <AlertDescription>{flash.warning}</AlertDescription>
                </Alert>
            )}
            {flash.error && (
                <Alert variant="destructive">
                    <XCircle />
                    <AlertTitle>Error</AlertTitle>
                    <AlertDescription>{flash.error}</AlertDescription>
                </Alert>
            )}
        </div>
    );
}
