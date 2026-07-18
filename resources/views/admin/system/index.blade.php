@extends('layouts.app')

@section('title', __('messages.general.information'))

@section('content')
<div class="d-flex flex-column gap-4">
    <div>
        <h2 class="mb-1">{{ __('messages.general.information') }}</h2>
        <p class="text-white-50 mb-0">Thông tin runtime phục vụ kiểm tra môi trường triển khai.</p>
    </div>

    <section class="admin-panel">
        <dl class="system-information mb-0">
            @foreach($information as $key => $value)
                <div>
                    <dt>{{ $key }}</dt>
                    <dd>{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</div>
@endsection
