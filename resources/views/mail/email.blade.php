@component('mail::layout')
{{-- Header --}}
@slot('header')
@component('mail::header', ['url' => config('app.url')]){{ config('app.name') }}@endcomponent
@endslot

{{-- Body --}}
{!! $content ?? null !!}

{{-- Subcopy --}}
@isset($subcopy)
@slot('subcopy')
@component('mail::subcopy')
{!! $subcopy !!}
@endcomponent
@endslot
@endisset

{{-- Footer --}}
@slot('footer')
@component('mail::footer')
© {{ date('Y') }} Techfusion Automata. @lang('All rights reserved.')
@endcomponent
@endslot
@endcomponent
