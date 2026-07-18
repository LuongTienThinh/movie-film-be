@extends('layouts.app')

@section('title', __('messages.user.management'))

@section('content')
<div class="d-flex flex-column gap-4">
    <div>
        <h2 class="mb-1">{{ __('messages.user.management') }}</h2>
        <p class="text-white-50 mb-0">{{ __('messages.user.management_description') }}</p>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="admin-search-form align-items-center">
        <input type="search" name="search" value="{{ $search }}" placeholder="{{ __('messages.placeholder.search') }}">
        <select name="role" class="admin-select-field">
            <option value="">{{ __('messages.user.all_roles') }}</option>
            <option value="admin" {{ $role === 'admin' ? 'selected' : '' }}>Admin</option>
            <option value="user" {{ $role === 'user' ? 'selected' : '' }}>User</option>
        </select>
        <button type="submit" class="btn admin-secondary-button">{{ __('messages.search') }}</button>
        @if($search !== '' || $role !== '')
            <a class="btn admin-ghost-button" href="{{ route('admin.users.index') }}">{{ __('messages.clear_filter') }}</a>
        @endif
    </form>

    <section class="admin-panel table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-start">{{ __('messages.user.name') }}</th>
                    <th class="text-start">Email</th>
                    <th>{{ __('messages.user.phone') }}</th>
                    <th>{{ __('messages.user.role') }}</th>
                    <th>{{ __('messages.user.joined_at') }}</th>
                    <th>{{ __('messages.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td class="text-start fw-semibold">{{ $user->name }}</td>
                        <td class="text-start">{{ $user->email }}</td>
                        <td>{{ $user->phone ?: '-' }}</td>
                        <td><span class="admin-role-badge admin-role-badge--{{ $user->role }}">{{ ucfirst($user->role) }}</span></td>
                        <td>{{ optional($user->created_at)->format('d/m/Y') }}</td>
                        <td>
                            <a class="icon-action mx-auto" href="{{ route('admin.users.edit', $user->id) }}" aria-label="{{ __('messages.edit') }}">
                                @include('icons.edit')
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-5 text-center text-white-50">{{ __('messages.user.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{ $users->links('pagination::bootstrap-5') }}
</div>
@endsection
