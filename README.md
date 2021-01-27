# Assets
This module will assist in minifying JS, CSS, and fonts, as well as managing sessionStorage cache using JS.

## Installation:

Open App/Config/Autoload.php and add the namespace to the $psr4 array: `'Tomkirsch' 		=> ROOTPATH.'vendor/tomkirsch',`

Open/Create your app's .env file, and change settings to what you'd like. See `Config\AssetConfig.php` for full list.
```
#--------------------------------------------------------------------
# Assets
#--------------------------------------------------------------------
Tomkirsch\Assets\Config\AssetConfig.minify = true
```

Open App/Config/Services.php and add a method to grab an instance of the library:
```
	public static function assets($getShared = true){
		return $getShared ? static::getSharedInstance('assets') : new \Tomkirsch\Assets\Libraries\AssetLib();
	}
```
Open App/Config/Routes.php and add the Min controller:
```
$routes->get('min', '\Tomkirsch\Assets\Controllers\Min::index');
```

Now add site-wide assets in your Controller:
```
<?php namespace App\Controllers;

use CodeIgniter\Controller;
use Tomkirsch\Assets\Libraries\AssetLib;

class MyController extends Controller{
	protected $assets;
	
	public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger){

		// Do Not Edit This Line
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
					'priority'=>AssetLib::PRIORITY_POSTDOM_LOCAL,
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
				'priority'=>AssetLib::PRIORITY_POSTDOM_LOCAL,
			],
			[
				'content'=>'css/two.css',
				'priority'=>AssetLib::PRIORITY_POSTDOM_LOCAL,
			],
		]);
		
		// scripts
		$this->assets->addScripts([
			[
				'content'=>'test-one.js',
				'priority'=>AssetLib::PRIORITY_PREDOM_LOCAL,
			],
			// post-DOM local scripts can just be string file names
			'test-two.js',
			'test-three.js',
		]);
		
		print view('home', [
			'assetsHead'		=>$this->assets->renderHead(), // this code will go in your html <head>
			'assetsBody'		=>$this->assets->renderBody(), // this code will go just before </body>
		]);
	}
}
```

And finally write the HTML/JS code in your view at the correct locations:
```
<html>
	<head>
		<?= $assetsHead ?>
	</head>
	...
	<body>
	<?= $assetsBody ?>
	</body>
</html>
```
