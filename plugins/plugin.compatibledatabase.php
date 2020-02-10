<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * This script extend support backward compatibility 
 * to plugins which use the deprecated mysql_* functions.
 * You can modify this file as you want, to implement
 * missing functions.
 *
 * @author    Superritchman
 * @version   1.0
 *
 * Dependencies: requires plugin.localdatabase.php
 */

if (!function_exists('mysql_connect')) {
	function mysql_connect($server,$user,$password="",$newlink=false,$flags=0) {
		global $mysqli;
		$mysqli = new mysqli($server, $user, $password);
		return $mysqli;
	}
}
if (!function_exists('mysql_select_db')) {
	function mysql_select_db($database_name, $link=false) {
		global $mysqli;
		return $mysqli->select_db($database_name);
	}
}
if (!function_exists('mysql_affected_rows')) {
	function mysql_affected_rows($ident = null) {
		global $mysqli;
		return $mysqli->affected_rows;
	}
}
if (!function_exists('mysql_close')) {
	function mysql_close($ident = null){
		global $mysqli;
		return $mysqli->close();
	}
}

if (!function_exists('mysql_errno')) {
	function mysql_errno($ident = null){
		global $mysqli;
		return $mysqli->errno;
	}
}

if (!function_exists('mysql_error')) {
	function mysql_error($ident = null){
		global $mysqli;
		return $mysqli->error;
	}
}

if (!function_exists('mysql_escape_string')) {
	function mysql_escape_string($string){
		global $mysqli;
		return $mysqli->real_escape_string($string);
	}
}

if (!function_exists('mysql_real_escape_string')) {
	function mysql_real_escape_string($string){
		global $mysqli;
		return $mysqli->real_escape_string($string);
	}
}

if (!function_exists('mysql_fetch_array')) {
	function mysql_fetch_array($result, $int=null){
		return $result->fetch_array();
	}
}

if (!function_exists('mysql_fetch_assoc')) {
	function mysql_fetch_assoc($result){
		return $result->fetch_assoc();
	}
}

if (!function_exists('mysql_fetch_field')) {
	function mysql_fetch_field($result, $offset = 0){
		return $result->fetch_field();
	}
}

if (!function_exists('mysql_fetch_object')) {
	function mysql_fetch_object($result){
		return $result->fetch_object();
	}
}
if (!function_exists('mysql_fetch_row')) {
	function mysql_fetch_row($result){
		return $result->fetch_row();
	}
}
if (!function_exists('mysql_free_result')) {
	function mysql_free_result($result){
		return $result->free_result();
	}
}
if (!function_exists('mysql_insert_id')) {
	function mysql_insert_id($link = null){
		global $_db;
		return mysqli_insert_id(is_null($link) ? $_db->conn : $link);
	}
}
// if (!function_exists('mysql_list_dbs')) {
// 	function mysql_list_dbs($result){
// 		return mysqli_list_dbs($result);
// 	}
// }
// if (!function_exists('mysql_list_dbs')) {
// 	function mysql_list_dbs($result){
// 		return mysqli_list_dbs($result);
// 	}
// }
if (!function_exists('mysql_query')) {
	function mysql_query($query, $res=null){
		global $mysqli;
		return $mysqli->query($query);
	}
}
if (!function_exists('mysql_num_rows')) {
	function mysql_num_rows($result){
		return $result->num_rows;
	}
}
if (!function_exists('mysql_ping')) {
	function mysql_ping($resource = null){
		global $mysqli;
		return $mysqli->ping();
	}
}
if (!function_exists('mysql_set_charset')) {
	function mysql_set_charset($cname, $res=null){
		global $mysqli;
		return $mysqli->set_charset($cname);
	}
}
if (!function_exists('mysql_stat')) {
	function mysql_stat($link = null) {
		global $mysqli;
		return $mysqli->stat();
	}
}

// if (!function_exists('mysql_tablename')) {
// 	function mysql_tablename($resource) {
// 		return mysqli_tablename($resource);
// 	}
// }
 
if (!function_exists('mysql_thread_id')) {
	function mysql_thread_id($res = null) {
		global $mysqli;
		return $mysqli->thread_id;
	}
}

if (!function_exists('mysql_unbuffered_query')) {
	function mysql_unbuffered_query($query, $con=null) {
		global $mysqli;
		return $mysqli->query($query);
	}
}

if (!function_exists('mysql_get_server_info')) {
	function mysql_get_server_info($con=null) {
		global $mysqli;
		return $mysqli->server_info;
	}
}

if (!function_exists('mysql_result')) {
    function mysql_result($result, $row, $field) {
        global $mysqli;
        $result->data_seek($row);
        return $result->fetch_row()[$field];
    }
}

if (!function_exists('call_user_method_array')) {
	function call_user_method_array($method, $object, $array) {
		return call_user_func_array(array($object, $method), $array);
	}
}

?>