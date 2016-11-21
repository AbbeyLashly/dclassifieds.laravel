<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Dc\Util;

use Auth;
use Cache;

use App\AdBanEmail;
use App\AdBanIp;

class BanController extends Controller
{
    public function index(Request $request)
    {
        //message to show the user
        $message = trans('ban.You are banned.');

        /**
         * check for ban by ip
         */
        $remoteIp  = Util::getRemoteAddress();
        $cacheKey  = '_ban_ip_' . $remoteIp;
        $banInfo   = Cache::rememberForever($cacheKey, function() use ($remoteIp) {
            return AdBanIp::where('ban_ip', $remoteIp)->first();
        });

        /**
         * check if user is banned my email
         */
        if (Auth()->check()) {
            $userMail  = Auth()->user()->email;
            $cacheKey  = '_ban_email_' . $userMail;
            $banInfo   = Cache::rememberForever($cacheKey, function() use ($userMail) {
                return AdBanEmail::where('ban_email', $userMail)->first();
            });
        }

        //show ban reason
        if(!empty($banInfo)){
            $message = $banInfo->ban_reason;
        }

        return view('errors.ban', ['message' => $message]);
    }
}
