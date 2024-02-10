@extends('layouts.app', [
    'activenav' => 'party.create',
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item active"><a href="{{ route('parties.create') }}">Create Party</a></li>
@endsection

@section('content')
    <div class="page-header mt-0">
        <h1>Create Party</h1>
    </div>

    <div class="col-md-6 offset-md-3">
        <form action="{{ route('parties.store') }}" method="post" class="card">
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
                        <input type="checkbox" class="form-check-input" name="allow_requests" value="1"
                               @if(old('allow_requests', $party->allow_requests)) checked @endif>
                        Allow new songs to be added to the queue
                    </label>
                    <label class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="explicit" value="1"
                               @if(old('explicit', $party->explicit)) checked @endif>
                        Allow explicit songs
                    </label>
                    <label class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="downvotes" value="1"
                               @if(old('downvotes', $party->downvotes)) checked @endif>
                        Allow songs to be downvoted
                    </label>
                    <label class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="process_requests" value="1"
                               @if(old('process_requests', $party->process_requests)) checked @endif>
                        Process the queue
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
                <div class="mb-3">
                    <label class="form-label">Maximum Song Length</label>
                    <div>
                        <input type="text" name="max_song_length" class="w-25 form-control @error('max_song_length') is-invalid @enderror"
                               value="{{ old('max_song_length', $party->max_song_length ?? '') }}">
                        <small class="form-hint">The maximum length a requested song can be (in seconds)</small>
                        @error('max_song_length')
                        <p class="invalid-feedback">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
            <div class="card-footer text-end">
                <div class="d-flex">
                    <a href="{{ route('home') }}" class="btn btn-link">Cancel</a>
                    <button type="submit" class="btn btn-primary ms-auto">Save</button>
                </div>
            </div>
        </form>
    </div>
@endsection

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
