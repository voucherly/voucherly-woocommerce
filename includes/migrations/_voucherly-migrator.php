<?php

defined('ABSPATH') || exit;

class Voucherly_Migrator
{
    private const DB_VERSION_OPTION = 'voucherly_db_version';

    /**
     * List of migration files, keyed by version.
     * Each file must define two functions: voucherly_migrate_X_Y_Z_up() and _down().
     * Add new entries at the bottom in ascending version order.
     */
    private const MIGRATIONS = [
        '1.2.0' => '20260325_migration-1.2.0.php',
    ];

    /**
     * Check if migrations need to run and execute them (upgrade or downgrade).
     */
    public static function run()
    {
        $current_version = self::getCurrentVersion();
        $db_version = get_option(self::DB_VERSION_OPTION, '0.0.0');

        if (version_compare($db_version, $current_version, '=')) {
            return;
        }

        if (version_compare($db_version, $current_version, '<')) {
            self::upgrade($db_version);
        } else {
            self::downgrade($db_version, $current_version);
        }

        update_option(self::DB_VERSION_OPTION, $current_version);
    }

    /**
     * Run 'up' for all migrations above the stored db version.
     *
     * @param mixed $db_version
     */
    private static function upgrade($db_version)
    {
        foreach (self::MIGRATIONS as $version => $file) {
            if (version_compare($db_version, $version, '<')) {
                require_once __DIR__.'/'.$file;
                $function = self::buildFunctionName($version, 'up');
                if (function_exists($function)) {
                    call_user_func($function);
                }
            }
        }
    }

    /**
     * Run 'down' in reverse order for all migrations above the target version.
     *
     * @param mixed $db_version
     * @param mixed $target_version
     */
    private static function downgrade($db_version, $target_version)
    {
        foreach (array_reverse(self::MIGRATIONS, true) as $version => $file) {
            if (version_compare($version, $db_version, '<=') && version_compare($version, $target_version, '>')) {
                require_once __DIR__.'/'.$file;
                $function = self::buildFunctionName($version, 'down');
                if (function_exists($function)) {
                    call_user_func($function);
                }
            }
        }
    }

    /**
     * Build the function name for a migration version and direction.
     * E.g. '1.2.0' + 'up' => 'voucherly_migrate_1_2_0_up'.
     *
     * @param mixed $version
     * @param mixed $direction
     */
    private static function buildFunctionName($version, $direction)
    {
        return 'voucherly_migrate_'.str_replace('.', '_', $version).'_'.$direction;
    }

    /**
     * Get current plugin version from main plugin file header.
     */
    private static function getCurrentVersion()
    {
        return get_plugin_data(dirname(__DIR__, 2).'/woocommerce-gateway-voucherly.php')['Version'];
    }
}
