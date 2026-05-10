<?php

namespace App\Imports;

final class DormImportResult
{
    public int $processed = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public int $failed = 0;

    /** @var list<array{row: int|string, message: string}> */
    public array $errors = [];
}
