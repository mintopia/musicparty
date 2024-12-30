{{ csrf_field() }}
<div class="card-body">
    <div class="mb-3">
        <label class="form-label required">Name</label>
        <div>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                   placeholder="Name" value="{{ old('name', $party->name ?? '') }}">
            <small class="form-hint">Name for the party</small>
            @error('name')
            <p class="invalid-feedback">{{ $message }}</p>
            @enderror
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label required">Backup Playlist</label>
        <div>
            <select id="backup_playlist_id" name="backup_playlist_id" class="form-control @error('backup_playlist_id') is-invalid @enderror">
                <option>Select...</option>
                @foreach($playlists as $playlist)
                    <option
                        value="{{ $playlist->id }}"
                        @if(old('backup_playlist_id', $party->backup_playlist_id) == $playlist->id) selected @endif
                    >{{ $playlist->name }}</option>
                @endforeach
                <option value="other" @if(!in_array(old('backup_playlist_id', $party->backup_playlist_id), collect($playlists)->pluck('id')->toArray())) selected @endif>Other Playlist</option>
            </select>
            <input
                type="text"
                id="custom_backup_playlist_id"
                name="custom_backup_playlist_id"
                class="
                                form-control
                                mt-2
                                @error('custom_backup_playlist_id') is-invalid @enderror
                                @if(in_array(old('backup_playlist_id', $party->backup_playlist_id), collect($playlists)->pluck('id')->toArray())) d-none @endif
                            "
                placeholder="Spotify Playlist ID"
                value="{{ old('custom_backup_playlist_id', in_array($party->backup_playlist_id, array_keys($playlists)) ? '' : $party->backup_playlist_id) }}"
            >
            <small class="form-hint">The backup playlist is used to fill the queue if there are no requests</small>
            @error('backup_playlist_id')
            <p class="invalid-feedback">{{ $message }}</p>
            @enderror
        </div>
    </div>
    <div class="mb-3">
        <label class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="active" value="1"
                   @if(old('active', $party->active)) checked @endif>
            Process the queue and requests
        </label>
        <label class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="allow_requests" value="1"
                   @if(old('allow_requests', $party->allow_requests)) checked @endif>
            Allow new songs to be added to the queue
        </label>

        <label class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="downvotes" value="1"
                   @if(old('downvotes', $party->downvotes)) checked @endif>
            Allow songs to be downvoted
        </label>
        <label class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="weighted" value="1"
                   @if(old('weighted', $party->weighted)) checked @endif>
            Treat the queue as a raffle
        </label>

        <label class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="show_qrcode" value="1"
                   @if(old('show_qrcode', $party->show_qrcode)) checked @endif>
            Show QR Code on TV view
        </label>

        <h2 class="mt-4">Restrictions</h2>

        <label class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="explicit" value="1"
                   @if(old('explicit', $party->explicit)) checked @endif>
            Allow explicit songs
        </label>

        <div class="mb-3 row">
            <div class="mb-3 col-md-6">
                <label class="form-label">Maximum Song Length</label>
                <div class="input-group">
                    <input type="text" name="max_song_length" class="form-control @error('max_song_length') is-invalid @enderror"
                           value="{{ old('max_song_length', $party->max_song_length ?? '') }}">
                    <span class="input-group-text">seconds</span>
                </div>
                <small class="form-hint">The maximum length a requested song can be</small>
                @error('max_song_length')
                <p class="invalid-feedback">{{ $message }}</p>
                @enderror
            </div>
            <div class="mb-3 col-md-6">
                <label class="form-label">Song Cooldown</label>
                <div class="input-group">
                    <input type="text" name="no_repeat_interval" class="form-control @error('no_repeat_interval') is-invalid @enderror"
                           value="{{ old('no_repeat_interval', $party->no_repeat_interval ?? '') }}">
                    <span class="input-group-text">seconds</span>
                </div>
                <small class="form-hint">The amount of time after a song is queued before it can be requested again</small>
                @error('max_song_length')
                <p class="invalid-feedback">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-3 col-md-6">
                <label class="form-label">Max Downvotes</label>
                <div class="input-group">
                    <input type="text" name="downvotes_per_hour" class="form-control @error('downvotes_per_hour') is-invalid @enderror"
                           value="{{ old('downvotes_per_hour', $party->downvotes_per_hour ?? '') }}">
                    <span class="input-group-text">per hour</span>
                </div>
                <small class="form-hint">The maximum number of times a user can downvote in a 1 hour period</small>
                @error('downvotes_per_hour')
                <p class="invalid-feedback">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <h2 class="mt-4">Advanced Options</h2>

        <p>
            You might need these options if you're playing the Party on something that isn't a computer.
        </p>

        <label class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="poll" value="1"
                   @if(old('poll', $party->poll)) checked @endif>
            Poll the Spotify API for updates
        </label>
        <label class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="queue" value="1"
                   @if(old('queue', $party->queue)) checked @endif>
            Add upcoming song to the Spotify queue as well as the playlist
        </label>
        <label class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="force" value="1"
                   @if(old('force', $party->force)) checked @endif>
            Force playback on the selected device
        </label>
    </div>
    <div class="mb-3">
        <label class="form-label">Playback Device</label>
        <div>
            <select id="device_id" name="device_id" class="form-control @error('device_id') is-invalid @enderror">
                <option>None</option>
                @foreach($devices as $device)
                    <option
                        value="{{ $device->id }}"
                        @if(old('device_id', $party->device_id) == $device->id) selected @endif
                    >{{ $device->name }}</option>
                @endforeach
            </select>
            <small class="form-hint">The device to play the party on, if force is enabled</small>
            @error('device_id')
            <p class="invalid-feedback">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>


@push('footer')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('backup_playlist_id').addEventListener('change', (event) => {
                const customInput = document.getElementById('custom_backup_playlist_id');
                if (event.target.value === 'other') {
                    customInput.classList.remove('d-none');
                } else {
                    customInput.classList.add('d-none');
                }
            })
        });
    </script>
@endpush
