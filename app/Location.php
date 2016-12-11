<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Ad;
use Cache;

class Location extends Model
{
    protected $table        = 'location';
    protected $primaryKey   = 'location_id';
    public $timestamps      = false;
    
    protected $fillable = ['location_parent_id', 'location_active', 'location_name', 'location_slug',
        'location_post_code', 'location_ord'];
    
    public function parents()
    {
        return $this->belongsTo('App\Location', 'location_parent_id');
    }
    
    public function children()
    {
        return $this->hasMany('App\Location', 'location_parent_id');
    }
    
    public function getAllHierarhy($_parent_id = null, $_level = 0, $_active = 1)
    {
        $cacheKey = __CLASS__ . '_' . __LINE__ . '_' . md5(config('dc.site_domain') . serialize(func_get_args()));
        $ret = Cache::get($cacheKey, []);
        if(empty($ret)){
            $_level++;

            $lquery = $this->where('location_parent_id', $_parent_id)
                ->with('children')
                ->orderBy('location_ord', 'asc')
                ->orderBy('location_name', 'asc');

            if($_active){
                $lquery->where('location_active', '=', 1);
            }

            $locationCollection = $lquery->get();

            if(!$locationCollection->isEmpty()){
                foreach ($locationCollection as $k => $v){
                    $ret[$v->location_id] = ['lid' => $v->location_id,
                        'title'     => $v->location_name,
                        'slug'      => $v->location_slug,
                        'active'    => $v->location_active,
                        'post_code' => $v->location_post_code,
                        'ord'       => $v->location_ord,
                        'level'     => $_level,
                        'ad_count'  => Ad::where('location_id', $v->location_id)->count()
                    ];

                    if(!empty($v->location_post_code)){
                        $ret[$v->location_id]['title'] = $v->location_name . ' [' . trans('location.ZIP') . ':' . $v->location_post_code . ']';
                    }

                    if($v->children->count() > 0){
                        $ret[$v->location_id]['c'] = $this->getAllHierarhy($v->location_id, $_level, $_active);
                    }
                }
                Cache::put($cacheKey, $ret, config('dc.cache_expire'));
            }
        }
        return $ret;
    }

    public function getAllHierarhyFlat($_parent_id = null, $_level = 0)
    {
        $ret = [];
        $_level++;
        $locationCollection = $this->where('location_parent_id', $_parent_id)
            ->where('location_active', '=', 1)
            ->with('children')
            ->orderBy('location_ord', 'asc')
            ->get();

        if(!$locationCollection->isEmpty()){
            foreach ($locationCollection as $k => $v){
                $ret[$v->location_id] = ['lid' => $v->location_id,
                    'title' => $v->location_name,
                    'level' => $_level];

                if($v->children->count() > 0){
                    $ret = array_merge($ret, $this->getAllHierarhyFlat($v->location_id, $_level));
                }
            }
        }
        return $ret;
    }
    
    public function getOneLevel($_parent_id = null)
    {
        $cacheKey = __CLASS__ . '_' . __LINE__ . '_' . md5(config('dc.site_domain') . serialize(func_get_args()));
        return Cache::rememberForever($cacheKey, function() use ($_parent_id) {
            return $this->where('location_parent_id', $_parent_id)
                ->orderBy('location_name', 'asc')
                ->get();
        });
    }
    
    public function getIdBySlug($_slug)
    {
        $ret = 0;
        $cacheKey = __CLASS__ . '_' . __LINE__ . '_' . md5(config('dc.site_domain') . serialize(func_get_args()));
        $res = Cache::rememberForever($cacheKey, function() use ($_slug) {
            return $this->select('location_id')
                ->where('location_slug', $_slug)
                ->first();
        });
        if(!empty($res)){
            $ret = $res->location_id;
        }
        return $ret;
    }
    
    public function getSlugById($_location_id)
    {
        $ret = '';
        $cacheKey = __CLASS__ . '_' . __LINE__ . '_' . md5(config('dc.site_domain') . serialize(func_get_args()));
        $res = Cache::rememberForever($cacheKey, function() use ($_location_id) {
            return $this->select('location_slug')
                ->where('location_id', $_location_id)
                ->first();
        });
        if(!empty($res)){
            $ret = $res->location_slug;
        }
        return $ret;
    }
    
    public function getParentsByIdFlat($_location_id)
    {
        $ret = [];
        do{
            $locationCollection = $this->where('location_id', $_location_id)->with('parents')->first();
            if(!empty($locationCollection)){
                $ret[$locationCollection->location_id] = $locationCollection->attributes;
            }
            if(!empty($locationCollection->parents)){
                $_location_id = $locationCollection->parents->location_id;
            }
        } while ( !empty($locationCollection) && !empty($locationCollection->parents));
        return $ret;
    }

    public function getLocationInfo($_location_id)
    {
        $ret = '';
        $cacheKey = __CLASS__ . '_' . __LINE__ . '_' . md5(config('dc.site_domain') . serialize(func_get_args()));
        $res = Cache::rememberForever($cacheKey, function() use ($_location_id) {
            return $this->where('location_id', $_location_id)
                ->first();
        });
        if(!empty($res)){
            $ret = $res;
        }
        return $ret;
    }
}