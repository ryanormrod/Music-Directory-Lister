<?php

if(file_exists('includes/getid3/getid3.php')) {
    require_once('includes/getid3/getid3.php');
} else { echo 'Could not locate the getid3 PHP library.'; }

/**
 * class MusicDirectory{}
 * @package default
 * @author Ryan Ormrod
 */
class MusicDirectory {
    protected $config;
    protected $getID3;
	private $errors;
    /**
     * function __construct()
     * 
     * Create a new instance of getID3
     *
     * @return void
     * @author Ryan Ormrod.
     */
    function __construct() {
		$this->errors = array();
        $this->getID3 = new getID3();
	    $this->getID3->setOption(array('encoding' => "UTF-8"));
    }
    
    /**
     * function setMusicDirectory()
     * 
     * Define the directory in which the mp3 files are stored.
     *
     * @return void
     * @param string $musicDir
     * @author Ryan Ormrod .
     */
    
    public function setMusicDirectory($musicDir) {
		if(is_dir($musicDir)) {
        	$this->config['musicDir'] = $musicDir;
		} else {
			$this->errors[1][] = 'Unable to locate the music directory: ' .$musicDir;
		}
    }
    
    /**
     * function setDatabaseDirectory()
     * 
     * Define the directory in which the database files are stored
     *
     * @return void
     * @param string $databaseDir 
     * @author Ryan Ormrod.
     */
    public function setDatabaseDirectory($databaseDir) {
        if(!is_dir($databaseDir)) {
			$this->errors[2][] = 'Created a new folder for the database: ' . $databaseDir;
            if(!mkdir($databaseDir)) {
				$this->errors[1][] = 'Unable to create a folder for the database ' . $databaseDir;
			}
        } 
        $this->config['databaseDir'] = $databaseDir;   
    }
    
    /**
     * function setDatabaseFile()
     * 
     * Define the name of the database file and insert in the CSV heading
     *
     * @return void
     * @param string $fileName
     * @author Ryan Ormrod.
     */
    public function setDatabaseFile($fileName) {
        $file = fopen($this->config['databaseDir'].'/'.$fileName, 'w');
        fputcsv($file, 
            array(
                'Directory', 
                'Song Name', 
                'Artist', 
                'Song Title'), 
                ','
            );
        fclose($file);
        $this->config['database'] = $fileName; 
    }
    
    /**
     * function setFilters()
     * 
     * Define a list of filters for the file types that we need e.g
     * 
     * array('mp3', 'MP3') for only mp3 files
     *
     * @return void
     * @param array $filters
     * @author Ryan Ormrod .
     */
    public function setFilters($filters = array()) {
        if(is_array($filters)) {
            $this->config['filters'] = $filters;
        }
    }
    
    /**
     * function setIgnores()
     * 
     * Set a list of directory names and file names to ignore.
     *
     * e.g array('.','..','cgi-bin')
     * 
     * @return void
     * @param array $ignores
     * @author Ryan Ormrod .
     */
    public function setIgnores($ignores = array()) {
        if(is_array($ignores)) {
            $this->config['ignores'] = $ignores;
        }
    }
    
    /**
     * function getMusicListings()
     * 
     * A recursive function to keep travelling throughout directories
     * until it reaches the final directory. Each loop detects a directory
     * and then goes into the directory looking for files and folders. If 
     * it is a directory then it launches a new instance of the function and
     * runs over again until complete.
     *
     * @return void
     * @param string $musicDir
     * @param int $level
     * @author Ryan Ormrod .
     */
    public function getMusicListings($musicDir = '', $level = 0) {
        if($this->validMusicDir() && $this->validDatabase()) {
            $musicDir = (empty($musicDir) && $level == 0) 
                         ? $this->config['musicDir'] 
                         : $musicDir;
            if($directory = opendir("$musicDir")) {
                $fileHandle = fopen($this->config['databaseDir']
                                    .'/'.
                                    $this->config['database'], 'a');
                while(($document = readdir($directory)) !== FALSE) {
                    if(!in_array($document, $this->config['ignores'])) {
                        set_time_limit(30);
                        if(is_dir("$musicDir/$document")) {
                            $this->getMusicListings(
                                "$musicDir/$document", 
                                ($level+1)
                            );
                        } elseif(in_array(pathinfo("$document", PATHINFO_EXTENSION),
                          $this->config['filters'])) {
                            $fileInfo = $this->getID3->analyze("$musicDir/$document");
                            fputcsv($fileHandle, 
                            array(
                                "$musicDir/$document",
                                $fileInfo['tags']['id3v2']['title'][0],
                                $fileInfo['tags']['id3v2']['artist'][0],
                                $fileInfo['tags']['id3v2']['album'][0]                               
                            ),',');        
                        }
                    }
                }
                fclose($fileHandle);
            } else {
                $this->outputError('Can not open music directory');   
            }
        } else {
            $this->outputError('The music or database directory does not exist');   
        }
    }
   
   /**
     * function showMusicDirectory()
     * 
     * Read the information from the current database and display it on the
     * screen.
     *
     * @return void
     * @author Ryan Ormrod.
     */
    public function showMusicDirectory() {
        if($this->validDatabase()) {
            $database = fopen($this->config['databaseDir']
                        .'/'.$this->config['database'], 'r');
            while($line = fgets($database, 1000) ) {
                echo '<p class="musicLine">'.$line.'</p>';
            }
            fclose($database);
        } else {
            $this->outputError('Can not access the database');   
        }   
    }
    
    /**
     * function validMusicDir()
     * 
     * Ensure that the music directory has been set and that it is in fact
     * a directory.
     *
     * @return void
     * @author Ryan Ormrod.
     */
    private function validMusicDir() {
        if(array_key_exists('musicDir', $this->config)) {
            return (is_dir($this->config['musicDir']))
                    ? TRUE : FALSE;
        } else {
            $this->outputError('You must declare the music directory'); 
            return FALSE;  
        }
    }
    
    /**
     * function validDatabase()
     * 
     * Check that the database has been set and that the file exists
     *
     * @return void
     * @author Ryan Ormrod.
     */
    private function validDatabase() {
        if(array_key_exists('database', $this->config)) {
            return (file_exists($this->config['databaseDir']
                    .'/'.$this->config['database']));
                    
        } else {
            $this->outputError('You must declare the database file');
            return FALSE;
        }
    }

    /**
     * function outputError()
     * 
     * Output an error message to the screen.
     *
     * @return void
     * @param $errorMessage
     * @author Ryan Ormrod.
     */
    private function outputError($errorMessage) {
        echo '<p class="error">'.$errorMessage.'</p>';   
    }
}
?>
