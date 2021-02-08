<?php

namespace HTMLBlocks;

use Eloquent\Pathogen\Path;
use ValueError;

class Config
{
    private string $path;

    private string $htmlPath;

    private array $config;

    public function __construct(string $path, array $config)
    {
        if (!array_key_exists('html', $config)) {
            throw new ValueError("No HTML file specified in configuration");
        }
        $configPath = Path::fromString($path);
        $basePath = $configPath->replaceName('');
        $this->htmlPath = $basePath->join(Path::fromString($config['html']))->string();
        $this->config = $config;
        $this->path = $path;
    }

    /**
     * Get the value of path
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Get the value of config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Get the value of htmlPath
     */
    public function getHtmlPath()
    {
        return $this->htmlPath;
    }
}
