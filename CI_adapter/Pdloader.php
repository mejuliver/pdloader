<?php
	defined('BASEPATH') OR exit('No direct script access allowed');

	class Pdloader{

		function load($n){

			require_once(APPPATH.'/../loader/Loader.php'); 
			
			$loader = new \PDLoader\Loader;

			return $loader->load($n);

		}

	}