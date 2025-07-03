@extends('layouts.app', [
    'activenav' => "party:{$party->code}"
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parties.show', $party->code) }}">{{ $party->name }}</a>
        <li class="breadcrumb-item active">Player Update</li>
@endsection
@push('precontainer')
    @include('parties._player')
@endpush
@section('content')
    <div class="col-xl-8 offset-xl-2 col-lg-10 offset-lg-1">
        <next
            code="{{ $party->code }}"
            can_manage="{{ $canManage }}"
            initialstate='@json($party->getState())'>
        </next>
        <button id="togglePlay">Toggle Play</button>
    </div>
@endsection
@push('head')
    <script src="https://sdk.scdn.co/spotify-player.js"></script>
@endpush
@push('footer')
    <script>
        // Keepalive, to ensure our session doesn't expire
        setInterval(function() {
            fetch('/api/v1/ping');
        }, 60000);

        document.addEventListener("DOMContentLoaded", function() {
            let channel = 'spotifytoken.{{ $party->user->id }}';
            window.Echo.channel(channel).listen('User\\SpotifyAccessTokenUpdatedEvent', (payload) => {
                console.log(payload);
                window.accessToken = payload.accessToken;
            });
        });

        window.accessToken = '{{ $party->user->getSpotifyAccessToken() }}';
        window.currentTrack = null;

        // Spotify Player
        window.onSpotifyWebPlaybackSDKReady = () => {
            const player = new Spotify.Player({
                name: 'Music Party {{ $party->code }}',
                getOAuthToken: cb => {
                    cb(window.accessToken);
                },
                volume: 0.5
            });

            // Ready
            player.addListener('ready', ({device_id}) => {
                console.log('Ready with Device ID', device_id);
                window.deviceId = device_id;
            });

            // Not Ready
            player.addListener('not_ready', ({device_id}) => {
                console.log('Device ID has gone offline', device_id);
                document.getElementById('togglePlay').style.display = 'block';
            });

            player.addListener('initialization_error', ({message}) => {
                console.error(message);
            });

            player.addListener('authentication_error', ({message}) => {
                console.error(message);
            });

            player.addListener('account_error', ({message}) => {
                console.error(message);
            });

            player.addListener('player_state_changed', ({
                    position,
                    duration,
                    track_window: {current_track}
                }) => {
                if (window.currentTrack != current_track.id) {
                    window.currentTrack = current_track.id;
                    console.log('Track changed, updating party');
                    console.log(current_track, position, duration);
                    fetch('/webhooks/parties/{{ $party->code }}/simple', {
                        method: 'POST',
                        headers: {
                            'x-csrf-token': '{{ csrf_token() }}',
                        }
                    });
                }
            });


            player.connect();
            window.player = player;

            document.getElementById('togglePlay').onclick = function() {
                player.togglePlay();
                fetch('/api/v1/parties/{{ $party->code }}/control', {
                    method: 'POST',
                    headers: {
                        'x-csrf-token': '{{ csrf_token() }}',
                        'content-type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'play',
                        'deviceId': window.deviceId,
                    }),
                });
                document.getElementById('togglePlay').style.display = 'none';
            };
        };


    </script>
@endpush
