<?php

/******************************************************************************
 *
 * Purpose: Download del file in formato shapezip

 * Author:  Filippo Formentini formentini@perspectiva.it
 *
 ******************************************************************************
 *
 * Copyright (c) 2021 Perspectiva di Formentini Filippo
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
require_once ADMIN_PATH . "lib/functions.php";
require_once ROOT_PATH . "lib/i18n.php";
require_once "dxfConfig.php";

$user = new GCUser();
$hasGeowebShp = false;
//Controllo del gruppo 
$groups = $_SESSION["GROUPS"];

if(!empty($groups)){
	if(array_intersect($dxfShpAllowedGroups, $groups)){
		$hasGeowebShp = true;
	}	
}

$fileJson = new stdClass();
$fileJson->{"hasGeowebShp"} = $hasGeowebShp;
die(json_encode($fileJson));