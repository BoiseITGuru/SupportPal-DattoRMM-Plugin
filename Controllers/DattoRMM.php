<?php

namespace App\Plugins\DattoRMM\Controllers;

use App\Modules\Core\Controllers\Plugins\Plugin;
use App\Plugins\DattoRMM\Requests\SettingsRequest;
use Illuminate\Database\Schema\Blueprint;
use JsValidator;
use Lang;
use Redirect;
use Session;
use TemplateView;
use Schema;

class DattoRMM extends Plugin
{
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
        return TemplateView::other('DattoRMM::settings')
            ->with('jsValidator', JsValidator::formRequest(SettingsRequest::class))
            ->with('fields', $this->settings());
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
}
