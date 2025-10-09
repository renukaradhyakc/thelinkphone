<!DOCTYPE HTML>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <link rel="icon" href="{{ asset(getSettingData()['favicon']) }}" type="image/png">
    <title>Schedule Appointment Invoice</title>
    <!-- Fonts -->
    <!-- General CSS Files -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
          integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <style>
        * {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Lato", DejaVu Sans, sans-serif;
            padding: 30px;
            font-size: 14px;
        }
    </style>
</head>
<body>
<table class="mb-5" width="100%">
    <tr>
        <td class="text-left">
            <div><img width="100px" src="{{ getSettingData()['logo'] }}" alt=""
                      class="object-cover"></div>
        </td>
        <td class="text-right">
            <div style="font-size: 50px">Invoice</div>
            <div>
                <b>Date</b>: {{ \Carbon\Carbon::parse($scheduleEvent->transaction->created_at)->translatedFormat('F j, Y') }}
            </div>
        </td>
    </tr>
</table>
<table class="mb-2" width="100%">
    <thead>
    <tr>
        <td>
            <div><b>User Info:</b></div>
            <div>{{ getLogInUser()->full_name }}</div>
            <div>{{ getLogInUser()->email }}</div>
        </td>
    </tr>
    </thead>
</table>
<hr>
<table class="mb-5" width="100%">
    <thead>
    <tr>
        <td class="text-left">
            <div><b>Customer Info:</b></div>
            <div>{{ $scheduleEvent->name }}</div>
            <div>{{ $scheduleEvent->email }}</div>
        </td>
        <td class="text-right">
            <div><b>Scheduled Info:</b></div>
            <div>
                <b>Date:</b> {{ \Carbon\Carbon::parse($scheduleEvent->schedule_date)->translatedFormat('F j, Y').', '.\Carbon\Carbon::parse($scheduleEvent->schedule_date)->translatedFormat('l') }}
            </div>
            <div><b>Time:</b> {{ $scheduleEvent->slot_time }}</div>
        </td>
    </tr>
    </thead>
</table>
<table class="w-100">
    <tr>
        <td colspan="2">
            <table class="table table-bordered w-100">
                <thead>
                <tr>
                    <th style="word-break: break-all;width: 50%"><b>Event</b></th>
                    <th style="word-break: break-all;width: 10%;text-align: right"><b>Quantity</b></th>
                    <th style="word-break: break-all;width: 20%;text-align: right"><b>Amount</b></th>
                    <th style="word-break: break-all;width: 20%;text-align: right"><b>Total</b></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>{{ $scheduleEvent->event->name }}</td>
                    <td class="text-right">1</td>
                    <td class="text-right">{{ getCurrencyIcon() }} {{ number_format($scheduleEvent->transaction->amount, 2) }}</td>
                    <td class="text-right">{{ getCurrencyIcon() }} {{ number_format($scheduleEvent->transaction->amount, 2) }}</td>
                </tr>
                </tbody>
            </table>
        </td>
    </tr>
    <tr>
        <td></td>
        <td>
            <table style="float: right !important;">
                <tbody style="font-size: 15px !important;">
                <tr>
                    <td style="border-bottom: 1px solid;">Sub Total:&nbsp;</td>
                    <td style="border-bottom: 1px solid black">
                        {{ getCurrencyIcon() }} {{ number_format($scheduleEvent->transaction->amount, 2) }}
                    </td>
                </tr>
                <tr>
                    <td style="border-bottom: 1px solid;">Total:&nbsp;</td>
                    <td style="border-bottom: 1px solid;">
                        {{ getCurrencyIcon() }} {{ number_format($scheduleEvent->transaction->amount, 2) }}
                    </td>
                </tr>
                </tbody>
            </table>
        </td>
    </tr>
    <tr>
        <td colspan="2" style="padding-top: 100px !important;">
            <br><strong>Regards:</strong>
            <br><span>{{ getSettingData()['application_name'] }}</span>
        </td>
    </tr>
</table>
</body>
</html>
