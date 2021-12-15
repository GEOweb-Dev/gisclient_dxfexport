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

/**
 *	Classe per la generazione dei filtri WFS
 *
 */
class dxfWfsFilters
{

    public static function GetFilterField($field, $value)
    {
        $attributeFilters = (object) [
            'filters' => [
                (object) [
                    "operator" => "equalto",
                    "field" => $field,
                    "value" => $value
                ]
            ],

        ];
        return dxfWfsFilters::GetFilterProperties($attributeFilters);
    }

    public static function GetFilterPolygon($polygonMask)
    {
        //TODO semplice replace la funzione andrebbe migliorata
        $polygonMask = str_replace(
            "POLYGON((",
            "&FILTER=<ogc:Filter xmlns:ogc=%22http://www.opengis.net/ogc%22><ogc:And><ogc:BBOX><ogc:PropertyName>geom</ogc:PropertyName><gml:Envelope xmlns:gml='http://www.opengis.net/gml'><gml:lowerCorner>-32690 4401560</gml:lowerCorner><gml:upperCorner>1114190 5548440</gml:upperCorner></gml:Envelope></ogc:BBOX><ogc:Intersects><ogc:PropertyName>geom</ogc:PropertyName><gml:Polygon xmlns:gml=%22http://www.opengis.net/gml%22><gml:outerBoundaryIs><gml:LinearRing><gml:coordinates>",
            $polygonMask
        );
        $polygonMask = str_replace(
            "))",
            "</gml:coordinates></gml:LinearRing></gml:outerBoundaryIs></gml:Polygon></ogc:Intersects></ogc:And></ogc:Filter>",
            $polygonMask
        );
        return $polygonMask;
    }

    public static function GetFilterBBOX($minX, $maxX, $minY, $maxY)
    {
        return "&FILTER=%3Cogc:Filter%20xmlns:ogc=%22http://www.opengis.net/ogc%22%3E%3Cogc:BBOX%3E%3Cogc:PropertyName%3Egeom%3C/ogc:PropertyName%3E%3Cgml:Envelope%20xmlns:gml=%22http://www.opengis.net/gml%22%3E%3Cgml:lowerCorner%3E" . $minX . "%20" . $minY . "%3C/gml:lowerCorner%3E%3Cgml:upperCorner%3E" . $maxX . "%20" . $maxY . "%3C/gml:upperCorner%3E%3C/gml:Envelope%3E%3C/ogc:BBOX%3E%3C/ogc:Filter%3E";
    }

    /**
     * Esporta una array con le stringhe da applicare alle chiamate http al WFS
     * Il risultato è un array in quanto le chiamate possono essere parcellizzate per limiatare il numero delle chiamate
     */
    public static function GetFilterProperties($attributeFilters)
    {
        $filters = $attributeFilters->{'filters'};
        $filterPropertiesArray = [];
        $filterProperties = "";
        $filterPropertiesHeader = '&FILTER=%3Cogc:Filter xmlns:ogc=%22http://www.opengis.net/ogc%22%3E';
        $filterPropertiesHeader .= "%3Cogc%3AAnd%3E%0A%3Cogc%3ABBOX%3E%3Cogc%3APropertyName%3Egeom%3C%2Fogc%3APropertyName%3E%3Cgml%3AEnvelope%20xmlns%3Agml%3D%22http%3A%2F%2Fwww.opengis.net%2Fgml%22%3E%3Cgml%3AlowerCorner%3E-32690%204401560%3C%2Fgml%3AlowerCorner%3E%3Cgml%3AupperCorner%3E1114190%205548440%3C%2Fgml%3AupperCorner%3E%3C%2Fgml%3AEnvelope%3E%3C%2Fogc%3ABBOX%3E";
        $filterPropertiesEnd = "%3C%2Fogc%3AAnd%3E%0A%3C/ogc:Filter%3E";

        $filterIn2 = array_filter($filters, function ($obj) {
            return $obj->{"operator"} == "in2";
        });
        if (count($filterIn2) > 0) { //gestione filtri speciali
            $filter = $filterIn2[0];
            $filterPropertiesArray = [];
            $filterIn = explode(",", $filter->{"value"});
            $count = 0;
            for ($kin = 0; $kin < count($filterIn); $kin++) {
                $filterProperties = $filterProperties . "%3Cogc:PropertyIsEqualTo matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filter->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E" . trim($filterIn[$kin]) . "%3C/ogc:Literal%3E%3C/ogc:PropertyIsEqualTo%3E";
                $count++;
                if ($count == 200) {
                    $filterProperties = $filterPropertiesHeader . "%3Cogc:Or%3E" . $filterProperties . "%3C/ogc:Or%3E" . $filterPropertiesEnd;
                    array_push($filterPropertiesArray, $filterProperties);
                    $filterProperties = "";
                    $count = 0;
                }
            }
            //ultime features
            //verifica di un solo elemento
            if ($count == 1) {
                $filterProperties = $filterPropertiesHeader . $filterProperties . $filterPropertiesEnd;
            } else if ($count > 1) {
                $filterProperties = $filterPropertiesHeader . "%3Cogc:Or%3E" . $filterProperties . "%3C/ogc:Or%3E" . $filterPropertiesEnd;
            }

            array_push($filterPropertiesArray, $filterProperties);
            return $filterPropertiesArray;
        }

        //filtri normali
        $filterProperties = $filterPropertiesHeader;
        if (count($filters) > 1) {
            switch (strtoupper($attributeFilters->{"logic"})) {
                case "OR":
                    $filterProperties = $filterProperties . "%3Cogc:Or%3E";
                    break;
                default:
                    $filterProperties = $filterProperties . "%3Cogc:And%3E";
                    break;
            }
        }
        for ($i = 0; $i < count($filters); $i++) {
            switch ($filters[$i]->{"operator"}) {
                case "equalto":
                    $filterProperties = $filterProperties . "%3Cogc:PropertyIsEqualTo matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filters[$i]->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E" . $filters[$i]->{"value"} . "%3C/ogc:Literal%3E%3C/ogc:PropertyIsEqualTo%3E";
                    break;
                case "notequalto":
                    $filterProperties = $filterProperties . "%3Cogc:PropertyIsNotEqualTo matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filters[$i]->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E" . $filters[$i]->{"value"} . "%3C/ogc:Literal%3E%3C/ogc:PropertyIsNotEqualTo%3E";
                    break;
                case "greaterthan":
                    $filterProperties = $filterProperties . "%3Cogc:PropertyIsGreaterThanOrEqualTo matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filters[$i]->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E" . $filters[$i]->{"value"} . "%3C/ogc:Literal%3E%3C/ogc:PropertyIsGreaterThanOrEqualTo%3E";
                    break;
                case "lessthan":
                    $filterProperties = $filterProperties . "%3Cogc:PropertyIsLessThanOrEqualTo matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filters[$i]->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E" . $filters[$i]->{"value"} . "%3C/ogc:Literal%3E%3C/ogc:PropertyIsLessThanOrEqualTo%3E";
                    break;
                case "contains":
                    $filterProperties = $filterProperties . "%3Cogc:PropertyIsLike %20wildcard%3D%27*%27%20singleChar%3D%27.%27%20escape%3D%27!%27 matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filters[$i]->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E*" . $filters[$i]->{"value"} . "*%3C/ogc:Literal%3E%3C/ogc:PropertyIsLike%3E";
                    break;
                case "startswith":
                    $filterProperties = $filterProperties . "%3Cogc:PropertyIsLike %20wildcard%3D%27*%27%20singleChar%3D%27.%27%20escape%3D%27!%27 matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filters[$i]->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E" . $filters[$i]->{"value"} . "*%3C/ogc:Literal%3E%3C/ogc:PropertyIsLike%3E";
                    break;
                case "endswith":
                    $filterProperties = $filterProperties . "%3Cogc:PropertyIsLike %20wildcard%3D%27*%27%20singleChar%3D%27.%27%20escape%3D%27!%27 matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filters[$i]->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E*" . $filters[$i]->{"value"} . "%3C/ogc:Literal%3E%3C/ogc:PropertyIsLike%3E";
                    break;
                case "in":
                    $filterIn = explode(",", $filters[$i]->{"value"});
                    if (count($filterIn) > 1) { //prefissi
                        $filterProperties = $filterProperties . "%3Cogc:Or%3E";
                    }
                    for ($iin = 0; $iin < count($filterIn); $iin++) {
                        $filterProperties = $filterProperties . "%3Cogc:PropertyIsEqualTo matchCase=%22false%22%3E%3Cogc:PropertyName%3E" . $filters[$i]->{"field"} . "%3C/ogc:PropertyName%3E%3Cogc:Literal%3E" . trim($filterIn[$iin]) . "%3C/ogc:Literal%3E%3C/ogc:PropertyIsEqualTo%3E";
                    }
                    if (count($filterIn) > 1) { //suffissi
                        $filterProperties = $filterProperties . "%3C/ogc:Or%3E";
                    }
                    break;
                default:
                    break;
            }
        }

        if (count($filters) > 1) {
            switch (strtoupper($attributeFilters->{"logic"})) {
                case "AND":
                    $filterProperties = $filterProperties . "%3C/ogc:And%3E";
                    break;
                case "OR":
                    $filterProperties = $filterProperties . "%3C/ogc:Or%3E";
                    break;
            }
        }

        $filterProperties = $filterProperties . $filterPropertiesEnd;
        $filterPropertiesArray = [$filterProperties];
        return $filterPropertiesArray;
    }
}
