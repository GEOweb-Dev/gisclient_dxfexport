<?php
/******************************************************************************
*
* Purpose: Inizializzazione dei parametri per la creazione della mappa

* Author:  Filippo Formentini formentini@perspectiva.it
*
******************************************************************************
*
* Copyright (c) 2018 Perspectiva di Formentini Filippo
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version. See the COPYING file.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with p.mapper; if not, write to the Free Software
* Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*
******************************************************************************/
error_reporting(E_ALL);
ini_set("display_errors", "on");
//variabili
require_once "dxfConfig.php";

$dxf = "";
//nome del file
if(isset($_REQUEST["fileName"])){
	$fileHandle = $dxfTempPath.$_REQUEST["fileName"];
	if (isset($dxfTempPathCluster) && !empty($dxfTempPathCluster)) {
		$fileHandle = $dxfTempPathCluster.$_REQUEST["fileName"];
	}
	$dxf = file_get_contents($fileHandle, true);
}else{
	die("File DXF non trovato");
}

header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
header ("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header ("Pragma: no-cache"); // HTTP/1.0
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename='.$_REQUEST["fileName"]);
print $dxf;






?>
