<?php
if (!defined('ABSPATH')) { exit; }

if (!class_exists('WCTF_Request')) {

class WCTF_Request {

    const DEFAULT_TIMEOUT = 30;

    public static function get($endpoint,$query=array(),$headers=array()){
        if(!empty($query)){ $endpoint = add_query_arg($query,$endpoint); }
        return self::request('GET',$endpoint,array(),$headers);
    }

    public static function post($endpoint,$body=array(),$headers=array()){
        return self::request('POST',$endpoint,$body,$headers);
    }

    public static function put($endpoint,$body=array(),$headers=array()){
        return self::request('PUT',$endpoint,$body,$headers);
    }

    public static function delete($endpoint,$body=array(),$headers=array()){
        return self::request('DELETE',$endpoint,$body,$headers);
    }

    private static function request($method,$endpoint,$body=array(),$headers=array()){
        $config = function_exists('wctf_config') ? wctf_config() : array();
        $url = self::build_url($endpoint,$config);

        $args = array(
            'method'=>strtoupper($method),
            'timeout'=>!empty($config['timeout']) ? absint($config['timeout']) : self::DEFAULT_TIMEOUT,
            'redirection'=>3,
            'httpversion'=>'1.1',
            'blocking'=>true,
            'sslverify'=>empty($config['debug']),
            'headers'=>self::build_headers($config,$headers),
        );

        if(!empty($body)){
            $args['body'] = wp_json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        $start = microtime(true);
        $response = wp_remote_request($url,$args);
        $duration = round((microtime(true)-$start)*1000,2);

        return self::parse_response($response,$url,$method,$duration);
    }

    private static function build_url($endpoint,$config){
        $base = !empty($config['api_url']) ? untrailingslashit($config['api_url']) : '';
        return $base.'/'.ltrim($endpoint,'/');
    }

    private static function build_headers($config,$headers){
        $default = array(
            'Accept'=>'application/json',
            'Content-Type'=>'application/json',
        );

        if (!empty($config['api_key'])) {
    $default['X-API-Key'] = $config['api_key'];
}

        return array_merge($default,$headers);
    }

    private static function parse_response($response,$url,$method,$duration){

        if(is_wp_error($response)){
            self::log('error',array(
                'method'=>$method,
                'url'=>$url,
                'duration'=>$duration,
                'message'=>$response->get_error_message(),
            ));

            return array(
                'success'=>false,
                'status'=>0,
                'message'=>$response->get_error_message(),
                'body'=>array(),
                'headers'=>array(),
                'raw'=>'',
            );
        }

        $status = wp_remote_retrieve_response_code($response);
        $message = wp_remote_retrieve_response_message($response);
        $raw = wp_remote_retrieve_body($response);

        $body = json_decode($raw,true);
        if(json_last_error() !== JSON_ERROR_NONE){
            $body = array();
        }

        self::log(($status>=200 && $status<300)?'info':'error',array(
            'method'=>$method,
            'url'=>$url,
            'status'=>$status,
            'duration'=>$duration,
        ));

        return array(
            'success'=>($status>=200 && $status<300),
            'status'=>$status,
            'message'=>$message,
            'body'=>is_array($body)?$body:array(),
            'headers'=>wp_remote_retrieve_headers($response),
            'raw'=>$raw,
        );
    }

    private static function log($level,$context=array()){
        if(function_exists('wctf_log')){
            wctf_log($level,'HTTP Request',$context);
        }
    }
}

}
