<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Membres — {{ $association->name }} — YoleTeam</title>
    <link rel="icon" href="/icons/icon.svg?v=2" type="image/svg+xml">
    @fonts
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        .print-table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .print-table th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; color: #475569; border-bottom: 2px solid #0B2545; padding: 6px 6px; }
        .print-table td { border-bottom: 1px solid #E2E8F0; padding: 5px 6px; vertical-align: top; }
        .print-table tr { break-inside: avoid; }
        .print-table thead { display: table-header-group; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body class="font-sans text-navy-950 bg-white">
    <div class="max-w-[1100px] mx-auto px-6 py-6 print:p-0 print:max-w-none">
        <div class="no-print flex flex-wrap items-center justify-between gap-3 mb-6 rounded-xl bg-slate-50 p-3">
            <a href="{{ route('members.index', request()->query()) }}" class="btn-ghost btn-sm"><x-icon name="left" class="w-4 h-4" />Retour aux membres</a>
            <button type="button" onclick="window.print()" class="btn-primary btn-sm"><x-icon name="printer" class="w-4 h-4" />Imprimer / Enregistrer en PDF</button>
        </div>

        <header class="flex items-end justify-between gap-4 border-b border-slate-200 pb-3 mb-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $association->name }}</p>
                <h1 class="text-2xl font-extrabold tracking-tight">Liste des membres</h1>
                <p class="text-sm text-slate-600 mt-0.5">{{ implode(' · ', $filtersSummary) }} — {{ trans_choice(':count membre|:count membres', $members->count()) }}</p>
            </div>
            <p class="text-sm text-slate-600 text-right">Édité le {{ today()->translatedFormat('j F Y') }}<br><span class="text-xs">Présence : saison {{ today()->year }}</span></p>
        </header>

        @if ($members->isEmpty())
            <p class="text-sm text-slate-600">Aucun membre ne correspond aux filtres.</p>
        @else
            <table class="print-table">
                <thead>
                    <tr>
                        <th>Membre</th>
                        <th>Âge</th>
                        <th>Yole</th>
                        <th>Gabarit</th>
                        <th>Niveau</th>
                        <th>Postes (★ préféré)</th>
                        <th>Téléphone</th>
                        <th>E-mail</th>
                        <th class="text-right">Présence</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($members as $member)
                        @php($rate = $rates[$member->id] ?? null)
                        <tr>
                            <td>
                                <b>{{ $member->last_name }}</b> {{ $member->first_name }}
                                @if ($member->nickname)<span class="text-slate-500"> « {{ $member->nickname }} »</span>@endif
                                @unless ($member->is_active)<span class="text-slate-500"> (inactif)</span>@endunless
                            </td>
                            <td class="whitespace-nowrap">{{ $member->formattedAge() ?? '—' }}</td>
                            <td class="whitespace-nowrap">{{ $member->yoleYears() !== null ? $member->yoleYears().' an'.($member->yoleYears() > 1 ? 's' : '') : '—' }}</td>
                            <td class="whitespace-nowrap">{{ $member->weight_kg !== null ? $member->formattedWeight().' kg' : '—' }}{{ $member->height_cm ? ' · '.$member->height_cm.' cm' : '' }}</td>
                            <td class="whitespace-nowrap">{{ $member->level?->label() }}</td>
                            <td>{{ $rolesLabels[$member->id] ?: '—' }}</td>
                            <td class="whitespace-nowrap">{{ $member->phone ?? '—' }}</td>
                            <td>{{ $member->email ?? '—' }}</td>
                            <td class="text-right whitespace-nowrap">{{ $rate !== null ? $rate.' %' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <p class="mt-6 text-[10px] text-slate-400">YoleTeam · {{ $association->name }}</p>
    </div>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
