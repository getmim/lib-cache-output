<?php
/**
 * Cleaner
 * @package lib-cache-output
 * @version 0.0.2
 */

namespace LibCacheOutput\Library;
use Mim\Library\Fs;

class Cleaner
{
    static function router(string $route, array $params=[], array $query=[]): void {
        $path = \Mim::$app->router->to($route, $params);
        $path = parse_url($path, PHP_URL_PATH);

        $base = Callback::getCacheBase();

        $apath = $base . trim($path, '/');

        if(!is_dir($apath))
            return;

        $files = Fs::scan($apath);

        foreach($files as $file){
            $afile = $apath . '/' . $file;
            if(is_file($afile))
                unlink($afile);
        }

        Fs::cleanUp($apath);
    }
}