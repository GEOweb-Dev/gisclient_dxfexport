<?php

/******************************************************************************
 *
 * Purpose: Inizializzazione dei parametri per la creazione della mappa

 * Author:  Filippo Formentini formentini@perspectiva.it
 *
 ******************************************************************************
 *
 * Copyright (c) 2017 Perspectiva di Formentini Filippo
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
require_once "dxfFactory.php";
require_once "dxfFeatureExport.php";

set_time_limit(300);

//if (empty($_REQUEST['mapset'])) die (json_encode(array('error' => 200, 'message' => 'No mapset name')));
if (empty($_REQUEST['themes'])) die(json_encode(array('error' => 200, 'message' => 'No themes defined')));

//elaborazione dei parametri della richiesta
$filterType = $_REQUEST["filterType"]; //1:BBOX 2:Attribute 3:Field
if (is_null($filterType)) {
	$filterType = 1; //BBOX default
}
$minX = $_REQUEST["minx"];
$maxX = $_REQUEST["maxx"];
$minY = $_REQUEST["miny"];
$maxY = $_REQUEST["maxy"];
$mapSet = $_REQUEST["mapset"];
$themes = $_REQUEST["themes"];
$project = $_REQUEST["project"];
$epsg = $_REQUEST["epsg"];
$template = $_REQUEST["template"];
$enableLineThickness = $_REQUEST["enableLineThickness"];
$lineScale = $_REQUEST["lineScale"];
$enableColors = $_REQUEST["enableColors"];
$exportEmptyLayers = $_REQUEST["exportEmptyLayers"];
$enableTemplateLayer = $_REQUEST["enableTemplateLayer"];
$textScaleMultiplier = $_REQUEST["textScaleMultiplier"];
$labelScaleMultiplier = $_REQUEST["labelScaleMultiplier"];
$insertScaleMultiplier = $_REQUEST["insertScaleMultiplier"];
$layerFilter = $_REQUEST["layers"];
$attributeFilters = $_REQUEST["attributeFilters"];
$processingFilter = $_REQUEST["processingFilter"];

$outputFormat = $_REQUEST["outputFormat"]; //empty server default json || download

//inizializzazione del servizio
$user = new GCUser();
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

//carico l'oggetto mapset
//$objMapset = new gcMap($mapSet, false, NULL, false);
$dxfFeatureExport = new dxfFeatureExport($dxfLogPath);
$dxfFeatureExport->debug = $dxfDebug;
$dxfFeatureExport->dxfSplitLayers = $dxfSplitLayers;

$dxfFeatureExport->log(print_r($_REQUEST, TRUE));


if($filterType != 2){ //per attributi non escludiamo livelli
	$dxfFeatureExport->dxfExcludeGroups = $dxfExcludeGroups;
	$dxfFeatureExport->dxfExcludeLayers = $dxfExcludeLayers;
}
$dxfFeatureExport->dxfExcludeClassNames = $dxfExcludeClassNames;

//eseguo la conversione di un mapset in un file di configurazione
$configFile = new stdClass();
$configFile->{"minX"} = $minX;
$configFile->{"maxX"} = $maxX;
$configFile->{"minY"} = $minY;
$configFile->{"maxY"} = $maxY;
$configFile->{"mapSet"} = $mapSet;
$configFile->{"themes"} = explode(",", $themes);;
$configFile->{"project"} = $project;
$configFile->{"epsg"} = $epsg;
//default template
if (is_null($template)) {
	if (is_null($dxfStandardTemplate)) {
		$template = "template_dxf.dxf";
	} else {
		$template = $dxfStandardTemplate;
	}
}
$configFile->{"templateFile"} = "templates/" . urldecode($template);
$configFile->{"titolo"} = "Estrazione DXF";

$configFile->{"dxfEnableTemplateContesti"} = (is_null($enableTemplateLayer)) ? boolval($dxfenableDxfContesti) : $enableTemplateLayer;
$configFile->{"dxfTemplateContestiPath"} = $dxfTemplateContestiPath;
$configFile->{"dxfRemoveWFSHeadersLines"} = $dxfRemoveWFSHeadersLines;

$configFile->{"attributeFilters"} = null;
if (!is_null($attributeFilters)) {
	$attributeFilters = json_decode($attributeFilters);
	$configFile->{"attributeFilters"} = $attributeFilters;
}
if (!is_null($processingFilter)) {
	$processingFilter = json_decode($processingFilter);
}
$layers = $dxfFeatureExport->getLayers($filterType, $mapSet, $themes, $layerFilter, $project, $epsg, $attributeFilters, $processingFilter, $minX, $maxX, $minY, $maxY);

$configFile->{"layers"} = $layers;
$configFile->{"dxfDrawHatches"} = $dxfDrawHatches;
if($filterType==3){
	//var_dump($dxfFeatureExport->getPoligonMask($processingFilter));
	$configFile->{"dxfPoligonMask"} = $dxfFeatureExport->getPoligonMaskArrayFromfilter($processingFilter);
}

//abilita spessori
$configFile->{"dxfEnableLineThickness"} = (is_null($enableLineThickness)) ? boolval($dxfEnableLineThickness) : $enableLineThickness;
//abilita colori
$configFile->{"dxfEnableColors"} = (is_null($enableColors)) ? boolval($dxfEnableColors) : $enableColors;
$configFile->{"dxfLineScale"} = (is_null($lineScale)) ? $dxfLineScale : $lineScale;
$configFile->{"dxfExportEmptyLayers"} = (is_null($exportEmptyLayers)) ? boolval($dxfExportEmptyLayers) : $exportEmptyLayers;

$configFile->{"dxfTextScaleMultiplier"} = (is_null($textScaleMultiplier)) ? $dxfTextScaleMultiplier : $dxfTextScaleMultiplier * doubleval($textScaleMultiplier);
$configFile->{"dxfLabelScaleMultiplier"} = (is_null($labelScaleMultiplier)) ? $dxfLabelScaleMultiplier : $dxfLabelScaleMultiplier * doubleval($labelScaleMultiplier);
$configFile->{"dxfInsertScaleMultiplier"} = (is_null($insertScaleMultiplier)) ? $dxfInsertScaleMultiplier : $dxfInsertScaleMultiplier * doubleval($insertScaleMultiplier);

$dxfFact = new dxfFactory(json_encode($configFile), $dxfLogPath);
//autenticazione
$dxfFact->dxfUserName = $dxfUserName;
$dxfFact->dxfPassword = $dxfPassword;
//attivo il debug
$dxfFact->debug = $dxfDebug;
//imposto la sessione
$dxfFact->sessionId = session_id();
//imposto la blacklist di layer da eliminare
$dxfFact->excludeGeometryLayers = $dxfExcludeGeometryLayers;
$dxfFact->excludeTextLayers = $dxfExcludeTextLayers;
$dxfFact->excludeBlockNames = $dxfExcludeBlockNames;
$dxfFact->layersGuaine = $dxfLayersGuaine;

/*definizione del tipo di output*/
if (is_null($outputFormat)) {
	$outputFormat = "download";
	if ($dxfSaveToDir == 1) { //server default json
		$outputFormat = "json";
	}
}

$fileName = uniqid('dxf_', true) . ".dxf";
if ($outputFormat == "json") {
	//$dxfTempPath � definito in dxfConfig.php 
	$fileHandle = $dxfTempPath . $fileName;
	$dxfFact->createDxf($fileHandle);
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
	print $dxfFact->createDxf();
}
