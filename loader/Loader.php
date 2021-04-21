<?php

	namespace PDLoader;

	if( !isset( $_GET['error']) || ( isset( $_GET['error'] ) && $_GET['error'] == 1 )  ){

		ini_set('error_reporting', -1);
		ini_set('display_errors', 1);
		ini_set('html_errors', 1);	

	}else{
		// Turn off error reporting
		error_reporting(0);

		// Report runtime errors
		error_reporting(E_ERROR | E_WARNING | E_PARSE);

		// Report all errors
		error_reporting(E_ALL);

		// Same as error_reporting(E_ALL);
		ini_set("error_reporting", E_ALL);

		// Report all errors except E_NOTICE
		error_reporting(E_ALL & ~E_NOTICE);
	}
	
	class Loader{

		public function load($comp=false){

			if( $comp ){

				$comp = ucfirst($comp);

				if( !file_exists(__DIR__.'/modules/'.$comp.'/'.$comp.'.php') ){
					print_r('Component '.$comp.' not found.');
					return;
				}

				require_once(__DIR__.'/modules/'.$comp.'/'.$comp.'.php');

				$module = __NAMESPACE__ . '\\' .$comp;

				return new $module;

			}else{
				print_r('Component '.$comp.' not found.');
			}
		}

		protected function config($module=false){
			$modulec = explode('\\',get_class($this))[1];


			if( !$module ){
				require(__DIR__.'/../config.php');
				return ( isset( $config[strtolower($modulec)] ) ) ? $config[strtolower($modulec)] : false;
				
			}else{

				if( file_exists(__DIR__.'/modules/'.$modulec.'/config.php') ){
					require(__DIR__.'/modules/'.$modulec.'/config.php');
					return $config;
				}else{
					return false;
				}
			}
			
		}

		public function url(){
			return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/";
		}

		public function is_connected()
		{
		    $connected = @fsockopen("www.example.com", 80); 
		                                        //website, port  (try 80 or 443)
		    if ($connected){
		        $is_conn = true; //action when connected
		        fclose($connected);
		    }else{
		        $is_conn = false; //action in connection failure
		    }
		    return $is_conn;

		}

		
		public function appheader(){

			$conf = __DIR__.'/../config.php';
			if( file_exists( $conf ) ){

				require( $conf );

				$config_main = $config;

				foreach( $config_main as $k => $v ){

					$conf_file = __DIR__.'/modules/'.ucfirst($k).'/config.php';

					if( file_exists( $conf_file ) ){

						require($conf_file);

						if( isset($config['autoload']) && isset($config['autoload']['header']) ){

							foreach( $config['autoload']['header'] as $l){
								$t = '';
								if( isset($l['custom']) ){
									$t.=$l['custom'];
								}else{

									$async = ( isset($l['async']) && $l['async'] ) ? true : false;

									if( $l['type'] == 'css' ){

										$media = ( isset($l['media']) ) ? ' media="'.$l['media'].'"' : '';
										$t.='<link rel="stylesheet" href="'.$l['src'].'" type="text/css"';

										if( $async ){
											$t.=' media="none" onload="'.'if(media!=\'all\')media=\'all\''.'">';
											$t.="\n";
											$t.='<noscript><link rel="stylesheet" href="'.$l['src'].'"'.$media.'></noscript>';
											$t.="\n";
										}else{
											$t.=' media="'.$media.'">';
											$t.="\n";
										}
									}elseif( $l['type']== 'js' ){
										$async = ( $async ) ? ' async defer' : '';
										$t.='<script src="'.$l['src'].'"'.$async.'></script>';
										$t.="\n";
									}
								}

								echo $t;
							}

						}
					}

					$config = [];

				}

			}
			
		}

		public function appfooter(){
			$conf = __DIR__.'/../config.php';
			if( file_exists( $conf ) ){

				require( $conf );

				$config_main = $config;

				foreach( $config_main as $k => $v ){

					$conf_file = __DIR__.'/modules/'.ucfirst($k).'/config.php';

					if( file_exists( $conf_file ) ){
						require($conf_file);

						if( isset($config['autoload']) && isset($config['autoload']['footer']) ){

							foreach( $config['autoload']['footer'] as $l ){
								$t = '';

								$async = ( isset($l['async']) && $l['async'] ) ? true : false;
								if( isset($l['custom']) ){
									$t.=$l['custom'];
								}else{
									$async = ( $async ) ? ' async defer' : '';
									$t.='<script src="'.$l['src'].'"'.$async.'></script>';
									$t.="\n";
								}
								echo $t;
							}

						}
					}

				}

			}
		}

	}

	$loader = new Loader;

