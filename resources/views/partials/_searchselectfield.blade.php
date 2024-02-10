<div class="form-group mb-3">
    <label class="form-label" for="{{ $property }}">{{ $name }}</label>
    <select class="form-select @error($property) is-invalid @enderror" name="{{ $property }}">
        <option value="" @if(($filters->{$property} ?? '') == '') selected @endif>All</option>
        @foreach($options as $option)
            <option value="{{ $option->$valueProperty }}"
                    @if(($filters->{$property} ?? '') == $option->$valueProperty) selected @endif>{{ $option->$nameProperty }}</option>
        @endforeach
    </select>
    @error($property)
    <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
