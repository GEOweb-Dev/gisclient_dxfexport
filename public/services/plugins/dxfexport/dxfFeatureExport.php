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
require_once ROOT_PATH . 'lib/gcuser.class.php';

/**
*	Classe per la generazione della di un file DXF
*
*/
class dxfFeatureExport {
	
	public $debug = False;
	public $dxfSplitLayers = [];
	public $logTxt = "";
	public $logPath = "";
	
	public function __construct($dxfLogPath) {
		$this->logPath = $dxfLogPath;
	}
	
	public function log($message){
		$this->logTxt .= $message."/n";
	}
	
	public function getLayers($mapSet, $themes, $project, $epsg){
		$layers = array(); //layer da restituire
		if(!is_null($themes)){
			$themes = explode(",", $themes);
		}else{
			$themes = array();
		}
		if(!is_null($mapSet)){
			$mapSet = explode(",", $mapSet);
		}else{
			$mapSet = array();
		}
		
		$db = GCApp::getDB();
		
		//definizione dell'url da chiamare
		$owsUrl = NULL;
        if (defined('GISCLIENT_OWS_URL')) {
            $owsUrl = rtrim(GISCLIENT_OWS_URL, '?&');
            
            if (false === ($owsUrlQueryPart = parse_url($owsUrl, PHP_URL_QUERY))) {
                throw new Exception("Could not parse '". GISCLIENT_OWS_URL . "' as string");
            }
            if(!empty($owsUrlQueryPart)) {
                $sep = '&';
            } else {
                $sep = '?';
            }
        }       

		//non considero mai l'utente amministratore per l'estrazione DXF
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
		if(!empty($themes)) {
			//TODO Mettrere i parameteri
			$themeList = "'" . implode("','", $themes) . "'";
			$mapSetList = "'" . implode("','", $mapSet) . "'";
			
			$sqlFilter = 'mapset_name in ('.$mapSetList.') and theme_name in ('.$themeList.')'; //project = \''. $project .'\' and 
		}
		//Ricavo i layer visibili e che dispongano di esportazione WFS
		//al momento elimino anche gli inquadramenti
		//case when coalesce(layer.private,1) = 1 then '.($isAdmin ? '1' : 'wms').' else 1 end as wms,
		//case when coalesce(layer.private,1) = 1 then '.($isAdmin ? '1' : 'wfs').' else 1 end as wfs,
		//case when coalesce(layer.private,1) = 1 then '.($isAdmin ? '1' : 'wfst').' else 1 end as wfst,
		$sql = ' SELECT layer.*,mapset_name, layergroup.owstype_id, theme_id,theme_name,theme_title,theme_single,  layergroup.layergroup_name,
			layer.queryable as wfs,	
			layer_order
			FROM '.DB_SCHEMA.'.theme 
			INNER JOIN '.DB_SCHEMA.'.layergroup USING (theme_id) 
			INNER JOIN '.DB_SCHEMA.'.mapset_layergroup using (layergroup_id)
			LEFT JOIN '.DB_SCHEMA.'.layer USING (layergroup_id)
			LEFT JOIN '.DB_SCHEMA.'.layer_groups USING (layer_id)
			WHERE ('.$sqlFilter.') AND ('.$authClause.") and queryable = 1 and layer.layer_name not like '%inquadrament%' ORDER BY layer.layer_order;";
			// 
			
		//die($sql);
		$stmt = $db->prepare($sql);
		$stmt->execute($sqlValues);
		
		//eseguo il loop sui layer per la generazione dei layer DXF
		while($thisLayer = $stmt->fetch(PDO::FETCH_ASSOC)) {
			//echo '<pre>';
			//var_dump($thisLayer);
			//echo '<pre>';
			//verifico che sia un WMS
			//verifico che sia presente in temi se in forniti
			if($thisLayer["owstype_id"] == 1 && (count($themes) == 0 || in_array($thisLayer["theme_name"], $themes))){
				//definizione del layer
				$layer = new stdClass();
				$styles = array(); //elenco degli stili restituire
				$layer->{"layerName"} = $thisLayer["theme_name"]."_".$thisLayer["layer_name"];
				//campo label
				if($thisLayer["labelitem"] != NULL){
					$layer->{"fieldText"} = $thisLayer["labelitem"];
				}
				if($thisLayer["classitem"] != NULL){
					$layer->{"classItem"} = $thisLayer["classitem"];
				}
				//url per il download dei file
				$url = $owsUrl;
				$url .= sprintf("?PROJECT=%s&MAP=%s&SERVICE=WFS&TYPENAME=%s&MAXFEATURES=-1&SRS=EPSG:%s&REQUEST=GetFeature&VERSION=1.0.0&outputFormat=geojson", $project, $thisLayer["mapset_name"], $thisLayer["layergroup_name"].".".$thisLayer["layer_name"], $epsg);
				//print $url;
				$layer->{"wfs"} = $url;
				//"geometryType" : "polygon", // TODO definire la geometria
				
				//ricavo gli stili e le classi
				//non si riesce a sovrapporre più stili per una singola classe
				$sqlStyle = "select pattern_id,symbol_name,class_id,layer_id,class_name,class_title	,class_text,expression,maxscale,minscale,class_template,class_order,legendtype_id,symbol_ttf_name,
                label_font,label_angle,label_color,label_outlinecolor,label_bgcolor,label_size,label_minsize,label_maxsize,label_position,label_antialias,label_free,label_priority,
                label_wrap,label_buffer,label_force,label_def,keyimage,style_id,style_name,color,outlinecolor,bgcolor,angle,size,minsize,maxsize,width,maxwidth,minwidth,
                style_def,style_order,symbolcategory_id,icontype,symbol_def,symbol_type,font_name,ascii_code,filled,points,image,pattern_name,pattern_def,pattern_order
                from gisclient_3.class c
                left join gisclient_3.style s using (class_id)
                left join gisclient_3.symbol using (symbol_name)
                left join gisclient_3.e_pattern using(pattern_id)
                where c.layer_id=?
                order by style_order";
				$stmtStyle = $db->prepare($sqlStyle);
				$stmtStyle->execute([$thisLayer["layer_id"]]);
				//loop sugli stili per la definizione delle classi
				$i = 0;
				
				while($thisStyle = $stmtStyle->fetch(PDO::FETCH_ASSOC)) {
					//echo '<pre>';
					//var_dump($thisStyle);
					//echo '</pre>';
					//se è il primo stile lo uso per definire caratteristiche di base del layer
					if($i == 0){
						//per la selezione del colore del layer dò la precedenza all'outline
						if($thisStyle["outlinecolor"] != NULL){
							$thisColor = explode(" ", $thisStyle["outlinecolor"]);;
							$layer->{"color"} = $this->getDecimalColor($thisColor[0], $thisColor[1], $thisColor[2]);
						}else if($thisStyle["color"] != NULL){
							//poi al colore normale
							$thisColor = explode(" ", $thisStyle["color"]);;
							$layer->{"color"} = $this->getDecimalColor($thisColor[0], $thisColor[1], $thisColor[2]);
						}
						else if($thisStyle["label_color"] != NULL){
							//poi al colore della label
							$thisColor = explode(" ", $thisStyle["label_color"]);;
							$layer->{"color"} = $this->getDecimalColor($thisColor[0], $thisColor[1], $thisColor[2]);
						}
					}
					
					$style = new stdClass(); //classe generica
					
					$style->{"expression"} = $thisStyle["expression"];
					$style->{"className"} = $thisStyle["class_name"];
					//print $thisStyle["expression"]."\n";
					//verifica del campo classitem da unire con l'espressione
					if($thisLayer["classitem"] != NULL){
						$style->{"expression"} = "'[".$thisLayer["classitem"]."]' = ".$thisStyle["expression"];
					}
					//verifica del campo etichetta
					if($thisStyle["class_text"] != NULL){
						$style->{"fieldText"} = $thisStyle["class_text"];
					}
					//verifica del nome del simbolo
					if($thisStyle["symbol_name"] != NULL){
						$style->{"symbol_name"} = $thisStyle["symbol_name"];
					}
					//verifica outline
					if($thisStyle["outlinecolor"] != NULL){
						$thisColor = explode(" ", $thisStyle["outlinecolor"]);;
						$style->{"outlineColor"} = $this->getDecimalColor($thisColor[0], $thisColor[1], $thisColor[2]);
					}
					if($thisStyle["color"] != NULL){
						//poi al colore normale
						$thisColor = explode(" ", $thisStyle["color"]);;
						//print($thisLayer["layer_name"]." color ".$thisStyle["color"]." aci ".$this->getDecimalColor($thisColor[0], $thisColor[1], $thisColor[2])."\n");
						$style->{"color"} = $this->getDecimalColor($thisColor[0], $thisColor[1], $thisColor[2]);
					}
					if($thisStyle["label_color"] != NULL){
						//poi al colore della label
						$thisColor = explode(" ", $thisStyle["label_color"]);;
						$style->{"labelColor"} = $this->getDecimalColor($thisColor[0], $thisColor[1], $thisColor[2]);
					}					
					if($thisStyle["label_angle"] != NULL){
						if(is_numeric($thisStyle["label_angle"])){
							$style->{"textAngle"} = intval($thisStyle["label_angle"]);
						}else{
							$style->{"fieldTextAngle"} = $thisStyle["label_angle"];
						}
					}
					if($thisStyle["angle"] != NULL){
						if(is_numeric($thisStyle["angle"])){
							$style->{"angle"} = $thisStyle["angle"];
						}else{
							$style->{"fieldAngle"} = $thisStyle["angle"];
						}
					}
					if($thisStyle["pattern_name"] != NULL){
						$style->{"lineType"} = $this->avaliablePattern($thisStyle["pattern_name"]); 
					}				
					if($thisStyle["width"] != NULL){
						if(is_numeric($thisStyle["width"])){
							$style->{"thickness"} = floatval($thisStyle["width"]); 
						}
					}
					
					$label_maxsize = 0;
					$label_minsize = 0;
					$label_size = 0;
					if($thisStyle["label_maxsize"] != NULL){
						if(is_numeric($thisStyle["label_maxsize"])){
							$label_maxsize = floatval($thisStyle["label_maxsize"]); 
						}
					}
					if($thisStyle["label_minsize"] != NULL){
						if(is_numeric($thisStyle["label_minsize"])){
							$label_minsize = floatval($thisStyle["label_minsize"]); 
						}
					}
					if($thisStyle["label_size"] != NULL){
						if(is_numeric($thisStyle["label_size"])){
							$label_size = floatval($thisStyle["label_size"]); 
						}
					}
					if($label_minsize>0){
						$style->{"labelSize"} = $label_minsize;
					} elseif($label_maxsize>0){
						$style->{"labelSize"} = $label_maxsize;
					} elseif($label_size>0){
						$style->{"labelSize"} = $label_size;
					}
					$i++;/**/
					array_push($styles, $style);
				}
				//controllo se il layer deve essere splittato per i propri stili
				if(in_array($thisLayer["layer_name"], $this->dxfSplitLayers)){
					//eseguo lo split dei layer
					foreach($styles as $style){
						$clonedLayer = unserialize(serialize($layer));
						$clonedLayer->{"layerName"} = $clonedLayer->{"layerName"}."_".$style->{"className"};
						//var_dump($style);
						$clonedLayer->{"styles"} = [$style];
						array_push($layers, $clonedLayer);
					}
				}else{
					//inserisco il layer singolo
					$layer->{"styles"} = $styles;
					array_push($layers, $layer);
				}
				
			}
		}
		if($this->debug){
			$this->log('<pre>');
			$this->log(json_encode($layers));
			$this->log('</pre>');
			file_put_contents($this->logPath, $this->logTxt, FILE_APPEND);
		}
		return $layers;
	}

	/*
	* Elenco dei tipi di linea supportati
	*/
	public static $lineTypes = array(
		'continuous',
		'dash_dot_dot',
		'dash_dot',
		'linea_tratt',
		'dot',
		'linea_puntinata_10',
		'nascosta'
	);
	
	/**
	* Verifica se un tipo di linea è supportato altrmenti restituisce il tipo continuous
	* @param string $pattern
	* @return string
	*/
	public static function avaliablePattern($pattern){
		return $pattern;
		if(in_array(strtolower($pattern), SELF::$lineTypes)){
			return $pattern;
		}else{
			return "Continuous";
		}
	}
	

	/*
	* Elenco dei colori Autocad convertiti in RGB
	*/
	public static $colors = [
		0 => [0,0,0],
		1 => [255,0,0],
		2 => [255,255,0],
		3 => [0,255,0],
		4 => [0,255,255],
		5 => [0,0,255],
		6 => [255,0,255],
		7 => [255,255,255],
		8 => [65,65,65 ],
		9 => [128,128,128],
		10 => [255,0,0  ],
		11 => [255,170,170],
		12 => [189,0,0  ],
		13 => [189,126,126],
		14 => [129,0,0  ],
		15 => [129,86,86 ],
		16 => [104,0,0  ],
		17 => [104,69,69 ],
		18 => [79,0,0  ],
		19 => [79,53,53 ],
		20 => [255,63,0  ],
		21 => [255,191,170],
		22 => [189,46,0  ],
		23 => [189,141,126],
		24 => [129,31,0  ],
		25 => [129,96,86 ],
		26 => [104,25,0  ],
		27 => [104,78,69 ],
		28 => [79,19,0  ],
		29 => [79,59,53 ],
		30 => [255,127,0  ],
		31 => [255,212,170],
		32 => [189,94,0  ],
		33 => [189,157,126],
		34 => [129,64,0  ],
		35 => [129,107,86 ],
		36 => [104,52,0  ],
		37 => [104,86,69 ],
		38 => [79,39,0  ],
		39 => [79,66,53 ],
		40 => [255,191,0  ],
		41 => [255,234,170],
		42 => [189,141,0  ],
		43 => [189,173,126],
		44 => [129,96,0  ],
		45 => [129,118,86 ],
		46 => [104,78,0  ],
		47 => [104,95,69 ],
		48 => [79,59,0  ],
		49 => [79,73,53 ],
		50 => [255,255,0  ],
		51 => [255,255,170],
		52 => [189,189,0  ],
		53 => [189,189,126],
		54 => [129,129,0  ],
		55 => [129,129,86 ],
		56 => [104,104,0  ],
		57 => [104,104,69 ],
		58 => [79,79,0  ],
		59 => [79,79,53 ],
		60 => [191,255,0  ],
		61 => [234,255,170],
		62 => [141,189,0  ],
		63 => [173,189,126],
		64 => [96,129,0  ],
		65 => [118,129,86 ],
		66 => [78,104,0  ],
		67 => [95,104,69 ],
		68 => [59,79,0  ],
		69 => [73,79,53 ],
		70 => [127,255,0  ],
		71 => [212,255,170],
		72 => [94,189,0  ],
		73 => [157,189,126],
		74 => [64,129,0  ],
		75 => [107,129,86 ],
		76 => [52,104,0  ],
		77 => [86,104,69 ],
		78 => [39,79,0  ],
		79 => [66,79,53 ],
		80 => [63,255,0  ],
		81 => [191,255,170],
		82 => [46,189,0  ],
		83 => [141,189,126],
		84 => [31,129,0  ],
		85 => [96,129,86 ],
		86 => [25,104,0  ],
		87 => [78,104,69 ],
		88 => [19,79,0  ],
		89 => [59,79,53 ],
		90 => [0,255,0  ],
		91 => [170,255,170],
		92 => [0,189,0  ],
		93 => [126,189,126],
		94 => [0,129,0  ],
		95 => [86,129,86 ],
		96 => [0,104,0  ],
		97 => [69,104,69 ],
		98 => [0,79,0  ],
		99 => [53,79,53 ],
		100 => [0,255,63 ],
		101 => [170,255,191],
		102 => [0,189,46 ],
		103 => [126,189,141],
		104 => [0,129,31 ],
		105 => [86,129,96 ],
		106 => [0,104,25 ],
		107 => [69,104,78 ],
		108 => [0,79,19 ],
		109 => [53,79,59 ],
		110 => [0,255,127],
		111 => [170,255,212],
		112 => [0,189,94 ],
		113 => [126,189,157],
		114 => [0,129,64 ],
		115 => [86,129,107],
		116 => [0,104,52 ],
		117 => [69,104,86 ],
		118 => [0,79,39 ],
		119 => [53,79,66 ],
		120 => [0,255,191],
		121 => [170,255,234],
		122 => [0,189,141],
		123 => [126,189,173],
		124 => [0,129,96 ],
		125 => [86,129,118],
		126 => [0,104,78 ],
		127 => [69,104,95 ],
		128 => [0,79,59 ],
		129 => [53,79,73 ],
		130 => [0,255,255],
		131 => [170,255,255],
		132 => [0,189,189],
		133 => [126,189,189],
		134 => [0,129,129],
		135 => [86,129,129],
		136 => [0,104,104],
		137 => [69,104,104],
		138 => [0,79,79 ],
		139 => [53,79,79 ],
		140 => [0,191,255],
		141 => [170,234,255],
		142 => [0,141,189],
		143 => [126,173,189],
		144 => [0,96,129],
		145 => [86,118,129],
		146 => [0,78,104],
		147 => [69,95,104],
		148 => [0,59,79 ],
		149 => [53,73,79 ],
		150 => [0,127,255],
		151 => [170,212,255],
		152 => [0,94,189],
		153 => [126,157,189],
		154 => [0,64,129],
		155 => [86,107,129],
		156 => [0,52,104],
		157 => [69,86,104],
		158 => [0,39,79 ],
		159 => [53,66,79 ],
		160 => [0,63,255],
		161 => [170,191,255],
		162 => [0,46,189],
		163 => [126,141,189],
		164 => [0,31,129],
		165 => [86,96,129],
		166 => [0,25,104],
		167 => [69,78,104],
		168 => [0,19,79 ],
		169 => [53,59,79 ],
		170 => [0,0,255],
		171 => [170,170,255],
		172 => [0,0,189],
		173 => [126,126,189],
		174 => [0,0,129],
		175 => [86,86,129],
		176 => [0,0,104],
		177 => [69,69,104],
		178 => [0,0,79 ],
		179 => [53,53,79 ],
		180 => [63,0,255],
		181 => [191,170,255],
		182 => [46,0,189],
		183 => [141,126,189],
		184 => [31,0,129],
		185 => [96,86,129],
		186 => [25,0,104],
		187 => [78,69,104],
		188 => [19,0,79 ],
		189 => [59,53,79 ],
		190 => [127,0,255],
		191 => [212,170,255],
		192 => [94,0,189],
		193 => [157,126,189],
		194 => [64,0,129],
		195 => [107,86,129],
		196 => [52,0,104],
		197 => [86,69,104],
		198 => [39,0,79 ],
		199 => [66,53,79 ],
		200 => [191,0,255],
		201 => [234,170,255],
		202 => [141,0,189],
		203 => [173,126,189],
		204 => [96,0,129],
		205 => [118,86,129],
		206 => [78,0,104],
		207 => [95,69,104],
		208 => [59,0,79 ],
		209 => [73,53,79 ],
		210 => [255,0,255],
		211 => [255,170,255],
		212 => [189,0,189],
		213 => [189,126,189],
		214 => [129,0,129],
		215 => [129,86,129],
		216 => [104,0,104],
		217 => [104,69,104],
		218 => [79,0,79 ],
		219 => [79,53,79 ],
		220 => [255,0,191],
		221 => [255,170,234],
		222 => [189,0,141],
		223 => [189,126,173],
		224 => [129,0,96 ],
		225 => [129,86,118],
		226 => [104,0,78 ],
		227 => [104,69,95 ],
		228 => [79,0,59 ],
		229 => [79,53,73 ],
		230 => [255,0,127],
		231 => [255,170,212],
		232 => [189,0,94 ],
		233 => [189,126,157],
		234 => [129,0,64 ],
		235 => [129,86,107],
		236 => [104,0,52 ],
		237 => [104,69,86 ],
		238 => [79,0,39 ],
		239 => [79,53,66 ],
		240 => [255,0,63 ],
		241 => [255,170,191],
		242 => [189,0,46 ],
		243 => [189,126,141],
		244 => [129,0,31 ],
		245 => [129,86,96 ],
		246 => [104,0,25 ],
		247 => [104,69,78 ],
		248 => [79,0,19 ],
		249 => [79,53,59 ],
		250 => [51,51,51 ],
		251 => [80,80,80 ],
		252 => [105,105,105],
		253 => [130,130,130],
		254 => [190,190,190],
		255 => [255,255,255]
		];
	
	
	
	/**
     * Converte un colore RGB in decimal
     * @param int $r
     * @param int $g
     * @param int $b
     * @return int
     */
	public static function getDecimalColor($r, $g, $b)
    {
		//return $r.','.$g.','.$b;
		return (256 * 256 * $r) + (256 * $g) + $b;
	}
	
	/**
     * Converte un colore RGB in uno ACI
     * @param int $r
     * @param int $g
     * @param int $b
     * @return int
     */
    public static function getAci($r, $g, $b)
    {
        $dist = 1000;
        $aci = 0;
        foreach (SELF::$colors as $key => $color) {
			if($r == $g && $g == $b){
				$red = pow(((intval($r) - $color[0])), 2);
				$green = pow((intval($g) - $color[1]), 2);
				$blue = pow((intval($b) - $color[2]), 2);
			}else{
			// sqrt(((r - r1) * .299)^2 + ((g - g1) * .587)^2 + ((b - b1) * .114)^2)
				$red = pow(((intval($r) - $color[0]) * 0.299), 2);
				$green = pow((intval($g) - $color[1]) * 0.587, 2);
				$blue = pow((intval($b) - $color[2]) * 0.114, 2);
			}
			
            $c = sqrt($red + $green + $blue);
            if ($c < $dist) {
                $dist = $c;
                $aci = $key;
            }
        }
        return $aci;
    }
	
}

?>
