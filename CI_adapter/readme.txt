codeigniter library

put in config autoload libraries so you can call globally, you can do

$this->pdloader->load(<module name>);

open the Pdloader.php library and update the loader path, default is outside the codeigniter project folder
