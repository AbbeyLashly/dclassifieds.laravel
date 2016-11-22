<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Http\Requests;

use App\Ad;

class RssController extends Controller
{
    protected $adModel;

    public function __construct(Ad $adModel)
    {
        $this->adModel = $adModel;
    }

    /**
     * Rss Feed Generator
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        if(config('dc.enable_rss')) {

            //xml template
            $xml = '<?xml version="1.0"?>
                        <rss version="2.0">
                           <channel>
                              <title>' . config('dc.site_home_page_title') . '</title>
                              <description>' . config('dc.rss_feed_description') . '</description>
                              <link>' . config('dc.site_url') . '</link>';

            //get ads
            $where['ad_active'] = 1;
            $order  = ['ad.ad_id' => 'desc'];
            $limit  = config('dc.rss_num_items');
            $res    = $this->adModel->getAdList($where, $order, $limit);

            //generate xml
            if (!empty($res)) {
                foreach ($res as $k => $v) {
                    $link = url(str_slug($v->ad_title) . '-' . 'ad' . $v->ad_id . '.html');
                    $xml .= '<item>';
                    $xml .= '<title>' . htmlspecialchars(stripslashes($v->ad_title)) . '</title>';
                    $xml .= '<link>' . $link . '</link>';
                    $xml .= '<description></description>';
                    $xml .= '<guid>' . $link . '</guid>';
                    $xml .= '</item>' . chr(10);
                }
            }

            $xml .= '</channel>
                    </rss>';

            //output generated xml
            echo $xml;
        }
    }
}