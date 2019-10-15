<?php
/******************************************************************************
*
* Purpose: Factory per la generazione dei DXF

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
*
*
* La classe richiede obbligatoriamente il file di configurazione con le informazioni
* necessare alla generazione del Dxf. Il formato del file JSON di configurazione �
* presente nel file config_extraction_schema.json
*
* Il processo di generazione del DXF viene elaborato dalla funzione CreateDxf
* la quale esegue le seguenti funzioni
*
* initDxf         Caricamento template e verifica input/oputput su disco
* addLayers       Creazione dei livelli
* addEntities     Creazione delle entit�
* mergeDxf        Assemblaggio DXF
* writeDxf        Scrittura DXF
*
******************************************************************************/

include_once('dxfErrors.php');
include_once('dxfInterfaces.php');
include_once('lexerParser.php');
include_once('dxfCode.php');

/**
*	Classe per la generazione della di un file DXF
*
*
*/
class dxfFactory implements iDxfFactory {
	private $handle = 0; //contatore univoco per le entit� del dxf
	
	private $dxfCode = null; //classe per la gestione del codice DXF
	
	//public $rete = ""; //rete attualmente in uso
	private $outputFile = ""; //file di destinazione
	private $outputFilePoints = ""; //file di destinazione punti. Utilizzato in alternativa con $entPoints
	private $outputFileLines = ""; //file di destinazione linee. Utilizzato in alternativa con $entLines
	private $outputFileHatches = ""; //file di destinazione hatch. Utilizzato in alternativa con $entHatches
	
	private $dxf; //array con le linee del DXF
	private $entities; //array con le linee del DXF
	private $layers; //array con le linee dei layers
	private $entHatches; //array con i riempimenti del DXF
	private $entLines; //array con le linee del DXF
	private $entPoints; //array con punti simboli e testi	
	
	private $handleHatches = 2000000; //offset degli handle per il draworder delle etitities
	private $handleLines = 3000000; //offset degli handle per il draworder delle etitities	
	private $handlePoints = 4000000; //offset degli handle per il draworder delle etitities	
	
	public $debug = False; //abilita le informazioni di debug
	public $dummy = NULL; //abilita le geometrie dummy
	public $drawHatches = False; //abilita disegno dei riempimenti
	
	public $configExtraction = NULL;
	public $configExtractionStr = NULL;
	
	public $defaultSize = 0.4;
	public $defaultColor = (256 * 256) * 255 + (256 * 255) + 255;
	
	//eliminazione delle geometrie doppie o indesiderate
	public $excludeGeometryLayers = array(
		
	);
	
	//eliminazione dei testi doppi indesiderati
	public $excludeTextLayers = array(
		
	);
	
	public $enableLineThickness = True;
	public $enableColors = True;
	
	public $dxfLineScale = 0.15;
	public $dxfTextScaleMultiplier = 1;
	public $dxfLabelScaleMultiplier = 1;
	public $dxfInsertScaleMultiplier = 1;
	
	public $dxfEnableTemplateContesti = False;
	public $dxfTemplateContestiPath = null;
	public $dxfTemplateContesti = null;
	
	public $parserExpression;
	
	public $logTxt = ""; //log per debugging
	
	/**
	* Crea l'oggetto per la generazione del DXF
	*
	* @param string $configJson File json di configurazione
	*
	* @return void
	*/
	public function __construct($configJson, $dxfLogPath) {
		
		$this->logPath = $dxfLogPath;
		
		$this->handle = 100000; //parto da un offset per evitare conflitti con elementi del template
		$this->configExtractionStr = $configJson;
		$this->entities = array(); //TODO ELIMINARE
		$this->entHatches = array();
		$this->entPoints = array();
		$this->entLines = array();	
		$this->layers = array();
		
		//eseguo il parsing della configurazione
		$this->configExtraction = json_decode($configJson);
	
		//controllo della configurazione
		if(is_null($this->configExtraction->{'templateFile'})) throw new Exception(dxfErrors::template_non_configurato);
		//if(empty($this->configExtraction->{'rete'})) throw new Exception(dxfErrors::rete_non_configurato);
		if(is_null($this->configExtraction->{'minX'})) throw new Exception(dxfErrors::bbox_undefined);
		if(is_null($this->configExtraction->{'minY'})) throw new Exception(dxfErrors::bbox_undefined);
		if(is_null($this->configExtraction->{'maxX'})) throw new Exception(dxfErrors::bbox_undefined);
		if(is_null($this->configExtraction->{'maxY'})) throw new Exception(dxfErrors::bbox_undefined);
		if(is_null($this->configExtraction->{'epsg'})) throw new Exception(dxfErrors::epsg_undefined);
		if(is_null($this->configExtraction->{'layers'})) throw new Exception(dxfErrors::layers_undefined);
		if(is_null($this->configExtraction->{'themes'})) throw new Exception(dxfErrors::layers_undefined);
		
		if(!is_null($this->configExtraction->{'dxfEnableTemplateContesti'})) $this->dxfEnableTemplateContesti = $this->configExtraction->{'dxfEnableTemplateContesti'};
		if(!is_null($this->configExtraction->{'dxfTemplateContestiPath'})) $this->dxfTemplateContestiPath = $this->configExtraction->{'dxfTemplateContestiPath'};
		if(!is_null($this->configExtraction->{'dxfDrawHatches'})) $this->drawHatches = $this->configExtraction->{'dxfDrawHatches'};
		
		if(!is_null($this->configExtraction->{'dxfEnableColors'})) $this->enableColors = $this->configExtraction->{'dxfEnableColors'};
		if(!is_null($this->configExtraction->{'dxfEnableLineThickness'})) $this->enableLineThickness = $this->configExtraction->{'dxfEnableLineThickness'};
		if(!is_null($this->configExtraction->{'dxfLineScale'})) $this->dxfLineScale = $this->configExtraction->{'dxfLineScale'};
		
		if(!is_null($this->configExtraction->{'dxfTextScaleMultiplier'})) $this->dxfTextScaleMultiplier = $this->configExtraction->{'dxfTextScaleMultiplier'};
		if(!is_null($this->configExtraction->{'dxfLabelScaleMultiplier'})) $this->dxfLabelScaleMultiplier = $this->configExtraction->{'dxfLabelScaleMultiplier'};
		if(!is_null($this->configExtraction->{'dxfInsertScaleMultiplier'})) $this->dxfInsertScaleMultiplier = $this->configExtraction->{'dxfInsertScaleMultiplier'};

		//TODO Unit test required
		// print("dxfTextScaleMultiplier ".$this->dxfTextScaleMultiplier);
		// print("<br/>dxfLabelScaleMultiplier ".$this->dxfLabelScaleMultiplier);
		// print("<br/>dxfInsertScaleMultiplier ".$this->dxfInsertScaleMultiplier);
		// print("<br/>dxfLineScale ".$this->dxfLineScale);

		//creo il parser per le espressioni
		$this->parserExpression = new Parser();
		
		//creo la classe per la gestione del codice
		$this->dxfCode = new dxfCode($this, $this->dxfLineScale, $this->enableColors, $this->enableLineThickness, $this->drawHatches);
		
		//abilito il template dei contesti
		if($this->dxfEnableTemplateContesti){
			$this->dxfTemplateContesti = json_decode(file_get_contents($this->dxfTemplateContestiPath));
			if(is_null($this->dxfTemplateContesti)){
				$this->log("File dei contesti non valido");
			}
		}
	}
		
	public function getNextHandle(){
		return $this->handle++;
	}
	
	public function getNextHandlePoint(){
		$this->handle++;
		return  $this->handle + $this->handlePoints;
	}
	public function getNextHandleLine(){
		$this->handle++;
		return $this->handle + $this->handleLines;
	}
	public function getNextHandleHatch(){
		$this->handle++;
		return $this->handle + $this->handleHatches;
	}
	
	
	public function log($message){
		if($this->debug){
			$now = DateTime::createFromFormat('U.u', number_format(microtime(true), 6, '.', ''));
			file_put_contents($this->logPath, PHP_EOL.$now->format('Y-m-d H:i:s.u')." ".$message, FILE_APPEND);
			//$this->logTxt .= date('Y-m-d H:i:s')." ".$message."\n";
		}
		
	}
	
	
	
	/**
	* Funzione di debug
	*
	*/
	private function info(){
		$this->log( "handle: ".$this->handle."\n");
		$this->log( "outputFile: ".$this->outputFile."\n");
		//print "dxf:".$this->dxf."\n";
		$this->log( "HEADER: ".$this->startOfSection("HEADER")."-".$this->endOfSection("HEADER")."\n");
		$this->log( "CLASSES: ".$this->startOfSection("CLASSES")."-".$this->endOfSection("CLASSES")."\n");
		$this->log( "TABLES: ".$this->startOfSection("TABLES")."-".$this->endOfSection("TABLES")."\n");
		$this->log( "BLOCKS: ".$this->startOfSection("BLOCKS")."-".$this->endOfSection("BLOCKS")."\n");
		$this->log( "ENTITIES: ".$this->startOfSection("ENTITIES")."-".$this->endOfSection("ENTITIES")."\n");
		$this->log( "OBJECTS: ".$this->startOfSection("OBJECTS")."-".$this->endOfSection("OBJECTS")."\n");
		$this->log( "LAYERS: ".$this->startOfTable("LAYER")."-".$this->endOfTable("LAYER")."\n");
		return NULL;
	}
	
	/**
	* Imposta manualmente l'handle delle entit�
	*
	* @param int $h Nuova handle
	*
	* @return void
	*/
	private function setHandle($h){
		if ($h <= $this->handle){
			throw new Exception(dxfErrors::handle_invalid);
		}
		$this->handle = $h;
	}
	
	/**
	* Ricerca la posizione iniziale di una section all'interno del DXF
	*
	* @param string $section Identificativo della section da cercare
	*
	* @return int
	*/
	private function startOfSection($section){
		//$sectionStr = "  0";//."\n";
		//$sectionStr."SECTION"."\n";
		//$sectionStr."  2"."\n";
		//$sectionStr.strtoupper($section);
		for($i = 0; $i < count($this->dxf); $i++){
			if($this->dxf[$i] == "SECTION"){
				if($this->dxf[$i + 2] == strtoupper($section)){
					return $i -1;
				}
			}
		}
		return -1;
	}
	
	/**
	* Ricerca la posizione iniziale di una section all'interno del DXF
	*
	* @param string $section Identificativo della section da cercare
	*
	* @return int
	*/
	private function endOfSection($section){
		$currentSection = false; //controllo se la table � quella corrente
		for($i = 0; $i < count($this->dxf); $i++){
			if($this->dxf[$i] == "SECTION"){
				if($this->dxf[$i + 2] == strtoupper($section)){
					$currentSection = true;
				}
			}
			if($this->dxf[$i] == "ENDSEC" && $currentSection){
				return $i -1;
			}
		}
		return -1;
	}
	
	
	/**
	* Ricerca la posizione iniziale di una table all'interno del DXF
	*
	* @param string $table Identificativo della table da cercare
	*
	* @return int
	*/
	private function startOfTable($table){
		for($i = 0; $i < count($this->dxf); $i++){
			if($this->dxf[$i] == "TABLE"){
				if($this->dxf[$i + 2] == strtoupper($table)){
					return $i -1;
				}
			}
		}
		return -1;
	}
	
	/**
	* Ricerca la posizione iniziale di una table all'interno del DXF
	*
	* @param string $table Identificativo della table da cercare
	*
	* @return int
	*/
	private function endOfTable($table){
		$currentTable = false; //controllo se la table � quella corrente
		for($i = 0; $i < count($this->dxf); $i++){
			if($this->dxf[$i] == "TABLE"){
				if($this->dxf[$i + 2] == strtoupper($table)){
					$currentTable = true;
				}
			}
			if($this->dxf[$i] == "ENDTAB" && $currentTable){
				return $i -1;
			}
		}
		return -1;
	}
	
	
	private function setDxfProperty($prop, $num, $value){
		for($i = 0; $i < count($this->dxf); $i++){
			if($this->dxf[$i] == $prop){
				for($k = $i; $k < count($this->dxf); $k++){
					if(trim($this->dxf[$k]) == $num){
						$this->dxf[$k + 1] = $value;
						break;
					}
				}
				break;
			}
		}
		return -1;
	}
	
	/**
	* Inizializza il DXF per le operazioni di lettura/scrittura
	*
	* @return void
	*/
	private function initDxf(){
		//verifico che il file sia accessibile
		if(file_exists($this->outputFile)){
			if(!unlink($this->outputFile)){
				throw new Exception(dxfErrors::file_dest_invalid);
			}
		}
		//Apertura del template
		$this->dxf = file($this->configExtraction->{'templateFile'}, FILE_IGNORE_NEW_LINES);
		
	}

	/**
	* Esegue il merge di tutti gli array nelle relative sections
	*
	* @return void
	*/
	private function mergeDxf(){
		
		//eseguo il merge dei layers
		//se non esiste la sezione la aggiungo
		if($this->startOfTable("LAYER") < 0){
			
		}
		else{
			$part1 = array_slice($this->dxf, 0, $this->endOfTable("LAYER"));
			$part2 = array_slice($this->dxf, $this->endOfTable("LAYER"));
			$this->dxf = array_merge($part1, $this->layers, $part2);
		}

		//eseguo il merge delle entities
		$part1 = array_slice($this->dxf, 0, $this->endOfSection("ENTITIES"));
		$part2 = array_slice($this->dxf, $this->endOfSection("ENTITIES"));
		//print(count($this->dxf) - $this->endOfSection("ENTITIES"));
		$this->dxf = array_merge($part1, $this->entHatches, $this->entLines, $this->entPoints, $part2);
				
	}
	
	/**
	* Scrive il DXF sul disco
	*
	* @return void
	*/
	private function writeDxf($outputFile){
		
		//eseguo il merge dei layers
		//se non esiste la sezione la aggiungo
		if($this->startOfTable("LAYER") < 0){
			
		}
		else{
			$part1 = array_slice($this->dxf, 0, $this->endOfTable("LAYER"));
			$part2 = array_slice($this->dxf, $this->endOfTable("LAYER"));
			$this->dxf = array_merge($part1, $this->layers, $part2);
		}

		//eseguo il merge delle entities
		$part1 = array_slice($this->dxf, 0, $this->endOfSection("ENTITIES"));
		$part2 = array_slice($this->dxf, $this->endOfSection("ENTITIES"));
		
		file_put_contents($outputFile, implode(PHP_EOL, $part1));
		file_put_contents($outputFile, PHP_EOL, FILE_APPEND);
		
		//scrittura dei file con i punti
		$out = fopen($this->outputFile, "a");
		$in = fopen($this->outputFilePoints, "r");
		while ($line = fgets($in)){	
			fwrite($out, $line);
		}
		fclose($in);
		$in = fopen($this->outputFileLines, "r");
		while ($line = fgets($in)){	
			fwrite($out, $line);
		}
		fclose($in);
		$in = fopen($this->outputFileHatches, "r");
		while ($line = fgets($in)){	
			fwrite($out, $line);
		}
		fclose($in);
		fclose($out);
		//elimino i file di cache
		unlink($this->outputFilePoints);
		unlink($this->outputFileLines);
		unlink($this->outputFileHatches);
		
		//file_put_contents($outputFile, implode(PHP_EOL, $this->entHatches), FILE_APPEND);
		//file_put_contents($outputFile, PHP_EOL, FILE_APPEND);
		//file_put_contents($outputFile, implode(PHP_EOL, $this->entLines), FILE_APPEND);
		//file_put_contents($outputFile, PHP_EOL, FILE_APPEND);
		//file_put_contents($outputFile, implode(PHP_EOL, $this->entPoints), FILE_APPEND);
		//file_put_contents($outputFile, PHP_EOL, FILE_APPEND);
		file_put_contents($outputFile, implode(PHP_EOL, $part2), FILE_APPEND);
	}
	
	/**
	* Genera un DXF in base alla configurazione caricata
	*
	* @param string $fileDest Percorso del file di destinazione 
	*
	* @return bool
	*/
	public function createDxf($fileDest = NULL){
		//setto il file di destinazione
		$this->outputFile = $fileDest;
		if(isset($this->outputFile)){
			//creo i file per le singole entit�
			$path_parts = pathinfo($this->outputFile);
			$this->outputFilePoints =  join('/',  array($path_parts['dirname'], $path_parts['filename']."_points".".txt")); 
			$this->outputFileLines =  join('/',  array($path_parts['dirname'], $path_parts['filename']."_lines".".txt")); 
			$this->outputFileHatches =  join('/',  array($path_parts['dirname'], $path_parts['filename']."_hatches".".txt")); 
		}
		
		//inizializzo il file
		$this->initDxf();
		
		//ricavo il filtro per il WFS
		$filterEnvelope = "&FILTER=%3Cogc:Filter%20xmlns:ogc=%22http://www.opengis.net/ogc%22%3E%3Cogc:BBOX%3E%3Cogc:PropertyName%3Egeom%3C/ogc:PropertyName%3E%3Cgml:Envelope%20xmlns:gml=%22http://www.opengis.net/gml%22%3E%3Cgml:lowerCorner%3E".$this->configExtraction->{'minX'}."%20".$this->configExtraction->{'minY'}."%3C/gml:lowerCorner%3E%3Cgml:upperCorner%3E".$this->configExtraction->{'maxX'}."%20".$this->configExtraction->{'maxY'}."%3C/gml:upperCorner%3E%3C/gml:Envelope%3E%3C/ogc:BBOX%3E%3C/ogc:Filter%3E";
		
		//aggiungo il layer con l'extent
		$this->layers = array_merge($this->layers, $this->dxfCode->addLayer("boundingbox", 2, NULL, NULL));
		
		//aggiungo il layer del template contesti se utilizzato
		if($this->dxfEnableTemplateContesti){
			foreach ($this->configExtraction->{'themes'} as $theme){
				foreach ($this->dxfTemplateContesti as $themeContesto){
					if ($themeContesto->{"themeName"} == $theme){
						foreach ($themeContesto->{"layers"} as $tLayer){
							$this->layers = array_merge($this->layers, $this->dxfCode->addLayer($tLayer->{"layerNameDxf"}, $tLayer->{"style"}->{"color"}, NULL, $tLayer->{"style"}->{"lineType"}));
						}
						$this->layers = array_merge($this->layers, $this->dxfCode->addLayer($themeContesto->{"layer_default"}->{"layerNameDxf"}, $themeContesto->{"layer_default"}->{"style"}->{"color"}, NULL, $themeContesto->{"layer_default"}->{"style"}->{"lineType"}));
						$this->layers = array_merge($this->layers, $this->dxfCode->addLayer($themeContesto->{"layer_default_blocchi"}->{"layerNameDxf"}, $themeContesto->{"layer_default_blocchi"}->{"style"}->{"color"}, NULL, $themeContesto->{"layer_default_blocchi"}->{"style"}->{"lineType"}));
						$this->layers = array_merge($this->layers, $this->dxfCode->addLayer($themeContesto->{"layer_default_testi"}->{"layerNameDxf"}, $themeContesto->{"layer_default_testi"}->{"style"}->{"color"}, NULL, $themeContesto->{"layer_default_testi"}->{"style"}->{"lineType"}));
					}
				}
			}
		}
		
		//Ciclo sui layer
		foreach ($this->configExtraction->{'layers'} as $dLayer){
			//aggiungo i layer
			if(empty($dLayer->{'wfs'}) || empty($dLayer->{'layerName'})){
				continue;
			}
			//aggiungo l'envelope
			$wfsUrl = $dLayer->{'wfs'}.$filterEnvelope;
			$geojson = $this->getFeatures($wfsUrl);
			if(!is_null($geojson)){
				//$geometryType = $dLayer->{'geometryType'};
				//se ci sono delle feature aggiungo il layer
				if(count($geojson->{'features'}) > 0){
					if(!isset($dLayer->{'lineType'})){
						if(count($dLayer->{'styles'}) > 0){
							$dLayer->{'lineType'} = $dLayer->{'styles'}[0]->{'lineType'}; //assegno il primo valore disponibile
						}else{
							$dLayer->{'lineType'} = NULL;
						}
					}
					if(!$this->dxfEnableTemplateContesti){
						$this->layers = array_merge($this->layers, $this->dxfCode->addLayer($dLayer->{'layerName'}, NULL, $dLayer->{'color'}, $this->getLineStyleName($dLayer->{'lineType'})));
					}
				}
				foreach ($geojson->{'features'} as $feature){
					$coords = $feature->{'geometry'}->{'coordinates'};
					//$coords = $clip->clip($coords);
					$props = $feature->{'properties'};
					//ricavo lo style relativo
					$stylesFeature = $this->getStyles($props, $dLayer->{'styles'});
					foreach ($stylesFeature as $style){									
						$this->drawFeature($this->getFeatureStylebyLayer($style, $dLayer, $feature->{'geometry'}->{'type'}), $dLayer, $this->getLayerNamebyLayer($dLayer, $feature->{'geometry'}->{'type'}), $feature);
					}
				}
			}
		}
		
		//aggiungo il rettangolo di estrazione
			$coords = [
				[$this->configExtraction->{'minX'}, $this->configExtraction->{'minY'}, 0],
				[$this->configExtraction->{'minX'}, $this->configExtraction->{'maxY'}, 0],
				[$this->configExtraction->{'maxX'}, $this->configExtraction->{'maxY'}, 0],
				[$this->configExtraction->{'maxX'}, $this->configExtraction->{'minY'}, 0],
				[$this->configExtraction->{'minX'}, $this->configExtraction->{'minY'}, 0],
			];
			$this->dxfCode->addPolygon("boundingbox", $coords, 1, NULL, "".((256 * 256 * 255) + (256 * 255)) , NULL);
			//setto l'extent
			//$this->setDxfProperty("\$EXTMAX", "10", $this->configExtraction->{'maxX'});
			//$this->setDxfProperty("\$EXTMAX", "20", $this->configExtraction->{'maxY'});
			//$this->setDxfProperty("\$EXTMIN", "10", $this->configExtraction->{'minX'});
			//$this->setDxfProperty("\$EXTMIN", "20", $this->configExtraction->{'minY'});
			//$this->setDxfProperty("AcDbViewportTableRecord", "10", $this->configExtraction->{'minX'});
			//$this->setDxfProperty("AcDbViewportTableRecord", "20", $this->configExtraction->{'minY'});
			//$this->setDxfProperty("AcDbViewportTableRecord", "11", $this->configExtraction->{'maxX'});
			//$this->setDxfProperty("AcDbViewportTableRecord", "21", $this->configExtraction->{'maxY'});
			$this->setDxfProperty("AcDbViewportTableRecord", "12", $this->configExtraction->{'minX'});
			$this->setDxfProperty("AcDbViewportTableRecord", "22", $this->configExtraction->{'minY'});
			$this->setDxfProperty("AcDbViewportTableRecord", "40", ($this->configExtraction->{'maxX'} - $this->configExtraction->{'minX'}));
			//$this->setDxfProperty("AcDbViewportTableRecord", "41", ($this->configExtraction->{'maxY'} - $this->configExtraction->{'minY'})/10);
			$this->setDxfProperty("AcDbViewportTableRecord", "41", 5);
		
		//Caricamento delle features	
		//verifico se sono richieste le informazioni di debug
		if($this->debug){
			$this->info();
		}
		//verifico se sono richieste delle geometrie dummy
		if($this->dummy){
			$this->addDummy();
		}
				
		//se il file � fornito lo salvo su disco
		if(isset($this->outputFile)){
			$this->writeDxf($this->outputFile);
			return;
		}
			
		if($this->debug){
			file_put_contents($this->logPath, $this->logTxt, FILE_APPEND);
		}
		
		//assemblaggio del dxf se deve essere restituito in download
		$this->mergeDxf();
		return implode(PHP_EOL, $this->dxf);
		
	}
	
	/**
	* Return the correct style based on current cofiguration
	* @param object $style Style object
	* @param object $dLayer Layer object
	* @return void
	*/
	public function getFeatureStylebyLayer($style, $dLayer, $geometryType){
		$featureStyle = $style;
		if($this->dxfEnableTemplateContesti){
			$themeFound = False;
			foreach ($this->dxfTemplateContesti as $theme){
				$layerFound = False;
				if ($dLayer->{"themeName"} == $theme->{"themeName"}){
					$themeFound = True;
					foreach ($theme->{"layers"} as $tLayer){
						if($this->stringArrayCheck($dLayer->{"layerName"}, $tLayer->{"layerNames"})){
							//definizione dello stile custom
							$featureStyle->{"color"} = $tLayer->{"style"}->{"color"};
							$featureStyle->{"lineType"} = $tLayer->{"style"}->{"lineType"};
							if(!is_null($tLayer->{"style"}->{"labelSize"})) $featureStyle->{"labelSize"} = $tLayer->{"style"}->{"labelSize"};
							$layerFound = True;
							break;
						}
					}
					if(!$layerFound){
						//in base alla geometria lo mando su layer di default
						switch (strtolower($geometryType)) {
						case "text":
							$featureStyle->{"color"} = $theme->{"layer_default_testi"}->{"style"}->{"color"};
							$featureStyle->{"lineType"} = $theme->{"layer_default_testi"}->{"style"}->{"lineType"};
							if(!is_null($theme->{"layer_default_testi"}->{"style"}->{"labelSize"})) $featureStyle->{"labelSize"} = $theme->{"layer_default_testi"}->{"style"}->{"labelSize"};
							break;
						case "insert":
							$featureStyle->{"color"} = $theme->{"layer_default_blocchi"}->{"style"}->{"color"};
							$featureStyle->{"lineType"} = $theme->{"layer_default_blocchi"}->{"style"}->{"lineType"};
							if(!is_null($theme->{"layer_default_testi"}->{"style"}->{"labelSize"})) $featureStyle->{"labelSize"} = $theme->{"layer_default_testi"}->{"style"}->{"labelSize"};
							break;
						case "polyline":
						case "linestring":
						case "multilinestring":
						case "polygon":
						case "multipolygon":
						case "point":
						default:
							$featureStyle->{"color"} = $theme->{"layer_default"}->{"style"}->{"color"};
							$featureStyle->{"lineType"} = $theme->{"layer_default"}->{"style"}->{"lineType"};
							if(!is_null($theme->{"layer_default_testi"}->{"style"}->{"labelSize"})) $featureStyle->{"labelSize"} = $theme->{"layer_default_testi"}->{"style"}->{"labelSize"};
							break;
						}
					}
				}
			}
		}
		return $featureStyle;
	}
		
	/**
	* Returns the correct insert autocad layer name based on current cofiguration
	* @param object $dLayer Layer object
	* @return void
	*/
	public function getInsertLayerNamebyLayer($dLayer){
		$layerName = "";
		if($this->dxfEnableTemplateContesti){
			$themeFound = False;
			foreach ($this->dxfTemplateContesti as $theme){
				if ($dLayer->{"themeName"} == $theme->{"themeName"}){
					$themeFound = True;
					$layerName = $theme->{"layer_default_blocchi"}->{"layerNameDxf"};	
				}
			}
			if(!$themeFound){ //se la feature non ha tema la disegno in maniera standard sullo 0
				$layerName = "0";
			}
		}else{
			//disegno standard
			$layerName = $dLayer->{"layerName"};
		}
		return $layerName;
	}
	
	/**
	* Returns the correct annotation autocad layer name based on current cofiguration
	* @param object $dLayer Layer object
	* @return void
	*/
	public function getAnnotationLayerNamebyLayer($dLayer){
		$layerName = "";
		if($this->dxfEnableTemplateContesti){
			$themeFound = False;
			foreach ($this->dxfTemplateContesti as $theme){
				if ($dLayer->{"themeName"} == $theme->{"themeName"}){
					$themeFound = True;
					$layerName = $theme->{"layer_default_testi"}->{"layerNameDxf"};	
				}
			}
			if(!$themeFound){ //se la feature non ha tema la disegno in maniera standard sullo 0
				$layerName = "0";
			}
		}else{
			//disegno standard
			$layerName = $dLayer->{"layerName"};
		}
		return $layerName;
	}

	/**
	* Returns the correct autocad layer name based on current cofiguration
	* @param object $dLayer Layer object
	* @param string $geometryType Geometry Type
	* @return void
	*/
	public function getLayerNamebyLayer($dLayer, $geometryType){
		$layerName = "";
		if($this->dxfEnableTemplateContesti){
			$themeFound = False;
			foreach ($this->dxfTemplateContesti as $theme){
				$layerFound = False;
				if ($dLayer->{"themeName"} == $theme->{"themeName"}){
					$themeFound = True;
					foreach ($theme->{"layers"} as $tLayer){
						if($this->stringArrayCheck($dLayer->{"layerName"}, $tLayer->{"layerNames"})){
							//definizione dello stile custom
							$layerName = $tLayer->{"layerNameDxf"};
							$layerFound = True;
							break;
						}
					}
					if(!$layerFound){
						//in base alla geometria lo mando su layer di default
						switch (strtolower($geometryType)) {
							case "text":
								$layerName = $theme->{"layer_default_testi"}->{"layerNameDxf"};
								break;
							case "insert":
								$layerName = $theme->{"layer_default_blocchi"}->{"layerNameDxf"};
								break;
							case "polyline":
							case "linestring":
							case "multilinestring":
							case "polygon":
							case "multipolygon":
							case "point":
							default:
								$layerName = $theme->{"layer_default"}->{"layerNameDxf"};
								break;
						}
					}
				}
			}
			if(!$themeFound){ //se la feature non ha tema la disegno in maniera standard sullo 0
				$layerName = "0";
			}
		}else{
			//disegno standard
			$layerName = $dLayer->{"layerName"};
		}
		return $layerName;
	}
	
	
	/**
	* Add entity to polylines' array
	* @param Array $strGeom Dxf array codes
	* @return void
	*/
	public function writeLine($strGeom){
		if(isset($this->outputFile)){
			file_put_contents($this->outputFileLines, implode(PHP_EOL, $strGeom), FILE_APPEND);
			file_put_contents($this->outputFileLines, PHP_EOL, FILE_APPEND);
		}else{
			//$this->entLines = array_merge($this->entLines, $strGeom);
			foreach ($strGeom as $fline){
				array_push($this->entLines, $fline);
			}
		}
	}
	
	/**
	* Add entity to points' array
	* @param Array $strGeom Dxf array codes
	* @return void
	*/
	public function writePoint($strGeom){
		//valuto se scrivere su disco o utilizzare array in memoria
		if(isset($this->outputFile)){
			file_put_contents($this->outputFilePoints, implode(PHP_EOL, $strGeom), FILE_APPEND);
			file_put_contents($this->outputFilePoints, PHP_EOL, FILE_APPEND);
		}else{
			//$this->entPoints = array_merge($this->entPoints, $strGeom);
			foreach ($strGeom as $fline){
				array_push($this->entPoints, $fline);
			}
		}
	}
	
	/**
	* Add entity to polygon' array
	* @param Array $strGeom Dxf array codes
	* @return void
	*/
	public function writeHatch($strGeom){
		if(isset($this->outputFile)){			
			file_put_contents($this->outputFileHatches, implode(PHP_EOL, $strGeom), FILE_APPEND);
			file_put_contents($this->outputFileHatches, PHP_EOL, FILE_APPEND);
		}else{
			foreach ($strGeom as $fline){
				array_push($this->entHatches, $fline);
			}
		}
	}
	
	/**
	* Ricava gli style in base alle propriet� dell'utente
	*
	* @param object $props Propriet� della feature
	* @param object $styles Array degli stili
	*
	* @return object Primo stile trovato con le caratteristiche fornite
	*/
	public function getStyles($props, $styles){
		//var_dump($props);
		//return $styles[0];
		//$this->log("feature");
		$expression = "";
		$styleResult = [];
		try {
			$style = NULL;
			foreach ($styles as $thisStyle){
				//valutazione dell'espressione
				$expression = $thisStyle->{'expression'}; 
				$this->log($expression);
				if($expression == NULL){
					array_push($styleResult, $thisStyle);
					continue;
				}
				//ricavo tutti i campi da sostituire
				preg_match_all("/\[(.*?)\]/", $expression, $fieldList);
				//Eseguo la sostituzione dei campi
				if(count($fieldList) > 0){
					foreach ($fieldList[0] as $field){
						$valueProp = "0";
						if(isset($props->{$this->normalizeField($field)})){
							$valueProp = $props->{$this->normalizeField($field)};
							//elimino i caratteri non validi
							$valueProp = str_replace(array("\n","\r","\t"), '', $valueProp);
						}
						if($valueProp == ""){
							//questa verifica controlla se il valore � una stringa in base agli apici
							//non ho il tipo di campo e i valori nulli non vengono valutati con i numeri
							if( substr($expression, strrpos($expression, $field) -1, 1) != "'"){
								$valueProp = "0";
							}
						}
						$expression = str_replace($field, $valueProp, $expression);
					}
					//valuto l'espressione
					$this->log($expression);
					//print($expression."\n");
					$result = $this->parserExpression->evaluateString($expression);
					$this->log("result ".$result);
					//se valida uso lo stile
					if ($result == 1){
						array_push($styleResult, $thisStyle);
					}
				}
			}
		} catch (Exception $e) {
			$this->log("Espressione non valida ".$expression); //var_dump($e)
			//$styleResult = [$styles[0]];
		}
		return $styleResult;
	}
	
	/**
	* Calcola una espressione in base alle propriet� dell'utente
	*
	* @param object $props Propriet� della feature
	* @param object $expression Espressione da valutare
	*
	* @return object Primo stile trovato con le caratteristiche fornite
	*/
	public function calculateExpression($props, $expression){
		//var_dump($props);
		//$this->log($expression);
		try {
			//valutazione dell'espressione
			if($expression == NULL){
				return "";
			}
			//Valuto se l'espressione � una sola parola
			if(strpos($expression, ' ') === false){
				if(isset($props->{$this->normalizeField($expression)})){
					$result =  utf8_decode($props->{$this->normalizeField($expression)});
				}
				return $result;
			}
			//ricavo tutti i campi da sostituire
			preg_match_all("/\[(.*?)\]/", $expression, $fieldList);
			//Eseguo la sostituzione dei campi
			if(count($fieldList) > 0){
				foreach ($fieldList[0] as $field){
					if(isset($props->{$this->normalizeField($field)})){
						$expression = str_replace($field, $props->{$this->normalizeField($field)}, $expression);
					}else{
						$expression = str_replace($field, "0", $expression);
					}
				}
				//valuto l'espressione
				$this->log($expression);
				$result = $this->parserExpression->calculateString($expression);
				$this->log("result ".$result);
				return utf8_decode($result);
				break;
			}
		} catch (Exception $e) {
			$this->log("Espressione non valida ".$expression); //var_dump($e)
			return "";
		}
		return "";
	}
		
	public function evalExpression($expression){
		//
		
		$expression = str_replace("=", "==", $expression);
		//valido l'espressione
		eval("\$result = ".$expression.";");
	}
	//**************************************************************************************************************
	
	public function drawFeature($style, $dLayer, $layerName, $feature){
		
		$props = $feature->{'properties'};
		$coords = $feature->{'geometry'}->{'coordinates'};
		$this->log("Style: ".json_encode($style));
		if(is_null($style)){
			$this->log("Impossibile definire lo stile".$dLayer->{'layerName'}." la feature non sar� visualizzata");
			return;
		}
		//colore
		$color = NULL;
		if(isset($style->{'color'})){
			$color = $style->{'color'};
		}
		$outlineColor = NULL;
		if(isset($style->{'outlineColor'})){
			$outlineColor = $style->{'outlineColor'};
		}else{
			//$outlineColor = $dLayer->{'color'};
		}
		$linetype = NULL;
		if(isset($style->{'lineType'})){
			$linetype = $this->getLineStyleName($style->{'lineType'});
		}
		$fieldText = NULL;
		if(isset($dLayer->{'fieldText'})){
			$fieldText = $dLayer->{'fieldText'};
		}
		if(isset($style->{'fieldText'})){
			$fieldText = $style->{'fieldText'};
		}
		$labelColor = NULL;
		if(isset($style->{'labelColor'})){
			$labelColor = $style->{'labelColor'};
		}
		$symbolName = NULL;
		if(isset($style->{'symbol_name'})){
			$symbolName = $style->{'symbol_name'};
		}
		(!empty($dLayer->{'thickness'})) ? $thickness = $dLayer->{'thickness'} : $thickness = 1;
		if(isset($style->{'thickness'})){
			$thickness = $style->{'thickness'};
		}
		$labelSize = $this->defaultSize;
		if(isset($style->{'labelSize'})){
			$labelSize = $style->{'labelSize'};
		}
		//fine definizione dei valori di default
		$this->log("Simbolo ".$feature->{'geometry'}->{'type'});
		switch (strtolower($feature->{'geometry'}->{'type'})) {
			case "point":
				//setto la z se non presente
				if(count($coords) == 2){
					array_push($coords, 0);
				}
				//verifico l'etichetta
				if(!is_null($fieldText)){
					//aggiungo una etichetta
					$text = "";
					$angle = 0;
					
					if(!empty($props)){
						if(isset($fieldText)){
							if(isset($props->{$this->normalizeField($fieldText)})){
								$text = $props->{$this->normalizeField($fieldText)};
							}else{
								$this->log("Campo ".$fieldText." non configurato");
							}
						}
						//setto l'angolo al valore del campo definito
						if(isset($style->{'fieldTextAngle'})){ 
							if(isset($props->{$this->normalizeField($style->{'fieldTextAngle'})})){ 
								$angle = intval($props->{$this->normalizeField($style->{'fieldTextAngle'})});
							}
						}
					}
					//aggiungo i valori fissi degli angoli
					(isset($style->{'textAngle'})) ? $angle += intval($style->{'textAngle'}) : $angle +=0;
					if(!$this->stringArrayCheck($dLayer->{"layerName"}, $this->excludeTextLayers)){
						$this->dxfCode->addText($this->getLayerNamebyLayer($dLayer, "text"), $coords[0], $coords[1], $coords[2], $text, $this->getLabelSize($labelSize, $this->dxfTextScaleMultiplier), $angle, NULL, $labelColor);
					}
				}
				//se il nome del simbolo � settato aggiungo un blocco altrimenti un punto
				if(!$this->stringArrayCheck($dLayer->{"layerName"}, $this->excludeGeometryLayers)){
					$this->log("Simbolo ".$symbolName);
					$this->log("Colore ".$color);
					if(!is_null($symbolName)){
						$angle = 0;
						$symbolName = $this->getSymbolName($symbolName);
						//print_r($style);
						(isset($style->{'fieldAngle'})) ? $angle = intval($props->{$this->normalizeField($style->{'fieldAngle'})}): $angle = 0;
						if(isset($style->{'angle'})) { $angle += intval($style->{'angle'}); };
						$this->dxfCode->addInsert($this->getLayerNamebyLayer($dLayer, "insert"), $coords[0], $coords[1], $coords[2], $symbolName, $angle, $color, $this->dxfInsertScaleMultiplier);
					}else{
						$this->dxfCode->addPoint3D($this->getLayerNamebyLayer($dLayer, "insert"), $coords[0], $coords[1], $coords[2], 1, $color);
					}
				}
			break;
			case "text":
				if(!$this->stringArrayCheck($dLayer->{"layerName"}, $this->excludeTextLayers)){
					$text = "";
					$angle = 0;
					if(!empty($props)){
						(!is_null($fieldText)) ? $text = $props->{$dLayer->{'fieldText'}} : $text = "";
						(isset($style->{'fieldTextAngle'})) ? $angle = intval($props->{$this->normalizeField($style->{'fieldTextAngle'})}) : $angle = 0;
					}
					//(isset($style->{'labelSize'})) ? $labelSize = $style->{'labelSize'} : $labelSize = $this->defaultSize;
					(isset($style->{'textAngle'})) ? $angle += intval($style->{'textAngle'}) : $angle +=0;
					//setto la z se non presente
					if(count($coords) == 2){
						array_push($coords, 0);
					}
					$this->dxfCode->addText($layerName, $coords[0], $coords[1], $coords[2], $text, $this->getLabelSize($labelSize, $this->dxfTextScaleMultiplier), $angle, NULL, $labelColor);
				}
			break;
			case "insert":
				if(!$this->stringArrayCheck($dLayer->{"layerName"}, $this->excludeGeometryLayers)){
					$symbolName = $this->getSymbolName($symbolName);
					$angle = 0;
					//$size = $this->defaultSize;
					if(!empty($props)){
						(isset($fieldText)) ? $text = $props->{$fieldText} : $text = "";
						(isset($style->{'fieldAngle'})) ? $angle = intval($props->{$this->normalizeField($style->{'fieldAngle'})}): $angle = 0;
						if(isset($style->{'angle'})) { $angle += intval($style->{'angle'}); };
					}
					//setto la z se non presente
					if(count($coords) == 2){
						array_push($coords, 0);
					}				
					$this->dxfCode->addInsert($layerName, $coords[0], $coords[1], $coords[2], $symbolName, $angle, $color, $this->dxfInsertScaleMultiplier);
				}
			break;
			case "polyline":
			case "linestring":
			case "multilinestring":
				//setto l'outline come il colore se non � definito
				if(is_null($outlineColor)){
					$outlineColor = $color;
				}
				if(is_null($color) && is_null($outlineColor)){
					//$this->printFeatureLayer("Impossibile definire il colore", $feature, $dLayer);
					//return;
				}
				//TODO non aggiungo se il nome contiene una etichetta, andrebbe definita in maniera differente
				//ma al momento non esistono soluzioni alternative
				if(!$this->stringArrayCheck($dLayer->{"layerName"}, $this->excludeGeometryLayers)){
					if(strtolower($feature->{'geometry'}->{'type'}) == "multilinestring"){
						for($li = 0; $li < count($coords); $li++){
							$this->dxfCode->addPolyLine($layerName, $coords[$li], $thickness, ($dLayer->{'splitted'}) ? null : $linetype, $outlineColor);
						}
					}else{
						$this->dxfCode->addPolyLine($layerName, $coords, $thickness, ($dLayer->{'splitted'}) ? null : $linetype, $outlineColor);
					}
				}
				//sezione etichette alle linee
				if(!$this->stringArrayCheck($dLayer->{"layerName"}, $this->excludeTextLayers)){
					if(!is_null($fieldText)){
						//aggiungo una etichetta
						$text = "TEXT NOT FOUND";
						$angle = 0;
						//$labelSize = $this->defaultSize;
						//calcolo del testo
						if(!empty($props)){
							if (!is_null($fieldText)) {
								$text =  $this->calculateExpression($props, $fieldText);
							}
						}
						if(is_null($labelColor)){
							if(!is_null($outlineColor)){
								$labelColor = $outlineColor;
							}
						}
						//calcolo della coordinata per l'etichetta
						$midCount = intval(count($coords)/2);
						$midPoint = [$this->midPointX($coords[$midCount-1][0],$coords[$midCount][0]),$this->midPointY($coords[$midCount-1][1],$coords[$midCount][1]), 0];
						//calcolo l'angolo
						$angle = $this->calcAngle($coords[$midCount-1][0], $coords[$midCount][0], $coords[$midCount-1][1], $coords[$midCount][1] );
						//rettifico l'orientamento
						$angle = $this->labelAngle($angle);
						$this->dxfCode->addText($this->getAnnotationLayerNamebyLayer($dLayer), $midPoint[0] + $this->getOffsetX($angle), $midPoint[1]+ $this->getOffsetY($angle), $midPoint[2], $text, $this->getLabelSize($labelSize, $this->dxfLabelScaleMultiplier), $angle, NULL, $labelColor);
					}
				}
				//sezione simboli associati alle linee
				//if(!in_array($dLayer->{'layerName'}, $this->excludeGeometryLayers)){
					if(!is_null($symbolName)){
						//$symbolName = "FGN_DIREZIONE_1";
						$symbolCoords = $this->getPointCoordsDistance($coords, 10);
						//aggiungo un insert
						foreach($symbolCoords as $symbolCoord){
							$this->dxfCode->addInsert($this->getInsertLayerNamebyLayer($dLayer), $symbolCoord[0], $symbolCoord[1], 0, $symbolName, $symbolCoord[2], $color, 1);
						}
					}
				//}
			break;
			case "polygon":
			case "multipolygon":
				//verifica del multipolygon
				if(!$this->stringArrayCheck($dLayer->{"layerName"}, $this->excludeGeometryLayers)){		
					if(is_array($coords[0])){
						for($i=0; $i< count($coords); $i++){
							$this->dxfCode->addPolygon($layerName, $coords[$i], $thickness, $linetype, $outlineColor, $color);
						}
					}else{
						$this->dxfCode->addPolygon($layerName, $coords, $thickness, $linetype, $outlineColor, $color);
					}
				}
			break;
		}
	}
	
	/**
	* Aggiunge un layer 
	*
	* @param string $layerName Nome del layer di destinazione nel file DXF
	* @param integer $color Identificativo del colore autocad per il layer (defalut 1)
	* @param string $lineType Identificativo della linea (defalut Continuous)
	*
	* @return array
	*/
	
	
	/*
	* Calcola l'angolo tra una coppia di coordinate
	*
	* @param double $x1 Coordinata X1
	* @param double $y1 Coordinata Y1
	* @param double $x2 Coordinata X2
	* @param double $y2 Coordinata Y2
	*
	* @return double
	*/
	public function calcAngle($x1, $x2, $y1, $y2)
    {
        $dx = $x2-$x1;
        $dy = $y2-$y1;
        $angle = 0.0;
        // Calculate angle
        if ($dx == 0.0)
        {
            if ($dy == 0.0){
                $angle = 0.0;
				}
            else if ($dy > 0.0)
                {$angle = M_PI / 2.0;}
            else
                {$angle = M_PI * 3.0 / 2.0;}
        }
        else if ($dy == 0.0)
        {
            if  ($dx > 0.0)
                {$angle = 0.0;}
            else
                {$angle = M_PI;}
        }
        else
        {
            if  ($dx < 0.0)
                {$angle = atan($dy/$dx) + M_PI;}
            else if ($dy < 0.0)
                {$angle = atan($dy/$dx) + (2 * M_PI);}
            else
                {$angle = atan($dy/$dx);}
        }

        // Convert to degrees
        $angle = $angle * 180 / M_PI;

        // Return
        return $angle;
    }

	
	public function labelAngle($angle)
    {
		if($angle > 90 && $angle < 270){
			$angle += 180; 
		}
		return $angle;
	}
	
	
	/*
	* Calcola la coordinata di mezzo sull'asse x
	*
	* @param double $x1 Coordinata X1
	* @param double $y1 Coordinata X2
	*
	* @return double
	*/
	public function midPointX($x1, $x2){
		$mid1 = 0.0;

		if( $x1 > $x2 ){
			$mid1 = ($x1 - $x2)/2 + $x2;
		}
		else{
			$mid1 = ($x2 - $x1)/2 + $x1;
		}
		return $mid1;
	}
	
	/*
	* Calcola la coordinata di mezzo sull'asse y
	*
	* @param double $y1 Coordinata Y1
	* @param double $y2 Coordinata Y2
	*
	* @return double
	*/
	public function midPointY($y1, $y2){
		$mid2 = 0.0;
		if ( $y1 > $y2 ){
			$mid2 = ($y1 - $y2)/2 + $y2;
		}
		else{
			$mid2 = ($y2 - $y1)/2 + $y1;
		}
		return $mid2;
	}
	
	/*
	* Calcola la distanza tra una coppia di coordinate
	*
	* @param double $x1 Coordinata X1
	* @param double $y1 Coordinata Y1
	* @param double $x2 Coordinata X2
	* @param double $y2 Coordinata Y2
	*
	* @return double
	*/
	public function calcDistance($x1, $x2, $y1, $y2){
		$distance = 0.0;
		$distance = sqrt(($x2 -$x1) * ($x2 - $x1)  + ($y2 - $y1) * ($y2 - $y1)); 
		return $distance;
	}
	
	public function getPointCoordsDistance($coords, $metersPerLabel){
		$arrResult = array();
		$totalLenght = 0.0; //lunghezza totale del segmento
		$segmentLength = 0.0; //lunghezza parziale del segmento
		//calcolo la lunghezza totale
		for ($f=1; $f < count($coords); $f++){
			$totalLenght += $this->calcDistance($coords[$f-1][0], $coords[$f][0], $coords[$f-1][1], $coords[$f][1]);
		}
		//verifico che l'offset non sia troppo vicino al limite
		if ($totalLenght < $metersPerLabel) {
			return $arrResult; 
		}
		//ciclo i segmenti in modo da assegnare un oggetto per ogni multiplo di metersPerLabel
		for ($f=1; $f < count($coords); $f++){
			$segmentLength = $this->calcDistance($coords[$f-1][0], $coords[$f][0], $coords[$f-1][1], $coords[$f][1]);
			$segmentAngle = $this->calcAngle($coords[$f-1][0],$coords[$f][0],$coords[$f-1][1],$coords[$f][1]);
			if($segmentLength < $metersPerLabel){
				//restituisco il punto medio
				$point_x = ($coords[$f-1][0] + $coords[$f][0]) / 2;
				$point_y = ($coords[$f-1][1] + $coords[$f][1]) / 2;
				//array_push($arrResult, [$point_x, $point_y, $segmentAngle]);
			}else{
				$numPoints = floor($segmentLength/$metersPerLabel);
				//calcolo le coordinate del punto distante tot metri dalla x
				for ($k=1; $k <= $numPoints; $k++){				
					//inserisco l'etichetta
					//angolo tra i punti
					$tempAngSin = $segmentAngle * M_PI / 180;
					$point_x = $coords[$f-1][0] + ($k * $metersPerLabel)  * cos($tempAngSin);
					$point_y = $coords[$f-1][1] + ($k * $metersPerLabel) * sin($tempAngSin);
					$actualDistance += $metersPerLabel;
					array_push($arrResult, [$point_x, $point_y, $segmentAngle]);
				}
			}			
		}
		return $arrResult;
	}
		
	public function getLabelSize($labelSize, $multiplier){
		//Se i contesti non sono attivati abilito la scalatura
		if(!$this->dxfEnableTemplateContesti){
			$labelSize = $labelSize * $multiplier;
		}
		return $labelSize;
	}
	public function getSymbolName($name){
		//return "CAMBIO ATTRIBUTI";
		return $name;
	}
	
	
	public function getLineStyleName($name){
		//return "CAMBIO ATTRIBUTI";
		$result = "Continuous";
		switch(strtolower($name)){
			case "dash":
				$result = "ACAD_ISO02W100";
			break;
			case "dash_dash_dot_dot":
				$result = "ACAD_ISO13W100";
			break;
			case "dash_dash_dot":
				$result = "ACAD_ISO11W100";
			break;
			case "dash_dot_dot_dot":
				$result = "ACAD_ISO14W100";
			break;
			case "dash_dot_dot":
				$result = "ACAD_ISO12W100";
			break;
			case "dash_dot":
				$result = "ACAD_ISO10W100";
			break;
			case "linea_tratt":
				$result = "ACAD_ISO03W100";
			break;
			case "dot":
				$result = "ACAD_ISO07W100";
			break;
			case "linea_puntinata_10":
				$result = "ACAD_ISO07W100";
			break;
			case "nascosta":
				$result = "ACAD_ISO04W100";
				break;
			default:
			break;
		}
		return $result;
	}
	
	
	/*
	* Calcola la lunghezza lineare di un array di coordinate
	*
	* @param array $coords array di coordinate XY
	*
	* @return double
	*/
	public function getLength($coords){
		$len = 0.0;
		if(count($coords) < 2){
			return $len;
		}
		for($i = 1; $i < count($coords); $i++){
			$len = $this->calcDistance($coords[$i-1][0], $coords[$i][0], $coords[$i-1][1], $coords[$i][1]);
		}
		return $len;
	}
	
	public function normalizeField($str){
		//devo eliminare anche i caratteri non utili
		$str = str_replace("'", "", $str);
		return str_replace("[", "", str_replace("]", "", $str));
	}
	
	public function printFeatureLayer($message ,$feature, $dLayer){
		if(isset($message)){
			$this->log($message);
		}
		/*if(isset($feature)){
			$this->log(var_dump($feature));
		}
		if(isset($dLayer)){
			$this->log($dLayer);
		}*/
	}
	
	public function getOffsetX($angle){
		while($angle > 360){
			$angle = $angle - 360;
		}
		if($angle > 0 && $angle <= 90){
			return -0.2;
		}
		return 0.2;
	}
	
	public function getOffsetY($angle){
		//if(($angle >= 0 && $angle <= 180)){
		//		return 1;
		//}
		//return -1;
		return 0.2;
	}
	
	
	/**
	* Ricava il GeoJson da una chiamata WFS su MapServer
	*
	* @param string $url URI del wfs da richiamare comprensivo di tutti i parametri necessari
	*
	* @return object
	*/
	public function getFeatures($url){
		$this->log($url);
		$this->log("Iniziata richiesta");
		$json = file_get_contents($url);
		$this->log("Terminata richiesta");
		$arr = explode("\n", $json);
		//elimino gli errori generati da mapserver
		array_shift($arr);
		array_shift($arr);
		array_shift($arr);
		$json = implode("\n",$arr);
		$geoJson = json_decode($json);
		
		return $geoJson;
	}
	
	/**
     * Verifica se la stringa � presente in un array in base a un patterna
     * @param string $str
     * @param array $arrayMatch
     * @return bool
     */
	public static function stringArrayCheck($str, $arrayMatch)
	{
		foreach($arrayMatch as $match) {
			if (fnmatch($match, $str)) {
			  return True;
			}
		}
		return False;
	}
	
	/**
	* Aggiunge delle geometrie di prova al file
	*
	* @return void
	*/
	public function addDummy(){
		$this->entities = array_merge($this->entities, $this->dxfCode->addPoint3D("0", 10, 10, 0, 1));
		$coords = array(array(11, 11, 11), array(22, 22, 22));
		$this->entities = array_merge($this->entities, $this->dxfCode->addPolyLine3d("0", $coords, 1, NULL));
		$this->entities = array_merge($this->entities, $this->dxfCode->addText("0", 20, 20, 0, "GEOIREN", 22, 90, ""));
		$coords = array(array(33, 33), array(44, 44));
		$this->entities = array_merge($this->entities, $this->dxfCode->addPolyLine("0", $coords, 1, NULL));
		$this->entities = array_merge($this->entities, $this->dxfCode->addInsert("0", 50, 50, 0, "ENEL", 0));
		$coords = array(array(55, 55), array(55, 66), array(66, 66), array(66, 55), array(55, 55));
		$this->entities = array_merge($this->entities, $this->addPolygon("0", $coords, 1, NULL, 1, NULL));
	}

}

?>
