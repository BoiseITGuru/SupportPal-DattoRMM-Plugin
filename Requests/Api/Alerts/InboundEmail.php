<?php declare(strict_types=1);

namespace App\Plugins\DattoRMM\Requests\Api\Alerts;

use App\Http\ApiFormRequest;
use Illuminate\Support\Carbon;

use function config;

class InboundEmail extends ApiFormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'text'          => ['required', 'string'],
            'html'          => ['required', 'string'],
            'to'            => ['required', 'string'],
            'from'          => ['required', 'string'],
            'subject'       => ['required', 'string'],
            'spam_score'    => ['required', 'string'],
        ];
    }
}
