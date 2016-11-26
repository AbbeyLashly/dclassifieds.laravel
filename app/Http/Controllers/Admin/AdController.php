<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Http\Requests;
use App\Http\Requests\AdPostRequest;
use App\Http\Controllers\Controller;
use App\Http\Dc\Util;

use App\Ad;
use App\AdType;
use App\AdCondition;
use App\EstateConstructionType;
use App\EstateFurnishingType;
use App\EstateHeatingType;
use App\EstateType;
use App\CarBrand;
use App\CarModel;
use App\CarEngine;
use App\CarTransmission;
use App\CarCondition;
use App\CarModification;
use App\ClothesSize;
use App\ShoesSize;
use App\AdPic;
use App\Category;
use App\Location;
use App\User;
use App\AdBanIp;
use App\AdBanEmail;


use Validator;
use Cache;
use Mail;

class AdController extends Controller
{
    protected $ad;
    protected $category;
    protected $location;
    protected $user;

    public function __construct(Ad $_ad, Category $_category, Location $_location, User $_user)
    {
        $this->ad       = $_ad;
        $this->category = $_category;
        $this->location = $_location;
        $this->user     = $_user;
    }

    public function index(Request $request)
    {
        $params     = Input::all();
        $where      = [];
        $order      = ['ad_id' => 'desc'];
        $limit      = 0;
        $orderRaw   = '';
        $whereIn    = [];
        $whereRaw   = [];
        $paginate   = config('dc.admin_list_num_items');
        $page       = 1;

        if(isset($params['ad_id_search']) && !empty($params['ad_id_search'])){
            $where['ad_id'] = ['=', $params['ad_id_search']];
        }

        if(isset($params['ad_ip']) && !empty($params['ad_ip'])){
            $where['ad_ip'] = ['like', $params['ad_ip'] . '%'];
        }

        if(isset($params['location_name']) && !empty($params['location_name'])){
            $where['location_name'] = ['like', $params['location_name'] . '%'];
        }

        if(isset($params['ad_title']) && !empty($params['ad_title'])){
            $where['ad_title'] = ['like', $params['ad_title'] . '%'];
        }

        if(isset($params['user_id']) && !empty($params['user_id'])){
            $where['user_id'] = ['=', $params['user_id']];
        }

        if(isset($params['ad_publisher_name']) && !empty($params['ad_publisher_name'])){
            $where['ad_publisher_name'] = ['like', $params['ad_publisher_name'] . '%'];
        }

        if(isset($params['ad_email']) && !empty($params['ad_email'])){
            $where['ad_email'] = ['like', $params['ad_email'] . '%'];
        }

        if(isset($params['ad_promo']) && is_numeric($params['ad_promo']) && ($params['ad_promo'] == 0 || $params['ad_promo'] == 1)){
            $where['ad_promo'] = ['=', $params['ad_promo']];
        }

        if(isset($params['ad_active']) && is_numeric($params['ad_active']) && ($params['ad_active'] == 0 || $params['ad_active'] == 1)){
            $where['ad_active'] = ['=', $params['ad_active']];
        }

        if(isset($params['ad_view']) && !empty($params['ad_view'])){
            $where['ad_view'] = ['=', $params['ad_view']];
        }

        if (isset($params['page']) && is_numeric($params['page'])) {
            $page = $params['page'];
        }

        $adList = $this->ad->getAdList($where, $order, $limit, $orderRaw, $whereIn, $whereRaw, $paginate, $page);
        return view('admin.ad.ad_list', ['adList' => $adList,
            'params' => $params,
            'yesnoselect' => ['_' => '', 0 => trans('admin_common.No'), 1 => trans('admin_common.Yes')]]);
    }

    public function edit(Request $request)
    {
        //get ad id
        $adId = $request->id;

        //get ad info
        $adDetail = $this->ad->getAdDetail($adId, 0);

        $adDetail->ad_price_type_1     = $adDetail->ad_price_type_2 = $adDetail->ad_price_type_3 = $adDetail->ad_price_type_4 = $adDetail->ad_price;
        $adDetail->ad_price_type_5     = $adDetail->ad_price_type_6 = $adDetail->ad_price_type_7 = $adDetail->ad_price_type_8 = $adDetail->ad_price;

        if($adDetail->ad_price > 0){
            $adDetail->price_radio = $adDetail->price_radio_type_4 = $adDetail->price_radio_type_5 = $adDetail->price_radio_type_6 = $adDetail->price_radio_type_8 = 1;
        } elseif ($adDetail->ad_free){
            $adDetail->price_radio = $adDetail->price_radio_type_4 = $adDetail->price_radio_type_5 = $adDetail->price_radio_type_6 = $adDetail->price_radio_type_8 = 2;
        }

        $adDetail->condition_id_type_1 = $adDetail->condition_id_type_3 = $adDetail->condition_id;
        $adDetail->estate_sq_m_type_7  = $adDetail->estate_sq_m;
        $adDetail->ad_description      = Util::br2nl($adDetail->ad_description);

        //get ad pics
        $adPic = AdPic::where('ad_id', $adId)->get();

        $carModelListArray = [];
        if(old('car_brand_id')){
            if(is_numeric(old('car_brand_id')) && old('car_brand_id') > 0){

                $carModel   = new CarModel();
                $select     = ['car_model_id', 'car_model_name'];
                $where      = ['car_brand_id' => old('car_brand_id'), 'car_model_active' => 1];
                $order      = ['car_model_name' => 'asc'];
                $carModelListCollection = $carModel->getListSimple($select, $where, $order);

                if(!$carModelListCollection->isEmpty()){
                    $carModelListArray = [0 => trans('search.Select Car Model')];
                    foreach ($carModelListCollection as $k => $v){
                        $carModelListArray[$v->car_model_id] = $v->car_model_name;
                    }
                }
            }
        }

        $adDetail->ad_category_info = $this->category->getParentsByIdFlat($adDetail->category_id);
        $adDetail->pics = AdPic::where('ad_id', $adDetail->ad_id)->get();

        return view('admin.ad.ad_edit', [
            'ad_detail' => $adDetail,
            'ad_pic' => $adPic,
            'c' => $this->category->getAllHierarhy(),
            'l' => $this->location->getAllHierarhy(),
            'at' => AdType::all(),
            'ac' => AdCondition::all(),
            'estate_construction_type' => EstateConstructionType::all(),
            'estate_furnishing_type' => EstateFurnishingType::all(),
            'estate_heating_type' => EstateHeatingType::all(),
            'estate_type' => EstateType::all(),
            'car_brand_id' => CarBrand::all(),
            'car_model_id' => $carModelListArray,
            'car_engine_id' => CarEngine::all(),
            'car_transmission_id' => CarTransmission::all(),
            'car_condition_id' => CarCondition::all(),
            'car_modification_id' => CarModification::all(),
            'clothes_sizes' => ClothesSize::allCached('clothes_size_ord'),
            'shoes_sizes' => ShoesSize::allCached('shoes_size_ord')
        ]);
    }

    public function save(AdPostRequest $request)
    {
        $adData = $request->all();

        //fill additional fields
        $adData['ad_description'] = Util::nl2br(strip_tags($adData['ad_description']));
        if(!isset($adData['ad_active'])){
            $adData['ad_active'] = 0;
        } else {
            $adData['ad_active'] = 1;
        }
        if(!isset($adData['ad_promo'])){
            $adData['ad_promo'] = 0;
            $adData['ad_promo_until'] = NULL;
        } else {
            $adData['ad_promo'] = 1;
        }

        switch ($adData['category_type']){
            case 1:
                if($adData['price_radio'] == 1){
                    $adData['ad_price'] = $adData['ad_price_type_1'];
                    $adData['ad_free'] = 0;
                } else {
                    $adData['ad_price'] = 0;
                    $adData['ad_free'] = 1;
                }
                $adData['condition_id'] = $adData['condition_id_type_1'];
                break;
            case 2:
                $adData['ad_price'] = $adData['ad_price_type_2'];
                $adData['condition_id'] = $adData['condition_id_type_2'];
                break;
            case 3:
                $adData['ad_price'] = $adData['ad_price_type_3'];
                $adData['condition_id'] = $adData['condition_id_type_3'];
                break;
            case 4:
                if($adData['price_radio_type_4'] == 1){
                    $adData['ad_price'] = $adData['ad_price_type_4'];
                    $adData['ad_free'] = 0;
                } else {
                    $adData['ad_price'] = 0;
                    $adData['ad_free'] = 1;
                }
                break;
            case 5:
                if($adData['price_radio_type_5'] == 1){
                    $adData['ad_price'] = $adData['ad_price_type_5'];
                    $adData['ad_free'] = 0;
                } else {
                    $adData['ad_price'] = 0;
                    $adData['ad_free'] = 1;
                }
                $adData['condition_id'] = $adData['condition_id_type_5'];
                break;
            case 6:
                if($adData['price_radio_type_6'] == 1){
                    $adData['ad_price'] = $adData['ad_price_type_6'];
                    $adData['ad_free'] = 0;
                } else {
                    $adData['ad_price'] = 0;
                    $adData['ad_free'] = 1;
                }
                $adData['condition_id'] = $adData['condition_id_type_6'];
                break;
            case 7:
                $adData['ad_price'] = $adData['ad_price_type_7'];
                $adData['estate_sq_m'] = $adData['estate_sq_m_type_7'];
                break;
            case 8:
                if($adData['price_radio_type_8'] == 1){
                    $adData['ad_price'] = $adData['ad_price_type_8'];
                    $adData['ad_free'] = 0;
                } else {
                    $adData['ad_price'] = 0;
                    $adData['ad_free'] = 1;
                }
                break;
        }

        $adData['ad_description_hash'] = md5($adData['ad_description']);

        //save ad
        $ad = Ad::find($adData['ad_id']);
        $ad->update($adData);

        /**
         * clear cache, set message, redirect to list
         */
        Cache::flush();
        session()->flash('message', trans('admin_common.Ad saved'));
        return redirect(url('admin/ad'));
    }

    public function delete(Request $request)
    {
        //locations to be deleted
        $data = [];

        //check for single delete
        if(isset($request->id)){
            $data[] = $request->id;
        }

        //check for mass delete if no single delete
        if(empty($data)){
            $data = $request->input('ad_id');
        }

        //delete
        if(!empty($data)){
            foreach ($data as $k => $v){
                $ad = Ad::where('ad_id', $v)->first();
                if(!empty($ad)){
                    //delete images
                    if(!empty($ad->ad_pic)){
                        @unlink(public_path('uf/adata/') . '740_' . $ad->ad_pic);
                        @unlink(public_path('uf/adata/') . '1000_' . $ad->ad_pic);
                    }

                    $more_pics = AdPic::where('ad_id', $ad->ad_id)->get();
                    if(!$more_pics->isEmpty()){
                        foreach ($more_pics as $km => $vm){
                            @unlink(public_path('uf/adata/') . '740_' . $vm->ad_pic);
                            @unlink(public_path('uf/adata/') . '1000_' . $vm->ad_pic);
                            $vm->delete();
                        }
                    }

                    $ad->delete();
                }
            }
            //clear cache, set message, redirect to list
            Cache::flush();
            session()->flash('message', trans('admin_common.Ads deleted'));
            return redirect(url('admin/ad'));
        }

        //nothing for deletion set message and redirect
        session()->flash('message', trans('admin_common.Nothing for deletion'));
        return redirect(url('admin/ad'));
    }

    public function deleteMainImage(Request $request)
    {
        $id = 0;

        if(isset($request->id)){
            $id = $request->id;
        }

        //delete
        if(!empty($id)){
            $ad = Ad::where('ad_id', $id)->first();
            if(!empty($ad)){
                //delete images
                if(!empty($ad->ad_pic)){
                    @unlink(public_path('uf/adata/') . '740_' . $ad->ad_pic);
                    @unlink(public_path('uf/adata/') . '1000_' . $ad->ad_pic);
                }
                $ad->ad_pic = '';
                $ad->save();
            }

            //clear cache, set message, redirect to list
            Cache::flush();
            return redirect(url('admin/ad/edit/' . $id));
        }

        return redirect(url('admin/ad/edit/' . $id));
    }

    public function deleteImage(Request $request)
    {
        $id = 0;
        $adId = 0;

        if(isset($request->id)){
            $id = $request->id;
        }

        if(isset($request->ad_id)){
            $adId = $request->ad_id;
        }

        //delete
        if(!empty($id) && !empty($adId)){

            $pic = AdPic::where('ad_id', $adId)->where('ad_pic_id', $id)->first();
            if($pic){
                @unlink(public_path('uf/adata/') . '740_' . $pic->ad_pic);
                @unlink(public_path('uf/adata/') . '1000_' . $pic->ad_pic);
                $pic->delete();
            }

            //clear cache, set message, redirect to list
            Cache::flush();
            return redirect(url('admin/ad/edit/' . $adId));
        }

        return redirect(url('admin/ad/edit/' . $adId));
    }

    public function banByIp(Request $request)
    {
        $id = 0;

        if(isset($request->id)){
            $id = $request->id;
        }

        if(!empty($id)){
            $ad = Ad::where('ad_id', $id)->first();
            if(!empty($ad)){
                try{
                    AdBanIp::create(['ban_ip' => $ad->ad_ip, 'ban_reason' => trans('admin_ad.You are banned')]);
                } catch (\Exception $e){}
            }

            //clear cache, set message, redirect to list
            Cache::flush();
            session()->flash('message', trans('admin_common.Banned IP saved'));
            return redirect(url('admin/ad'));
        }

        return redirect(url('admin/ad'));
    }

    public function banByMail(Request $request)
    {
        $id = 0;

        if(isset($request->id)){
            $id = $request->id;
        }

        if(!empty($id)){
            $ad = Ad::where('ad_id', $id)->first();
            if(!empty($ad)){
                try{
                    $data = ['ban_email' => $ad->ad_email, 'ban_reason' => trans('admin_ad.You are banned')];
                    AdBanEmail::create($data);
                    //send email to inform the user
                    Mail::send('emails.user_ban_email', ['data' => $data], function ($m) use ($data) {
                        $m->from('test@mylove.bg', 'dclassifieds banned');
                        $m->to($data['ban_email'])->subject('You are banner in DClassifieds');
                    });
                } catch (\Exception $e){}
            }

            //clear cache, set message, redirect to list
            Cache::flush();
            session()->flash('message', trans('admin_common.Banned Mail saved'));
            return redirect(url('admin/ad'));
        }

        return redirect(url('admin/ad'));
    }
}