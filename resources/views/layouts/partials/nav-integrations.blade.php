@php
    $activeIntegrationTab = request()->routeIs('laravel-crm.integrations.clicksend*') ? 'clicksend' : 'xero';
    $integrationTabUrls = ['xero' => route('laravel-crm.integrations.xero')];
    if (! config('laravel-crm.modules') || (is_array(config('laravel-crm.modules')) && in_array('sms-marketing', config('laravel-crm.modules')))) {
        $integrationTabUrls['clicksend'] = route('laravel-crm.integrations.clicksend');
    }
@endphp

<x-mary-tabs
    :selected="$activeIntegrationTab"
    class="mb-5"
    :x-data="\Illuminate\Support\Js::from(['tabUrls' => $integrationTabUrls, 'active' => $activeIntegrationTab])"
    x-init="$watch('selected', v => { if (tabUrls[v] && v !== active) window.location.href = tabUrls[v] })"
>
    <x-mary-tab name="xero" label="Xero" style="padding: 0" />
    @hassmsmarketingenabled
        <x-mary-tab name="clicksend" label="ClickSend" style="padding: 0" />
    @endhassmsmarketingenabled
</x-mary-tabs>
