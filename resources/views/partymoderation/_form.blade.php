{{ csrf_field() }}
<div class="card-body">
    <div class="mb-3 col-md-4">
        <label class="form-label required">Type</label>
        <div>
            <select id="type" name="type" class="form-control @error('type') is-invalid @enderror">
                <option>Select...</option>
                @foreach($types as $name => $text)
                    <option
                        value="{{ $name }}"
                        @if(old('type', $moderation->type->name ?? null) == $name) selected @endif
                    >{{ $text }}</option>
                @endforeach
            </select>
            <small class="form-hint">The type of filter to create</small>
            @error('type')
            <p class="invalid-feedback">{{ $message }}</p>
            @enderror
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label required">Value</label>
        <div>
            <input type="text" name="value" class="form-control @error('value') is-invalid @enderror"
                   placeholder="Filter Text" value="{{ old('value', $moderation->value ?? '') }}">
            <small class="form-hint">The value to filter</small>
            @error('value')
            <p class="invalid-feedback">{{ $message }}</p>
            @enderror
        </div>
    </div>
    <div class="mb-3">
        <label class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="enabled" value="1"
                   @if(old('enabled', $moderation->enabled)) checked @endif>
            Enable this filter
        </label>
    </div>
    <div class="mb-3">
        <label class="form-check form-switch">
            <input type="checkbox" class="form-check-input" name="regex" value="1"
                   @if(old('regex', $moderation->regex)) checked @endif>
            Apply filter as a regular expression
        </label>
    </div>
    <div class="mb-3">
        <label class="form-label">Notes</label>
        <div>
            <input type="text" name="notes" class="form-control @error('notes') is-invalid @enderror"
                   placeholder="Notes" value="{{ old('notes', $moderation->notes ?? '') }}">
            <small class="form-hint">Notes for your own reference</small>
            @error('notes')
            <p class="invalid-feedback">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>

