<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;


use App\Pay;
use App\User;
use App\Ad;
use App\Wallet;

use Cache;
use Log;
use Input;
use Stripe\Stripe;

class StripePayController extends Controller
{
    /**
     * Show Stripe Pay form
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        //get peytype
        $payType = $request->paytype;

        //get info for this payment
        $payTypeInfo = Pay::find(Pay::PAY_TYPE_STRIPE);

        $stripeData = ['paytype' => $payType,
            'sum_to_charge' => $payTypeInfo->pay_sum_to_charge,
            'pay_currency' => $payTypeInfo->pay_currency,
            'publish_key' => $payTypeInfo->pay_publish_key
        ];

        //set page title
        $title = [config('dc.site_domain')];
        $title[] = trans('payment_stripe.Payment via Stripe');

        return view('pay.stripe', ['stripeData' => $stripeData, 'title' => $title]);
    }

    /**
     * Stripe pay create charge
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function stripe(Request $request)
    {
        //get post params
        $params = Input::all();

        //get get params
        $payType = $request->paytype;
        $payType = trim($payType);

        //get info for this payment
        $payTypeInfo = Pay::find(Pay::PAY_TYPE_STRIPE);

        // See your keys here: https://dashboard.stripe.com/account/apikeys
        Stripe::setApiKey($payTypeInfo->pay_secret_key);

        // Get the credit card details submitted by the form
        $token = '';
        if (isset($params['stripeToken']) && !empty($params['stripeToken'])) {
            $token = $params['stripeToken'];
        }

        if (!empty($token) && !empty($payType)) {
            // Create a charge: this will charge the user's card
            try {
                $charge = \Stripe\Charge::create([
                    'amount' => $payTypeInfo->pay_sum_to_charge * 100,
                    'currency' => $payTypeInfo->pay_currency,
                    'source' => $token,
                    'description' => config('dc.site_domain') . '-' . trans('payment_stripe.Promo Option')
                ]);
            } catch (\Exception $e) {
                // The card has been declined
                Log::info('STRIPE :: ERROR ' . $e->getMessage() . ' Action: ' . $payType);
                session()->flash('message', trans('payment_stripe.There is error, please contact us.'));
                return view('common.info_page');
            }

            //check if user is paying for promo ad or is adding money to wallet
            $payPrefix = mb_strtolower(mb_substr($payType, 0, 1));

            //make ad promo
            if ($payPrefix == 'a') {
                $adId = mb_substr($payType, 1);
                $adInfo = Ad::find($adId);
                if (!empty($adInfo)) {

                    //calc promo period
                    $promoUntilDate = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d')+$payTypeInfo->pay_promo_period, date('Y')));

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
                        'wallet_description' => trans('payment_stripe.Payment via Stripe')
                    ];
                    Wallet::create($walletData);

                    //subtract money from wallet
                    $walletData = ['user_id' => $adInfo->user_id,
                        'ad_id' => $adId,
                        'sum' => -$payTypeInfo->pay_sum,
                        'wallet_date' => date('Y-m-d H:i:s'),
                        'wallet_description' => trans('payment_stripe.Your ad #:ad_id is Promo Until :date.', ['ad_id' => $adId, 'date' => $promoUntilDate])
                    ];
                    Wallet::create($walletData);
                }
            }

            //add money to wallet
            if ($payPrefix == 'w') {
                $userId = mb_substr($payType, 1);
                $userInfo = User::find($userId);
                if (!empty($userInfo)) {
                    //save money to wallet
                    $walletData = ['user_id' => $userInfo->user_id,
                        'sum' => $payTypeInfo->pay_sum,
                        'wallet_date' => date('Y-m-d H:i:s'),
                        'wallet_description' => trans('payment_stripe.Add Money to Wallet via Stripe')
                    ];
                    Wallet::create($walletData);
                }
            }

            Cache::flush();
            session()->flash('message', trans('payment_stripe.Thank you for your payment'));
            return view('common.info_page');

        } else {
            Log::info('STRIPE :: ERROR TOKEN MISSING Action: ' . $payType);
            session()->flash('message', trans('payment_stripe.There is error, please contact us.'));
            return view('common.info_page');
        }
    }
}