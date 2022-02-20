<?php
/**
 * SiteInfo Model
 *
 * @package    App\Plugins\DattoRMM\Models
 * @copyright  Copyright (c) 2022 Technology Advocates (http://www.tech-advocates.com)
 * @since      File available since Release 0.0
 */
namespace App\Plugins\DattoRMM\Models;

use App\Modules\Core\Models\BaseModel;
use Illuminate\Database\Query\Builder;

/**
 * Class SiteInfo
 *
 * @package    App\Plugins\DattoRMM\Models
 * @copyright  Copyright (c) 2022 Technology Advocates (http://www.tech-advocates.com)
 * @version    Release: @package_version@
 * @since      Class available since Release 0.0
 */
class SiteInfo extends BaseModel
{
    /**
     * Name of the table
     *
     * @var string
     */
    protected $table = 'datto_rmm_sites';

    /**
     * Which fields are fillable
     *
     * @var array
     */
    protected $fillable = ['org_id', 'datto_site_id', 'datto_site_uid', 'datto_site_accountUid', 'datto_site_name', 'datto_site_description', 'datto_site_numDevices', 'datto_site_numOnlineDevices', 'datto_site_numOfflineDevices', 'datto_site_portalUrl', 'enabled'];

    /**
     * We set the updated_at attribute manually, do not do it automatically.
     *
     * @var boolean
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'org_id'                        => 'int',
        'datto_site_id'                 => 'int',
        'datto_site_uid'                => 'string',
        'datto_site_accountUid'         => 'string',
        'datto_site_name'               => 'string',
        'datto_site_description'        => 'string',
        'datto_site_numDevices'         => 'int',
        'datto_site_numOnlineDevices'   => 'int',
        'datto_site_numOfflineDevices'  => 'int',
        'datto_site_portalUrl'          => 'string',
        'enabled'                       => 'int',
    ];
}
