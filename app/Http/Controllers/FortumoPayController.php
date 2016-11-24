<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;

use App\Pay;
use App\User;
use App\Ad;
use App\Wallet;

use Cache;

class FortumoPayController extends Controller
{
    /**
     * Fortumo sms pay callback
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        //send reply by sms
        $smsReply = trans('payment_fortumo.There is error, please contact us.');

        //get info for this payment
        $payTypeInfo = Pay::find(Pay::PAY_TYPE_FORTUMO);

        //calc promo period
        $promoUntilDate = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')+$payTypeInfo->pay_promo_period, date('Y')));

        //get incoming params
        $message        = isset($request->message) ?  $request->message : null;
        $status         = isset($request->status) ?  $request->status : null;
        $billingType    = isset($request->billing_type) ?  $request->billing_type : null;

        //check if ping is comming from allowed ips
        $fortumoRemoteAddress = explode(',', $payTypeInfo->pay_allowed_ip);

        if(in_array($request->ip(), $fortumoRemoteAddress) && $this->checkSignature($request->all(), $payTypeInfo->pay_secret)) {

            $message = trim($message);

            if(!empty($message) && ( preg_match("/OK/i", $status) || (preg_match("/MO/i", $billingType) && preg_match("/pending/i", $status)) )){
                try {

                    //check if user is paying for promo ad or is adding money to wallet
                    $payPrefix = mb_strtolower(mb_substr($message, 0, 1));

                    //make ad promo
                    if ($payPrefix == 'a') {
                        $adId   = mb_substr($message, 1);
                        $adInfo = Ad::find($adId);
                        if (!empty($adInfo)) {

                            //check if ad is promo and extend promo period
                            if(!empty($adInfo->ad_promo_until) && $adInfo->ad_promo == 1){
                                $currentPromoPeriodTimestamp = strtotime($adInfo->ad_promo_until);
                                if($currentPromoPeriodTimestamp) {
                                    $promoUntilDate = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d', $currentPromoPeriodTimestamp) + $payTypeInfo->pay_promo_period, date('Y')));
                                }
                            }

                            //update ad
                            $adInfo->ad_promo = 1;
                            $adInfo->ad_promo_until = $promoUntilDate;
                            $adInfo->ad_active = 1;
                            $adInfo->promo_expire_warning_mail_send = 0;
                            $adInfo->save();

                            //add money to wallet
                            $walletData = ['user_id' => $adInfo->user_id,
                                'ad_id' => $adId,
                                'sum' => $payTypeInfo->pay_sum,
                                'wallet_date' => date('Y-m-d H:i:s'),
                                'wallet_description' => trans('payment_fortumo.Payment via Fortumo SMS')
                            ];
                            Wallet::create($walletData);

                            //subtract money from wallet
                            $walletData = ['user_id' => $adInfo->user_id,
                                'ad_id' => $adId,
                                'sum' => -$payTypeInfo->pay_sum,
                                'wallet_date' => date('Y-m-d H:i:s'),
                                'wallet_description' => trans('payment_fortumo.Your ad #:ad_id is Promo Until :date.', ['ad_id' => $adId, 'date' => $promoUntilDate])
                            ];
                            Wallet::create($walletData);

                            $smsReply = trans('payment_fortumo.Your ad #:ad_id is Promo Until :date.', ['ad_id' => $adId, 'date' => $promoUntilDate]);
                            Cache::flush();
                        }
                    }

                    //add money to wallet
                    if ($payPrefix == 'w') {
                        $userId     = mb_substr($message, 1);
                        $userInfo   = User::find($userId);
                        if (!empty($userInfo)) {
                            //save money to wallet
                            $walletData = ['user_id' => $userInfo->user_id,
                                'sum' => $payTypeInfo->pay_sum,
                                'wallet_date' => date('Y-m-d H:i:s'),
                                'wallet_description' => trans('payment_fortumo.Add Money to Wallet via Fortumo SMS')
                            ];
                            Wallet::create($walletData);
                            $smsReply = trans('payment_fortumo.You have added :money to your wallet.', ['money' => number_format($payTypeInfo->pay_sum, 2) . config('dc.site_price_sign')]);
                            Cache::flush();
                        }
                    }
                } catch (\Exception $e){}
            }
        }
        echo $smsReply;
    }

    /**
     * Check if request is comming from Fortumo
     *
     * @param $paramsArray
     * @param $secret
     * @return bool
     */
    public function checkSignature($paramsArray, $secret)
    {
        ksort($paramsArray);
        $str = '';
        foreach ($paramsArray as $k => $v) {
            if($k != 'sig') {
                $str .= "$k=$v";
            }
        }
        $str .= $secret;
        $signature = md5($str);
        return ($paramsArray['sig'] == $signature);
    }
}