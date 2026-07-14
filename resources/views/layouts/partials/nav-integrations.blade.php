<div role="tablist" class="tabs tabs-bordered mb-5">
    <a role="tab" href="{{ route('laravel-crm.integrations.xero') }}" class="tab {{ request()->routeIs('laravel-crm.integrations.xero*') ? 'tab-active' : '' }}">Xero</a>
    @hassmsmarketingenabled
        <a role="tab" href="{{ route('laravel-crm.integrations.clicksend') }}" class="tab {{ request()->routeIs('laravel-crm.integrations.clicksend*') ? 'tab-active' : '' }}">ClickSend</a>
    @endhassmsmarketingenabled
</div>
