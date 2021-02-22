<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
@include('cashier::head')
<body style="box-sizing:border-box;margin:0;padding:0;width:100%;word-break:break-word;-webkit-font-smoothing:antialiased">
<div>
    <table class="all-font-sans" width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center" style="vertical-align:middle" bgcolor="#edf2f7" valign="middle"> <table class="sm-w-full" width="600" cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td> &nbsp;</td>
                    </tr>
                    <tr>
                        <td align="center" class="sm-px-16">
                            <table style="padding-left:16px;padding-right:16px;box-shadow:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -2px rgba(0,0,0,.05)" width="100%" bgcolor="#ffffff" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td>
                                        <table style="text-align:center" width="100%" align="center" cellpadding="0" cellspacing="0" role="presentation">
                                            <tr>
                                                <td style="font-weight:300;padding-bottom:32px;padding-top:64px;font-size:48px">
                                                    Your receipt
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-top:8px;padding-bottom:8px;color:#718096">
                                                    {{ $invoice->date()->toFormattedDateString() }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-top:8px;padding-bottom:40px;color:#718096">
                                                    #&nbsp;{{ $invoice->id() }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top:24px">
                                        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                            <tr>
                                                <td>
                                                    <table width="100%" cellpadding="0" cellspacing="0" role="presentation">

                                                        @foreach($invoice->items() as $item)
                                                        <tr>
                                                            <td colspan="2" style="padding-bottom:16px;vertical-align:top" width="100%" valign="top">
                                                                {{ $item->description }}
                                                                <span style="display:block;padding-top:12px;padding-left:14px;color:#718096">
                                                                    @if($item->quantity > 1)
                                                                        {{ $item->quantity }} x {{ Laravel\Cashier\Cashier::formatAmount($item->getUnitPrice()) }}
                                                                    @endif
                                                                </span>
                                                            </td>
                                                            <td style="padding-bottom:16px;padding-left:20px;text-align:right;vertical-align:top;white-space:nowrap" align="right" valign="top">
                                                                {{ Laravel\Cashier\Cashier::formatAmount($item->getSubtotal()) }}
                                                            </td>
                                                            <td style="padding-bottom:16px;padding-left:16px;padding-right:16px;text-align:right;color:#a0aec0;vertical-align:top;white-space:nowrap" align="right" valign="top">
                                                                {{ $item->getTaxPercentage() }}%
                                                            </td>
                                                        </tr>
                                                        @endforeach

                                                        <tr>
                                                            <td colspan="2"></td>
                                                            <td style="padding-bottom:16px;padding-left:20px">
                                                                <div style="background-color:#e2e8f0;height:3px;line-height:1px">&nbsp;</div>
                                                            </td>
                                                            <td></td>
                                                        </tr>
                                                        <tr style="font-weight:700">
                                                            <td width="100%"></td>
                                                            <td style="padding-bottom:16px;text-align:right;vertical-align:top;white-space:nowrap" align="right" valign="top">
                                                                Subtotal
                                                            </td>
                                                            <td style="padding-bottom:16px;padding-left:20px;text-align:right;vertical-align:top;white-space:nowrap" align="right" valign="top">
                                                                {{ $invoice->subtotal() }}
                                                            </td>
                                                            <td></td>
                                                        </tr>

                                                        @foreach( $invoice->taxDetails() as $taxDetail )
                                                            @unless( $taxDetail['tax_percentage'] == 0 )
                                                            <tr>
                                                                <td width="100%"></td>
                                                                <td style="padding-bottom:16px;text-align:right;vertical-align:top;white-space:nowrap" align="right" valign="top">
                                                                    {{ $taxDetail['tax_percentage'] }}% VAT
                                                                </td>
                                                                <td style="padding-bottom:16px;padding-left:20px;text-align:right;vertical-align:top;white-space:nowrap" align="right" valign="top">
                                                                    {{ $taxDetail['total'] }}
                                                                </td>
                                                                <td></td>
                                                            </tr>
                                                            @endunless
                                                        @endforeach

                                                        @if( $invoice->hasStartingBalance() )
                                                            <tr>
                                                                <td width="100%"></td>
                                                                <td style="padding-bottom:16px;text-align:right;vertical-align:top;white-space:nowrap" align="right" valign="top">
                                                                    Balance applied
                                                                </td>
                                                                <td style="padding-bottom:16px;padding-left:20px;text-align:right;vertical-align:top;white-space:nowrap" align="right" valign="top">
                                                                    {{ $invoice->usedBalance() }}
                                                                </td>
                                                                <td></td>
                                                            </tr>
                                                        @endif

                                                        <tr>
                                                            <td width="100%"></td>
                                                            <td colspan="2" style="padding-bottom:16px">
                                                                <div style="background-color:#e2e8f0;height:3px">&nbsp;</div>
                                                            </td>
                                                            <td></td>
                                                        </tr>

                                                        <tr style="font-weight:700">
                                                            <td width="100%"></td>
                                                            <td style="padding-bottom:16px;vertical-align:top;white-space:nowrap" valign="top">
                                                                Total due
                                                            </td>
                                                            <td style="padding-bottom:16px;padding-left:20px;text-align:right;vertical-align:top;white-space:nowrap" align="right" valign="top">
                                                                {{ $invoice->totalDue() }}
                                                            </td>
                                                            <td></td>
                                                        </tr>
                                                        <tr>
                                                            <td width="100%"></td>
                                                            <td colspan="2" style="padding-bottom:16px">
                                                                <div style="background-color:#e2e8f0;height:3px">&nbsp;</div>
                                                            </td>
                                                            <td></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                                            <tr>
                                                <td style="padding-bottom:14px;padding-top:24px;font-size:30px">
                                                    Your details
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-top:12px;padding-bottom:16px">
                                                    @foreach($invoice->receiverAddress() as $line)
                                                    {{ $line }}<br>
                                                    @endforeach
                                                </td>
                                            </tr>

                                            @if($invoice->hasStartingBalance())
                                            <tr>
                                                <td style="padding-top:16px;padding-bottom:16px">
                                                    Balance before: {{ $invoice->startingBalance() }}
                                                    Balance after: {{ $invoice->completedBalance() }}
                                                </td>
                                            </tr>
                                            @endif

                                            @if($invoice->extraInformation()->isNotEmpty())
                                            <tr>
                                                <td style="padding-top:16px;padding-bottom:16px">
                                                    @foreach($invoice->extraInformation() as $line)
                                                        {{ $line }}<br>
                                                    @endforeach
                                                </td>
                                            </tr>
                                            @endif

                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <table style="padding-top:24px;padding-bottom:24px;text-align:center" width="100%" align="center" cellpadding="0" cellspacing="0" role="presentation">
                                            <tr>
                                                <td style="padding-bottom:0;padding-top:8px;color:#718096">
                                                    <a href="{{ config('app.url') }}" target="_blank" style="color:#718096;text-decoration:none">
                                                        {{ config('app.name') }}
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr><td>&nbsp;</td></tr>
                </table>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
