<?php

namespace MatinUtils\LogSystem;

use Carbon\Carbon;
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
        $pid = str_replace('{', '', $pid);
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
            CURLOPT_URL => env('LOG_HOST', 'http://log.api') . "/multi-log/$pid/" . env('LOG_APPLICATION'),
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

    public function httpLug(string $type, string $message, array $data = [], $forceTime = null)
    {
        if (strlen($message) > 255) {
            $data['message'] = $message;
            $message = "Message is too long, please look at Data";
        }
        $time = empty($forceTime) ? microtime(true) : $forceTime;
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
            CURLOPT_URL => $url = env('LOG_HOST', 'http://log.api') . sprintf('/log/%s/%s/%s/%s?%s', $pid, $type, env('LOG_APPLICATION', ''), $time, http_build_query(['message' => $message])),
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
            $this->saveInfile($type, $message, $data);
            return false;
        }

        if ($err = curl_error($curl)) {
            $this->saveInfile($type, $message, $data);
            app('log')->error("singlelug cURL Error #:" . $err);
            return false;
        }

        if ($response != 'OK' && $type != 'lug') {
            $this->saveInfile($type, $message, $data);
            return false;
        }
        return [curl_getinfo($curl, CURLINFO_HTTP_CODE), $response];
    }

    public function socketLug(string $type, string $message, array $data = [], $forceTime = null)
    {
        if (strlen($message) > 255) {
            $data['message'] = $message;
            $message = "Message is too long, please look at Data";
        }
        $time = empty($forceTime) ? microtime(true) : $forceTime;
        $pid = $this->getPID();

        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[3];
        $postFields = [
            'info' => array_merge(
                [
                    'lugVia' => 'Socket',
                    'lugSockConfs' => config('lug.easySocket.host') . '-' . config('lug.easySocket.port', 0),
                    'project' => env('LOG_SERVICE_NAME', ''),
                    'file' =>  $data['file'] ?? $stack['file'] ?? 'no_file',
                    'line' => $data['line'] ?? $stack['line'] ?? 'no_line',
                    'serverIp' => gethostbyname(gethostname()) ?? '-'
                ],
                $this->common
            ),
            'appToken' => env('LOG_APPLICATION', ''),
            'pid' => $pid,
            'time' => $time,
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
        if (empty($this->socketClient) || !$this->socketClient->isConnected) {
            $host =  config('lug.easySocket.host');
            $port =  config('lug.easySocket.port', 0);
            if (!empty($host)) {
                $this->socketClient = new SocketClient($host, $port);
            }
        }
        if (($this->socketClient->isConnected)) {  ///> checking again bc sometimes even after re-connection the connection is still unavailable
            if (!$this->socketClient->send($postFields)) {
                $this->saveInfile($type, $message, $data);
            }
        } else {
            $this->saveInfile($type, $message, $data);
        }
    }

    public function saveInfile($type, $message, $data)
    {
        try {
            $pid = $this->getPID();
            $date = Carbon::now()->format('y-m-d');
            $filepath = storage_path("logs/misseLugs/$date");
            if (!file_exists($filepath)) {
                mkdir($filepath, 0777, true);
            }
            file_put_contents("$filepath/$pid:" . uniqid(), json_encode(['time' => microtime(true), 'type' => $type, 'message' => $message, 'data' => $data]));
        } catch (\Throwable $th) {
            app('log')->error("Could not save missed lugd. pid $pid . $message");
            app('log')->error($th->getMessage());
        }
    }

    public function lug(string $type, string $message, array $data = [], $forceTime = null, string $preferedSendType = 'socket')
    {
        $sendType = $preferedSendType == 'http' ? 'http' : config('lug.sendType', 'http');
        return $this->{$sendType . 'Lug'}($type, $message, $data, $forceTime);
    }

    public function closeSocket()
    {
        if (($this->socketClient->isConnected)) {  ///> checking again bc sometimes even after re-connection the connection is still unavailable
            return $this->socketClient->closeSocket();
        }
    }
}
