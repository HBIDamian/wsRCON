<?php
declare(strict_types=1);

namespace HBIDamian\wsRCON;

class FrameHandler {
    
    private Main $plugin;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }
    
    public function decodeFrame(string &$data): ?string {
        if (strlen($data) < 2) return null;
        
        $firstByte = ord($data[0]);
        $secondByte = ord($data[1]);
        
        $opcode = $firstByte & 0x0F;
        $masked = ($secondByte >> 7) & 1;
        $payloadLen = $secondByte & 0x7F;
        
        // Only handle text frames
        if ($opcode !== 1) {
            // Remove this frame from buffer even if we don't process it
            $this->plugin->debugLog("Received non-text frame with opcode: " . $opcode);
            $data = '';
            return null;
        }
        
        $offset = 2;
        
        if ($payloadLen === 126) {
            if (strlen($data) < 4) return null;
            $payloadLen = unpack('n', substr($data, $offset, 2))[1];
            $offset += 2;
        } elseif ($payloadLen === 127) {
            if (strlen($data) < 10) return null;
            $payloadLen = unpack('J', substr($data, $offset, 8))[1];
            $offset += 8;
        }
        
        if ($masked) {
            if (strlen($data) < $offset + 4) return null;
            $maskKey = substr($data, $offset, 4);
            $offset += 4;
        }
        
        if (strlen($data) < $offset + $payloadLen) return null;
        
        $payload = substr($data, $offset, $payloadLen);
        
        if ($masked) {
            for ($i = 0; $i < $payloadLen; $i++) {
                $payload[$i] = chr(ord($payload[$i]) ^ ord($maskKey[$i % 4]));
            }
        }
        
        // Remove processed frame from buffer
        $data = substr($data, $offset + $payloadLen);
        
        $this->plugin->debugLog("Decoded WebSocket frame: " . substr($payload, 0, 100));
        
        return $payload;
    }
    
    public function encodeFrame(string $message): string {
        $length = strlen($message);
        $frame = chr(0x81); // Text frame with FIN bit
        
        if ($length < 126) {
            $frame .= chr($length);
        } elseif ($length < 65536) {
            $frame .= chr(126) . pack('n', $length);
        } else {
            $frame .= chr(127) . pack('J', $length);
        }
        
        return $frame . $message;
    }
    
    public function sendMessage(mixed $socket, string $message): void {
        if (!SocketUtils::isValidSocket($socket)) {
            $this->plugin->debugLog("Invalid socket type: " . gettype($socket));
            return;
        }
        
        $frame = $this->encodeFrame($message);
        $result = @socket_write($socket, $frame);
        
        if ($result === false) {
            $this->plugin->debugLog("Failed to write WebSocket frame to socket");
        } else {
            $this->plugin->debugLog("Successfully sent {$result} bytes to socket");
        }
    }
    
    public function sendRawMessage(mixed $socket, string $message): void {
        if (!SocketUtils::isValidSocket($socket)) {
            return;
        }
        @socket_write($socket, $message);
    }
}
