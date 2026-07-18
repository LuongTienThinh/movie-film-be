@extends('layouts.app')

@section('title', __('messages.user.edit_title'))

@section('content')
<div class="admin-form-shell">
    <div class="mb-4">
        <a class="text-white-50" href="{{ route('admin.users.index') }}">&larr; {{ __('messages.back_to_list') }}</a>
        <h2 class="mt-3 mb-1">{{ __('messages.user.edit_title') }}</h2>
        <p class="text-white-50 mb-0">{{ $user->email }}</p>
    </div>

    <form action="{{ route('admin.users.update', $user->id) }}" method="POST" class="admin-panel admin-user-form d-grid gap-3">
        @csrf
        @method('PUT')

        <div>
            <label class="form-label" for="name">{{ __('messages.user.name') }}</label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="form-label" for="email">Email</label>
            <input class="form-control @error('email') is-invalid @enderror" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="form-label" for="phone">{{ __('messages.user.phone') }}</label>
            <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $user->phone) }}">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="gender">{{ __('messages.user.gender') }}</label>
                <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender">
                    <option value="">{{ __('messages.user.not_set') }}</option>
                    <option value="male" {{ old('gender', $user->gender) === 'male' ? 'selected' : '' }}>{{ __('messages.user.gender_male') }}</option>
                    <option value="female" {{ old('gender', $user->gender) === 'female' ? 'selected' : '' }}>{{ __('messages.user.gender_female') }}</option>
                    <option value="other" {{ old('gender', $user->gender) === 'other' ? 'selected' : '' }}>{{ __('messages.user.gender_other') }}</option>
                </select>
                @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="date_of_birth">{{ __('messages.user.date_of_birth') }}</label>
                <input class="form-control @error('date_of_birth') is-invalid @enderror" id="date_of_birth" type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($user->date_of_birth)->format('Y-m-d')) }}">
                @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div>
            <label class="form-label" for="role">{{ __('messages.user.role') }}</label>
            <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                <option value="user" {{ old('role', $user->role) === 'user' ? 'selected' : '' }}>User</option>
                <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
            </select>
            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="d-flex justify-content-end gap-2 mt-2">
            <a class="btn admin-ghost-button" href="{{ route('admin.users.index') }}">{{ __('messages.cancel') }}</a>
            <button class="btn admin-primary-button" type="submit">{{ __('messages.save_changes') }}</button>
        </div>
    </form>
</div>
@endsection
