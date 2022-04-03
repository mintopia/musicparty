<div class="card-body">
    <div class="row">
        <div class="col-lg-6 offset-lg-3">
            <div class="form-group">
                <label class="form-label" for="name">Name</label>
                <input class="form-control @error('name') is-invalid @enderror" type="text" name="name" placeholder="Name" value="{{ old('name') }}" />
                @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="backup_playlist_id">Backup Playlist</label>
                <select name="backup_playlist_id" id="backup_playlist_id" class="form-control">
                    @foreach ($playlists as $id => $name)
                        <option value="{{ $id }}" @if(old('backup_playlist_id') == $id) selected="selected"@endif>{{ $name }}</option>
                    @endforeach
                </select>
                @error('backup_playlist_id')
                <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>
