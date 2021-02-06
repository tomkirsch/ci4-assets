<?php
	/*
// unminified JS:
var StyleLoader = {
	init: function(cache, debug){
		var that = this;
		
		this.cache = cache;
		this.debug = debug && console ? true : false;
		this.styleList = [];
		this.woff2 = null;
		this.maxItems = 100;
		var nua = navigator.userAgent;
		this.noSupport = !window.addEventListener // IE8 and below
						|| (nua.match(/(Android (2|3|4.0|4.1|4.2|4.3))|(Opera (Mini|Mobi))/) && !nua.match(/Chrome/))
						|| !window.XMLHttpRequest
		;
		try{
			// localStorage is way too permanent. Use sessionStorage instead.
			this.storage = sessionStorage;
		}catch(e){
			if(this.debug){
				console.log('StyleLoader: sessionStorage not possible');
				console.log(e);
			}
			this.noSupport = true;
		}
		
		// Mozilla, Opera, Webkit 
		if( document . addEventListener ) {
			document . addEventListener( "DOMContentLoaded", function () {
				document . removeEventListener( "DOMContentLoaded", arguments . callee, false );
				that._domReady();
			}, false );
			// If IE event model is used
		} else if ( document . attachEvent ) {
			// ensure firing before onload
			document . attachEvent( "onreadystatechange", function () {
				if ( document . readyState === "complete" ) {
					document . detachEvent( "onreadystatechange", arguments . callee );
					that._domReady();
				}
			} );
		}
	},
	add: function(dataArray){
		for(var i=0; i<dataArray.length; i++){
			var data = dataArray[i];
			if(!data['url']){
				throw new Error('StyleLoader: URL not supplied');
			}
			if(!data['priority']){
				throw new Error('StyleLoader: priority not supplied');
			}
			if(data['priority'] !== 'pre_dom' && data['priority'] !== 'post_dom'){
				throw new Error('StyleLoader: priority must be pre_dom or post_dom. found: "'+data['priority']+'"');
			}
			data.processed = false;
			this.styleList.push(data);
		}
		this._loadStyles('pre_dom'); // start loading pre_dom immediately
	},
	addFonts: function(dataArray){
		var queue = [];
		for(var i=0; i<dataArray.length; i++){
			var data = dataArray[i];
			if(!this.noSupport && data.type === 'css' && this._woff2Supported()){
				data.cache = true;
				queue.push(data);
			}else{
				this._injectCss(data.fontface); // use @font-face, which will let browser choose to download the correct fallback font file
			}
		}
		this.add(queue);
	},
	_loadStyles: function(priority){
		for(var i=0; i<this.styleList.length; i++){
			if(this.styleList[i].priority !== priority || this.styleList[i].processed){
				continue;
			}
			this._loadStyle(this.styleList[i]);
			this.styleList[i].processed = true;
		}
	},
	_loadStyle: function(data){
		var that = this;
		
		// NOTE: the data.url should have the cache query already in place
		
		// old browser? use regular <link> elements and get out of here
		if(this.noSupport){
			var stylesheet = document.createElement('link');
			stylesheet.href = data.url;
			stylesheet.rel = 'stylesheet';
			stylesheet.type = 'text/css';
			document.getElementsByTagName('head')[0].appendChild(stylesheet);
			return;
		}
		
		// get fixed-length mysql-like date
		var dateCode = this._getDateCode();
		var styleKey = 'sl-' + this._hash(data.url + this.cache); // generate hash from URL and cache version
		var cachedData = this.storage[styleKey];
		if(data.cache && cachedData){
			if(this.debug){
				console.log('StyleLoader: Got cached style ' + styleKey);
			}
			var parsedData = cachedData.substring(dateCode.length); // remove old date from the data
			this._setItem(styleKey, dateCode + parsedData); // update data with new date
			this._injectCss(parsedData); // inject date-less data
			return;
		}
		
		// still here? AJAX it
		var request = new XMLHttpRequest();
        request.open('GET', data.url);
		request.onload = function() {
			if (request.status >= 200 && request.status < 400) {
				that._injectCss(request.responseText);
				if(data.cache){
					that._setItem(styleKey, dateCode + request.responseText); // store it
					if(that.debug){
						console.log(that.storage[styleKey] ? 'StyleLoader: Stored ' + styleKey : 'StyleLoader: Storage failed');
					}
				}
			}
		}
		request.send();
	},
	_domReady: function(){
		this._loadStyles('post_dom');
	},
	_woff2Supported: function(){
		if(this.woff2 !== null){
			return this.woff2;
		}
		// Source: https://github.com/filamentgroup/woff2-feature-test
        if( !( "FontFace" in window ) ) {
			return false;
		}
		var f = new FontFace('t', 'url( "data:font/woff2;base64,d09GMgABAAAAAADwAAoAAAAAAiQAAACoAAEAAAAAAAAAAAAAAAAAAAAAAAAAAAAABmAALAogOAE2AiQDBgsGAAQgBSAHIBuDAciO1EZ3I/mL5/+5/rfPnTt9/9Qa8H4cUUZxaRbh36LiKJoVh61XGzw6ufkpoeZBW4KphwFYIJGHB4LAY4hby++gW+6N1EN94I49v86yCpUdYgqeZrOWN34CMQg2tAmthdli0eePIwAKNIIRS4AGZFzdX9lbBUAQlm//f262/61o8PlYO/D1/X4FrWFFgdCQD9DpGJSxmFyjOAGUU4P0qigcNb82GAAA" ) format( "woff2" )', {});
		f.load()['catch'](function() {});
		this.woff2 = (f.status == 'loading' || f.status == 'loaded');
		return this.woff2;
	},
	_injectCss: function(css){
		// should work IE 7-9
		var head = document.head || document.getElementsByTagName('head')[0],
			style = document.createElement('style');
		style.type = 'text/css';
		if (style.styleSheet){
		  // This is required for IE8 and below.
		  style.styleSheet.cssText = css;
		} else {
		  style.appendChild(document.createTextNode(css));
		}
		head.appendChild(style);
	},
	_setItem: function(key, val){
		// max items reached?
		if(this.storage.length > this.maxItems){
			this.clean(Math.floor(this.maxItems/4)); // clean the oldest quarter
		}
		// set item, if fail we assume its full and delete stuff
		try{
			this.storage.setItem(key, val);
		}catch(e){
			// since we only know how many items we have, and not the length of the acutal data, this error is certainly possbile
			// omitting code to check error numbers since it's messy... let's just assume it's full
			if(this.debug){
				console.log('StyleLoader: Cannot setItem, possibly full. Running cleanup.');
			}
			this.clean(Math.floor(this.maxItems/2)); // clean the oldest half
			try{
				this.storage.setItem(key, val);
			}catch(e){
				console.log('StyleLoader: Cannot set storage item after cleaning.');
			}
		}
	},
	clean: function(maxItems){
		var dateCode = this._getDateCode();
		var arr = [];
		for(var i = 0; i < this.storage.length; i++){
			// ensure its a styleloader key!
			var key = this.storage.key(i);
			if(key.indexOf('sl-') !== 0){
				continue;
			}
			var data = this.storage[key];
			// parse the date
			var cachedDate = data.substring(0, dateCode.length);
			
			// Split timestamp into [ Y, M, D, h, m, s ]
			var t = cachedDate.split(/[- :]/);
			// Apply each element to the Date function
			var d = new Date(Date.UTC(t[0], t[1]-1, t[2], t[3], t[4], t[5]));
			// store it in our temp array using unix epoch			
			arr.push({time: d.getTime(), key: key});
		}
		if(!arr.length && this.debug){
			console.log('StyleLoader: Cannot clean storage, no keys found!');
			return;
		}
		arr.sort(function(a,b){
			return a.time - b.time;
		});
		var len = arr.length;
		if(maxItems && maxItems < len){
			len = maxItems;
		}
		for(i=0; i<len; i++){
			this.storage.removeItem(arr[i].key);
		}
		if(this.debug){
			console.log('StyleLoader: Cleaned '+ len + ' items');
		}
	},
	_getDateCode: function(){
		// get a mysql-like date code that is always the same length
		var date = new Date();
		return date.getUTCFullYear() + '-' +
			('00' + (date.getUTCMonth()+1)).slice(-2) + '-' +
			('00' + date.getUTCDate()).slice(-2) + ' ' + 
			('00' + date.getUTCHours()).slice(-2) + ':' + 
			('00' + date.getUTCMinutes()).slice(-2) + ':' + 
			('00' + date.getUTCSeconds()).slice(-2)
		;
	},
	_hash: function(str){
		var hash = 0, i, chr;
		if (str.length === 0) return hash;
		for (i = 0; i < str.length; i++) {
			chr   = str.charCodeAt(i);
			hash  = ((hash << 5) - hash) + chr;
			hash |= 0; // Convert to 32bit integer
		}
		return hash;
	}
};
*/ ?>var StyleLoader={init:function(t,e){var o=this;this.cache=t,this.debug=!(!e||!console),this.styleList=[],this.woff2=null,this.maxItems=100;var s=navigator.userAgent;this.noSupport=!window.addEventListener||s.match(/(Android (2|3|4.0|4.1|4.2|4.3))|(Opera (Mini|Mobi))/)&&!s.match(/Chrome/)||!window.XMLHttpRequest;try{this.storage=sessionStorage}catch(t){this.debug&&(console.log("StyleLoader: sessionStorage not possible"),console.log(t)),this.noSupport=!0}document.addEventListener?document.addEventListener("DOMContentLoaded",function(){document.removeEventListener("DOMContentLoaded",arguments.callee,!1),o._domReady()},!1):document.attachEvent&&document.attachEvent("onreadystatechange",function(){"complete"===document.readyState&&(document.detachEvent("onreadystatechange",arguments.callee),o._domReady())})},add:function(t){for(var e=0;e<t.length;e++){var o=t[e];if(!o.url)throw new Error("StyleLoader: URL not supplied");if(!o.priority)throw new Error("StyleLoader: priority not supplied");if("pre_dom"!==o.priority&&"post_dom"!==o.priority)throw new Error('StyleLoader: priority must be pre_dom or post_dom. found: "'+o.priority+'"');o.processed=!1,this.styleList.push(o)}this._loadStyles("pre_dom")},addFonts:function(t){for(var e=[],o=0;o<t.length;o++){var s=t[o];!this.noSupport&&"css"===s.type&&this._woff2Supported()?(s.cache=!0,e.push(s)):this._injectCss(s.fontface)}this.add(e)},_loadStyles:function(t){for(var e=0;e<this.styleList.length;e++)this.styleList[e].priority!==t||this.styleList[e].processed||(this._loadStyle(this.styleList[e]),this.styleList[e].processed=!0)},_loadStyle:function(t){var e=this;if(this.noSupport){var o=document.createElement("link");return o.href=t.url,o.rel="stylesheet",o.type="text/css",void document.getElementsByTagName("head")[0].appendChild(o)}var s=this._getDateCode(),n="sl-"+this._hash(t.url+this.cache),i=this.storage[n];if(t.cache&&i){this.debug&&console.log("StyleLoader: Got cached style "+n);var a=i.substring(s.length);return this._setItem(n,s+a),void this._injectCss(a)}var r=new XMLHttpRequest;r.open("GET",t.url),r.onload=function(){r.status>=200&&r.status<400&&(e._injectCss(r.responseText),t.cache&&(e._setItem(n,s+r.responseText),e.debug&&console.log(e.storage[n]?"StyleLoader: Stored as "+n:"StyleLoader: Storage failed")))},r.send()},_domReady:function(){this._loadStyles("post_dom")},_woff2Supported:function(){if(null!==this.woff2)return this.woff2;if(!("FontFace"in window))return!1;var t=new FontFace("t",'url( "data:font/woff2;base64,d09GMgABAAAAAADwAAoAAAAAAiQAAACoAAEAAAAAAAAAAAAAAAAAAAAAAAAAAAAABmAALAogOAE2AiQDBgsGAAQgBSAHIBuDAciO1EZ3I/mL5/+5/rfPnTt9/9Qa8H4cUUZxaRbh36LiKJoVh61XGzw6ufkpoeZBW4KphwFYIJGHB4LAY4hby++gW+6N1EN94I49v86yCpUdYgqeZrOWN34CMQg2tAmthdli0eePIwAKNIIRS4AGZFzdX9lbBUAQlm//f262/61o8PlYO/D1/X4FrWFFgdCQD9DpGJSxmFyjOAGUU4P0qigcNb82GAAA" ) format( "woff2" )',{});return t.load().catch(function(){}),this.woff2="loading"==t.status||"loaded"==t.status,this.woff2},_injectCss:function(t){var e=document.head||document.getElementsByTagName("head")[0],o=document.createElement("style");o.type="text/css",o.styleSheet?o.styleSheet.cssText=t:o.appendChild(document.createTextNode(t)),e.appendChild(o)},_setItem:function(t,e){this.storage.length>this.maxItems&&this.clean(Math.floor(this.maxItems/4));try{this.storage.setItem(t,e)}catch(o){this.debug&&console.log("StyleLoader: Cannot setItem, possibly full. Running cleanup."),this.clean(Math.floor(this.maxItems/2));try{this.storage.setItem(t,e)}catch(t){console.log("StyleLoader: Cannot set storage item after cleaning.")}}},clean:function(t){for(var e=this._getDateCode(),o=[],s=0;s<this.storage.length;s++){var n=this.storage.key(s);if(0===n.indexOf("sl-")){var i=this.storage[n].substring(0,e.length).split(/[- :]/),a=new Date(Date.UTC(i[0],i[1]-1,i[2],i[3],i[4],i[5]));o.push({time:a.getTime(),key:n})}}if(o.length||!this.debug){o.sort(function(t,e){return t.time-e.time});var r=o.length;for(t&&t<r&&(r=t),s=0;s<r;s++)this.storage.removeItem(o[s].key);this.debug&&console.log("StyleLoader: Cleaned "+r+" items")}else console.log("StyleLoader: Cannot clean storage, no keys found!")},_getDateCode:function(){var t=new Date;return t.getUTCFullYear()+"-"+("00"+(t.getUTCMonth()+1)).slice(-2)+"-"+("00"+t.getUTCDate()).slice(-2)+" "+("00"+t.getUTCHours()).slice(-2)+":"+("00"+t.getUTCMinutes()).slice(-2)+":"+("00"+t.getUTCSeconds()).slice(-2)},_hash:function(t){var e,o=0;if(0===t.length)return o;for(e=0;e<t.length;e++)o=(o<<5)-o+t.charCodeAt(e),o|=0;return o}};