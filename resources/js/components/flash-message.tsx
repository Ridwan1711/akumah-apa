import { usePage } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

export default function FlashMessage() {
    const { props } = usePage<{ flash?: { success?: string; error?: string } }>();
    const flash = props.flash;

    if (!flash?.success && !flash?.error) return null;

    return (
        <div className="mb-4">
            {flash.success && (
                <Alert>
                    <CheckCircle2 className="text-green-600" />
                    <AlertTitle>Berhasil</AlertTitle>
                    <AlertDescription>{flash.success}</AlertDescription>
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
