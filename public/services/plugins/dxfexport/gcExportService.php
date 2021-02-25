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
require_once ADMIN_PATH."lib/functions.php";
require_once ROOT_PATH."lib/i18n.php";
require_once ROOT_PATH . 'lib/GCService.php';
//require_once 'include/gcMap.class.php';
require_once ADMIN_PATH."lib/gcFeature.class.php";
require_once "dxfConfig.php";
require_once "dxfFactory.php";
require_once "dxfFeatureExport.php";

set_time_limit(300);

//if (empty($_REQUEST['mapset'])) die (json_encode(array('error' => 200, 'message' => 'No mapset name')));
if (empty($_REQUEST['themes'])) die (json_encode(array('error' => 200, 'message' => 'No themes defined')));

//elaborazione dei parametri della richiesta
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
$enableTemplateLayer = $_REQUEST["enableTemplateLayer"];
$textScaleMultiplier = $_REQUEST["textScaleMultiplier"];
$labelScaleMultiplier = $_REQUEST["labelScaleMultiplier"];
$insertScaleMultiplier = $_REQUEST["insertScaleMultiplier"];
$outputFormat = $_REQUEST["outputFormat"]; //empty server default json || download

//inizializzazione del servizio
$gcService = GCService::instance();
$gcService->startSession();

//carico l'oggetto mapset
//$objMapset = new gcMap($mapSet, false, NULL, false);
$dxfFeatureExport = new dxfFeatureExport($dxfLogPath);
$dxfFeatureExport->debug = $dxfDebug;
$dxfFeatureExport->dxfSplitLayers = $dxfSplitLayers;
$dxfFeatureExport->dxfExcludeGroups = $dxfExcludeGroups;
$dxfFeatureExport->dxfExcludeLayers = $dxfExcludeLayers;

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
if(is_null($template)){
	if(is_null($dxfStandardTemplate)){
		$template="template_dxf.dxf";
	}else{
		$template = $dxfStandardTemplate;
	}
}
$configFile->{"templateFile"} = "templates/".urldecode($template);
$configFile->{"titolo"} = "Estrazione DXF";

$configFile->{"dxfEnableTemplateContesti"} = (is_null($enableTemplateLayer)) ? boolval($dxfenableDxfContesti) : $enableTemplateLayer;
$configFile->{"dxfTemplateContestiPath"} = $dxfTemplateContestiPath;

//die($dxfFeatureExport->getAci(200,200,200)."");
$layers = $dxfFeatureExport->getLayers($mapSet, $themes, $project, $epsg);

$configFile->{"layers"} = $layers;
$configFile->{"dxfDrawHatches"} = $dxfDrawHatches;

//abilita spessori
$configFile->{"dxfEnableLineThickness"} = (is_null($enableLineThickness)) ? boolval($dxfEnableLineThickness) : $enableLineThickness;
//abilita colori
$configFile->{"dxfEnableColors"} = (is_null($enableColors)) ? boolval($dxfEnableColors) : $enableColors;
$configFile->{"dxfLineScale"} = (is_null($lineScale)) ? $dxfLineScale : $lineScale;

$configFile->{"dxfTextScaleMultiplier"} = (is_null($textScaleMultiplier)) ? $dxfTextScaleMultiplier : $dxfTextScaleMultiplier * doubleval($textScaleMultiplier);
$configFile->{"dxfLabelScaleMultiplier"} = (is_null($labelScaleMultiplier)) ? $dxfLabelScaleMultiplier : $dxfLabelScaleMultiplier * doubleval($labelScaleMultiplier);
$configFile->{"dxfInsertScaleMultiplier"} = (is_null($insertScaleMultiplier)) ? $dxfInsertScaleMultiplier : $dxfInsertScaleMultiplier * doubleval($insertScaleMultiplier);

//print json_encode($configFile);
//die(var_dump($configFile));


$dxfFact = new dxfFactory(json_encode($configFile), $dxfLogPath);
//attivo il debug
$dxfFact->debug = $dxfDebug;
//imposto la blacklist di layer da eliminare
$dxfFact->excludeGeometryLayers = $dxfExcludeGeometryLayers;
$dxfFact->excludeTextLayers = $dxfExcludeTextLayers;
$dxfFact->layersGuaine = $dxfLayersGuaine;


/*definizione del tipo di output*/
if(is_null($outputFormat)){
	$outputFormat = "download";
	if($dxfSaveToDir == 1){ //server default json
		$outputFormat = "json";
	}
}

$fileName = uniqid('dxf_', true).".dxf";
if($outputFormat == "json"){
	//$dxfTempPath � definito in dxfConfig.php 
	$fileHandle = $dxfTempPath.$fileName;
	$dxfFact->createDxf($fileHandle);
	$fileJson = new stdClass();
	$fileJson->{"filePath"} = $fileHandle;
	$fileJson->{"fileName"} = $fileName;
	die(json_encode($fileJson));
}else{
	header ("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
	header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
	header ("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header ("Pragma: no-cache"); // HTTP/1.0
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename='.basename($fileName));
	print $dxfFact->createDxf();
}
?>