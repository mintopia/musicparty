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
        </div>
    </div>
    <div class="col-lg-6 offset-lg-3">
        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea rows="3" class="form-control @error('description') is-invalid @enderror" name="description" data-toggle="markdown-editor">{{ old('description', isset($game) ? $game->description : '') }}</textarea>
        </div>
        @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-lg-2 offset-lg-3">
        <div class="form-group">
            <label class="form-label" for="max_players">Maximum Players</label>
            <input class="form-control @error('max_players') is-invalid @enderror" type="max_players" name="max_players" placeholder="Max Players" value="{{ old('max_players') }}" />
            @error('max_players')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>
