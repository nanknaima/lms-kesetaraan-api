<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; }
        .kop { text-align: center; border-bottom: 2px solid #0f766e; padding-bottom: 8px; margin-bottom: 16px; }
        .kop img { height: 50px; }
        .kop h2 { margin: 4px 0 0; color: #0f766e; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background: #0f766e; color: #fff; }
        tr:nth-child(even) { background: #f8fafc; }
        .footer { margin-top: 40px; display: flex; justify-content: space-between; font-size: 11px; }
        .ttd { margin-top: 60px; }
    </style>
</head>
<body>
    <div class="kop">
        {{-- Ganti dengan logo sekolah kesetaraan --}}
        <h2>LMS Sekolah Kesetaraan Paket A / B / C</h2>
        <div>{{ $judul }}</div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($kolom as $k)
                    <th>{{ $k }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $val)
                        <td>{{ $val }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($kolom) }}">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <div>Dicetak pada: {{ $tanggalCetak }}</div>
        <div class="ttd">
            Mengetahui,<br><br><br><br>
            (....................................)
        </div>
    </div>
</body>
</html>
