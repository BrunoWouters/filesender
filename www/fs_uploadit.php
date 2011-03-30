<?php

/*
 * FileSender www.filesender.org
 * 
 * Copyright (c) 2009-2011, AARNet, HEAnet, SURFnet, UNINETT
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 
 * *	Redistributions of source code must retain the above copyright
 * 	notice, this list of conditions and the following disclaimer.
 * *	Redistributions in binary form must reproduce the above copyright
 * 	notice, this list of conditions and the following disclaimer in the
 * 	documentation and/or other materials provided with the distribution.
 * *	Neither the name of AARNet, HEAnet, SURFnet and UNINETT nor the
 * 	names of its contributors may be used to endorse or promote products
 * 	derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/*
 * ------------------------------
 * Upload file from flex application and move into site_filestore folder
 * ------------------------------
 * returns string: moveOk, moveError,invalidAuth back to flex
 */
function upperHexNumber($matches) {
    return '\u'.strtoupper($matches[1]);
}

require_once('../classes/_includes.php');

// flash upoload creates a new session id https so we need to make sure we are using the same session  
if(!empty($_REQUEST['s'])) { 
    session_id($_REQUEST['s']); 
    session_start();

    // Ensure existing session, users don't have the permission to create
    // a session because that would be a security vulnerability.
    if (!isset($_SESSION['validSession'])) {
        session_destroy();
        session_start();
        session_regenerate_id();
        $_SESSION['validSession'] = true;
        trigger_error("Invalid session supplied.", E_USER_ERROR);
    }
}

$authvoucher = AuthVoucher::getInstance();

// Check if there is a file supplied
if(!isset($_FILES['Filedata'])) trigger_error("request without file upload", E_USER_ERROR);

$authsaml = AuthSaml::getInstance();
$authvoucher = AuthVoucher::getInstance();
$CFG = config::getInstance();
$cofig = $CFG->loadConfig();
date_default_timezone_set($config['Default_TimeZone']);

if($authvoucher->aVoucher() || $authsaml->isAuth()) { 
 
    $uploadfolder =  $config["site_filestore"];

    // generate unique filename
	// tempFilename is created from ((uid or vid)+originalfilename+filesize)
	$tempFilename = ""; 

	// add voucher if this is a voucher upload
	if ($authvoucher->aVoucher()) {
		$tempFilename .= $_REQUEST['vid'];
	}
	// else add SAML eduPersonTargetedID
	else if( $authsaml->isAuth()) {
		$authAttributes = $authsaml->sAuth();
		$tempFilename .= $authAttributes["eduPersonTargetedID"];	
	} 
	
	// add the file name
	$tempFilename .=  sanitizeFilename($_GET['n']);

	// add the file size to the filename
	$tempFilename .=  $_GET['total'];

	// md5 $tempFilename
	$tempFilename = md5($tempFilename).'.tmp';
	
    // move file to correct uploadfolder destination
    $result = move_uploaded_file($_FILES['Filedata']['tmp_name'], $uploadfolder.sanitizeFilename(tempFilename).".tmp");

    if($result) {
        logEntry("File Moved");
        echo "moveOk";
    } else {
        // error moving files
        logEntry("Unable to move the file");
        echo "moveError";
    }
} else {
    logEntry("Error authorising Flash upload :Voucher-".$authvoucher->aVoucher().":SAML-". $authsaml->isAuth());
    echo "invalidAuth";
}
?>
