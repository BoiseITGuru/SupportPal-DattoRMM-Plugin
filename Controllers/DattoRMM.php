<?php

namespace App\Plugins\DattoRMM\Controllers;

use App\Modules\Core\Controllers\Plugins\Plugin;
use App\Modules\Core\Models\Brand;
use App\Modules\Core\Models\CustomField;
use App\Modules\Ticket\Models\Department;
use App\Modules\Ticket\Models\TicketCustomField;
use App\Modules\Ticket\Models\TicketCustomFieldValue;
use App\Plugins\DattoRMM\Requests\SettingsRequest;
use Crypt;
use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use JsValidator;
use Lang;
use Log;
use Redirect;
use Request;
use Response;
use Session;
use TemplateView;
use Schema;

class DattoRMM extends Plugin
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
            // Attempt to connect to WHMCS for status
            if (! empty($settings['brand' . $brand['id'] . '-datto_url'])
                && ! empty($settings['brand' . $brand['id'] . '-datto_api_key'])
                && ! empty($settings['brand' . $brand['id'] . '-datto_api_sec'])
            ) {
                // // Try an API call
                // $response = $this->whmcsConnect(['action' => 'getstats'], $brand['id']);

                // if (isset($response['result']) && $response['result'] === 'success') {
                //     // All working well
                //     $settings['brand' . $brand['id'] . '-status'] = self::ACTIVE;
                // } else {
                //     // Something went wrong
                //     $settings['brand' . $brand['id'] . '-status'] = self::ERROR;

                //     // If there's an error message, show it
                //     if (isset($response['message'])) {
                //         $settings['brand' . $brand['id'] . '-error_message'] = $response['message'];
                //     }
                // }
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
        $data = $request->all();

        // Work through each row of data.
        foreach ($data as $key => $value) {
            if (! empty($value) || $value == 0) {
                $this->addSetting($key, $value);
            }
        }

        // All done, return with a success message.
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
            if (! Schema::hasTable('comits_org_site_map')) {
                Schema::create('comits_org_site_map', function (Blueprint $table) {
                    $table->engine = 'InnoDB';

                    $table->charset = 'utf8mb4';
                    $table->collation = 'utf8mb4_unicode_ci';

                    $table->increments('id')->unsigned();

                    $table->integer('org_id')->unsigned();
                    $table->foreign('org_id')->references('id')->on('user_organisation')->onDelete('cascade');

                    $table->integer('datto_site_id')->default(0);
                    $table->tinyInteger('enabled')->default(0);
                });
            }

            //TO DO - add devices tables

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Deactivating serves as temporarily disabling the plugin, but the files still remain. This function should
     * typically clear any caches and temporary directories.
     *
     * @return boolean
     */
    public function deactivate()
    {
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
                $data['datto_api_sec'] = $existingApiSecret;
            }

            // // Attempt call with details provided
            // $fields = ['action' => 'getadmindetails'];
            // $response = $this->whmcsConnect($fields, 0, $data);3

            $response = ['result' => 'success'];


            if (isset($response['result']) && $response['result'] === 'success') {
                return Response::json([
                    'status'  => 'success',
                    'message' => null,
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
}
