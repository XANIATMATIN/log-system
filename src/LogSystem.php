<?php

namespace MatinUtils\LogSystem;

use Exception;

class LogSystem
{
    protected $lugs, $socketClient, $sendType, $common = [];

    public function __construct($pid = '')
    {
        $this->setPID($this->getPID());
        $this->sendType = config('lug.sendType', 'http');
    }

    public function getPID()
    {
        $pid = request()->headers->get('pid') ?? request('pid');
        if (empty($pid)) {
            $pid = $this->setPID(uniqid());
        }
        return $pid;
    }

    public function addCommon($name, $value)
    {
        $this->common[$name] = $value;
    }

    public function setPID($pid = '')
    {
        $pid = str_replace('{','', $pid);
        if (strlen($pid) < 3) {
            $pid = uniqid();
        }
        request()->headers->set('pid', $pid);
        return $pid;
    }

    public function multiLug(string $type, string $message, array $data = [])
    {
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[2];
        $postFields = array_merge([
            'lugVia' => 'Http',
            'project' => env('LOG_SERVICE_NAME', ''),
            'file' =>  $data['file'] ?? $stack['file'] ?? 'no_file',
            'line' => $data['line'] ?? $stack['line'] ?? 'no_line',
            'serverIp' => gethostbyname(gethostname()) ?? '-'
        ], $this->common);


        if (strlen($message) > 255) {
            $data['lugMessage'] = $message;
            $message = "Message is too long, please look at Data";
        }

        foreach ($data as $key => $item) {
            try {
                $postFields['serialize'][$key] = serialize($item);
            } catch (Exception $exception) {
                $postFields['serialize'][$key] = serialize('exception: ' . $exception->getMessage());
            }
        }

        $this->lugs['logs'][] = [
            'type' => $type ?? 'dd',
            'message' => $message ?? null,
            'time' => microtime(true),
            'info' => $postFields
        ];

        if (count($this->lugs['logs']) >= 9) {
            return $this->send();
        }
    }

    public function send()
    {
        if (empty($this->lugs)) {
            return null;
        }
        $pid = $this->getPID();
        $options = [
            CURLOPT_URL => env('LOG_HOST', 'http://log.api') . "/multi-log/$pid/". env('LOG_APPLICATION'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_TIMEOUT_MS => env('LOG_REQUEST_TIMEOUT', 50),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query($this->lugs),
        ];
        $curl = curl_init();
        curl_setopt_array($curl, $options);
        try {
            $response = curl_exec($curl);
        } catch (\Throwable $th) {
            app('log')->error("multiLug cURL Exception #:" . $th->getMessage());
        }

        if ($err = curl_error($curl)) {
            app('log')->error("multiLug cURL Error #:" . $err);
            $this->lugs = [];
            return false;
        }

        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) != 200) {
            app('log')->error('multilug Error ', ['pid' => $this->getPID(), 'response' => base64_encode($response ?? ''), 'error' => $err ?? '']);
            $this->lug('lug', "Lug Error, couldn't send lugs.", ['response' => $response, 'lugs' => $this->lugs]);
            $this->lugs = [];
            return false;
        }
        $this->lugs = [];
        return [curl_getinfo($curl, CURLINFO_HTTP_CODE), $response];
    }

    public function httpLug(string $type, string $message, array $data = [])
    {
        if (strlen($message) > 255) {
            $data['message'] = $message;
            $message = "Message is too long, please look at Data";
        }
        $pid = $this->getPID();

        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[3];
        $postFields = ['info' => array_merge([
            'lugVia' => 'Http',
            'project' => env('LOG_SERVICE_NAME', ''),
            'file' =>  $data['file'] ?? $stack['file'] ?? 'no_file',
            'line' => $data['line'] ?? $stack['line'] ?? 'no_line',
            'serverIp' => gethostbyname(gethostname()) ?? '-'
        ], $this->common)];

        foreach ($data as $key => $item) {
            try {
                $postFields['info']['serialize'][$key] = serialize($item);
            } catch (Exception $exception) {
                $postFields['info']['serialize'][$key] = serialize('exception: ' . $exception->getMessage());
            }
        }
        $options = [
            CURLOPT_URL => env('LOG_HOST', 'http://log.api') . sprintf('/log/%s/%s/%s?%s', $pid, $type, microtime(true), http_build_query(['token' => env('LOG_APPLICATION', ''), 'message' => $message])),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_TIMEOUT_MS => env('LOG_REQUEST_TIMEOUT', 50),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query($postFields),
        ];

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        try {
            $response = curl_exec($curl);
        } catch (\Throwable $th) {
            $exceptionMessage = $th->getMessage();
            app('log')->error("singlelug cURL Exception #:" . $exceptionMessage);
        }

        if ($err = curl_error($curl)) {
            app('log')->error("singlelug cURL Error #:" . $err);
            return false;
        }

        if ($response != 'OK' && $type != 'lug') {
            app('log')->error('singlelug Error', ['pid' => $this->getPID(), 'response' => base64_encode($response ?? ''), 'error' => $err ?? '']);
            if (!str_starts_with($exceptionMessage ?? '', 'cURL Error #:Operation timed out after')) {
                $this->lug('lug', "Lug Error, couldn't send lugs.", ['response' => $response, 'postFields' => $postFields]);
            }
            return false;
        }
        return [curl_getinfo($curl, CURLINFO_HTTP_CODE), $response];
    }

    public function socketLug(string $type, string $message, array $data = [])
    {
        if (strlen($message) > 255) {
            $data['message'] = $message;
            $message = "Message is too long, please look at Data";
        }
        $pid = $this->getPID();

        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[3];
        $postFields = [
            'info' => array_merge(
                [
                    'lugVia' => 'Socket',
                    'project' => env('LOG_SERVICE_NAME', ''),
                    'file' =>  $data['file'] ?? $stack['file'] ?? 'no_file',
                    'line' => $data['line'] ?? $stack['line'] ?? 'no_line',
                    'serverIp' => gethostbyname(gethostname()) ?? '-'
                ],
                $this->common
            ),
            'appToken' => env('LOG_APPLICATION', ''),
            'pid' => $pid,
            'time' => microtime(true),
            'message' => $message,
            'type' => $type
        ];
        foreach ($data as $key => $item) {
            try {
                $postFields['info']['serialize'][$key] = serialize($item);
            } catch (Exception $exception) {
                $postFields['info']['serialize'][$key] = serialize('exception: ' . $exception->getMessage());
            }
        }
        if (!$this->socketClient->send($postFields)) {
            $this->httpLug('warning', 'Socket lug failed. message ->> ' . $message, $data);
        }
    }

    public function lug(string $type, string $message, array $data = [], string $preferedSendType = 'socket')
    {
        $sendType = $this->sendType($preferedSendType);
        return $this->{$sendType . 'Lug'}($type, $message, $data);
    }

    protected function sendType($preferedSendType)
    {
        $sendType = $preferedSendType == 'http' ? 'http' : config('lug.sendType', 'http');
        if ($sendType == 'socket') {
            if (empty($this->socketClient) || !$this->socketClient->isConnected) {
                $host =  config('lug.easySocket.host');
                $port =  config('lug.easySocket.port', 0);
                if (!empty($host)) {
                    $this->socketClient = new SocketClient($host, $port);
                }
            }
            if (($this->socketClient->isConnected)) {  ///> checking again bc sometimes even after re-connection the connection is still unavailable
                return 'socket';
            }
        }
        return 'http';
    }
}
