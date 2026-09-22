@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-semibold text-sm text-confirm']) }}>
        {{ $status }}
    </div>
@endif
