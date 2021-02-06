<?php
/*
var ScriptLoader = {
	jsFiles: [],
	index: 0,
	add: function(file){
		if(Array.isArray(file)){
			this.jsFiles = this.jsFiles.concat(file);
		}else{
			this.jsFiles.push(file);
		}
	},
	init: function(){
		var that = this;
		if (window.addEventListener){
			window.addEventListener("load", function(){ that.onLoad(); }, false);
		}else if (window.attachEvent){
			window.attachEvent("onload", function(){ that.onLoad(); });
		}else{
			window.onload = function(){ that.onLoad(); };
		}
	},
	onLoad: function(){
		this.loadNext();
	},
	loadNext: function(){
		var that = this;
		if(this.index >= this.jsFiles.length) return;
		var file = this.jsFiles[this.index++];
		this.loadScript(file).then(function(){
			that.loadNext();
		}, function(){
			console.log('Could not load script: '+file);
		});
	},
	loadScript: function(url){
		return new Promise(function(resolve,  reject) {
			var script = document.createElement("script");
			script.onload = resolve;
			script.onerror = reject;
			script.src = url;
			document.getElementsByTagName("head")[0].appendChild(script);
		});
	}
};
*/
?>
var ScriptLoader={jsFiles:[],index:0,add:function(n){Array.isArray(n)?this.jsFiles=this.jsFiles.concat(n):this.jsFiles.push(n)},init:function(){var n=this;window.addEventListener?window.addEventListener("load",function(){n.onLoad()},!1):window.attachEvent?window.attachEvent("onload",function(){n.onLoad()}):window.onload=function(){n.onLoad()}},onLoad:function(){this.loadNext()},loadNext:function(){var n=this;if(!(this.index>=this.jsFiles.length)){var t=this.jsFiles[this.index++];this.loadScript(t).then(function(){n.loadNext()},function(){console.log("Could not load script: "+t)})}},loadScript:function(n){return new Promise(function(t,i){var o=document.createElement("script");o.onload=t,o.onerror=i,o.src=n,document.getElementsByTagName("head")[0].appendChild(o)})}};
