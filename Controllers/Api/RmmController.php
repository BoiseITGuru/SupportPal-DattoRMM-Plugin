<?php declare(strict_types=1);

namespace App\Plugins\DattoRMM\Controllers\Api;

use App\Modules\Core\Controllers\BaseApiController;
use App\Modules\Core\Controllers\Plugins\Plugin;
use App\Modules\Core\Controllers\Mailer\Mailer;
use App\Modules\Core\Models\ActivityLog;
use App\Modules\Ticket\Models\Ticket;
use App\Plugins\DattoRMM\Requests\Api\Alerts\InboundEmail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use kamermans\OAuth2\GrantType\ClientCredentials;
use kamermans\OAuth2\OAuth2Middleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Crypt;
use Exception;
use function call_user_func;
use function count;
use function mb_strtolower;
use function mb_strimwidth;
use function trans;

class RmmController extends BaseApiController
{
    /**
     * TicketController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Get DattoRMM Token
     *
     * @return JsonResponse
     */
    public function dattoRmmConnect($brandId = 0)
    {
        $settings = $this->settings();

        // If any details are empty, use from the default brand
        if (empty($settings['brand' . $brandId . '-datto_url'])
            || empty($settings['brand' . $brandId . '-datto_api_key'])
            || empty($settings['brand' . $brandId . '-datto_api_sec'])
        ) {
            $settings['brand' . $brandId . '-datto_url'] = Arr::get($settings, 'brand0-datto_url');
            $settings['brand' . $brandId . '-datto_api_key'] = Arr::get($settings, 'brand0-datto_api_key');
            $settings['brand' . $brandId . '-datto_api_sec'] = Arr::get($settings, 'brand0-datto_api_sec');
        }

        if (! empty($settings['brand' . $brandId . '-datto_url'])
            && ! empty($settings['brand' . $brandId . '-datto_api_key'])
            && ! empty($settings['brand' . $brandId . '-datto_api_sec'])
        ) {
            // Authorization client - this is used to request OAuth access tokens
            $reauth_client = new Client([
                // URL for access_token request
                'base_uri' => $settings['brand' . $brandId . '-datto_url'].'auth/oauth/token',
            ]);

            $reauth_config = [
                "client_id" => "public-client",
                "client_secret" => "public",
                "grant_type" => "password",
                "username" => $settings['brand' . $brandId . '-datto_api_key'],
                "password" => $settings['brand' . $brandId . '-datto_api_sec']
            ];

            $grant_type = new ClientCredentials($reauth_client, $reauth_config);
            $oauth = new OAuth2Middleware($grant_type);

            $stack = HandlerStack::create();
            $stack->push($oauth);

            // This is the normal Guzzle client that you use in your application
            $client = new Client([
                'handler' => $stack,
                'auth'    => 'oauth',
            ]);

            $response = $client->get($settings['brand' . $brandId . '-datto_url'].'api/v2/account/sites');
        }

        return $response;
    }
}
