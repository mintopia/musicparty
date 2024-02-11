<div class="mb-3">
    <label
        class="form-label @if($setting->isRequired()) required @endif">{{ $setting->name }}</label>
    <div>
        <input type="text" name="{{ $setting->code }}" class="form-control @error($setting->code) is-invalid @enderror"
               value="{{ old($setting->code, $setting->value ?? '') }}">
        @if($setting->description)
            <small class="form-hint">{{ $setting->description }}</small>
        @endif
        @error($setting->code)
        <p class="invalid-feedback">{{ $message }}</p>
        @enderror
    </div>
</div>
