<?php

/**
 * AbraFlexi Changes processor - nastavení testů.
 *
 * @author     Vítězslav Dvořák <vitex@arachne.cz>
 * @copyright  2015-2020 Spoje.Net 2021-2022 VitexSoftware
 */

include_once file_exists('../vendor/autoload.php') ? '../vendor/autoload.php' : 'vendor/autoload.php';

echo __DIR__;

if (file_exists(__DIR__ . '/../.env')) {
    (new \Ease\Shared())->loadConfig(__DIR__ . '/../.env', true);
}
