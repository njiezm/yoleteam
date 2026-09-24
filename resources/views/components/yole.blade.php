{{-- Top-down drawing of a yole, rendered client-side by resources/js/yole.js from App\Services\CrewPlanPresenter data. --}}
@props(['data'])
<div {{ $attributes->merge(['class' => 'aspect-[400/820]']) }} data-yole="{{ json_encode($data, JSON_UNESCAPED_UNICODE) }}"></div>
