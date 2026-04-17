<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\Printer;
use Mike42\Escpos\GdEscposImage;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;

class ReceiptController extends Controller
{
    private string $fontPath;

    public function __construct()
    {
        $this->fontPath = storage_path('fonts/Hanuman/static/Hanuman-Regular.ttf');
    }

    public function print(Request $request)
    {
        $data = $request->json()->all();

        if (empty($data)) {
            return response()->json(['success' => false, 'message' => 'No data received'], 400);
        }

        try {
            // ✅ USB via CUPS
            $connector = new CupsPrintConnector("Diamond_Printer");
            $printer   = new Printer($connector);

            $this->buildReceipt($printer, $data);

            $printer->feed(5);
            $printer->cut();
            $printer->close();

            return response()->json([
                'success' => true,
                'message' => 'Printed via USB (CUPS)'
            ]);
        } catch (\Exception $e) {
            Log::error('Print error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function buildReceipt(Printer $printer, array $data): void
    {
        $branchName  = $data['branch_name']  ?? 'My Store';
        $branchPhone = $data['branch_phone'] ?? '';
        $orderNumber = $data['order_number'] ?? '-';
        $printedAt   = $data['printed_at']   ?? now()->format('d/m/Y H:i');
        $cashier     = $data['cashier']      ?? '-';
        $subtotal    = number_format(floatval($data['subtotal'] ?? 0), 2);
        $total       = number_format(floatval($data['total']    ?? 0), 2);
        $discount    = floatval($data['discount'] ?? 0);
        $tax         = floatval($data['tax']      ?? 0);
        $cash        = floatval($data['cash_tendered'] ?? 0);
        $change      = floatval($data['change_given']  ?? 0);

        $customerType = ($data['customer_type'] ?? '') === 'wholesale' ? 'Wholesale' : 'Retail';

        $payLabel = [
            'cash'     => 'Cash',
            'card'     => 'Card',
            'qr_code'  => 'QR Code',
            'qr'       => 'QR',
            'online'   => 'Transfer',
            'transfer' => 'Transfer',
        ][$data['payment_method'] ?? ''] ?? ($data['payment_method'] ?? '-');

        $line  = str_repeat('-', 32) . "\n";
        $dline = str_repeat('=', 32) . "\n";

        // ── Header ──────────────────────────────────────
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        if ($this->hasKhmer($branchName)) {
            $this->printKhmer($printer, $branchName);
        } else {
            $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $printer->text($branchName . "\n");
            $printer->selectPrintMode();
        }

        if ($branchPhone) {
            $printer->text("Tel: " . $branchPhone . "\n");
        }

        // ── Order Info ──────────────────────────────────
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text($line);
        $printer->text("Order # : " . $orderNumber . "\n");
        $printer->text("Date    : " . $printedAt . "\n");

        if ($this->hasKhmer($cashier)) {
            $printer->text("Cashier : ");
            $this->printKhmer($printer, $cashier);
        } else {
            $printer->text("Cashier : " . $cashier . "\n");
        }

        $printer->text("Type    : " . $customerType . "\n");
        $printer->text($line);

        // ── Items ───────────────────────────────────────
        foreach ($data['items'] ?? [] as $item) {
            $name       = $item['name']  ?? '';
            $qty        = $item['qty']   ?? 0;
            $unitPrice  = number_format(floatval($item['unit_price']  ?? 0), 2);
            $totalPrice = number_format(floatval($item['total_price'] ?? 0), 2);

            // Render item name — as image if contains Khmer
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            if ($this->hasKhmer($name)) {
                $this->printKhmer($printer, $name);
            } else {
                $printer->text($name . "\n");
            }

            // Qty and price always plain text
            $calc = "  " . $qty . " x $" . $unitPrice;
            $printer->text(
                str_pad($calc, 22) .
                    str_pad("$" . $totalPrice, 10, ' ', STR_PAD_LEFT) . "\n"
            );
        }

        // ── Totals ──────────────────────────────────────
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text($line);
        $printer->text(str_pad("Subtotal:", 22) . str_pad("$" . $subtotal, 10, ' ', STR_PAD_LEFT) . "\n");

        if ($discount > 0) {
            $printer->text(str_pad("Discount:", 22) . str_pad("-$" . number_format($discount, 2), 10, ' ', STR_PAD_LEFT) . "\n");
        }
        if ($tax > 0) {
            $printer->text(str_pad("Tax:", 22) . str_pad("$" . number_format($tax, 2), 10, ' ', STR_PAD_LEFT) . "\n");
        }

        $printer->text($dline);
        $printer->setEmphasis(true);
        $printer->text(str_pad("TOTAL:", 22) . str_pad("$" . $total, 10, ' ', STR_PAD_LEFT) . "\n");
        $printer->setEmphasis(false);
        $printer->text($dline);

        // ── Payment ─────────────────────────────────────
        $printer->text(str_pad("Payment:", 22) . str_pad($payLabel, 10, ' ', STR_PAD_LEFT) . "\n");
        if ($cash > 0) {
            $printer->text(str_pad("Cash:", 22) . str_pad("$" . number_format($cash, 2), 10, ' ', STR_PAD_LEFT) . "\n");
        }
        if ($change > 0) {
            $printer->text(str_pad("Change:", 22) . str_pad("$" . number_format($change, 2), 10, ' ', STR_PAD_LEFT) . "\n");
        }

        // ── Footer ──────────────────────────────────────
        $printer->text($line);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("Thank you for your purchase!\n");
        $this->printKhmer($printer, "អរគុណសម្រាប់ការទិញ!");
    }

    // Check if text contains Khmer characters
    private function hasKhmer(string $text): bool
    {
        return preg_match('/[\x{1780}-\x{17FF}]/u', $text) === 1;
    }

    private function printKhmer(Printer $printer, string $text): void
    {
        try {
            $img = $this->createKhmerImage($text, $this->fontPath);

            $escposImage = new GdEscposImage();
            $escposImage->readImageFromGdResource($img);

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->bitImage($escposImage);

            imagedestroy($img);
        } catch (\Exception $e) {
            Log::error('Khmer print error: ' . $e->getMessage());
            $printer->text("(Khmer error)\n");
        }
    }

    private function createKhmerImage(string $text, string $fontPath)
    {
        $tmpPng = tempnam(sys_get_temp_dir(), 'khmer_') . '.png';
        $script = base_path('render-khmer.cjs');

        shell_exec(
            "node " .
                escapeshellarg($script) . " " .
                escapeshellarg($text) . " " .
                escapeshellarg($tmpPng) . " " .
                escapeshellarg($fontPath) . " 2>&1"
        );

        if (!file_exists($tmpPng)) {
            throw new \Exception('Node.js canvas failed to create Khmer image');
        }

        $img = imagecreatefrompng($tmpPng);
        unlink($tmpPng);
        return $img;
    }
}
