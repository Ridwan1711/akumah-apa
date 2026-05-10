<?php

namespace App\Imports;

use App\Models\DormBuilding;
use App\Models\DormRoom;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DormGedungKamarSheetImport implements ToCollection, WithHeadingRow
{
    public function __construct(
        private readonly string $strategy,
        private readonly DormImportResult $result,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $buildingName = trim((string) ($row['building_name'] ?? ''));
            $roomNumber = trim((string) ($row['room_number'] ?? ''));

            if ($buildingName === '' && $roomNumber === '') {
                continue;
            }

            if ($buildingName === '' || $roomNumber === '') {
                $this->result->failed++;
                $this->result->errors[] = ['row' => $rowNumber, 'message' => 'Gedung_Kamar: building_name dan room_number wajib diisi.'];

                continue;
            }

            $description = trim((string) ($row['building_description'] ?? ''));
            $capacity = (int) ($row['capacity'] ?? 0);
            if ($capacity < 1) {
                $capacity = 4;
            }
            $floorRaw = $row['floor'] ?? null;
            $floor = $floorRaw === null || $floorRaw === '' ? null : (int) $floorRaw;

            try {
                DB::transaction(function () use ($buildingName, $description, $roomNumber, $capacity, $floor): void {
                    $building = DormBuilding::query()->firstOrCreate(
                        ['name' => $buildingName],
                        ['description' => $description !== '' ? $description : null],
                    );

                    if ($building->wasRecentlyCreated) {
                        $this->result->created++;
                    } elseif ($this->strategy === 'update' && $description !== '') {
                        $building->update(['description' => $description]);
                        $this->result->updated++;
                    }

                    $room = DormRoom::query()
                        ->where('building_id', $building->id)
                        ->where('room_number', $roomNumber)
                        ->first();

                    if ($room !== null) {
                        if ($this->strategy === 'update') {
                            $room->update([
                                'capacity' => $capacity,
                                'floor' => $floor,
                            ]);
                            $this->result->updated++;
                        } else {
                            $this->result->skipped++;
                        }
                    } else {
                        DormRoom::query()->create([
                            'building_id' => $building->id,
                            'room_number' => $roomNumber,
                            'capacity' => $capacity,
                            'floor' => $floor,
                        ]);
                        $this->result->created++;
                    }

                    $this->result->processed++;
                });
            } catch (\Throwable $e) {
                $this->result->failed++;
                $this->result->errors[] = ['row' => $rowNumber, 'message' => 'Gedung_Kamar: '.$e->getMessage()];
            }
        }
    }
}
