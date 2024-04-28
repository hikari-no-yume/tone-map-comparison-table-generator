<?php declare(strict_types=1);

/*
 * hikari_no_yume's script for generating instrument comparison tables from
 * https://github.com/shingo45endo/tone-browser JSON data for use on
 * https://dtm.noyu.me/
 *
 * Currently used for these pages:
 * - https://dtm.noyu.me/wiki/Roland_SC-55
 *
 * Consider this to be MIT license. But please contact me if you're interested
 * in expanding it. Maybe we can collaborate!
 */

function error(string $msg): void {
    fprintf(STDERR, "Error: $msg" . PHP_EOL);
    exit(1);
}
function ln(string $text): void {
    echo $text, PHP_EOL;
}

array_shift($argv); // discard script filename

$filenames = [];
$ignoredMsbs = [];
$renames = [];

foreach ($argv as $arg) {
    if (str_starts_with($arg, "--ignore-msb=")) {
        $ignoredMsbs[substr($arg, strlen("--ignore-msb="))] = TRUE;
    } else if (str_starts_with($arg, "--rename=")) {
        [$renameFrom, $renameTo] = explode(':', substr($arg, strlen("--rename=")));
        $renames[$renameFrom] = $renameTo;
    } else if (str_starts_with($arg, "--")) {
        error("Unknown option: $arg");
    } else {
        $filenames[] = $arg;
    }
}
if (empty($filenames)) {
    error("Please specify .json filenames");
}

$modules = [];
$tones = [];

foreach ($filenames as $filename) {
    $moduleName = basename($filename, '.json');
    $modules[] = $moduleName;

    $json = file_get_contents($filename) or error("Couldn't open '$filename'");
    $json = json_decode($json, /* associative: */ FALSE, JSON_THROW_ON_ERROR);
    $map = $json->toneMaps or error("JSON format not as expected");

    foreach ($map as $tone) {
        if ($ignoredMsbs[$tone->bankM] ?? FALSE) {
            continue;
        }
        $name = trim($tone->toneRef->name);
        $name = $renames[$name] ?? $name;
        $tones[$tone->prog][$tone->bankM][$moduleName] = $name;
    }
}

ln('{| class="wikitable"');
ln('! style="width: 1em;" | Prog #');
ln('! style="width: 1em;" | Bank Select MSB');
foreach ($modules as $moduleName) {
    ln('! style="width: 6em;" | ' . ($renames[$moduleName] ?? $moduleName));
}
ln('|-');
$lastProgNo = NULL;
foreach ($tones as $progNo => $msbs) {
    ln('| rowspan="' . count($msbs) . '" | ' . $progNo);
    ksort($msbs, SORT_NUMERIC);
    foreach ($msbs as $msb => $modulesForMsb) {
        ln('|' . $msb);
        for ($i = 0; $i < count($modules); $i++) {
            $toneName = $modulesForMsb[$modules[$i]] ?? NULL;
            $red = '';
            for ($colspan = 1; $i + $colspan < count($modules); $colspan++) {
                $nextToneName = $modulesForMsb[$modules[$i + $colspan]] ?? NULL;
                if ($nextToneName !== $toneName) {
                    // Highlight cases where a tone is not missing in an earlier
                    // module, but rather *different*!
                    if ($toneName !== NULL && $nextToneName !== NULL) {
                        $red = 'style="background: red;" ';
                    }
                    break;
                }
            }
            ln('| ' . $red . 'colspan="' . $colspan . '" | ' . ($toneName === NULL ? '—' : $toneName));
            $i += ($colspan - 1);
        }
        ln('|-');
    }
}
ln('|}');
