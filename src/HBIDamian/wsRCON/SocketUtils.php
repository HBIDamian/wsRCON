<?php
declare(strict_types=1);

namespace HBIDamian\wsRCON;

class SocketUtils {
    
    public static function isValidSocket(mixed $socket): bool {
        return is_resource($socket) || (is_object($socket) && get_class($socket) === 'Socket');
    }
    
    public static function getSocketPeerName(mixed $socket): string {
        $address = '';
        $port = 0;
        @socket_getpeername($socket, $address, $port);
        return $address . ':' . $port;
    }
    
    public static function getSocketType(mixed $socket): string {
        return is_resource($socket) ? 'resource' : gettype($socket);
    }
}
