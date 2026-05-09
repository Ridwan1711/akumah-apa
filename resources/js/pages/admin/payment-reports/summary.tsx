import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArcElement,
    CategoryScale,
    Chart,
    Colors,
    DoughnutController,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';
import { ArrowRight, Download, TrendingUp } from 'lucide-react';
import { useEffect, useMemo, useRef } from 'react';
import {
    CrudCard,
    CrudPageHeader,
    CrudStatStrip,
    CrudTableShell,
    CrudToolbar,
} from '@/components/manhood';
import AppLayout from '@/layouts/app-layout';
import { can } from '@/lib/authz';
import type { Auth, BreadcrumbItem, Payment } from '@/types';

Chart.register(
    LineController,
    DoughnutController,
    CategoryScale,
    LinearScale,
    LineElement,
    PointElement,
    ArcElement,
    Tooltip,
    Legend,
    Colors,
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Laporan Keuangan', href: '/admin/payment-reports' },
];

const categoryLabels: Record<string, string> = { spp: 'SPP', non_spp: 'Non-SPP', infaq: 'Infaq' };

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}

type ClassSummary = { id: number; name: string; invoice_count: number; total_invoiced: number; total_paid: number };
type CategorySummary = { category: string; total_invoiced: number };

type Props = {
    stats: {
        total_invoiced: number;
        total_paid: number;
        total_pending: number;
        total_overdue: number;
        collection_rate: number;
    };
    byCategory: CategorySummary[];
    byClass: ClassSummary[];
    recentPayments: Payment[];
};

export default function PaymentReportSummary({ stats, byCategory, byClass, recentPayments }: Props) {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const canViewReport = can(auth, 'payment.report.view');

    const trendRef = useRef<HTMLCanvasElement | null>(null);
    const donutRef = useRef<HTMLCanvasElement | null>(null);

    const trendLabels = useMemo(
        () => byClass.slice(0, 8).map((item) => item.name),
        [byClass],
    );

    const trendInvoiced = useMemo(
        () => byClass.slice(0, 8).map((item) => Number(item.total_invoiced)),
        [byClass],
    );

    const trendPaid = useMemo(
        () => byClass.slice(0, 8).map((item) => Number(item.total_paid)),
        [byClass],
    );

    useEffect(() => {
        if (!trendRef.current || !donutRef.current) return;

        const trendChart = new Chart(trendRef.current, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Tagihan',
                        data: trendInvoiced,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.16)',
                        tension: 0.35,
                        fill: true,
                    },
                    {
                        label: 'Terbayar',
                        data: trendPaid,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.12)',
                        tension: 0.35,
                        fill: true,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', align: 'end' },
                },
            },
        });

        const donutChart = new Chart(donutRef.current, {
            type: 'doughnut',
            data: {
                labels: byCategory.map((item) => categoryLabels[item.category] ?? item.category),
                datasets: [
                    {
                        data: byCategory.map((item) => Number(item.total_invoiced)),
                        borderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '66%',
                plugins: {
                    legend: { position: 'bottom' },
                },
            },
        });

        return () => {
            trendChart.destroy();
            donutChart.destroy();
        };
    }, [trendLabels, trendInvoiced, trendPaid, byCategory]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Laporan Keuangan" />
            <div>
                <CrudPageHeader
                    title="Laporan Keuangan"
                    description="Ringkasan koleksi pembayaran, tagihan, dan tunggakan per kategori serta per kelas."
                />

                <CrudStatStrip
                    items={[
                        { key: 'invoiced', label: 'Total Tagihan', value: formatCurrency(stats.total_invoiced), icon: <TrendingUp size={18} />, tone: 'blue' },
                        { key: 'paid', label: 'Total Terbayar', value: formatCurrency(stats.total_paid), icon: <TrendingUp size={18} />, tone: 'green' },
                        { key: 'pending', label: 'Sisa Tunggakan', value: formatCurrency(stats.total_pending), icon: <TrendingUp size={18} />, tone: 'amber' },
                        { key: 'overdue', label: 'Sisa Jatuh Tempo', value: formatCurrency(stats.total_overdue), icon: <TrendingUp size={18} />, tone: 'purple' },
                    ]}
                />

                <CrudToolbar
                    left={null}
                    right={(
                        <>
                            {canViewReport ? (
                                <Link href="/admin/payment-reports/arrears" className="mcr-btn secondary">
                                    <ArrowRight size={14} />
                                    Daftar Tunggakan
                                </Link>
                            ) : null}
                            {canViewReport ? (
                                <a href="/admin/payment-reports/export" className="mcr-btn primary">
                                    <Download size={14} />
                                    Export CSV
                                </a>
                            ) : null}
                        </>
                    )}
                />

                <div className="mcr-no-print" style={{ display: 'grid', gridTemplateColumns: '2fr 1fr', gap: 14, marginBottom: 14 }}>
                    <CrudCard title="Tren Tagihan vs Terbayar (Top Kelas)">
                        <div style={{ height: 280 }}>
                            <canvas ref={trendRef} />
                        </div>
                    </CrudCard>
                    <CrudCard title="Distribusi Kategori Tagihan">
                        <div style={{ height: 280 }}>
                            <canvas ref={donutRef} />
                        </div>
                    </CrudCard>
                </div>

                <CrudCard
                    title="Tingkat Koleksi"
                    subtitle={`Collection rate saat ini: ${stats.collection_rate}%`}
                >
                    <div style={{ height: 10, borderRadius: 999, background: 'var(--mhs-bg-3)', overflow: 'hidden' }}>
                        <div
                            style={{
                                width: `${Math.min(stats.collection_rate, 100)}%`,
                                height: '100%',
                                borderRadius: 999,
                                background: 'linear-gradient(90deg, var(--mhs-success), #34d399)',
                            }}
                        />
                    </div>
                </CrudCard>

                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14, marginTop: 14 }}>
                    <CrudCard title="Rekap Per Kelas">
                        <CrudTableShell>
                            <table className="mcr-table">
                                <thead>
                                    <tr>
                                        <th>Kelas</th>
                                        <th style={{ textAlign: 'right' }}>Tagihan</th>
                                        <th style={{ textAlign: 'right' }}>Terbayar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {byClass.map((item) => (
                                        <tr key={item.id}>
                                            <td>{item.name}</td>
                                            <td style={{ textAlign: 'right' }}>{formatCurrency(item.total_invoiced)}</td>
                                            <td style={{ textAlign: 'right' }}>{formatCurrency(item.total_paid)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CrudTableShell>
                    </CrudCard>

                    <CrudCard title="Pembayaran Terbaru">
                        <CrudTableShell>
                            <table className="mcr-table">
                                <thead>
                                    <tr>
                                        <th>No. Bayar</th>
                                        <th>Santri</th>
                                        <th style={{ textAlign: 'right' }}>Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentPayments.map((item) => (
                                        <tr key={item.id}>
                                            <td><code>{item.payment_number}</code></td>
                                            <td>{item.invoice?.student?.full_name ?? '-'}</td>
                                            <td style={{ textAlign: 'right' }}>{formatCurrency(item.amount)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CrudTableShell>
                    </CrudCard>
                </div>
            </div>
        </AppLayout>
    );
}
