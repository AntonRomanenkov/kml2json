<?php

$zip = new ZipArchive;
$rootPath = $_SERVER['DOCUMENT_ROOT'];
$rootPath .= "/downloads";
$files = array(
    "Magix3_SurveyBlocks_Polygons_kml", 
    "Core_Library_Drillholes_kml",
    "Tenements_Live_kml"
);
$fields = array(
    array("R_NUMBER", "COMMISSIONED_BY", "RELEASE_DATE", "EXTRACT_DATE"),
    array("SHORT_NAME", "DEPTH", "COMMODITY", "EXTRACT_DATE"),
    array("Tenement ID", "Tenement Type", "Survey Status", "Start Date", "Extract Date")
);

ini_set('memory_limit', '1024M');
$isFailed = false;
foreach ($files as $index => $file) {
    if (!file_exists($file)) {
        continue;
    }
    $path = $rootPath . "/" . $file . "/kmz";
    $kmlPath = $rootPath . "/" . $file . "/kml";
    $name = substr(str_replace("_", " ", $file), 0, -4);
    
    $jsonData = array(
        "type" => "FeatureCollection",
        "name" => $name,
        "features" => array()
    );

    $res = $zip->open($file);
    if ($res === true) {
        $zip->extractTo($path);
        $zip->close();
    
        $kmlFileName = explode("_kml", $file);
        $kmz_file = $path . '/' . $file . '/' . $kmlFileName[0] . '.kmz';
    
        $kmzRes = $zip->open($kmz_file);
        if ($kmzRes === true) {
            $zip->extractTo($kmlPath);
            $zip->close();
    
            $kml = $kmlPath . '/doc.kml';
    
            $xml = simplexml_load_file($kml);
            if (isset($xml->Document->Folder)) {
                $childs = $xml->Document->Folder->children();
            } else {
                $childs = $xml->Document->Placemark;
            }
            foreach ($childs as $key => $placemark)
            {
                $featuresAry = array(
                    "type" => "Feature",
                    "geometry" => array(
                        "type" => "",
                        "coordinates" => array()
                    ),
                    "properties" => array()
                );
                $attr = $placemark->attributes();
                if (isset($attr["id"])) {
                    foreach ($placemark->ExtendedData->SchemaData->SimpleData as $simpleData) {
                        $name = strval($simpleData->attributes()->name);
                        if (in_array( $name, $fields[$index] ) ) {
                            $value = dom_import_simplexml($simpleData)->textContent;
                            $featuresAry["properties"][$name] = $value;
                        }
                    }
                    $coordinatesAry = array();
                    if (isset( $placemark->MultiGeometry )) {
                        $multiGeometry = $placemark->MultiGeometry;
                        $featuresAry["geometry"]["type"] = "MultiLineString";
                        $coordAry = array();
                        foreach ($multiGeometry->Polygon as $polygons) {
                            $coords = dom_import_simplexml($polygons->outerBoundaryIs->LinearRing->coordinates)->textContent;
                            
                            $ary = array();
                            $coords = explode(",0.0", $coords);
                            for ($j = 0; $j < count($coords) - 1; $j++) {
                                $longlat = explode(",", $coords[$j]);
                                $ary[] = $longlat;
                            }
                            $coordAry[] = $ary;
                        }
                        $coordinatesAry = $coordAry;
                    } else {
                        if (isset($placemark->Point)) {
                            $featuresAry["geometry"]["type"] = "MultiPoint";
                            $coords = dom_import_simplexml($placemark->Point->coordinates)->textContent;
                        } else if (isset($placemark->Polygon->outerBoundaryIs->LinearRing)) {
                            $featuresAry["geometry"]["type"] = "LineString";
                            $coords = dom_import_simplexml($placemark->Polygon->outerBoundaryIs->LinearRing->coordinates)->textContent;
                        }
                        $coords = explode(",0.0", $coords);
                        for ($i = 0; $i < count($coords) - 1; $i++) {
                            $longlat = explode(",", $coords[$i]);
                            $coordinatesAry[] = $longlat;
                        }
                    }
                    $featuresAry["geometry"]["coordinates"] = $coordinatesAry;
                    $jsonData["features"][] = $featuresAry;
                }
            }
        } else {
            $isFailed = true;
        }
    } else {
        $isFailed = true;
    }
    
    $jsonPath = $rootPath . "/" . $file . "/json";
    if (!file_exists($jsonPath)) {
        mkdir($jsonPath, 0777, true);
    }
    file_put_contents( $jsonPath  . "/" . $file . '.json', json_encode($jsonData, JSON_NUMERIC_CHECK));
}

if ($isFailed) {
    exit("Failed...");
}
exit("Successed!!");