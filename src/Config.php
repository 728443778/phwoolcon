<?php
namespace Phwoolcon;

use Phalcon\Config as PhalconConfig;
use Phalcon\Di;

class Config
{
    protected static $config;

    public static function clearCache()
    {
        is_file($cacheFile = storagePath('cache/config.php')) and unlink($cacheFile);
        Cache::delete('db_configs');
    }

    public static function environment()
    {
        return static::get('environment');
    }

    /**
     * @param $key          string
     * @param $defaultValue mixed
     * @return mixed
     */
    public static function get($key = null, $defaultValue = null)
    {
        return $key === null ? static::$config : fnGet(static::$config, $key, $defaultValue, '.');
    }

    protected static function loadDb(PhalconConfig $config)
    {
        $dbConfig = new PhalconConfig(Model\Config::all());
        $config->merge($dbConfig);
        static::$config = $config->toArray();
    }

    protected static function loadFiles($files)
    {
        $settings = [];
        foreach ($files as $file) {
            // @codeCoverageIgnoreStart
            if (!is_file($file)) {
                continue;
            }
            // @codeCoverageIgnoreEnd
            $key = pathinfo($file, PATHINFO_FILENAME);
            $value = include $file;
            $settings[$key] = is_array($value) ? $value : [];
        }
        return $settings;
    }

    public static function register(Di $di)
    {
        // @codeCoverageIgnoreStart
        if (is_file($cacheFile = storagePath('cache/config.php'))) {
            static::$config = include $cacheFile;
            Config::get('app.cache_config') or static::clearCache();
            return;
        }
        // @codeCoverageIgnoreEnd
        $defaultFiles = glob($_SERVER['PHWOOLCON_CONFIG_PATH'] . '/*.php');
        $environment = isset($_SERVER['PHWOOLCON_ENV']) ? $_SERVER['PHWOOLCON_ENV'] : 'production';
        $environmentFiles = glob($_SERVER['PHWOOLCON_CONFIG_PATH'] . '/' . $environment . '/*.php');

        $config = new PhalconConfig(static::loadFiles($defaultFiles));
        $environmentSettings = static::loadFiles($environmentFiles);
        $environmentSettings['environment'] = $environment;
        $environmentConfig = new PhalconConfig($environmentSettings);
        $config->merge($environmentConfig);

        $di->remove('config');
        $di->setShared('config', $config);
        static::$config = $config->toArray();
        Config::get('database.default') and static::loadDb($config);
        // @codeCoverageIgnoreStart
        if (Config::get('app.cache_config')) {
            is_dir($cacheDir = dirname($cacheFile)) or mkdir($cacheDir, 0777, true);
            file_put_contents($cacheFile, sprintf('<?php return %s;', var_export(static::$config, true)));
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @return mixed
     */
    public static function set($key, $value)
    {
        array_set(static::$config, $key, $value, '.');
        return $value;
    }

    public static function runningUnitTest()
    {
        return static::environment() == 'testing';
    }
}
