@if($violations->count() > 0)
<div class="section">
    <div class="section-title">Pelanggaran</div>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th style="width: 60px;">Kategori</th>
                <th style="width: 40px;" class="text-center">Poin</th>
            </tr>
        </thead>
        <tbody>
            @foreach($violations as $violation)
            <tr>
                <td>{{ $violation->date }}</td>
                <td>{{ $violation->violationType->name ?? '-' }}</td>
                <td>{{ ucfirst($violation->violationType->category ?? '-') }}</td>
                <td class="text-center">{{ $violation->violationType->points ?? 0 }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
