<?php

use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

defined('TYPO3') or die();

(static function (): void {
    // OAuth bearer tokens are reused across requests until they expire, so they are cached rather than
    // fetched per rate lookup. The per-entry lifetime is set from the token's own expires_in.
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['products_shipping_ups_token'] ??= [
        'frontend' => VariableFrontend::class,
        'backend' => Typo3DatabaseBackend::class,
        'groups' => [
            'system',
        ],
    ];
})();
