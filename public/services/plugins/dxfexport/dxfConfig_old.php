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
$dxfLogPath = "/var/srv/geoweb/tmp/dxf/log.txt";
//abilita l'inserimento dei riempimenti True attivi False non attivi
$dxfDrawHatches = False;
//abilita la dimensione personalizzata delle linee e polilinee. Mettendo a false la dimensione sar� DA LAYER
$dxfEnableLineThickness = True;
//abilita il colore personalizzato per gli oggetti. Mettendo a false il colore sar� DA LAYER
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
		'btu_circoscrizione',
		'base_dbt_dbt_accesso_civici',
		'base_dbt_dbt_reticolo_stradale',
		'base_dbt_dbt_aree_verdi_coltura_agricola',
		
		'gas_pronto_intervento_etichette_tratte',
		'gas_pronto_intervento_impianti_aeeg',
		'gas_pronto_intervento_sedetecnica',
		'gas_pronto_intervento_zone_gestione',
		'gas_quote_t',
		'gas_quote_p',
		'gas_quote_l',
		'gas_particolari_l',
		'gas_particolari_a',
		'gas_particolari_p',
		'gas_etichette_tratte',
		'gas_cantieri',
		
		'acqua_quote_t',
		'acqua_quote_p',
		'acqua_quote_l',
		'acqua_distretti',
		'acqua_acquedotto',
		'acqua_acquedotto_frazionale',
		'acqua_cantieri',
		'acqua_etichette_tratte',
		'acqua_zone_gestione',
		'acqua_zone_omogenee',
		'acqua_perdite',
		
		'fognature_frecce',
		'fognature_agglomerati',
		'fognature_quote_t',
		'fognature_quote_p',
		'fognature_quote_l',
		'fognature_f_etichette_tratte',
		'fognature_particolari_l',
		'fognature_particolari_a',
		'fognature_particolari_p',
		'fognature_cantieri',
		
		
		'teleriscaldamento_particolari_l',
		'teleriscaldamento_particolari_a',
		'teleriscaldamento_particolari_p',
		'teleriscaldamento_cantieri',
		'teleriscaldamento_quote_testi',
		'teleriscaldamento_quote_linee',
		'teleriscaldamento_cantieri'
		
		/*
		'etichette',
		'etichette_cabine',
		'etichette_sedetecnica',
		'condotte_etichette',
		'siti',
		'aree_salvaguardia',
		'avvisi',
		'zone_gestione',
		*/
		
		

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
?>
