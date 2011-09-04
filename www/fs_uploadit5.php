<?php
/*
 *  FileSender www.filesender.org
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
 *
 * ------------------------------
 * Upload file from flex application and move into site_filestore folder
 * ------------------------------
 * returns string: moveOk, moveError,invalidAuth back to flex
 */
function upperHexNumber($matches) {
    return '\u'.strtoupper($matches[1]);
}

// use token if available for SIMPLESAML 1.7 or set session if earlier version of SIMPLESAML
	if (isset($_POST['token']) && $_POST['token'] != "") {
	$_COOKIE['SimpleSAMLAuthToken'] = $_POST['token'];
	}	
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
	
	
require_once('../classes/_includes.php');

$authsaml = AuthSaml::getInstance();
$authvoucher = AuthVoucher::getInstance();
$CFG = config::getInstance();
$config = $CFG->loadConfig();
$functions = Functions::getInstance();
date_default_timezone_set($config['Default_TimeZone']);

logEntry("DEBUG fs_uploadit5: REQUEST data: " . print_r($_REQUEST, true));
logEntry("DEBUG fs_uploadit5: POST data: " . print_r($_POST, true));

if($authvoucher->aVoucher() || $authsaml->isAuth()) { 
$uploadfolder =  $config["site_filestore"];
$tempuploadfolder =  $config["site_temp_filestore"];
	
$fileuid = getGUID();
$tempFilename = ""; 
$s = "complete";

	//  add SAML saml_uid_attribute
	if( $authsaml->isAuth()) {
		$authAttributes = $authsaml->sAuth();
		$tempFilename = $authAttributes["saml_uid_attribute"];
		$filedata["fileauthuseruid"] = $authAttributes["saml_uid_attribute"];
		$filedata["fileauthuseremail"] = $authAttributes["email"];
		logEntry("DEBUG fs_uploadit5: authsaml filedata " . print_r($filedata, true));
		logEntry("DEBUG fs_uploadit5: tempfilename 1a : ".$tempFilename);
	} 
	// add voucher if this is a voucher upload
	if ($authvoucher->aVoucher()) {
		$tempFilename = $_POST['vid'];
		$tempData = $functions->getVoucherData($_POST["filevoucheruid"]);
		$filedata["fileauthuseruid"] = $tempData[0]["fileauthuseruid"];	
		$filedata["fileauthuseremail"] = $tempData[0]["fileauthuseremail"];	
		logEntry("DEBUG fs_uploadit5: authvoucher filedata " . print_r($filedata, true));
		logEntry("DEBUG fs_uploadit5: authvoucher tempData " . print_r($tempData, true));
		logEntry("DEBUG fs_uploadit5: tempfilename 1v : ".$tempFilename);
	}
	
	// add the file name
	$tempFilename .=  sanitizeFilename($_POST['n']);
	logEntry("DEBUG fs_uploadit5: tempfilename 2 : ".$tempFilename);

	// add the file size to the filename
	$tempFilename .=  $_POST['total'];
	logEntry("DEBUG fs_uploadit5: tempfilename 3 : ".$tempFilename);

	// md5 $tempFilename
	$tempFilename = md5($tempFilename).'.tmp';
	logEntry("DEBUG fs_uploadit5: tempfilename 4 : ".$tempFilename);

	$correctfilename = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/','upperHexNumber',trim(json_encode($_REQUEST['n']),"\""));
	logEntry("DEBUG fs_uploadit5: correctfilename  : ".$correctfilename);

    // move file to correct uploadfolder destination
	$result = rename($tempuploadfolder.$tempFilename, $uploadfolder.$fileuid.".tmp");
	if(!$result) {
		logEntry("Unable to move the file");
		trigger_error("Unable to move the file", E_USER_ERROR);
	}
	
	$filedata["filefrom"] = $_POST["filefrom"];
	$filedata["filesize"] = $_POST["total"];
	$filedata["filesubject"] = $_POST["filesubject"];
	$filedata["filemessage"] = $_POST["filemessage"];
	$filedata["fileoriginalname"] =  $_POST["n"];
	$filedata["fileuid"] = $fileuid;
	$filedata["filestatus"]  = "Available";
	$filedata["fileexpirydate"] = date($config["postgresdateformat"],strtotime($_POST["fileexpirydate"]));
	
	// loop though multiple emails
	$emailto = str_replace(",",";",$_POST["fileto"]);
	$emailArray = preg_split("/;/", $emailto);
	foreach ($emailArray as $Email) { 
	$filedata["fileto"] = $Email;
	$filedata["filevoucheruid"] = getGUID();
	
	$functions->inserFileHTML5($filedata);
	}
	// if AUP then add session variable to store that a user selected the session variable
	if(isset($_POST["aup"]))
	{
		$_SESSION["aup"] = "true";
	}
	if(isset($_POST["filestatus"]) && $_POST["filestatus"] == "Voucher")
		{
		$tempData = $functions->getVoucherData($_POST["filevoucheruid"]);
		$functions->closeVoucher($tempData[0]["fileid"]);
		$s = "completev";	
        }
		
		if(isset($_POST["loadtype"]) && $_POST["loadtype"] == "standard")
		{
		$redirect = "Location: index.php?s=".$s;
		header( $redirect ) ;
		}
		
        echo "moveOk";
		logEntry("File Moved");
		// close voucher

} else {
	    echo "invalidAuth";
	    logEntry("fs_uploadit5.php: Error authorising upload :Voucher-".$authvoucher->aVoucher().":SAML-". $authsaml->isAuth());

}
?>
