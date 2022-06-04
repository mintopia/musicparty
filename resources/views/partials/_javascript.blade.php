<script>
    window.echoConfig = {
        key: '{{ env('MIX_PUSHER_APP_KEY') }}',
        cluster: '{{ env('MIX_PUSHER_APP_CLUSTER') }}',
        wsHost: '{{ env('MIX_PUSHER_CLIENT_HOSTNAME') }}',
        wsPort: '{{ env('MIX_PUSHER_CLIENT_PORT') }}'
    };
</script>
