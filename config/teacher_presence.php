<?php

return [
    // Batas toleransi setelah jam selesai sesi sebelum dianggap terlewat.
    'cutoff_grace_minutes' => (int) env('TEACHER_PRESENCE_CUTOFF_GRACE_MINUTES', 20),

    // Waktu konfirmasi yang diberikan setelah sesi terlewat.
    'confirm_window_minutes' => (int) env('TEACHER_PRESENCE_CONFIRM_WINDOW_MINUTES', 180),

    // Jadwal command timeout checker.
    'timeout_check_interval_minutes' => (int) env('TEACHER_PRESENCE_TIMEOUT_CHECK_INTERVAL_MINUTES', 10),
];

