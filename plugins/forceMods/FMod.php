<?php

// FMod
// (c) schmidi 2011
// www.doh-nuts.at
 
 
class FMod {
	
	protected $name = '';
	protected $url = '';
	protected $enabled = false;
		
		
	/**
	 * @fn __construct()
	 * @brief Function initialize instance of FMod.
	 *
	 * @param mixed $name
	 * @param mixed $url
	 * @param mixed $enabled
	 * @return void
	 */
	function __construct($name, $url, $enabled = true) {
		$this->name = trim($name);
		$this->url = trim($url);
		if($enabled === true || strtolower(trim($enabled)) == 'true' || trim($enabled) == '') {
			$this->enabled = true;
		}
	}

	
	/**
	 * @fn getName()
	 * @brief Function returns the name.
	 *
	 * @return 
	 */
	function getName() {
		return $this->name;
	}
	
	
	/**
	 * @fn getUrl()
	 * @brief Function returns the url.
	 *
	 * @return 
	 */
	function getUrl() {
		return $this->url;
	}
	
	
	/**
	 * @fn isEnabled()
	 * @brief Function returns the status.
	 *
	 * @return 
	 */
	function isEnabled() {
		return $this->enabled;
	}
	
	
	/**
	 * @fn enable()
	 * @brief Function enables mod.
	 *
	 * @return void
	 */
	function enable() {
		$this->enabled = true;
	}
	
	
	/**
	 * @fn disable()
	 * @brief Function disables mod.
	 *
	 * @return void
	 */
	function disable() {
		$this->enabled = false;
	}
	
	
	/**
	 * @fn enable()
	 * @brief Function returns string-representation of FMod.
	 *
	 * @return 
	 */
	function __toString() {
		$str = "name={$this->name}, url={$this->url}, enabled=";
		$str .= $this->enabled ? 'true' : 'false';
		return $str;
	}
	
}

?>