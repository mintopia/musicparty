@extends('layouts.app', [
    'activenav' => 'admin',
])

@section('breadcrumbs')
    @include('admin.users._breadcrumbs')
    <li class="breadcrumb-item active"><a href="{{ route('admin.users.edit', $user->id) }}">Edit</a>
        @endsection

        @section('content')
            <div class="page-header mt-0">
                <h1>Edit {{ $user->nickname }}</h1>
            </div>

            <div class="col-md-6 offset-md-3">
                <form action="{{ route('admin.users.update', $user->id) }}" method="post" class="card">
                    {{ csrf_field() }}
                    {{ method_field('PATCH') }}
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required">Nickname</label>
                            <div>
                                <input type="text" name="nickname"
                                       class="form-control @error('nickname') is-invalid @enderror"
                                       placeholder="Nickname" value="{{ old('nickname', $user->nickname ?? '') }}">
                                <small class="form-hint">The user's nickname</small>
                                @error('nickname')
                                <p class="invalid-feedback">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Name</label>
                            <div>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                       placeholder="Name" value="{{ old('name', $user->name ?? '') }}">
                                <small class="form-hint">The user's first name and surname</small>
                                @error('name')
                                <p class="invalid-feedback">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <div class="mb-6">
                            <div class="form-label">Roles</div>
                            <div>
                                @foreach($roles as $role)
                                    <label class="form-check">
                                        <input name="roles[]" class="form-check-input" type="checkbox"
                                               value="{{ $role->code }}"
                                               @if(in_array($role->code, old('roles', $currentRoles))) checked @endif>
                                        <span class="form-check-label">{{ $role->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="terms" value="1"
                                       @if(old('terms', $user->terms_agreed_at)) checked @endif>
                                Accept Terms and Conditions
                            </label>
                            <label class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="first_login" value="1"
                                       @if(old('first_login', !$user->first_login)) checked @endif>
                                Completed Signup
                            </label>
                            <label class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" name="suspended" value="1"
                                       @if(old('suspended', $user->suspended)) checked @endif>
                                Suspended
                            </label>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <div class="d-flex">
                            <a href="{{ route('admin.users.show', $user->id) }}" class="btn btn-link">Cancel</a>
                            <button type="submit" class="btn btn-primary ms-auto">Save</button>
                        </div>
                    </div>
                </form>
            </div>
@endsection
