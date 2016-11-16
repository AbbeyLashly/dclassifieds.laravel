<?php

namespace App\Http\Requests;

use App\Http\Requests\Request;
use Route;

class AdPostRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        list($controller, $action) = explode('@', Route::getCurrentRoute()->getActionName());

        $rules = [
            'ad_title'          => 'required|max:255',
            'category_id'       => 'required|integer|not_in:0',
            'ad_description'    => 'required|min:' . config('dc.ad_description_min_lenght'),
            'type_id'           => 'required|integer|not_in:0',
            'ad_image.*'        => 'mimes:jpeg,bmp,png|max:' . config('dc.ad_image_max_size'),
            'location_id'       => 'required|integer|not_in:0',
            'ad_publisher_name' => 'required|string|max:255',
            'ad_email'          => 'required|email|max:255'
        ];

        if($action == 'postPublish' || $action == 'postAdEdit'){
            $rules['policy_agree'] = 'required';
        }

        if(config('dc.require_ad_image') && $action == 'postPublish'){
            $rules['ad_image'] = 'require_one_of_array';
        }

        if(config('dc.enable_recaptcha_publish') && $action == 'postPublish'){
            $rules['g-recaptcha-response'] = 'required|recaptcha';
        }

        return $rules;
    }

    /**
     * Get the validator instance for the request.
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function getValidatorInstance()
    {
        $validator = parent::getValidatorInstance();

        /**
         * type 1 common ads validation
         */
        $validator->sometimes(['ad_price_type_1'], 'required|numeric|not_in:0', function($input){
            if(($input->category_type == 1 && $input->price_radio == 1) || ($input->category_type == 1 && !isset($input->price_radio))){
                return true;
            }
            return false;
        });
        $validator->sometimes(['condition_id_type_1'], 'required|integer|not_in:0', function($input){
            return $input->category_type == 1 ? 1 : 0;
        });

        /**
         * type 2 estate ads validation
         */
        $validator->sometimes(['ad_price_type_2'], 'required|numeric|not_in:0', function($input){
            if($input->category_type == 2){
                return true;
            }
            return false;
        });
        $validator->sometimes(['estate_type_id'], 'required|integer|not_in:0', function($input){
            return $input->category_type == 2 ? 1 : 0;
        });
        $validator->sometimes(['estate_sq_m'], 'required|numeric|not_in:0', function($input){
            return $input->category_type == 2 ? 1 : 0;
        });

        /**
         * type 3 cars ads validation
         */
        $validator->sometimes(['car_brand_id'], 'required|integer|not_in:0', function($input){
            return $input->category_type == 3 ? 1 : 0;
        });
        $validator->sometimes(['car_model_id'], 'required|integer|not_in:0', function($input){
            return $input->category_type == 3 ? 1 : 0;
        });
        $validator->sometimes(['car_engine_id'], 'required|integer|not_in:0', function($input){
            return $input->category_type == 3 ? 1 : 0;
        });
        $validator->sometimes(['car_transmission_id'], 'required|integer|not_in:0', function($input){
            return $input->category_type == 3 ? 1 : 0;
        });
        $validator->sometimes(['car_modification_id'], 'required|integer|not_in:0', function($input){
            return $input->category_type == 3 ? 1 : 0;
        });
        $validator->sometimes(['car_year'], 'required|integer|not_in:0', function($input){
            return $input->category_type == 3 ? 1 : 0;
        });
        $validator->sometimes(['car_kilometeres'], 'required|integer|not_in:0', function($input){
            return $input->category_type == 3 ? 1 : 0;
        });
        $validator->sometimes(['car_condition_id'], 'required|integer|not_in:0', function($input){
            return $input->category_type == 3 ? 1 : 0;
        });
        $validator->sometimes(['condition_id_type_3'], 'required|integer|not_in:0', function($input){
            return $input->category_type == 3 ? 1 : 0;
        });
        $validator->sometimes(['ad_price_type_3'], 'required|numeric|not_in:0', function($input){
            return $input->category_type == 3 ? 1 : 0;
        });

        /**
         * type 4 services validation
         */
        $validator->sometimes(['ad_price_type_4'], 'required|numeric|not_in:0', function($input){
            if(($input->category_type == 4 && $input->price_radio_type_4 == 1) || ($input->category_type == 4 && !isset($input->price_radio_type_4))){
                return true;
            }
            return false;
        });

        /**
         * type 5 clothes validation
         */
        $validator->sometimes(['ad_price_type_5'], 'required|numeric|not_in:0', function($input){
            if(($input->category_type == 5 && $input->price_radio_type_5 == 1) || ($input->category_type == 5 && !isset($input->price_radio_type_5))){
                return true;
            }
            return false;
        });
        $validator->sometimes(['clothes_size_id'], 'required|integer|not_in:0', function($input){
            return $input->category_type == 5 ? 1 : 0;
        });

        /**
         * type 6 shoes validation
         */
        $validator->sometimes(['ad_price_type_6'], 'required|numeric|not_in:0', function($input){
            if(($input->category_type == 6 && $input->price_radio_type_6 == 1) || ($input->category_type == 6 && !isset($input->price_radio_type_6))){
                return true;
            }
            return false;
        });
        $validator->sometimes(['shoes_size_id'], 'required|integer|not_in:0', function($input){
            return $input->category_type == 6 ? 1 : 0;
        });

        /**
         * type 7 real estate land validation
         */
        $validator->sometimes(['ad_price_type_7'], 'required|numeric|not_in:0', function($input){
            if($input->category_type == 7){
                return true;
            }
            return false;
        });
        $validator->sometimes(['estate_sq_m_type_7'], 'required|numeric|not_in:0', function($input){
            return $input->category_type == 7 ? 1 : 0;
        });

        return $validator;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'require_one_of_array' => trans('publish_edit.You need to upload at least one ad pic.')
        ];
    }
}
