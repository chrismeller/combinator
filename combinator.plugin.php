<?php

	class Combinator extends Plugin {
		
		public function filter_rewrite_rules ( $rules ) {
			
			// get the base path for the theme
			$path = Site::get_path( 'theme' );
			
			// trim off the habari path, it's all relative to habari
			$path = MultiByte::substr( $path, MultiByte::strlen( Site::get_path( 'base', true ) ) );
			
			$rule = new RewriteRule( array(
				'name' => 'combinator_display_js',
				'parse_regex' => '#^' . $path . '/combined_(?P<location>[header|footer]*).js/(?P<hash>[^/]*)$#i',
				'build_str' => $path . '/combined_{$location}.js/{$hash}',
				'handler' => 'PluginHandler',
				'action' => 'display_combined_js',
				'rule_class' => RewriteRule::RULE_PLUGIN,
				'is_active' => true,
				'priority' => 9,
				'description' => 'Display combined javascript'
			) );
			
			$rules[] = $rule;
			
			$rule = new RewriteRule( array(
				'name' => 'combinator_display_css',
				'parse_regex' => '#^' . $path . '/combined_(?P<location>[header|footer]*).css/(?P<hash>[^/]*)$#i',
				'build_str' => $path . '/combined_{$location}.css/{$hash}',
				'handler' => 'PluginHandler',
				'action' => 'display_combined_css',
				'rule_class' => RewriteRule::RULE_PLUGIN,
				'is_active' => true,
				'description' => 'Display combined CSS'
			) );
			
			$rules[] = $rule;
			
			return $rules;
			
		}
		
		public function configure ( ) {
			
			$ui = new FormUI( 'combinator' );
			
			$ui->append( 'checkbox', 'combinator_js', 'option:combinator_js', _t('Combine Javascript') );
			$ui->append( 'checkbox', 'combinator_css', 'option:combinator_css', _t('Combine CSS') );
			
			$ui->append( 'submit', 'save', _t('Save') );
			
			$ui->set_option( 'success_message', _t('Options saved.') );
			
			return $ui->out();
			
		}
		
		public function action_plugin_act_display_combined_js ( $handler ) {
			
			Header('Content-Type: text/javascript');
			
			$location = $handler->handler_vars['location'];
			$hash = $handler->handler_vars['hash'];
			
			$cache_key = 'combinator:' . '_js_' . $location . '_' . $hash;
			
			if ( Cache::has( $cache_key ) ) {
				echo Cache::get( $cache_key );
			}
			
			if ( $location == 'header' ) {
				$jses = Stack::get_sorted_stack( 'template_header_javascript' );
			}
			else if ( $location == 'footer' ) {
				$jses = Stack::get_sorted_stack( 'template_footer_javascript' );
			}
			else {
				return false;
			}
			
			// if there's nothing in the stack, don't do anything
			if ( empty( $jses ) ) {
				return;
			}
			
			$combined = $this->combine_js( $jses );
			
			Cache::set( $cache_key, $combined, HabariDateTime::DAY );
			
			echo $combined;
			
		}
		
		public function action_plugin_act_display_combined_css ( $handler ) {
			
			Header('Content-Type:text/css');
			
			$location = $handler->handler_vars['location'];
			$hash = $handler->handler_vars['hash'];
			
			$cache_key = 'combinator:' . '_css_' . $location . '_' . $hash;
			
			if ( Cache::has( $cache_key ) ) {
				echo Cache::get( $cache_key );
			}
			
			if ( $location == 'header' ) {
				$csses = Stack::get_sorted_stack( 'template_stylesheet' );
			}
			else if ( $location == 'footer' ) {
				$csses = Stack::get_sorted_stack( 'template_footer_stylesheet' );
			}
			else {
				return false;
			}
			
			// if there's nothing in the stack, don't do anything
			if ( empty( $csses ) ) {
				return;
			}
			
			$combined = $this->combine_css( $csses );
			
			Cache::set( $cache_key, $combined, HabariDateTime::DAY );
			
			echo $combined;
			
		}
		
		public function action_template_header ( $theme ) {
			
			if ( Options::get( 'combinator_js', false ) ) {
				
				$js = Stack::get_named_stack( 'template_header_javascript' );
				$js_hash = sha1( implode( "\n", $js ) );
				$js_url = URL::get( 'combinator_display_js', array( 'location' => 'header', 'hash' => $js_hash ) );
				
				// remove the entire js stack
				Stack::remove( 'template_header_javascript' );
				
				// add our combined js
				Stack::add( 'template_header_javascript', $js_url, 'combined' );
				
			}
			
			if ( Options::get( 'combinator_css', false ) ) {
						
				$css = Stack::get_named_stack( 'template_stylesheet' );
				$css = implode( "\n", array_keys( $css ) );
				$css_hash = sha1( $css );
				$css_url = URL::get( 'combinator_display_css', array( 'location' => 'header', 'hash' => $css_hash ) );
			
				// and the css stack
				Stack::remove( 'template_stylesheet' );

				// and css
				Stack::add( 'template_stylesheet', array( $css_url, 'screen' ), 'combined' );
				
			}
			
		}
		
		private function combine_js ( $jses ) {
			
			$combined = array();
			foreach ( $jses as $js ) {
				
				// if it looks like a URL
				if ( ( MultiByte::strpos( $js, 'http://' ) === 0 || MultiByte::strpos( $js, 'https://' ) === 0 ) && MultiByte::strpos( $js, "\n" ) === false ) {
					$combined[] = file_get_contents( $js );
				}
				else {
					$combined[] = $js;
				}
				
			}
			
			$js = implode( "\n", $combined );
			
			return $js;
			
		}
		
		private function combine_css ( $csses ) {
			
			$combined = array();
			foreach ( $csses as $css ) {
				
				$content = $css[0];
				$media = $css[1];
				
				if ( ( MultiByte::strpos( $content, 'http://' ) === 0 || MultiByte::strpos( $content, 'https://' ) === 0 ) && MultiByte::strpos( $content, "\n" ) === false ) {
					$combined[] = file_get_contents( $content );
				}
				else {
					$combined[] = $content;
				}
				
			}
			
			$css = implode( "\n", $combined );
			
			return $css;
			
		}
		
	}

?>