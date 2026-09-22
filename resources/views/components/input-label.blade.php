@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-[15px] font-bold text-ink']) }}>
    {{ $value ?? $slot }}
</label>
