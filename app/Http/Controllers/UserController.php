<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Database\Eloquent\Collection;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\User;
use App\Ad;
use App\UserMail;
use App\UserMailStatus;
use App\Location;
use App\Wallet;
use App\Pay;
use App\Http\Dc\Util;

use Validator;
use Image;
use Cache;
use Mail;
use Auth;

class UserController extends Controller
{
    protected $userModel;
    protected $mailModel;
    protected $locationModel;
    protected $walletModel;
    
    public function __construct(User $userModel, UserMail $mailModel, Location $locationModel, Wallet $walletModel)
    {
        $this->userModel     = $userModel;
        $this->mailModel     = $mailModel;
        $this->locationModel = $locationModel;
        $this->walletModel   = $walletModel;
    }

    /**
     * Show user profile for edit
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function myProfile(Request $request)
    {
        $user = $this->userModel->find(Auth::user()->user_id);
        $user->password = '';

        //set page title
        $title = [config('dc.site_domain')];
        $title[] = trans('myprofile.My Profile');

        return view('user.myprofile', ['user' => $user,
            'location' => $this->locationModel->getAllHierarhy(),
            'title' => $title
        ]);
    }

    /**
     * Save user profile data
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Foundation\Validation\ValidationException
     */
    public function myProfileSave(Request $request)
    {
        $currentUser = Auth::user();
        $rules = [
            'name'          => 'required|max:255',
            'email'         => 'required|email|max:255|unique:user,email,' . $currentUser->user_id  . ',user_id',
            'avatar_img'    => 'mimes:jpeg,bmp,png|max:300',
        ];
         
        $validator = Validator::make($request->all(), $rules);
        
        $validator->sometimes(['password'], 'required|confirmed|min:6', function($input){
            return !empty($input->password) ? 1 : 0;
        });
        
        if ($validator->fails()) {
            $this->throwValidationException(
                    $request, $validator
            );
        }
        
        $userData = $request->all();
        
        if(empty($userData['password'])){
            unset($userData['password']);
        } else {
            $userData['password'] = bcrypt($userData['password']);
        }
        
        $user = User::find($currentUser->user_id);
        $user->update($userData);
        
        //upload and fix ad images
        $avatar = Input::file('avatar_img');
        if(!empty($avatar)){
            $destinationPath = public_path('uf/udata/');
            if($avatar->isValid()){
                @unlink(public_path('uf/udata/') . '100_' . $user->avatar);
                
                $fileName = $user->user_id . '_' .md5(time() + rand(0,9999)) . '.' . $avatar->getClientOriginalExtension();
                $avatar->move($destinationPath, $fileName);
                 
                $img = Image::make($destinationPath . $fileName);
                $width = $img->width();
                $height = $img->height();
                
                if($width == $height || $width > $height){
                    $img->heighten(100, function ($constraint) {
                        $constraint->upsize();
                    })->save($destinationPath . '100_' . $fileName);
                } else {
                    $img->widen(100, function ($constraint) {
                        $constraint->upsize();
                    })->save($destinationPath . '100_' . $fileName);
                }
                
                $img->resizeCanvas(100, 100, 'center')->save($destinationPath . '100_' . $fileName);
                $user->avatar = $fileName;
                $user->save();
                @unlink($destinationPath . $fileName);
            }
        }
        
        //set flash message and return
        session()->flash('message', trans('myprofile.Your profile is updated.'));
        return redirect()->back();
    }

    /**
     * Show user mail list
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function myMail(Request $request)
    {
        $currentUserId = Auth::user()->user_id;
        $where = ['user_id_to' => $request->user()->user_id, 'UMS.mail_deleted' => 0];
        $order = ['mail_date' => 'DESC'];
        $mailList = $this->mailModel->getMailList($currentUserId, $where, $order);

        //set page title
        $title = [config('dc.site_domain')];
        $title[] = trans('mymail.My Messages');

        return view('user.mymail', ['mailList' => $mailList, 'title' => $title]);
    }

    /**
     * View mail conversation
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
     */
    public function mailView(Request $request)
    {
        //get params
        $hash = $request->hash;
        $userIdFrom = $request->user_id_from;
        $adId = $request->ad_id;
        $currentUserId = Auth::user()->user_id;
        
        //calc hash
        $hashArray = array($currentUserId, $userIdFrom, $adId);
        sort($hashArray);
        $calculatedHash = md5(join('-', $hashArray));
        
        //check hash
        if($calculatedHash != $hash){
            return redirect(url('mymail'));
        }
        
        //mark conversation as read
        UserMailStatus::where('mail_hash', $hash)
            ->where('user_id', $currentUserId)
            ->update(['mail_status' => UserMailStatus::MAIL_STATUS_READ]);

        Cache::flush();
        
        //get conversation
        $where = ['user_mail.mail_hash' => $hash, 'UMS.mail_deleted' => 0];
        $order = ['mail_date' => 'ASC'];
        $mailList = $this->mailModel->getMailList($currentUserId, $where, $order);
        
        if($mailList->isEmpty()){
            return redirect(route('mymail'));
        }

        //set page title
        $title = [config('dc.site_domain')];
        $title[] = trans('mailview.Mail View');
        
        return view('user.mailview', ['mailList' => $mailList, 'hash' => $hash, 'title' => $title]);
    }

    /**
     * Save mail reply
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Illuminate\Foundation\Validation\ValidationException
     */
    public function mailViewSave(Request $request)
    {
        //get params
        $hash = $request->hash;
        $userIdFrom = $request->user_id_from;
        $adId = $request->ad_id;
        $currentUserId = Auth::user()->user_id;
    
        //calc hash
        $hashArray = array($currentUserId, $userIdFrom, $adId);
        sort($hashArray);
        $calculatedHash = md5(join('-', $hashArray));
    
        //check hash
        if($calculatedHash != $hash){
            return redirect(url('mymail'));
        }
        
        //get ad info
        $adDetail = Ad::where('ad_active', 1)->findOrFail($adId);
        
        //validate form
        $rules = ['contact_message' => 'required|min:20'];
         
        $validator = Validator::make($request->all(), $rules);
        
        if ($validator->fails()) {
            $this->throwValidationException(
                $request, $validator
            );
        }
        
        //if user save message
        if($currentUserId > 0){

            //get other user info
            $userInfo = $this->userModel->getUserById($userIdFrom);

            //save in db and send mail
            $this->mailModel->saveMailToDbAndSendMail($currentUserId, $userIdFrom, $adId, $request->contact_message, $userInfo->email);
        
            //set flash message and return
            session()->flash('message', trans('mailview.Your message was send.'));
            
            //clear the cache
            Cache::flush();
        } else {
            //set error flash message and return
            session()->flash('message', trans('mailview.Ups something is wrong, please try again later or contact our team.'));
        }
        return redirect()->back();
    }

    /**
     * Delete conversation
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function mailDelete(Request $request)
    {
        //get params
        $mailId = $request->mail_id;
        $currentUserId = $request->user()->user_id;
        
        //mark mail deleted
        $umStatus = UserMailStatus::where('user_id', $currentUserId);
        if(is_numeric($mailId)){
            $umStatus->where('mail_id', $mailId);
        } else {
            $umStatus->where('mail_hash', $mailId);
        }
        $umStatus->update(['mail_deleted' => UserMailStatus::MAIL_STATUS_DELETED]);
    
        //clear the cache
        Cache::flush();
        
        return redirect()->back();
    }

    /**
     * Show user wallet list
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function myWallet(Request $request)
    {
        $user = Auth::user();

        $params     = $request->all();
        $where      = [];
        $order      = ['wallet_id' => 'desc'];
        $limit      = 0;
        $orderRaw   = '';
        $whereIn    = [];
        $whereRaw   = [];
        $paginate   = config('dc.mywallet_num_items');
        $page       = 1;


        $where['wallet.user_id'] = ['=', $user->user_id];
        if (isset($params['page']) && is_numeric($params['page'])) {
            $page = $params['page'];
        }

        //get wallet transactions list and total
        $walletList     = $this->walletModel->getList($where, $order, $limit, $orderRaw, $whereIn, $whereRaw, $paginate, $page);
        $walletTotal    = $this->walletModel->where('user_id', $user->user_id)->sum('sum');

        //set page title
        $title = [config('dc.site_domain')];
        $title[] = trans('mywallet.My Wallet Page Title');
        $title[] = trans('mywallet.Page:') . ' ' . $page;

        return view('user.mywallet', ['title' => $title,
            'walletList' => $walletList,
            'wallet_total' => $walletTotal,
            'user' => $user,
            'params' => $params
        ]);
    }

    /**
     * Show payment methods for add money to wallet
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getAddToWallet(Request $request)
    {
        //set page title
        $title = [config('dc.site_domain')];
        $title[] = trans('addtowallet.Add Money To Wallet');

        $paymentMethods = new Collection();
        if(config('dc.enable_promo_ads')){
            $where['pay_active']    = 1;
            $order['pay_ord']       = 'ASC';
            $payModel               = new Pay();
            $paymentMethods         = $payModel->getList($where, $order);
        }

        return view('user.addtowallet', ['title' => $title, 'payment_methods' => $paymentMethods]);
    }

    /**
     * Save add money to wallet and redirect to payment gateway
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     * @throws \Illuminate\Foundation\Validation\ValidationException
     */
    public function postAddToWallet(Request $request)
    {
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
        $user = Auth::user();

        $where['pay_active'] = 1;
        $order['pay_ord'] = 'ASC';
        $payModel = new Pay();
        $paymentMethods = $payModel->getList($where, $order);
        if (!$paymentMethods->isEmpty()) {
            foreach ($paymentMethods as $k => $v) {
                if($v->pay_id == $params['ad_type_pay']){
                    if(empty($v->pay_page_name)){
                        $message[] = trans('addtowallet.Your money will be added to your wallet automatically when you pay.');
                        $message[] = trans('addtowallet.Send sms to add money to your wallet', [
                            'number' => $v->pay_number,
                            'text' => $v->pay_sms_prefix . ' w' . $user->user_id,
                            'sum' => number_format($v->pay_sum, 2, '.', ''),
                            'cur' => config('dc.site_price_sign')
                        ]);
                    } else {
                        $message[] = trans('addtowallet.Your money will be added to your wallet automatically when you pay.');
                        $message[] = trans('addtowallet.Click the button to pay and add money to your wallet', [
                            'pay' => $v->pay_name,
                            'sum' => number_format($v->pay_sum, 2, '.', ''),
                            'cur' => config('dc.site_price_sign')
                        ]);
                        session()->flash('message', $message);
                        return redirect(url($v->pay_page_name . '/w' . $user->user_id));
                    }
                }
            }
        }

        //set flash message and go to info page
        session()->flash('message', $message);
        return redirect(route('info'));
    }
}