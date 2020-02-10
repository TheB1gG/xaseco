<?php

// FModList
// (c) schmidi 2011
// www.doh-nuts.at


class FModList {
	
	protected $mods = array();
	protected $currentIndex = -1;
		

	/**
	 * @fn addMod()
	 * @brief Function adding Mod to ModList.
	 *
	 * @param mixed $mod
	 * @return
	 */
	function addMod($mod) {
		if(get_class($mod) == 'FMod') {
			if($mod->getUrl()) {
				$this->mods[] = $mod;
				return true;
			}
		}
		return false;
	}
	
	
	/**
	 * @fn countMods()
	 * @brief Function counting Mods in ModList.
	 *
	 * @return
	 */
	function countMods() {
		return count($this->mods);
	}
	
	
	/**
	 * @fn nextMod()
	 * @brief Function calculates and returns index of next mod.
	 *
	 * @param mixed $random
	 * @return
	 */
	function nextMod($random = false) {
		$n = $this->countMods();
		if($n < 1) {
			$this->currentIndex = -1;
			return $this->currentIndex;
		}
		
		// skip the rest if only 1 mod
		if($n == 1) {
			$this->currentIndex = 0;
			return $this->currentIndex;
		}

		if($random) {
			$index = mt_rand() % $n;
			if($index == $this->currentIndex) {
				$index = ($index + 1) % $n;
			}
		}
		else {
			$index = ($this->currentIndex + 1) % $n;
		}	
			
		for($i = 0; $i < $n; $i++) {
			$mod = &$this->mods[$index];
			if($mod->isEnabled()) {
				$this->currentIndex = $index;
				return $index;
			}
			$index = ($index + 1) % $n;
		}
		
		$this->currentIndex = -1;
		return $this->currentIndex;
	}
	
	
	/**
	 * @fn getIndex()
	 * @brief Function returns index of current mod.
	 *
	 * @return
	 */
	function getIndex() {
		return $this->currentIndex;
	}
	

	/**
	 * @fn getName()
	 * @brief Function returns name of (current) mod.
	 *
	 * @param mixed $index
	 * @return
	 */
	function getName($index = -1) {
		if($index < 0) {
			$index = $this->currentIndex;
		}
		
		if(isset($this->mods[$index])) {
			return $this->mods[$index]->getName(); 
		}
		return '';
	}
	
	
	/**
	 * @fn getUrl()
	 * @brief Function returns url of (current) mod.
	 *
	 * @param mixed $index
	 * @return
	 */
	function getUrl($index = -1) {
		if($index < 0) {
			$index = $this->currentIndex;
		}
		
		if(isset($this->mods[$index])) {
			return $this->mods[$index]->getUrl(); 
		}
		return '';
	}
	
	
	/**
	 * @fn isEnabled()
	 * @brief Function returns status of (current) mod.
	 *
	 * @param mixed $index
	 * @return
	 */
	function isEnabled($index = -1) {
		if($index < 0) {
			$index = $this->currentIndex;
		}
		
		if(isset($this->mods[$index])) {
			return $this->mods[$index]->isEnabled(); 
		}
		return false;
	}
	
	
	/**
	 * @fn enable()
	 * @brief Function enables (current) mod.
	 *
	 * @param mixed $index
	 * @return
	 */
	function enable($index = -1) {
		if($index < 0) {
			$index = $this->currentIndex;
		}
		
		if(isset($this->mods[$index])) {
			$this->mods[$index]->enable(); 
		}
	}
	
	
	/**
	 * @fn disable()
	 * @brief Function disables (current) mod.
	 *
	 * @param mixed $index
	 * @return
	 */
	function disable($index = -1) {
		if($index < 0) {
			$index = $this->currentIndex;
		}
		
		if(isset($this->mods[$index])) {
			$this->mods[$index]->disable(); 
		}
		return '';
	}
	
	
	/**
	 * @fn __toString()
	 * @brief Function returns string-representation of FModList.
	 *
	 * @return
	 */
	function __toString() {
		$str = '';
		foreach($this->mods as $i => &$mod) {
			$str .= $mod . "; ";
		}
		return $str;
	}
	
}

?>