<?php
// Debian autoloader for abraflexi-mailer
require_once '/usr/share/php/AbraFlexi/autoload.php';
require_once '/usr/share/php/EaseHtml/autoload.php';
require_once '/usr/share/php/AbraFlexiBricks/autoload.php';
require_once '/usr/share/php/Symfony/Component/Mailer/autoload.php';

spl_autoload_register(function ($class) {
    $prefix = 'AbraFlexi\\Mailer\\';
    $base_dir = '/usr/lib/abraflexi-mailer/AbraFlexi/Mailer/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});



// No application-specific classes to autoload

require_once '/usr/share/php/Composer/InstalledVersions.php';

(function (): void {
    $versions = [];
    foreach (\Composer\InstalledVersions::getAllRawData() as $d) {
        $versions = array_merge($versions, $d['versions'] ?? []);
    }
    $name    = 'unknown';
    $version = defined('APP_VERSION') ? APP_VERSION : '0.0.0';
    $versions[$name] = ['pretty_version' => $version, 'version' => $version,
        'reference' => null, 'type' => 'library', 'install_path' => __DIR__,
        'aliases' => [], 'dev_requirement' => false];
    \Composer\InstalledVersions::reload([
        'root' => ['name' => $name, 'pretty_version' => $version, 'version' => $version,
            'reference' => null, 'type' => 'project', 'install_path' => __DIR__,
            'aliases' => [], 'dev' => false],
        'versions' => $versions,
    ]);
})();
