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

include_once('dxfErrors.php');
include_once('dxfWfsFilters.php');
include_once('aciColors.php');

require_once ROOT_PATH . 'lib/gcuser.class.php';

/**
 *	Classe per la generazione della di un file DXF
 *
 */
class dxfFeatureExport
{

	public $debug = False;
	public $dxfSplitLayers = [];
	public $dxfExcludeGroups = [];
	public $dxfExcludeLayers = [];
	public $dxfExcludeClassNames = [];
	public $logTxt = "";
	public $logPath = "";

	public function __construct($dxfLogPath)
	{
		$this->logPath = $dxfLogPath;
	}

	public function log($message)
	{
		$this->logTxt .= $message . "/n";
	}

	public function getLayers($filterType, $mapSet, $themes, $layerFilter, $project, $epsg, $attributeFilters, $processingFilter, $minX, $maxX, $minY, $maxY)
	{
		$poligonMask = null;
		$layers = array(); //layer da restituire
		if (!is_null($themes)) {
			$themes = explode(",", $themes);
		} else {
			$themes = array();
		}
		if (!is_null($mapSet)) {
			$mapSet = explode(",", $mapSet);
		} else {
			$mapSet = array();
		}
		// if (!is_null($layerFilter)) {
		// 	$layerFilter = explode(",", $layerFilter);
		// 	$layerNames = array();
		// 	for ($i = 0; $i < count($layerFilter); $i++) {
		// 		$layerName = explode(".", $layerFilter[$i]);
		// 		array_push($layerNames, $layerName[1]);
		// 	}
		// 	$layerFilter = $layerNames;
		// } else {
		// 	$layerFilter = array();
		// }

		$db = GCApp::getDB();
		$dbData = new GCDataDB("geoirenweb_ut" . "/" .  USER_SCHEMA);

		//definizione dell'url da chiamare
		$owsUrl = NULL;
		if (defined('GISCLIENT_OWS_URL')) {
			$owsUrl = rtrim(GISCLIENT_OWS_URL, '?&');

			if (false === ($owsUrlQueryPart = parse_url($owsUrl, PHP_URL_QUERY))) {
				throw new Exception("Could not parse '" . GISCLIENT_OWS_URL . "' as string");
			}
			if (!empty($owsUrlQueryPart)) {
				$sep = '&';
			} else {
				$sep = '?';
			}
		}

		//non considero mai l'utente amministratore per l'estrazione DXF
		$isAdmin = false;
		//leggo i permessi
		//valutare la validit� di questa sezione
		if (!$isAdmin) {
			if (!empty($user->groups)) {
				$in = array();
				foreach ($this->groups as $k => $groupId) {
					array_push($in, ':group_param_' . $k);
					$sqlValues[':group_param_' . $k] = $groupId;
				}
				$groupFilter = ' and groupname in (' . implode(',', $in) . ') ';
			} else {
				$groupFilter = ' and 1=2 ';
			}
		}
		$authClause = '(coalesce(layer.private,0)=0)';

		$sqlValues = array();

		if (!empty($themes)) {
			//TODO Mettere i parameteri
			$themeList = "'" . implode("','", $themes) . "'";
			$mapSetList = "'" . implode("','", $mapSet) . "'";
			$sqlFilter = "mapset_name in ($mapSetList) and theme_name in ($themeList)"; //project = \''. $project .'\' and 
			if (!empty($layerFilter)) {
				$layerFilter = explode(",", $layerFilter);
				$layerFilterList = "'" . implode("','", $layerFilter) . "'";
				$sqlFilter .= " and layergroup.layergroup_name || '.' || layer.layer_name in ($layerFilterList)";
			}
		}
		//Ricavo i layer visibili e che dispongano di esportazione WFS
		//case when coalesce(layer.private,1) = 1 then '.($isAdmin ? '1' : 'wms').' else 1 end as wms,
		//case when coalesce(layer.private,1) = 1 then '.($isAdmin ? '1' : 'wfs').' else 1 end as wfs,
		//case when coalesce(layer.private,1) = 1 then '.($isAdmin ? '1' : 'wfst').' else 1 end as wfst,
		$sql = 'SELECT layer.*,
			mapset_name, layergroup.owstype_id, theme_id,theme_name,theme_title,theme_single,  layergroup.layergroup_name,
			layer.queryable as wfs,	
			project_name,
			layer_order,';
		//Aggiungo la presenza del campo filtro nel caso sia disponibile
		 if ($filterType == 3) {
			$tableNamesArr = array();
			 foreach ($processingFilter->{"tables"} as $tableValue) {
				array_push($tableNamesArr, "'".$tableValue->{"tableName"}."'");
			}
			 $tableNames = implode(', ',$tableNamesArr);
			 $sql .= " (select count(*) from " . DB_SCHEMA . ".layer as layer2 where layer.layer_id = layer2.layer_id and data in (" . $tableNames . ")) as processing_field_count ";
		 } else {
		 	$sql .= ' 0 as processing_field_count ';
		 }
		$sql .= ' FROM ' . DB_SCHEMA . '.theme 
			INNER JOIN ' . DB_SCHEMA . '.layergroup USING (theme_id) 
			INNER JOIN ' . DB_SCHEMA . '.mapset_layergroup using (layergroup_id)
			LEFT JOIN ' . DB_SCHEMA . '.layer USING (layergroup_id)
			LEFT JOIN ' . DB_SCHEMA . '.layer_groups USING (layer_id)
			WHERE (' . $sqlFilter . ')  and queryable = 1 ORDER BY layer.layer_order;';
		$this->log($sql);
		$stmt = $db->prepare($sql);
		$stmt->execute($sqlValues);

		if($filterType==3){
			$poligonMask = $this->getPoligonMask($processingFilter);
		}
		$this->log(print_r($_SESSION['GISCLIENT_USER_LAYER'], TRUE));
		//eseguo il loop sui layer per la generazione dei layer DXF
		while ($thisLayer = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$type_name = $thisLayer["layergroup_name"] . '.' . $thisLayer["layer_name"];
			if($thisLayer['private'] != 0 && $_SESSION['GISCLIENT_USER_LAYER'][$thisLayer['project_name']][$type_name]['WFS'] != 1){
				continue;
			}

			//verifico che sia un WMS
			$layerName = $thisLayer["theme_name"] . "_" . $thisLayer["layergroup_name"] . "_" . $thisLayer["layer_name"];
			//verifico che sia valido per l'estrazione
			if (
				!($thisLayer["owstype_id"] == 1
					&& (count($themes) == 0 || in_array($thisLayer["theme_name"], $themes))
					&& !dxfFeatureExport::stringArrayCheck($thisLayer["layergroup_name"], $this->dxfExcludeGroups)
					&& !dxfFeatureExport::stringArrayCheck($layerName, $this->dxfExcludeLayers))
			) {
				continue;
			}

			//definizione del layer
			$layer = new stdClass();

			$layer->{"themeName"} = $thisLayer["theme_name"];
			$layer->{"groupName"} = $thisLayer["layergroup_name"];
			$layer->{"layerName"} = $layerName;
			$layer->{"splitted"} = False; //verifica se il layer � stato splittato
			//campo label
			if ($thisLayer["labelitem"] != NULL) {
				$layer->{"fieldText"} = $thisLayer["labelitem"];
			}
			if ($thisLayer["classitem"] != NULL) {
				$layer->{"classItem"} = $thisLayer["classitem"];
			}
			//url per il download dei file
			$url = $owsUrl;
			$url .= sprintf("?PROJECT=%s&MAP=%s&SERVICE=WFS&TYPENAME=%s&MAXFEATURES=-1&SRS=EPSG:%s&REQUEST=GetFeature&VERSION=1.0.0&outputFormat=geojson", $project, $thisLayer["mapset_name"], $thisLayer["layergroup_name"] . "." . $thisLayer["layer_name"], $epsg);
			$wfsUrls = [];
			//applicazione dei filtri al layer
			switch ($filterType) {
				case 1: //BBOX
					array_push($wfsUrls, $url . dxfWfsFilters::GetFilterBBOX($minX, $maxX, $minY, $maxY));
					break;
				case 2: //Attribute
					$filterProperties = dxfWfsFilters::GetFilterProperties($attributeFilters);
					foreach ($filterProperties as $value) {
						array_push($wfsUrls, $url . $value);
					}
					break;
				case 3: //Field
					if ($thisLayer["processing_field_count"] > 0) {
						//applico il filtro sul campo
						foreach (dxfWfsFilters::GetFilterField($processingFilter->{'field'}, $processingFilter->{'value'}) as $value) {
							array_push($wfsUrls, $url . $value);
						}
					} else {
						//applico il filtro geografico
						if (!is_null($poligonMask)) {
							array_push($wfsUrls, $url . dxfWfsFilters::GetFilterPolygon($poligonMask));
						} else {
							$this->log("Maschera non valida");
						}
					}
					break;
				default:
					# code...
					break;
			}

			$layer->{"wfs"} = $wfsUrls;

			//"geometryType" : "polygon", // TODO definire la geometria

			$styles = $this->getStyles($db, $thisLayer, $layer, $this->dxfExcludeClassNames);

			//controllo se il layer deve essere splittato per i propri stili
			if (in_array($thisLayer["layer_name"], $this->dxfSplitLayers)) {
				//eseguo lo split dei layer
				foreach ($styles as $style) {
					$clonedLayer = unserialize(serialize($layer));
					$clonedLayer->{"layerName"} = $clonedLayer->{"layerName"} . "_" . $style->{"className"};
					$clonedLayer->{"styles"} = [$style];
					//aggiornamento dei colori altrimenti prendono il colore del primo stile principale
					if ($style->{"outlinecolor"} != NULL) {
						$clonedLayer->{"color"} = $style->{"outlinecolor"};
					} else if ($style->{"color"} != NULL) {
						$clonedLayer->{"color"} = $style->{"color"};
					} else if ($style->{"label_color"} != NULL) {
						$clonedLayer->{"color"} = $style->{"label_color"};
					}
					$clonedLayer->{"splitted"} = True;

					array_push($layers, $clonedLayer);
				}
			} else {
				//inserisco il layer singolo
				$layer->{"styles"} = $styles;
				array_push($layers, $layer);
			}
		}

		if ($this->debug) {
			$this->log('<pre>');
			$this->log(json_encode($layers));
			$this->log('</pre>');
			file_put_contents($this->logPath, $this->logTxt, FILE_APPEND);
		}
		
		return $layers;
	}


	/**
	 * Crea gli stili del layer
	 * $db  connessione db
	 * $thisLayer db row del layer
	 * $layer layer di destinazione
	 */
	public static function getStyles($db, $thisLayer, $layer, $dxfExcludeClassNames)
	{
		//ricavo gli stili e le classi
		$styles = array(); //elenco degli stili restituire
		//non si riesce a sovrapporre pi� stili per una singola classe
		$sqlStyle = "select pattern_id,symbol_name,class_id,layer_id,class_name,class_title	,class_text,expression,maxscale,minscale,class_template,class_order,legendtype_id,symbol_ttf_name,
                label_font,label_angle,label_color,label_outlinecolor,label_bgcolor,label_size,label_minsize,label_maxsize,label_position,label_antialias,label_free,label_priority,
                label_wrap,label_buffer,label_force,label_def,keyimage,style_id,style_name,color,outlinecolor,bgcolor,angle,size,minsize,maxsize,width,maxwidth,minwidth,
                style_def,style_order,symbolcategory_id,icontype,symbol_def,symbol_type,font_name,ascii_code,filled,points,image,pattern_name,pattern_def,pattern_order
                from " . DB_SCHEMA . ".class c
                left join " . DB_SCHEMA . ".style s using (class_id)
                left join " . DB_SCHEMA . ".symbol using (symbol_name)
                left join " . DB_SCHEMA . ".e_pattern using(pattern_id)
                where c.layer_id=?
				order by style_order";

		$stmtStyle = $db->prepare($sqlStyle);
		$stmtStyle->execute([$thisLayer["layer_id"]]);
		//loop sugli stili per la definizione delle classi
		$i = 0;

		while ($thisStyle = $stmtStyle->fetch(PDO::FETCH_ASSOC)) {
			//echo '<pre>';
			//print("pp_".$thisLayer["layer_name"]);
			//var_dump($thisStyle);
			//echo '</pre>';
			//se � il primo stile lo uso per definire caratteristiche di base del layer
			if ($i == 0) {
				//per la selezione del colore del layer d� la precedenza all'outline
				if ($thisStyle["outlinecolor"] != NULL) {
					$thisColor = explode(" ", $thisStyle["outlinecolor"]);;
					$layer->{"color"} = aciColors::getDecimalColor($thisColor[0], $thisColor[1], $thisColor[2]);
				} else if ($thisStyle["color"] != NULL) {
					//poi al colore normale
					$thisColor = explode(" ", $thisStyle["color"]);;
					$layer->{"color"} = aciColors::getDecimalColor($thisColor[0], $thisColor[1], $thisColor[2]);
				} else if ($thisStyle["label_color"] != NULL) {
					//poi al colore della label
					$thisColor = explode(" ", $thisStyle["label_color"]);;
					$layer->{"color"} = aciColors::getDecimalColor($thisColor[0], $thisColor[1], $thisColor[2]);
				}
			}

			$style = new stdClass(); //classe generica

			$style->{"expression"} = $thisStyle["expression"];
			$style->{"className"} = $thisStyle["class_name"];
			//se la classe non è valida procedo
			if (dxfFeatureExport::stringArrayCheck($thisStyle["class_name"], $dxfExcludeClassNames)) {
				continue;
			}

			//verifica del campo classitem da unire con l'espressione
			if ($thisLayer["classitem"] != NULL) {
				//supporto alla sintassi con {} 
				//TODO è un wokaround deve essere migliorata
				if (strpos($style->{"expression"}, '{') !== false) {
					$style->{"expression"} = str_replace("{", "('", $style->{"expression"});
					$style->{"expression"} = str_replace("}", "')", $style->{"expression"});
					$style->{"expression"} = "'[" . $thisLayer["classitem"] . "]' in " . $style->{"expression"};
				} else {
					$style->{"expression"} = "'[" . $thisLayer["classitem"] . "]' = " . $thisStyle["expression"];
				}
			}
			//verifica del campo etichetta
			if ($thisStyle["class_text"] != NULL) {
				$style->{"fieldText"} = $thisStyle["class_text"];
			}
			//verifica del nome del simbolo
			if ($thisStyle["symbol_name"] != NULL) {
				$style->{"symbol_name"} = $thisStyle["symbol_name"];
			}
			//verifica del simbolo all'interno dello stile
			if ($thisStyle["style_def"] != NULL) {
				$styleDefList = explode(PHP_EOL, $thisStyle["style_def"]);
				foreach ($styleDefList as $defKey => $defValue) {
					$styleValueList = explode(" ", $defValue);
					if (sizeof($styleValueList) < 2) {
						continue;
					}
					switch (strtoupper($styleValueList[0])) {
						case 'SYMBOL':
							$symbolStyleName = $styleValueList[1];
							//$symbolStyleName = str_replace("[", "", $symbolStyleName);
							//$symbolStyleName = str_replace("]", "", $symbolStyleName);
							$style->{"symbol_name"} = $symbolStyleName;
							break;
					}
				}
			}

			//verifica outline
			if ($thisStyle["outlinecolor"] != NULL) {
				$thisColor = explode(" ", $thisStyle["outlinecolor"]);;
				$style->{"outlineColor"} = aciColors::getDecimalColor($thisColor[0], $thisColor[1], $thisColor[2]);
			}
			if ($thisStyle["color"] != NULL) {
				//poi al colore normale
				$thisColor = explode(" ", $thisStyle["color"]);;

				$style->{"color"} = aciColors::getDecimalColor($thisColor[0], $thisColor[1], $thisColor[2]);
			}
			if ($thisStyle["label_position"] != NULL) {
				//posizione del testo
				$style->{"labelPosition"} = $thisStyle["label_position"];
			}
			if ($thisStyle["label_def"] != NULL) {
				$labelDefList = explode(PHP_EOL, $thisStyle["label_def"]);
				foreach ($labelDefList as $defKey => $defValue) {
					$labelDefList = explode(" ", $defValue);
					if (sizeof($labelDefList) < 2) {
						continue;
					}
					switch (strtoupper($labelDefList[0])) {
						case 'POSITION':
							$positionStyleName = $labelDefList[1];
							$style->{"labelPosition"} = $positionStyleName;
							break;
					}
				}
			}
			if ($thisStyle["label_color"] != NULL) {
				//poi al colore della label
				$thisColor = explode(" ", $thisStyle["label_color"]);;
				$style->{"labelColor"} = aciColors::getDecimalColor($thisColor[0], $thisColor[1], $thisColor[2]);
			}
			if ($thisStyle["label_angle"] != NULL) {
				if (is_numeric($thisStyle["label_angle"])) {
					$style->{"textAngle"} = intval($thisStyle["label_angle"]);
				} else {
					$style->{"fieldTextAngle"} = $thisStyle["label_angle"];
				}
			}
			if ($thisStyle["angle"] != NULL) {
				if (is_numeric($thisStyle["angle"])) {
					$style->{"angle"} = $thisStyle["angle"];
				} else {
					$style->{"fieldAngle"} = $thisStyle["angle"];
				}
			}
			if ($thisStyle["pattern_name"] != NULL) {
				$style->{"lineType"} = dxfFeatureExport::avaliablePattern($thisStyle["pattern_name"]);
			}
			if ($thisStyle["width"] != NULL) {
				if (is_numeric($thisStyle["width"])) {
					$style->{"thickness"} = floatval($thisStyle["width"]);
				}
			}

			$label_maxsize = 0;
			$label_minsize = 0;
			$label_size = 0;
			
			if ($thisStyle["label_maxsize"] != NULL) {
				$label_maxsize = $thisStyle["label_maxsize"];
				// if (is_numeric($thisStyle["label_maxsize"])) {
				// 	$label_maxsize = floatval($thisStyle["label_maxsize"]);
				// }
			}
			if ($thisStyle["label_minsize"] != NULL) {
				$label_minsize = $thisStyle["label_minsize"];
				// if (is_numeric($thisStyle["label_minsize"])) {
				// 	$label_minsize = floatval($thisStyle["label_minsize"]);
				// }
			}
			if ($thisStyle["label_size"] != NULL) {
				$label_size = $thisStyle["label_size"];
				// if (is_numeric($thisStyle["label_size"])) {
				// 	$label_size = floatval($thisStyle["label_size"]);
				// }
			}
			if(isset($label_minsize)) {
				$style->{"labelSize"} = $label_minsize;
			}else if(isset($label_size)) {
				$style->{"labelSize"} = $label_size;
			}else if(isset($label_maxsize)) {
				$style->{"labelSize"} = $label_maxsize;
			}
			if ($style->{"labelSize"} == NULL) { 
				$style->{"labelSize"} = 4;
			}
			$i++;/**/

			array_push($styles, $style);
		}
		return $styles;
	}

	/**
	 * Ricava la maschera per il filtro territoriale in formato wkt
	 */
	public static function getPoligonMask($processingFilter){
		$dbData = new GCDataDB("geoirenweb_ut" . "/" .  USER_SCHEMA);
		//ricavo il poligono nel caso di filter field
			$fieldValue = $processingFilter->{"value"};
			if ($processingFilter->{"fieldType"} == 's') {
				$fieldValue  = "'$fieldValue'";
			}
			//calcolo il poligono maschera
			$sqlPoligonMask = " select ST_ASTEXT(ST_BuildArea(ST_UNION(ST_Buffer(geom, 20,'endcap=square join=bevel')))) as rmask from (";
			$tables = $processingFilter->{"tables"};
			$sqlTableMask = "";
			for ($i = 0; $i < count($tables); $i++) {
				$sqlTableMask .= " select " . $tables[$i]->{'geometryField'} . " from " . $tables[$i]->{'tableNameSql'} . " where " . $tables[$i]->{"fieldSql"} . " = " . $fieldValue  . "";
				if (!is_null($tables[$i]->{'sqlAdditionalFilter'})) {
					$sqlTableMask .= $tables[$i]->{'sqlAdditionalFilter'};
				}
				if ($i < count($tables) - 1) {
					$sqlTableMask .= " union ";
				}
			}
			$sqlPoligonMask .= $sqlTableMask . ') as tmask;';
			//print($sqlPoligonMask);
			$stmtPolygon = $dbData->db->query($sqlPoligonMask);
			//$stmtPolygon = $dbData->db->prepare($sqlPoligonMask);
			//$stmtPolygon = $dbData->db->prepare("select 1 as mask;");
			//$stmtPolygon =  $dbData->db->query("select ST_ASTEXT(ST_UNION(ST_Buffer(ST_Envelope(geom), 20,'endcap=square join=bevel'))) as rmask from ( select geom from elettricita.fcl_e_mt_section inner join elettricita.ocl_ut_e_mt_circuit on fcl_e_mt_section.circ_id = ocl_ut_e_mt_circuit.obj_id where circ_no ilike '200003%' and id_stato = 3) as tmask");
			//$stmtPolygon = $dbData->db->query("select ST_ASTEXT(ST_UNION(ST_Buffer(ST_Envelope(geom), 20,'endcap=square join=bevel'))) as rmask from ( select geom from elettricita.fcl_e_bt_section inner join elettricita.ocl_ut_e_bt_circuit on fcl_e_bt_section.circ_id = ocl_ut_e_bt_circuit.obj_id where circ_no = '200003A02' and id_stato = 3) as tmask");
			//$stmtPolygon->query($stmtPolygon);
			$rowMask = $stmtPolygon->fetch(PDO::FETCH_ASSOC);
			$poligonMask = $rowMask["rmask"];
			return $poligonMask;
	}

	/**
	 * Ricava la maschera per il filtro territoriale in formato array di punti
	 */
	public static function getPoligonMaskArray($poligonMask){
		$coords = array();
		//"POLYGON((606850.7 4954556.9622,606840.7 4954566.9622,606840.7 4958979.5496,606838.1389 4958982.1107,606838.1389 4959048.9816,606546.6146 4959048.9816,606536.6146 4959058.9816,606536.6146 4959099.8,606515.5887 4959099.8,606511.2495 4959099.8,606501.2495 4959109.8,606501.2495 4959157.0562,606511.2495 4959167.0562,606540.1257 4959167.0562,606540.1257 4959168.9625,606541.1054 4959169.9422,606541.1054 4959370.2684,606551.1054 4959380.2684,606682.1432 4959380.2684,606682.1432 4959384.564,606576.2682 4959384.564,606566.2682 4959394.564,606566.2682 4959534.1458,606476.1286 4959534.1458,606466.1286 4959544.1458,606466.1286 4959654.1268,606476.1286 4959664.1268,606479.1116 4959664.1268,606479.1116 4959688.1558,606478.4837 4959688.1558,606478.4837 4959677.0172,606468.4837 4959667.0172,606255.759 4959667.0172,606245.759 4959677.0172,606245.759 4959904.6815,606246.2353 4959905.1578,606246.2353 4959933.7852,606256.2353 4959943.7852,606292.2747 4959943.7852,606425.0928 4959943.7852,606474.3044 4959943.7852,606484.3044 4959933.7852,606484.3044 4959884.3972,606850.7 4959884.3972,606860.7 4959874.3972,606860.7 4959821.2535,606972.9161 4959821.2535,606972.9161 4959875.4274,606982.9161 4959885.4274,607035.5421 4959885.4274,607035.5421 4960009.5054,607045.5421 4960019.5054,607089.8139 4960019.5054,607089.8139 4960134.6617,607099.8139 4960144.6617,607495.8361 4960144.6617,607505.8361 4960134.6617,607505.8361 4960039.1551,607513.9776 4960031.0136,607513.9776 4960028.8105,607513.9776 4959828.7271,607635.7332 4959828.7271,607643.2068 4959821.2535,607776.0055 4959821.2535,607776.0055 4960094.3768,607786.0055 4960104.3768,607797.8758 4960104.3768,607797.8758 4960144.504,607773.1713 4960144.504,607763.1713 4960154.504,607763.1713 4960312.8373,607773.1713 4960322.8373,607807.8758 4960322.8373,607814.9123 4960322.8373,607931.4126 4960322.8373,607941.4126 4960312.8373,607941.4126 4960092.3444,607936.5874 4960087.5192,607936.5874 4959821.2535,608208.4502 4959821.2535,608218.4502 4959811.2535,608218.4502 4954566.9622,608208.4502 4954556.9622,606850.7 4954556.9622),(606528.0534 4959688.1558,606528.0534 4959664.1268,606576.2682 4959664.1268,606586.2682 4959654.1268,606586.2682 4959565.509,606628.1718 4959565.509,606628.1718 4959621.0222,606628.1718 4959729.2976,606638.1718 4959739.2976,606693.6766 4959739.2976,606693.6766 4959795.1169,606695.224499999 4959796.6648,606560.2123 4959796.6648,606560.2123 4959698.1558,606550.2123 4959688.1558,606528.0534 4959688.1558),(607055.5421 4959821.2535,607364.1275 4959821.2535,607364.1275 4959822.1729,607374.1275 4959832.1729,607439.4997 4959832.1729,607439.4997 4959982.8233,607123.802 4959982.8233,607123.802 4959875.4274,607113.802 4959865.4274,607055.5421 4959865.4274,607055.5421 4959821.2535),(606722.9863 4959631.0222,606840.7 4959631.0222,606840.7 4959685.0308,606722.9863 4959685.0308,606722.9863 4959631.0222),(606840.7 4959506.5136,606840.7 4959542.232,606725.4891 4959542.232,606725.4891 4959431.6328,606823.6122 4959431.6328,606830.9458 4959424.2992,606830.9458 4959496.7594,606840.7 4959506.5136))"
		$poligonMask = str_replace("POLYGON((", "", $poligonMask);
		$poligonMask = str_replace("))", "", $poligonMask);
		$coordsStr = explode(",", $poligonMask);
		foreach ($coordsStr as $coordStr){
			array_push($coords, explode(" ", $coordStr));
		}
    	return $coords;
	}

	public static function getPoligonMaskArrayFromfilter($processingFilter){
    	return dxfFeatureExport::getPoligonMaskArray(dxfFeatureExport::getPoligonMask($processingFilter));
	}


	/*
	* Elenco dei tipi di linea supportati
	*/
	public static $lineTypes = array(
		'dash',
		'dash_dash_dot_dot',
		'dash_dash_dot',
		'dash_dot_dot_dot',
		'dash_dot_dot',
		'dash_dot',
		'linea_tratt',
		'dot',
		'linea_puntinata_10',
		'nascosta'
	);

	/**
	 * Verifica se un tipo di linea � supportato altrmenti restituisce il tipo continuous
	 * @param string $pattern
	 * @return string
	 */
	public static function avaliablePattern($pattern)
	{
		return $pattern;
		if (in_array(strtolower($pattern), SELF::$lineTypes)) {
			return $pattern;
		} else {
			return "Continuous";
		}
	}

	/**
	 * Verifica se la stringa � presente in un array in base a un patterna
	 * @param string $str
	 * @param array $arrayMatch
	 * @return bool
	 */
	public static function stringArrayCheck($str, $arrayMatch)
	{
		foreach ($arrayMatch as $match) {
			if (fnmatch($match, $str)) {
				return True;
			}
		}
		return False;
	}
}
