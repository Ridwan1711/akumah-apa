import { Head, Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import TextLink from '@/components/text-link';
import { home, login } from '@/routes';

type Props = {
    lastUpdated: string;
    privacyContactEmail?: string | null;
};

export default function PrivacyPolicy({ lastUpdated, privacyContactEmail }: Props) {
    return (
        <div className="min-h-svh bg-background">
            <Head title="Kebijakan privasi" />

            <header className="border-b border-border bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/80">
                <div className="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 font-medium text-foreground"
                    >
                        <AppLogoIcon className="size-8 shrink-0 fill-current" />
                        <span className="sr-only">Beranda</span>
                    </Link>
                    <TextLink href={login.url()} className="text-sm">
                        Kembali ke login
                    </TextLink>
                </div>
            </header>

            <main className="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-10">
                <article className="space-y-8 text-sm leading-relaxed text-foreground">
                    <div className="space-y-2">
                        <h1 className="text-balance text-2xl font-semibold tracking-tight">
                            Kebijakan privasi aplikasi SIAKAD Manhood
                        </h1>
                        <p className="text-muted-foreground">
                            Terakhir diperbarui: {lastUpdated}
                        </p>
                    </div>

                    <section className="space-y-3" aria-labelledby="scope-heading">
                        <h2 id="scope-heading" className="text-base font-semibold">
                            Ruang lingkup dan penggunaan yang diperbolehkan
                        </h2>
                        <p>
                            Aplikasi ini merupakan sistem informasi akademik dan administrasi
                            untuk warga <strong>Pondok Pesantren Manarul Huda</strong> (santri,
                            guru, wali, serta staf yang ditunjuk). Pengunduhan, pemasangan, dan
                            penggunaan aplikasi <strong>tidak ditujukan untuk publik umum</strong>{' '}
                            di luar lingkungan tersebut dan tidak disarankan tanpa izin resmi
                            dari pengelola pesantren.
                        </p>
                        <p>
                            Listing di Google Play dapat memudahkan distribusi kepada anggota;
                            batasan organisasi tetap mengikuti kebijakan internal pesantren dan
                            akun yang diberikan oleh administrator.
                        </p>
                    </section>

                    <section className="space-y-3" aria-labelledby="controller-heading">
                        <h2 id="controller-heading" className="text-base font-semibold">
                            Pengendali data
                        </h2>
                        <p>
                            Pengelola data pribadi dalam konteks operasional sistem adalah{' '}
                            <strong>Pondok Pesantren Manarul Huda</strong> (sesuaikan nama badan
                            hukum resmi, alamat, dan kontak di dokumentasi administratif Anda).
                            Untuk pertanyaan privasi teknis, pengguna dapat menghubungi
                            administrator SIAKAD melalui kanal resmi pesantren.
                        </p>
                        {privacyContactEmail ? (
                            <p>
                                Email kontak privasi (opsional):{' '}
                                <a
                                    className="font-medium text-foreground underline underline-offset-4"
                                    href={`mailto:${privacyContactEmail}`}
                                >
                                    {privacyContactEmail}
                                </a>
                            </p>
                        ) : null}
                    </section>

                    <section className="space-y-3" aria-labelledby="data-heading">
                        <h2 id="data-heading" className="text-base font-semibold">
                            Data yang dapat diproses
                        </h2>
                        <ul className="list-disc space-y-2 pl-5">
                            <li>
                                Data akun dan autentikasi (misalnya nama pengguna, nama tampilan,
                                peran, serta data yang diperlukan untuk keamanan sesi).
                            </li>
                            <li>
                                Data akademik dan administrasi sesuai modul SIAKAD (jadwal,
                                kehadiran, penilaian, rapor, administrasi asrama, izin keluar,
                                dan lain-lain sebagaimana tersedia di sistem).
                            </li>
                            <li>
                                Data keuangan/tagihan dan bukti pembayaran sejauh dimasukkan ke
                                sistem oleh pengguna yang berwenang.
                            </li>
                            <li>
                                Lokasi perangkat (koordinat perkiraan atau presisi) bila
                                pengguna memberikan izin dan fitur memerlukannya untuk operasional
                                (misalnya metadata kehadiran atau pemantauan sesuai kebijakan
                                institusi).
                            </li>
                            <li>
                                Token push notification (Firebase Cloud Messaging) untuk
                                mengirimkan pemberitahuan ke perangkat Anda.
                            </li>
                            <li>
                                Foto profil atau berkas unggahan lain yang Anda atau admin unggah
                                ke dalam sistem.
                            </li>
                        </ul>
                    </section>

                    <section className="space-y-3" aria-labelledby="purpose-heading">
                        <h2 id="purpose-heading" className="text-base font-semibold">
                            Tujuan pemrosesan
                        </h2>
                        <p>
                            Data diproses untuk menyediakan layanan SIAKAD: pembelajaran,
                            administrasi pesantren, komunikasi resmi, keamanan akun, serta
                            kepatuhan terhadap ketentuan internal institusi. Pemrosesan didasarkan
                            pada kebutuhan operasional warga pesantren yang menggunakan layanan
                            ini.
                        </p>
                    </section>

                    <section className="space-y-3" aria-labelledby="third-heading">
                        <h2 id="third-heading" className="text-base font-semibold">
                            Layanan pihak ketiga
                        </h2>
                        <p>
                            Untuk notifikasi push, aplikasi memakai{' '}
                            <strong>Google Firebase Cloud Messaging (FCM)</strong>. Google
                            bertindak sebagai penyedia infrastruktur pengiriman pesan; konten
                            notifikasi dan kebijakan retensi mengikuti pengaturan aplikasi dan
                            server SIAKAD Anda.
                        </p>
                    </section>

                    <section className="space-y-3" aria-labelledby="retention-heading">
                        <h2 id="retention-heading" className="text-base font-semibold">
                            Penyimpanan dan retensi
                        </h2>
                        <p>
                            Data disimpan di server yang mengoperasikan backend SIAKAD selama
                            diperlukan untuk keperluan akademik, administrasi, audit, dan
                            kewajiban hukum yang berlaku. Penghapusan atau anonimisasi mengikuti
                            kebijakan retensi internal institusi dan prosedur administrator.
                        </p>
                    </section>

                    <section className="space-y-3" aria-labelledby="rights-heading">
                        <h2 id="rights-heading" className="text-base font-semibold">
                            Hak pengguna
                        </h2>
                        <p>
                            Anda dapat meminta klarifikasi, koreksi data, atau pembatasan
                            pemrosesan tertentu melalui administrator SIAKAD sesuai prosedur
                            pesantren. Anda dapat menarik izin lokasi atau notifikasi dari
                            pengaturan perangkat; beberapa fitur mungkin tidak berfungsi penuh
                            setelah izin dicabut.
                        </p>
                    </section>

                    <section className="space-y-3" aria-labelledby="changes-heading">
                        <h2 id="changes-heading" className="text-base font-semibold">
                            Perubahan kebijakan
                        </h2>
                        <p>
                            Kebijakan ini dapat diperbarui sewaktu-waktu. Tanggal perubahan
                            dicantumkan di bagian atas halaman. Penggunaan berkelanjutan setelah
                            pembaruan berarti Anda memahami versi terbaru.
                        </p>
                    </section>

                    <section className="space-y-4" aria-labelledby="android-heading">
                        <h2 id="android-heading" className="text-base font-semibold">
                            Izin aplikasi Android (ringkasan untuk Play Store / Data safety)
                        </h2>
                        <p className="text-muted-foreground">
                            Tabel berikut menjelaskan izin yang dideklarasikan pada build
                            Android aplikasi beserta tujuan operasionalnya.
                        </p>
                        <div className="overflow-x-auto rounded-md border border-border">
                            <table className="w-full min-w-lg border-collapse text-left text-sm">
                                <thead>
                                    <tr className="border-b border-border bg-muted/50">
                                        <th className="px-3 py-2 font-semibold">Izin</th>
                                        <th className="px-3 py-2 font-semibold">Tujuan</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    <tr>
                                        <td className="px-3 py-2 align-top font-mono text-xs">
                                            INTERNET
                                        </td>
                                        <td className="px-3 py-2">
                                            Berkomunikasi dengan server API SIAKAD (login, data
                                            akademik, dan fitur lain yang memerlukan jaringan).
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="px-3 py-2 align-top font-mono text-xs">
                                            POST_NOTIFICATIONS
                                        </td>
                                        <td className="px-3 py-2">
                                            Menampilkan notifikasi push (FCM) dan notifikasi lokal
                                            pada Android 13 ke atas.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="px-3 py-2 align-top font-mono text-xs">
                                            USE_BIOMETRIC
                                        </td>
                                        <td className="px-3 py-2">
                                            Opsional: membuka atau mengamankan akses cepat dengan
                                            biometrik perangkat (sidik jari / wajah) tanpa
                                            menggantikan autentikasi server.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="px-3 py-2 align-top font-mono text-xs">
                                            ACCESS_FINE_LOCATION / ACCESS_COARSE_LOCATION
                                        </td>
                                        <td className="px-3 py-2">
                                            Mengambil lokasi perangkat bila pengguna memberi izin,
                                            hanya saat aplikasi ditampilkan di layar (foreground),
                                            terkait kehadiran/jadwal guru dan pemantauan sesuai
                                            kebijakan institusi. Lokasi tidak dikumpulkan saat
                                            aplikasi berjalan di latar belakang tanpa tampil.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="px-3 py-2 align-top font-mono text-xs">
                                            SYSTEM_ALERT_WINDOW
                                        </td>
                                        <td className="px-3 py-2">
                                            Menampilkan pengingat overlay di atas aplikasi lain
                                            untuk kehadiran mengajar, setelah pengguna memberikan
                                            izin tampilan di atas aplikasi lain.
                                        </td>
                                    </tr>
                                    <tr>
                                        <td className="px-3 py-2 align-top font-mono text-xs">
                                            FOREGROUND_SERVICE / FOREGROUND_SERVICE_SPECIAL_USE
                                        </td>
                                        <td className="px-3 py-2">
                                            Mendukung layanan foreground untuk fitur overlay
                                            pengingat kehadiran (sub-tipe khusus yang dinyatakan pada
                                            manifest aplikasi).
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </article>
            </main>
        </div>
    );
}
