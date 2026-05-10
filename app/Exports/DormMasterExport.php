<?php

namespace App\Exports;

use App\Models\DormBuilding;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DormMasterExport implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'building_name',
            'building_description',
            'room_number',
            'capacity',
            'floor',
            'musyrif_name',
        ];
    }

    public function collection()
    {
        $rows = collect();
        $buildings = DormBuilding::query()
            ->with(['rooms.musyrif.user:id,name'])
            ->orderBy('name')
            ->get();

        foreach ($buildings as $building) {
            foreach ($building->rooms->sortBy('room_number') as $room) {
                $rows->push([
                    'building_name' => $building->name,
                    'building_description' => (string) ($building->description ?? ''),
                    'room_number' => $room->room_number,
                    'capacity' => $room->capacity,
                    'floor' => $room->floor ?? '',
                    'musyrif_name' => (string) ($room->musyrif?->user?->name ?? ''),
                ]);
            }
        }

        return $rows;
    }
}
