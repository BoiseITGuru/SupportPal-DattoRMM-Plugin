<?php

namespace App\Plugins\DattoRMM\Controllers;

use App\Modules\Core\Controllers\Plugins\Plugin;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\CustomField;
use App\Modules\Core\Models\ScheduledTask;
use App\Modules\Core\Interfaces\ScheduledTaskInterface;
use App\Modules\Ticket\Models\Department;
use App\Modules\Ticket\Models\TicketCustomField;
use App\Modules\Ticket\Models\TicketCustomFieldValue;
use App\Plugins\DattoRMM\Requests\SettingsRequest;
use App\Plugins\DattoRMM\Models\SiteInfo;
use Crypt;
use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use kamermans\OAuth2\GrantType\RefreshToken;
use kamermans\OAuth2\GrantType\PasswordCredentials;
use kamermans\OAuth2\OAuth2Middleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use JsValidator;
use Lang;
use Log;
use Redirect;
use Request;
use Response;
use Session;
use TemplateView;
use Schema;

class DattoRMM extends Plugin implements ScheduledTaskInterface
{
    const ACTIVE = 0;
    const ERROR = 1;
    const NOT_CONFIGURED = 2;

    /**
     * Plugin identifier.
     */
    const IDENTIFIER = 'DattoRMM';

    /**
     * Initialise the plugin.
     */
    public function __construct()
    {
        parent::__construct();

        $this->setIdentifier(self::IDENTIFIER);

        // Register the settings page.
        $this->registerSetting('plugin.dattormm.settings');
    }

    /**
     * Get the settings page.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function getSettingsPage()
    {
        $settings = $this->settings();

        // // Check that the ticket custom field still exists, else delete it
        // $this->getRelatedServiceField();

        // Get brands (add a default with ID 0 to start)
        $brands = [['id' => 0, 'name' => Lang::get('general.default')]] + brand_config(null)->toArray();

        foreach ($brands as $brand) {
            // Attempt to connect to Datto RMM for status
            if (! empty($settings['brand' . $brand['id'] . '-datto_url'])
                && ! empty($settings['brand' . $brand['id'] . '-datto_api_key'])
                && ! empty($settings['brand' . $brand['id'] . '-datto_api_sec'])
            ) {
                // Try an API call
                $client = $this->dattoRmmConnect();
                $response = $this->getDattoSites($client);

                if (isset($response['sites'])) {
                    // All working well
                    $settings['brand' . $brand['id'] . '-status'] = self::ACTIVE;
                } else {
                    // Something went wrong
                    $settings['brand' . $brand['id'] . '-status'] = self::ERROR;

                    // If there's an error message, show it
                    if (isset($response['message'])) {
                        $settings['brand' . $brand['id'] . '-error_message'] = $response['message'];
                    }
                }
            } else {
                // Not fully configured
                $settings['brand' . $brand['id'] . '-status'] = self::NOT_CONFIGURED;
            }
        }

        // // Roles list for permission assignment
        // $roles = Role::pluck('name', 'id')->all();
        // $permissions = Permission::with('roles')->where('name', 'LIKE', 'whmcsinformation_%')->get();

        return TemplateView::other('DattoRMM::settings')
            ->with('jsValidator', JsValidator::formRequest(SettingsRequest::class))
            ->with('fields', $settings)
            ->with('brands', $brands);
            // ->with('roles', $roles)
            // ->with('permissions', $permissions);
    }

    /**
     * Update the settings.
     *
     * @param  SettingsRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateSettings(SettingsRequest $request)
    {
        $brands = brand_config(null);

        // Starting list of inputs
        $inputs = [
            'brand0-datto_url', 'brand0-datto_api_key', 'brand0-datto_api_sec'
        ];
        $apiKeyInput = [0 => 'brand0-datto_api_key'];
        $apiSecInput = [0 => 'brand0-datto_api_sec'];

        // Add inputs for each brand other than default
        foreach ($brands as $brand) {
            array_push(
                $inputs,
                'brand' . $brand->id . '-datto_url',
                'brand' . $brand->id . '-datto_api_key',
                'brand' . $brand->id . '-datto_api_sec'
            );
            $apiKeyInput[$brand->id] = 'brand' . $brand->id . '-datto_api_key';
            $apiSecInput[$brand->id] = 'brand' . $brand->id . '-datto_api_sec';
        }

        // Get settings data
        $data = Request::all($inputs);

        foreach ($apiKeyInput as $brandId => $apiKeyInput) {
            if (! empty($data[$apiKeyInput])) {
                // Encrypt password if included
                $data[$apiKeyInput] = Crypt::encrypt($data[$apiKeyInput]);
            } elseif (! empty($data['brand' . $brandId . '-datto_url'])) {
                // Don't update as we may wish to not update what is already saved
                // Only do this if the system URL and API username is set (DEV-2430)
                unset($data[$apiKeyInput]);
            }
        }

        foreach ($apiSecInput as $brandId => $apiSecInput) {
            if (! empty($data[$apiSecInput])) {
                // Encrypt password if included
                $data[$apiSecInput] = Crypt::encrypt($data[$apiSecInput]);
            } elseif (! empty($data['brand' . $brandId . '-datto_url'])) {
                // Don't update as we may wish to not update what is already saved
                // Only do this if the system URL and API username is set (DEV-2430)
                unset($data[$apiSecInput]);
            }
        }

        // Work through each row of data
        foreach ($data as $key => $value) {
            if (! isset($value)) {
                continue;
            }

            if ($value !== '') {
                $this->addSetting($key, $value);
            } else {
                $this->removeSetting($key);
            }
        }

        // All done, return with a success message
        Session::flash('success', Lang::get('messages.success_settings'));

        return Redirect::route('plugin.dattormm.settings');
    }

    /**
     * Plugins can run an installation routine when they are activated. This will typically include adding default
     * values, initialising database tables and so on.
     *
     * @return boolean
     */
    public function activate()
    {
        try {
            // Add Setting Page Permissions.
            $attributes = ['view' => true, 'create' => true, 'update' => true, 'delete' => true];
            $this->addPermission('settings', $attributes, 'DattoRMM::lang.permission');

            // Create Organization to site mapping table
            if (! Schema::hasTable('datto_rmm_sites')) {
                Schema::create('datto_rmm_sites', function (Blueprint $table) {
                    $table->engine = 'InnoDB';

                    $table->charset = 'utf8mb4';
                    $table->collation = 'utf8mb4_unicode_ci';

                    $table->increments('id')->unsigned();

                    $table->integer('org_id')->default(1);
                    $table->foreign('org_id')->references('id')->on('user_organisation')->onDelete('cascade');

                    $table->longInteger('datto_site_id');
                    $table->string('datto_site_uid');
                    $table->string('datto_site_accountUid');
                    $table->string('datto_site_name');
                    $table->string('datto_site_description');
                    $table->integer('datto_site_numDevices');
                    $table->integer('datto_site_numOnlineDevices');
                    $table->integer('datto_site_numOfflineDevices');
                    $table->string('datto_site_portalUrl');
                    $table->tinyInteger('enabled')->default(0);
                });
            }

            //Scheduled Tasks to grab sites every 15 minutes
            ScheduledTask::register(
                'Sync Datto RMM Sites',
                'This task syncs the active sites in Datto RMM to SupportPal',
                885
            );

            //TO DO - add devices tables

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get DattoRMM Token
     *
     * @return Client
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

        $settings['brand' . $brandId . '-datto_api_key'] = decode($settings['brand' . $brandId . '-datto_api_key']);
        $settings['brand' . $brandId . '-datto_api_sec'] = decode($settings['brand' . $brandId . '-datto_api_sec']);

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

            // $grant_type = new ClientCredentials($reauth_client, $reauth_config);
            // $oauth = new OAuth2Middleware($grant_type);
            $token = new PasswordCredentials($reauth_client, $reauth_config);
            // $reauth_client->setGrantType($token);

            // $refreshToken = new RefreshToken($reauth_config);
            // $reauth_client->setRefreshTokenGrantType($refreshToken);
            $oauth = new OAuth2Middleware($token);
            $stack = HandlerStack::create();
            $stack->push($oauth);

            // This is the normal Guzzle client that you use in your application
            $client = new Client([
                'handler' => $stack,
                'auth'    => 'oauth',
            ]);

            // $response = $client->get($settings['brand' . $brandId . '-datto_url'].'api/v2/account/sites');
        }

        // return json_decode($response->getBody(), true);
        return $client;
    }

    /**
     * Get Datto Sites
     * 
     * @return JsonResponse
     */
    public function getDattoSites($client, $brandId = 0)
    {
        $settings = $this->settings();

        // If any details are empty, use from the default brand
        if (empty($settings['brand' . $brandId . '-datto_url'])) {
            $settings['brand' . $brandId . '-datto_url'] = Arr::get($settings, 'brand0-datto_url');
        }

        $response = $client->get($settings['brand' . $brandId . '-datto_url'].'api/v2/account/sites');

        return json_decode($response->getBody(), true);
    }

    /**
     * Deactivating serves as temporarily disabling the plugin, but the files still remain. This function should
     * typically clear any caches and temporary directories.
     *
     * @return boolean
     */
    public function deactivate()
    {
        try {
            // Remove scheduled tasks
            ScheduledTask::deregister();

            return true;
        } catch (Exception $e) {
            return false;
        }
    
        return true;
    }

    /**
     * When a plugin is uninstalled, it should be completely removed as if it never was there. This function should
     * delete any created database tables, and any files created outside of the plugin directory.
     *
     * @return boolean
     */
    public function uninstall()
    {
        try {
            // Remove scheduled tasks
            ScheduledTask::deregister();

            // Remove settings
            $this->removeSettings();

            // Remove permissions
            $this->removePermissions();

            // Drop Organization to Datto Site Mapping table if it exists
            if (Schema::hasTable('comits_org_site_map')) {
                Schema::disableForeignKeyConstraints();
                Schema::drop('comits_org_site_map');
                Schema::enableForeignKeyConstraints();
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Validate the Datto RMM authentication details
     *
     * @return JsonResponse
     */
    public function validateAuth()
    {
        $data = Request::all(['datto_url', 'datto_api_key', 'datto_api_sec']);

        try {
            // If the API Secret hasn't been entered, use existing value in settings
            $existingApiKey = Arr::get($this->settings(), 'datto_api_key');
            if (empty($data['datto_api_key']) && ! empty($existingApiKey)) {
                // $data['datto_api_key'] = decode($$existingApiKey);
                $data['datto_api_key'] = $existingApiKey;
            }

            // If the API Secret hasn't been entered, use existing value in settings
            $existingApiSecret = Arr::get($this->settings(), 'datto_api_sec');
            if (empty($data['datto_api_sec']) && ! empty($existingApiSecret)) {
                // $data['datto_api_sec'] = decode($existingApiSecret);
                // $data['datto_api_sec'] = $existingApiSecret;
            }

            $client = $this->dattoRmmConnect();
            $response = $this->getDattoSites($client);

            // $response['sites'] = "test";

            if (isset($response['sites'])) {
                return Response::json([
                    'status'  => 'success',
                    'message' => $response,
                    'data'    => null
                ]);
            }

            // throw new RuntimeException((string) Arr::get($response, 'message'));
        } catch (Exception $e) {
            return Response::json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'data'    => null
            ]);
        }
    }

    /**
     * Scheduled Task to sync Datto RMM Sites
     */
    public function runTask()
    {
        $settings = $this->settings();

        // Get brands (add a default with ID 0 to start)
        $brands = [['id' => 0, 'name' => Lang::get('general.default')]] + brand_config(null)->toArray();

        foreach ($brands as $brand) {
            // Attempt to connect to Datto RMM
            if (! empty($settings['brand' . $brand['id'] . '-datto_url'])
                && ! empty($settings['brand' . $brand['id'] . '-datto_api_key'])
                && ! empty($settings['brand' . $brand['id'] . '-datto_api_sec'])
            ) {
                // Try an API call
                $client = $this->dattoRmmConnect();
                $response = $this->getDattoSites($client);

                if (isset($response['sites'])) {
                    // All working well
                    $settings['brand' . $brand['id'] . '-status'] = self::ACTIVE;

                    foreach($response['sites'] as $site) {
                        $site = SiteInfo::updateOrCreate([
                            'datto_site_id'                 => $site['id'],
                            'datto_site_uid'                => $site['uid']
                        ],
                        [
                            'datto_site_accountUid'         => $site['accountUid'],
                            'datto_site_name'               => $site['name'],
                            'datto_site_description'        => $site['description'],
                            'datto_site_numDevices'         => $site['devicesStatus']['numberOfDevices'],
                            'datto_site_numOnlineDevices'   => $site['devicesStatus']['numberOfOnlineDevices'],
                            'datto_site_numOfflineDevices'  => $site['devicesStatus']['numberOfOfflineDevices'],
                            'datto_site_portalUrl'          => $site['portalUrl']
                        ]);
                    }
                } else {
                    // Something went wrong
                    $settings['brand' . $brand['id'] . '-status'] = self::ERROR;

                    // If there's an error message, show it
                    if (isset($response['message'])) {
                        $settings['brand' . $brand['id'] . '-error_message'] = $response['message'];
                    }

                    throw new RuntimeException((string) Arr::get($response, 'message'));
                }
            } else {
                // Not fully configured
                $settings['brand' . $brand['id'] . '-status'] = self::NOT_CONFIGURED;
            }
        }
    }
}
