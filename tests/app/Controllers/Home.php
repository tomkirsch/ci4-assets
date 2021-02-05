<?php namespace App\Controllers;

class Home extends BaseController
{
	public function index(){
		$this->assets->addStyles([
			[
				'content'=>'subpage.css',
				'priority'=>$this->assets::PRIORITY_POSTDOM_LOCAL,
			],
		]);
		$this->assets->addScripts([
			[
				'content'=>'subpage.js',
				'priority'=>$this->assets::PRIORITY_POSTDOM_LOCAL,
			],
		]);
		return view('welcome_message');
	}
}
