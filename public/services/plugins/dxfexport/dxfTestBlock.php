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
require_once 'dxfFactory.php';

$sqlValues = array();
$project = "geoweb_iren";
//ricavo tutte le espressioni
$sql = "select distinct theme.project_name, theme.theme_name, symbol_name
                from gisclient_3.class c
                left join gisclient_3.style s using (class_id)
                --left join gisclient_3.symbol using (symbol_name)
                --left join gisclient_3.e_pattern using(pattern_id) 
				INNER JOIN gisclient_3.layer USING (layer_id)
				INNER JOIN gisclient_3.layergroup USING (layergroup_id) 
				INNER JOIN gisclient_3.theme on gisclient_3.layergroup.theme_id = gisclient_3.theme.theme_id
				where project_name = '$project' and  symbol_name is not null order by theme.project_name,theme.theme_name, symbol_name";
$db = GCApp::getDB();			
//die($sql);
$stmt = $db->prepare($sql);
$stmt->execute($sqlValues);



$dxf = file("templates/template_dxf.dxf", FILE_IGNORE_NEW_LINES);
$startBlocks = 0;
$endBlocks = 0;
//ricavo i blocchi
for($i = 0; $i < count($dxf); $i++){
	if($dxf[$i] == "SECTION"){
		if($dxf[$i + 2] == strtoupper("BLOCKS")){
			$startBlocks = $i -1;
		}
	}
}
$currentSection = false; //controllo se la table è quella corrente
for($i = 0; $i < count($dxf); $i++){
	if($dxf[$i] == "SECTION"){
		if($dxf[$i + 2] == strtoupper("BLOCKS")){
			$currentSection = true;
		}
	}
	if($dxf[$i] == "ENDSEC" && $currentSection){
		$endBlocks = $i -1;
	}
}
$blocks = array_slice($dxf, $startBlocks, $endBlocks);

print("<h1>Report disponibilit&agrave; blocchi nel template DXF</h1>");
print("<p>Il seguente report elenca tutti i nomi dei simboli utilizzati nella configurazione di GEOIREN e se &egrave; presente il blocco nel template utilizzato per i dxf.</p><br/>");

print("<table>");
print("<tr><td><strong>Progetto</strong></td><td><strong>Tema</strong></td><td><strong>Blocco</strong></td><td><strong>Stato</strong></td></tr>");
 while($thisRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
	$symbol = $thisRow["symbol_name"];
	$found = false;
	print("<tr>\n");
	print("<td><span>".$thisRow["project_name"]."</span></td>"."<td><span>".$thisRow["theme_name"]."</span></td>");
	print("<td><span>".$thisRow["symbol_name"]."</span></td>\n");
	for($i = 0; $i < count($blocks); $i++){
		//if(strpos($blocks[$i], $symbol) !== false){
		if($blocks[$i] == $symbol){
			$found = true;
			break;
		}
	}	
	if($found){
		print("<td><span style='color:green'>blocco disponibile</span></td>");
	}else{
		print("<td><span style='color:red'>blocco non disponibile</span></td>");
	}
	print("</tr>\n	");
	ob_flush();
}
print("</table>");
?>
