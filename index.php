<?php
error_reporting(-1);

$zip = new ZipArchive;
$rootPath = $_SERVER['DOCUMENT_ROOT'];
$files = array("Magix3_SurveyBlocks_Polygons_kml", "Core_Library_Drillholes_kml");
$fields = array(
array(
    "R_NUMBER", "COMMISSIONED_BY", "RELEASE_DATE", "EXTRACT_DATE"
),
array(
    "SHORT_NAME", "DEPTH", "COMMODITY", "EXTRACT_DATE"
    )
);

$isFailed = false;
foreach ($files as $index => $file) {
    $jsonData = array();
    $path = $rootPath . "/kmz";
    $kmlPath = $rootPath . "/kml" . '/' . $file;
    
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
            $childs = $xml->Document->Folder->children();
            foreach ($childs as $key => $placemark)
            {
                $attr = $placemark->attributes();
                $ary = array();
                if (isset($attr["id"])) {
                    foreach ($placemark->ExtendedData->SchemaData->SimpleData as $simpleData) {
                        $name = strval($simpleData->attributes()->name);
                        if (in_array( $name, $fields[$index] ) ) {
                            $value = dom_import_simplexml($simpleData)->textContent;
                            $ary[$name] = $value;
                        }
                    }
                    $jsonData[] = $ary;
                }
            }
        } else {
            $isFailed = true;
        }
    } else {
        $isFailed = true;
    }

    file_put_contents($file . '.json', json_encode($jsonData));
}

if ($isFailed) {
    exit("Failed...");
}
exit("Successed!!");