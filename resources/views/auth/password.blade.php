@extends('layout.index_layout')

@section('content')
	<div class="container">
        	<div class="row">
            	<div class="col-md-12">
                    <ol class="breadcrumb">
                    	  <li><a href="#">Home</a></li>
                          <li class="active">Lost Password</li>
                    </ol>
                </div>
            </div>
        </div>
        
        
        <div class="container">
        	<div class="row">
            	<div class="col-md-12">
                
                	<div class="row">
                        <div class="col-md-offset-2 col-md-10">
                            <h2>Lost Password</h2>
                        </div>
                    </div>
                    
                    @include('common.errors')
                    @if (session()->has('status'))
					    <div class="alert alert-info">{{ session('status') }}</div>
					@endif                 
                
                    <form class="form-horizontal" method="POST" action="{{url('lostpassword')}}">
                    
                    	{!! csrf_field() !!}
                    
                    	<div class="form-group">
                            <label for="email" class="col-md-2 control-label">E-Mail</label>
                            <div class="col-md-5">
                            	<input type="email" class="form-control" id="email" name="email" value="{{ old('email') }}">
                            </div>
                        </div>
                    
                        <div class="form-group">
                            <div class="col-md-offset-2 col-md-10 margin_bottom_15">
                            	<button type="submit" class="btn btn-primary">Send</button>
                            </div>
                        </div>
                    </form>
                
                
                
                
                
                </div>
            </div>
        </div>
        
        
        
        <div class="container home_info_panel">
        	<div class="row">
            	<div class="col-md-10">
                	Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer commodo ac purus a cursus. Fusce elementum purus sit amet orci lobortis mattis. Sed sodales velit quis tortor tempor pulvinar. Morbi finibus sem neque, eu suscipit ante suscipit id. Suspendisse laoreet et dolor vel aliquet. Nam eu nisi nec nibh malesuada consectetur. Sed vestibulum consectetur tincidunt. Nulla posuere sapien nec sapien sodales, et posuere dui feugiat. Aenean a odio rutrum sapien faucibus finibus vel ut erat. Cras dignissim vitae ante at molestie. 
                </div>
                <div class="col-md-2">
                	<div class="fb-like" data-href="https://www.facebook.com/Bitak.net" data-layout="box_count" data-action="like" data-show-faces="true" data-share="false"></div>
                </div>
            </div>
        </div>
        
        <div class="container home_info_link_panel">
        	<div class="row">
            	<div class="col-md-12">
                    <ol class="breadcrumb">
                          <li class="active">Main Cateegories</li>
                          <li><a href="#">Real Estates</a></li>
                          <li><a href="#">Cars and Parts</a></li>
                          <li><a href="#">Electronics</a></li>
                          <li><a href="#">Sport, Books, Hobby</a></li>
                          <li><a href="#">Home and Garden</a></li>
                          <li><a href="#">Fashion</a></li>
                          <li><a href="#">Baby and Kids</a></li>
                          <li><a href="#">Тourism</a></li>
                          <li><a href="#">Business, Services</a></li>
                          <li><a href="#">Job</a></li>
                    </ol>
                </div>
            </div>
        </div>
@endsection
