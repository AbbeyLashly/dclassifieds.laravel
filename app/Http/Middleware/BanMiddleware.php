<?php

namespace App\Http\Middleware;

use Closure;
use Auth;
use Cache;

use App\AdBanEmail;
use App\AdBanIp;

class BanMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        /**
         * check for ban by ip
         */
        $remoteIp  = $request->ip();
        $cacheKey  = '_ban_ip_' . $remoteIp;
        $banInfo   = Cache::rememberForever($cacheKey, function() use($remoteIp) {
            return AdBanIp::where('ban_ip', $remoteIp)->first();
        });
        if(!empty($banInfo) && !$request->is('ban')){
            return redirect('ban');
        }

        /**
         * check if user is banned my email
         */
        if (Auth()->check()) {
            $userMail  = Auth()->user()->email;
            $cacheKey  = '_ban_email_' . $userMail;
            $banInfo   = Cache::rememberForever($cacheKey, function() use($userMail) {
                return AdBanEmail::where('ban_email', $userMail)->first();
            });
            if(!empty($banInfo) && !$request->is('ban')){
                return redirect('ban');
            }
        }
        return $next($request);
    }
}
