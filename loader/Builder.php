<?php

	namespace PDLoader;

	session_start();

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

	if( file_exists(__DIR__.'/../config.php') ){
		require_once(__DIR__.'/../config.php');
	}

	unset($_SESSION['pdloaderpatch']);

	require_once(__DIR__.'/Loader.php');	

	class Builder extends Loader{

		function __construct(){
			if( !file_exists(__DIR__.'/modules') ){
				mkdir(__DIR__.'/modules', 0755, true); // create modules folder
			}

			if( file_exists(__DIR__.'/auth.env') && substr(sprintf('%o', fileperms(__DIR__.'/auth.env')), -4) != 0600  ){
				chmod(__DIR__.'/auth.env',0600);
			}

			if( file_exists(__DIR__.'/.env') && substr(sprintf('%o', fileperms(__DIR__.'/.env')), -4) != 0600 ){
				chmod(__DIR__.'/.env',0600);
			}

			if( !file_exists(__DIR__.'/../config.php') ){

				$configF = fopen(__DIR__."/../config.php", "w") or die("Unable to open file!");
				$txt = "<?php\n\n";
				fwrite($configF, $txt);
				$txt = "\$config = [];\n";		
				fwrite($configF, $txt);
				fclose($configF);
			}

			$this->buildConfig();
		}
		public function checkAuth(){
			if( file_exists(__DIR__.'/auth.env') ) {
				if( isset($_SESSION['pdloaderauth'] ) && $_SESSION['pdloaderauth'] == 'pdloader2019' ){
					return true;
				}else{
					unset( $_SESSION['pdloaderauth']);
				}
				$pass = trim( file_get_contents(__DIR__.'/auth.env') );

				if( trim( $pass ) == '' ){
					return true;
				}else{
					unset( $_SESSION['pdloaderauth']);
					return false;
				}

			}else{
				unset($_SESSION['pdloaderauth']);
				return false;
			}
		}
		public function auth($p){

			if( file_exists(__DIR__.'/auth.env') ) {

				$pass = trim( file_get_contents(__DIR__.'/auth.env') );

				if( trim( $pass ) == '' ){
					return true;
				}

				if( isset( $_SESSION['pdloaderauth']) && $_SESSION['pdloaderauth'] == 'pdloader2019' ){
					return true;
				}
				
				if(  $p == $pass ){
					$_SESSION['pdloaderauth'] = 'pdloader2019';
					return true;
				}else{
					unset($_SESSION['pdloaderauth']);
					return false;
				}

			}else{
				unset($_SESSION['pdloaderauth']);
				return false;
			}
		}	
		private function getEnv(){

			if( !file_exists(__DIR__.'/.env') ){
				return false;
			}
			
			if( trim(file_get_contents(__DIR__.'/.env')) == '' ){
				return false;
			}

			$envraw = explode(',',file_get_contents(__DIR__.'/.env'));
			$env = [];

			foreach( $envraw as $e ){
				$env[strtolower(trim(explode('=',$e)[0]))] = trim(explode('=',$e)[1]);
			}

			return $env;
		}
		public function updateModule($n){

			header('Content-type:application/json');

			$env = $this->getEnv();

			if( isset($env[$n]) ){
				if( file_exists(__DIR__.'/modules/'.ucfirst($n) ) ){
					$this->deleteFolder( __DIR__.'/modules/'.ucfirst($n) );
				}
				$this->createModule($env[$n],$n);
				$this->deleteFolder(__DIR__.'/temp');

				echo json_encode([ 'success' => true, 'msg' => 'Module has been updated' ]);
			}else{
				echo json_encode([ 'success' => true, 'msg' => 'Module not found' ]);
			}
			exit;
		}
		public function build(){

			// run the env rebuilder
			$this->buildConfig();
			$this->buildModules();

			// temp blueprints
			$this->deleteFolder(__DIR__.'/temp');
		}
		public function buildModules($update=false){
			global $config;

			// download the loader repo
			$env = $this->getEnv();

			if( !$env ){
				header('Content-type:application/json');
				echo json_encode([ 'success' => false, 'msg' => 'Build config first, run build config.']);
				exit;
			}


			foreach( $config as $k => $v ){
				if( isset($env[strtolower($k)]) ){
					if( $update ){
						if( file_exists(__DIR__.'/modules/'.ucfirst($k) ) ){
							$this->deleteFolder(__DIR__.'/modules/'.ucfirst($k));
						}
					};
					if( !file_exists(__DIR__.'/modules/'.strtolower($k) ) ){

						$this->createModule($env[strtolower($k)],$k);
					}
				}
			}

			// re check folder
			foreach (new \DirectoryIterator(__DIR__.'/modules') as $fileInfo) {
			    if($fileInfo->isDot()) continue;
			    if( !isset( $config[strtolower($fileInfo->getFilename())]) ){

			    	$this->deleteFolder(__DIR__.'/modules/'.$fileInfo->getFilename());
			    }
			}
		}
		public function buildConfig(){
			
			$this->fetcher('https://github.com/mejuliver/loader-env-repo/archive/refs/heads/master.zip',__DIR__.'/temp/loader.zip');
			

			$zip = new \ZipArchive;	
			$res = $zip->open(__DIR__.'/temp/loader.zip');

			$loader_folder = false;


			if ($res === TRUE) {
				$zip->extractTo(__DIR__.'/temp');
				$zip->close();
			  	
			  	
				// update the .env file	
			  	foreach (new \DirectoryIterator(__DIR__.'/temp') as $fileInfo) {
				    if($fileInfo->isDot()) continue;
			    	
			    	if( strpos($fileInfo->getFilename(),'loader-env-repo') == 0 || strpos($fileInfo->getFilename(),'loader-env-repo') ){
			    		$loader_folder = $fileInfo->getFilename();
			    		break;
			    	}
				}
			} else {
			  print_r('Unable to process loader file');
			}
			
			if( $loader_folder ){
				
				$env_temp = file_get_contents(__DIR__.'/temp/'.$loader_folder.'/.env');

				file_put_contents(__DIR__.'/.env', $env_temp);
			}

			$this->deleteFolder(__DIR__.'/temp');
		}
		private function fetcher($url,$des){

			if( !$this->is_connected() ){
				echo json_encode([ 'success' => false, 'msg' => 'Unable to download from server. You are not connected to a network']);
				exit;
			}

			if( !file_exists(__DIR__.'/temp') ){
				mkdir(__DIR__.'/temp', 0755, true); // create temp folder
			}

			$context = stream_context_create(array('http' => array('header'=>'Connection: close\r\n')));
			// build env
			file_put_contents($des, 
			    file_get_contents($url,false,$context)
			);
		}
		// delete module
		public function deleteFolder($dir){
			if(file_exists($dir)){
				$it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
				$files = new \RecursiveIteratorIterator($it,
				             \RecursiveIteratorIterator::CHILD_FIRST);

				foreach($files as $file) {
					chmod($file->getRealPath(),0755);
				    if ($file->isDir()){
				        rmdir($file->getRealPath());
				    } else {
				        unlink($file->getRealPath());
				    }
				}
				rmdir($dir);
			}
		}	
		// create module
		private function createModule($url,$m){
	

			$this->fetcher($url,__DIR__.'/temp/'.strtolower($m).'.zip');

			$zip = new \ZipArchive;
			$res = $zip->open(__DIR__.'/temp/'.strtolower($m).'.zip');

			$m_folder = false;

			if ($res === TRUE) {
				$zip->extractTo(__DIR__.'/temp');
				$zip->close();
			  	
			  	
				// update the .env file	
			  	foreach (new \DirectoryIterator(__DIR__.'/temp') as $fileInfo) {
				    if($fileInfo->isDot()) continue;
			    	if( strpos($fileInfo->getFilename(),'loader-module-'.strtolower($m)) != false ){
			    		$m_folder = $fileInfo->getFilename();
			    		break;
			    	}
				    
				}
			}
			
			if( $m_folder ){
				// move the module
				$this->deleteFolder( __DIR__.'/modules/'.ucfirst($m));
				
				rename(__DIR__.'/temp/'.$m_folder, __DIR__.'/modules/'.ucfirst($m));

				if( method_exists($this->load($m),'onBuild')){
					$this->load($m)->onBuild();
				}
			}
		}
		private function get_http_response_code($url) {

			$headers = get_headers($url);
			substr($headers[0], 9, 3);

			if($headers != "200"){
			    return false;
			}else{
			    $res = file_get_contents($url);
			    return $res;
			}
		    
		}

		public function getModules(){
			global $config;

			if( !$this->getEnv() ){
				return [];
			}

			$modules = [];
			forEach( $this->getEnv() as $k => $v ){
				$embedded = false;
				$doc = false;
				if( isset($config[strtolower($k)]) ){
					$embedded = true;
					if( file_exists(__DIR__.'/modules/'.ucfirst($k).'/doc.html' ) ){
						$doc = true;
					}
				}
				$onmenu = '';
				
				if( file_exists(__DIR__.'/modules/'.ucfirst($k)) && method_exists($this->load(ucfirst($k)),'onMenu')){
					$onmenu.=str_replace('{moduleurl}','/modules/'.ucfirst($k),$this->load(ucfirst($k))->onMenu() );
				}

				$modules[] = [ 'name' => $k, 'url' => $v, 'embedded' => $embedded, 'doc' => $doc, 'onmenu' => $onmenu ];
				
			}

			return $modules;
		}
		public function is_connected(){
		    $connected = @fsockopen("www.google.com", 443); 
			//website, port  (try 80 or 443)

		    if ($connected){
		        $is_conn = true; //action when connected
		        fclose($connected);
		    }else{
		        $is_conn = false; //action in connection failure
		    }
		    return $is_conn;

		}

	}


	$builder = new builder;
	$auth = $builder->checkAuth();
	$msg = false;	

	if( $auth ){
		
		if( isset($_GET['type']) ){
			header('Content-type: application/json');
			switch( $_GET['type'] ){
				case 'modules' :
					if( isset($_GET['req']) && $_GET['req'] == 'update' ){
						$builder->buildModules(true);
						// temp blueprints
						$builder->deleteFolder(__DIR__.'/temp');
						echo json_encode([ 'msg' => 'Modules has been rebuild']);
						return;
					}
					$builder->buildModules();
					// temp blueprints
					$builder->deleteFolder(__DIR__.'/temp');
					echo json_encode([ 'msg' => 'Modules has been build']);
					return;
				break;
				case 'config':
					$builder->buildConfig();
					echo json_encode([ 'msg' => 'Config has been rebuild']);
					return;
				break;
				case 'all':
					$builder->build();
				break;
				case 'update_module':
					$builder->updateModule($_GET['name']);
				break;
				default :
				
			}
		}
	}else{

		if( isset( $_POST['passcode'] ) ){

			if( $builder->auth( $_POST['passcode'] ) ){
				header('location:Builder.php');
			}else{
				$msg = 'Invalid code, you are not authorized to access the builder!';
				$auth = false;

			}

		}else{
			if( isset($_GET['type']) ){
				echo json_encode([ 'msg' => 'You are not authorized to perform this action']);
				return;
			}
		}

	}

	

	
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Builder | Loader</title>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
	<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/css/bootstrap.min.css" media="all">
</head>
<body>
	<?php if( $auth ) : ?>
	<section style="padding-top:64px">
		<div class="container">
			<div class="row">
				<div class="col-12 col-md-4" style="margin-bottom:8px">
					<a href="Builder.php?type=modules" class="btn btn-success btn-block">Build Modules</a>
				</div>
				<div class="col-12 col-md-4" style="margin-bottom:8px">
					<a href="Builder.php?type=modules&req=update" class="btn btn-success btn-block">Rebuild Modules</a>
				</div>
				<div class="col-12 col-md-4" style="margin-bottom:8px">
					<a href="Builder.php?type=config" class="btn btn-success btn-block">Update Config</a>
				</div>
			</div>
		</div>
	</section>
	<hr style="margin-top:32px;margin-bottom:32px">
	<section style="padding-bottom:64px;">
		<div class="container">
			<div class="col-12">
				<p style="margin-bottom:16px">You can only install or remove module(s) by updating your project config.</p>
				<div class="table-responsive">
					<table class="table table-bordered ">
						<thead>
							<tr>
								<th>Module name</th>
								<th style="width:50px;"></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach( $builder->getModules() as $m ) { ?>
								<tr>
									<td>
										<a href="<?php echo str_replace('/get/HEAD.zip', '', $m['url']); ?>" target="_blank">
											<?php echo $m['name']; ?>
										</a>
									</td>
									<td style="width:300px" class="text-center">
										<?php if( $m['embedded'] ) { ?>
										<a href="Builder.php?type=update_module&name=<?php echo $m['name']; ?>" target="_blank" class="update-module" style="margin-right:12px;">Update</a>
										<?php if( $m['doc'] ) { ?>
										<a href="modules/<?php echo ucfirst($m['name']); ?>/doc.html?v=<?php echo uniqid(); ?>" target="_blank">How to Use</a>
										<?php } ?>
										<?php echo $m['onmenu']; ?>
										<?php } else { ?>
										Not installed
										<?php } ?>
									</td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</section>
	<footer>
		<div class="container">
			<div class="row">
				<div class="col-12">
					<p class="text-center">
						Copyright © PD 2019
					</p>
				</div>
			</div>
		</div>
	</footer>
	<script>
		document.querySelectorAll('.btn').forEach(function(el){
			el.onclick = function(e){
				e.preventDefault();

				var url = this.getAttribute('href');

				document.querySelectorAll('.btn').forEach(function(el2){
					el2.disabled = true;
					el2.style.opacity = '.5';
				});

				var xhttp = new XMLHttpRequest();
				xhttp.onreadystatechange = function() {
				    if (this.readyState == 4 && this.status == 200) {
				    	alert(JSON.parse(xhttp.responseText).msg);

				  		setTimeout(function(){
				  			location.reload();
				  		},300)
				    }
				};
				xhttp.open("GET",url, true);
				xhttp.send();
			}
		});

		document.querySelectorAll('.update-module').forEach(function(el){
			el.onclick = function(e){
				e.preventDefault();

				var url = this.getAttribute('href');

				var _self = this;

				this.disabled = true;
				this.closest('tr').style.opacity = '.5';

				var xhttp = new XMLHttpRequest();
				xhttp.onreadystatechange = function() {
				    if (this.readyState == 4 && this.status == 200) {
				    	alert(JSON.parse(xhttp.responseText).msg);

				  		setTimeout(function(){
				  			location.reload();
				  		},300)
				    }
				};
				xhttp.open("GET",url, true);
				xhttp.send();
			}
		});
	</script>
	<?php else : ?>
		<section style="padding-top:64px">
			<div class="container">
				<div class="row">
					<div class="col-12 col-md-4 offset-md-4">
						<form action="Builder.php" method="post" style="border: 1px solid #ededed;padding: 24px;border-radius: 5px;border-bottom-width: 2px;">
							<?php if( $msg ): ?>
							<div class="alert alert-danger" style="margin-bottom:6px">
								<?php echo $msg; ?>
							</div>
							<?php endif; ?>
							<fieldset>
								<label>Passcode</label>
								<input type="password" class="form-control" name="passcode">
							</fieldset>
							<button class="btn btn-success btn-block" style="margin-top: 8px;">Submit</button>
						</form>
					</div>
				</div>	
			</div>
		</section>
	<?php endif; ?>
</body>
</html>