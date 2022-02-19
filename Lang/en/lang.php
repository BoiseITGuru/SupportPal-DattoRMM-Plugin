<?php

return [

    "name"                      => "Datto RMM",
    "description"               => "Datto RMM Integration for syncing device info and alerts",

    // Status
    "active_desc"               => "The connection to Datto RMM is correctly configured and working.",
    "error_desc"                => "The plugin cannot properly connect to Datto RMM, please verify the authentication details below.",
    "not_configured"            => "Not Configured",
    "not_configured_desc"       => "The plugin has not yet been fully configured, please fill out the details below.",
    "not_configured_brand_desc" => "The plugin has not yet been fully configured for this brand.",

    // Settings
    "authentication"            => "Authentication",
    "authentication_desc"       => "Enter your Datto RMM API details below to allow the plugin to connect.",
    // "authentication_desc"       => "Enter your Datto RMM API details below to allow the plugin to connect. If you have multiple Datto RMM Accounts, add a brand to get the option to connect to a different account for that brand's tickets.",
    "authentication_brand_desc" => "You can configure the plugin to connect to multiple Datto RMM Accounts. Please ensure to set your main account's configuration details for the default brand tab and then set other accounts details in a different brand tab.",
    "datto_url"                 => "Datto API URL",
    "datto_url_desc"            => "Full Datto API URL Path",

    "datto_api_key"             => "Datto API Key",
    "datto_api_key_desc"        => "Datto API Key",

    "datto_api_sec"             => "Datto API Secret",
    "datto_api_sec_desc"        => "Datto API Secret",

    "required_field"            => "The field is required.",

    "permission"                => "Manage Settings",


    "validation_url"            => "The :attribute must be a valid URL.",
    "validation_trailing_slash" => "The :attribute must end with a trailing slash.",

];
