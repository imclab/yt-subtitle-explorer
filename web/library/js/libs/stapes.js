// Stapes.js : http://hay.github.com/stapes
(function(){"use strict";var a="0.5.2",b=1,c={bind:function(a,b){if(c.isObject(a)){c.each(a,function(d,e){c.typeOf(d)==="function"&&(a[e]=c.bind(d,b||a))});return a}return Function.prototype.bind?a.bind(b):function(){return a.apply(b,arguments)}},clone:function(a){if(c.isArray(a))return a.slice();if(c.isObject(a)){var b={};c.each(a,function(a,c){b[c]=a});return b}return a},create:function(a){var b;if(typeof Object.create=="function")b=Object.create(a);else{var c=function(){};c.prototype=a,b=new c}return b},each:function(a,b,d){if(c.isArray(a))if(Array.prototype.forEach)a.forEach(b,d);else for(var e=0,f=a.length;e<f;e++)b.call(d,a[e],e);else for(var g in a)b.call(d,a[g],g)},filter:function(a,b,d){var e=[];if(c.isArray(a)&&Array.prototype.filter)return a.filter(b,d);c.each(a,function(a){b.call(d,a)&&e.push(a)});return e},isArray:function(a){return c.typeOf(a)==="array"},isObject:function(a){return c.typeOf(a)==="object"},keys:function(a){return c.map(a,function(a,b){return b})},makeUuid:function(){return"xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g,function(a){var b=Math.random()*16|0,c=a=="x"?b:b&3|8;return c.toString(16)})},map:function(a,b,d){var e=[];if(c.isArray(a)&&Array.prototype.map)return a.map(b,d);c.each(a,function(a,c){e.push(b.call(d,a,c))});return e},size:function(a){return c.isArray(a)?a.length:c.keys(a).length},toArray:function(a){return c.isObject(a)?c.values(a):Array.prototype.slice.call(a,0)},typeOf:function(a){return Object.prototype.toString.call(a).replace(/\[object |\]/g,"").toLowerCase()},values:function(a){return c.map(a,function(a,b){return a})}},d={attributes:{},eventHandlers:{"-1":{}},guid:-1,addEvent:function(a){d.eventHandlers[a.guid][a.type]||(d.eventHandlers[a.guid][a.type]=[]),d.eventHandlers[a.guid][a.type].push({guid:a.guid,handler:a.handler,scope:a.scope,type:a.type})},addEventHandler:function(a,b,e){var f={},g;typeof a=="string"?(g=e||!1,f[a]=b):(g=b||!1,f=a),c.each(f,function(a,b){var e=b.split(" ");c.each(e,function(b){d.addEvent.call(this,{guid:this._guid||this._.guid,handler:a,scope:g,type:b})},this)},this)},addGuid:function(a,c){if(!a._guid||!!c)a._guid=b++,d.attributes[a._guid]={},d.eventHandlers[a._guid]={}},attr:function(a){return d.attributes[a]},createModule:function(a){var b=c.create(a);d.addGuid(b,!0),f.mixinEvents(b);return b},emitEvents:function(a,b,e,f){e=e||!1,f=f||this._guid,c.each(d.eventHandlers[f][a],function(a){var c=a.scope?a.scope:this;e&&(a.type=e),a.scope=c,a.handler.call(a.scope,b,a)},this)},removeAttribute:function(a){var b=this.has(a),c=d.attr(this._guid)[a];!b||(delete d.attr(this._guid)[a],this.emit("change",a),this.emit("change:"+a),this.emit("remove",a),this.emit("remove:"+a))},removeEventHandler:function(a,b){var e=d.eventHandlers[this._guid];a&&b?c.each(e[a],function(c,d){c.handler===b&&e[a].splice(d--,1)},this):a?delete e[a]:d.eventHandlers[this._guid]={}},setAttribute:function(a,b){var c=this.has(a),e=d.attr(this._guid)[a];if(b!==e){d.attr(this._guid)[a]=b,this.emit("change",a),this.emit("change:"+a,b);var f={key:a,newValue:b,oldValue:e||null};this.emit("mutate",f),this.emit("mutate:"+a,f);var g=c?"update":"create";this.emit(g,a),this.emit(g+":"+a,b)}},updateAttribute:function(a,b){var e=this.get(a),f=b(c.clone(e));d.setAttribute.call(this,a,f)}},e={emit:function(a,b){b=typeof b=="undefined"?null:b,c.each(a.split(" "),function(a){d.eventHandlers[-1].all&&d.emitEvents.call(this,"all",b,a,-1),d.eventHandlers[-1][a]&&d.emitEvents.call(this,a,b,a,-1),typeof this._guid=="number"&&(d.eventHandlers[this._guid].all&&d.emitEvents.call(this,"all",b,a),d.eventHandlers[this._guid][a]&&d.emitEvents.call(this,a,b))},this)},off:function(){d.removeEventHandler.apply(this,arguments)},on:function(){d.addEventHandler.apply(this,arguments)}};d.Module={create:function(){return d.createModule(this)},each:function(a,b){c.each(d.attr(this._guid),a,b||this)},extend:function(a,b){var d=b?a:this,e=b?b:a;c.each(e,function(a,b){d[b]=a});return this},filter:function(a){return c.filter(d.attr(this._guid),a)},get:function(a){if(typeof a=="string")return this.has(a)?d.attr(this._guid)[a]:null;if(typeof a=="function"){var b=this.filter(a);return b.length?b[0]:null}},getAll:function(){return c.clone(d.attr(this._guid))},getAllAsArray:function(){var a=c.map(d.attr(this._guid),function(a,b){c.isObject(a)&&(a.id=b);return a});return c.clone(a)},has:function(a){return typeof d.attr(this._guid)[a]!="undefined"},push:function(a){c.isArray(a)?c.each(a,function(a){d.setAttribute.call(this,c.makeUuid(),a)},this):d.setAttribute.call(this,c.makeUuid(),a)},remove:function(a){typeof a=="function"?this.each(function(b,c){a(b)&&d.removeAttribute.call(this,c)}):d.removeAttribute.call(this,a)},set:function(a,b){c.isObject(a)?c.each(a,function(a,b){d.setAttribute.call(this,b,a)},this):d.setAttribute.call(this,a,b)},size:function(){return c.size(d.attributes[this._guid])},update:function(a,b){typeof a=="string"?d.updateAttribute.call(this,a,b):typeof a=="function"&&this.each(function(b,c){d.updateAttribute.call(this,c,a)})}};var f={_:d,create:function(){return d.createModule(d.Module)},extend:function(a){c.each(a,function(a,b){d.Module[b]=a})},mixinEvents:function(a){a=a||{},d.addGuid(a),c.each(e,function(b,c){a[c]=b});return a},on:function(){d.addEventHandler.apply(this,arguments)},util:c,version:a};typeof exports!="undefined"?(typeof module!="undefined"&&module.exports&&(exports=module.exports=f),exports.Stapes=f):typeof define=="function"&&define.amd?define(function(){return f}):window.Stapes=f})()