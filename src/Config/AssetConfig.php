<?php namespace Tomkirsch\Assets\Config;

use CodeIgniter\Config\BaseConfig;

class AssetConfig extends BaseConfig
{
	// use this to test caching on development servers
	public $ignoreDevEnv = FALSE;
	
	// cachebuster. When you update your styles, change this value to clear old cache from browsers
	// NOTE: the code will automatically check for $_ENV['CI_ENVIRONMENT'] === 'development' and set to time() if $ignoreDevEnv is FALSE
	public $cacheKey = 1; 
	
	// where your font files live
	public $fontPath = 'fonts/';
	
	// where your CSS files live
	public $cssPath = 'css/';
	
	// where your JS files live
	public $jsPath = 'js/';
	
	// whether to use minfy library
	// https://github.com/mrclay/minify
	public $minify = FALSE;
	
	// change this to a place in your app where config.php and groupsConfig.php live if you'd like to change them
	public $minifyConfigPath = ROOTPATH.'vendor\tomkirsch\assets\src\ThirdParty\\';
	
	// the URI where your minify controller is (eg. example.com/min?f=home.css)
	public $minifyUri = 'min';
	
	// set to false to disable both browser cache and sessionStorage cache
	public $cache = TRUE; 
	
	// debug JS code with console
	public $debugJs = FALSE;
	
	// styleLoader view
	public $styleLoaderView = 'Tomkirsch\Assets\Views\styleLoader';
	
	// scriptLoader view
	public $scriptLoaderView = 'Tomkirsch\Assets\Views\scriptLoader';
	
}