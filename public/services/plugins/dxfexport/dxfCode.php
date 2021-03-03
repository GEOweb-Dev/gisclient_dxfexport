<?php
/******************************************************************************
*
* Purpose: Helper per la generazione del codice DXF

* Author:  Filippo Formentini formentini@perspectiva.it
*
******************************************************************************
*
* Copyright (c) 2019 Perspectiva di Formentini Filippo
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
*
******************************************************************************/

include_once('dxfInterfaces.php');
/**
*	Classe per la generazione della di un file DXF
*
*
*/
class dxfCode implements iDxfCode {
	
	public $dxfFactory = null;
	
	public $defaultSize = 0.4;
	public $defaultColor = (256 * 256) * 255 + (256 * 255) + 255;
	
	public $lineScale = 0.1;
	public $enableColors = True;
	public $enableLineThickness = True;
	public $drawHatches = False;
	
	public $layerIndex = array();
	
	/**
	* Crea l'oggetto per la generazione del DXF
	* @param iDxfFactory $dFactory
	* @param float $dLineScale
	* @param boolean $dEnableColors
	* @param boolean $dEnableLineThickness
	* @param boolean $dDrawHatches
	* @return void
	*/
	public function __construct($dFactory, $dLineScale, $dEnableColors, $dEnableLineThickness, $dDrawHatches) {
		$this->dxfFactory = $dFactory;
		$this->lineScale = $dLineScale;
		$this->enableColors = $dEnableColors;
		$this->enableLineThickness = $dEnableLineThickness;
		$this->drawHatches = $dDrawHatches;
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
	public function addLayer($layerName, $aciColor, $color, $lineType){
		$strLayer = array();
		//controllo duplicati
		if(in_array($layerName, $this->layerIndex)){
			return $strLayer;
		}
		array_push($this->layerIndex, $layerName);
		
		$tmpHandle = $this->dxfFactory->getNextHandle();
		if (is_null($lineType))
		{
			$lineType = "Continuous";
		}
		if($color == "0"){ //controllo bianco e nero
			$aciColor = "7";
			$color = null;
		}
		//print $layerName." ".$color." ".$aciColor;
		array_push($strLayer, "  0");
		array_push($strLayer, "LAYER");
		array_push($strLayer, "  5");
		array_push($strLayer, $tmpHandle."");
		array_push($strLayer, "330");
		array_push($strLayer, "2");
		array_push($strLayer, "100");
		array_push($strLayer, "AcDbSymbolTableRecord");
		array_push($strLayer, "100");
		array_push($strLayer, "AcDbLayerTableRecord");
		array_push($strLayer, "  2");
		array_push($strLayer, $layerName."");
		array_push($strLayer, " 70");
		array_push($strLayer, "	 0");
		// print $layerName."<br/>:";
		// print "color:".$color."<br/>:";
		// print "_aciColor:".$aciColor."<br/>:";
		if(!is_null($aciColor)){
			array_push($strLayer, " 62");
			array_push($strLayer, $aciColor."");
		}else{
			if (is_null($color)){
				array_push($strLayer, " 62");
				array_push($strLayer, "7");
			}else{
				// if($color < 256){
				// 	array_push($strLayer, " 62");
				// 	array_push($strLayer, $color."");
				// }else{
					array_push($strLayer, " 62");
					array_push($strLayer, !is_null($aciColor) ? $aciColor."" : "7");				
					array_push($strLayer, " 420");
					array_push($strLayer,  $color."");
				// }
			}
		}		
		array_push($strLayer, "  6");
		array_push($strLayer,  $lineType);
		array_push($strLayer, "370");
		array_push($strLayer, "	-3");
		array_push($strLayer, "390");
		array_push($strLayer, "0");
		
		return $strLayer;
	}
	
	/**
	* Crea l'array per l'inserimento di un punto
	*
	* @param string $layerName Nome del layer di destinazione nel file DXF
	* @param double $x Coordinata X
	* @param double $y Coordinata Y
	* @param double $z Coordinata Z
	* @param double $thickness Dimensione del punto
	*
	* @return array
	*/
    public function addPoint3D($layerName, $x, $y, $z, $thickness, $color){
		//se il colore � nullo non disegno
		//if (is_null($color)){
		//	return;
		//}
		//if (is_null($color))
		//{
		//	$color = $this->defaultColor;
		//}
		if ($color == 0)
		{
			$color = null;
			//$color = $this->defaultColor;
		}
		$tmpHandle = $this->dxfFactory->getNextHandlePoint();
        $strGeom = array();
        array_push($strGeom, "  0");
        array_push($strGeom, "POINT");
		array_push($strGeom, "  5");
		array_push($strGeom, $tmpHandle."");
		array_push($strGeom, "100");
		array_push($strGeom, "AcDbEntity");
        array_push($strGeom, "  8");
        array_push($strGeom, $layerName);
		array_push($strGeom, "100");
		array_push($strGeom, "AcDbPoint");
		foreach($this->getColor($color, null, $this->enableColors) as $colorLine){
			array_push($strGeom, $colorLine);
		}
		// array_push($strGeom, " 62");
		// array_push($strGeom, ($this->enableColors) ? "7" : "256");
		// if ($this->enableColors && !is_null($color)){
			// array_push($strGeom, " 420");
			// array_push($strGeom,  $color."");
		// }
        array_push($strGeom, "  10");
        array_push($strGeom, $x."");
        array_push($strGeom, "  20");
        array_push($strGeom, $y."");
		array_push($strGeom, "  30");
        array_push($strGeom, $z."");
		if($this->enableLineThickness){
			array_push($strGeom, "  39");
			array_push($strGeom, $thickness."");
		}
		
		$this->dxfFactory->writePoint($strGeom);
		
		$this->dxfFactory->log("POINT added ".$tmpHandle);
		return $strGeom;
    }
	
	/**
	* Crea l'array per l'inserimento di una polilinea 3d
	*
	* @param string $layerName Nome del layer di destinazione nel file DXF
	* @param double[double[2]] $coords Array di coordinate 3D
	* @param double $thickness Dimensione della linea
	* @param string $lineType Stile della linea (Default CONTINUOUS)
	*
	* @return array
	*/
	public function addPolyLine3d($layerName, $coords, $thickness, $lineType, $color){
		//se il colore � nullo non disegno
		if (is_null($color)){
			return;
		}
		$tmpHandle = $this->dxfFactory->getNextHandleLine();
		$strGeom = array();
		//if (is_null($lineType))
		//{
		//	$lineType = "CONTINUOUS";
		//}
		if ($color == 0)
		{
			$color = null;
		}
		if (count(coords) > 0)
		{
			array_push($strGeom, "  0");
			array_push($strGeom, "POLYLINE");
			array_push($strGeom, "  5");
			array_push($strGeom, $tmpHandle."");
			array_push($strGeom, "  330");
			array_push($strGeom, "1E");
			array_push($strGeom, "  100");
			array_push($strGeom, "AcDbEntity");
			array_push($strGeom, "  8");
			array_push($strGeom, $layerName);
			array_push($strGeom, "  66");
			array_push($strGeom, "1");
			if (!is_null($lineType)){
				array_push($strGeom, "  6");
				array_push($strGeom, $lineType);
			}
			array_push($strGeom, " 48");
			array_push($strGeom, " ".$this->lineScale);
			array_push($strGeom, " 62");
			array_push($strGeom, ($this->enableColors) ? "7" : "256");
			if ($this->enableColors && !is_null($color)){
				array_push($strGeom, " 420");
				array_push($strGeom,  $color."");
			}
			if($this->enableLineThickness){
				array_push($strGeom, "  39");
				array_push($strGeom, $thickness."");
			}
			array_push($strGeom, "  100");
			array_push($strGeom, "AcDb3dPolyline");
			array_push($strGeom, "10");
			array_push($strGeom, "0.0");
			array_push($strGeom, "20");
			array_push($strGeom, "0.0");
			array_push($strGeom, "30");
			array_push($strGeom, "0.0");
			array_push($strGeom, "70");
			array_push($strGeom, " 8");
			
			for($i = 0; $i < count($coords); $i++){
				$coord = $coords[$i];
				array_push($strGeom, "  0");
				array_push($strGeom, "VERTEX");
				array_push($strGeom, "  5");
				array_push($strGeom, $this->dxfFactory->getNextHandleLine()."");
				array_push($strGeom, "  330");
				array_push($strGeom, "4037");
				array_push($strGeom, "  100");
				array_push($strGeom, "AcDbEntity");
				array_push($strGeom, "  8");
				array_push($strGeom, $layerName);
				array_push($strGeom, "  6");
				array_push($strGeom, $lineType);
				array_push($strGeom, " 48");
				array_push($strGeom, " ".$this->lineScale);
				foreach($this->getColor($color, null, $this->enableColors) as $colorLine){
					array_push($strGeom, $colorLine);
				}
				// array_push($strGeom, " 62");
				// array_push($strGeom, ($this->enableColors) ? "7" : "256");
				// if ($this->enableColors && !is_null($color)){
					// array_push($strGeom, " 420");
					// array_push($strGeom,  $color."");
				// }
				array_push($strGeom, "  100");
				array_push($strGeom, "AcDbVertex");
				array_push($strGeom, "  100");
				array_push($strGeom, "AcDb3dPolylineVertex");
				array_push($strGeom, "  10");
				array_push($strGeom, $coord[0]."");
				array_push($strGeom, "  20");
				array_push($strGeom, $coord[1]);
				array_push($strGeom, "  30");
				array_push($strGeom, $coord[2]);
				array_push($strGeom, "  70");
				array_push($strGeom, "32");
			}
			array_push($strGeom, "  0");
			array_push($strGeom, "SEQEND");
			array_push($strGeom, "  5");
			array_push($strGeom, $this->dxfFactory->getNextHandleLine()."");
			array_push($strGeom, "  100");
			array_push($strGeom, "AcDbEntity");
			array_push($strGeom, "  8");
			array_push($strGeom, $layerName);
			array_push($strGeom, "  6");
			array_push($strGeom, "CONTINUOUS");
		}
		
		$this->dxfFactory->writeLine($strGeom);
		$this->dxfFactory->log("POLILYNE added ".$tmpHandle);
		return $strGeom;
		
	}
	
	/**
	* Crea l'array per l'inserimento di una polilinea 
	*
	* @param string $layerName Nome del layer di destinazione nel file DXF
	* @param double[double[2]] $coords Array di coordinate
	* @param double $thickness Dimensione della linea
	* @param string $lineType Stile della linea (Default CONTINUOUS)
	*
	* @return array
	*/
	public function addPolyLine($layerName, $coords, $thickness, $lineType, $color, $lineWeight){
		//se il colore � nullo non disegno
		if (is_null($color)){
			return;
		}
		$strGeom = array();
		$tmpHandle = $this->dxfFactory->getNextHandleLine();
		//if (is_null($lineType))
		//{
		//	$lineType = "CONTINUOUS";
		//}
		if ($color == 0)
		{
			$color = null;
		}
		if (count($coords) > 0)
		{
			//inizio
			array_push($strGeom, "  0");
			array_push($strGeom, "LWPOLYLINE");
			array_push($strGeom, "  5");
			array_push($strGeom, $tmpHandle."");
			array_push($strGeom, "  330");
			array_push($strGeom, "1E");
			array_push($strGeom, "  100");
			array_push($strGeom, "AcDbEntity");
			array_push($strGeom, "  8");
			array_push($strGeom, $layerName);
			if (!is_null($lineType)){
				array_push($strGeom, "  6");
				array_push($strGeom, $lineType);
			}
			array_push($strGeom, " 48");
			array_push($strGeom, " ".$this->lineScale);
			if(!is_null($lineWeight)){
				array_push($strGeom, "  370");
				array_push($strGeom, " ".$lineWeight);
			}
			foreach($this->getColor($color, null, $this->enableColors) as $colorLine){
				array_push($strGeom, $colorLine);
			}
			// array_push($strGeom, " 62");
			// array_push($strGeom, ($this->enableColors) ? "7" : "256");
			// if ($this->enableColors && !is_null($color)){
				// array_push($strGeom, " 420");
				// array_push($strGeom,  $color."");
			// }
			array_push($strGeom, "  100");
			array_push($strGeom, "AcDbPolyline");
			array_push($strGeom, "  90");
			array_push($strGeom, count($coords)."");
			array_push($strGeom, "  70");
			array_push($strGeom, "128");
			//array_push($strGeom, "  43");
			//array_push($strGeom, "0.0");
			if($this->enableLineThickness && !is_null($thickness)){
				array_push($strGeom, " 43");
				array_push($strGeom, $this->getThickness($thickness));
			}
			for($i = 0; $i < count($coords); $i++){
				$coord = $coords[$i];
				array_push($strGeom, "  10");
				array_push($strGeom, $coord[0]."");
				array_push($strGeom, "  20");
				array_push($strGeom, $coord[1]."");
			}
		}
		$this->dxfFactory->writeLine($strGeom);
		$this->dxfFactory->log("POLILYNE added ".$tmpHandle);
		return $strGeom;
    }
	
	/**
	* Crea l'array per l'inserimento di un poligono
	*
	* @param string $layerName Nome del layer di destinazione nel file DXF
	* @param double[double[2]] $coords Array di coordinate
	* @param double $thickness Dimensione della linea del poligono
	* @param string $lineType Stile della linea (Default CONTINUOUS)
	* @param string $outlineColor Colore della linea
	* @param string $color Colore del riempimento se inserito aggiunge una HATCH
	*
	* @return array
	*/
	public function addPolygon($layerName, $coords, $thickness, $lineType, $outlineColor, $color){
		$tmpHandle = $this->dxfFactory->getNextHandleHatch();
		$strGeom = array();
		//disegno solo se ho un colore
		if (count($coords) > 0 && !is_null($outlineColor))
		{
			
			
			if (is_null($lineType))
			{
				$lineType = "Continuous";
			}
			//if (is_null($outlineColor))
			//{
			//	$outlineColor = $this->defaultColor;
			//}
			if ($outlineColor == 0)
			{
				$outlineColor = null;
				//$outlineColor = $this->defaultColor;
			}
		//inizio
			array_push($strGeom, "  0");
			array_push($strGeom, "LWPOLYLINE");
			array_push($strGeom, "  5");
			array_push($strGeom, $tmpHandle."");
			array_push($strGeom, "  330");
			array_push($strGeom, "1E");
			array_push($strGeom, "  100");
			array_push($strGeom, "AcDbEntity");
			array_push($strGeom, "  8");
			array_push($strGeom, $layerName);
			array_push($strGeom, "  6");
			array_push($strGeom, $lineType);
			//array_push($strGeom, "  43");
			//array_push($strGeom, $thickness);
			array_push($strGeom, " 48");
			array_push($strGeom, " 0.1");
			foreach($this->getColor($outlineColor, null, $this->enableColors) as $colorLine){
				array_push($strGeom, $colorLine);
			}
			// array_push($strGeom, " 62");
			// array_push($strGeom, ($this->enableColors) ? "7" : "256");
			// if ($this->enableColors && !is_null($outlineColor)){
				// array_push($strGeom, " 420");
				// array_push($strGeom,  $outlineColor."");
			// }
			array_push($strGeom, "  100");
			array_push($strGeom, "AcDbPolyline");
			array_push($strGeom, "  90");
			array_push($strGeom, count($coords)."");
			array_push($strGeom, "  70");
			array_push($strGeom, "128");
			if($this->enableLineThickness && !is_null($thickness)){
				array_push($strGeom, " 43");
				array_push($strGeom, $this->getThickness($thickness));
			}
			for($i = 0; $i < count($coords); $i++){
				$coord = $coords[$i];
				array_push($strGeom, "  10");
				array_push($strGeom, $coord[0]."");
				array_push($strGeom, "  20");
				array_push($strGeom, $coord[1]."");
			}
		
			$this->dxfFactory->writeLine($strGeom);
		}
		//print($layerName." ".empty($color)." ".$color."\n");
		
		if(!empty($color) && $this->drawHatches){
			$this->addHatch($layerName, $coords, $color, NULL, $tmpHandle);
		}
		$this->dxfFactory->log("POLYGON added ".$tmpHandle);
		return $strGeom;
    }
	
	
/**
	* Crea l'array per l'inserimento di un retino
	*
	* @param string $layerName Nome del layer di destinazione nel file DXF
	* @param double[double[2]] $coords Array di coordinate
	
	
	*
	* @return array
	*/
	public function addHatch($layerName, $coords, $color, $pattern, $parentHandle){
		//se il colore � nullo non disegno
		if (is_null($color)){
			return;
		}
		$tmpHandle = $this->dxfFactory->getNextHandleHatch();
		$strGeom = array();
		if (is_null($pattern))
		{
			$pattern = "SOLID";
		}
		if (count($coords) > 0)
		{
			array_push($strGeom, "0");
			array_push($strGeom, "HATCH");
			array_push($strGeom, "  5");
			array_push($strGeom, $tmpHandle."");
			array_push($strGeom, "330");
			array_push($strGeom, "1F");
			array_push($strGeom, "100");
			array_push($strGeom, "AcDbEntity");
			array_push($strGeom, "  8");
			array_push($strGeom, $layerName);
			foreach($this->getColor($color, null, $this->enableColors) as $colorLine){
				array_push($strGeom, $colorLine);
			}
			// array_push($strGeom, " 62");
			// array_push($strGeom, ($this->enableColors) ? "7" : "256");
			// if ($this->enableColors && !is_null($color)){
				// array_push($strGeom, " 420");
				// array_push($strGeom,  $color."");
			// }
			array_push($strGeom, " 440");
			array_push($strGeom, "0.1");
			array_push($strGeom, "100");
			array_push($strGeom, "AcDbHatch");
			array_push($strGeom, " 10");
			array_push($strGeom, "0.0");
			array_push($strGeom, " 20");
			array_push($strGeom, "0.0");
			array_push($strGeom, " 30");
			array_push($strGeom, "0.0");
			array_push($strGeom, "210");
			array_push($strGeom, "0.0");
			array_push($strGeom, "220");
			array_push($strGeom, "0.0");
			array_push($strGeom, "230");
			array_push($strGeom, "1.0");
			array_push($strGeom, "  2");
			array_push($strGeom, $pattern);
			array_push($strGeom, " 70");
			array_push($strGeom, "     1");
			array_push($strGeom, " 71");
			array_push($strGeom, "     1");
			array_push($strGeom, " 91");
			array_push($strGeom, "        1");
			array_push($strGeom, " 92");
			array_push($strGeom, "        1");
			array_push($strGeom, " 93");
			array_push($strGeom, count($coords));
			
			for($i = 0; $i < count($coords); $i++){
				$coord = $coords[$i];
				if($i == count($coords) - 1){
					$coord1 = $coords[0];
				}else{
					$coord1 = $coords[$i + 1];
				}
				array_push($strGeom, " 72");
				array_push($strGeom, "     1");
				array_push($strGeom, "  10");
				array_push($strGeom, $coord[0]."");
				array_push($strGeom, "  20");
				array_push($strGeom, $coord[1]."");
				array_push($strGeom, "  11");
				array_push($strGeom, $coord1[0]."");
				array_push($strGeom, "  21");
				array_push($strGeom, $coord1[1]."");				
			}
			
			array_push($strGeom, " 97");
			array_push($strGeom, "        1");
			array_push($strGeom, "330");
			array_push($strGeom, $parentHandle."");
			array_push($strGeom, " 75");
			array_push($strGeom, "     1");
			array_push($strGeom, " 76");
			array_push($strGeom, "     1");
			array_push($strGeom, " 98");
			array_push($strGeom, "        1");
			array_push($strGeom, " 10");
			array_push($strGeom, "0.0");
			array_push($strGeom, " 20");
			array_push($strGeom, "0.0");
			//array_push($strGeom, "1001");
			//array_push($strGeom, "GradientColor1ACI");
			//array_push($strGeom, "1070");
			//array_push($strGeom, "     5");
			//array_push($strGeom, "1001");
			//array_push($strGeom, "GradientColor2ACI");
			//array_push($strGeom, "1070");
			//array_push($strGeom, "     2");
			//array_push($strGeom, "1001");
			//array_push($strGeom, "ACAD");
			//array_push($strGeom, "1010");
			//array_push($strGeom, "0.0");
			//array_push($strGeom, "1020");
			//array_push($strGeom, "0.0");
			//array_push($strGeom, "1030");
			//array_push($strGeom, "0.0");
		}
		
		$this->dxfFactory->writeHatch($strGeom);
		$this->dxfFactory->log("HATCH added ".$tmpHandle);
		return $strGeom;
	}
	/**
	* Crea l'array per l'inserimento di un testo
	*
	* @param string $layerName Nome del layer di destinazione nel file DXF
	* @param double $x Coordinata X
	* @param double $y Coordinata Y
	* @param double $z Coordinata Z
	* @param double $strValue Testo da inserire
	* @param double $labelSize Dimensione del testo
	* @param double $angle Angolo del testo
	* @param double $textAlign Allineamento orizzontale del testo
	*
	* @return array
	*/
	public function addText($layerName, $x, $y, $z, $text, $labelSize, $angle, $textAlignHorizontal, $textAlignVertical, $color){		
		//se il colore � nullo non disegno
		//if (is_null($color)){
		//	return;
		//}	
		//rimuovo gli a capo e caratteri non compatibili
		$text = str_replace("\r", "", $text);
		$text = str_replace("\n", "", $text);
		$text = utf8_encode($text);
		$this->dxfFactory->log($text);
		if (is_null($textAlignHorizontal))
		{
			$textAlignHorizontal = 0;
		}
		if (is_null($textAlignVertical))
		{
			$textAlignVertical = 0;
		}
		if ($color == 0)
		{
			$color = null;
		}
		$tmpHandle = $this->dxfFactory->getNextHandlePoint();
		$strGeom = array();
		array_push($strGeom, "  0");
		array_push($strGeom, "TEXT");
		array_push($strGeom, "  5");
		array_push($strGeom, $tmpHandle."");
		array_push($strGeom, "  330");
		array_push($strGeom, "1F");
		array_push($strGeom, "  100");
		array_push($strGeom, "AcDbEntity");
		array_push($strGeom, "  8");
		array_push($strGeom, $layerName);
		array_push($strGeom, "  6");
		array_push($strGeom, "Continuous");
		foreach($this->getColor($color, null, $this->enableColors) as $colorLine){
			array_push($strGeom, $colorLine);
		}
		// array_push($strGeom, " 62");
		// array_push($strGeom, ($this->enableColors) ? "7" : "256");
		// if ($this->enableColors && !is_null($color)){
			// array_push($strGeom, " 420");
			// array_push($strGeom,  $color."");
		// }
		array_push($strGeom, "  100");
		array_push($strGeom, "AcDbText");
		array_push($strGeom, "  10");
		array_push($strGeom, $x."");
		array_push($strGeom, "  20");
		array_push($strGeom, $y."");
		array_push($strGeom, "  30");
		array_push($strGeom, $z."");
		array_push($strGeom, " 40");
		array_push($strGeom, number_format((float)$labelSize, 2, '.', '')."");
		array_push($strGeom, "  1");
		array_push($strGeom, $text);
		array_push($strGeom, " 50");
		array_push($strGeom, $angle."");
		array_push($strGeom, " 72");
		array_push($strGeom, $textAlignHorizontal);
		array_push($strGeom, "  11");
		array_push($strGeom, $x."");
		array_push($strGeom, "  21");
		array_push($strGeom, $y."");
		array_push($strGeom, "  31");
		array_push($strGeom, $z."");
		array_push($strGeom, "100");
		array_push($strGeom, "AcDbText");
		array_push($strGeom, " 73");
		array_push($strGeom, $textAlignVertical);
				
		$this->dxfFactory->writePoint($strGeom);
		$this->dxfFactory->log("TEXT added ".$tmpHandle);
		return $strGeom;

    }
	
	/**
	* Crea l'array per l'inserimento di un blocco
	*
	* @param string $layerName Nome del layer di destinazione nel file DXF
	* @param double $x Coordinata X
	* @param double $y Coordinata Y
	* @param double $z Coordinata Z
	* @param double $blockName Testo da inserire
	* @param double $angle Angolo del testo
	*
	* @return array
	*/
	public function addInsert($layerName, $x, $y, $z, $blockName, $angle, $color, $scaleInsert){
		//se il colore � nullo non disegno
		if (is_null($color)){
			return;
		}
		if ($color == 0)
		{
			$color = null;
		}
		
		$tmpHandle = $this->dxfFactory->getNextHandlePoint();
		$strGeom = array();
		array_push($strGeom, "  0");
		array_push($strGeom, "INSERT");
		array_push($strGeom, "  5");
		array_push($strGeom, $tmpHandle."");
		array_push($strGeom, "  330");
		array_push($strGeom, "1E");
		array_push($strGeom, "  100");
		array_push($strGeom, "AcDbEntity");
		array_push($strGeom, "  8");
		array_push($strGeom, $layerName);
		array_push($strGeom, "  6");
		array_push($strGeom, "Continuous");
		foreach($this->getColor($color, null, $this->enableColors) as $colorLine){
			array_push($strGeom, $colorLine);
		}
		// array_push($strGeom, " 62");
		// array_push($strGeom, ($this->enableColors) ? "7" : "256");
		// if ($this->enableColors && !is_null($color)){
			// array_push($strGeom, " 420");
			// array_push($strGeom,  $color."");
		// }
		array_push($strGeom, "  100");
		array_push($strGeom, "AcDbBlockReference");
		array_push($strGeom, "  2");
		array_push($strGeom, $blockName);
		array_push($strGeom, "  10");
		array_push($strGeom, $x."");
		array_push($strGeom, "  20");
		array_push($strGeom, $y."");
		array_push($strGeom, "  30");
		array_push($strGeom, $z."");
			//annotativo
			/*array_push($strGeom, "1001");
			array_push($strGeom, "AcadAnnotativeDecomposition");
			array_push($strGeom, "1000");
			array_push($strGeom, "AnnotativeData");
			array_push($strGeom, "1002");
			array_push($strGeom, "{");
			array_push($strGeom, "1070");
			array_push($strGeom, "     1");
			array_push($strGeom, "1070");
			array_push($strGeom, "     1");
			array_push($strGeom, "1002");
			array_push($strGeom, "}");
			*/
			//fine annotativo
		array_push($strGeom, " 50");
		array_push($strGeom, $angle."");
		
		array_push($strGeom, "  41");
		array_push($strGeom, number_format((int)$scaleInsert, 0, '.', '')."");
		array_push($strGeom, "  42");
		array_push($strGeom, number_format((int)$scaleInsert, 0, '.', '')."");
		
		$this->dxfFactory->writePoint($strGeom);
		$this->dxfFactory->log("INSERT added ".$tmpHandle);
		return $strGeom;
	}
	
	public function getThickness($thickness){
		//la divisione per 30 � arbitraria. TODO Valutare se � corretta.
		return $thickness / 30;
	}
	
	public function getColor($color, $aciColor, $enableColor){
		$colorArray = array();
		if(!$enableColor){
			array_push($colorArray, " 62");
			array_push($colorArray, "256");
			return $colorArray;
		}
		//print (!is_null($color) && is_null($aciColor));
		// if(!is_null($color) && is_null($aciColor)){ //provo a trasformare il colore in ACI
		// 	print "$color _ $aciColor".(!is_null($color) && is_null($aciColor))."<br />";
		// 	$aciColor = $this->colorDecToAci($color);
		// }
		if(!is_null($aciColor)){
			array_push($colorArray, " 62");
			array_push($colorArray, $aciColor);
			return $colorArray;
		}
		if(!is_null($color)){
			//if($color <= 256){
			//	array_push($colorArray, " 62");
			//	array_push($colorArray, $color);
			//}else{
				array_push($colorArray, " 62");
				array_push($colorArray, "256");
				array_push($colorArray, " 420");
				array_push($colorArray,  $color."");
			//}
			return $colorArray;
		}
		//valore nullo
		array_push($colorArray, " 62");
		array_push($colorArray, "256");
		return $colorArray;
	}
	
	private function getDecimalColor($r, $g, $b)
    {
		return (256 * 256 * $r) + (256 * $g) + $b;
	}
	
	public function colorDecToAci($color){
		switch($color){
			case $this->getDecimalColor(255,0,0):
				return 1;
				break;
			case $this->getDecimalColor(0,0,255):
				return 5;
				break;
			case $this->getDecimalColor(0,3,0):
				return 5;
				break;
			case $this->getDecimalColor(0,0,7):
				return 7;
				break;
			case $this->getDecimalColor(0,0,0):
				return 7;
				break;
			case $this->getDecimalColor(255,255,255):
				return 7;
				break;
		}
		return null;
	}
}

?>
