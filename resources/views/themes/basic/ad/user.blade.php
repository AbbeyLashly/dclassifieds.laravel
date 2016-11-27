@extends('layout.index_layout')

@section('title', join(' / ', $title))

@section('search_filter')
    <div style="margin-bottom: 20px;"></div>
@endsection

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <ol class="breadcrumb">
                    <li><a href="{{ route('home') }}"><span class="glyphicon glyphicon-home" aria-hidden="true"></span> {{ trans('user.Home') }}</a></li>
                    <li><a href="{{ url('ad/user/' . $user->user_id) }}">{{ trans('user.Ad List') }}</a></li>
                    <li class="active">{{ trans('user.Ad List User', ['name' => $user->name]) }}</li>
                </ol>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="row">
                    <div class="col-md-12">
                        <div class="media">
                            <div class="media-left">
                                <a href="{{ url('ad/user/' . $user->user_id) }}">
                                    @if(!empty($user->avatar))
                                        <img src="{{ asset('uf/udata/100_' . $user->avatar) }}" alt="{{ $user->name }}">
                                    @else
                                        <img src="{{ 'https://www.gravatar.com/avatar/' . md5(trim($user->email)) . '?s=100&d=identicon' }}" alt="{{ $user->name }}">
                                    @endif
                                </a>
                            </div>
                            <div class="media-body">
                                <h3 class="media-heading">{{ $user->name }}</h3>
                                <small><span class="text-muted">{{ trans('user.Registered') }}: {{ $user->created_at }}</span></small>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                @if(isset($userAdList) && !$userAdList->isEmpty())
                    <div class="row">
                        @foreach ($userAdList as $k => $v)
                            @if(config('dc.show_small_item_ads_list'))
                                @include('common.ad_list_small')
                            @else
                                @include('common.ad_list')
                            @endif
                        @endforeach
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <nav>{{  $userAdList->appends($params)->links() }}</nav>
                        </div>
                    </div>
                @endif

                @if (session()->has('message'))
                    <div class="alert alert-info">{{ session('message') }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection

@if(isset($userAdList) && !$userAdList->isEmpty())
    @section('meta')
        @if($userAdList->appends($params)->previousPageUrl())
            <link rel="prev" href="{{ $userAdList->appends($params)->previousPageUrl() }}" />
        @endif
        @if($userAdList->appends($params)->nextPageUrl())
            <link rel="next" href="{{ $userAdList->appends($params)->nextPageUrl() }}" />
        @endif
    @endsection
@endif