<?php
/*
 
 Copyright (c) 2001 - 2006 Ampache.org
 All Rights Reserved

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.

*/

/*!
        @function check_upload_extension
        @discussion checks the extension against the allow list
*/
function check_upload_extension($name='file') { 


        $filename = $_FILES[$name]['name'];

        $path_parts = pathinfo($filename);

        $extension = $path_parts['extension'];

        $allowed_extensions = "/" . conf('catalog_file_pattern') . "/";
        if (preg_match($allowed_extensions,$extension)) { 
                return true;
        }

	debug_event('upload',"Error: Invalid Extension $extension",'2');

	return false;

} // check_upload_extension

/*!
        @function check_upload_size
        @discussion checks the filesize of the upload
*/
function check_upload_size($name='file') { 
	static $total_size;

        $size = $_FILES[$name]['size'];
	
	$total_size = $total_size + $size;

        if ($size > conf('max_upload_size')) { 
		debug_event('upload',"Error: Upload to large, $size",'2');
		return false;
        }

	if ($total_size > conf('max_upload_size')) { 
		debug_event('upload',"Error: Total Upload to large, $total_size",'2');
		return false;
	}
        
        return true;

} // check_upload_size

/*!
	@function check_upload_directory
	@discussion this functions checks to make sure that you can actually write to the upload directory
*/
function check_upload_directory($directory) { 

	/* We need to make sure we can write to said dir */
	if (@is_writable($directory)) { 
		return true;
	}

	return false;

} // check_upload_directory

/*!
	@function find_upload_catalog
	@dicussion all upload directories must be contained within another catalog, this checks
		to make sure that is true. returns id of catalog found or false
*/
function find_upload_catalog($directory) { 

        $sql = "SELECT id, path FROM catalog";
        $db_results = mysql_query($sql, dbh());

        while( $results = mysql_fetch_assoc($db_results)) {
		
		$catalog_path = str_replace("\\","/",$results['path']);

		$directory = str_replace("\\","/",$directory);
		if (strncmp($directory,$catalog_path,strlen($catalog_path)) == '0') { 
			return $results['id'];
		}

        } // end while loop through catalog records

	return false;
        
} // find_upload_catalog

/*!
	@function upload_file
	@discussion this uploads a file to ampache 
*/
function upload_file($file,$target_directory) { 

	/* Build target file names */
	$full_filename = $target_directory . "/" . $_FILES[$file]['name'];

	/* Check to make sure the file doesn't exist already */
	if (file_exists($full_filename)) { 
		$GLOBALS['error']->add_error($file,"Error: $full_filename already exists");
		return false;
	}

	/* Attempt to move the file */
	if (!$upload_code = @move_uploaded_file($_FILES[$file]['tmp_name'],$full_filename)) { 
		$GLOBALS['error']->add_error($file,"Error: Unable to move $full_filename");
		return false;
	}

	return $full_filename;

} // upload_file

/*!
	@function insert_quarantine_record
	@discussion this inserts the record that a file has been added
*/
function insert_quarantine_record($username,$action,$filename) { 

	/* First make sure this file isn't listed already */
	$sql = "SELECT id FROM " . tbl_name('upload') . " WHERE file='" . sql_escape($filename) . "'";
	$db_results = mysql_query($sql, dbh());

	/* If no rows, insert using ugly sql statement */
	if (!mysql_num_rows($db_results)) { 
		$sql = "INSERT INTO " . tbl_name('upload') . " (`user`,`file`,`action`,`addition_time`)" . 
			" VALUES ('$username','" . sql_escape($filename) . "','$action','" . time() . "')";
		$db_results = mysql_query($sql, dbh());
	}

	else { 
		$sql = "UPDATE " . tbl_name('upload') . " SET action='$action' WHERE file='" . sql_escape($filename) . "'";
		$db_results = mysql_query($sql, dbh());
	}

} // insert_quarantine_record

/*!
	@function update_quarantine_record
	@discusison this updates an existing quarantine record
*/
function update_quarantine_record($id, $new_action) { 

	$sql = "UPDATE " . tbl_name('upload') . " SET action='$new_action' WHERE id='" . sql_escape($id) . "'";
	$db_results = mysql_query($sql, dbh());

	return true;

} // update_quarantine_record

/*!
	@function get_uploads
	@discussion gets uploads and returns an array of em 
*/
function get_uploads() { 

	$sql = "SELECT * FROM " . tbl_name('upload');
	$db_results = mysql_query($sql, dbh());
	
	$results = array();

	while ($r = mysql_fetch_assoc($db_results)) { 

                /* Create the vainfo object and get info */
		$vainfo	      = new vainfo($r['file']);
		$vainfo->get_info();
                $data         = $vainfo->tags;

                $key = get_tag_type($vainfo->tags);

                /* Fill Empty info from filename/path */
		$data = clean_tag_info($vainfo->tags,$key,$r['file']);

		$data['id'] 		= $r['id'];
		$data['user']		= $r['user'];
		$data['action']		= $r['action'];
		$data['addition_time']	= $r['addition_time'];

		$results[] = $data;

	} // end while

	return $results;

} // get_uploads

/*!
	@function show_upload
	@discussion This shows the upload templates
*/
function show_upload() { 

	require_once( "templates/show_upload.inc" );
	echo( "\n<br /><br />\n" );
	require_once( "templates/show_uploads.inc" );

} // show_upload

/*!
        @function report_file_error
        @discussion use with $_FILES and move_uploaded_file
        if move_uploaded_file returns false (error), pass
        $file['error'] here for interpretation
*/
function report_file_error( $error_code ) {

        $codes = array(
          0 => _( "The file uploaded successfully" ),
                1 => _( "The uploaded file exceeds the upload_max_filesize directive in php.ini" ),
                2 => _( "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form" ),
                3 => _( "The uploaded file was only partially uploaded" ),
                4 => _( "No file was uploaded" ),
                6 => _( "Missing a temporary folder" )  
        );
        
        return $codes[$error_code];

} // report_file_error


/**
 * show_upload_status_style
 * Pure fluf, it shows Grey for 'unknown' Green for Add and Red for delete
 */
function show_upload_status_style($action) { 

	switch ($action) { 
		case 'add':
			return 'width:10px;background:green;';
		break;
		case 'delete':
			return 'width:10px;background:red;';
		break;
		default: 
			return 'width:10px;';
		break;
	} // end switch

} // show_upload_status_style

?>
