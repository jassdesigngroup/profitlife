@props(['name', 'bag' => 'default'])
@error($name, $bag)
    <p {{ $attributes->merge(['class' => 'mt-1.5 text-sm font-medium text-danger-700']) }} role="alert">{{ $message }}</p>
@enderror
