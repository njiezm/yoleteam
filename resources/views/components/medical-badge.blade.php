{{-- Medical certificate status of a member: icon only (compact) or chip with text. --}}
@props(['member', 'compact' => false])
@php($ok = (bool) $member->medical_certificate)
@if ($compact)
    <span {{ $attributes->class(['inline-grid place-items-center w-5 h-5 rounded-full shrink-0', 'bg-emerald-100 text-emerald-700' => $ok, 'bg-red-100 text-red-600' => ! $ok]) }}
          title="{{ $ok ? 'Certificat médical à jour' : 'Certificat médical manquant : pas à jour' }}" role="img" aria-label="{{ $ok ? 'Certificat médical à jour' : 'Certificat médical manquant' }}">
        <x-icon name="medical" class="w-3 h-3" />
    </span>
@else
    <span {{ $attributes->class(['chip', 'bg-emerald-100 text-emerald-800' => $ok, 'bg-red-100 text-red-700' => ! $ok]) }}>
        <x-icon name="medical" class="w-3.5 h-3.5" />{{ $ok ? 'Certificat médical à jour' : 'Certificat médical manquant' }}
    </span>
@endif
