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

//variabili
//error_reporting(E_ALL);
//ini_set("display_errors", "on");
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

require_once '../../../../config/config.php';
require_once ADMIN_PATH."lib/functions.php";
require_once ROOT_PATH."lib/i18n.php";
require_once ROOT_PATH . 'lib/GCService.php';
//require_once 'include/gcMap.class.php';
require_once ADMIN_PATH."lib/gcFeature.class.php";
require_once 'lexerParser.php';


//Verifica di tutte le espressioni
//non considero mai l'utente amministratore
$isAdmin = false;
//leggo i permessi
//valutare la validità di questa sezione
if(!$isAdmin) {
	if(!empty($user->groups)) {
		$in = array();
		foreach($this->groups as $k => $groupId) {
			array_push($in, ':group_param_'.$k);
			$sqlValues[':group_param_'.$k] = $groupId;
		}
		$groupFilter = ' and groupname in ('.implode(',',$in).') ';
	} else {
		$groupFilter = ' and 1=2 ';
	}
}
$authClause = '(coalesce(layer.private,0)=0)';

$sqlValues = array();

if(!empty($mapSet)) {
	//$sqlFilter = 'mapset_name = :mapset_name';
	//$sqlValues = array(':mapset_name'=>$mapSet);
}
$mapSet = "base_dbt";
$mapSetList = "'" . implode("','", $mapSet) . "'";	
$sqlFilter = 'mapset_name in ('.$mapSetList.') and theme_name in ('.$themeList.')'; //project = \''. $project .'\' and 

//ricavo tutte le espressioni
$sql = 'select distinct expression
                from gisclient_3.class c
                left join gisclient_3.style s using (class_id)
				INNER JOIN gisclient_3.layer USING (layer_id)
				INNER JOIN gisclient_3.layergroup USING (layergroup_id) 
				where expression is not null';
$db = GCApp::getDB();			
//die($sql);
$stmt = $db->prepare($sql);
$stmt->execute($sqlValues);

$parserExpression = new Parser();

 while($thisRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
	$expression = $thisRow["expression"];
	$expressionValue = preg_replace('/[\[].*[\]\)]/U' , '1', $expression);
	print("<strong>$expression</strong><br/>");
	print("$expressionValue");
	$result = ($parserExpression->evaluateString($expressionValue))? 1 : 0;
	print("$result<br/>");
	ob_flush();
}

?>
