@extends('layouts.app', [
    'activenav' => "party:{$party->code}"
])

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
    <li class="breadcrumb-item"><a href="{{ route('parties.show', $party->code) }}">{{ $party->name }}</a>
    <li class="breadcrumb-item"><a href="{{ route('parties.users.index', $party->code) }}">Users</a>
    <li class="breadcrumb-item active"><a href="{{ route('parties.users.show', [$party->code, $member->id]) }}">{{ $member->user->nickname }}</a>
@endsection
@push('precontainer')
    @include('parties._player')
@endpush
@section('content')
    <div class="page-header mt-0">
        <h1>Edit {{ $member->user->nickname }}</h1>
    </div>
    <div class="col-md-6 offset-md-3">
        <form action="{{ route('parties.users.update', [$party->code, $member->id]) }}" method="post" class="card">
            {{ csrf_field() }}
            {{ method_field('PATCH') }}
            <div class="card-body">
                <div class="mb-3">
                    <h3 class="card-title">Role</h3>
                    <p class="card-subtitle">
                        A user's role determines what permissions they have for the party.
                    </p>
                    @if (Auth::user()->id === $member->user_id)
                        <div class="alert alert-warning">
                            <div class="d-flex">
                                <div class="p-2">
                                    <i class="icon ti ti-alert-triangle"></i>
                                </div>
                                <div>
                                    You are editing permissions for yourself. If you change your membership from owner, you
                                    will be unable to manage this party.
                                </div>
                            </div>
                        </div>
                    @endif
                    <div>
                        <label class="form-check">
                            <input class="form-check-input" type="radio" name="role" value="owner"
                                   @if(old('role', $member->role->code) === 'owner') checked="" @endif>
                            <span class="form-check-label">Owner</span>
                            <p class="form-hint">
                                The user will have full control of the party
                            </p>
                        </label>
                        <label class="form-check">
                            <input class="form-check-input" type="radio" name="role" value="user"
                                   @if(old('role', $member->role->code) === 'user') checked="" @endif>
                            <span class="form-check-label">User</span>
                            <p class="form-hint">
                                An ordinary user of the party, they can request songs
                            </p>
                        </label>
                        <label class="form-check">
                            <input class="form-check-input" type="radio" name="role" value="banned"
                                   @if(old('role', $member->role->code) === 'banned') checked="" @endif>
                            <span class="form-check-label">Banned</span>
                            <p class="form-hint">
                                This user will not be able to make requests
                            </p>
                        </label>
                    </div>
                </div>
                @error('role')
                <p class="invalid-feedback">{{ $message }}</p>
                @enderror
            </div>
            <div class="card-footer text-end">
                <div class="d-flex">
                    <a href="{{ route('parties.users.show', [$party->code, $member->id]) }}" class="btn btn-link">Cancel</a>
                    <button type="submit" class="btn btn-primary ms-auto">Save</button>
                </div>
            </div>
        </form>
    </div>
@endsection
