<?php

namespace App\Http\Dc;
use Route;

class Util
{
    /**
     * Sanitize string
     *
     * @param $string
     * @return string
     */
    static function sanitize( $string )
    {
        return addslashes(strip_tags(trim($string)));
    }

    /**
     * Build url from route name and parameters
     *
     * @param $_route_name
     * @param array $_params
     * @return string
     */
    static function buildUrlByRouteName($_route_name, $_params = array())
    {
        $params = array();
        if(!empty($_params)){
            foreach($_params as $k => $v){
                $params[] = $k  . '/' . $v;
            }
        }
        return route($_route_name) . '/' . join('/', $params);
    }

    /**
     * Build url from parameters
     *
     * @param array $_url_params
     * @param string $_divider
     * @return string
     */
    static function buildUrl($_url_params = array(), $_divider = '/')
    {
        $root = request()->root();
        return $root . $_divider . join($_divider, $_url_params);
    }

    /**
     * Create query string from array
     *
     * @param array $_params
     * @param int $_remove_zero
     * @return string
     */
    static function getQueryStringFromArray($_params = array(), $_remove_zero = 1)
    {
        $ret = array();
        foreach ($_params as $k => $v){
            if(is_array($v) && !empty($v)){
                //$ret[] = $k . '[]=' . join('&', $v);
                foreach ($v as $ak => $av){
                    $ret[] = $k . '[]=' . $av;
                }
            } else {
                if(!$_remove_zero){
                    $ret[] = $k . '=' . $v;
                } elseif ($v > 0) {
                    $ret[] = $k . '=' . $v;
                }
            }
        }
        if(!empty($ret)){
            return join('&', $ret);
        } else {
            return '';
        }
    }

    /**
     * Get user ip
     *
     * @return string
     */
    static function getRemoteAddress()
    {
        $ret = '';
    
        if(isset($_SERVER['HTTP_CF_CONNECTING_IP']) && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])){
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
    
        if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
    
        if(isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])){
            return $_SERVER['REMOTE_ADDR'];
        }
    
        return $ret;
    }

    /**
     * Extract video from video link
     *
     * @param $_video_link
     * @return string
     */
    static function getVideoReady( $_video_link )
    {
        //youtube video template
        $youtube_video_template = '<iframe class="embed-responsive-item" src="http://www.youtube.com/embed/%s" frameborder="0" allowfullscreen></iframe>';
    
        //vimeo video template
        $vimeo_video_template = '<iframe src="http://player.vimeo.com/video/%s?title=0&amp;byline=0&amp;portrait=0" class="embed-responsive-item" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>';
    
        //container
        $video = '';
    
        //is youtube video
        if(preg_match('/https:\/\/(www.)?youtube.com\/watch\?v=([a-zA-Z0-9_-]+[^&])/', $_video_link, $matches)){
            $video = sprintf($youtube_video_template, $matches[2]);
        }
    
        return $video;
    }

    /**
     * Format price
     *
     * @param $_price
     * @param string $_price_sign
     * @return string
     */
    static function formatPrice( $_price, $_price_sign = '' )
    {
        if(!empty($_price_sign)) {
            if (config('dc.show_price_sign_before_price')){
                return $_price_sign . number_format($_price, 2, '.', ' ');
            } else {
                return number_format($_price, 2, '.', ' ') . $_price_sign;
            }
        } else {
            return number_format($_price, 2, '.', ' ');
        }
    }

    /**
     * Return Free or Negotiable if ad type = 8
     *
     * @param $adType
     * @return string|\Symfony\Component\Translation\TranslatorInterface
     */
    static function getFreeName($adType)
    {
        $ret = trans('publish_edit.Free');
        if($adType == 8){
            $ret = trans('publish_edit.Negotiable');
        }
        return $ret;
    }

    /**
     * Get value from old or from data model
     *
     * @param $_name
     * @param string $_model
     * @param string $_model_name
     * @return mixed
     */
    static function getOldOrModelValue($_name, $_model = '', $_model_name = '')
    {
        if(empty($_model_name)){
            $_model_name = $_name;
        }
        $old = session()->get('_old_input');
        if(isset($old[$_name])){
            return $old[$_name];
        }
        
        if(!empty($_model) && isset($_model->$_model_name) && !empty($_model->$_model_name)){
            return $_model->$_model_name;
        }
    }

    /**
     * Convert html <br /> to new line \n
     *
     * @param $_text
     * @return mixed
     */
    static function br2nl($_text)
    {
        return str_replace('<br />', "\n", $_text);
    }

    /**
     * Convert new line \n to html <br />
     *
     * @param $_text
     * @return mixed
     */
    static function nl2br($_text)
    {
        return str_replace("\r\n","<br />", $_text);
    }

    /**
     * Get current controller name
     *
     * @return string
     */
    static function getController()
    {
        $controller_action = class_basename(Route::current()->getAction()['controller']);
        list($controller, $action) = explode('@', $controller_action);
        return strtolower($controller);
    }
}
