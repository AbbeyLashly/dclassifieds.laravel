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
        $ad_id = $request->id;

        //get ad info
        $ad_detail = $this->ad->getAdDetail($ad_id, 0);

        $ad_detail->ad_price_type_1     = $ad_detail->ad_price_type_2 = $ad_detail->ad_price_type_3 = $ad_detail->ad_price_type_4 = $ad_detail->ad_price;
        $ad_detail->ad_price_type_5     = $ad_detail->ad_price_type_6 = $ad_detail->ad_price_type_7 = $ad_detail->ad_price;

        if($ad_detail->ad_price > 0){
            $ad_detail->price_radio = $ad_detail->price_radio_type_4 = $ad_detail->price_radio_type_5 = $ad_detail->price_radio_type_6 = 1;
        } elseif ($ad_detail->ad_free){
            $ad_detail->price_radio = $ad_detail->price_radio_type_4 = $ad_detail->price_radio_type_5 = $ad_detail->price_radio_type_6 = 2;
        }

        $ad_detail->condition_id_type_1 = $ad_detail->condition_id_type_3 = $ad_detail->condition_id;
        $ad_detail->estate_sq_m_type_7  = $ad_detail->estate_sq_m;
        $ad_detail->ad_description      = Util::br2nl($ad_detail->ad_description);

        //get ad pics
        $ad_pic = AdPic::where('ad_id', $ad_id)->get();

        $car_model_id = array();
        if(old('car_brand_id')){
            if(is_numeric(old('car_brand_id')) && old('car_brand_id') > 0){
                $car_models = CarModel::where('car_brand_id', old('car_brand_id'))->orderBy('car_model_name', 'asc')->get();
                if(!$car_models->isEmpty()){
                    $car_model_id = array(0 => 'Select Car Model');
                    foreach ($car_models as $k => $v){
                        $car_model_id[$v->car_model_id] = $v->car_model_name;
                    }
                }
            }
        }

        $ad_detail->ad_category_info = $this->category->getParentsByIdFlat($ad_detail->category_id);
        $ad_detail->pics = AdPic::where('ad_id', $ad_detail->ad_id)->get();

        return view('admin.ad.ad_edit', [
            'ad_detail' => $ad_detail,
            'ad_pic' => $ad_pic,
            'c' => $this->category->getAllHierarhy(),
            'l' => $this->location->getAllHierarhy(),
            'at' => AdType::all(),
            'ac' => AdCondition::all(),
            'estate_construction_type' => EstateConstructionType::all(),
            'estate_furnishing_type' => EstateFurnishingType::all(),
            'estate_heating_type' => EstateHeatingType::all(),
            'estate_type' => EstateType::all(),
            'car_brand_id' => CarBrand::all(),
            'car_model_id' => $car_model_id,
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
        $ad_data = $request->all();

        //fill aditional fields
        $ad_data['ad_description'] = Util::nl2br(strip_tags($ad_data['ad_description']));
        if(!isset($ad_data['ad_active'])){
            $ad_data['ad_active'] = 0;
        } else {
            $ad_data['ad_active'] = 1;
        }
        if(!isset($ad_data['ad_promo'])){
            $ad_data['ad_promo'] = 0;
            $ad_data['ad_promo_until'] = NULL;
        } else {
            $ad_data['ad_promo'] = 1;
        }

        switch ($ad_data['category_type']){
            case 1:
                if($ad_data['price_radio'] == 1){
                    $ad_data['ad_price'] = $ad_data['ad_price_type_1'];
                    $ad_data['ad_free'] = 0;
                } else {
                    $ad_data['ad_price'] = 0;
                    $ad_data['ad_free'] = 1;
                }
                $ad_data['condition_id'] = $ad_data['condition_id_type_1'];
                break;
            case 2:
                $ad_data['ad_price'] = $ad_data['ad_price_type_2'];
                $ad_data['condition_id'] = $ad_data['condition_id_type_2'];
                break;
            case 3:
                $ad_data['ad_price'] = $ad_data['ad_price_type_3'];
                $ad_data['condition_id'] = $ad_data['condition_id_type_3'];
                break;
            case 4:
                if($ad_data['price_radio_type_4'] == 1){
                    $ad_data['ad_price'] = $ad_data['ad_price_type_4'];
                    $ad_data['ad_free'] = 0;
                } else {
                    $ad_data['ad_price'] = 0;
                    $ad_data['ad_free'] = 1;
                }
                break;
            case 5:
                if($ad_data['price_radio_type_5'] == 1){
                    $ad_data['ad_price'] = $ad_data['ad_price_type_5'];
                    $ad_data['ad_free'] = 0;
                } else {
                    $ad_data['ad_price'] = 0;
                    $ad_data['ad_free'] = 1;
                }
                $ad_data['condition_id'] = $ad_data['condition_id_type_5'];
                break;
            case 6:
                if($ad_data['price_radio_type_6'] == 1){
                    $ad_data['ad_price'] = $ad_data['ad_price_type_6'];
                    $ad_data['ad_free'] = 0;
                } else {
                    $ad_data['ad_price'] = 0;
                    $ad_data['ad_free'] = 1;
                }
                $ad_data['condition_id'] = $ad_data['condition_id_type_6'];
                break;
            case 7:
                $ad_data['ad_price'] = $ad_data['ad_price_type_7'];
                $ad_data['estate_sq_m'] = $ad_data['estate_sq_m_type_7'];
                break;
        }

        $ad_data['ad_description_hash'] = md5($ad_data['ad_description']);

        //save ad
        $ad = Ad::find($ad_data['ad_id']);
        $ad->update($ad_data);

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

    public function deletemainimg(Request $request)
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

    public function deleteimg(Request $request)
    {
        $id = 0;
        $ad_id = 0;

        if(isset($request->id)){
            $id = $request->id;
        }

        if(isset($request->ad_id)){
            $ad_id = $request->ad_id;
        }

        //delete
        if(!empty($id) && !empty($ad_id)){

            $pic = AdPic::where('ad_id', $ad_id)->where('ad_pic_id', $id)->first();
            if($pic){
                @unlink(public_path('uf/adata/') . '740_' . $pic->ad_pic);
                @unlink(public_path('uf/adata/') . '1000_' . $pic->ad_pic);
                $pic->delete();
            }

            //clear cache, set message, redirect to list
            Cache::flush();
            return redirect(url('admin/ad/edit/' . $ad_id));
        }

        return redirect(url('admin/ad/edit/' . $ad_id));
    }

    public function banbyip(Request $request)
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

    public function banbymail(Request $request)
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
