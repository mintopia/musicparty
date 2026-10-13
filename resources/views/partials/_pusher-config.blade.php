<script>
    window.pusherConfig = @json(app(\App\Services\RealtimeConfig::class)->forRequest(request()));
</script>
