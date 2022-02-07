<?php
function lug($type, $message, $data = [], string $sendType = 'socket')
{
	return app('log-system')->lug($type, $message, $data, $sendType);
}

function multilug($type, $message, $data = [])
{
	return app('log-system')->multilug($type, $message, $data);
}

function lugDebug($message, $data = [])
{
	if (strpos(config('lug.activeTypes', 'error,dd,dump'), 'debug') === false) {
		return;
	}
	$stack = [];
	$fullStack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	foreach ($fullStack as $item) {
		$file = $item['file'] ?? 'no_file';
		$line = $item['line'] ?? 'no_line';
		$stack[] = "$file:$line";
	}
	$data['stack'] = $stack;
	return lug('error', $message, $data);
}


function lugHttp($message = 'Messageless', $data = [])
{
	return lug('info', $message, $data, 'http');
}

function lugError($message, $data = [])
{
	if (strpos(config('lug.activeTypes', 'error,dd,dump'), 'error') === false) {
		return;
	}
	return lug('error', $message, $data);
}

function lugInfo($message, $data = [])
{
	if (strpos(config('lug.activeTypes', 'error,dd,dump'), 'info') === false) {
		return;
	}
	return lug('info', $message, $data);
}

function lugDump(...$data)
{
	if (strpos(config('lug.activeTypes', 'error,dd,dump'), 'dump') === false) {
		return;
	}
	return Lug('dd', $data['message'] ?? 'No message Dump', $data);
}

function lugDd(...$data)
{
	if (strpos(config('lug.activeTypes', 'error,dd,dump'), 'dd') === false) {
		return;
	}
	lug('dd', $data['message'] ?? 'No message Die & Dump', $data);
	die;
}

function lugWSResError($message, $data = [])
{
	if (strpos(config('lug.activeTypes', 'error,dd,dump'), 'WSResError') === false) {
		return;
	}
	return lug('WSResError', $message, $data);
}

function lugWarning($message, $data = [])
{
	if (strpos(config('lug.activeTypes', 'error,dd,dump'), 'warning') === false) {
		return;
	}
	return lug('warning', $message, $data);
}
