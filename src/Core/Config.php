<?php
/**
 * Configuration Loader
 * Loads and manages application configuration
 */

namespace P2P\Core;

class Config
{
    private static ?array $config = null;
    private static string $configPath = '';

    /**
     * Initialize configuration
     */
    public static function init(string $path = null): void
    {
        self::$configPath = $path ?? dirname(__DIR__, 2) . '/config/config.php';
        self::load();
    }

    /**
     * Load configuration from file
     */
    private static function load(): void
    {
        if (self::$config === null && file_exists(self::$configPath)) {
            self::$config = require self::$configPath;
        }
        
        if (self::$config === null) {
            self::$config = [];
        }
    }

    /**
     * Get configuration value by key
     * Supports dot notation for nested values
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$config === null) {
            self::load();
        }

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set configuration value
     */
    public static function set(string $key, mixed $value): void
    {
        if (self::$config === null) {
            self::load();
        }

        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    /**
     * Get all configuration
     */
    public static function all(): array
    {
        if (self::$config === null) {
            self::load();
        }
        return self::$config;
    }
}
