@if($grades->count() > 0)
<div class="section">
    <div class="section-title">Nilai Kitab</div>
    <table>
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">No</th>
                <th>Mata Pelajaran</th>
                <th style="width: 90px;" class="text-center">Komponen</th>
                <th style="width: 60px;" class="text-center">Nilai</th>
                <th style="width: 50px;" class="text-center">Huruf</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($grades as $i => $grade)
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>{{ $grade->subject->name ?? '-' }}</td>
                <td class="text-center text-xs">{{ $grade->component->name ?? '-' }}</td>
                <td class="text-center text-bold">{{ $grade->score }}</td>
                <td class="text-center">{{ \App\Models\Diniyyah\Score::computeGradeLetter((float) $grade->score) }}</td>
                <td>-</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="summary-box">
        <strong>Rata-rata Nilai:</strong> {{ $avgScore ?? '-' }}
    </div>
</div>
@endif
