@extends('layouts.app')

@section('title', __('messages.film.management.detail'))

@section('content')
<div class="d-flex flex-column gap-4">
    <div class="d-flex justify-content-between align-items-center">
        <h2>{{ __('messages.film.management.detail') }}</h2>
    </div>
    <form class="film-form" action="{{ route('admin.film.update', ['id' => $film->id]) }}" method="post" autocomplete="off" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="film-detail row">
            <div class="col-8">
                <div class="form-section">
                    <h5>{{ __('messages.film.sections.basic') }}</h5>
                    <div class="form-group row">
                        <label class="col-3 require" for="name">{{ __('messages.film.name') }}</label>
                        <input class="col-9" type="text" name="name" id="name" value="{{ $film->name }}">
                    </div>
                    <div class="form-group row">
                        <label class="col-3 require" for="origin_name">{{ __('messages.film.origin_name') }}</label>
                        <input class="col-9" type="text" name="origin_name" id="origin_name" value="{{ $film->origin_name }}">
                    </div>
                    <div class="form-group row">
                        <label class="col-3 require" for="slug">{{ __('messages.film.slug') }}</label>
                        <input class="col-9" type="text" name="slug" id="slug" value="{{ $film->slug }}" disabled>
                    </div>
                    <div class="form-group row">
                        <label class="col-3 require" for="status">{{ __('messages.film.status') }}</label>
                        <div class="col-9 shine-select" data-name="status_id">
                            <button type="button" class="shine-select-header">
                                <span class="selected-values" data-selected="{{ $film->status->id ?? '' }}">
                                    @if(isset($film->status) && $film->status->id)
                                        <span class="badge" data-value="{{ $film->status->id }}"></span>
                                    @endif
                                    <input type="text" class="search-input" autocomplete="off" placeholder="{{ __('messages.placeholder.search') }}">
                                </span>
                                @include('icons.chevron')
                            </button>
                            <div class="shine-select-body">
                                @foreach($statuses as $status)
                                    <div class="shine-select-item {{ (isset($film->status) && $film->status->id == $status->id) ? 'selected' : '' }}" data-value="{{ $status->id }}">
                                        <span>@if(\Illuminate\Support\Facades\Lang::has('messages.status.' . $status->slug)){{ __('messages.status.' . $status->slug) }}@else{{ $status->name }}@endif</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-3 require" for="type">{{ __('messages.film.type') }}</label>
                        <div class="col-9 shine-select" data-mode="single" data-name="type_id">
                            <button type="button" class="shine-select-header">
                                <span class="selected-values" data-selected="{{ $film->type->id ?? '' }}">
                                    @if(isset($film->type) && $film->type->id)
                                        <span class="badge" data-value="{{ $film->type->id }}"></span>
                                    @endif
                                    <input type="text" class="search-input" autocomplete="off" placeholder="{{ __('messages.placeholder.search') }}">
                                </span>
                                @include('icons.chevron')
                            </button>
                            <div class="shine-select-body">
                                @foreach($types as $type)
                                    <?php $label = \Illuminate\Support\Facades\Lang::has('messages.type.' . $type->slug) ? __('messages.type.' . $type->slug) : $type->name; ?>
                                    <div class="shine-select-item {{ (isset($film->type) && $film->type->id == $type->id) ? 'selected' : '' }}" data-value="{{ $type->id }}">
                                        <span>{{ $label }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-3 require" for="total_ep">{{ __('messages.film.total_ep') }}</label>
                        <input class="col-9" type="text" name="total_ep" id="total_ep" value="{{ $film->episode_total }}">
                    </div>
                    <div class="form-group row">
                        <label class="col-3 require" for="time">{{ __('messages.film.time') }}</label>
                        <input class="col-9" type="text" name="time" id="time" value="{{ $film->time }}">
                    </div>
                    <div class="form-group row">
                        <label class="col-3 require" for="countries">{{ __('messages.film.countries') }}</label>
                        <div class="col-9 shine-select" data-mode="multiple" data-name="countries[]">
                            <button type="button" class="shine-select-header">
                                <span class="selected-values" data-selected="{{ implode(',', $selectedCountries) }}">
                                    @foreach($selectedCountries as $cId)
                                        <span class="badge" data-value="{{ $cId }}"></span>
                                    @endforeach
                                    <input type="text" class="search-input" autocomplete="off" placeholder="{{ __('messages.placeholder.search') }}">
                                </span>
                                @include('icons.chevron')
                            </button>
                            <div class="shine-select-body">
                                @foreach($countries as $country)
                                    <div class="shine-select-item {{ in_array($country->id, $selectedCountries) ? 'selected' : '' }}" data-value="{{ $country->id }}">
                                        <span>{{ $country->name }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-3 require" for="year">{{ __('messages.film.year') }}</label>
                        <input class="col-9" type="text" name="year" id="year" value="{{ $film->year }}">
                    </div>
                    <div class="form-group row">
                        <label class="col-3 require" for="quality">{{ __('messages.film.quality') }}</label>
                        <div class="col-9 shine-select" data-mode="single" data-name="quality">
                            <button type="button" class="shine-select-header">
                                <span class="selected-values" data-selected="{{ $film->quality ?? '' }}">
                                    @if(isset($film->quality) && $film->quality)
                                        <span class="badge" data-value="{{ $film->quality }}"></span>
                                    @endif
                                    <input type="text" class="search-input" autocomplete="off" placeholder="{{ __('messages.placeholder.search') }}">
                                </span>
                                @include('icons.chevron')
                            </button>
                            <div class="shine-select-body">
                                <div class="shine-select-item {{ ($film->quality ?? '') == 'HD' ? 'selected' : '' }}" data-value="HD"><span>HD</span></div>
                                <div class="shine-select-item {{ ($film->quality ?? '') == 'FHD' ? 'selected' : '' }}" data-value="FHD"><span>FHD</span></div>
                                <div class="shine-select-item {{ ($film->quality ?? '') == 'CAM' ? 'selected' : '' }}" data-value="CAM"><span>CAM</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-100">
                <div class="section-split">@include('icons.split')</div>
            </div>
            <div class="col-4">
                <div class="form-section">
                    <h5>{{ __('messages.film.sections.media') }}</h5>
                    <?php
                        $posterUrl = '';
                        if (!empty($film->poster_url)) $posterUrl = $film->poster_url;
                        elseif (!empty($film->thumbnail_url)) $posterUrl = $film->thumbnail_url;
                    ?>

                    <div class="file-uploader poster-uploader">
                        @if($posterUrl)
                            <input type="hidden" name="remove_poster" value="0">
                            <div class="file-preview">
                                <img src="{{ $posterUrl }}" alt="poster">
                            </div>
                            <div class="d-flex gap-2">
                                <label class="file-change">{{ __('messages.change') }}
                                    <input type="file" name="poster" class="file-input" accept="image/*">
                                </label>
                            </div>
                        @else
                            <input type="hidden" name="remove_poster" value="0">
                            <label class="file-placeholder">
                                <span class="plus">+</span>
                                <input type="file" name="poster" class="file-input" accept="image/*">
                            </label>
                            <div class="file-preview" style="display:none">
                                <img src="" alt="poster preview">
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 file-uploader thumbnail-uploader">
                        @if(!empty($film->thumbnail_url))
                            <input type="hidden" name="remove_thumbnail" value="0">
                            <div class="file-preview">
                                <img src="{{ $film->thumbnail_url }}" alt="thumbnail">
                            </div>
                            <div class="d-flex gap-2">
                                <label class="file-change">{{ __('messages.change') }}
                                    <input type="file" name="thumbnail" class="file-input" accept="image/*">
                                </label>
                            </div>
                        @else
                            <input type="hidden" name="remove_thumbnail" value="0">
                            <label class="file-placeholder small">
                                <span class="plus">+</span>
                                <input type="file" name="thumbnail" class="file-input" accept="image/*">
                            </label>
                            <div class="file-preview" style="display:none">
                                <img src="" alt="thumbnail preview">
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="section-split">@include('icons.split')</div>

        <div class="form-section">
            <h5>{{ __('messages.film.sections.description') }}</h5>
            <div class="form-group row">
                <label class="col-3" for="description">{{ __('messages.film.description') }}</label>
                <div class="col-9">
                    <textarea name="description" id="description" rows="6">{{ old('description', $film->description) }}</textarea>
                </div>
            </div>
        </div>

        <div class="form-section">
            <h5>{{ __('messages.genres') }}</h5>
            <div class="form-group row">
                <label class="col-3" for="genres"></label>
                <div class="col-9">
                    <div class="genre-list" data-name="genres[]">
                        @foreach($genres ?? [] as $g)
                            @php
                                $gId = $g->id ?? $g['id'] ?? null;
                                $gName = $g->name ?? ($g['name'] ?? '');
                                $selected = false;
                                $old = old('genres');
                                if (is_array($old) && in_array($gId, $old)) $selected = true;
                                elseif (isset($selectedGenres) && in_array($gId, $selectedGenres)) $selected = true;
                                elseif (isset($film) && $film->genres && $film->genres->contains($gId)) $selected = true;
                            @endphp
                            <span class="genre-item {{ $selected ? 'selected' : '' }}" data-id="{{ $gId }}">{{ $gName }}</span>
                        @endforeach
                    </div>
                    @if(old('genres'))
                        @foreach(old('genres', []) as $gId)
                            <input type="hidden" name="genres[]" value="{{ $gId }}">
                        @endforeach
                    @else
                        @if(isset($selectedGenres) && is_array($selectedGenres))
                            @foreach($selectedGenres as $gId)
                                <input type="hidden" name="genres[]" value="{{ $gId }}">
                            @endforeach
                        @elseif(isset($film) && $film->genres)
                            @foreach($film->genres as $g)
                                <input type="hidden" name="genres[]" value="{{ $g->id }}">
                            @endforeach
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="form-section">
            <h5>{{ __('messages.film.sections.episodes') }}</h5>
            <div class="form-group row">
                <label class="col-3" for="episodes"></label>
                <div class="col-9">
                    @php
                        $epNames = old('episode_name', []);
                        $epLinks = old('episode_link', []);
                        // if none from old input, try film->episodes relation
                        if (empty($epNames) && isset($film) && isset($film->episodes) && count($film->episodes)) {
                            $epNames = [];
                            $epLinks = [];
                            foreach($film->episodes as $ep) {
                                $epNames[] = $ep->name ?? ($ep->number ?? '');
                                $epLinks[] = $ep->link ?? '';
                            }
                        }
                        $rows = max(1, max(count($epNames), count($epLinks)));
                    @endphp
                    <div class="episodes-list">
                        @for($i = 0; $i < $rows; $i++)
                            @php
                                $nameVal = $epNames[$i] ?? '';
                                $linkVal = $epLinks[$i] ?? '';
                            @endphp
                            <div class="episode-row d-flex gap-2 mb-2 align-items-center">
                                <input type="text" name="episode_name[]" class="episode-name text-center" value="{{ $nameVal }}" placeholder="{{ __('messages.film.episode_name') }}">
                                <input type="text" name="episode_link[]" class="episode-link" value="{{ $linkVal }}" placeholder="{{ __('messages.placeholder.link') }}">
                                <div class="episode-actions">
                                    @if($i === $rows - 1)
                                        <button type="button" class="btn btn-sm btn-add">@include('icons.tick')</button>
                                    @else
                                        <button type="button" class="btn btn-sm btn-edit">@include('icons.edit')</button>
                                        <button type="button" class="btn btn-sm btn-delete">@include('icons.delete')</button>
                                    @endif
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group row">
            <div class="col-3"></div>
            <div class="col-9">
                <button class="btn btn-primary">{{ __('messages.film.edit.button') }}</button>
            </div>
        </div>
    </form>
</div>

@endsection
