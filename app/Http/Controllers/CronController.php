<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Ad;

use Cache;
use Mail;


class CronController extends Controller
{
    /**
     * Deactivate expired ads
     *
     * @param Request $request
     */
    public function deactivate(Request $request)
    {
        if($request->pass == config('dc.cron_password')) {
            $today = date('Y-m-d');
            Ad::where('ad_valid_until', '<', $today)->update(['ad_active' => 0]);
            Cache::flush();
        }
    }

    /**
     * Send mail ad will expire soon
     *
     * @param Request $request
     */
    public function sendMailDeactivateSoon(Request $request)
    {
        if($request->pass == config('dc.cron_password')) {
            $expireDate = date('Y-m-d', mktime(null, null, null, date('m'), date('d') + config('dc.send_warning_mail_ad_expire'), date('Y')));
            $expireSoonAds = Ad::where('ad_valid_until', '=', $expireDate)
                ->where('expire_warning_mail_send', 0)
                ->take(config('dc.num_mails_to_send_at_once'))
                ->get();
            if (!$expireSoonAds->isEmpty()) {
                foreach ($expireSoonAds as $k => $v) {
                    $v->expire_warning_mail_send = 1;
                    $v->save();

                    Mail::send('emails.ad_expire_warning', ['ad' => $v], function ($m) use ($v) {
                        $m->from(config('dc.site_contact_mail'), config('dc.site_domain'));
                        $m->to($v->ad_email)->subject(trans('cron.Your Ad Will Expire Soon') . $v->ad_title);
                    });
                }
            }
        }
    }

    /**
     * Send mail promo ad will expire soon
     *
     * @param Request $request
     */
    public function sendMailPromoExpireSoon(Request $request)
    {
        if($request->pass == config('dc.cron_password')) {
            $expireDate = date('Y-m-d', mktime(null, null, null, date('m'), date('d') + config('dc.send_warning_mail_promo_expire'), date('Y')));
            $expirePromoAds = Ad::where('ad_promo_until', '=', $expireDate)
                ->where('promo_expire_warning_mail_send', 0)
                ->take(config('dc.num_mails_to_send_at_once_promo_warning'))
                ->get();
            if (!$expirePromoAds->isEmpty()) {
                foreach ($expirePromoAds as $k => $v) {
                    $v->promo_expire_warning_mail_send = 1;
                    $v->save();

                    Mail::send('emails.promo_expire_warning', ['ad' => $v], function ($m) use ($v) {
                        $m->from(config('dc.site_contact_mail'), config('dc.site_domain'));
                        $m->to($v->ad_email)->subject(trans('cron.Your Promo Will Expire Soon') . $v->ad_title);
                    });
                }
            }
        }
    }

    /**
     * Remove promo from ads, where promo is expired
     *
     * @param Request $request
     */
    public function deactivatePromo(Request $request)
    {
        if($request->pass == config('dc.cron_password')) {
            $today = date('Y-m-d');
            Ad::where('ad_promo_until', '<', $today)->update(['ad_promo' => 0, 'ad_promo_until' => NULL]);
            Cache::flush();
        }
    }

    /**
     * Remove duplicate ads, with same nd5 hash of the description
     *
     * @param Request $request
     */
    public function removeDouble(Request $request)
    {
        if($request->pass == config('dc.cron_password')) {
            $doubleAds = Ad::select('ad_id')
                ->groupBy('ad_description_hash')
                ->havingRaw('count(ad_id) >= 2')
                ->get()
                ->toArray();
            if(!empty($doubleAds)){
                $inArray = [];
                foreach($doubleAds as $k => $v){
                    $inArray[] = $v['ad_id'];
                }
                Ad::whereIn('ad_id', $inArray)->delete();
            }
        }
    }
}