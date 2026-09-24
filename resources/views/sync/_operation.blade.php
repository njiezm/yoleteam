{{-- One-line description of a sync operation. Expects $operation and $context. --}}
@if ($operation->entity === 'attendance')
    @php
        $outing = $context['outings']->get($operation->entity_uuid);
        $member = $context['members']->get($operation->payload['member_id'] ?? 0);
    @endphp
    <p class="text-sm font-semibold truncate">{{ $member?->short_name ?? 'Membre supprimé' }} → {{ ($context['statusLabel'])($operation->payload['status'] ?? null) }}</p>
    <p class="text-xs muted truncate">Présence · {{ $outing ? $outing->title.' du '.$outing->date->translatedFormat('j M') : 'sortie supprimée' }}</p>
@else
    @php($plan = $context['plans']->get($operation->entity_uuid))
    <p class="text-sm font-semibold truncate">{{ $plan?->boat->name ?? 'Plan supprimé' }} · {{ count($operation->payload['assignments'] ?? []) }} poste(s) pourvu(s)</p>
    <p class="text-xs muted truncate">Plan d’équipage{{ $plan ? ' · '.$plan->outing->title.' du '.$plan->outing->date->translatedFormat('j M') : '' }}</p>
@endif
