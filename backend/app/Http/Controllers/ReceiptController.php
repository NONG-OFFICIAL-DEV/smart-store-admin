<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    private string $printerIp   = '192.168.1.114'; // <-- change this
    private int    $printerPort = 9100;

    // GET /test-print  →  prints a test receipt
    public function index()
    {
        $ESC = "\x1b";
        $GS  = "\x1d";

        $r  = $ESC . "\x40";

        $r .= $ESC . "\x61\x01";
        $r .= $ESC . "\x45\x01";
        $r .= $ESC . "\x21\x30";
        $r .= "MY STORE\n";
        $r .= $ESC . "\x21\x00";
        $r .= $ESC . "\x45\x00";
        $r .= "--------------------------------\n";

        $r .= $ESC . "\x61\x00";
        $r .= "Order  : #0001\n";
        $r .= "Date   : " . now()->format('d/m/Y H:i') . "\n";
        $r .= "--------------------------------\n";

        $r .= str_pad("Americano x2",  22) . str_pad("$5.00",  10, ' ', STR_PAD_LEFT) . "\n";
        $r .= str_pad("Croissant x1",  22) . str_pad("$2.50",  10, ' ', STR_PAD_LEFT) . "\n";
        $r .= str_pad("Green Tea x1",  22) . str_pad("$2.50",  10, ' ', STR_PAD_LEFT) . "\n";
        $r .= "--------------------------------\n";
        $r .= str_pad("Subtotal:",     22) . str_pad("$10.00", 10, ' ', STR_PAD_LEFT) . "\n";
        $r .= str_pad("Tax (10%):",    22) . str_pad("$1.00",  10, ' ', STR_PAD_LEFT) . "\n";
        $r .= "--------------------------------\n";
        $r .= $ESC . "\x45\x01";
        $r .= str_pad("TOTAL:",        22) . str_pad("$11.00", 10, ' ', STR_PAD_LEFT) . "\n";
        $r .= $ESC . "\x45\x00";
        $r .= $ESC . "\x61\x01";
        $r .= "\nThank you!\n";
        $r .= "Please come again\n";
        $r .= "\n\n\n";
        $r .= $GS  . "\x56\x00";

        $result = $this->sendToPrinter($r);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    private function sendToPrinter(string $data): array
    {
        $errno  = 0;
        $errstr = '';

        // 3 second connection timeout — won't hang PHP
        $fp = fsockopen($this->printerIp, $this->printerPort, $errno, $errstr, 3);

        if (!$fp) {
            return ['success' => false, 'message' => "Printer error ($errno): $errstr"];
        }

        fwrite($fp, $data);
        fclose($fp);

        return ['success' => true, 'message' => 'Printed successfully'];
    }
}
