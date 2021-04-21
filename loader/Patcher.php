<?php

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


	class Patcher{

		public function patch(){	

			$this->fetcher('https://bitbucket.org/utso-pulgada/pd-loader/get/HEAD.zip',__DIR__.'/temp/loader.zip');
			

			$zip = new \ZipArchive;	
			$res = $zip->open(__DIR__.'/temp/loader.zip');

			$loader_folder = false;

			if ($res === TRUE) {
				$zip->extractTo(__DIR__.'/temp');
				$zip->close();
			  	
			  	foreach (new \DirectoryIterator(__DIR__.'/temp') as $fileInfo) {
				    if($fileInfo->isDot()) continue;
				    
			    	if( strpos($fileInfo->getFilename(),'pd-loader') != false ){
			    		$loader_folder = $fileInfo->getFilename();			    		
			    		break;
			    	}
				    
				}
			} else {
			  print_r('Unable to process loader file');
			}
			

			if( $loader_folder ){

				// zip the loader folder contents
				$rootPath = realpath('temp/'.$loader_folder .'/loader');

				if( file_exists(__DIR__.'/temp/'.$loader_folder.'/loader/auth.env') ){
					unlink(__DIR__.'/temp/'.$loader_folder.'/loader/auth.env');
				}
				if( file_exists(__DIR__.'/temp/'.$loader_folder.'/loader/.env') ){
					unlink(__DIR__.'/temp/'.$loader_folder.'/loader/.env');
				}

				// Initialize archive object
				$zip = new ZipArchive();
				$zip->open('loader.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);

				// Create recursive directory iterator
				/** @var SplFileInfo[] $files */
				$files = new RecursiveIteratorIterator(
				    new RecursiveDirectoryIterator($rootPath),
				    RecursiveIteratorIterator::LEAVES_ONLY
				);

				foreach ($files as $name => $file)
				{
				    // Skip directories (they would be added automatically)
				    if (!$file->isDir())
				    {
				        // Get real and relative path for current file
				        $filePath = $file->getRealPath();
				        $relativePath = substr($filePath, strlen($rootPath) + 1);

				        // Add current file to archive
				        $zip->addFile($filePath, $relativePath);
				    }
				}

				// Zip archive will be created only after closing object

				$zip->close();				

				// unzip laoder
				$zip = new \ZipArchive;	
				$res = $zip->open('loader.zip');


				if ($res === TRUE) {
					$zip->extractTo(__DIR__.'/');
					$zip->close();

					unlink(__DIR__.'/loader.zip');
				} else {
				  print_r('Unable to process loader file');
				}
			}

		}
		public function deleteFolder($dir){
			if(file_exists($dir)){
				$it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
				$files = new \RecursiveIteratorIterator($it,\RecursiveIteratorIterator::CHILD_FIRST);

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
			
		public function is_connected()
		{
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
		private function fetcher($url,$des){

			if( !$this->is_connected() ){
				echo json_encode([ 'success' => false, 'msg' => 'Unable to download from server. You are not connected to a network']);
				exit;
			}

			if( !file_exists(__dir__.'/temp') ){
				mkdir(__DIR__.'/temp', 0777, true); // create temp folder
			}

			$context = stream_context_create(array('http' => array('header'=>'Connection: close\r\n')));
			// build env
			file_put_contents($des, 
			    file_get_contents($url,false,$context)
			);

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
		public function auth($p){

			if( file_exists(__DIR__.'/auth.env') ) {

				$pass = trim( file_get_contents(__DIR__.'/auth.env') );

				if( trim( $pass ) == '' ){
					return true;
				}

				if(  $p == $pass ){
					$_SESSION['pdloaderpatch'] = 'pdloader2019';
					return true;
				}else{
					unset($_SESSION['pdloaderpatch']);
					return false;
				}

			}else{
				unset($_SESSION['pdloaderpatch']);
				return false;
			}
		}

		public function checkAuth(){
			if( file_exists(__DIR__.'/auth.env') ) {
				if( isset($_SESSION['pdloaderpatch'] ) && $_SESSION['pdloaderpatch'] == 'pdloader2019' ){
					return true;
				}else{
					unset( $_SESSION['pdloaderpatch']);
				}
				$pass = trim( file_get_contents(__DIR__.'/auth.env') );

				if( trim( $pass ) == '' ){
					return true;
				}else{
					unset($_SESSION['pdloaderpatch']);
					return false;
				}

			}else{
				unset($_SESSION['pdloaderpatch']);
				return false;
			}
		}
	}

	$patcher = new Patcher;
	$auth = $patcher->checkAuth();
	$msg = false;

	if( $auth ){
		if( isset( $_GET['patch']) ){

			if( $_GET['patch'] == 1 ){
				$patcher->patch();
				$patcher->deleteFolder(realpath('temp'));

				if( file_exists(__DIR__.'/auth.env') ){
					chmod(__DIR__.'/auth.env',0600);
				}

				if( file_exists(__DIR__.'/.env') ){
					chmod(__DIR__.'/.env',0600);
				}
				
				unset($_SESSION['pdloaderauth']);
				unset($_SESSION['pdloaderpatch']);
			}

		}
	}else{
		if( isset( $_POST['passcode'] ) ){

			if( $patcher->auth( $_POST['passcode'] ) ){
				header('location:Patcher.php');
			}else{
				$msg = 'Invalid code, you are not authorized to access the patcher!';
				$auth = false;

			}
		}
	}


	
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Patcher	| Loader</title>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
	<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.3.1/css/bootstrap.min.css" media="all">
</head>
<body>
	<section style="padding-top:64px">
		<div class="container">
			<div class="row">
				<?php if( $auth ) : ?>
				<div class="col-12">
					<h4>You are about to patch the Loader files, are you sure you want to proceed? <a href="Patcher.php?patch=1" class="btn btn-success" style="margin-left: 8px;">Patch now</a></h4>
				</div>
				<?php else: ?>
				<div class="col-12 col-md-4 offset-md-4">
					<form action="Patcher.php" method="post" style="border: 1px solid #ededed;padding: 24px;border-radius: 5px;border-bottom-width: 2px;">
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
				<?php endif; ?>
			</div>
		</div>
	</section>
</body>
</html>