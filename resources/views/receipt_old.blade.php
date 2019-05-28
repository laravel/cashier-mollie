<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <title>Invoice</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background: #fff;
            background-image: none;
            font-size: 12px;
        }
        address{
            margin-top:15px;
        }
        h2 {
            font-size:28px;
            color:#cccccc;
        }
        .container {
            padding-top:30px;
        }
        .invoice-head td {
            padding: 0 8px;
        }
        {{--
        .invoice-body{
            background-color:transparent;
        }
        .logo {
            padding-bottom: 10px;
        }
        --}}
        .table th {
            vertical-align: bottom;
            font-weight: bold;
            padding: 8px;
            line-height: 20px;
            text-align: left;
        }
        .table td {
            padding: 8px;
            line-height: 20px;
            text-align: left;
            vertical-align: top;
            border-top: 1px solid #dddddd;
        }
        .well {
            margin-top: 15px;
        }
    </style>
</head>

<body>
    <div class="container">
        <table style="margin-left: auto; margin-right: auto" width="550">
            <tr>
                <td width="160">
                    &nbsp;
                </td>

                <!-- Organization Name / Image -->
                <td align="right">
                    {{--<strong>{{ $header ?? $vendor }}</strong>--}}
                </td>
            </tr>
            <tr valign="top">
                <td style="font-size:28px;color:#cccccc;">
                    Receipt
                </td>

                <!-- Organization Name / Date -->
                <td>
                    <br><br>
                    <strong>To:</strong>
                    @foreach($invoice->receiverAddress() as $line)
                        {{ $line }}
                        <br>
                    @endforeach
                    <br>
                    <strong>Date:</strong> {{ $invoice->date()->toFormattedDateString() }}
                </td>
            </tr>
            <tr valign="top">
                <!-- Organization Details -->
                <td style="font-size:9px;">
                    {{--{{ $vendor }}<br>--}}
                    @if (isset($street))
                        {{ $street }}<br>
                    @endif
                    @if (isset($location))
                        {{ $location }}<br>
                    @endif
                    @if (isset($phone))
                        <strong>T</strong> {{ $phone }}<br>
                    @endif
                    @if (isset($vendorVat))
                        {{ $vendorVat }}<br>
                    @endif
                    @if (isset($url))
                        <a href="{{ $url }}">{{ $url }}</a>
                    @endif
                </td>
                <td>
                    <!-- Invoice Info -->
                    <p>
                        {{--<strong>Product:</strong> {{ $product }}<br>--}}
                        <strong>Invoice Number:</strong> {{ $invoice->id() }}<br>
                    </p>

                    @if($invoice->hasStartingBalance())
                    <p>
                        Starting balance: {{ $invoice->startingBalance() }}
                    </p>
                    @endif

                    <!-- Extra / VAT Information -->
                    @if (isset($vat))
                        <p>
                            {{ $vat }}
                        </p>
                    @endif

                    <table width="100%" class="table" border="0">
                        <tr>
                            <th align="left">Description</th>
                            <th align="right" style="text-align: right;">Amount</th>
                            <th align="right">VAT %</th>
                        </tr>

                        <!-- Display The Invoice Items -->
                        @foreach ($invoice->items() as $item)
                            <tr>
                                <td>
                                    {{ $item->description }}
                                    @if($item->quantity > 1)
                                        <br>{{ $item->quantity }} x {{ Laravel\Cashier\Cashier::formatAmount($item->getUnitPrice()) }}
                                    @endif
                                </td>
                                <td style="text-align: right;">
                                    {{ Laravel\Cashier\Cashier::formatAmount($item->getSubtotal()) }}
                                </td>
                                <td>
                                    {{ $item->getTaxPercentage() }}%
                                </td>
                            </tr>
                        @endforeach

                        <!-- Display The Subtotal -->
                        <tr style="border-top:2px solid #000;">
                            <td style="text-align: right;"><strong>Subtotal</strong></td>
                            <td style="text-align: right;"><strong>{{ $invoice->subtotal() }}</strong></td>
                            <td>&nbsp;</td>
                        </tr>

                        <!-- Display The Tax Details -->
                        @foreach( $invoice->taxDetails() as $taxDetail )
                        <tr style="border-top:2px solid #000;">
                            <td style="text-align: right;">{{ $taxDetail['tax_percentage'] }}% VAT over {{ $taxDetail['over_subtotal'] }}</td>
                            <td style="text-align: right;">{{ $taxDetail['total'] }}</td>
                            <td>&nbsp;</td>
                        </tr>
                        @endforeach

                        <!-- Display The Final Total -->
                        <tr style="border-top:2px solid #000;">
                            <td style="text-align: right;"><strong>Total</strong></td>
                            <td style="text-align: right;"><strong>{{ $invoice->total() }}</strong></td>
                            <td>&nbsp;</td>
                        </tr>

                        @if($invoice->hasStartingBalance())
                        <!-- Display The Used Balance -->
                        <tr style="border-top:2px solid #000;">
                            <td style="text-align: right;">Balance applied</td>
                            <td style="text-align: right;">{{ $invoice->usedBalance() }}</td>
                            <td>&nbsp;</td>
                        </tr>

                        <!-- Display The Total Due -->
                        <tr style="border-top:2px solid #000;">
                            <td style="text-align: right;"><strong>Total due</strong></td>
                            <td style="text-align: right;"><strong>{{ $invoice->totalDue() }}</strong></td>
                            <td>&nbsp;</td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
            <tr>
                <td> </td>
                <td style="border-top:none">
                    New account balance: {{ $invoice->completedBalance() }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
