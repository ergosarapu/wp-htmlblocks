<?php

namespace HTMLBlocks;

use Symfony\Component\Yaml\Yaml;
use ValueError;

use function Env\env;

define('HTMLBLOCKS_CONFIG', 'HTMLBLOCKS_CONFIG');

class ConfigLoader
{
    private string $confFilePaths;

    private array $configList = [];

    /**
     * @throws ValueError
     */
    public function __construct(string $confFilePaths = null)
    {
        if (!$confFilePaths) {
            // Try to get paths from env
            $confFilePaths = env(HTMLBLOCKS_CONFIG);
            if (!$confFilePaths) {
                throw new ValueError("No '" . HTMLBLOCKS_CONFIG . "' found in env");
            }
        }
        $this->confFilePaths = $confFilePaths;
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        $paths = explode(PATH_SEPARATOR, $this->confFilePaths);
        foreach ($paths as $path) {
            $config = new Config($path, Yaml::parseFile($path)['block']);
            array_push($this->configList, $config);
        }
    }

    public function listConfigs(): array
    {
        return $this->configList;
    }
}
