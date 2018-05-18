<?xml version="1.0"?>
<rss version="2.0">
  <channel>
    <title>TCL OTA</title>
    <link>https://tclota.birth-online.de/timeline.php</link>
    <description>TCL OTA updates</description>
<?php

require_once __DIR__ . '/lib/autoloader.php';

use \TclUpdates\SQLiteReader;

$db = new SQLiteReader();

$allVars = $db->getAllVariantsByRef();
$unknowns = $db->getUnknownRefs();
if (count($unknowns) > 0) {
    foreach ($unknowns as $uref) {
        $allVars[$uref] = array(
            'family' => 'Unknown',
            'model' => 'Model',
            'variant' => '',
        );
    }
}

$allfiles = $db->getAllFiles($db::FULL_ONLY);
foreach ($allfiles as $file) {
    $updates = $db->getAllUpdatesForFile($file['sha1']);
    $validRefs = array();
    $validDevs = array();
    $firstSeen = new DateTime();
    $firstSeen->setTimezone(new DateTimeZone('CET'));
    foreach ($updates as $u) {
        $dev = $allVars[$u['curef']];
        $validRefs[] = $u['curef'];
        $validDevs[] = $dev['family'] . ' ' . $dev['model'];
        $firstSeenDate = new DateTime($u['seenDate']);
        $firstSeenDate->setTimezone(new DateTimeZone('CET'));
        if ($firstSeenDate < $firstSeen) {
            $firstSeen = $firstSeenDate;
        }
    }
    $validDevs = array_unique($validDevs);
    sort($validDevs);
    $device = $allVars[$updates[0]['curef']];
    $date = new DateTime($file['published_first']);
    $date->setTimezone(new DateTimeZone('CET'));
    $dateLast = new DateTime($file['published_last']);
    $dateLast->setTimezone(new DateTimeZone('CET'));
    echo '<div class="version">' . $file['tv'];
    if ($file['fv']) {
        echo '<span>(OTA from ' . $file['fv'] . ')</span>';
    }

?>    <item>
       <title>News for September the Second</title>
       <link>http://example.com/2002/09/01</link>
       <description>other things happened today</description>
    </item>
<?php
    #echo '</div>';
    #echo '<div class="date"><span>' . $date->format('Y-m-d') . '</span> ' . $date->format('H:i.s') . ' CET</div>';
    #echo '<div class="devices"><span>' . implode('</span> / <span>', $validDevs) . '</span></div>';
    #echo '<div class="lastreleased">Last released: <span>' . $dateLast->format('Y-m-d H:i.s') . '</span> (first seen in the wild: <span>' . $firstSeen->format('Y-m-d H:i.s') . '</span>)</div>';
    #echo '<div class="validfor">Valid for (order of release): <span>' . implode('</span>, <span>', $validRefs) . '</span></div>';
    #print_r($file);
    #print_r($updates);
    echo '</div></div>';
}

?>
  </channel>
</rss>
