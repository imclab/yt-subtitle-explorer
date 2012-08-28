define(
	[
		'jquery',
		'stapes'
	],
	function(
		$,
		Stapes
	){

		'use strict';

        var MyModule = Stapes.create().extend({

            // default options
            options: {

            	el: ''

            },

            init: function( opts ){

                var self = this;

                // prevent double initializations
                if (self.inited) return self;
                self.inited = true;

                // extend default options
                self.extend( self.options, opts );

                self.initEvents();

                $(function(){
                	var el = $(self.options.el);
	                self.set({
	                	el: el,
	                	state: el.data('val') 
	                });
	            });

                self.emit('ready'); // if applicable
                return self;
            },

            initEvents: function(){

            	var self = this;

            	self.on({
            		'create:el': function(el){

            			el.on('click', '.btn', function(e){

            				e.preventDefault();

            				var btn = $(this);

            				self.set('state', btn.data('val'));
            			});
            		},

            		'change:state': function( state ){

            			var el = self.get('el');

            			if (el){

            				el.find('.btn').each(function(){

            					var btn = $(this);

            					btn.toggleClass('active', btn.data('val') === state);
            				});
            			}
            		}
            	});
            }

        });

        // Factory function to return new stapes instances
        return function(){

            // Create a "sub-module"
            return MyModule.create();
        };
	}
);