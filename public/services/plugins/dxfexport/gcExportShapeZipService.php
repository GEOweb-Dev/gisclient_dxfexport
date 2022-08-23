<?php

/******************************************************************************
 *
 * Purpose: Download del file in formato shapezip

 * Author:  Filippo Formentini formentini@perspectiva.it
 *
 ******************************************************************************
 *
 * Copyright (c) 2021 Perspectiva di Formentini Filippo
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

//error_reporting( E_ALL );
//ini_set('display_errors', 1);

//variabili
require_once '../../../../config/config.php';
require_once ADMIN_PATH . "lib/functions.php";
require_once ROOT_PATH . "lib/i18n.php";
//require_once ROOT_PATH . 'lib/GCService.php';
//require_once 'include/gcMap.class.php';
require_once ADMIN_PATH . "lib/gcFeature.class.php";
require_once "dxfConfig.php";

set_time_limit(300);
//elaborazione dei parametri della richiesta
$minX = $_REQUEST["minx"];
$maxX = $_REQUEST["maxx"];
$minY = $_REQUEST["miny"];
$maxY = $_REQUEST["maxy"];
$mapSet = $_REQUEST["mapset"];
$themes = $_REQUEST["themes"];
$project = $_REQUEST["project"];
$epsg = $_REQUEST["epsg"];
$attributeFilters = $_REQUEST["attributeFilters"];
$layerFilter = $_REQUEST["layers"];
$outputFormat = $_REQUEST["outputFormat"]; //empty server default json || download

$user = new GCUser();

//Controllo del gruppo 
$groups = $_SESSION["GROUPS"];
//$groups = ["geoweb_shp"];
if(empty($groups)){
	header("HTTP/1.1 401 Unauthorized");
	die("gruppo non valido");
}
if(!array_intersect($dxfShpAllowedGroups, $groups)){
	header("HTTP/1.1 401 Unauthorized");
	die("gruppo non valido");
}

$isAuthenticated = $user->isAuthenticated();
if (empty($_SESSION['GISCLIENT_USER_LAYER'])) {
	$user->setAuthorizedLayers(array('mapset_name' => $mapSet));
}
// user does not have an open session, try to log in
if (!$isAuthenticated && isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
	if ($user->login($_SERVER['PHP_AUTH_USER'], GCUser::encPwd($_SERVER['PHP_AUTH_PW']))) {
		$user->setAuthorizedLayers(array('mapset_name' => $mapSet));
		$isAuthenticated = true;
	}
}
// user could not even log in, send correct headers and exit
if (!$isAuthenticated) {
	print_debug('unauthorized access', null, 'system');
	header('WWW-Authenticate: Basic realm="Gisclient"');
	header('HTTP/1.0 401 Unauthorized');
	exit(0);
}

//if (empty($_REQUEST['mapset'])) die (json_encode(array('error' => 200, 'message' => 'No mapset name')));
if (empty($_REQUEST['themes'])) die(json_encode(array('error' => 200, 'message' => 'No themes defined')));

//inizializzazione del servizio
//$gcService = GCService::instance();
//$gcService->startSession();

$wfsUrl = rtrim(GISCLIENT_OWS_URL, '?&');
$wfsUrl .= "?PROJECT=$project&MAP=$mapSet&SERVICE=WFS&TYPENAME=$layerFilter&MAXFEATURES=-1&SRS=EPSG:$epsg&REQUEST=GetFeature&VERSION=1.0.0&outputFormat=shapezip";
$wfsfilter = '&FILTER=%3Cogc:Filter xmlns:ogc=%22http://www.opengis.net/ogc%22%3E%3Cogc:And%3E';
//creazione dell'url
if (!is_null($minX)) {
	//ricavo il filtro per il WFS
	$filterEnvelope = "%3Cogc:BBOX%3E%3Cogc:PropertyName%3Egeom%3C/ogc:PropertyName%3E%3Cgml:Envelope%20xmlns:gml=%22http://www.opengis.net/gml%22%3E%3Cgml:lowerCorner%3E$minX%20$minY%3C/gml:lowerCorner%3E%3Cgml:upperCorner%3E$maxX%20$maxY%3C/gml:upperCorner%3E%3C/gml:Envelope%3E%3C/ogc:BBOX%3E";
}
if (!is_null($attributeFilters)) {
	$filterProperties = '';
	$attributeFilters = json_decode($attributeFilters);
	$filters = $attributeFilters->{"filters"};
	if (count($filters) > 1) {
		switch (strtoupper($attributeFilters->{"logic"})) {
			case "AND":
				$filterProperties = $filterProperties . "%3Cogc:And%3E";
				break;
			case "OR":
				$filterProperties = $filterProperties . "%3Cogc:Or%3E";
				break;
		}
	}
	for ($i = 0; $i < count($filters); $i++) {
		switch ($filters[$i]->{"operator"}) {
			case "equalto":
				$filterProperties = $filterProperties . "%3Cogc:PropertyIsEqualTo matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filters[$i]->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E" . $filters[$i]->{"value"} . "%3C/ogc:Literal%3E%3C/ogc:PropertyIsEqualTo%3E";
				break;
			case "notequalto":
				$filterProperties = $filterProperties . "%3Cogc:PropertyIsNotEqualTo matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filters[$i]->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E" . $filters[$i]->{"value"} . "%3C/ogc:Literal%3E%3C/ogc:PropertyIsNotEqualTo%3E";
				break;
			case "greaterthan":
				$filterProperties = $filterProperties . "%3Cogc:PropertyIsGreaterThanOrEqualTo matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filters[$i]->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E" . $filters[$i]->{"value"} . "%3C/ogc:Literal%3E%3C/ogc:PropertyIsGreaterThanOrEqualTo%3E";
				break;
			case "lessthan":
				$filterProperties = $filterProperties . "%3Cogc:PropertyIsLessThanOrEqualTo matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filters[$i]->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E" . $filters[$i]->{"value"} . "%3C/ogc:Literal%3E%3C/ogc:PropertyIsLessThanOrEqualTo%3E";
				break;
			case "contains":
				$filterProperties = $filterProperties . "%3Cogc:PropertyIsLike %20wildcard%3D%27*%27%20singleChar%3D%27.%27%20escape%3D%27!%27 matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filters[$i]->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E*" . $filters[$i]->{"value"} . "*%3C/ogc:Literal%3E%3C/ogc:PropertyIsLike%3E";
				break;
			case "startswith":
				$filterProperties = $filterProperties . "%3Cogc:PropertyIsLike %20wildcard%3D%27*%27%20singleChar%3D%27.%27%20escape%3D%27!%27 matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filters[$i]->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E" . $filters[$i]->{"value"} . "*%3C/ogc:Literal%3E%3C/ogc:PropertyIsLike%3E";
				break;
			case "endswith":
				$filterProperties = $filterProperties . "%3Cogc:PropertyIsLike %20wildcard%3D%27*%27%20singleChar%3D%27.%27%20escape%3D%27!%27 matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filters[$i]->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E*" . $filters[$i]->{"value"} . "%3C/ogc:Literal%3E%3C/ogc:PropertyIsLike%3E";
				break;
			case "in":
				$filterProperties = $filterProperties . "%3Cogc:Or%3E";
				$filterIn = explode(",", $filters[$i]->{"value"});
				for ($iin = 0; $iin < count($filterIn); $iin++) {
					$filterProperties = $filterProperties . "%3Cogc:PropertyIsEqualTo matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filters[$i]->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E" . trim($filterIn[$iin]) . "%3C/ogc:Literal%3E%3C/ogc:PropertyIsEqualTo%3E";
				}
				$filterProperties = $filterProperties . "%3C/ogc:Or%3E";
				break;
			default:
				break;
		}
	}
	if (count($filters) > 1) {
		switch (strtoupper($attributeFilters->{"logic"})) {
			case "AND":
				$filterProperties = $filterProperties . "%3C/ogc:And%3E";
				break;
			case "OR":
				$filterProperties = $filterProperties . "%3C/ogc:Or%3E";
				break;
		}
	}
}

if (!is_null($filterEnvelope)) {
	$wfsfilter = $wfsfilter . $filterEnvelope;
}
if (!is_null($filterProperties)) {
	$wfsfilter = $wfsfilter . $filterProperties;
}
$wfsfilter = $wfsfilter . "%3C/ogc:And%3E%3C/ogc:Filter%3E";
$wfsUrl = $wfsUrl . $wfsfilter;

$wfsUrl = str_replace(' ', '+', $wfsUrl); //avoid malformed
$wfsUrl.="&GC_SESSION_ID=". session_id();
$_SESSION["WMS_GETFEATURE_FORMATS"] = "shapezip";
session_write_close();

//download del file
$fileName = uniqid('shp_', true) . ".zip";
$fileHandle = $dxfTempPath . $fileName;
$out = fopen($fileHandle, "wb");

$ch = curl_init();
curl_setopt($ch, CURLOPT_FILE, $out);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_URL, $wfsUrl);
curl_exec($ch);
curl_close($ch);
$file = file($fileHandle);

if($dxfRemoveSHPHeadersLines > 0){
	$content = file_get_contents($fileHandle);
	$arr = explode("\n", $content);
		//elimino gli errori generati da mapserver
		for ($i = 0; $i < $dxfRemoveSHPHeadersLines; $i++) {
			array_shift($arr);
		}
		$newcontent  = implode("\n", $arr);
		file_put_contents($fileHandle, $newcontent);
}

/*definizione del tipo di output*/
if (is_null($outputFormat)) {
	$outputFormat = "download";
	if ($dxfSaveToDir == 1) { //server default json verrà scaricato comunque come file ZIP
		$outputFormat = "json";
	}
}

if ($outputFormat == "json") {
	//$dxfTempPath � definito in dxfConfig.php 
	$fileHandle = $dxfTempPath . $fileName;
	$fileJson = new stdClass();
	$fileJson->{"filePath"} = $fileHandle;
	$fileJson->{"fileName"} = $fileName;
	die(json_encode($fileJson));
} else {
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Pragma: no-cache"); // HTTP/1.0
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename=' . basename($fileName));
	//TODO restituire il file
}
