<?php
/**
 * Callback
 * @package lib-cache-output
 * @version 0.0.1
 */

namespace LibCacheOutput\Library;

use LibCompress\Library\Compressor;
use Mim\Library\Fs;

class Callback
{

    private static function buildCompression(array &$content, array $comps): void{
        if(!module_exists('lib-compress'))
            return;

        $original_content = $content['plain'];
        foreach($comps as $comp){
            $result = null;

            if($comp === 'brotli')
                $result = Compressor::brotliContent($original_content);
            if($comp === 'gzip')
                $result = Compressor::gzipContent($original_content);
            if($result)
                $content[$comp] = $result;
        }
    }

    private static function buildCache($content){
        $cache_file = self::getCachePath();

        $etag = md5($cache_file);

        $php = self::buildCacheFile($cache_file, $etag);
        Fs::write($cache_file, $php);
        foreach($content as $com => $ctn)
            Fs::write($cache_file . '.' . $com, $ctn);

        return $etag;
    }

    private static function buildCacheFile($path, $etag){
        $name    = basename($path);
        $nl      = PHP_EOL;
        $expires = time() + \Mim::$app->res->getCache();

        $res = [
            'etag'    => $etag,
            'headers' => \Mim::$app->res->getHeader()
        ];

        $tx = '<?php' . $nl . $nl;
        $tx.= 'if(time() > ' . $expires . ')' . $nl;
        $tx.= '    return false;' . $nl;
        $tx.= 'return ' . to_source($res) . ';';

        return $tx;
    }

    private static function getCachePath(){
        $path = \Mim::$app->req->path;
        $cache_path = self::getCacheBase();
        $cache_name = 'req';

        $path = trim($path, '/');
        if($path)
            $path.= '/';

        $query = \Mim::$app->config->libCacheOutput->query;
        $qparams = [];
        foreach($query as $name => $default){
            if(false === $default)
                continue;
            if($default === true)
                $default = 1;
            $cache_name.= '-' . $name . '-' . \Mim::$app->req->getQuery($name, $default);
        }

        $cache_name.= '.php';
        $cache_file = $cache_path . $path . $cache_name;

        return $cache_file;
    }

    // check output cache, print it if exists
    static function coreReady(){
        if(is_dev())
            return;

        $cache_file = self::getCachePath();
        if(!is_file($cache_file))
            return;

        $mim = &\Mim::$app->res;

        $cache_data = include $cache_file;

        $caches = [
            'br'    => 'brotli',
            'gzip'  => 'gzip',
            'plain' => 'plain'
        ];

        // remove the cache
        if(!$cache_data){
            unlink($cache_file);
            foreach($caches as $cache){
                $cache_data_file = $cache_file . '.' . $cache;
                if(is_file($cache_data_file))
                    unlink($cache_data_file);
            }
            return;
        }

        $etag    = $cache_data['etag'];
        $headers = $cache_data['headers'];

        $req_etag = \Mim::$app->req->getServer('HTTP_IF_NONE_MATCH');

        if($etag == $req_etag){
            $mim->setStatus(304);
            $mim->addHeader('ETag', $etag, false);
            $mim->send(false);
            exit;
        }

        $accepts = \Mim::$app->req->accept->encoding;
        $accepts[] = 'plain';
        $encoding = 'plain';
        foreach($caches as $header => $cache){
            $cache_data_file = $cache_file . '.' . $cache;
            if(!in_array($header, $accepts) || !is_file($cache_data_file))
                continue;

            $used_content = file_get_contents($cache_data_file);
            $mim->addHeader('Content-Length', strlen($used_content), false);
            $mim->addContent($used_content, true);

            foreach($headers as $name => $values){
                foreach($values as $val)
                    $mim->addHeader($name, $val);
            }

            if($header != 'plain')
                $mim->addHeader('Content-Encoding', $header);

            $mim->addHeader('ETag', $etag, false);
            $mim->send(false);

            exit;
        }
    }

    static function corePrinting(){
        if(is_dev())
            return true;

        ini_set('zlib.output_compression','Off');
        $mim = &\Mim::$app->res;

        $content = [
            'plain' => $mim->getContent()
        ];
        $status  = $mim->getStatus();
        $cookies = $mim->getCookie();
        $headers = $mim->getHeader();

        $skip_cache      = $status != 200 || !!$cookies;
        $resp_compress   = 'plain';
        $target_compress = ['brotli', 'gzip'];

        if(!$mim->getCache())
            $skip_cache = true;

        $accepts = \Mim::$app->req->accept->encoding;
        if(in_array('br', $accepts))
            $resp_compress = 'brotli';
        elseif(in_array('gzip', $accepts))
            $resp_compress = 'gzip';

        if($skip_cache)
            $target_compress = $resp_compress == 'plain' ? [] : [$resp_compress];

        if($target_compress)
            self::buildCompression($content, $target_compress);

        $etag = null;
        if(!$skip_cache)
            $etag = self::buildCache($content);

        $used_content = $content['plain'];
        if(!isset($content[$resp_compress]))
            $resp_compress = 'plain';

        $used_content = $content[$resp_compress];
        $mim->addHeader('Content-Length', strlen($used_content), false);
        $mim->addContent($used_content, true);

        $content_encoding = '';
        
        if($resp_compress === 'brotli')
            $content_encoding = 'br';
        elseif($resp_compress === 'gzip')
            $content_encoding = 'gzip';
        

        if($content_encoding)
            $mim->addHeader('Content-Encoding', $content_encoding);
        if($etag)
            $mim->addHeader('ETag', $etag, false);

        return true;
    }

    static function getCacheBase(){
        $path = \Mim::$app->config->libCacheOutput->path;
        if(substr($path, 0, 1) != '/')
            $path = BASEPATH . '/' . chop($path, '/') . '/';

        return $path;
    }
}