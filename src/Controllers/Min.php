<?php

namespace Tomkirsch\Assets\Controllers;

use CodeIgniter\Controller;

class Min extends Controller
{
	protected $assets;
	public function index()
	{
		$this->assets = service('assets');
		$this->assets->runMinify(); // note that this will exit the script and ob_end_clean()
	}
}
