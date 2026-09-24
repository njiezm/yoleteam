@props(['status'])
@if ($status)
    <span class="chip" style="background: {{ $status->background() }}; color: {{ $status->textColor() }}">{{ $status->label() }}</span>
@else
    <span class="chip bg-slate-100 text-slate-500">Non pointé</span>
@endif
