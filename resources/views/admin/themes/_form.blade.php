<div class="card-body">
    <div class="row">
        <div class=" col-md-6 mb-3">
            <label class="form-label required">Name</label>
            <div>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                       placeholder="Name" value="{{ old('name', $theme->name ?? '') }}" @if($theme->readonly) disabled="" @endif>
                <small class="form-hint">The name of the theme. </small>
                @error('name')
                <p class="invalid-feedback">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <label class="form-check form-switch">
                <input type="checkbox" class="form-check-input" name="active" value="1"
                       @if(old('active', $theme->active)) checked @endif>
                Active
            </label>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col">
            <label class="form-label">Custom CSS</label>
            <textarea class="form-control font-monospace @error('css') is-invalid @enderror" placeholder="Custom CSS Code" name="css" rows="5" @if($theme->readonly) disabled="" @endif>{{ old('css', $theme->css ?? '') }}</textarea>

            @error('css')
            <p class="invalid-feedback">{{ $message }}</p>
            @enderror
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <h3 class="my-4">Site Colours</h3>
            @foreach(App\Models\Theme::COLOUR_TOKENS as $token => $label)
                @php($required = in_array($token, ['primary', 'nav-background'], true))
                @php($value = old("tokens.{$token}", $theme->tokens[$token] ?? ''))
                <div class="mb-3">
                    <label class="form-label @if($required) required @endif">{{ $label }} Colour</label>
                    <div class="col-md-5">
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" value="{{ $value ?: '#000000' }}" title="{{ $label }} Colour" @if($theme->readonly) disabled="" @endif>
                            <input type="text" name="tokens[{{ $token }}]" class="form-control @error("tokens.{$token}") is-invalid @enderror"
                                   placeholder="Colour" value="{{ $value }}" @if($theme->readonly) disabled="" @endif>
                        </div>
                    </div>
                    @error("tokens.{$token}")
                        <p class="invalid-feedback d-block">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="row">
                <div class="col-md-6">
                    <label class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="dark_mode" value="1"
                               @if(old('dark_mode', $theme->dark_mode)) checked @endif>
                        Dark Mode
                    </label>
                </div>
            </div>
        </div>
        <div class="col-md-6">
        </div>
    </div>
</div>

@push('footer')
    <script>

        function bindColourPickers() {
            document.querySelectorAll('input[type=color]').forEach(function(colorinput) {
                const textinput = colorinput.closest('div.input-group').querySelector('input[type=text]');
                textinput.addEventListener('keyup', (event) => {
                    colorinput.value = event.target.value;
                });
                colorinput.addEventListener('change', (event) => {
                    textinput.value = event.target.value;
                });
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            bindColourPickers();
        });
    </script>
@endpush
