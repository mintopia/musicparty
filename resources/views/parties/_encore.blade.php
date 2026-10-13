@php($reverbApp = config('reverb.apps.apps.0'))
<div class="card mt-3" id="encore-relay">
    <div class="card-header">
        <h3 class="card-title">Encore relay</h3>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">Relay URL</dt>
            <dd class="col-sm-8"><code>wss://{{ request()->getHost() }}/app/{{ $reverbApp['key'] }}</code></dd>
            <dt class="col-sm-4">Pusher protocol</dt>
            <dd class="col-sm-8">On</dd>
            <dt class="col-sm-4">Channel</dt>
            <dd class="col-sm-8"><code>private-encore.{{ $party->code }}</code></dd>
            <dt class="col-sm-4">App key</dt>
            <dd class="col-sm-8"><code>{{ $reverbApp['key'] }}</code></dd>
            @can('admin')
                <dt class="col-sm-4">App secret</dt>
                <dd class="col-sm-8"><code>{{ $reverbApp['secret'] }}</code></dd>
            @endcan
        </dl>
    </div>
</div>
