<?php

namespace App\Http\Controllers;

use App\Models\ReportCard;
use Illuminate\View\View;

class ReportCardVerificationController extends Controller
{
    public function show(string $token): View
    {
        $reportCard = ReportCard::where('verification_token', $token)
            ->with(['student', 'semester.academicYear'])
            ->first();

        if (! $reportCard) {
            return view('raport-verify', [
                'valid' => false,
                'reportCard' => null,
            ]);
        }

        return view('raport-verify', [
            'valid' => true,
            'reportCard' => $reportCard,
        ]);
    }
}
