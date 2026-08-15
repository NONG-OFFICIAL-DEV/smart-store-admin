<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>

    <style>
        @page {
            size: 80mm 200mm;
            margin: 0;
        }

        /* 1. Ensure the font path is absolute and reachable */
        @font-face {
            font-family: 'KhmerFont';
            src: url('{{ public_path("fonts/NotoSansKhmer-Regular.ttf") }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        /* 2. Apply font to everything and handle line-height */
        body {
            font-family: 'KhmerFont', sans-serif;
            font-size: 12px;
            width: 70mm;
            margin: 0 auto;
            padding: 5mm;
            color: #000;
            line-height: 1.5; /* Khmer script needs more vertical space */
        }

        /* 3. Fix: Bold fonts often fail if you don't have a specific -Bold.ttf file */
        /* If your Khmer font looks like squares when bolded, remove 'bold' or use a Bold font file */
        .bold {
            font-weight: bold;
        }

        .center { text-align: center; }
        .text-right { text-align: right; }

        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .item-table th {
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
        }

        .logo {
            width: 50px;
            margin-bottom: 5px;
        }

        .footer {
            font-size: 10px;
            margin-top: 15px;
        }
    </style>
</head>

<body>
    <div class="center">
        <img src="https://i.pinimg.com/736x/fd/9c/fc/fd9cfc4eb0b0498a45b73343f1aa04ed.jpg" class="logo"><br>
        <span class="bold" style="font-size: 16px;">មាតា ភីអូអេស (MY POS)</span><br> <span>Street 123, Phnom Penh</span><br>
        <span>Tel: 012 345 678</span>
        
    </div>

    <script>
        window.onload = function() {
            // Only trigger print if not in a PDF generation context
            if (!window.isPdf) {
                window.print();
            }
        }
    </script>
</body>
</html>
