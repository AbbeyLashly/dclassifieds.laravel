<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;

use App\Pay;
use App\User;
use App\Ad;
use App\Wallet;

use Cache;


class MobioPayController extends Controller
{
    /**
     * Mobio SMS Pay callback
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        //get info for this payment
        $payTypeInfo = Pay::find(Pay::PAY_TYPE_MOBIO);

        //calc promo period
        $promoUntilDate = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')+$payTypeInfo->pay_promo_period, date('Y')));

        //get incoming params
        $message    = isset($request->message) ? $request->message : null;
        $item       = isset($request->item) ?  $request->item : null;
        $fromnum    = isset($request->fromnum) ?  $request->fromnum : null;
        $extId      = isset($request->extid) ? $request->extid : null;
        $servID     = isset($request->servID) ? $request->servID : null;

        //check if ping is comming from allowed ips
        $mobioRemoteAddress = explode(',', $payTypeInfo->pay_allowed_ip);

        if(in_array($request->ip(), $mobioRemoteAddress)) {

            $smsReply = trans('payment_mobio.There is error, please contact us.');
            $item = trim($item);

            if(!empty($item)){
                try {

                    //check if user is paying for promo ad or is adding money to wallet
                    $payPrefix = mb_strtolower(mb_substr($item, 0, 1));

                    //make ad promo
                    if ($payPrefix == 'a') {
                        $adId   = mb_substr($item, 1);
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
                                'wallet_description' => trans('payment_mobio.Payment via Mobio SMS')
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

                            $smsReply = trans('payment_mobio.Your ad #:ad_id is Promo Until :date.', ['ad_id' => $adId, 'date' => $promoUntilDate]);
                            Cache::flush();
                        }
                    }

                    //add money to wallet
                    if ($payPrefix == 'w') {
                        $userId     = mb_substr($item, 1);
                        $userInfo   = User::find($userId);
                        if (!empty($userInfo)) {
                            //save money to wallet
                            $walletData = ['user_id' => $userInfo->user_id,
                                'sum' => $payTypeInfo->pay_sum,
                                'wallet_date' => date('Y-m-d H:i:s'),
                                'wallet_description' => trans('payment_mobio.Add Money to Wallet via Mobio SMS')
                            ];
                            Wallet::create($walletData);
                            $smsReply = trans('payment_mobio.You have added :money to your wallet.', ['money' => number_format($payTypeInfo->pay_sum, 2) . config('dc.site_price_sign')]);
                            Cache::flush();
                        }
                    }
                } catch (\Exception $e){}
            }

            file_get_contents("http://mobio.bg/paynotify/pnsendsms.php?servID=$servID&tonum=$fromnum&extid=$extId&message=" . urlencode($smsReply));
        }
    }
}