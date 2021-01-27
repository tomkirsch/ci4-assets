<?php namespace Tomkirsch\Assets\Libraries;

use Minify\App as MinifyApp; // package: mrclay/minify

/*

Add a font - font files MUST be prepped and named appropriately
	$assetLib->add_font([
		'path'		=> 'anton',		// if your font lives in a subfolder, use the path attribute (otherwise can be omitted)
		'family'	=> 'Anton', 	// what to use in your CSS font-family rule. Ensure this matches the @font-face in font's css file.
		'name'		=> 'anton', 	// file name prefix
		'weight'	=> '400',		// font weight, used in file names. Ensure this matches the @font-face in font's css file.
		'style'		=> 'normal',	// font style, used in file names. Ensure this matches the @font-face in font's css file.
		'priority'	=> 'pre_dom',	// when to load the font, pre_dom or post_dom (post_dom will cause FOUT)
		'type'		=> 'css',		// should be css, but you can set to woff2 if you don't want to support other browsers
		'fallbacks'	=> 'woff2,woff,ttf,eot', // all these files must exist in same naming format
	]);
	
the above code assumes these files exist:
FONTPATH/anton/anton-400-normal.woff2
FONTPATH/anton/anton-400-normal.woff
FONTPATH/anton/anton-400-normal.ttf
FONTPATH/anton/anton-400-normal.eot
*/


class AssetLib{
	const PRIORITY_RAW = 'raw'; // assumes content is text containing CSS/JS - will spit it out inside <style> or <script> tags
	const PRIORITY_INLINE = 'inline'; // PHP will read file contents and spit out inside <style> or <script> tags
	const PRIORITY_PREDOM_REMOTE = 'predom_remote'; // asset will get fetched with normal <link> or <script> tag inside <head>
	const PRIORITY_POSTDOM_REMOTE = 'postdom_remote'; // asset will get injected in <head> afer DOM load
	const PRIORITY_PREDOM_LOCAL = 'predom_local'; // asset will get fetched with normal <link> or <script> tag inside <head> and minified (if possible)
	const PRIORITY_POSTDOM_LOCAL = 'postdom_local'; // asset will get injected in <head> afer DOM load (if possible)
	
	protected $config;
	protected $fonts = [];
	protected $styles = [];
	protected $scripts = [];
	
	// group our output in chunks
	protected $output;
	
	// this is the order we put things in the DOM
	protected $domOrder = [
		self::PRIORITY_RAW,
		self::PRIORITY_INLINE, 
		self::PRIORITY_PREDOM_REMOTE.'_link',
		self::PRIORITY_PREDOM_REMOTE.'_ajax',
		self::PRIORITY_PREDOM_LOCAL.'_link',
		self::PRIORITY_PREDOM_LOCAL.'_ajax',
		self::PRIORITY_POSTDOM_REMOTE.'_ajax',
		self::PRIORITY_POSTDOM_LOCAL.'_ajax',
	];
	
	static function getFontFormat($type){
		switch($type){
			case 'ttf': return 'truetype';
			case 'eot': return 'embedded-opentype';
			default:
				return $type; // woff, woff2
		}
	}
	
	public function __construct($config=NULL){
		$this->config = $config ?? config('Tomkirsch\Assets\Config\AssetConfig');
		// is this a development environment? then ensure caching is disabled!
		if(env('CI_ENVIRONMENT') === 'development' && !$this->config->ignoreDevEnv){
			$this->config->cacheKey = time();
		}
		$this->resetOutput();
	}
	
	// add a font
	public function addFont(array $data){
		$data = array_merge([
			'path'=>'',
		], $data);
		$data['path'] = $this->config->fontPath.$data['path'];
		$this->fonts[] = new Font($data);
		return $this;
	}
	
	public function addStyle(array $data, bool $useConfigPath=TRUE){
		$this->createStyle($data, $useConfigPath);
		return $this;
	}
	public function addStyles(array $data, bool $useConfigPath=TRUE){
		foreach($data as $s){
			$this->addStyle($s, $useConfigPath);
		}
		return $this;
	}
	public function removeStyle($ids){
		if(!is_array($ids)) $ids = [$ids];
		for($i=0; $i<count($this->styles); $i++){
			if(in_array($this->styles[$i]->id, $ids)){
				unset($this->styles[$i]);
			}
		}
		return $this;
	}
	
	public function addScript($data, bool $useConfigPath=TRUE){
		if(!is_array($data)){
			// assume it's a local JS file we want to minify
			$data = [
				'content'=>$data,
			];
		}
		$this->createScript($data, $useConfigPath);
		return $this;
	}
	public function addScripts(array $data, bool $useConfigPath=TRUE){
		foreach($data as $s){
			$this->addScript($s, $useConfigPath);
		}
		return $this;
	}
	public function removeScript($ids){
		if(!is_array($ids)) $ids = [$ids];
		for($i=0; $i<count($this->scripts); $i++){
			if(in_array($this->scripts[$i]->id, $ids)){
				unset($this->scripts[$i]);
			}
		}
		return $this;
	}
	
	// detect if we should serve all CSS via <link>s
	public function isCriticalRequest():bool{
		// detect 'critical' in $_GET (doesn't need a value)
		return isset($_GET['critical']);
	}
	
	// render HTML that goes in <head>
	public function renderHead(){
		$this->resetOutput();
		$critical = $this->isCriticalRequest();
		
		// get the StyleLoader singleton JS code
		$this->output['script'] .= view($this->config->styleLoaderView, [], ['debug'=>FALSE])."\n";
		// init the StyleLoader
		$cacheKey = $this->config->cache ? $this->config->cacheKey : 'false';
		$debugJs = $this->config->debugJs ? 'true' : 'false';
		$this->output['script'] .= 'StyleLoader.init('.$cacheKey.', '.$debugJs.');'."\n";
		
		// now the JS code for ScriptLoader
		$this->output['script'] .= view($this->config->scriptLoaderView, [], ['debug'=>FALSE])."\n";
		// we init ScriptLoader in <body>...
		
		// render fonts
		if(!empty($this->fonts)){
			$data = [];
			// create @font-face declarations for JS fallback and <noscript>
			$fontface = '';
			foreach($this->fonts as $font){
				$ff = $font->fontface();
				$fontface .= $ff;
				$url = base_url($font->filename($font->type));
				$url .= stristr($url, '?') ? '&' : '?';
				$url .= 'v='.$this->config->cacheKey;
				$data[] = [
					'type'=>$font->type,
					'cache'=>$font->cache,
					'url'=>$url,
					'priority'=>stristr('pre', $font->priority) ? 'pre_dom' : 'post_dom',
					'fontface'=>$ff,
				];
			}
			// if running in critical mode, don't use StyleLoader
			if($critical){
				$this->output['style'] .= $fontface."\n";
			}else{
				// use StyleLoader to cache fonts
				$this->output['script'] .= 'StyleLoader.addFonts('.json_encode($data).');'."\n";
				// fallback for no JS
				$this->output['noscript'] .= '<style type="text/css">'.$fontface.'</style>'."\n";
			}
		}
		
		// now render styles/scripts
		// to optimize delivery and maintain order, we need to chunk these into groups based on priority
		$styleDict = [];
		foreach($this->styles as $style){
			// are we in critical mode?
			if($critical){
				// is this style a critical stylesheet? then we don't add it!
				if($style->critical) continue;
				// is the style NOT raw css? Then force it to load PRE-DOM using regular old <link> elements
				if($style->priority !== AssetLib::PRIORITY_RAW){
					$style->priority = ($style->priority === AssetLib::PRIORITY_POSTDOM_LOCAL) ? AssetLib::PRIORITY_PREDOM_LOCAL : AssetLib::PRIORITY_PREDOM_REMOTE;
					$style->ajax = FALSE;
					$style->cache = FALSE;
				}
			}
			// critical styles are always inline
			if($style->critical){
				$style->priority = AssetLib::PRIORITY_INLINE;
				$style->ajax = FALSE;
				$style->cache = FALSE;
			}
			// we need to split pre/post dom stuff depending on ajax option
			$group = $style->priority;
			if(stristr($style->priority, 'dom') !== FALSE){
				$group .= $style->ajax ? '_ajax' : '_link';
			}
			// add to the queue in the correct priority bucket
			if(!isset($styleDict[$group])) $styleDict[$group] = [];
			$styleDict[$group][] = $style->content;
		}
		
		// now scripts
		$scriptDict = [];
		foreach($this->scripts as $script){
			// is script loading after DOM? then it doesn't go in the <head>!
			if($script->priority === self::PRIORITY_POSTDOM_REMOTE || $script->priority === self::PRIORITY_POSTDOM_LOCAL){
				continue;
			}
			$group = $script->priority;
			if(stristr($script->priority, 'dom') !== FALSE){
				// no ajaxed scripts are in head
				$group = $script->priority.'_link';
			}
			// add to the queue in the correct priority bucket
			if(!isset($scriptDict[$group])) $scriptDict[$group] = [];
			$scriptDict[$group][] = $script->content;
		}
		// now assemble based on domOrder
		foreach($this->domOrder as $priority){
			foreach($styleDict as $group=>$queue){
				if($group === $priority){
					$this->renderQueue($queue, $priority, 'style');
				}
			}
			foreach($scriptDict as $group=>$queue){
				if($group === $priority){
					$this->renderQueue($queue, $priority, 'script');
				}
			}
		}
		$out = $critical ? '<!-- AssetLib Head (Running in critical CSS detection mode) -->' : '<!-- AssetLib Head -->';
		$out .= "\n".$this->getOutput();
		return $out;
	}
	
	// render HTML that goes in <body>, preferrable just before the closing tag
	public function renderBody(){
		$this->resetOutput();
		$scriptDict = [];
		
		foreach($this->scripts as $script){
			// is script before DOM? then it doesn't go in the <body>!
			if($script->priority !== self::PRIORITY_POSTDOM_REMOTE && $script->priority !== self::PRIORITY_POSTDOM_LOCAL){
				continue;
			}
			$group = $script->priority;
			if(stristr($script->priority, 'dom') !== FALSE){
				$group .= $script->ajax ? '_ajax' : '_link';
			}
			// add to the queue in the correct priority bucket
			if(!isset($scriptDict[$group])) $scriptDict[$group] = [];
			$scriptDict[$group][] = $script->content;
		}
		// now assemble based on domOrder
		foreach($this->domOrder as $priority){
			foreach($scriptDict as $group=>$queue){
				if($group === $priority){
					$this->renderQueue($queue, $priority, 'script');
				}
			}
		}
		// init ScriptLoader
		$this->output['script'] .= 'ScriptLoader.init();'."\n";
		
		$out = '<!-- AssetLib Body -->'."\n";
		return $out.$this->getOutput();
	}
	
	// call this to run the 3rd party minify code
	public function runMinify(){
		$app = new MinifyApp($this->config->minifyConfigPath);
		$app->runServer();
	}
	
	protected function createStyle(array $data, bool $useConfigPath=TRUE){
		$this->addAsset(new Style($data), $this->styles, $useConfigPath ? $this->config->cssPath : '');
	}
	
	protected function createScript(array $data, bool $useConfigPath=TRUE){
		$this->addAsset(new Script($data), $this->scripts, $useConfigPath ? $this->config->jsPath : '');
	}
	
	// parse data attributes and place it in the correct position
	protected function addAsset($asset, &$list, $path){
		// should we add the path?
		if($asset->priority !== self::PRIORITY_RAW){
			// content is a filepath
			if($asset->priority === self::PRIORITY_INLINE || stristr($asset->priority, 'local')){
				// file is local
				$asset->content = $path . $asset->content;
			}
		}
		// ensure we have an ID
		if(empty($asset->id)){
			$asset->id = 'asset'.(count($this->scripts) + count($this->styles));
		}
		// find index to insert
		$index = NULL;
		if($asset->before){
			$i = 0;
			foreach($list as $a){
				if($a->id === $asset->before){
					$index = $i;
					break;
				}
				$i++;
			}
		}else if($asset->after){
			$i = 0;
			foreach($list as $a){
				if($a->id === $asset->after){
					$index = $i + 1;
					break;
				}
				$i++;
			}
		}else{
			// before/after not given. put it next to anything with the same priority if possible
			for($i = count($list) - 1; $i >= 0; $i--){
				if($list[$i]->priority === $asset->priority){
					$index = $i + 1;
					break;
				}
			}
		}
		if($index !== NULL && $index < count($list)){
			array_splice($list, $index, 0, [$asset]);
		}else{
			$list[] = $asset;
		}
	}
	
	protected function getMinifyUrl($files){
		$url = $this->config->minifyUri;
		$url .= stristr($url, '?') ? '&' : '?';
		return site_url($url.'f='.implode(',', $files));
	}
	
	// render a group of styles or scripts with the same priority
	protected function renderQueue($queue, $priority, $type){
		if(empty($queue)) return '';
		$out = "\n<!-- AssetLib: $priority $type -->\n";
		switch($priority){
			case self::PRIORITY_RAW:
				$this->renderRaw($queue, $type);
				break;
				
			case self::PRIORITY_INLINE:
				$this->renderInline($queue, $type);
				break;
				
			case self::PRIORITY_PREDOM_REMOTE.'_link':
				$out .= $this->renderGroup([
					'queue'=>$queue,
					'type'=>$type,
					'minify'=>FALSE, 
					'ajax'=>FALSE, 
					'priority'=>'pre_dom', 
					'path'=>'',
					'useCacheKey'=>FALSE,
				]);
				break;
				
			case self::PRIORITY_PREDOM_REMOTE.'_ajax':
				$out .= $this->renderGroup([
					'queue'=>$queue,
					'type'=>$type,
					'minify'=>FALSE, 
					'ajax'=>TRUE, 
					'priority'=>'pre_dom', 
					'path'=>'',
					'useCacheKey'=>FALSE,
				]);
				break;
				
			case self::PRIORITY_PREDOM_LOCAL.'_link':
				$out .= $this->renderGroup([
					'queue'=>$queue,
					'type'=>$type,
					'minify'=>TRUE, 
					'ajax'=>FALSE, 
					'priority'=>'pre_dom', 
					'path'=>base_url().'/',
					'useCacheKey'=>TRUE,
				]);
				break;
			
			case self::PRIORITY_PREDOM_LOCAL.'_ajax':
				$out .= $this->renderGroup([
					'queue'=>$queue,
					'type'=>$type,
					'minify'=>TRUE, 
					'ajax'=>TRUE, 
					'priority'=>'pre_dom', 
					'path'=>base_url().'/',
					'useCacheKey'=>TRUE,
				]);
				break;
				
			case self::PRIORITY_POSTDOM_REMOTE.'_ajax':
				$out .= $this->renderGroup([
					'queue'=>$queue,
					'type'=>$type,
					'minify'=>FALSE, 
					'ajax'=>TRUE, 
					'priority'=>'post_dom', 
					'path'=>base_url().'/',
					'useCacheKey'=>FALSE,
				]);
				break;
				
			case self::PRIORITY_POSTDOM_LOCAL.'_ajax':
				$out .= $this->renderGroup([
					'queue'=>$queue,
					'type'=>$type,
					'minify'=>TRUE, 
					'ajax'=>TRUE, 
					'priority'=>'post_dom', 
					'path'=>base_url().'/',
					'useCacheKey'=>TRUE,
				]);
				break;
			
			default:
				throw new \Exception('Unknown priority: '.$priority);
		}
		return $out;
	}
	
	// render RAW CSS/JS
	protected function renderRaw($queue, $type){
		$out = '';
		if(empty($queue)) return;
		$out .= implode('', $queue);
		$this->output[$type] .= $out."\n";
	}
	
	// render a group of files as inline CSS/JS by reading the files
	protected function renderInline($queue, $type){
		$out = '';
		if(empty($queue)) return;
		foreach($queue as $file){
			if(!file_exists($file)){
				$out .= '/* 
				
WARNING: File does not exist '.$file.' 
				
				*/';
			}else{
				$out .= file_get_contents($file);
			}
		}
		$this->output[$type] .= $out."\n";
	}
	
	// render non-inlined files
	protected function renderGroup($options){
		if(empty($options['queue'])) return ''; // nothing to process!
		// set some defaults, just in case
		$options = array_merge([
			'minify'=>FALSE,
			'path'=>'',
			'priority'=>'pre_dom',
			'ajax'=>FALSE,
			'useCacheKey'=>FALSE,
		], $options);
		
		$styleLoaderData = []; // styleloader data to be JSONed, for styles only 
		$scriptLoaderData = []; // scriptloader data to be JSONed, for scripts only
		
		if($options['minify'] && $this->config->minify){
			// concat and minify the group
			$url = $this->getMinifyUrl($options['queue']);
			if($options['useCacheKey']){
				$url .= stristr($url, '?') ? '&' : '?';
				$url .= 'v='.$this->config->cacheKey;
			}
			if($options['type'] === 'style'){
				$link = '<link rel="stylesheet" type="text/css" href="'.$url.'">'."\n";
				if($options['ajax']){
					$styleLoaderData[] = ['url'=>$url, 'priority'=>$options['priority'], 'cache'=>TRUE];
					$this->output['noscript'] .= $link; // provide the fallback
				}else{
					$this->output['link'].= $link;
				}
			}else{
				if($options['ajax']){
					$scriptLoaderData[] = $url;
				}else{
					$this->output['link'] .= '<script src="'.$url.'"></script>'."\n";
				}
			}
		}else{
			// loop
			$i=0;
			foreach($options['queue'] as $file){
				$url = $options['path'].$file;
				if($options['useCacheKey']){
					$url .= stristr($url, '?') ? '&' : '?';
					$url .= 'v='.$this->config->cacheKey;
				}
				if($options['type'] === 'style'){
					$link = '<link rel="stylesheet" type="text/css" href="'.$url.'">'."\n";
					if($options['ajax']){
						$styleLoaderData[] = ['url'=>$url, 'priority'=>$options['priority'], 'cache'=>TRUE];
						$this->output['noscript'] .= $link; // provide the fallback
					}else{
						$this->output['link'].= $link;
					}
				}else{
					if($options['ajax']){
						$scriptLoaderData[] = $url;
					}else{
						$this->output['link'] .= '<script src="'.$url.'"></script>'."\n";
					}
				}
			}
		}
		// add JS commands if we got any
		if(!empty($styleLoaderData)) 	$this->output['script'] .= 'StyleLoader.add('.json_encode($styleLoaderData).');'."\n";
		if(!empty($scriptLoaderData)) 	$this->output['script'] .= 'ScriptLoader.add('.json_encode($scriptLoaderData).');'."\n";
	}
	
	protected function resetOutput(){
		// output will be sent in this order:
		$this->output = [
			'style'=>'', // code that belongs in <style>
			'link'=>'', // <style>s and <script>s with src attribute
			'script'=>'', // code that belongs in <script> (local)
			'noscript'=>'', // code that belongs in <noscript>
		];
	}
	
	// wrap output with <style>, <script>, etc.
	protected function getOutput(){
		$out = '';
		foreach($this->output as $key=>$str){
			if(empty($str)) continue;
			switch($key){
				case 'style':
					$out .= '<style type="text/css">'.$str.'</style>'."\n"; break;
				case 'script':
					$out .= '<script>'.$str.'</script>'."\n"; break;
				case 'noscript':
					$out .= '<noscript>'.$str.'</noscript>'."\n"; break;
				case 'link':
				default:
					$out .= $str;
			}
		}
		return $out;
	}
}

class Style{
	public $id; // for use with before and after. optional.
	public $content; // the raw CSS text or filename. required.
	public $critical = FALSE; // set to true when adding a css file that contains the critical styles. It will be automatically inlined.
	public $priority = AssetLib::PRIORITY_POSTDOM_REMOTE; // see constants
	public $ajax = TRUE; // only possible with POSTDOM priorities
	public $cache = TRUE; // saves AJAX responses in SessionStorage JS. only possible with POSTDOM priorities
	public $before; // id of style to place before (for load order)
	public $after; // id of style to place after (for load order)
	
	
	public function __construct(array $attr=[]){
		foreach($attr as $key=>$val){
			if(property_exists($this, $key)) $this->{$key} = $val;
		}
		if(empty($this->content)){
			throw new \Exception('Style must have content defined');
		}
	}
}

class Script{
	public $id;
	public $content;
	public $ajax = TRUE; // only possible with POSTDOM priorities
	public $priority = AssetLib::PRIORITY_POSTDOM_LOCAL; // see constants
	public $before;
	public $after;
	
	public function __construct(array $attr=[]){
		foreach($attr as $key=>$val){
			if(property_exists($this, $key)) $this->{$key} = $val;
		}
		if(empty($this->content)){
			throw new \Exception('Script must have content defined');
		}
	}
}

class Font{
	public $priority = AssetLib::PRIORITY_PREDOM_LOCAL; // see constants
	public $path = '';
	public $family;
	public $name;
	public $weight = 'normal';
	public $style = 'normal';
	public $type = 'css';
	public $fallbacks = [];
	public $cache = TRUE;
	
	public function __construct(array $attr=[]){
		foreach($attr as $key=>$val){
			if(property_exists($this, $key)) $this->{$key} = $val;
		}
		// parse CSV
		if(!is_array($this->fallbacks)){
			$this->fallbacks = explode(',', $this->fallbacks);
		}
		// we can only cache CSS fonts
		if($this->type === 'css'){
			$this->cache = FALSE;
		}
	}
	
	public function filename($ext){
		return $this->path.$this->name.'-'.$this->weight.'-'.$this->style.'.'.$ext;
	}
	
	public function fontFace(){
		// get CSS type for the font
		$format = AssetLib::getFontFormat($this->type);
		
		// build src
		// first is "local", in case the font is somehow miraculously on the person's device
		$src = ['local("'.$this->family.'")'];
		// if we're not using base64 encoded WOFF2, then use the main font file specified
		if($this->type !== 'css'){
			$src[] = 'url("'.base_url($this->filename($this->type)).'") format("'.$format.'")';
		}
		// now process fallbacks
		foreach($this->fallbacks as $ext){
			if(empty($ext)) continue;
			$format = AssetLib::getFontFormat($ext);
			$src[] = 'url("'.base_url($this->filename($ext)).'") format("'.$format.'")';
		}
		$src = implode(', ', $src);
		return <<<CSS
@font-face{ font-family: "{$this->family}"; font-style: {$this->style}; font-weight: {$this->weight}; src: $src; }
CSS;
	}
}
