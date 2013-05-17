<?php
/*
 This file is part of kml2svg
 http://github.com/AndrewRose/kml2svg
 http://andrewrose.co.uk
 License: GPL; see below
 Copyright Andrew Rose (hello@andrewrose.co.uk) 2013

    kml2svg is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    cached.php is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with cached.php.  If not, see <http://www.gnu.org/licenses/>
*/

$outputSvg = TRUE; // FALSE = gd
$scale = 1.5;
$skipPostcodes = ['ZE', 'KW', 'GY', 'JE']; // outer islands of the UK hidden

ini_set('memory_limit', '512M');
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 'On');
error_reporting(E_ALL);

$maxLon = 0;
$maxLat = 0;
$minLon = 999999;
$minLat = 999999;

function checkCords($lon, $lat)
{
	global $maxLon;
	global $maxLat;
	global $minLon;
	global $minLat;

	if($lat > $maxLat) $maxLat = $lat;
	if($lon > $maxLon) $maxLon = $lon;

	if($lat < $minLat) $minLat = $lat;
	if($lon < $minLon) $minLon = $lon;

	return [$lat,  $lon];
}

$data = file_get_contents('postcode-boundaries.kml');

$doc = new DOMDocument();
$doc->loadXML($data);
$xpath = new DOMXPath($doc);
$data = array();

$placemarks = $xpath->query('/Document/Folder/Placemark');

$cords = [];

foreach($placemarks as $placemark)
{
	foreach($placemark->childNodes as $node)
	{
		switch($node->nodeName)
		{
			case 'name':
			{
				$name = $node->nodeValue;
				if(in_array($name, $skipPostcodes))
				{
					continue 3;
				}
			}
			break;

			case 'MultiGeometry':
			{
				$polys = $xpath->query('Polygon', $node);
				//$polys = $xpath->query('Polygon/outerBoundaryIs/LinearRing/coordinates', $node);
				foreach($polys as $poly)
				{
					$tmpname = $name.rand();
					$cords[$tmpname] = [];

					$datums = explode(' ', trim($poly->nodeValue));
					foreach($datums as $datum)
					{
						$tmp = explode(',', $datum);
						$cords[$tmpname][] = checkCords($tmp[1], $tmp[0]);
					}
				}
			}
			break;

			case 'Polygon':
			{
				$cords[$name] = [];
				$polys = $xpath->query('outerBoundaryIs/LinearRing/coordinates', $node);
				foreach($polys as $poly)
				{
					$datums = explode(' ', trim($poly->nodeValue));
					foreach($datums as $datum)
					{
						$tmp = explode(',', $datum);
						$cords[$name][] = checkCords($tmp[1], $tmp[0]);
					}
				}

			}
			break;
		}
	}
}

$height = (3963.0 * acos(sin($maxLat/57.2958) * sin($minLat/57.2958) + cos($maxLat/57.2958) * cos($minLat/57.2958) * cos($minLon/57.2958 - $minLon/57.2958)));
$width = (3963.0 * acos(sin($maxLat/57.2958) * sin($maxLat/57.2958) + cos($maxLat/57.2958) * cos($maxLat/57.2958) * cos($maxLon/57.2958 - $minLon/57.2958)));

$uheight=intval($height*$scale)+intval($height*$scale*.05);
$uwidth=intval($width*$scale)+intval($width*$scale*.05);

$xdiff=intval($height*$scale*.025);
$ydiff=intval($width*$scale*.025);

if($outputSvg)
{
	echo '<html><head></head><body>';
	echo '<svg xmlns="http://www.w3.org/2000/svg" version="1.1"> <g id="map" transform="rotate(180, '.($uheight/2).','.($uwidth/2).')">';
}
else
{
	$gd = imagecreatetruecolor($uwidth, $uheight);
	$white = imagecolorallocate($gd, 255, 255, 255);
	$red = imagecolorallocate($gd, 255, 0, 0);
}

foreach($cords as $outcode => $datums)
{
	$previousDatum = array_shift($datums);

	$y = (3963.0 * acos(sin($maxLat/57.2958) * sin($previousDatum[0]/57.2958) + cos($maxLat/57.2958) * cos($previousDatum[0]/57.2958) *  cos($minLon/57.2958 - $minLon/57.2958)));
	$x = (3963.0 * acos(sin($maxLat/57.2958) * sin($maxLat/57.2958) + cos($maxLat/57.2958) * cos($maxLat/57.2958) *  cos($previousDatum[1]/57.2958 - $minLon/57.2958)));
	$previousX = intval($x*$scale)+$xdiff;
	$previousY = intval($y*$scale)+$ydiff;

	$points = [];
	foreach($datums as $nextDatum)
	{
		$y = (3963.0 * acos(sin($maxLat/57.2958) * sin($nextDatum[0]/57.2958) + cos($maxLat/57.2958) * cos($nextDatum[0]/57.2958) *  cos($minLon/57.2958 - $minLon/57.2958)));
		$x = (3963.0 * acos(sin($maxLat/57.2958) * sin($maxLat/57.2958) + cos($maxLat/57.2958) * cos($maxLat/57.2958) *  cos($nextDatum[1]/57.2958 - $minLon/57.2958)));

		$nextX=intval($x*$scale)+$xdiff;
		$nextY=intval($y*$scale)+$ydiff;

		if($outputSvg)
		{
			$points[] = $previousY.','.$previousX;
		}
		else
		{
//$points[] = $previousY;
			imageline ($gd, $previousX,  $previousY, $nextX, $nextY, $white);
		}

		$previousY=$nextY;
		$previousX=$nextX;
	}

	if($outputSvg)
	{
		echo '<polygon id="'.$outcode.'" class="poly" data-name="'.$outcode.'" points="'.implode(' ', $points).'" style="fill:green;stroke:purple;stroke-width:1" />'."\n";
	}
}

if($outputSvg)
{
	echo '</g></svg></body></html>';
}
else
{
	header('Content-Type: image/png');
	imagepng($gd);
	imagedestroy($gd);
}
