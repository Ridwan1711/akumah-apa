<?php

namespace Database\Seeders;

use App\Models\WaGatewaySession;
use Illuminate\Database\Seeder;

class WaGatewaySessionSeeder extends Seeder
{
    public function run(): void
    {
        $sessions = [
            [
                'slug' => 'pesantren',
                'label' => 'WA Utama Pesantren',
                'description' => 'OTP login, pengumuman resmi, fallback default.',
                'sort_order' => 1,
            ],
            [
                'slug' => 'bendahara',
                'label' => 'WA Bendahara',
                'description' => 'Tagihan, reminder invoice, pembayaran terverifikasi.',
                'sort_order' => 2,
            ],
            [
                'slug' => 'pendidikan',
                'label' => 'WA Pendidikan',
                'description' => 'Nilai, jadwal, rapor, absensi.',
                'sort_order' => 3,
            ],
        ];

        foreach ($sessions as $session) {
            WaGatewaySession::query()->updateOrCreate(
                ['slug' => $session['slug']],
                [
                    'label' => $session['label'],
                    'description' => $session['description'],
                    'sort_order' => $session['sort_order'],
                    'status' => WaGatewaySession::STATUS_DISCONNECTED,
                    'is_enabled' => true,
                ],
            );
        }
    }
}
