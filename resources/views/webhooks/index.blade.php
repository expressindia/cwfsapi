<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Webhook Events</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6f8;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        h1 {
            margin-bottom: 25px;
        }

        .card {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f9fafb;
            font-weight: 600;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            background: #e5e7eb;
        }

        .empty {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Webhook Events</h1>

    <div class="card">

        @if($events->count())

            <table>

                <thead>
                    <tr>
                        <th>Event ID</th>
                        <th>Event Type</th>
                        <th>Fullscript Order</th>
                        <th>Shipment</th>
                        <th>Tracking</th>
                        <th>Status</th>
                        <th>Received</th>
                    </tr>
                </thead>

                <tbody>

                @foreach($events as $event)

                    <tr>

                        <td>
                            {{ $event->fullscript_event_id ?? '-' }}
                        </td>

                        <td>
                            <span class="badge">
                                {{ $event->tracking_data['event_type'] ?? 'fulfillment.shipment.shipped' }}
                            </span>
                        </td>

                        <td>
                            {{ $event->fullscript_order_id ?? '-' }}
                        </td>

                        <td>
                            {{ $event->tracking_data['shipment_number'] ?? '-' }}
                        </td>

                        <td>
                            <strong>
                                {{ $event->tracking_data['tracking_number'] ?? '-' }}
                            </strong>

                            @if(!empty($event->tracking_data['carrier']))
                                <br>
                                {{ $event->tracking_data['carrier'] }}
                            @endif
                        </td>

                        <td>
                            {{ $event->status ?? '-' }}
                        </td>

                        <td>
                            {{ $event->created_at?->format('Y-m-d H:i:s') ?? '-' }}
                        </td>

                    </tr>

                @endforeach

                </tbody>

            </table>

        @else

            <div class="empty">
                No webhook events found.
            </div>

        @endif

    </div>

</div>

</body>
</html>