<?php
/******************************************************************************
*
* Purpose: Configurazione per l'estrazione DXF

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
//Toggle del debug
$dxfDebug = True;
//Directory di salvataggio del dxf e dei file temporanei
$dxfTempPath = "/var/srv/geoweb-f/gisclient-3/tmp/dxf/";
//Attivazione del salvataggio dei file
$dxfSaveToDir = 1;
//File di log
$dxfLogPath = "/var/srv/geoweb-f/gisclient-3/tmp/dxf/log.txt";
//abilita l'inserimento dei riempimenti True attivi False non attivi
$dxfDrawHatches = False;
//abilita la dimensione personalizzata delle linee e polilinee. Mettendo a false la dimensione sarà DA LAYER
$dxfEnableLineThickness = True;
//abilita il colore personalizzato per gli oggetti. Mettendo a false il colore sarà DA LAYER
$dxfEnableColors = True;
//Singolo layer per blocchi
$dxfEnableSingleLayerBlock = False;
//Layer da escludere per singolo layer per blocchi
$dxfExcludeSingleLayerBlock = array('base_btu_btu_simbologia_p');
//parametro di ridimensionamento dei testi
$dxfTextScaleMultiplier = 2;
//parametro di ridimensionamento dele label
$dxfLabelScaleMultiplier = 5;
//parametro di ridimensionamento dei simboli
$dxfInsertScaleMultiplier = 3;
//layer geometrici da escludere di default
$dxfExcludeGeometryLayers = array(
		'frecce',
		'etichette',
		'etichette_cabine',
		'etichette_tratte',
		'etichette_sedetecnica',
		'condotte_etichette',
		'siti',
		'aree_salvaguardia',
		'avvisi',
		'cantieri',
		'distretti',
		'acquedotto',
		'acquedotto_frazionale',
		'circoscrizione',
		'sede_tecnica',
		'zone_gestione',
		'zone_omogenee',
		'f_sede_tecnica',
		'agglomerati',
		'perdite',
		'quote_t',
		'quote_p',
		'quote_l',
		'quote_linee',
		'quote_testi',
		'particolari_l',
		'particolari_a',
		'particolari_p'
	);
//layer testuali da escludere di default
$dxfExcludeTextLayers = array(
		
	);
//split dei layer in base alle classi	
$dxfSplitLayers = array(
	'tratte_condotte_principali',
	'tratte_allacciamenti',
	'reflui_condotte'
	);
//parametro di ridimensionamento dei testi
$dxfTextScaleMultiplier = 7;
//parametro di ridimensionamento dei simboli
$dxfInsertScaleMultiplier = 6;

	
?>
