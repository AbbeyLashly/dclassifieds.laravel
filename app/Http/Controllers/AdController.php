<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Input;

use App\Http\Requests;
use App\Http\Requests\AdPostRequest;
use App\Http\Controllers\Controller;
use App\Http\Dc\Util;

use App\Category;
use App\Location;
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
use App\User;
use App\UserMail;
use App\UserMailStatus;
use App\AdReport;
use App\AdFav;
use App\Pay;
use App\MagicKeywords;
use App\Wallet;

use Image;
use Validator;
use Mail;
use DB;
use Auth;
use Cookie;
use Cache;

class AdController extends Controller
{
    protected $categoryModel;
    protected $locationModel;
    protected $adModel;
    protected $userModel;
    protected $mailModel;

    public function __construct(Category $categoryModel, Location $locationModel, Ad $adModel, User $userModel, UserMail $mailModel)
    {
        $this->categoryModel    = $categoryModel;
        $this->locationModel    = $locationModel;
        $this->adModel          = $adModel;
        $this->userModel        = $userModel;
        $this->mailModel        = $mailModel;
    }

    /**
     * Show home page
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        //is there selected location
        $lid = session()->get('lid', 0);

        //generate category url with location if selected
        $firstLevelChilds = $this->categoryModel->getOneLevel();
        if(!empty($firstLevelChilds)){
            foreach ($firstLevelChilds as $k => &$v){
                $categoryUrlParams = [];
                $categoryUrlParams[] = $this->categoryModel->getCategoryFullPathById($v->category_id);
                if(session()->has('location_slug')){
                    $categoryUrlParams[] = 'l-' . session()->get('location_slug');
                }

                if(!empty($categoryUrlParams)){
                    $v->category_url = Util::buildUrl($categoryUrlParams);
                }
            }
        }

        //get home page promo ads
        $where = ['ad_promo' => 1, 'ad_active' => 1];

        //check if location is selected and get all childs
        $allLocationChilds = [];
        $whereIn = [];
        if($lid > 0){
            $alc = $this->locationModel->getAllHierarhyFlat($lid);
            if (!empty($alc)) {
                foreach ($alc as $ak => $av) {
                    $allLocationChilds[] = $av['lid'];
                }
            }
            $allLocationChilds[] = $lid;
            $whereIn['ad.location_id'] = $allLocationChilds;
        }

        $order = ['ad_publish_date' => 'desc'];
        $limit = config('dc.num_promo_ads_home_page');
        $promoAdList = $this->adModel->getAdList($where, $order, $limit, [], $whereIn);

        if($promoAdList->count() < config('dc.num_promo_ads_home_page')){
            $where['ad_promo'] = 0;
            $limit = config('dc.num_promo_ads_home_page') - $promoAdList->count();
            $promoAdList = $promoAdList->merge($this->adModel->getAdList($where, $order, $limit, [], $whereIn));
        }

        //if enable latest ads on home page get some new ads
        $latestAdList = new Collection();
        if(config('dc.enable_new_ads_on_homepage')){
            $where['ad_promo'] = 0;
            $limit = config('dc.num_latest_ads_home_page');
            $latestAdList = $this->adModel->getAdList($where, $order, $limit, [], $whereIn);
        }

        /**
         * get ad count by category and selected filter
         */
        if(!$firstLevelChilds->isEmpty()){
            unset($where['ad_promo']);
            if($lid > 0){
                $whereIn['ad.location_id'] = $allLocationChilds;
            }

            foreach($firstLevelChilds as $k => &$v){

                $thisCategoryChilds = [];
                //get this category childs
                $acc = $this->categoryModel->getAllHierarhyFlat($v->category_id);
                if(!empty($acc)){
                    foreach($acc as $ak => $av){
                        $thisCategoryChilds[] = $av['cid'];
                    }
                }
                $thisCategoryChilds[] = $v->category_id;
                $whereIn['category_id'] = $thisCategoryChilds;
                $v->ad_count = $this->adModel->getAdCount($where, $whereIn);
            }
        }

        /**
         * magic keywords
         */
        $magicKeywords = new Collection();
        if(config('dc.enable_magic_keywords')){
            $order = ['keyword_count' => 'DESC'];
            $limit = config('dc.num_magic_keywords_to_show');
            $mkModel = new MagicKeywords();
            $magicKeywords = $mkModel->getList($order, $limit);
        }

        return view('ad.home',[
            'categoryList'      => $this->categoryModel->getAllHierarhy(),
            'locationList'      => $this->locationModel->getAllHierarhy(),
            'firstLevelChilds'  => $firstLevelChilds,
            'lid'               => $lid,
            'promoAdList'       => $promoAdList,
            'latestAdList'      => $latestAdList,
            'magicKeywords'     => $magicKeywords
        ]);

    }

    /**
     * Get search form data and convert url to seo friendly and redirect to search action
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function proxy(Request $request)
    {
        //root url / base url
        $root = $request->root();

        //generated url if no parameters redirect to search
        $redirectUrl = url('search');

        //generated url parameters container
        $urlParams = array();

        //get incoming parameters
        $params = Input::all();

        //check for category selection
        $cid = 0;
        if(isset($params['cid']) && $params['cid'] > 0){
            $cid = $params['cid'];
            $categorySlug = $this->categoryModel->getCategoryFullPathById($cid);
            $urlParams[] = $categorySlug;
            unset($params['cid']);
        }

        //check for location selection
        $lid = 0;
        if(isset($params['lid']) && $params['lid'] > 0){
            $lid = $params['lid'];
            $locationSlug = $this->locationModel->getSlugById($lid);
            $urlParams[] = 'l-' . $locationSlug;
            unset($params['lid']);
            session()->put('lid', $lid);
            session()->put('location_slug', $locationSlug);
        } else {
            if(session()->has('lid')){
                session()->forget('lid');
            }

            if(session()->has('location_slug')){
                session()->forget('location_slug');
            }
        }

        //check for search text
        $searchText = '';
        if(isset($params['search_text'])){
            $searchTextTmp = Util::sanitize($params['search_text']);
            if(!empty($searchTextTmp) && mb_strlen($searchTextTmp, 'utf-8') > 3){
                $searchText = $searchTextTmp;
                $searchText = preg_replace('/\s+/', '-', $searchText);
                $urlParams[] = 'q-' . $searchText;
            }
            unset($params['search_text']);
        }

        //generate new url for redirection
        if(!empty($urlParams)){
            $redirectUrl = Util::buildUrl($urlParams);
        }

        //check if there are parameters for query string if other category selected do not add
        $queryString = '';
        if(session('old_cid') == $cid) {
            if (!empty($params)) {
                //clear token var if exist
                if (isset($params['_token'])) {
                    unset($params['_token']);
                }
                $queryString = Util::getQueryStringFromArray($params);
            }
        }

        //add query string to generated url
        if(!empty($queryString)){
            $redirectUrl .= '?' . $queryString;
        }

        //save the selected category
        if($cid > 0){
            session(['old_cid' => $cid]);
        }

        return redirect($redirectUrl);
    }

    /**
     * Search ads
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function search(Request $request)
    {
        //get incoming params
        $params = Input::all();

        //Flash the input for the current request to the session.
        $request->flash();

        //page title container
        $title = [config('dc.site_domain')];

        //check if there is car brand selected
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

        //breadcrump container
        $breadcrump = array();

        //check if category selected
        $categorySlug = '';
        if(isset($request->category_slug)){
            $categorySlug = Util::sanitize($request->category_slug);
        }

        $cid = 0;
        if(!empty($categorySlug)){
            $cid = $this->categoryModel->getCategoryIdByFullPath($categorySlug);
            if (empty($cid)) {
                abort(404);
            }

            if($categorySlug != $this->categoryModel->getCategoryFullPathById($cid)){
                abort(404);
            }
        }

        //if category selected get info, get childs and generate url and breadcrump
        $firstLevelChilds = new Collection();
        $allCategoryChilds = [];
        if($cid > 0){
            $params['cid'] = $cid;
            $selectedCategoryInfo = Category::where('category_id', $cid)->first();

            //get first childs info and generate links
            $firstLevelChilds = $this->categoryModel->getOneLevel($cid);
            foreach ($firstLevelChilds as $k => &$v){

                $categoryUrlParams = [];
                $categoryUrlParams[] = $this->categoryModel->getCategoryFullPathById($v->category_id);
                if(session()->has('location_slug')){
                    $categoryUrlParams[] = 'l-' . session()->get('location_slug');
                }

                if(!empty($categoryUrlParams)){
                    $v->category_url = Util::buildUrl($categoryUrlParams);
                }
            }

            //generate breadcrump info
            $breadcrumpData = $this->categoryModel->getParentsByIdFlat($cid);
            if(!empty($breadcrumpData)){
                $categoryTitleArray = [];
                foreach ($breadcrumpData as $k => &$v){

                    $categoryUrlParams = [];
                    $categoryUrlParams[] = $this->categoryModel->getCategoryFullPathById($v['category_id']);
                    if(session()->has('location_slug')){
                        $categoryUrlParams[] = 'l-' . session()->get('location_slug');
                    }

                    if(!empty($categoryUrlParams)){
                        $v['category_url'] = Util::buildUrl($categoryUrlParams);
                    }

                    $categoryTitleArray[] = $v['category_title'];
                }

                //category part of breadcrump
                $breadcrump['c'] = array_reverse($breadcrumpData);

                //category part of page title
                $title[] = join(' / ', array_reverse($categoryTitleArray));
            }

            $acc = $this->categoryModel->getAllHierarhyFlat($cid);
            if(!empty($acc)){
                foreach($acc as $ak => $av){
                    $allCategoryChilds[] = $av['cid'];
                }
            }
            $allCategoryChilds[] = $cid;
        }

        //check for location selection
        $locationSlug = '';
        if(isset($request->location_slug)){
            $locationSlug = Util::sanitize($request->location_slug);
        }

        $lid = 0;
        if(!empty($locationSlug)){
            $lid = $this->locationModel->getIdBySlug($locationSlug);
            if (empty($lid)) {
                abort(404);
            }
            $params['lid'] = $lid;

            //get info for title
            $locationInfo = $this->locationModel->getLocationInfo($lid);
            if(!empty($locationInfo)){
                $title[] = $locationInfo->location_name;
            }
        }

        //if location selected get all child locations
        $allLocationChilds = [];
        if($lid > 0) {
            $alc = $this->locationModel->getAllHierarhyFlat($lid);
            if (!empty($alc)) {
                foreach ($alc as $ak => $av) {
                    $allLocationChilds[] = $av['lid'];
                }
            }
            $allLocationChilds[] = $lid;
        }

        //check for search text
        $searchText = '';
        $searchTextTmp = '';
        if(isset($request->search_text)){
            $searchTextTmp = Util::sanitize($request->search_text);
        }

        if(!empty($searchTextTmp) && mb_strlen($searchTextTmp, 'utf-8') > 3){
            $searchText = preg_replace('/-/', ' ', $searchTextTmp);
            $params['search_text'] = $searchText;
            $title[] = $searchText;
        }

        /*
         * init where vars
         */
        $where      = [];
        $order      = [];
        $limit      = 0;
        $orderRaw   = '';
        $whereIn    = [];
        $whereRaw   = [];
        $paginate   = 0;
        $page       = 1;

        /*
         * check common filters and set them in where array
         */
        if(isset($params['condition_id']) && !empty($params['condition_id']) && is_array($params['condition_id'])){
            $whereIn['condition_id'] = $params['condition_id'];
        }

        if(isset($params['type_id']) && !empty($params['type_id']) && is_array($params['type_id'])){
            $whereIn['type_id'] = $params['type_id'];
        }

        if(isset($params['price_from']) && is_numeric($params['price_from']) && $params['price_from'] > 0){
            $where['ad_price'] = ['>=', $params['price_from']];
        }

        if(isset($params['price_to']) && is_numeric($params['price_to']) && $params['price_to'] > 0){
            $where['ad_price'] = ['<=', $params['price_to']];
        }

        if(isset($params['price_free']) && is_numeric($params['price_free']) && $params['price_free'] > 0){
            $where['ad_free'] = ['=', 1];
        }

        //type 2 filters - real estates
        if(isset($params['estate_type_id']) && !empty($params['estate_type_id']) && is_array($params['estate_type_id'])){
            $whereIn['estate_type_id'] = $params['estate_type_id'];
        }

        if(isset($params['estate_sq_m_from']) && is_numeric($params['estate_sq_m_from']) && $params['estate_sq_m_from'] > 0){
            $where['estate_sq_m'] = ['>=', $params['estate_sq_m_from']];
        }

        if(isset($params['estate_sq_m_to']) && is_numeric($params['estate_sq_m_to']) && $params['estate_sq_m_to'] > 0){
            $where['estate_sq_m'] = ['<=', $params['estate_sq_m_to']];
        }

        if(isset($params['estate_year_from']) && is_numeric($params['estate_year_from']) && $params['estate_year_from'] > 0){
            $where['estate_year'] = ['>=', $params['estate_year_from']];
        }

        if(isset($params['estate_year_to']) && is_numeric($params['estate_year_to']) && $params['estate_year_to'] > 0){
            $where['estate_year'] = ['<=', $params['estate_year_to']];
        }

        if(isset($params['estate_construction_type_id']) && !empty($params['estate_construction_type_id']) && is_array($params['estate_construction_type_id'])){
            $whereIn['estate_construction_type_id'] = $params['estate_construction_type_id'];
        }

        if(isset($params['estate_heating_type_id']) && !empty($params['estate_heating_type_id']) && is_array($params['estate_heating_type_id'])){
            $whereIn['estate_heating_type_id'] = $params['estate_heating_type_id'];
        }

        if(isset($params['estate_floor_from']) && is_numeric($params['estate_floor_from']) && $params['estate_floor_from'] > 0){
            $where['estate_floor'] = ['>=', $params['estate_floor_from']];
        }

        if(isset($params['estate_floor_to']) && is_numeric($params['estate_floor_to']) && $params['estate_floor_to'] > 0){
            $where['estate_floor'] = ['<=', $params['estate_floor_to']];
        }

        if(isset($params['estate_num_floors_in_building']) && is_numeric($params['estate_num_floors_in_building']) && $params['estate_num_floors_in_building'] > 0){
            $where['estate_num_floors_in_building'] = $params['estate_num_floors_in_building'];
        }

        if(isset($params['estate_furnishing_type_id']) && !empty($params['estate_furnishing_type_id']) && is_array($params['estate_furnishing_type_id'])){
            $whereIn['estate_furnishing_type_id'] = $params['estate_furnishing_type_id'];
        }

        //type 3 filters - cars
        if(isset($params['car_engine_id']) && !empty($params['car_engine_id']) && is_array($params['car_engine_id'])){
            $whereIn['car_engine_id'] = $params['car_engine_id'];
        }

        if(isset($params['car_brand_id']) && is_numeric($params['car_brand_id']) && $params['car_brand_id'] > 0){
            $where['car_brand_id'] = $params['car_brand_id'];
        }

        if(isset($params['car_model_id']) && is_numeric($params['car_model_id']) && $params['car_model_id'] > 0){
            $where['car_model_id'] = $params['car_model_id'];
        }

        if(isset($params['car_transmission_id']) && !empty($params['car_transmission_id']) && is_array($params['car_transmission_id'])){
            $whereIn['car_transmission_id'] = $params['car_transmission_id'];
        }

        if(isset($params['car_modification_id']) && !empty($params['car_modification_id']) && is_array($params['car_modification_id'])){
            $whereIn['car_modification_id'] = $params['car_modification_id'];
        }

        if(isset($params['car_year_from']) && is_numeric($params['car_year_from']) && $params['car_year_from'] > 0){
            $where['car_year'] = ['>=', $params['car_year_from']];
        }

        if(isset($params['car_year_to']) && is_numeric($params['car_year_to']) && $params['car_year_to'] > 0){
            $where['car_year'] = ['<=', $params['car_year_to']];
        }

        if(isset($params['car_kilometeres_from']) && is_numeric($params['car_kilometeres_from']) && $params['car_kilometeres_from'] > 0){
            $where['car_kilometeres'] = ['>=', $params['car_kilometeres_from']];
        }

        if(isset($params['car_kilometeres_to']) && is_numeric($params['car_kilometeres_to']) && $params['car_kilometeres_to'] > 0){
            $where['car_kilometeres'] = ['<=', $params['car_kilometeres_to']];
        }

        if(isset($params['car_condition_id']) && !empty($params['car_condition_id']) && is_array($params['car_condition_id'])){
            $whereIn['car_condition_id'] = $params['car_condition_id'];
        }

        //type 5 filters - clothes
        if(isset($params['clothes_size_id']) && !empty($params['clothes_size_id']) && is_array($params['clothes_size_id'])){
            $whereIn['clothes_size_id'] = $params['clothes_size_id'];
        }

        //type 6 filters - shoes
        if(isset($params['shoes_size_id']) && !empty($params['shoes_size_id']) && is_array($params['shoes_size_id'])){
            $whereIn['shoes_size_id'] = $params['shoes_size_id'];
        }

        $showOnlyPromo = 0;
        if(isset($params['promo_ads']) && !empty($params['promo_ads'])){
            $where['ad_promo'] = 1;
            $showOnlyPromo = 1;
        }

        /*
         * get promo ads
         */
        $where['ad_promo'] = 1;
        $where['ad_active'] = 1;
        if($lid > 0){
            $whereIn['ad.location_id'] = $allLocationChilds;
        }
        if($cid > 0){
            $whereIn['category_id'] = $allCategoryChilds;
        }
        if(!empty($searchText)){
            $whereRaw['match(ad_title, ad_description) against(?)'] = [$searchText];
        }
        $orderRaw = 'rand()';
        $limit = config('dc.num_promo_ads_list');
        $promoAdList = $this->adModel->getAdList($where, $order, $limit, $orderRaw, $whereIn, $whereRaw, $paginate);

        /*
         * get normal ads
         */
        if(!$showOnlyPromo) {
            $where['ad_promo'] = 0;
        }
        $limit      = 0;
        $orderRaw   = '';
        $order      = ['ad_publish_date' => 'desc'];
        $paginate   = config('dc.num_ads_list');
        if (isset($params['page']) && is_numeric($params['page'])) {
            $page = $params['page'];
        }
        $title[] = trans('search.Page:') . ' ' . $page;

        $adList = $this->adModel->getAdList($where, $order, $limit, $orderRaw, $whereIn, $whereRaw, $paginate, $page);

        /**
         * magic keywords
         */
        if(!empty($searchText) && config('dc.enable_magic_keywords')){
            unset($where['ad_promo']);
            $adCount = $this->adModel->getAdCount($where, $whereIn, $whereRaw);
            if($adCount >= config('dc.minimum_results_to_save_magic_keyword')){
                $mkModel = new MagicKeywords();
                $mkCount = $mkModel->where('keyword', $searchText)->count();
                if($mkCount == 0){
                    $mkModel->keyword = $searchText;
                    $mkModel->save();
                } else {
                    $mkModel->where('keyword', $searchText)->increment('keyword_count', 1);
                }
            }
        }

        /**
         * get ad count by category and selected filter
         */
        if(!$firstLevelChilds->isEmpty()){
            unset($where['ad_promo']);

            foreach($firstLevelChilds as $k => &$v){

                $thisCategoryChilds = [];
                //get this category childs
                $acc = $this->categoryModel->getAllHierarhyFlat($v->category_id);
                if(!empty($acc)){
                    foreach($acc as $ak => $av){
                        $thisCategoryChilds[] = $av['cid'];
                    }
                }
                $thisCategoryChilds[] = $v->category_id;
                $whereIn['category_id'] = $thisCategoryChilds;
                $v->ad_count = $this->adModel->getAdCount($where, $whereIn, $whereRaw);
            }
        }

        /**
         * put all view vars in array
         */
        $viewParams = [
            'categoryList'      => $this->categoryModel->getAllHierarhy(), //all categories hierarhy
            'locationList'      => $this->locationModel->getAllHierarhy(), //all location hierarhy
            'params'            => $params, //incoming search params
            'cid'               => $cid, //selected category
            'lid'               => $lid, //selected location
            'search_text'       => $searchText, //search text
            'firstLevelChilds'  => $firstLevelChilds, //selected category one level childs
            'breadcrump'        => $breadcrump, //breadcrump data
            'promoAdList'       => $promoAdList, //promo ads
            'adList'            => $adList, //standard ads
            'showOnlyPromo'     => $showOnlyPromo, //show only promo ads
            'title'             => $title, //generated page title array

            //filter vars
            'adTypeList'                    => AdType::allCached('ad_type_name'),
            'adConditionList'               => AdCondition::allCached('ad_condition_name'),
            'estateConstructionTypeList'    => EstateConstructionType::allCached('estate_construction_type_name'),
            'estateFurnishingTypeList'      => EstateFurnishingType::allCached('estate_furnishing_type_name'),
            'estateHeatingTypeList'         => EstateHeatingType::allCached('estate_heating_type_name'),
            'estateTypeList'                => EstateType::allCached('estate_type_name'),
            'carBrandList'                  => CarBrand::allCached('car_brand_name'),
            'carModelList'                  => $carModelListArray,
            'carEngineList'                 => CarEngine::allCached('car_engine_name'),
            'carTransmissionList'           => CarTransmission::allCached('car_transmission_name'),
            'carConditionList'              => CarCondition::allCached('car_condition_name'),
            'carModificationList'           => CarModification::allCached('car_modification_name'),
            'clothesSizesList'              => ClothesSize::allCached('clothes_size_ord'),
            'shoesSizesList'                => ShoesSize::allCached('shoes_size_ord')
        ];

        if($cid > 0){
            $viewParams['selected_category_info'] = $selectedCategoryInfo;
        }

        if($lid > 0){
            $viewParams['location_info'] = $locationInfo;
        }

        return view('ad.search', $viewParams);
    }

    /**
     * Show ad detail info
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function detail(Request $request)
    {
        //get ad id
        $adId = $request->ad_id;
        
        //get ad info and increment num views
        $adDetail = $this->adModel->getAdDetail($adId);

        //check original slug and url slug
        $requestSlug = $request->ad_slug;
        $originalSlug  = str_slug($adDetail->ad_title);
        if($requestSlug != $originalSlug){
            $adUrl = url(str_slug($adDetail->ad_title) . '-' . 'ad' . $adDetail->ad_id . '.html');
            return redirect($adUrl);
        }

        $adDetail->increment('ad_view', 1);

        if(!empty($adDetail->ad_video)){
            $adDetail->ad_video_fixed = Util::getVideoReady($adDetail->ad_video);
        }
        
        //get ad pics
        $adPicModel = new AdPic();
        $where = [];
        $order = [];
        $where['ad_id'] = $adId;
        $order['ad_pic_id'] = 'ASC';
        $adPic = $adPicModel->getAdPics($where, $order);
        
        //get this user other ads
        $where = [];
        $order = [];
        $where['ad_active'] = 1;
        $where['ad_id'] = ['!=', $adDetail->ad_id];
        $order['ad_publish_date'] = 'desc';
        $limit = config('dc.num_addition_ads_from_user');
        $otherAds = $this->adModel->getAdList($where, $order, $limit);
        
        //save ad for last view
        $lastViewAd = [
            'ad_id'         => $adDetail->ad_id,
            'ad_title'      => $adDetail->ad_title,
            'location_name' => $adDetail->location_name,
            'ad_price'      => $adDetail->ad_price,
            'ad_pic'        => $adDetail->ad_pic,
            'ad_promo'      => $adDetail->ad_promo
        ];

        if(session()->has('last_view')){
            $lastViewArray = session('last_view');
            $addToLastView = 1;

            //check if this ad is in last view
            foreach($lastViewArray as $k => $v){
                if($v['ad_id'] == $lastViewAd['ad_id']){
                    $addToLastView = 0;
                    break;
                }
            }

            if($addToLastView) {
                $lastViewArray[] = $lastViewAd;
                if (count($lastViewArray) > config('dc.num_last_viewed_ads')) {
                    //reindex the array
                    $lastViewArray = array_values($lastViewArray);
                    //remove oldest ad
                    unset($lastViewArray[0]);
                }
                session()->put('last_view', $lastViewArray);
            }
        } else {
            $lastViewArray = [];
            $lastViewArray[] = $lastViewAd;
            session()->put('last_view', $lastViewArray);
        }

        //generate breadcrump
        $breadcrump = array();
        $breadcrumpData = $this->categoryModel->getParentsByIdFlat($adDetail->category_id);
        if(!empty($breadcrumpData)){
            foreach ($breadcrumpData as $k => &$v){
                $categoryUrlParams = array();
                $categoryUrlParams[] = $this->categoryModel->getCategoryFullPathById($v['category_id']);
                if(session()->has('location_slug')){
                    $categoryUrlParams[] = 'l-' . session()->get('location_slug');
                }
        
                if(!empty($categoryUrlParams)){
                    $v['category_url'] = Util::buildUrl($categoryUrlParams);
                }
            }
            //category part of breadcrump
            $breadcrump['c'] = array_reverse($breadcrumpData);
        }
        
        //check if ad is in favorites
        $adFav = 0;
        $favAdsInfo = [];
        //is there user
        if(Auth::check()){
            $adFavModel = new AdFav();
            $favAdsInfo = $adFavModel->getFavAds($request->user()->user_id);
        } else if(Cookie::has('__' . md5(config('dc.site_domain')) . '_fav_ads')) {
            //no user check cookie
            $favAdsInfo = $request->cookie('__' . md5(config('dc.site_domain')) . '_fav_ads', array());
        }
        if(isset($favAdsInfo[$adId])){
            $adFav = 1;
        }

        //generate title
        $title = [config('dc.site_domain')];
        $title[] = $adDetail->ad_title;
        $title[] = trans('detail.Ad Id') . ': ' . $adDetail->ad_id;
        
        return view('ad.detail', [
            'adDetail'      => $adDetail,
            'adPic'         => $adPic,
            'otherAds'      => $otherAds,
            'breadcrump'    => $breadcrump,
            'adFav'         => $adFav,
            'title'         => $title
        ]);
    }

    /**
     * Show ad publish form
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getPublish()
    {
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

        //get current user or make empty class
        $user = new \stdClass();
        if(Auth::check()){
            $user = Auth::user();
        }

        //set page title
        $title = [config('dc.site_domain')];
        $title[] = trans('publish_edit.Post an ad');

        //check if promo ads are enabled
        $payment_methods = new Collection();
        if(config('dc.enable_promo_ads')){
            $where['pay_active']    = 1;
            $order['pay_ord']       = 'ASC';
            $payModel               = new Pay();
            $payment_methods        = $payModel->getList($where, $order);
        }

        //check if promo ads are enabled, check if there is logged user
        //check if there are enough money in the wallet
        $enable_pay_from_wallet = 0;
        if(config('dc.enable_promo_ads') && Auth::check()){
            //no caching for the wallet :)
            $wallet_total = Wallet::where('user_id', Auth::user()->user_id)->sum('sum');
            if(number_format($wallet_total, 2, '.', '') >= number_format(config('dc.wallet_promo_ad_price'), 2, '.', '')){
                $enable_pay_from_wallet = 1;
            }
        }

        $firstLevelChilds = $this->categoryModel->getOneLevel();
        $location_first_level_childs = $this->locationModel->getOneLevel();

        /**
         * put all view vars in array
         */
        $view_params = [
            'c'     => $this->categoryModel->getAllHierarhy(), //all categories hierarhy
            'l'     => $this->locationModel->getAllHierarhy(), //all location hierarhy
            'user'  => $user, //user object or empty class,
            'title' => $title, //set the page title
            'payment_methods' => $payment_methods, //get payment methods
            'enable_pay_from_wallet' => $enable_pay_from_wallet, //enable/disable wallet promo ad pay
            'first_level_childs' => $firstLevelChilds, //first level categories
            'location_first_level_childs' => $location_first_level_childs, //first level locations

            //filter vars
            'at'                        => AdType::allCached('ad_type_name'),
            'ac'                        => AdCondition::allCached('ad_condition_name'),
            'estate_construction_type'  => EstateConstructionType::allCached('estate_construction_type_name'),
            'estate_furnishing_type'    => EstateFurnishingType::allCached('estate_furnishing_type_name'),
            'estate_heating_type'       => EstateHeatingType::allCached('estate_heating_type_name'),
            'estate_type'               => EstateType::allCached('estate_type_name'),
            'car_brand'                 => CarBrand::allCached('car_brand_name'),
            'car_model'                 => $carModelListArray,
            'car_engine'                => CarEngine::allCached('car_engine_name'),
            'car_transmission'          => CarTransmission::allCached('car_transmission_name'),
            'car_condition'             => CarCondition::allCached('car_condition_name'),
            'car_modification'          => CarModification::allCached('car_modification_name'),
            'clothes_sizes'             => ClothesSize::allCached('clothes_size_ord'),
            'shoes_sizes'               => ShoesSize::allCached('shoes_size_ord')
        ];
        
        return view('ad.publish', $view_params);
    }

    /**
     * Save ad to database
     *
     * @param AdPostRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postPublish(AdPostRequest $request)
    {
        $ad_data = $request->all();

        //get user info by ad email or create new user
        $current_user_id = 0;
        if(Auth::check()){
            $current_user_id = Auth::user()->user_id;
            $user = Auth::user();
        } else {
            //check this mail for registered user
            try{
                $user = User::where('email', $ad_data['ad_email'])->firstOrFail();
            } catch (\Exception $e) {
                //no user create one
                //generate password
                $password = str_random(10);

                $user                   = new User();
                $user->name             = $ad_data['ad_publisher_name'];
                $user->email            = $ad_data['ad_email'];
                $user->user_phone       = $ad_data['ad_phone'];
                $user->user_skype       = $ad_data['ad_skype'];
                $user->user_site        = $ad_data['ad_link'];
                $user->user_location_id = $ad_data['location_id'];
                $user->user_address     = $ad_data['ad_address'];
                $user->user_lat_lng     = $ad_data['ad_lat_lng'];
                $user->password         = bcrypt($password);
                $user->user_activation_token = str_random(30);
                $user->save();

                //send activation mail
                Mail::send('emails.activation', ['user' => $user, 'password' => $password], function ($m) use ($user) {
                    $m->from(config('dc.site_contact_mail'), config('dc.site_domain'));
                    $m->to($user->email)->subject(trans('publish_edit.Activate your account!'));
                });
            }
            $current_user_id = $user->user_id;
        }

        //fill additional fields
        $ad_data['user_id']             = $current_user_id;
        $ad_data['ad_publish_date']     = date('Y-m-d H:i:s');
        $ad_data['ad_valid_until']      = date('Y-m-d', mktime(null, null, null, date('m'), date('d')+config('dc.ad_valid_period_in_days'), date('Y')));
        $ad_data['ad_ip']               = Util::getRemoteAddress();
        $ad_data['ad_description']      = Util::nl2br(strip_tags($ad_data['ad_description']));

        switch ($ad_data['category_type']){
            case 1:
                if(isset($ad_data['price_radio']) && $ad_data['price_radio'] == 1){
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
                if(isset($ad_data['price_radio_type_4']) && $ad_data['price_radio_type_4'] == 1){
                    $ad_data['ad_price'] = $ad_data['ad_price_type_4'];
                    $ad_data['ad_free'] = 0;
                } else {
                    $ad_data['ad_price'] = 0;
                    $ad_data['ad_free'] = 1;
                }
                break;
            case 5:
                if(isset($ad_data['price_radio_type_5']) && $ad_data['price_radio_type_5'] == 1){
                    $ad_data['ad_price'] = $ad_data['ad_price_type_5'];
                    $ad_data['ad_free'] = 0;
                } else {
                    $ad_data['ad_price'] = 0;
                    $ad_data['ad_free'] = 1;
                }
                $ad_data['condition_id'] = $ad_data['condition_id_type_5'];
                break;
            case 6:
                if(isset($ad_data['price_radio_type_6']) && $ad_data['price_radio_type_6'] == 1){
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

        //generate ad unique code
        do{
            $ad_data['code'] = str_random(30);
        } while (Ad::where('code', $ad_data['code'])->first());

        //create ad
        $ad = Ad::create($ad_data);

        //upload and fix ad images
        $ad_image = Input::file('ad_image');
        $destination_path = public_path('uf/adata/');
        $first_image_uploaded = 0;
        foreach ($ad_image as $k){
            if(!empty($k) && $k->isValid()){
                $file_name = $ad->ad_id . '_' .md5(time() + rand(0,9999)) . '.' . $k->getClientOriginalExtension();
                $k->move($destination_path, $file_name);

                $img    = Image::make($destination_path . $file_name);
                $width  = $img->width();
                $height = $img->height();

                if($width > 1000 || $height > 1000) {
                    if ($width == $height) {
                        $img->resize(1000, 1000);
                        if(config('dc.watermark')){
                            $img->insert(public_path('uf/settings/') . config('dc.watermark'), config('dc.watermark_position'));
                        }
                        $img->save($destination_path . '1000_' . $file_name);
                    } elseif ($width > $height) {
                        $img->resize(1000, null, function($constraint){
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
                        if(config('dc.watermark')){
                            $img->insert(public_path('uf/settings/') . config('dc.watermark'), config('dc.watermark_position'));
                        }
                        $img->save($destination_path . '1000_' . $file_name);
                    } elseif ($width < $height) {
                        $img->resize(null, 1000, function($constraint){
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
                        if(config('dc.watermark')){
                            $img->insert(public_path('uf/settings/') . config('dc.watermark'), config('dc.watermark_position'));
                        }
                        $img->save($destination_path . '1000_' . $file_name);
                    }
                } else {
                    if(config('dc.watermark')){
                        $img->insert(public_path('uf/settings/') . config('dc.watermark'), config('dc.watermark_position'));
                        $img->save($destination_path . '1000_' . $file_name);
                    } else {
                        $img->save($destination_path . '1000_' . $file_name);
                    }
                }

                if(!$first_image_uploaded){
                    if($width >= 720 || $height >= 720) {
                        $img->fit(720, 720);
                        $img->save($destination_path . '740_' . $file_name);
                    } else {
                        $img->resizeCanvas(720, 720, 'top');
                        $img->save($destination_path . '740_' . $file_name);
                    }
                    $ad->ad_pic = $file_name;
                    $ad->save();
                    $first_image_uploaded = 1;
                } else {
                    $adPic = new AdPic();
                    $adPic->ad_id = $ad->ad_id;
                    $adPic->ad_pic = $file_name;
                    $adPic->save();
                }

                @unlink($destination_path . $file_name);
            }
        }

        $ad->ad_category_info   = $this->categoryModel->getParentsByIdFlat($ad->category_id);
        $ad->ad_location_info   = $this->locationModel->getParentsByIdFlat($ad->location_id);
        $ad->pics               = AdPic::where('ad_id', $ad->ad_id)->get();
        $ad->same_ads           = Ad::where([['ad_description_hash', $ad->ad_description_hash], ['ad_id', '<>', $ad->ad_id]])->get();

        //send info and activation mail
        Mail::send('emails.ad_activation', ['user' => $user, 'ad' => $ad], function ($m) use ($user){
            $m->from(config('dc.site_contact_mail'), config('dc.site_domain'));
            $m->to($user->email)->subject(trans('publish_edit.Activate your ad!'));
        });

        //send control mail
        if(config('dc.enable_control_mails')) {
            Mail::send('emails.control_ad_activation', ['user' => $user, 'ad' => $ad], function ($m) {
                $m->from(config('dc.site_contact_mail'), config('dc.site_domain'));
                $m->to(config('dc.control_mail'))->subject(config('dc.control_mail_subject'));
            });
        }

        //if promo ads are enable check witch option is selected
        if(isset($ad_data['ad_type_pay'])){

            //wallet pay
            if($ad_data['ad_type_pay'] == 1000){
                //no caching for the wallet :)
                $wallet_total = Wallet::where('user_id', Auth::user()->user_id)->sum('sum');
                if(number_format($wallet_total, 2, '.', '') >= number_format(config('dc.wallet_promo_ad_price'), 2, '.', '')){
                    //calc promo period
                    $promoUntilDate = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')+config('dc.wallet_promo_ad_period'), date('Y')));

                    //unset some ad fields
                    unset($ad->ad_category_info);
                    unset($ad->ad_location_info);
                    unset($ad->pics);
                    unset($ad->same_ads);

                    //make ad promo and activate it
                    $ad->ad_promo = 1;
                    $ad->ad_promo_until = $promoUntilDate;
                    $ad->ad_active = 1;
                    $ad->save();

                    //subtract money from wallet
                    $wallet_data = ['user_id' => $ad->user_id,
                        'ad_id' => $ad->ad_id,
                        'sum' => -number_format(config('dc.wallet_promo_ad_price'), 2, '.', ''),
                        'wallet_date' => date('Y-m-d H:i:s'),
                        'wallet_description' => trans('payment_fortumo.Your ad #:ad_id is Promo Until :date.', ['ad_id' => $ad->ad_id, 'date' => $promoUntilDate])
                    ];
                    Wallet::create($wallet_data);
                    Cache::flush();

                    $message[] = trans('payment_fortumo.Your ad #:ad_id is Promo Until :date.', ['ad_id' => $ad->ad_id, 'date' => $promoUntilDate]);
                    $message[] = trans('publish_edit.Your ad is activated');
                    $message[] = trans('publish_edit.Click here to publish new ad', ['link' => route('publish')]);
                }
            } else {
                $where['pay_active'] = 1;
                $order['pay_ord'] = 'ASC';
                $payModel = new Pay();
                $payment_methods = $payModel->getList($where, $order);
                if (!$payment_methods->isEmpty()) {
                    foreach ($payment_methods as $k => $v) {
                        if($v->pay_id == $ad_data['ad_type_pay']){
                            if(empty($v->pay_page_name)){
                                $message[] = trans('publish_edit.Your ad will be activated automatically when you pay.');
                                $message[] = trans('publish_edit.Send sms and make your ad promo', [
                                    'number' => $v->pay_number,
                                    'text' => $v->pay_sms_prefix . ' a' . $ad->ad_id,
                                    'period' => $v->pay_promo_period,
                                    'sum' => number_format($v->pay_sum, 2, '.', ''),
                                    'cur' => config('dc.site_price_sign')
                                ]);
                            } else {
                                $message[] = trans('publish_edit.Your ad will be activated automatically when you pay.');
                                $message[] = trans('publish_edit.Click the button to pay for promo', [
                                    'pay' => $v->pay_name,
                                    'period' => $v->pay_promo_period,
                                    'sum' => number_format($v->pay_sum, 2, '.', ''),
                                    'cur' => config('dc.site_price_sign')
                                ]);
                                session()->flash('message', $message);
                                return redirect(url($v->pay_page_name . '/a' . $ad->ad_id));
                            }
                        }
                    }
                }
            }
        }

        if(!isset($message) || empty($message)){
            $message[] = trans('publish_edit.Your ad is in moderation mode, please activate it.');
            $message[] = trans('publish_edit.If you dont receive mail from us, please check your spam folder or contact us.');
            $message[] = trans('publish_edit.Click here to publish new ad', ['link' => route('publish')]);
        }

        //set flash message and go to info page
        session()->flash('message', $message);
        return redirect(route('info'));
    }

    /**
     * Activate published ad
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function activate(Request $request)
    {
        $code = $request->token;
        if(!empty($code)){
            $ad = Ad::where('code', $code)->first();
            if(!empty($ad)){

                $message[] = trans('publish_edit.Your ad is active now');

                //if enabled add bonus to wallet
                if(config('dc.enable_bonus_on_ad_activation') && $ad->bonus_added == 0 && config('dc.bonus_sum_on_ad_activation') > 0){
                    //add money to wallet
                    $wallet_data = ['user_id' => $ad->user_id,
                        'ad_id' => $ad->ad_id,
                        'sum' => config('dc.bonus_sum_on_ad_activation'),
                        'wallet_date' => date('Y-m-d H:i:s'),
                        'wallet_description' => trans('publish_edit.Ad Activation Bonus')
                    ];
                    Wallet::create($wallet_data);
                    $ad->bonus_added = 1;
                    $message[] = trans('publish_edit.We have added to your wallet for ad activation.', ['sum' => config('dc.bonus_sum_on_ad_activation'), 'sign' => config('dc.site_price_sign')]);
                }

                $ad->ad_active = 1;
                $ad->save();

                Cache::flush();
            }
        }

        if(!isset($message) || empty($message)){
            $message = trans('publish_edit.Ups something is wrong. Please contact us.');
        }

        $message[] = trans('publish_edit.Click here to publish new ad', ['link' => route('publish')]);

        session()->flash('message', $message);
        return redirect(route('info'));
    }

    /**
     * Delete Ad
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function delete(Request $request)
    {
        $code = $request->token;
        if(!empty($code)){
            $ad = Ad::where('code', $code)->first();
            if(!empty($ad)){
                //delete images
                if(!empty($ad->ad_pic)){
                    @unlink(public_path('uf/adata/') . '740_' . $ad->ad_pic);
                    @unlink(public_path('uf/adata/') . '1000_' . $ad->ad_pic);
                }
                
                $more_pics = AdPic::where('ad_id', $ad->ad_id)->get();
                if(!$more_pics->isEmpty()){
                    foreach ($more_pics as $k => $v){
                        @unlink(public_path('uf/adata/') . '740_' . $v->ad_pic);
                        @unlink(public_path('uf/adata/') . '1000_' . $v->ad_pic);
                        $v->delete();
                    }
                }
                
                $ad->delete();
                $message[] = trans('publish_edit.Your ad is deleted');
            }
        }

        if(!isset($message) || empty($message)){
            $message[] = trans('publish_edit.Ups something is wrong. Please contact us.');
        }
        $message[] = trans('publish_edit.Click here to publish new ad', ['link' => route('publish')]);

        session()->flash('message', $message);
        return redirect(route('info'));
    }

    /**
     * Show ad contact form
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getAdContact(Request $request)
    {
        //get ad id
        $adId = $request->ad_id;
        
        //get ad info
        $adDetail = $this->adModel->getAdDetail($adId);

        //generate breadcrump
        $breadcrump = [];
        $breadcrumpData = $this->categoryModel->getParentsByIdFlat($adDetail->category_id);
        if(!empty($breadcrumpData)){
            foreach ($breadcrumpData as $k => &$v){
                $categoryUrlParams = array();
                $categoryUrlParams[] = $this->categoryModel->getCategoryFullPathById($v['category_id']);
                if(session()->has('location_slug')){
                    $categoryUrlParams[] = 'l-' . session()->get('location_slug');
                }
        
                if(!empty($categoryUrlParams)){
                    $v['category_url'] = Util::buildUrl($categoryUrlParams);
                }
            }
            //category part of breadcrump
            $breadcrump['c'] = array_reverse($breadcrumpData);
        }

        //generate title
        $title      = [config('dc.site_domain')];
        $title[]    = $adDetail->ad_title;
        $title[]    = trans('detail.Ad Id') . ': ' . $adDetail->ad_id;
        $title[]    = trans('contact.Send Message');
        
        return view('ad.contact', ['ad_detail' => $adDetail, 'breadcrump' => $breadcrump, 'title' => $title]);
    }

    /**
     * Save ad contact form
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Foundation\Validation\ValidationException
     */
    public function postAdContact(Request $request)
    {
        //get ad id
        $adId = $request->ad_id;
    
        //get ad info
        $adDetail = Ad::where('ad_active', 1)->findOrFail($adId);
        
        //validate form
        $rules = [
            'contact_message' => 'required|min:' . config('dc.ad_contact_min_words')
        ];

        if(config('dc.enable_recaptcha_ad_contact')){
            $rules['g-recaptcha-response'] = 'required|recaptcha';
        }
         
        $validator = Validator::make($request->all(), $rules);
        $validator->sometimes(['contact_name'], 'required|string|max:255', function($request){
            if(Auth::check()){
                return false;
            }
            return true;
        });
        $validator->sometimes(['contact_mail'], 'required|email|max:255', function($request){
            if(Auth::check()){
                return false;
            }
            return true;
        });
        
        if ($validator->fails()) {
            $this->throwValidationException(
                $request, $validator
            );
        }
        
        $current_user_id = 0;
        if(Auth::check()){
            $current_user_id = $request->user()->user_id;    
        } else {
            //check this mail for registered user
            try{
                $user = User::where('email', $request->contact_mail)->firstOrFail();
            } catch (\Exception $e) { 
                //no user create one
                //generate password
                $password = str_random(10);
                $user = new User();
                $user->name = $request->contact_name;
                $user->email = $request->contact_mail;
                $user->password = bcrypt($password);
                $user->user_activation_token = str_random(30);
                $user->save();
                
                //send activation mail
                Mail::send('emails.activation', ['user' => $user, 'password' => $password], function ($m) use ($user) {
                    $m->from(config('dc.site_contact_mail'), config('dc.site_domain'));
                    $m->to($user->email)->subject(trans('publish_edit.Activate your account!'));
                });
            }
            $current_user_id = $user->user_id;
        }
        
        //if user save message
        if($current_user_id > 0){
            //save in db and send mail
            $this->mailModel->saveMailToDbAndSendMail($current_user_id, $adDetail->user_id, $adId, $request->contact_message, $adDetail->ad_email);
            
            //set flash message and return
            session()->flash('message', trans('contact.Your message was send.'));
        } else {
            //set error flash message and return
            session()->flash('message', trans('contact.Ups something is wrong, please try again later or contact our team.'));
        }
        return redirect()->back();
    }

    /**
     * Ajax get car models based on selected car brand
     *
     * @param Request $request
     */
    public function ajaxGetCarModels(Request $request)
    {
        $ret = array('code' => 400);
        $car_brand_id = (int)$request->car_brand_id;
        if(is_numeric($car_brand_id) && $car_brand_id > 0){
            $carModelListCollection = CarModel::where('car_brand_id', $car_brand_id)->orderBy('car_model_name', 'asc')->get();
            if(!$carModelListCollection->isEmpty()){
                $info = array(0 => 'Select Car Model');
                foreach ($carModelListCollection as $k => $v){
                    $info[$v->car_model_id] = $v->car_model_name;
                }
                if(!empty($info)){
                    $ret['code'] = 200;
                    $ret['info'] = $info;
                }
            }
        }
        echo json_encode($ret);
    }

    /**
     * Ajax get category childs
     *
     * @param Request $request
     */
    public function ajaxGetCategory(Request $request)
    {
        $ret = array('code' => 400);
        $category_id = (int)$request->category_id;
        if(is_numeric($category_id)){
            if($category_id == 0) {
                $firstLevelChilds = $this->categoryModel->getOneLevel();
            } else {
                $firstLevelChilds = $this->categoryModel->getOneLevel($category_id);
                $breadcrumpData = $this->categoryModel->getParentsByIdFlat($category_id);
            }
            if(!$firstLevelChilds->isEmpty()){
                foreach ($firstLevelChilds as $k => $v){
                    $info[$v->category_id] = $v->category_title;
                }
                if(!empty($breadcrumpData)){
                    foreach($breadcrumpData as $k => $v){
                        $binfo[$v['category_id']] = $v['category_title'];
                    }

                }
                if(!empty($info)){
                    if(empty($binfo)){
                        $binfo = [];
                    }
                    $ret['code'] = 200;
                    $ret['info'] = $info;
                    $ret['binfo'] = $binfo;
                }
            } else {
                $ret['code'] = 300;
                $ret['info'] = $category_id;
            }
        }
        echo json_encode($ret);
    }

    /**
     * Ajax get location childs
     *
     * @param Request $request
     */
    public function ajaxGetLocation(Request $request)
    {
        $ret = array('code' => 400);
        $location_id = (int)$request->location_id;
        if(is_numeric($location_id)){
            if($location_id == 0) {
                $firstLevelChilds = $this->locationModel->getOneLevel();
            } else {
                $firstLevelChilds = $this->locationModel->getOneLevel($location_id);
                $breadcrumpData = $this->locationModel->getParentsByIdFlat($location_id);
            }
            if(!$firstLevelChilds->isEmpty()){
                foreach ($firstLevelChilds as $k => $v){
                    $info[$v->location_id] = $v->location_name;
                }
                if(!empty($breadcrumpData)){
                    foreach($breadcrumpData as $k => $v){
                        $binfo[$v['location_id']] = $v['location_name'];
                    }

                }
                if(!empty($info)){
                    if(empty($binfo)){
                        $binfo = [];
                    }
                    $ret['code'] = 200;
                    $ret['info'] = $info;
                    $ret['binfo'] = $binfo;
                }
            } else {
                $ret['code'] = 300;
                $ret['info'] = $location_id;
            }
        }
        echo json_encode($ret);
    }

    /**
     * Ajax save ad report
     *
     * @param Request $request
     */
    public function ajaxReportAd(Request $request)
    {
        $ret = array('code' => 400);
        parse_str($request->form_data, $form_data);
        if(isset($form_data['report_ad_id']) && is_numeric($form_data['report_ad_id']) && isset($form_data['report_radio']) && is_numeric($form_data['report_radio'])){
            $ad_report = new AdReport();
            $ad_report->report_ad_id = $form_data['report_ad_id'];
            $ad_report->report_type_id = $form_data['report_radio'];
            $ad_report->report_info = nl2br($form_data['report_more_info']);
            $ad_report->report_date = date('Y-m-d H:i:s');
            if(Auth::check()){
                $ad_report->report_user_id = $request->user()->user_id;
            }
            try{
                $ad_report->save();
                $ret['code'] = 200;
                $ret['message'] = trans('publish_edit.Thanks, The report is send.');
            } catch (\Exception $e){
                $ret['message'] = trans('publish_edit.Ups, something is wrong, please try again.');
            }
        } else {
            $ret['message'] = trans('publish_edit.Ups, something is wrong, please try again.');
        }
        echo json_encode($ret);
    }

    /**
     * Ajax save ad to favorites
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxSaveToFav(Request $request)
    {
        $ret = array('code' => 400);
        $adId = $request->ad_id;
        
        if(isset($adId) && is_numeric($adId)){
            $favAdsInfo = $request->cookie('__' . md5(config('dc.site_domain')) . '_fav_ads', array());
            if(Auth::check()){
                //registered user save/remove to/from db
                $adFavModel = new AdFav();
                $user_id = $request->user()->user_id;
                $fav_ads_db = $adFavModel->getFavAds($user_id);
                if(isset($fav_ads_db[$adId])){
                    //remove from db
                    $adFavModel->where('ad_id', $adId)->where('user_id', $user_id)->delete();
                    $ret['code'] = 201;
                } else {
                    //remove all favs from db for this user
                    $adFavModel->where('user_id', $user_id)->delete();
                    
                    $favAdsInfo[$adId] = $adId;
                    $fav_to_save = array_replace($favAdsInfo, $fav_ads_db);
                    
                    //save to db, if there is cookie info save it too
                    foreach ($fav_to_save as $k){
                        $adFavModel = new AdFav();
                        $adFavModel->ad_id = $k;
                        $adFavModel->user_id = $user_id;
                        $adFavModel->save();
                    }
                    $ret['code'] = 200;
                }
                
                //remove cookie, data is saved to db
                $favAdsInfo = array();
            } else {
                //not registered user save/remove ad to/from cookie
                if(isset($favAdsInfo[$adId])){
                    unset($favAdsInfo[$adId]);
                    $ret['code'] = 201;
                } else {
                    $favAdsInfo[$adId] = $adId;
                    $ret['code'] = 200;
                }
            }
            $cookie = Cookie::forever('__' . md5(config('dc.site_domain')) . '_fav_ads', $favAdsInfo);
            Cookie::queue($cookie);
        }
        
        return response()->json($ret);
    }

    /**
     * Show list with user ads
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function myAds(Request $request)
    {
        $params     = $request->all();
        $limit      = 0;
        $orderRaw   = '';
        $whereIn    = [];
        $whereRaw   = [];
        $page       = 1;
        $paginate   = config('dc.num_ads_on_myads');
        if (isset($params['page']) && is_numeric($params['page'])) {
            $page = $params['page'];
        }

        $where = ['user_id' => Auth::user()->user_id];
        $order = ['ad_publish_date' => 'desc'];
        $my_ad_list = $this->adModel->getAdList($where, $order, $limit, $orderRaw, $whereIn, $whereRaw, $paginate, $page);

        //set page title
        $title = [config('dc.site_domain')];
        $title[] = trans('myads.My Classifieds');
        $title[] = trans('myads.Page:') . ' ' . $page;

        return view('ad.myads', ['my_ad_list' => $my_ad_list, 'title' => $title, 'params' => $params]);
    }

    /**
     * Republish ad
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function rePublish(Request $request)
    {
        $code = $request->token;
        if(!empty($code)){
            $ad = Ad::where('code', $code)->first();
            if(!empty($ad)){
                $ad->ad_publish_date = date('Y-m-d H:i:s');
                $ad->ad_valid_until = date('Y-m-d', mktime(null, null, null, date('m'), date('d')+config('dc.ad_valid_period_in_days'), date('Y')));
                $ad->expire_warning_mail_send = 0;
                $ad->save();
                Cache::flush();
            } 
        }
        return redirect(url('myads'));
    }

    /**
     * Show ad edit form
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function getAdEdit(Request $request)
    {
        //get ad id
        $adId = $request->ad_id;
    
        //get ad info
        $adDetail = $this->adModel->getAdDetail($adId, 0);
        
        if($adDetail->user_id != Auth::user()->user_id){
            return redirect(route('myads'));
        }
        
        $adDetail->ad_price_type_1     = $adDetail->ad_price_type_2 = $adDetail->ad_price_type_3 = $adDetail->ad_price_type_4 = $adDetail->ad_price;
        $adDetail->ad_price_type_5     = $adDetail->ad_price_type_6 = $adDetail->ad_price_type_7 = $adDetail->ad_price;

        if($adDetail->ad_price > 0){
            $adDetail->price_radio = $adDetail->price_radio_type_4 = $adDetail->price_radio_type_5 = $adDetail->price_radio_type_6 = 1;
        } elseif ($adDetail->ad_free){
            $adDetail->price_radio = $adDetail->price_radio_type_4 = $adDetail->price_radio_type_5 = $adDetail->price_radio_type_6 = 2;
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
        
        $adDetail->ad_category_info = $this->categoryModel->getParentsByIdFlat($adDetail->category_id);

        //set page title
        $title = [config('dc.site_domain')];
        $title[] = trans('publish_edit.Edit Ad');

        $location_first_level_childs = $this->locationModel->getOneLevel();
        
        return view('ad.edit', [
            'c'     => $this->categoryModel->getAllHierarhy(), //all categories hierarhy
            'l'     => $this->locationModel->getAllHierarhy(), //all location hierarhy
            'ad_detail' => $adDetail,
            'ad_pic'    => $adPic,
            'title'     => $title,
            'location_first_level_childs' => $location_first_level_childs,

            //filter vars
            'at'                        => AdType::allCached('ad_type_name'),
            'ac'                        => AdCondition::allCached('ad_condition_name'),
            'estate_construction_type'  => EstateConstructionType::allCached('estate_construction_type_name'),
            'estate_furnishing_type'    => EstateFurnishingType::allCached('estate_furnishing_type_name'),
            'estate_heating_type'       => EstateHeatingType::allCached('estate_heating_type_name'),
            'estate_type'               => EstateType::allCached('estate_type_name'),
            'car_brand'                 => CarBrand::allCached('car_brand_name'),
            'car_model'                 => $carModelListArray,
            'car_engine'                => CarEngine::allCached('car_engine_name'),
            'car_transmission'          => CarTransmission::allCached('car_transmission_name'),
            'car_condition'             => CarCondition::allCached('car_condition_name'),
            'car_modification'          => CarModification::allCached('car_modification_name'),
            'clothes_sizes'             => ClothesSize::allCached('clothes_size_ord'),
            'shoes_sizes'               => ShoesSize::allCached('shoes_size_ord')
        ]);
    }

    /**
     * Save ad edit form
     *
     * @param AdPostRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postAdEdit(AdPostRequest $request)
    {
        $ad_data = $request->all();
         
        //fill additional fields
        $ad_data['user_id']         = Auth::user()->user_id;
        $ad_data['ad_publish_date'] = date('Y-m-d H:i:s');
        $ad_data['ad_valid_until']  = date('Y-m-d', mktime(null, null, null, date('m'), date('d')+config('dc.ad_valid_period_in_days'), date('Y')));
        $ad_data['expire_warning_mail_send'] = 0;
        $ad_data['ad_ip']           = Util::getRemoteAddress();
        $ad_data['ad_description']  = Util::nl2br(strip_tags($ad_data['ad_description']));
         
        switch ($ad_data['category_type']){
            case 1:
                if(isset($ad_data['price_radio']) && $ad_data['price_radio'] == 1){
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
                if(isset($ad_data['price_radio_type_4']) && $ad_data['price_radio_type_4'] == 1){
                    $ad_data['ad_price'] = $ad_data['ad_price_type_4'];
                    $ad_data['ad_free'] = 0;
                } else {
                    $ad_data['ad_price'] = 0;
                    $ad_data['ad_free'] = 1;
                }
                break;
            case 5:
                if(isset($ad_data['price_radio_type_5']) && $ad_data['price_radio_type_5'] == 1){
                    $ad_data['ad_price'] = $ad_data['ad_price_type_5'];
                    $ad_data['ad_free'] = 0;
                } else {
                    $ad_data['ad_price'] = 0;
                    $ad_data['ad_free'] = 1;
                }
                $ad_data['condition_id'] = $ad_data['condition_id_type_5'];
                break;
            case 6:
                if(isset($ad_data['price_radio_type_6']) && $ad_data['price_radio_type_6'] == 1){
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
         
        //upload and fix ad images
        $ad_image = Input::file('ad_image');
        if(!empty(array_filter($ad_image))){
            
            //delete current image
            if(!empty($ad->ad_pic)){
                @unlink(public_path('uf/adata/') . '740_' . $ad->ad_pic);
                @unlink(public_path('uf/adata/') . '1000_' . $ad->ad_pic);
            }
        
            $more_pics = AdPic::where('ad_id', $ad->ad_id)->get();
            if(!$more_pics->isEmpty()){
                foreach ($more_pics as $k => $v){
                    @unlink(public_path('uf/adata/') . '740_' . $v->ad_pic);
                    @unlink(public_path('uf/adata/') . '1000_' . $v->ad_pic);
                    $v->delete();
                }
            }
            
            //save new images
            $destination_path = public_path('uf/adata/');
            $first_image_uploaded = 0;
            foreach ($ad_image as $k){
                if(!empty($k) && $k->isValid()){
                    $file_name = $ad->ad_id . '_' .md5(time() + rand(0,9999)) . '.' . $k->getClientOriginalExtension();
                    $k->move($destination_path, $file_name);

                    $img    = Image::make($destination_path . $file_name);
                    $width  = $img->width();
                    $height = $img->height();

                    if($width > 1000 || $height > 1000) {
                        if ($width == $height) {
                            $img->resize(1000, 1000);
                            if(config('dc.watermark')){
                                $img->insert(public_path('uf/settings/') . config('dc.watermark'), config('dc.watermark_position'));
                            }
                            $img->save($destination_path . '1000_' . $file_name);
                        } elseif ($width > $height) {
                            $img->resize(1000, null, function($constraint){
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            });
                            if(config('dc.watermark')){
                                $img->insert(public_path('uf/settings/') . config('dc.watermark'), config('dc.watermark_position'));
                            }
                            $img->save($destination_path . '1000_' . $file_name);
                        } elseif ($width < $height) {
                            $img->resize(null, 1000, function($constraint){
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            });
                            if(config('dc.watermark')){
                                $img->insert(public_path('uf/settings/') . config('dc.watermark'), config('dc.watermark_position'));
                            }
                            $img->save($destination_path . '1000_' . $file_name);
                        }
                    } else {
                        if(config('dc.watermark')){
                            $img->insert(public_path('uf/settings/') . config('dc.watermark'), config('dc.watermark_position'));
                            $img->save($destination_path . '1000_' . $file_name);
                        } else {
                            $img->save($destination_path . '1000_' . $file_name);
                        }
                    }

                    if(!$first_image_uploaded){
                        if($width >= 720 || $height >= 720) {
                            $img->fit(720, 720)->save($destination_path . '740_' . $file_name);
                        } else {
                            $img->resizeCanvas(720, 720, 'top')->save($destination_path . '740_' . $file_name);
                        }
                        $ad->ad_pic = $file_name;
                        $ad->save();
                        $first_image_uploaded = 1;
                    } else {
                        $adPic = new AdPic();
                        $adPic->ad_id = $ad->ad_id;
                        $adPic->ad_pic = $file_name;
                        $adPic->save();
                    }

                    @unlink($destination_path . $file_name);
                }
            }
        }
         
        $ad->ad_category_info   = $this->categoryModel->getParentsByIdFlat($ad->category_id);
        $ad->ad_location_info   = $this->locationModel->getParentsByIdFlat($ad->location_id);
        $ad->pics               = AdPic::where('ad_id', $ad->ad_id)->get();
        $ad->same_ads           = Ad::where([['ad_description_hash', $ad->ad_description_hash], ['ad_id', '<>', $ad->ad_id]])->get();
         
        //send info mail
        Mail::send('emails.ad_edit', ['user' => Auth::user(), 'ad' => $ad], function ($m) use ($request){
            $m->from(config('dc.site_contact_mail'), config('dc.site_domain'));
            $m->to($request->user()->email)->subject(trans('publish_edit.Your ad is edited!'));
        });

        //send control mail
        if(config('dc.enable_control_mails')) {
            Mail::send('emails.control_ad_activation', ['user' => Auth::user(), 'ad' => $ad], function ($m) {
                $m->from(config('dc.site_contact_mail'), config('dc.site_domain'));
                $m->to(config('dc.control_mail'))->subject(config('dc.control_mail_edit_subject'));
            });
        }

        Cache::flush();

        $message[] = trans('publish_edit.Your ad is saved.');
        $message[] = trans('publish_edit.Click here to return to my ads', ['link' => route('myads')]);
        $message[] = trans('publish_edit.Click here to publish new ad', ['link' => route('publish')]);

        //set flash message and go to info page
        session()->flash('message', $message);
        return redirect(route('info'));
    }

    /**
     * Show list of ads by user
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function userAds(Request $request)
    {
        $params     = $request->all();
        $limit      = 0;
        $orderRaw   = '';
        $whereIn    = [];
        $whereRaw   = [];
        $page       = 1;
        $paginate   = config('dc.num_ads_user_list');
        if (isset($params['page']) && is_numeric($params['page'])) {
            $page = $params['page'];
        }

        $user = $this->userModel->getUserById($request->user_id);
        $where = ['user_id' => $user->user_id, 'ad_active' => 1];
        $order = ['ad_publish_date' => 'desc'];
        $user_ad_list = $this->adModel->getAdList($where, $order, $limit, $orderRaw, $whereIn, $whereRaw, $paginate, $page);

        //set page title
        $title = [config('dc.site_domain')];
        $title[] = trans('user.Ad List');
        $title[] = trans('user.Ad List User', ['name' => $user->name]);
        $title[] = trans('myads.Page:') . ' ' . $page;

        return view('ad.user', ['user' => $user, 'user_ad_list' => $user_ad_list, 'title' => $title, 'params' => $params]);
    }

    /**
     * Make ad promo, show payment methods
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function makePromo(Request $request)
    {
        //get ad info
        $adId = $request->ad_id;
        $adDetail = $this->adModel->getAdDetail($adId);

        //set page title
        $title = [config('dc.site_domain')];
        $title[] = trans('makepromo.Make promo Ad Id') . ' #' . $adDetail->ad_id;

        $payment_methods = new Collection();
        if(config('dc.enable_promo_ads')){
            $where['pay_active']    = 1;
            $order['pay_ord']       = 'ASC';
            $payModel               = new Pay();
            $payment_methods        = $payModel->getList($where, $order);
        }

        $enable_pay_from_wallet = 0;
        if(config('dc.enable_promo_ads') && Auth::check()){
            //no caching for the wallet :)
            $wallet_total = Wallet::where('user_id', Auth::user()->user_id)->sum('sum');
            if(number_format($wallet_total, 2, '.', '') >= number_format(config('dc.wallet_promo_ad_price'), 2, '.', '')){
                $enable_pay_from_wallet = 1;
            }
        }

        return view('ad.makepromo', ['title' => $title,
            'payment_methods' => $payment_methods,
            'ad_detail' => $adDetail,
            'enable_pay_from_wallet' => $enable_pay_from_wallet
        ]);
    }

    /**
     * Save ad promo, make ad promo if there are money in wallet, else redirect to payment gateway
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Illuminate\Foundation\Validation\ValidationException
     */
    public function postMakePromo(Request $request)
    {
        //get ad info
        $adId = $request->ad_id;
        $adDetail = $this->adModel->getAdDetail($adId);

        $rules = [
            'ad_type_pay' => 'required|integer|not_in:0'
        ];

        $messages = [
            'required' => trans('addtowallet.Please select payment method.'),
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            $this->throwValidationException(
                $request, $validator
            );
        }

        $params = $request->all();

        //if promo ads are enable check witch option is selected
        if(isset($params['ad_type_pay'])){

            //wallet pay
            if($params['ad_type_pay'] == 1000){
                //no caching for the wallet :)
                $wallet_total = Wallet::where('user_id', Auth::user()->user_id)->sum('sum');
                if(number_format($wallet_total, 2, '.', '') >= number_format(config('dc.wallet_promo_ad_price'), 2, '.', '')){
                    //calc promo period
                    $promoUntilDate = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')+config('dc.wallet_promo_ad_period'), date('Y')));

                    //make ad promo and activate it
                    $adDetail->ad_promo = 1;
                    $adDetail->ad_promo_until = $promoUntilDate;
                    $adDetail->ad_active = 1;
                    $adDetail->save();

                    //subtract money from wallet
                    $wallet_data = ['user_id' => $adDetail->user_id,
                        'ad_id' => $adDetail->ad_id,
                        'sum' => -number_format(config('dc.wallet_promo_ad_price'), 2, '.', ''),
                        'wallet_date' => date('Y-m-d H:i:s'),
                        'wallet_description' => trans('payment_fortumo.Your ad #:ad_id is Promo Until :date.', ['ad_id' => $adDetail->ad_id, 'date' => $promoUntilDate])
                    ];
                    Wallet::create($wallet_data);
                    Cache::flush();

                    $message[] = trans('payment_fortumo.Your ad #:ad_id is Promo Until :date.', ['ad_id' => $adDetail->ad_id, 'date' => $promoUntilDate]);
                    $message[] = trans('publish_edit.Click here to publish new ad', ['link' => route('publish')]);
                }
            } else {
                $where['pay_active'] = 1;
                $order['pay_ord'] = 'ASC';
                $payModel = new Pay();
                $payment_methods = $payModel->getList($where, $order);
                if (!$payment_methods->isEmpty()) {
                    foreach ($payment_methods as $k => $v) {
                        if($v->pay_id == $params['ad_type_pay']){
                            if(empty($v->pay_page_name)){
                                $message[] = trans('publish_edit.Send sms and make your ad promo', [
                                    'number' => $v->pay_number,
                                    'text' => $v->pay_sms_prefix . ' a' . $adDetail->ad_id,
                                    'period' => $v->pay_promo_period,
                                    'sum' => number_format($v->pay_sum, 2, '.', ''),
                                    'cur' => config('dc.site_price_sign')
                                ]);
                            } else {
                                $message[] = trans('publish_edit.Click the button to pay for promo', [
                                    'pay' => $v->pay_name,
                                    'period' => $v->pay_promo_period,
                                    'sum' => number_format($v->pay_sum, 2, '.', ''),
                                    'cur' => config('dc.site_price_sign')
                                ]);
                                session()->flash('message', $message);
                                return redirect(url($v->pay_page_name . '/a' . $adDetail->ad_id));
                            }
                        }
                    }
                }
            }
        }

        //set flash message and go to info page
        session()->flash('message', $message);
        return redirect(route('info'));
    }

    /**
     * Show user favorite ads, if logged user get from database, if not logged get from cookie
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function myFav(Request $request)
    {
        $params     = $request->all();
        $limit      = 0;
        $orderRaw   = '';
        $whereIn    = [];
        $whereRaw   = [];
        $page       = 1;
        $paginate   = config('dc.num_ads_on_myads');
        if (isset($params['page']) && is_numeric($params['page'])) {
            $page = $params['page'];
        }

        //get fav ads from cookie or db
        $favAdsInfo = [];
        if(Auth::check()){
            $adFavModel = new AdFav();
            $favAdsInfo = $adFavModel->getFavAds(Auth::user()->user_id);
        } else if(Cookie::has('__' . md5(config('dc.site_domain')) . '_fav_ads')) {
            //no user check cookie
            $favAdsInfo = $request->cookie('__' . md5(config('dc.site_domain')) . '_fav_ads', array());
        }

        if(!empty($favAdsInfo)){
            $whereIn['ad.ad_id'] = $favAdsInfo;
        }

        $where = ['ad_active' => 1];
        $order = ['ad_publish_date' => 'desc'];

        $my_ad_list = new Collection();
        if(!empty($whereIn)) {
            $my_ad_list = $this->adModel->getAdList($where, $order, $limit, $orderRaw, $whereIn, $whereRaw, $paginate, $page);
        }

        //set page title
        $title = [config('dc.site_domain')];
        $title[] = trans('myfav.My Favorite Classifieds');
        $title[] = trans('myfav.Page:') . ' ' . $page;

        return view('ad.myfav', ['my_ad_list' => $my_ad_list, 'title' => $title, 'params' => $params]);
    }
}
