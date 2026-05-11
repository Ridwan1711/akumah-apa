<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function privacyPolicy(): Response
    {
        return Inertia::render('legal/privacy-policy', [
            'lastUpdated' => env('PRIVACY_POLICY_LAST_UPDATED', '11 Mei 2026'),
            'privacyContactEmail' => env('PRIVACY_CONTACT_EMAIL'),
        ]);
    }
}
