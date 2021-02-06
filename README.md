# Assets
This module will assist in minifying JS, CSS, and fonts, as well as managing sessionStorage cache using JS.

## Installation
composer.json
```
	"require":{
		"tomkirsch/assets":"^1"
	}
```
Run `composer install --no-dev --optimize-autoloader`

To override defaults, create the config file for your app `App\Config\AssetConfig`
```
<?php namespace Config;

use Tomkirsch\Assets\Config\AssetConfig;

class Asset extends AssetConfig{
	public $cacheKey = 1;
	public $minify = TRUE;
	public $minifyConfigPath = COMPOSER_PATH.'/tomkirsch/Assets/ThirdParty';
}
```
See `Tomkirsch\Assets\Config\AssetConfig.php` for full list.

Open App/Config/Services.php and add a method to grab an instance of the library:
```
	public static function assets($getShared = true, $config=NULL){
		if(!$config) $config = new Asset();
		return $getShared ? static::getSharedInstance('assets') : new \Tomkirsch\Assets\Libraries\AssetLib($config);
	}
```
Open App/Config/Routes.php and add the Min controller:
```
$routes->get('min', '\Tomkirsch\Assets\Controllers\Min::index');
```

## Usage

Add site-wide assets in your Controller:
```
<?php namespace App\Controllers;
use CodeIgniter\Controller;

class MyController extends Controller{
	protected $assets;
	
	public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger){
		parent::initController($request, $response, $logger);

		// generate assets for non-ajax requests
		if(!$this->request->isAJAX()){
			$this->assets = service('assets');
		
			// fonts
			$assets->addFont([
				'path'		=> 'anton',
				'family'	=> 'Anton',
				'name'		=> 'anton', 
				'weight'	=> '400',
				'style'		=> 'normal',
				'priority'	=> 'pre_dom',
				'type'		=> 'css',
				'fallbacks'	=> 'woff2,woff,ttf,eot',
			]);

			// styles
			$assets->addStyles([
				[
					'content'=>'css/main.css',
					'priority'=>$this->assets::PRIORITY_POSTDOM_LOCAL,
				],
			]);

			// scripts
			$assets->addScripts([
				'main.js', // strings are assumed post-dom local priority
			]);
		}
	}
	
	public function mymethod(){
		// styles
		$this->assets->addStyles([
			[
				'content'=>'css/critical.css',
				'critical'=>TRUE,
			],
			[
				'content'=>'css/one.css',
				'priority'=>$this->assets::PRIORITY_POSTDOM_LOCAL,
			],
			[
				'content'=>'css/two.css',
				'priority'=>$this->assets::PRIORITY_POSTDOM_LOCAL,
			],
		]);
		
		// scripts
		$this->assets->addScripts([
			[
				'content'=>'test-one.js',
				'priority'=>$this->assets::PRIORITY_PREDOM_LOCAL,
			],
			// post-DOM local scripts can just be string file names
			'test-two.js',
			'test-three.js',
		]);
	}
}
```

And finally write the output in your view at the correct locations:
```
<html>
	<head>
		<?= config('assets')->renderHead() ?>
	</head>
	<body>
		<h1>Your HTML</h1>
		...

		<!-- render the body near the closing tag -->
		<?= config('assets')->renderBody() ?>
	</body>
</html>
```
