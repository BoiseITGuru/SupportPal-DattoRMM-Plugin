<?php declare(strict_types=1);

/**
 * File SettingsRequest.php
 *
 * @copyright  Copyright (c) 2015-2016 SupportPal (http://www.supportpal.com)
 * @license    http://www.supportpal.com/company/eula
 */
namespace App\Plugins\DattoRMM\Requests;

use App\Http\Requests\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

use function array_merge;

class SettingsRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<mixed>
     */
    public function rules(): array
    {
        $rules = [
            'brand0-datto_url'   => ['required', 'regex:/(.*)\/$/'],
        ];

        // Add rules for brands if more than one.
        if (brand_count() > 1) {
            brand_config(null)->each(function ($model) use (&$rules) {
                $rules['brand' . $model->id . '-datto_url'] = ['nullable', 'regex:/(.*)\/$/'];
            });
        }

        return $rules;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string>
     */
    public function messages(): array
    {
        $messages = [
            'brand0-datto_url.regex' => Lang::get('DattoRMM::lang.validation_trailing_slash'),
        ];

        // Add messages for brands if more than one.
        if (brand_count() > 1) {
            brand_config(null)->each(function ($model) use (&$messages) {
                $messages['brand' . $model->id . '-datto_url.regex']
                    = Lang::get('DattoRMM::lang.validation_trailing_slash');
            });
        }

        return $messages;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string>
     */
    public function attributes(): array
    {
        $brandAttributes = [];
        if (brand_count() > 1) {
            brand_config(null)->each(function ($model) use (&$brandAttributes) {
                $brandAttributes['brand' . $model->id . '-datto_url']
                    = Lang::get('DattoRMM::lang.datto_url');
            });
        }

        return array_merge($brandAttributes, [
            'brand0-datto_url'   => Str::lower(Lang::get('DattoRMM::lang.datto_url')),
        ]);
    }
}
