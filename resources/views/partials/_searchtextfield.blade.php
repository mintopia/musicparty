<div class="form-group mb-3">
    <label class="form-label" for="{{ $property }}">{{ $name }}</label>
    <input class="form-control @error($property) is-invalid @enderror" type="text" name="{{ $property }}"
           placeholder="{{ $name }}" value="{{ $filters->$property ?? '' }}"/>
    @error($property)
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
