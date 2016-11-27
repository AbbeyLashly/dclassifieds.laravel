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
                    <li><a href="{{ route('home') }}">{{ trans('publish_edit.Home') }}</a></li>
                    <li><a href="{{ route('myads') }}">{{ trans('publish_edit.My Classifieds') }}</a></li>
                    <li class="active">{{ trans('publish_edit.Edit ad #') }}{{ $adDetail->ad_id }}</li>
                </ol>
            </div>
        </div>
    </div>


    <div class="container">
        <div class="row">
            <div class="col-md-12">

                @if (session()->has('message'))
                    <div class="alert alert-info">{{ session('message') }}</div>
                @endif

                <form class="form-horizontal" method="POST" enctype="multipart/form-data">

                    {!! csrf_field() !!}
                    <input type="hidden" id="category_type" name="category_type" value="{{ Util::getOldOrModelValue('category_type', $adDetail) ? Util::getOldOrModelValue('category_type', $adDetail) : 0 }}" />
                    <input type="hidden" id="ad_id" name="ad_id" value="{{ $adDetail->ad_id }}" />

                    <div class="row">
                        <div class="col-md-offset-4 col-md-8">
                            <h2>{{ trans('publish_edit.Edit ad #') }}{{ $adDetail->ad_id }}</h2>
                        </div>
                    </div>

                    <div class="form-group required {{ $errors->has('ad_title') ? ' has-error' : '' }}">
                        <label for="ad_title" class="col-md-4 control-label">{{ trans('publish_edit.Ad Title') }}</label>
                        <div class="col-md-5">
                            <input type="text" class="form-control" id="ad_title" name="ad_title" value="{{ Util::getOldOrModelValue('ad_title', $adDetail) }}" maxlength="255"/>
                            @if ($errors->has('ad_title'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('ad_title') }}</strong>
                                </span>
                            @endif
                            @if(trans('publish_edit.Write the most descriptive title'))
                                <span class="help-block">
                                    {!! trans('publish_edit.Write the most descriptive title') !!}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="form-group required {{ $errors->has('category_id') ? ' has-error' : '' }}">
                        <label for="category_id" class="col-md-4 control-label">{{ trans('publish_edit.Category') }}</label>
                        <div class="col-md-5">
                            @if(isset($categoryList) && !empty($categoryList))
                                <?$cid = Util::getOldOrModelValue('category_id', $adDetail);?>
                                <select name="category_id" id="category_id" class="form-control cid_select" disabled>
                                    <option value="0"></option>
                                    @foreach ($categoryList as $k => $v)
                                        @if(isset($cid) && $cid == $v['cid'])
                                            <option value="{{$v['cid']}}" style="font-weight: bold;" selected data-type="{{ $v['category_type'] }}">{{$v['title']}}</option>
                                        @else
                                            <option value="{{$v['cid']}}" style="font-weight: bold;" data-type="{{ $v['category_type'] }}">{{$v['title']}}</option>
                                        @endif
                                        @if(isset($v['c']) && !empty($v['c']))
                                            @include('common.cselect', ['c' => $v['c'], 'cid' => $cid])
                                        @endif
                                    @endforeach
                                </select>
                            @endif

                            @if ($errors->has('category_id'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('category_id') }}</strong>
                                </span>
                            @endif
                            <span id="helpBlock" class="help-block">
                            <?
                            $adDetail->ad_category_info = array_reverse($adDetail->ad_category_info);
                            $category_array = array();
                            $slug = '';
                            foreach ($adDetail->ad_category_info as $k => $v){
                                $slug .= $v['category_slug'] . '/';
                                $link_tpl = '<a href="%s" target="_blank">%s</a>';
                                $category_array[] = sprintf($link_tpl, url($slug), $v['category_title']);
                            }//end of foreach
                            echo join(' / ', $category_array);
                            ?>
                            </span>

                            <input type="hidden" name="category_id" id="category_id" value="{{ Util::getOldOrModelValue('category_id', $adDetail) }}">
                        </div>
                    </div>

                    <div class="form-group required {{ $errors->has('ad_description') ? ' has-error' : '' }}">
                        <label for="ad_description" class="col-md-4 control-label">{{ trans('publish_edit.Ad Description') }}</label>
                        <div class="col-md-5">
                            <textarea class="form-control" name="ad_description" id="ad_description" rows="{{ config('dc.num_rows_ad_description_textarea') }}"><?=Util::getOldOrModelValue('ad_description', $adDetail) ?></textarea>
                            @if ($errors->has('ad_description'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('ad_description') }}</strong>
                                </span>
                            @endif
                            @if(trans('publish_edit.Write the most descriptive description'))
                                <span class="help-block">
                                    {!! trans('publish_edit.Write the most descriptive description') !!}
                                </span>
                            @endif
                        </div>
                    </div>

                    <hr>

                    <!-- category type 1 common goods -->

                    <div id="type_1" class="common_fields_container">
                    <div class="form-group required {{ $errors->has('ad_price_type_1') ? ' has-error' : '' }}" style="margin-bottom: 0px;">
                        <label for="ad_price_type_1" class="col-md-4 control-label">{{ trans('publish_edit.Price') }}</label>
                        <div class="col-md-5">
                            <div class="pull-left checkbox"><input type="radio" name="price_radio" id="price_radio" value="1" {{ Util::getOldOrModelValue('price_radio', $adDetail, 'ad_price') > 0 ? 'checked' : '' }}></div>
                            <div class="pull-left" style="margin-left:5px; width:50%;">
                                <div class="input-group">
                                    @if(config('dc.show_price_sign_before_price'))
                                        <div class="input-group-addon">{{ config('dc.site_price_sign') }}</div>
                                        <input type="text" class="form-control" id="ad_price_type_1" name="ad_price_type_1" value="{{ Util::getOldOrModelValue('ad_price_type_1', $adDetail, 'ad_price') }}" />
                                    @else
                                        <input type="text" class="form-control" id="ad_price_type_1" name="ad_price_type_1" value="{{ Util::getOldOrModelValue('ad_price_type_1', $adDetail, 'ad_price') }}" />
                                        <div class="input-group-addon">{{ config('dc.site_price_sign') }}</div>
                                    @endif
                                </div>
                            </div>
                            @if ($errors->has('ad_price_type_1'))
                                <div class="clearfix"></div>
                                <span class="help-block">
                                    <strong>{{ $errors->first('ad_price_type_1') }}</strong>
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-md-offset-4 col-md-6">
                            <label class="radio-inline">
                                @if(empty(old('price_radio')) && $adDetail->ad_free == 1)
                                    <input type="radio" name="price_radio" id="price_radio" value="2" checked> {{ trans('publish_edit.Free') }}
                                @else
                                    <input type="radio" name="price_radio" id="price_radio" value="2" {{ Util::getOldOrModelValue('price_radio', $adDetail) == 2 ? 'checked' : '' }}> {{ trans('publish_edit.Free') }}
                                @endif
                            </label>
                            @if(trans('publish_edit.Select a price for your ad'))
                                <span class="help-block">
                                    {!! trans('publish_edit.Select a price for your ad') !!}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="form-group required {{ $errors->has('condition_id_type_1') ? ' has-error' : '' }}">
                        <label for="condition_id_type_1" class="col-md-4 control-label">{{ trans('publish_edit.Condition') }}</label>
                        <div class="col-md-5">
                            @if(!$adConditionList->isEmpty())
                            <select name="condition_id_type_1" id="condition_id_type_1" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Condition') }}">
                                <option value="0"></option>
                                @foreach ($adConditionList as $k => $v)
                                    @if(Util::getOldOrModelValue('condition_id_type_1', $adDetail) == $v->ad_condition_id)
                                        <option value="{{ $v->ad_condition_id }}" selected>{{ $v->ad_condition_name }}</option>
                                    @else
                                        <option value="{{ $v->ad_condition_id }}">{{ $v->ad_condition_name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @endif
                            @if ($errors->has('condition_id_type_1'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('condition_id_type_1') }}</strong>
                                </span>
                            @endif
                            @if(trans('publish_edit.In what condition is your item'))
                                <span class="help-block">
                                    {!! trans('publish_edit.In what condition is your item') !!}
                                </span>
                            @endif
                        </div>
                    </div>

                    <hr>
                    <!-- end of type 1 -->
                    </div>

                    <!-- category type 2 real estate -->
                    <div id="type_2" class="common_fields_container">

                        <div class="form-group required {{ $errors->has('ad_price_type_2') ? ' has-error' : '' }}">
                            <label for="ad_price_type_2" class="col-md-4 control-label">{{ trans('publish_edit.Price') }}</label>
                            <div class="col-md-5">
                                <div class="input-group">
                                    @if(config('dc.show_price_sign_before_price'))
                                        <div class="input-group-addon">{{ config('dc.site_price_sign') }}</div>
                                        <input type="text" class="form-control" id="ad_price_type_2" name="ad_price_type_2" value="{{ Util::getOldOrModelValue('ad_price_type_2', $adDetail) }}" />
                                    @else
                                        <input type="text" class="form-control" id="ad_price_type_2" name="ad_price_type_2" value="{{ Util::getOldOrModelValue('ad_price_type_2', $adDetail) }}" />
                                        <div class="input-group-addon">{{ config('dc.site_price_sign') }}</div>
                                    @endif
                                </div>
                                @if ($errors->has('ad_price_type_2'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('ad_price_type_2') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Enter Price'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Enter Price') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group required {{ $errors->has('estate_type_id') ? ' has-error' : '' }}">
                            <label for="estate_type_id" class="col-md-4 control-label">{{ trans('publish_edit.Estate Type') }}</label>
                            <div class="col-md-5">
                                @if(!$estateTypeList->isEmpty())
                                <select name="estate_type_id" id="estate_type_id" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Estate Type') }}">
                                    <option value="0"></option>
                                    @foreach ($estateTypeList as $k => $v)
                                        @if(Util::getOldOrModelValue('estate_type_id', $adDetail) == $v->estate_type_id)
                                            <option value="{{ $v->estate_type_id }}" selected>{{ $v->estate_type_name }}</option>
                                        @else
                                            <option value="{{ $v->estate_type_id }}">{{ $v->estate_type_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @endif
                                @if ($errors->has('estate_type_id'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('estate_type_id') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Choose Estate Type'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Choose Estate Type') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group required {{ $errors->has('estate_sq_m') ? ' has-error' : '' }}">
                            <label for="estate_sq_m" class="col-md-4 control-label">{{ trans('publish_edit.Estate sq. m.') }}</label>
                            <div class="col-md-5">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="estate_sq_m" name="estate_sq_m" value="{{ Util::getOldOrModelValue('estate_sq_m', $adDetail) }}" />
                                    <div class="input-group-addon">{{ config('dc.site_metric_system') }}</div>
                                </div>
                                @if ($errors->has('estate_sq_m'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('estate_sq_m') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Enter Estate sq. m.'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Enter Estate sq. m.') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="estate_year" class="col-md-4 control-label">{{ trans('publish_edit.Estate year of construction') }}</label>
                            <div class="col-md-5">
                                <input type="text" class="form-control" id="estate_year" name="estate_year" value="{{ Util::getOldOrModelValue('estate_year', $adDetail) }}" />
                                @if(trans('publish_edit.Enter Estate year'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Enter Estate year') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="estate_construction_type_id" class="col-md-4 control-label">{{ trans('publish_edit.Estate Construction Type') }}</label>
                            <div class="col-md-5">
                                @if(!$estateConstructionTypeList->isEmpty())
                                <select name="estate_construction_type_id" id="estate_construction_type_id" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Estate Construction Type') }}">
                                    <option value="0"></option>
                                    @foreach ($estateConstructionTypeList as $k => $v)
                                        @if(Util::getOldOrModelValue('estate_construction_type_id', $adDetail) == $v->estate_construction_type_id)
                                            <option value="{{ $v->estate_construction_type_id }}" selected>{{ $v->estate_construction_type_name }}</option>
                                        @else
                                            <option value="{{ $v->estate_construction_type_id }}">{{ $v->estate_construction_type_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @endif
                                @if(trans('publish_edit.Enter Estate Construction Type'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Enter Estate Construction Type') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="estate_floor" class="col-md-4 control-label">{{ trans('publish_edit.Estate floor') }}</label>
                            <div class="col-md-5">
                                <input type="text" class="form-control" id="estate_floor" name="estate_floor" value="{{ Util::getOldOrModelValue('estate_floor', $adDetail) }}" />
                                @if(trans('publish_edit.Enter Estate Floor'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Enter Estate Floor') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="estate_num_floors_in_building" class="col-md-4 control-label">{{ trans('publish_edit.Num Floors in Building') }}</label>
                            <div class="col-md-5">
                                <input type="text" class="form-control" id="estate_num_floors_in_building" name="estate_num_floors_in_building" value="{{ Util::getOldOrModelValue('estate_num_floors_in_building', $adDetail) }}" />
                                @if(trans('publish_edit.Enter num floors in building'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Enter num floors in building') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="estate_heating_type_id" class="col-md-4 control-label">{{ trans('publish_edit.Estate Heating') }}</label>
                            <div class="col-md-5">
                                @if(!$estateHeatingTypeList->isEmpty())
                                <select name="estate_heating_type_id" id="estate_heating_type_id" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Estate Heating') }}">
                                    <option value="0"></option>
                                    @foreach ($estateHeatingTypeList as $k => $v)
                                        @if(Util::getOldOrModelValue('estate_heating_type_id', $adDetail) == $v->estate_heating_type_id)
                                            <option value="{{ $v->estate_heating_type_id }}" selected>{{ $v->estate_heating_type_name }}</option>
                                        @else
                                            <option value="{{ $v->estate_heating_type_id }}">{{ $v->estate_heating_type_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @endif
                                @if(trans('publish_edit.Enter Estate Heating'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Enter Estate Heating') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="estate_furnishing_type_id" class="col-md-4 control-label">{{ trans('publish_edit.Estate Furnishing') }}</label>
                            <div class="col-md-5">
                                @if(!$estateFurnishingTypeList->isEmpty())
                                <select name="estate_furnishing_type_id" id="estate_furnishing_type_id" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Estate Furnishing') }}">
                                    <option value="0"></option>
                                    @foreach ($estateFurnishingTypeList as $k => $v)
                                        @if(Util::getOldOrModelValue('estate_furnishing_type_id', $adDetail) == $v->estate_furnishing_type_id)
                                            <option value="{{ $v->estate_furnishing_type_id }}" selected>{{ $v->estate_furnishing_type_name }}</option>
                                        @else
                                            <option value="{{ $v->estate_furnishing_type_id }}">{{ $v->estate_furnishing_type_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @endif
                                @if(trans('publish_edit.Enter Estate Furnishing'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Enter Estate Furnishing') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group {{ $errors->has('condition_id_type_2') ? ' has-error' : '' }}">
                            <label for="condition_id_type_2" class="col-md-4 control-label">{{ trans('publish_edit.Estate Condition') }}</label>
                            <div class="col-md-5">
                                @if(!$adConditionList->isEmpty())
                                <select name="condition_id_type_2" id="condition_id_type_2" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Condition') }}">
                                    <option value="0"></option>
                                    @foreach ($adConditionList as $k => $v)
                                        @if(old('condition_id_type_2') == $v->ad_condition_id)
                                            <option value="{{ $v->ad_condition_id }}" selected>{{ $v->ad_condition_name }}</option>
                                        @else
                                            <option value="{{ $v->ad_condition_id }}">{{ $v->ad_condition_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @endif
                                @if ($errors->has('condition_id_type_2'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('condition_id_type_2') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Choose Estate Condition'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Choose Estate Condition') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <hr>
                    <!-- end of type 2 -->
                    </div>

                    <!-- category type 3 cars -->
                    <div id="type_3" class="common_fields_container">

                        <div class="form-group required {{ $errors->has('car_brand_id') ? ' has-error' : '' }}">
                            <label for="car_brand_id" class="col-md-4 control-label">{{ trans('publish_edit.Car Brand') }}</label>
                            <div class="col-md-5">
                                @if(!$carBrandList->isEmpty())
                                <select name="car_brand_id" id="car_brand_id" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Car Brand') }}">
                                    <option value="0"></option>
                                    @foreach ($carBrandList as $k => $v)
                                        @if(Util::getOldOrModelValue('car_brand_id', $adDetail) == $v->car_brand_id)
                                            <option value="{{ $v->car_brand_id }}" selected>{{ $v->car_brand_name }}</option>
                                        @else
                                            <option value="{{ $v->car_brand_id }}">{{ $v->car_brand_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @endif
                                @if ($errors->has('car_brand_id'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('car_brand_id') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Choose Car Brand'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Choose Car Brand') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group required {{ $errors->has('car_model_id') ? ' has-error' : '' }}">
                            <label for="car_model_id" class="col-md-4 control-label">{{ trans('publish_edit.Car Model') }}</label>
                            <div class="col-md-5">
                                <div id="car_model_loader"><img src="{{ asset('images/small_loader.gif') }}" /></div>
                                @if(isset($carModelList) && !empty($carModelList))
                                    <select name="car_model_id" id="car_model_id" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Car Model') }}">
                                        @foreach ($carModelList as $k => $v)
                                            @if(Util::getOldOrModelValue('car_model_id', $adDetail) == $k)
                                                <option value="{{ $k  }}" selected>{{ $v }}</option>
                                            @else
                                                <option value="{{ $k  }}">{{ $v }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                @else
                                    <select name="car_model_id" id="car_model_id" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Car Model') }}" disabled>
                                        <option value="0"></option>
                                    </select>
                                @endif
                                @if ($errors->has('car_model_id'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('car_model_id') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Choose Car Model'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Choose Car Model') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group required {{ $errors->has('car_engine_id') ? ' has-error' : '' }}">
                            <label for="car_engine_id" class="col-md-4 control-label">{{ trans('publish_edit.Car Engine') }}</label>
                            <div class="col-md-5">
                                @if(!$carEngineList->isEmpty())
                                <select name="car_engine_id" id="car_engine_id" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Car Engine') }}">
                                    <option value="0"></option>
                                    @foreach ($carEngineList as $k => $v)
                                        @if(Util::getOldOrModelValue('car_engine_id', $adDetail) == $v->car_engine_id)
                                            <option value="{{ $v->car_engine_id }}" selected>{{ $v->car_engine_name }}</option>
                                        @else
                                            <option value="{{ $v->car_engine_id }}">{{ $v->car_engine_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @endif
                                @if ($errors->has('car_engine_id'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('car_engine_id') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Choose Car Engine'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Choose Car Engine') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group required {{ $errors->has('car_transmission_id') ? ' has-error' : '' }}">
                            <label for="car_transmission_id" class="col-md-4 control-label">{{ trans('publish_edit.Car Transmission') }}</label>
                            <div class="col-md-5">
                                @if(!$carTransmissionList->isEmpty())
                                <select name="car_transmission_id" id="car_transmission_id" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Car Tranmission') }}">
                                    <option value="0"></option>
                                    @foreach ($carTransmissionList as $k => $v)
                                        @if(Util::getOldOrModelValue('car_transmission_id', $adDetail) == $v->car_transmission_id)
                                            <option value="{{ $v->car_transmission_id }}" selected>{{ $v->car_transmission_name }}</option>
                                        @else
                                            <option value="{{ $v->car_transmission_id }}">{{ $v->car_transmission_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @endif
                                @if ($errors->has('car_transmission_id'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('car_transmission_id') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Choose Car Transmission'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Choose Car Transmission') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group required {{ $errors->has('car_modification_id') ? ' has-error' : '' }}">
                            <label for="car_transmission_id" class="col-md-4 control-label">{{ trans('publish_edit.Car Modification') }}</label>
                            <div class="col-md-5">
                                @if(!$carModificationList->isEmpty())
                                <select name="car_modification_id" id="car_modification_id" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Car Modification') }}">
                                    <option value="0"></option>
                                    @foreach ($carModificationList as $k => $v)
                                        @if(Util::getOldOrModelValue('car_modification_id', $adDetail) == $v->car_modification_id)
                                            <option value="{{ $v->car_modification_id }}" selected>{{ $v->car_modification_name }}</option>
                                        @else
                                            <option value="{{ $v->car_modification_id }}">{{ $v->car_modification_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @endif
                                @if ($errors->has('car_modification_id'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('car_modification_id') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Choose Car Modification'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Choose Car Modification') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group required {{ $errors->has('car_year') ? ' has-error' : '' }}">
                            <label for="car_year" class="col-md-4 control-label">{{ trans('publish_edit.Car Year') }}</label>
                            <div class="col-md-5">
                                <div><input type="text" class="form-control" id="car_year" name="car_year" value="{{ Util::getOldOrModelValue('car_year', $adDetail) }}" /></div>
                                @if ($errors->has('car_year'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('car_year') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Enter Car Year'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Enter Car Year') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group required {{ $errors->has('car_kilometeres') ? ' has-error' : '' }}">
                            <label for="car_kilometeres" class="col-md-4 control-label">{{ trans('publish_edit.Car Kilometers') }}</label>
                            <div class="col-md-5">
                                <div><input type="text" class="form-control" id="car_kilometeres" name="car_kilometeres" value="{{ Util::getOldOrModelValue('car_kilometeres', $adDetail) }}" /></div>
                                @if ($errors->has('car_kilometeres'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('car_kilometeres') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Enter Car Kilometers'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Enter Car Kilometers') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group required {{ $errors->has('condition_id_type_3') ? ' has-error' : '' }}">
                            <label for="condition_id_type_3" class="col-md-4 control-label">{{ trans('publish_edit.Condition') }}</label>
                            <div class="col-md-5">
                                @if(!$adConditionList->isEmpty())
                                <select name="condition_id_type_3" id="condition_id_type_3" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Condition') }}">
                                    <option value="0"></option>
                                    @foreach ($adConditionList as $k => $v)
                                        @if(Util::getOldOrModelValue('condition_id_type_3', $adDetail) == $v->ad_condition_id)
                                            <option value="{{ $v->ad_condition_id }}" selected>{{ $v->ad_condition_name }}</option>
                                        @else
                                            <option value="{{ $v->ad_condition_id }}">{{ $v->ad_condition_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @endif
                                @if ($errors->has('condition_id_type_3'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('condition_id_type_3') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Choose Condition'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Choose Condition') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group required {{ $errors->has('car_condition_id') ? ' has-error' : '' }}">
                            <label for="car_condition_id" class="col-md-4 control-label">{{ trans('publish_edit.Car Condition') }}</label>
                            <div class="col-md-5">
                                @if(!$carConditionList->isEmpty())
                                <select name="car_condition_id" id="car_condition_id" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Car Condition') }}">
                                    <option value="0"></option>
                                    @foreach ($carConditionList as $k => $v)
                                        @if(Util::getOldOrModelValue('car_condition_id', $adDetail) == $v->car_condition_id)
                                            <option value="{{ $v->car_condition_id }}" selected>{{ $v->car_condition_name }}</option>
                                        @else
                                            <option value="{{ $v->car_condition_id }}">{{ $v->car_condition_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @endif
                                @if ($errors->has('car_condition_id'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('car_condition_id') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Choose Car Condition'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Choose Car Condition') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group required {{ $errors->has('ad_price_type_3') ? ' has-error' : '' }}">
                            <label for="ad_price_type_3" class="col-md-4 control-label">{{ trans('publish_edit.Price') }}</label>
                            <div class="col-md-5">
                                <div class="input-group">
                                    @if(config('dc.show_price_sign_before_price'))
                                        <div class="input-group-addon">{{ config('dc.site_price_sign') }}</div>
                                        <input type="text" class="form-control" id="ad_price_type_3" name="ad_price_type_3" value="{{ Util::getOldOrModelValue('ad_price_type_3', $adDetail) }}" />
                                    @else
                                        <input type="text" class="form-control" id="ad_price_type_3" name="ad_price_type_3" value="{{ Util::getOldOrModelValue('ad_price_type_3', $adDetail) }}" />
                                        <div class="input-group-addon">{{ config('dc.site_price_sign') }}</div>
                                    @endif
                                </div>
                                @if ($errors->has('ad_price_type_3'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('ad_price_type_3') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Enter Price'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Enter Price') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <hr>
                    <!-- end of type 3 -->
                    </div>

                    <!-- category type 4 services -->
                    <div id="type_4" class="common_fields_container">
                        <div class="form-group required {{ $errors->has('ad_price_type_4') ? ' has-error' : '' }}" style="margin-bottom: 0px;">
                            <label for="ad_price_type_4" class="col-md-4 control-label">{{ trans('publish_edit.Price') }}</label>
                            <div class="col-md-5">
                                <div class="pull-left checkbox"><input type="radio" name="price_radio_type_4" id="price_radio_type_4" value="1" {{ Util::getOldOrModelValue('price_radio_type_4', $adDetail) == 1 ? 'checked' : '' }}></div>
                                <div class="pull-left" style="margin-left:5px; width:50%;">
                                    <div class="input-group">
                                        @if(config('dc.show_price_sign_before_price'))
                                            <div class="input-group-addon">{{ config('dc.site_price_sign') }}</div>
                                            <input type="text" class="form-control" id="ad_price_type_4" name="ad_price_type_4" value="{{ Util::getOldOrModelValue('ad_price_type_4', $adDetail) }}" />
                                        @else
                                            <input type="text" class="form-control" id="ad_price_type_4" name="ad_price_type_4" value="{{ Util::getOldOrModelValue('ad_price_type_4', $adDetail) }}" />
                                            <div class="input-group-addon">{{ config('dc.site_price_sign') }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="clearfix"></div>
                                @if ($errors->has('ad_price_type_4'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('ad_price_type_4') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-md-offset-4 col-md-6">
                                <label class="radio-inline">
                                    <input type="radio" name="price_radio_type_4" id="price_radio_type_4" value="2" {{ Util::getOldOrModelValue('price_radio_type_4', $adDetail) == 2 ? 'checked' : '' }}> {{ trans('publish_edit.Free') }}
                                </label>
                                @if(trans('publish_edit.Select a price for your ad'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Select a price for your ad') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <hr>
                    <!-- end of type 4 -->
                    </div>

                    <!-- category type 5 clothes -->
                    <div id="type_5" class="common_fields_container">
                        <div class="form-group required {{ $errors->has('ad_price_type_5') ? ' has-error' : '' }}" style="margin-bottom: 0px;">
                            <label for="ad_price_type_5" class="col-md-4 control-label">{{ trans('publish_edit.Price') }}</label>
                            <div class="col-md-5">
                                <div class="pull-left checkbox"><input type="radio" name="price_radio_type_5" id="price_radio_type_5" value="1" {{ Util::getOldOrModelValue('price_radio_type_5', $adDetail) == 1 ? 'checked' : '' }}></div>
                                <div class="pull-left" style="margin-left:5px; width:50%;">
                                    <div class="input-group">
                                        @if(config('dc.show_price_sign_before_price'))
                                            <div class="input-group-addon">{{ config('dc.site_price_sign') }}</div>
                                            <input type="text" class="form-control" id="ad_price_type_5" name="ad_price_type_5" value="{{ Util::getOldOrModelValue('ad_price_type_5', $adDetail) }}" />
                                        @else
                                            <input type="text" class="form-control" id="ad_price_type_5" name="ad_price_type_5" value="{{ Util::getOldOrModelValue('ad_price_type_5', $adDetail) }}" />
                                            <div class="input-group-addon">{{ config('dc.site_price_sign') }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="clearfix"></div>
                                @if ($errors->has('ad_price_type_5'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('ad_price_type_5') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-md-offset-4 col-md-6">
                                <label class="radio-inline">
                                    <input type="radio" name="price_radio_type_5" id="price_radio_type_5" value="2" {{ Util::getOldOrModelValue('price_radio_type_5', $adDetail) == 2 ? 'checked' : '' }}> {{ trans('publish_edit.Free') }}
                                </label>
                                @if(trans('publish_edit.Select a price for your ad'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Select a price for your ad') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group required {{ $errors->has('clothes_size_id') ? ' has-error' : '' }}">
                            <label for="clothes_size_id" class="col-md-4 control-label">{{ trans('publish_edit.Clothes Size') }}</label>
                            <div class="col-md-5">
                                @if(!$clothesSizesList->isEmpty())
                                <select name="clothes_size_id" id="clothes_size_id" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Clothes Size') }}">
                                    <option value="0"></option>
                                    @foreach ($clothesSizesList as $k => $v)
                                        @if(Util::getOldOrModelValue('clothes_size_id', $adDetail) == $v->clothes_size_id)
                                            <option value="{{ $v->clothes_size_id }}" selected>{{ $v->clothes_size_name }}</option>
                                        @else
                                            <option value="{{ $v->clothes_size_id }}">{{ $v->clothes_size_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @endif
                                @if ($errors->has('clothes_size_id'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('clothes_size_id') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Choose Clothes Size'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Choose Clothes Size') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group required {{ $errors->has('condition_id_type_5') ? ' has-error' : '' }}">
                            <label for="condition_id_type_5" class="col-md-4 control-label">{{ trans('publish_edit.Condition') }}</label>
                            <div class="col-md-5">
                                @if(!$adConditionList->isEmpty())
                                <select name="condition_id_type_5" id="condition_id_type_5" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Condition') }}">
                                    <option value="0"></option>
                                    @foreach ($adConditionList as $k => $v)
                                        @if(old('condition_id_type_5') == $v->ad_condition_id)
                                            <option value="{{ $v->ad_condition_id }}" selected>{{ $v->ad_condition_name }}</option>
                                        @else
                                            <option value="{{ $v->ad_condition_id }}">{{ $v->ad_condition_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @endif
                                @if ($errors->has('condition_id_type_5'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('condition_id_type_5') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.In what condition is your item'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.In what condition is your item') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <hr>
                    <!-- end of type 5 -->
                    </div>

                    <!-- category type 6 shoes -->
                    <div id="type_6" class="common_fields_container">
                        <div class="form-group required {{ $errors->has('ad_price_type_6') ? ' has-error' : '' }}" style="margin-bottom: 0px;">
                            <label for="ad_price_type_6" class="col-md-4 control-label">{{ trans('publish_edit.Price') }}</label>
                            <div class="col-md-5">
                                <div class="pull-left checkbox"><input type="radio" name="price_radio_type_6" id="price_radio_type_6" value="1" {{ Util::getOldOrModelValue('price_radio_type_6', $adDetail) == 1 ? 'checked' : '' }}></div>
                                <div class="pull-left" style="margin-left:5px; width: 50%;">
                                    <div class="input-group">
                                        @if(config('dc.show_price_sign_before_price'))
                                            <div class="input-group-addon">{{ config('dc.site_price_sign') }}</div>
                                            <input type="text" class="form-control" id="ad_price_type_6" name="ad_price_type_6" value="{{ Util::getOldOrModelValue('ad_price_type_6', $adDetail) }}" />
                                        @else
                                            <input type="text" class="form-control" id="ad_price_type_6" name="ad_price_type_6" value="{{ Util::getOldOrModelValue('ad_price_type_6', $adDetail) }}" />
                                            <div class="input-group-addon">{{ config('dc.site_price_sign') }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="clearfix"></div>
                                @if ($errors->has('ad_price_type_6'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('ad_price_type_6') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-md-offset-4 col-md-6">
                                <label class="radio-inline">
                                    <input type="radio" name="price_radio_type_6" id="price_radio_type_6" value="2" {{ Util::getOldOrModelValue('price_radio_type_6', $adDetail) == 2 ? 'checked' : '' }}> {{ trans('publish_edit.Free') }}
                                </label>
                                @if(trans('publish_edit.Select a price for your ad'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Select a price for your ad') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group required {{ $errors->has('shoes_size_id') ? ' has-error' : '' }}">
                            <label for="shoes_size_id" class="col-md-4 control-label">{{ trans('publish_edit.Shoes Size') }}</label>
                            <div class="col-md-5">
                                @if(!$shoesSizesList->isEmpty())
                                <select name="shoes_size_id" id="shoes_size_id" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Shoes Size') }}">
                                    <option value="0"></option>
                                    @foreach ($shoesSizesList as $k => $v)
                                        @if(Util::getOldOrModelValue('shoes_size_id', $adDetail) == $v->shoes_size_id)
                                            <option value="{{ $v->shoes_size_id }}" selected>{{ $v->shoes_size_name }}</option>
                                        @else
                                            <option value="{{ $v->shoes_size_id }}">{{ $v->shoes_size_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @endif
                                @if ($errors->has('shoes_size_id'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('shoes_size_id') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Choose Shoes Size'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Choose Shoes Size') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group required {{ $errors->has('condition_id_type_6') ? ' has-error' : '' }}">
                            <label for="condition_id_type_6" class="col-md-4 control-label">{{ trans('publish_edit.Condition') }}</label>
                            <div class="col-md-5">
                                @if(!$adConditionList->isEmpty())
                                <select name="condition_id_type_6" id="condition_id_type_6" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Select Condition') }}">
                                    <option value="0"></option>
                                    @foreach ($adConditionList as $k => $v)
                                        @if(old('condition_id_type_6') == $v->ad_condition_id)
                                            <option value="{{ $v->ad_condition_id }}" selected>{{ $v->ad_condition_name }}</option>
                                        @else
                                            <option value="{{ $v->ad_condition_id }}">{{ $v->ad_condition_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @endif
                                @if ($errors->has('condition_id_type_6'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('condition_id_type_6') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.In what condition is your item'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.In what condition is your item') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <hr>
                    <!-- end of type 6 -->
                    </div>

                    <!-- category type 7 real estate land -->
                    <div id="type_7" class="common_fields_container">
                        <div class="form-group required {{ $errors->has('ad_price_type_7') ? ' has-error' : '' }}">
                            <label for="ad_price_type_7" class="col-md-4 control-label">{{ trans('publish_edit.Price') }}</label>
                            <div class="col-md-5">
                                <div class="input-group">
                                    @if(config('dc.show_price_sign_before_price'))
                                        <div class="input-group-addon">{{ config('dc.site_price_sign') }}</div>
                                        <input type="text" class="form-control" id="ad_price_type_7" name="ad_price_type_7" value="{{ Util::getOldOrModelValue('ad_price_type_7', $adDetail) }}" />
                                    @else
                                        <input type="text" class="form-control" id="ad_price_type_7" name="ad_price_type_7" value="{{ Util::getOldOrModelValue('ad_price_type_7', $adDetail) }}" />
                                        <div class="input-group-addon">{{ config('dc.site_price_sign') }}</div>
                                    @endif
                                </div>
                                @if ($errors->has('ad_price_type_7'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('ad_price_type_7') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Enter Price'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Enter Price') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group required {{ $errors->has('estate_sq_m_type_7') ? ' has-error' : '' }}">
                            <label for="estate_sq_m_type_7" class="col-md-4 control-label">{{ trans('publish_edit.Estate/Land sq. m.') }}</label>
                            <div class="col-md-5">
                                <div class="input-group">
                                    <input type="text" class="form-control" id="estate_sq_m_type_7" name="estate_sq_m_type_7" value="{{ Util::getOldOrModelValue('estate_sq_m_type_7', $adDetail) }}" />
                                    <div class="input-group-addon">{{ config('dc.site_metric_system') }}</div>
                                </div>
                                @if ($errors->has('estate_sq_m_type_7'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('estate_sq_m_type_7') }}</strong>
                                    </span>
                                @endif
                                @if(trans('publish_edit.Enter Estate/Land sq. m.'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Enter Estate/Land sq. m.') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <hr>
                    <!-- end of type 7 -->
                    </div>

                    <!-- category type 8 jobs -->
                    <div id="type_8" class="common_fields_container">
                        <div class="form-group required {{ $errors->has('ad_price_type_8') ? ' has-error' : '' }}" style="margin-bottom: 0px;">
                            <label for="ad_price_type_8" class="col-md-4 control-label">{{ trans('publish_edit.Salary') }}</label>
                            <div class="col-md-5">
                                <div class="pull-left checkbox"><input type="radio" name="price_radio_type_8" id="price_radio_type_8" value="1" {{ Util::getOldOrModelValue('price_radio_type_8', $adDetail) == 1 ? 'checked' : '' }}></div>
                                <div class="pull-left" style="margin-left:5px; width:50%;">
                                    <div class="input-group">
                                        @if(config('dc.show_price_sign_before_price'))
                                            <div class="input-group-addon">{{ config('dc.site_price_sign') }}</div>
                                            <input type="text" class="form-control" id="ad_price_type_8" name="ad_price_type_8" value="{{ Util::getOldOrModelValue('ad_price_type_8', $adDetail) }}" />
                                        @else
                                            <input type="text" class="form-control" id="ad_price_type_8" name="ad_price_type_8" value="{{ Util::getOldOrModelValue('ad_price_type_8', $adDetail) }}" />
                                            <div class="input-group-addon">{{ config('dc.site_price_sign') }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="clearfix"></div>
                                @if ($errors->has('ad_price_type_8'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('ad_price_type_8') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-md-offset-4 col-md-6">
                                <label class="radio-inline">
                                    <input type="radio" name="price_radio_type_8" id="price_radio_type_8" value="2" {{ Util::getOldOrModelValue('price_radio_type_8', $adDetail) == 2 ? 'checked' : '' }}> {{ trans('publish_edit.Negotiable') }}
                                </label>
                                @if(trans('publish_edit.Enter salary'))
                                    <span class="help-block">
                                        {!! trans('publish_edit.Enter salary') !!}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <hr>
                    <!-- end of type 8 -->
                    </div>

                    <div class="form-group required {{ $errors->has('type_id') ? ' has-error' : '' }}">
                        <label for="type_id" class="col-md-4 control-label">{{ trans('publish_edit.Private/Business Ad') }}</label>
                        <div class="col-md-5">
                            @if(!$adTypeList->isEmpty())
                            <select name="type_id" id="type_id" class="form-control chosen_select" data-placeholder="{{ trans('publish_edit.Please Select') }}">
                                <option value="0"></option>
                                @foreach ($adTypeList as $k => $v)
                                    @if(Util::getOldOrModelValue('type_id', $adDetail) == $v->ad_type_id)
                                        <option value="{{ $v->ad_type_id }}" selected>{{ $v->ad_type_name }}</option>
                                    @else
                                        <option value="{{ $v->ad_type_id }}">{{ $v->ad_type_name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @endif
                            @if ($errors->has('type_id'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('type_id') }}</strong>
                                </span>
                            @endif
                            @if(trans('publish_edit.Are you private or business seller'))
                                <span class="help-block">
                                    {!! trans('publish_edit.Are you private or business seller') !!}
                                </span>
                            @endif
                        </div>
                    </div>

                    <hr>

                    <?
                    $num_image = config('dc.ad_num_images');
                    $file_has_error = 0;
                    for($i = 1; $i <= $num_image; $i++){
                        if($errors->has('ad_image.' . ($i-1))){
                            $file_has_error = 1;
                        }
                    }
                    if($errors->has('ad_image')){
                        $file_has_error = 1;
                    }
                    ?>

                    <div class="form-group {{ $file_has_error ? ' has-error' : '' }}">
                        <label for="ad_image" class="col-md-4 control-label">{{ trans('publish_edit.Pics') }}</label>
                        <div class="col-md-5">
                            @for($i = 1; $i <= $num_image; $i++)
                                <div style="margin-bottom:5px;"><input type="file" name="ad_image[]" id="ad_image_{{ $i }}" style="display:inline;"> <button class="btn btn-danger btn-xs clear" data-id="{{ $i }}">{{ trans('publish_edit.Clear') }}</button></div>
                                @if ($errors->has('ad_image.' . ($i-1)))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('ad_image.' . ($i-1)) }}</strong>
                                    </span>
                                @endif
                            @endfor
                        </div>
                        <div class="col-md-offset-4 col-md-5">
                            @if ($errors->has('ad_image'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('ad_image') }}</strong>
                                </span>
                            @endif
                            @if(trans('publish_edit.Choose the best picture for your ad'))
                                <span class="help-block">
                                    {!! trans('publish_edit.Choose the best picture for your ad') !!}
                                </span>
                            @endif
                        </div>
                    </div>

                    <hr>

                    <div class="form-group required {{ $errors->has('location_id') ? ' has-error' : '' }}">
                        <label for="location_id" class="col-md-4 control-label">{{ trans('publish_edit.Location') }}</label>
                        <div class="col-md-5">
                            <div class="input-group">
                                @if(isset($locationList) && !empty($locationList))
                                <?$lid = Util::getOldOrModelValue('location_id', $adDetail)?>
                                <select name="location_id" id="location_id" class="form-control lid_select">
                                    <option value="0"></option>
                                    @foreach ($locationList as $k => $v)
                                        @if(isset($lid) && $lid == $v['lid'])
                                            <option value="{{$v['lid']}}" style="font-weight: bold;" selected>{{$v['title']}}</option>
                                        @else
                                            <option value="{{$v['lid']}}" style="font-weight: bold;">{{$v['title']}}</option>
                                        @endif
                                        @if(isset($v['c']) && !empty($v['c']))
                                            @include('common.lselect', ['c' => $v['c']])
                                        @endif
                                    @endforeach
                                </select>
                                @endif
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-info" id="quick-location-selector" data-toggle="modal" data-target="#quick-location-select-modal">{{ trans('publish_edit.Quick Select Location') }}</button>
                                </span>
                            </div>
                            @if ($errors->has('location_id'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('location_id') }}</strong>
                                </span>
                            @endif
                            @if(trans('publish_edit.Choose your Location'))
                                <span class="help-block">
                                    {!! trans('publish_edit.Choose your Location') !!}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ad_address" class="col-md-4 control-label">{{ trans('publish_edit.Address') }}</label>
                        <div class="col-md-5">
                            <div class="input-group">
                                <input type="text" class="form-control" id="ad_address" name="ad_address" value="{{ Util::getOldOrModelValue('ad_address', $adDetail) }}" >
                                <span class="input-group-btn">
                                    <input type="button" class="btn btn-info" id="ad_address_show_map" name="ad_address_show_map" value="{{ trans('publish_edit.Find on Map') }}" >
                                </span>
                            </div>
                            <input type="hidden" class="form-control" id="ad_lat_lng" name="ad_lat_lng" value="{{ Util::getOldOrModelValue('ad_lat_lng', $adDetail) }}" >
                            @if(trans('publish_edit.Choose your Address'))
                                <span class="help-block">
                                    {!! trans('publish_edit.Choose your Address') !!}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="form-group required {{ $errors->has('ad_publisher_name') ? ' has-error' : '' }}">
                        <label for="ad_publisher_name" class="col-md-4 control-label">{{ trans('publish_edit.Contact Name') }}</label>
                        <div class="col-md-5">
                            <input type="text" class="form-control" id="ad_publisher_name" name="ad_publisher_name" value="{{ Util::getOldOrModelValue('ad_publisher_name', $adDetail) }}" />
                            @if ($errors->has('ad_publisher_name'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('ad_publisher_name') }}</strong>
                                </span>
                            @endif
                            @if(trans('publish_edit.Enter your name'))
                                <span class="help-block">
                                    {!! trans('publish_edit.Enter your name') !!}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="form-group required {{ $errors->has('ad_email') ? ' has-error' : '' }}">
                        <label for="ad_email" class="col-md-4 control-label">{{ trans('publish_edit.E-Mail') }}</label>
                        <div class="col-md-5">
                            <input type="email" class="form-control" id="ad_email" name="ad_email" value="{{ Util::getOldOrModelValue('ad_email', $adDetail) }}" />
                            @if ($errors->has('ad_email'))
                                <span class="help-block">
                                    <strong>{{ $errors->first('ad_email') }}</strong>
                                </span>
                            @endif
                            @if(trans('publish_edit.Enter contact mail'))
                                <span class="help-block">
                                    {!! trans('publish_edit.Enter contact mail') !!}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ad_phone" class="col-md-4 control-label">{{ trans('publish_edit.Phone') }}</label>
                        <div class="col-md-5">
                            <input type="text" class="form-control" id="ad_phone" name="ad_phone" value="{{ Util::getOldOrModelValue('ad_phone', $adDetail) }}" >
                            @if(trans('publish_edit.Enter contact phone'))
                                <span class="help-block">
                                    {!! trans('publish_edit.Enter contact phone') !!}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ad_skype" class="col-md-4 control-label">{{ trans('publish_edit.Skype') }}</label>
                        <div class="col-md-5">
                            <input type="text" class="form-control" id="ad_skype" name="ad_skype" value="{{ Util::getOldOrModelValue('ad_skype', $adDetail) }}" >
                            @if(trans('publish_edit.Enter contact skype'))
                                <span class="help-block">
                                    {!! trans('publish_edit.Enter contact skype') !!}
                                </span>
                            @endif
                        </div>
                    </div>

                    @if(config('dc.enable_link_in_ad'))
                    <div class="form-group">
                        <label for="ad_link" class="col-md-4 control-label">{{ trans('publish_edit.Web Site') }}</label>
                        <div class="col-md-5">
                            <input type="text" class="form-control" id="ad_link" name="ad_link" value="{{ Util::getOldOrModelValue('ad_link', $adDetail) }}" >
                            @if(trans('publish_edit.Insert link to your site in this format: http://www.site.com'))
                                <span id="helpBlock" class="help-block">
                                    {{ trans('publish_edit.Insert link to your site in this format: http://www.site.com') }}
                                </span>
                            @endif
                        </div>
                    </div>
                    @endif

                    @if(config('dc.enable_video_in_ad'))
                    <div class="form-group">
                        <label for="ad_video" class="col-md-4 control-label">{{ trans('publish_edit.Video') }}</label>
                        <div class="col-md-5">
                            <input type="text" class="form-control" id="ad_video" name="ad_video" value="{{ Util::getOldOrModelValue('ad_video', $adDetail) }}" >
                            @if(trans('publish_edit.Insert link to youtube.com video'))
                                <span id="helpBlock" class="help-block">
                                    {{ trans('publish_edit.Insert link to youtube.com video') }}
                                </span>
                            @endif
                        </div>
                    </div>
                    @endif

                    <div class="form-group required {{ $errors->has('policy_agree') ? ' has-error' : '' }}">
                        <label for="policy_agree" class="col-md-4 control-label"></label>
                        <div class="col-md-8">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="policy_agree" {{ Util::getOldOrModelValue('policy_agree', $adDetail) ? 'checked' : '' }}> {{ trans('publish_edit.I agree with') }} <a href="{{ config('dc.privacy_policy_link') }}" target="_blank">{{ trans('publish_edit."Privacy Policy"') }}</a>
                                </label>
                                @if ($errors->has('policy_agree'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('policy_agree') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-md-offset-4 col-md-8">
                            <button type="submit" class="btn btn-primary">{{ trans('publish_edit.Save') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="google_map_container" style="display:none;">
        <div style="margin:10px 0px 20px 0px">
            <form class="form-inline">
                <div class="form-group">
                    <input type="text" name="address" id="address" class="form-control" style="width:445px;"/>
                    <input type="hidden" name="lat" id="lat"/>
                    <button type="button" name="location_find" id="location_find" class="btn btn-primary">
                        <span class="glyphicon glyphicon glyphicon-search" aria-hidden="true"></span> {{ trans('publish_edit.Find on the map') }}
                    </button>
                    <button type="button" name="location_ok" id="location_ok" class="btn btn-success">
                        <span class="glyphicon glyphicon glyphicon-ok" aria-hidden="true"></span> {{ trans('publish_edit.Yes, this is my location') }}
                    </button>
                </div>
            </form>
        </div>
        <div style="width: 800px; height:400px;" id="map_canvas"></div>
    </div>

    @include('common.location_select_modal')
@endsection

@section('styles')
    <link rel="stylesheet" href="{{asset('js/fancybox/jquery.fancybox.css')}}" />
@endsection

@section('js')
    <script src="{{asset('js/fancybox/jquery.fancybox.pack.js')}}"></script>
    @if(config('dc.google_maps_api_key'))
        <script src="https://maps.googleapis.com/maps/api/js?key={!! config('dc.google_maps_api_key') !!}&sensor=true&language={{ config('dc.google_maps_language') }}"></script>
    @else
        <script src="https://maps.googleapis.com/maps/api/js?sensor=true&language={{ config('dc.google_maps_language') }}"></script>
    @endif
    <script src="{{asset('js/google.map.js')}}"></script>
    <script src="{{asset('js/publish.js')}}"></script>
@endsection

