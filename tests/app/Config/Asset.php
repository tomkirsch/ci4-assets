<?php namespace Config;

use Tomkirsch\Assets\Config\AssetConfig;

class Asset extends AssetConfig{
	public $cacheKey = 1;
	public $minify = TRUE;
	public $minifyConfigPath = ROOTPATH.'../tomkirsch/Assets/ThirdParty';
}