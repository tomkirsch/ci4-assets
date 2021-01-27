<?php namespace Tomkirsch\Assets\Controllers;
use CodeIgniter\Controller;

class Min extends Controller{
	
	public function index(){
		$this->assets = service('assets');
		$this->assets->runMinify();
		// minify will output its own headers, so make sure CI doesn't do anything weird by exiting the script immediately
		die();
	}
}