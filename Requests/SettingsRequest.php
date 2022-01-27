<?php
/**
 * File SettingsRequest.php
 */
namespace App\Plugins\DattoRMM\Requests;

use App\Http\Requests\Request;
use Lang;

/**
 * Class SettingsRequest
 *
 * @package    App\Plugins\DattoRMM\Requests
 */
class SettingsRequest extends Request
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
        return [
            'datto_url' => 'required',
            'datto_api_key' => 'required',
            'datto_api_sec' => 'required',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'setting.alpha_num' => Lang::get('DattoRMM::lang.setting_alpha_num'),
        ];
    }
}
