import { usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, XCircle } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

export default function FlashMessage() {
    const { props } = usePage<{
        flash?: {
            success?: string;
            error?: string;
            warning?: string;
            dorm_import_error_token?: string | null;
            formal_tingkat_import_error_token?: string | null;
        };
    }>();
    const flash = props.flash;

    const hasBanner =
        flash?.success ||
        flash?.error ||
        flash?.warning ||
        flash?.dorm_import_error_token ||
        flash?.formal_tingkat_import_error_token;
    if (!hasBanner) return null;

    const dormErrorCsvHref =
        flash?.dorm_import_error_token != null && flash.dorm_import_error_token !== ''
            ? `/admin/asrama/import-errors?token=${encodeURIComponent(flash.dorm_import_error_token)}`
            : null;

    const formalTingkatErrorCsvHref =
        flash?.formal_tingkat_import_error_token != null && flash.formal_tingkat_import_error_token !== ''
            ? `/admin/formal-tingkat/import-errors?token=${encodeURIComponent(flash.formal_tingkat_import_error_token)}`
            : null;

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
                    <AlertDescription>
                        <span className="block whitespace-pre-wrap">{flash.warning}</span>
                        {dormErrorCsvHref ? (
                            <a
                                href={dormErrorCsvHref}
                                className="mt-3 block font-semibold underline underline-offset-4 text-amber-900 hover:text-amber-950 dark:text-amber-200 dark:hover:text-amber-50"
                            >
                                Unduh CSV detail error impor kobong
                            </a>
                        ) : null}
                        {formalTingkatErrorCsvHref ? (
                            <a
                                href={formalTingkatErrorCsvHref}
                                className="mt-3 block font-semibold underline underline-offset-4 text-amber-900 hover:text-amber-950 dark:text-amber-200 dark:hover:text-amber-50"
                            >
                                Unduh CSV detail error impor tingkat formal
                            </a>
                        ) : null}
                    </AlertDescription>
                </Alert>
            )}
            {!flash.warning && flash.dorm_import_error_token && (
                <Alert className="border-amber-400 bg-amber-50 text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100">
                    <AlertTriangle className="text-amber-600 dark:text-amber-400" />
                    <AlertTitle>Detail error impor</AlertTitle>
                    <AlertDescription>
                        <a
                            href={`/admin/asrama/import-errors?token=${encodeURIComponent(flash.dorm_import_error_token)}`}
                            className="inline-flex font-semibold underline underline-offset-4 text-amber-900 hover:text-amber-950 dark:text-amber-200 dark:hover:text-amber-50"
                        >
                            Unduh CSV detail error impor kobong
                        </a>
                    </AlertDescription>
                </Alert>
            )}
            {!flash.warning && flash.formal_tingkat_import_error_token && (
                <Alert className="border-amber-400 bg-amber-50 text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100">
                    <AlertTriangle className="text-amber-600 dark:text-amber-400" />
                    <AlertTitle>Detail error impor</AlertTitle>
                    <AlertDescription>
                        <a
                            href={`/admin/formal-tingkat/import-errors?token=${encodeURIComponent(flash.formal_tingkat_import_error_token)}`}
                            className="inline-flex font-semibold underline underline-offset-4 text-amber-900 hover:text-amber-950 dark:text-amber-200 dark:hover:text-amber-50"
                        >
                            Unduh CSV detail error impor tingkat formal
                        </a>
                    </AlertDescription>
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
