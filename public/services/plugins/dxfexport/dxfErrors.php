<?php
/******************************************************************************
*
* Purpose: Elenco degli errori per l'estrazione DXF

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

class dxfErrors
{
	const logFile = 'dxflog.log';
    const template_non_configurato = 'Il file template non è specificato nella configurazione.';
	const rete_non_configurato = 'La rete non è specificata nella configurazione.';
	const bbox_undefined = 'Il BBOX di estrazione non è correttamente configurato.';
	const epsg_undefined = 'Il codice EPSG di estrazione non è definito.';
	const handle_invalid = 'Numero di handle non valido.';
	const file_dest_invalid = 'Impossibile scrivere nel file di destinazione indicato.';
	const layers_undefined = 'Layer non configurato.';
	
	
}

?>
