@if($tahfidzProgress->count() > 0)
<div class="section">
    <div class="section-title">Progress Tahfidz</div>
    <table>
        <thead>
            <tr>
                <th style="width: 40px;" class="text-center">Juz</th>
                <th>Surah</th>
                <th style="width: 70px;">Ayat</th>
                <th style="width: 60px;">Tipe</th>
                <th style="width: 50px;">Nilai</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tahfidzProgress as $progress)
            <tr>
                <td class="text-center">{{ $progress->juz }}</td>
                <td>{{ ($progress->surah_to && $progress->surah_to !== $progress->surah_from) ? $progress->surah_from . ' - ' . $progress->surah_to : $progress->surah_from }}</td>
                <td>{{ $progress->ayat_from }}-{{ $progress->ayat_to }}</td>
                <td>{{ ucfirst($progress->type) }}</td>
                <td>{{ $progress->grade }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
