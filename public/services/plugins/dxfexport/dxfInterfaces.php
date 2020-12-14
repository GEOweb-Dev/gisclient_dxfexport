<?php
interface iDxfFactory
{
    public function getNextHandle();
	public function getNextHandlePoint();
	public function getNextHandleLine();
	public function getNextHandleHatch();
	public function log($message);
	
	public function writeLine($strGeom);
	public function writePoint($strGeom);
	public function writeHatch($strGeom);
}

interface iDxfCode
{
	function __construct($dxfFactory, $lineScale, $enableColors, $enableLineThickness, $drawHatches);
    public function addLayer($layerName, $aciColor, $color, $lineType);
	public function addPoint3D($layerName, $x, $y, $z, $thickness, $color);
	public function addPolyLine3d($layerName, $coords, $thickness, $lineType, $color);
	public function addPolyLine($layerName, $coords, $thickness, $lineType, $color);
	public function addPolygon($layerName, $coords, $thickness, $lineType, $outlineColor, $color);
	public function addHatch($layerName, $coords, $color, $pattern, $parentHandle);
	public function addText($layerName, $x, $y, $z, $text, $labelSize, $angle, $textAlignHorizontal, $textAlignVertical, $color);
	public function addInsert($layerName, $x, $y, $z, $blockName, $angle, $color, $scaleInsert);
}

?>