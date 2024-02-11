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
            <textarea class="form-control text-monospace @error('css') is-invalid @enderror" placeholder="Custom CSS Code" name="css" rows="5" @if($theme->readonly) disabled="" @endif>{{ old('css', $theme->css ?? '') }}</textarea>

            @error('css')
            <p class="invalid-feedback">{{ $message }}</p>
            @enderror
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <h3 class="my-4">Site Colours</h3>
            <div class="mb-3">
                <label class="form-label required">Primary</label>
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-prepend">
                            <input type="color" class="form-control form-control-color" value="{{ old('primary', $theme->primary ?? '') }}" title="Primary Colour" @if($theme->readonly) disabled="" @endif>
                        </span>
                        <input type="text" name="primary" class="form-control @error('primary') is-invalid @enderror"
                               placeholder="Colour" value="{{ old('primary', $theme->primary ?? '') }}" @if($theme->readonly) disabled="" @endif>
                    </div>
                </div>
                <small class="form-hint">The primary colour used for links, buttons, etc.</small>
                @error('primary')
                    <p class="invalid-feedback">{{ $message }}</p>
                @enderror
            </div>
            <div class="mb-3">
                <label class="form-label required">Navigation Background Colour</label>
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-prepend">
                            <input type="color" class="form-control form-control-color" value="{{ old('nav_background', $theme->nav_background ?? '') }}" title="Navigation Background Colour" @if($theme->readonly) disabled="" @endif>
                        </span>
                        <input type="text" name="nav_background" class="form-control @error('nav_background') is-invalid @enderror"
                               placeholder="Colour" value="{{ old('nav_background', $theme->nav_background ?? '') }}" @if($theme->readonly) disabled="" @endif>
                    </div>
                </div>
                <small class="form-hint">The colour used for the background of the navigation menu</small>
                @error('nav_background')
                <p class="invalid-feedback">{{ $message }}</p>
                @enderror
            </div>

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
            <h3 class="my-4">Seating Plan Colours</h3>
            <div class="mb-3">
                <label class="form-label required">Available</label>
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-prepend">
                            <input type="color" class="form-control form-control-color" value="{{ old('seat_available', $theme->seat_available ?? '') }}" title="Available Seat Colour" @if($theme->readonly) disabled="" @endif>
                        </span>
                        <input type="text" name="seat_available" class="form-control @error('seat_available') is-invalid @enderror"
                               placeholder="Colour" value="{{ old('seat_available', $theme->seat_available ?? '') }}" @if($theme->readonly) disabled="" @endif>
                    </div>
                </div>
                <small class="form-hint">The colour used for available seats</small>
                @error('seat_available')
                <p class="invalid-feedback">{{ $message }}</p>
                @enderror
            </div>
            <div class="mb-3">
                <label class="form-label required">Disabled</label>
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-prepend">
                            <input type="color" class="form-control form-control-color" value="{{ old('seat_disabled', $theme->seat_disabled ?? '') }}" title="Disabled Seat Colour" @if($theme->readonly) disabled="" @endif>
                        </span>
                        <input type="text" name="seat_disabled" class="form-control @error('seat_disabled') is-invalid @enderror"
                               placeholder="Colour" value="{{ old('seat_disabled', $theme->seat_disabled ?? '') }}" @if($theme->readonly) disabled="" @endif>
                    </div>
                </div>
                <small class="form-hint">The colour used for disabled seats</small>
                @error('seat_disabled')
                <p class="invalid-feedback">{{ $message }}</p>
                @enderror
            </div>
            <div class="mb-3">
                <label class="form-label required">Taken</label>
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-prepend">
                            <input type="color" class="form-control form-control-color" value="{{ old('seat_taken', $theme->seat_taken ?? '') }}" title="Taken Seat Colour" @if($theme->readonly) disabled="" @endif>
                        </span>
                        <input type="text" name="seat_taken" class="form-control @error('seat_taken') is-invalid @enderror"
                               placeholder="Colour" value="{{ old('seat_taken', $theme->seat_taken ?? '') }}" @if($theme->readonly) disabled="" @endif>
                    </div>
                </div>
                <small class="form-hint">The colour used for taken seats</small>
                @error('seat_taken')
                <p class="invalid-feedback">{{ $message }}</p>
                @enderror
            </div>
            <div class="mb-3">
                <label class="form-label required">Clan</label>
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-prepend">
                            <input type="color" class="form-control form-control-color" value="{{ old('seat_clan', $theme->seat_clan ?? '') }}" title="Clan Seat Colour"@if($theme->readonly) disabled="" @endif>
                        </span>
                        <input type="text" name="seat_clan" class="form-control @error('seat_clan') is-invalid @enderror"
                               placeholder="Colour" value="{{ old('seat_clan', $theme->seat_clan ?? '') }}"@if($theme->readonly) disabled="" @endif>
                    </div>
                </div>
                <small class="form-hint">The colour used for seats occupied by people in the same clan as the current user</small>
                @error('seat_clan')
                <p class="invalid-feedback">{{ $message }}</p>
                @enderror
            </div>
            <div class="mb-3">
                <label class="form-label required">Selected</label>
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-prepend">
                            <input type="color" class="form-control form-control-color" value="{{ old('seat_selected', $theme->seat_selected ?? '') }}" title="Selected Seat Colour" @if($theme->readonly) disabled="" @endif>
                        </span>
                        <input type="text" name="seat_selected" class="form-control @error('seat_selected') is-invalid @enderror"
                               placeholder="Colour" value="{{ old('seat_selected', $theme->seat_selected ?? '') }}" @if($theme->readonly) disabled="" @endif>
                    </div>
                </div>
                <small class="form-hint">The colour used for the currently selected seat.</small>
                @error('seat_selected')
                <p class="invalid-feedback">{{ $message }}</p>
                @enderror
            </div>
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
