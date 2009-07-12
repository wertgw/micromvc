<?php
/**
 * Common Functions
 *
 * This file contains a growing list of common functions for use in throughout
 * the MicroMVC system.
 *
 * @package		MicroMVC
 * @author		David Pennington
 * @copyright	Copyright (c) 2009 MicroMVC
 * @license		http://www.gnu.org/licenses/gpl-3.0.html
 * @link		http://micromvc.com
 * @version		1.0.1 <5/31/2009>
 ********************************** 80 Columns *********************************
 */

function ip_address() {

	//Get IP address - if proxy lets get the REAL IP address
	if (!empty($_SERVER['REMOTE_ADDR']) AND !empty($_SERVER['HTTP_CLIENT_IP'])) {
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
		$ip = $_SERVER['REMOTE_ADDR'];
	} elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} else {
		$ip = '0.0.0.0';
	}

	//Clean the IP and return it
	return sanitize_text($ip, 2);
}


//Get the current domain
function current_domain() {

	// Get the Site Name: www.site.com -also protects from XSS/CSFR attacks
	$regex = '/((([a-z0-9\-]{1,70}\.){1,5}[a-z]{2,4})|localhost)/i';

	//Match the name
	preg_match($regex,(!empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST']), $match);

	//MUST HAVE A HOST!
	if(empty($match[0])) {
		die('Sorry, host not found');
	}

	return $match[0];
}


//Check to see if it is an ajax request
function is_ajax_request() {
	if(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
		return TRUE;
	}
	return FALSE;
}


/**
 * Class registry
 *
 * This function acts as a singleton. If the requested class does not
 * exist it is instantiated and set to a static variable. If it has
 * previously been instantiated the variable is returned.
 *
 * @param	string	Class name being requested
 * @param	string	Folder name to find it in
 * @param	mixed	Optional params to pass
 * @param	bool	Location to look (1 = SITE, 2 = SYSTEM, 3 = MODULES)
 * @param	bool	(flag) load class but do not instantiate
 * @return	object
 */
function load_class($class = NULL, $path = NULL, $params = NULL, $instantiate = TRUE) {

	static $objects = array();

	//If a class is NOT given
	if ( ! $class) {
		trigger_error('Attempted to load a non-existent class with no name.');
		return FALSE;
	}

	//If this class is already loaded
	if(!empty($objects[$class])) {
		return $objects[$class];
	}

	// If the class is not already loaded
	if ( ! class_exists($class)) {
		//Require the file
		require_once($path. $class. '.php');
	}

	//If we just want to load the file - nothing more
	if ($instantiate == FALSE) {
		return TRUE;
	}

	return $objects[$class] = new $class(($params ? $params : ''));
}


/**
 * Custom error handler which shows more details when needed, yet can hide scary data
 * from the user. Auto-detects the level of errors you allow in your php.ini file and
 * only shows those errors and higher.
 *
 * @param $level
 * @param $message
 * @param $file
 * @param $line
 * @param $variables
 * @return void
 */
function _error_handler($level='', $message='', $file='', $line='', $variables='') {

	//If this error isn't worth reporting (or below the set level) - skip it
	if ($level == E_STRICT OR ($level & error_reporting()) !== $level) {
		return;
	}

	//Only show the system file that had the problem - not the whole server dir structure!
	$file = str_replace(SYSTEM_PATH, '', $file);

	//Set error types
	$error_levels = array(
	E_ERROR				=>	'Error',
	E_WARNING			=>	'Warning',
	E_PARSE				=>	'Parsing Error',
	E_NOTICE			=>	'Notice',
	E_CORE_ERROR		=>	'Core Error',
	E_CORE_WARNING		=>	'Core Warning',
	E_COMPILE_ERROR		=>	'Compile Error',
	E_COMPILE_WARNING	=>	'Compile Warning',
	E_USER_ERROR		=>	'User Error',
	E_USER_WARNING		=>	'User Warning',
	E_USER_NOTICE		=>	'User Notice',
	E_STRICT			=>	'Runtime Notice'
	);

	//Get Human-safe error title
	$error = $error_levels[$level];

	//If we only show simple error data
	if(DEBUG_MODE == FALSE) {

		//Create sentence
		$line_info = 'On line '. $line. ' in '. $file;

	} else {

		//If the database class is loaded - get the queries run (if any)
		if(class_exists('db')) {
			$db = db::get_instance();
		}

		//Get backtrace and remove last entry (this function)
		$backtrace = debug_backtrace();
		//Remove first entry (this error function)
		unset($backtrace[0]);

		if($backtrace) {

			//Store the array of backtraces
			$trace = array();

			//Max of 5 levels deep
			if(count($backtrace) > 5) {
				$backtrace = array_chunk($backtrace, 5, TRUE);
				$backtrace = $backtrace[0];
			}

			// start backtrace
			foreach ($backtrace as $key => $v) {

				if(!isset($v['line'])) {
					$v['line'] = ($key === 1 ? $line : '(unknown)');
				}
				if(!isset($v['file'])) {
					$v['file'] = ($key === 1 ? $file : '(unknown)');
				}

				$args = array();
				if(isset($v['args'])) {
					foreach ($v['args'] as $a) {
						$type = gettype($a);
						if($type == 'integer' OR $type == 'double') {
							$args[] = $a;

						} elseif ($type == 'string') {
							//Longer than 25 chars?
							$a = strlen($a) > 45 ? substr($a, 0, 45). '...' : $a;
							$args[] = '"'. htmlentities($a, ENT_QUOTES, 'utf-8'). '"';

						} elseif ($type == 'array') {
							$args[] = 'Array('.count($a).')';

						} elseif ($type == 'object') {
							$args[] = 'Object('.get_class($a).')';

						} elseif ($type == 'resource') {
							$args[] = 'Resource('.strstr($a, '#').')';

						} elseif ($type == 'boolean') {
							$args[] = ($a ? 'True' : 'False'). '';

						} elseif ($type == 'Null') {
							$args[] = 'Null';
						} else {
							$args[] = 'Unknown';
						}
					}

					//If only a couple arguments were given - convert to string
					if(count($args) < 4) {
						$args = implode(', ', $args);
					}
				}

				// Compose Backtrace
				$string = '';

				if(!empty($trace)) {
					$string .= 'Called by ';
				}

				//If this is a class
				if (isset($v['class'])) {
					$string .= 'Method <b>'.$v['class']. '->'. $v['function']. '('. (is_string($args) ? $args : ''). ')</b>';
				} else {
					$string .= 'Function <b>'. $v['function']. '('. (is_string($args) ? $args : ''). ')</b>';
				}

				//Add line number and file
				$string .= ' on line '. $v['line']. ' in '. str_replace(SYSTEM_PATH, '', $v['file']). '<br />';

				//Create an element containing the trace and function args (only if still an array)
				$trace[] = array($string, (is_string($args) ? '' : $args));

			}
		}
	}

	//Flush any output buffering first
	if(ob_get_level()) { ob_end_flush(); }

	//Load the view
	include(VIEW_PATH. 'errors'. DS. 'php_error.php');

	exit();
}


/**
 * Load a HTTP header error page and then exit script
 * @param $type
 */
function request_error($type = '404') {

	//Check the type of error
	if ($type == '400') {
		header("HTTP/1.0 400 Bad Request");
	} elseif ($type == '401') {
		header("HTTP/1.0 401 Unauthorized");
	} elseif ($type == '403') {
		header("HTTP/1.0 403 Forbidden");
	} elseif ($type == '500') {
		header("HTTP/1.0 500 Internal Server Error");
	} else {
		$type = '404';
		header("HTTP/1.0 404 Not Found");
	}

	//Load the view
	include(VIEW_PATH. 'errors'. DS. $type. '.php');

	//Exit
	exit();
}


/**
 * Load an error using the general error template and then exit the script
 * @param $message
 * @param $title
 */
function show_error($message = '', $title = 'An Error Was Encountered') {
	//Load the view
	exit(include(VIEW_PATH. 'errors'. DS. 'general.php'));
}


/**
 * Add <pre> tags around objects you want to dump.
 * @param mixed $data
 */
function print_pre($data = NULL) {
	print '<pre style="padding: 1em; margin: 1em 0;">';
	if(func_num_args() < 2) {
		print_r($data);
	} else {
		print_r(func_get_args());
	}
	print '</pre>';
}


/**
 * Return the output of print_pre as a string
 * @param	mixed $data
 * @return	string
 */
function return_print_pre($data = NULL) {
	ob_start();
	print_pre(func_get_args());
	$output = ob_get_contents();
	ob_end_clean();
	return $output;
}


/**
 * Cleans text of all bad characters
 * @param string	$text	text to clean
 * @param boolean	$level	Set to TRUE to only enable file safe chars
 * @return void
 */
function sanitize_text($text, $level=0){
	if(!$level) {
		//Delete anything that isn't a letter, number, or common symbol - then HTML encode the rest.
		return trim(htmlentities(preg_replace("/([^a-z0-9!@#$%^&*()_\-+\]\[{}\s\n<>:\\/\.,\?;'\"]+)/i", '', $text), ENT_QUOTES, 'UTF-8'));
	} else {
		//Make the text file/title/emailname safe
		return preg_replace("/([^a-z0-9_\-\.]+)/i", '_', trim($text));
	}
}


/**
 * split_text
 *
 * Split text into chunks ($inside contains all text inside
 * $start and $end, and $outside contains all text outside)
 *
 * @param	String  Text to split
 * @param	String  Start break item
 * @param	String  End break item
 * @return	Array
 */
function split_text($text='', $start='<code>', $end='</code>') {
	$tokens = explode($start, $text);
	$outside[] = $tokens[0];

	$num_tokens = count($tokens);
	for ($i = 1; $i < $num_tokens; ++$i) {
		$temp = explode($end, $tokens[$i]);
		$inside[] = $temp[0];
		$outside[] = $temp[1];
	}

	return array($inside, $outside);
}


/**
 * Random Charaters
 *
 * Pass this function the number of chars you want
 * and it will randomly make a string with that
 * many chars. (I removed chars that look alike.)
 *
 * @param	Int		Length of character string
 * @param	Int		Charater set to use
 * @return	Array
 */
function random_charaters($number, $type=0) {
	$ascii[0] = 'ACEFGHJKLMNPRSTUVWXY345679';
	$ascii[1] = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJK.MNOPQRSTUVWXYZ'
	. '!"#$%&\'()*+`-.\\/0123456789:;<=>?@{|}~';
	$chars = null;
	for($i=0; $i<$number; $i++) {
		$chars .= $ascii[$type]{rand(0,strlen($ascii[$type])-1)};
	}
	return $chars;
}


/**
 * Valid Email
 * @param	string	email to check
 * @return	boolean
 */
function valid_email($text){
	return ( ! preg_match("/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/i", $text)) ? FALSE : TRUE;
}


/**
 * unzip a file to a new location
 */
function unzip($file, $new_file) {

	if(file_exists($file)) {
		$zip = new ZipArchive;
		$zip->open($file);
		$zip->extractTo($new_file);
		$zip->close();
		return true;
	}

	return false;
}



/**
 * Upload Check Errors
 *
 * Checks the given tmpfile for any errors or problems with
 * the upload
 *
 * @access	public
 * @param	string	Name of the File
 * @return	boolean
 */
function upload_check_errors($file_name='') {

	$errors = array(
	UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
	UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
	UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
	UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
	UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
	UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
	UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.',
	);

	//Get the error
	$error = $_FILES[$file_name]['error'];

	//IF the error is something OTHER than "OK"
	if($error !== UPLOAD_ERR_OK) {
		if(isset($errors[$error])) {
			trigger_error($errors[$error], E_USER_WARNING);
		} else {
			trigger_error('Unknown file upload error in file: <b>'
			. clean_value($_FILES[$file_name]['name']). '</b>',
			E_USER_WARNING);
		}
		return false;
	}

	//If the file never made it to the server
	if(!is_uploaded_file($_FILES[$file_name]['tmp_name'])) {
		trigger_error('Possible file upload attack in file: '
		. clean_value($_FILES[$file_name]['name']). '</b>',
		E_USER_WARNING);
		return false;
	}

	return true;

}



/**
 * Upload Files
 *
 * @access	public
 * @param	string	The directory to place the uploaded files
 * @return	boolean
 */
function upload_files($dir) {

	//If the upload directory is useable and there are files to upload
	if(directory_usable($dir) && isset($_FILES)) {

		//Foreach file that has been uploaded
		foreach($_FILES as $name => $file) {

			//If no errors with the file
			if(upload_check_errors($name)) {
				if(!move_uploaded_file($file['tmp_name'], $dir. $file['name'])) {
					trigger_error('Could not move file', E_USER_ERROR);
					return;
				}
			}

		}
		return true;
	}

}



///////////////////////////////////////////////////////////
// A function to list all files within the specified directory
// and it's sub-directories. This is a recursive function that
// has no limit on the number of levels down you can search.
///////////////////////////////////////////////////////////
// What info does this function need?
/////////////////
//
// $data['start_dir']   The directory to start searching from   (Required) ("./" = current dir, "../" = up one level)
// $data['good_ext']    The file extensions to allow.           (Required) (set to 'array('all') to include everything)
// $data['skip_files']  An array of files to skip.              (Required) (empty array if you don't want to skip anything)
// $data['limit']       The limit of dir to search              (Required)
// $data['type']        Return files or Directories?            (Optional) (defaults to BOTH types but can also set to 'dir' or 'file')
// $data['light']       Only return file name and path          (Optional) (defaults to false) (true or false)
//
/////////////////
// Example data
/////////////////
//
// $data['start_dir']      = "../../";
// $data['good_ext']       = array('php', 'html');
// $data['skip_files']     = array('..', '.', 'txt', '.htaccess');
// $data['limit']          = 5;
// $data['type']           = 'file';
// $data['light']          = false;
//
//////////////////////////////////////////////////
function directory($data, $level=1) {

	//If no type was specified - default to showing BOTH
	if(!isset($data['type']) || !$data['type']) { $data['type'] = false; }

	//If light was not specified - defualt to heavy version
	if(!isset($data['light']) || !$data['light']) { $data['light'] = false; }

	//If the directory given actually IS a directory
	if (is_dir($data['start_dir'])) {

		//Then open the directory
		$handle = opendir($data['start_dir']);

		//Initialize array
		$files = array();

		//while their are files in the directory...
		while (($file = readdir($handle)) !== false) {

			//If the file is NOT in the bad file list...
			if (!(array_search($file, $data['skip_files']) > -1)) {

				//Store the full file path in a var
				$path = $data['start_dir']. $file;

				//if it is a dir
				if (filetype($path) == "dir") {

					//add it to our list of dirs
					if(!$data['type'] || $data['type'] == 'dir') {
						//Add the dir to our list
						$files[$path]['file'] = $file;
						$files[$path]['dir'] = substr($path, strlen(SYSTEM_PATH), -strlen($file));

						//If we are only getting the file names/paths
						if(!$data['light']) {
							$files[$path]['ext'] = 'dir';
							$files[$path]['level'] = $level;
							$files[$path]['size'] = 0;//@disk_total_space($path);
						}
					}

					//If the dir is NOT deeper than the limit && 'recursive' is set to true
					if($data['limit'] > $level){

						//Run this function on on the directory to see what is in it (this is where the recursive part starts)
						$files2 = directory(array('start_dir' => $path. '/', 'good_ext' => $data['good_ext'],
                                                  'skip_files' => $data['skip_files'], 'limit' => $data['limit'],
                                                  'type' => $data['type'], 'light' => $data['light']), $level + 1);

						//then combine the output with the current $files array
						if(is_array($files2)) { $files = array_merge($files, $files2); }
						$files2 = null;
					}

					//Else if it is a file
				} else {

					//get the extension of the file
					$ext = preg_replace('/(.+)\.([a-z0-9]{2,4})/i', '\\2', $file);

					//And if it is in the GOOD file extension list OR if the list is set to allow ALL files
					if( (($data['good_ext'][0] == "all") || (array_search($ext, $data['good_ext']) > -1)) && (!$data['type'] || $data['type'] == 'file') ) {

						//Add the file to our list
						$files[$path]['file'] = $file;
						$files[$path]['dir'] = substr($path, strlen(SYSTEM_PATH), -strlen($file));
						//Get the LAST "." followed by 2-4 letters/numbers
						$files[$path]['ext'] = $ext;

						//If we are only getting the file names/paths
						if(!$data['light']) {
							$files[$path]['level'] = $level;
							$files[$path]['size'] = filesize($path);
						}

					}
				}
			}
		}

		//Close the dir handle
		closedir($handle);

		//If there ARE files to sort
		if($files) {
			//sort by KEYS
			ksort($files);
		}

		//Return the result
		return $files;

	}

	trigger_error($data['start_dir']. " is not a valid directory.");
	return FALSE;
}


/**
 * Checks that a directory exists and is writable. If the directory does
 * not exist, the function will try to create it and/or change the
 * CHMOD settings on it.
 *
 * @param string $dir	directory you want to check
 * @param string $chmod	he CHMOD value you want to make it
 * @return unknown
 */
function directory_usable($dir, $chmod='0777') {

	//If it doesn't exist - make it!
	if(!is_dir($dir)) {
		if(!mkdir($dir, $chmod, true)) {
			trigger_error('Could not create the directory: <b>'. $dir. '</b>', E_USER_WARNING);
			return;
		}
	}

	//Make it writable
	if(!is_writable($dir)) {
		if(!chmod($dir, $chmod)) {
			trigger_error('Could not CHMOD 0777 the directory: <b>'. $dir. '</b>', E_USER_WARNING);
			return;
		}
	}

	return true;
}



/**
 * A function to recursively delete files and folders
 * @thanks: dev at grind [[DOT]] lv
 *
 * @param string	$dir	The path of the directory you want deleted
 * @param boolean	$remove	Remove Files (false) or Folder and Files (true)
 * @return boolean
 */
function destroy_directory($dir='', $remove=true) {

	//Try to open the directory handle
	if(!$dh = opendir($dir)) {
		trigger_error('<b>'. $dir. '</b> cannot be opened or does not exist', E_USER_WARNING);
		return;
	}

	//While there are files and directories in this directory
	while (false !== ($obj = readdir($dh))) {

		//Skip the object if it is the linux current (.) or parent (..) directory
		if($obj=='.' || $obj=='..') continue;

		$obj = $dir. $obj;

		//If the object is a directory
		if(is_dir($obj)) {

			//If we could NOT delete this directory
			if(!destroy_directory($obj, $remove)) {
				return;
			}

			//Else it must be a file
		} else {
			unlink($obj) or trigger_error('Could not remove file <b>'. $obj. '</b>', E_USER_WARNING);
		}

	}

	//Close the handle
	closedir($dh);

	if ($remove){
		rmdir($dir) or trigger_error('Could not remove directory <b>'. $dir. '</b>');
	}

	return true;
}


/**
 * Gzip/Compress Output
 * Original function came from wordpress.org
 * @return void
 */
function gzip_compression() {

	//If no encoding was given - then it must not be able to accept gzip pages
	if(!isset($_SERVER['HTTP_ACCEPT_ENCODING'])) { return false; }

	//If zlib is not ALREADY compressing the page - and ob_gzhandler is set
	if (( ini_get('zlib.output_compression') == 'On'
	|| ini_get('zlib.output_compression_level') > 0 )
	|| ini_get('output_handler') == 'ob_gzhandler' ) {
		return false;
	}

	//Else if zlib is loaded start the compression.
	if ( (extension_loaded( 'zlib' ))
	&& (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) ) {
		ob_start('ob_gzhandler');
	}

	/*
	 print $_SERVER['HTTP_ACCEPT_ENCODING']. '<br />'.
	 'extension_loaded("zlib") = '. extension_loaded( 'zlib' ). '<br />'.
	 'ini_get("zlib.output_compression") = '. ini_get('zlib.output_compression'). '<br />'.
	 'ini_get("output_handler") = '. ini_get('output_handler'). '<br />';
	 */
}


/**
 * Return a singleton instance of the current controller
 * @return object
 */
function get_instance(){
	return controller::get_instance();
}


/**
 * Creates pagination links for the number of pages given
 *
 * @param array $options
 * @return array
 */
function pagination($options=null) {

	/** [Options]
	 * total		Total number of items
	 * per_page		Items to show each page
	 * current_page	The current page that the user is on
	 * url			URI value to place in the links (must include "[[page]]")
	 * 				Example: /home/blog/page/[[page]]/
	 */

	//Don't allow page 0 or lower
	if($options['current_page'] < 0) {
		$options['current_page'] = 0;
	}


	//Initialize
	$data = array(
		'links' => null,
		'next' => null,
		'previous' => null,
		'total' => null,
		'offset' => 0,
	);

	//The offset to start from. This is useful if you are running a DB query
	if($options['current_page'] > 1) {
		$data["offset"] = (($options['per_page'] * $options['current_page']) - $options['per_page']);
	}

	//The Number of pages based on the total number of items and the number to show each page
	$data['total'] = ceil($options['total'] / $options['per_page']);

	//If there is more than one page...
	if($data['total'] > 1) {

		//If this is NOT the first page - show a previous link
		if($options['current_page'] > 1) {
			$data['previous'] = str_replace('[[page]]', ($options['current_page'] - 1), $options['url']);
		}

		//If this isn't the last page - add a "next" link
		if($options['current_page'] + 1 < $data['total']) {
			$data["next"] = str_replace('[[page]]', ($options['current_page'] + 1), $options['url']);
		}
	}

	//For each page, create the URL
	for($i = 0; $i < $data['total']; $i++) {
		if($options['current_page'] == $i) {
			$data['links'][$i] = '';
		} else {
			//Replace [[page]] with the page number
			$data["links"][$i] = str_replace('[[page]]', $i, $options['url']);
		}
	}

	return $data;
}
